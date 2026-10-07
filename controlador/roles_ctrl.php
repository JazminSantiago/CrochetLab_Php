<?php
// controlador/roles_ctrl.php
// Roles y permisos dinámicos: crear roles, asignar permisos (matriz área × acción) y eliminar roles.
//
// Protecciones:
//   - el rol Administrador es intocable (siempre tiene todos los permisos);
//   - los roles base (es_sistema) no se pueden eliminar;
//   - un rol con usuarios asignados no se puede eliminar;
//   - los permisos de administración (usuarios.escritura, roles.escritura, roles.eliminacion)
//     solo los puede conceder o quitar un superadministrador;
//   - nadie puede quitar a su propio rol el acceso a esta pantalla.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/../modelo/admin_util.php';

class RolesCtrl
{
    private $db;

    public function __construct()
    {
        $this->db = (new Conexion())->conectar();
    }

    public function db(): PDO
    {
        return $this->db;
    }

    public function obtener($id)
    {
        if (!ctype_digit((string)$id)) {
            return false;
        }
        $st = $this->db->prepare("SELECT id, nombre, descripcion, es_sistema FROM roles WHERE id = :id");
        $st->execute([':id' => (int)$id]);
        return $st->fetch();
    }

    // Todos los permisos existentes, ordenados por área y por acción
    public function permisosDisponibles(): array
    {
        return $this->db->query(
            "SELECT id, area, accion, descripcion FROM permisos
             ORDER BY area, CASE accion WHEN 'lectura' THEN 1 WHEN 'escritura' THEN 2 ELSE 3 END"
        )->fetchAll();
    }

    // Ids de los permisos que tiene un rol
    public function permisosDeRol(int $rolId): array
    {
        $st = $this->db->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id = :r");
        $st->execute([':r' => $rolId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    private function fallo(string $mensaje): array
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        return ['success' => false, 'mensaje' => $mensaje];
    }

    public function crear(bool $actorSuper, $nombre, $descripcion, $copiarDe): array
    {
        $nombre      = is_string($nombre) ? trim(preg_replace('/\s+/u', ' ', $nombre)) : '';
        $descripcion = is_string($descripcion) ? trim($descripcion) : '';

        if (!preg_match('/^[\p{L}\p{N} _.\-]{3,50}$/u', $nombre)) {
            return ['success' => false, 'mensaje' => 'El nombre debe tener de 3 a 50 caracteres (letras, números, espacios, punto, guion).'];
        }
        if (mb_strlen($descripcion) > 200) {
            return ['success' => false, 'mensaje' => 'La descripción no puede superar 200 caracteres.'];
        }

        try {
            $this->db->beginTransaction();

            $st = $this->db->prepare("SELECT 1 FROM roles WHERE LOWER(nombre) = LOWER(:n)");
            $st->execute([':n' => $nombre]);
            if ($st->fetch()) {
                return $this->fallo('Ya existe un rol con ese nombre.');
            }

            $st = $this->db->prepare(
                "INSERT INTO roles (nombre, descripcion, es_sistema) VALUES (:n, :d, FALSE) RETURNING id"
            );
            $st->execute([':n' => $nombre, ':d' => $descripcion !== '' ? $descripcion : null]);
            $nuevoId = (int)$st->fetchColumn();

            // Opcional: partir de los permisos de otro rol (sin los de administración si no es superadmin)
            $copiados = 0;
            if (ctype_digit((string)$copiarDe) && (int)$copiarDe > 0) {
                if (!$this->obtener($copiarDe)) {
                    return $this->fallo('El rol a copiar no existe.');
                }
                $filtro = $actorSuper ? '' : ' AND NOT ' . SQL_PERMISO_PRIVILEGIADO;
                $st = $this->db->prepare(
                    "INSERT INTO rol_permisos (rol_id, permiso_id)
                     SELECT CAST(:nuevo AS integer), rp.permiso_id
                     FROM rol_permisos rp JOIN permisos p ON p.id = rp.permiso_id
                     WHERE rp.rol_id = :origen" . $filtro
                );
                $st->execute([':nuevo' => $nuevoId, ':origen' => (int)$copiarDe]);
                $copiados = $st->rowCount();
            }

            $this->db->commit();
            return ['success' => true, 'id' => $nuevoId, 'nuevo_rol' => $nuevoId,
                    'detalle' => $nombre . ($copiados ? " ($copiados permisos copiados)" : ' (sin permisos)'),
                    'mensaje' => 'Rol «' . $nombre . '» creado. Ahora asígnale sus permisos.'];
        } catch (Throwable $e) {
            error_log('crear rol: ' . $e->getMessage());
            return $this->fallo('No se pudo crear el rol.');
        }
    }

    // $marcados: ids de permisos recibidos del formulario
    public function guardarPermisos(int $actorId, bool $actorSuper, $rolId, $marcados): array
    {
        $rol = $this->obtener($rolId);
        if (!$rol) {
            return ['success' => false, 'mensaje' => 'El rol no existe.'];
        }
        if ($rol['nombre'] === NOMBRE_ROL_ADMIN) {
            return ['success' => false, 'mensaje' => 'Los permisos del Administrador no se pueden modificar.'];
        }
        $rolId = (int)$rol['id'];

        // Normaliza lo recibido: solo enteros positivos únicos
        $nuevos = [];
        if (is_array($marcados)) {
            foreach ($marcados as $v) {
                if (is_string($v) && ctype_digit($v) && (int)$v > 0) {
                    $nuevos[(int)$v] = true;
                }
            }
        }
        $nuevos = array_keys($nuevos);

        try {
            $this->db->beginTransaction();

            // Los ids deben existir
            $todos = [];
            foreach ($this->permisosDisponibles() as $p) {
                $todos[(int)$p['id']] = $p['area'] . '.' . $p['accion'];
            }
            foreach ($nuevos as $id) {
                if (!isset($todos[$id])) {
                    return $this->fallo('Se recibió un permiso que no existe.');
                }
            }

            $actuales   = $this->permisosDeRol($rolId);
            $privil     = idsPermisosPrivilegiados($this->db);

            // Sin ser superadmin los permisos de administración no se pueden tocar: se conservan tal cual
            if (!$actorSuper) {
                $nuevos = array_values(array_unique(array_merge(
                    array_diff($nuevos, $privil),
                    array_intersect($actuales, $privil)
                )));
            }

            // No quitarse a uno mismo el acceso a esta pantalla
            $st = $this->db->prepare("SELECT rol_id FROM usuarios WHERE id = :id");
            $st->execute([':id' => $actorId]);
            if ((int)$st->fetchColumn() === $rolId) {
                $necesarios = array_keys(array_filter($todos, fn($n) => in_array($n, ['roles.lectura', 'roles.escritura'], true)));
                foreach ($necesarios as $id) {
                    if (!in_array($id, $nuevos, true)) {
                        return $this->fallo('No puedes quitarle a tu propio rol el acceso a «Roles y permisos».');
                    }
                }
            }

            $agregados = array_diff($nuevos, $actuales);
            $quitados  = array_diff($actuales, $nuevos);
            if (!$agregados && !$quitados) {
                return $this->fallo('No hubo cambios en los permisos.');
            }

            $this->db->prepare("DELETE FROM rol_permisos WHERE rol_id = :r")->execute([':r' => $rolId]);
            $ins = $this->db->prepare("INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (:r, :p)");
            foreach ($nuevos as $pid) {
                $ins->execute([':r' => $rolId, ':p' => $pid]);
            }
            $this->db->commit();

            $nombres = fn(array $ids) => implode(', ', array_map(fn($i) => $todos[$i], $ids));
            $detalle = $rol['nombre'] . ':'
                . ($agregados ? ' +' . $nombres($agregados) : '')
                . ($quitados  ? ' −' . $nombres($quitados)  : '');
            return ['success' => true, 'id' => $rolId, 'detalle' => $detalle,
                    'mensaje' => 'Permisos del rol «' . $rol['nombre'] . '» actualizados.'];
        } catch (Throwable $e) {
            error_log('guardarPermisos: ' . $e->getMessage());
            return $this->fallo('No se pudieron guardar los permisos.');
        }
    }

    public function eliminar(bool $actorSuper, $rolId): array
    {
        $rol = $this->obtener($rolId);
        if (!$rol) {
            return ['success' => false, 'mensaje' => 'El rol no existe.'];
        }
        if ($rol['es_sistema']) {
            return ['success' => false, 'mensaje' => 'Los roles base del sistema no se pueden eliminar.'];
        }
        if (!$actorSuper && rolEsPrivilegiado($this->db, $rol['id'])) {
            return ['success' => false, 'mensaje' => 'Solo un superadministrador puede eliminar roles con permisos de administración.'];
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE rol_id = :r");
        $st->execute([':r' => $rol['id']]);
        if ((int)$st->fetchColumn() > 0) {
            return ['success' => false, 'mensaje' => 'Hay usuarios con este rol. Cámbiales el rol antes de eliminarlo.'];
        }

        try {
            $this->db->prepare("DELETE FROM roles WHERE id = :id AND es_sistema = FALSE")->execute([':id' => $rol['id']]);
            return ['success' => true, 'id' => (int)$rol['id'], 'detalle' => $rol['nombre'],
                    'mensaje' => 'Rol «' . $rol['nombre'] . '» eliminado.'];
        } catch (Throwable $e) {
            error_log('eliminar rol: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo eliminar el rol.'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = is_string($_POST['accion']) ? $_POST['accion'] : '';

    // Cada acción exige su permiso: crear y guardar → escritura; eliminar → eliminacion
    requierePermiso('roles', $accion === 'eliminar_rol' ? 'eliminacion' : 'escritura');

    $ctrl       = new RolesCtrl();
    $actorId    = (int)$_SESSION['usuario_id'];
    $actorSuper = esSuperadmin($ctrl->db(), $actorId);
    $rolPost    = is_string($_POST['rol_id'] ?? null) ? $_POST['rol_id'] : '';
    $volverRol  = null;

    if ($accion === 'crear_rol') {
        $resultado = $ctrl->crear($actorSuper, $_POST['nombre'] ?? '', $_POST['descripcion'] ?? '', $_POST['copiar_de'] ?? '');
        $volverRol = $resultado['nuevo_rol'] ?? null;
    } elseif ($accion === 'guardar_permisos') {
        $resultado = $ctrl->guardarPermisos($actorId, $actorSuper, $rolPost, $_POST['permisos'] ?? []);
        $volverRol = ctype_digit($rolPost) ? (int)$rolPost : null;
    } elseif ($accion === 'eliminar_rol') {
        $resultado = $ctrl->eliminar($actorSuper, $rolPost);
    } else {
        $resultado = ['success' => false, 'mensaje' => 'Acción no permitida'];
        auditar('acceso_denegado', 'roles', null, 'Acción no permitida: ' . substr($accion, 0, 40));
    }

    if (!empty($resultado['success'])) {
        auditar($accion, 'roles', $resultado['id'] ?? null, $resultado['detalle'] ?? $resultado['mensaje']);
    }
    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = !empty($resultado['success']) ? 'success' : 'error';
    header('Location: ../vista/roles.php' . ($volverRol ? '?rol=' . (int)$volverRol : ''));
    exit();
}
?>
