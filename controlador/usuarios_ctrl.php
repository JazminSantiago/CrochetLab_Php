<?php
// controlador/usuarios_ctrl.php
// Gestión de usuarios para el administrador: listar, cambiar rol, activar/desactivar y desbloquear.
//
// Protecciones (además de exigir el permiso usuarios.escritura):
//   - nadie puede cambiarse el rol ni desactivarse a sí mismo;
//   - no se toca a un superadministrador;
//   - nunca se deja el sistema sin un Administrador activo;
//   - tocar a un administrador, o dar/quitar un rol con permisos de administración,
//     solo lo puede hacer un superadministrador (evita escalar privilegios).

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/../modelo/admin_util.php';

class UsuariosCtrl
{
    private $db;
    const POR_PAGINA = 25;

    public function __construct()
    {
        $this->db = (new Conexion())->conectar();
    }

    public function db(): PDO
    {
        return $this->db;
    }

    // ── Listado con filtros y paginación ──
    // $estado: '' (todos) | 'activos' | 'inactivos' | 'bloqueados'
    public function listar(string $q, int $rolId, string $estado, int $pagina): array
    {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[] = "(u.usuario ILIKE :q OR u.nombre ILIKE :q2 OR u.email ILIKE :q3)";
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params[':q'] = $params[':q2'] = $params[':q3'] = $like;
        }
        if ($rolId > 0) {
            $where[] = "u.rol_id = :rol";
            $params[':rol'] = $rolId;
        }
        if ($estado === 'activos') {
            $where[] = "u.activo = TRUE";
        } elseif ($estado === 'inactivos') {
            $where[] = "u.activo = FALSE";
        } elseif ($estado === 'bloqueados') {
            $where[] = "(u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW())";
        }
        $cond = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM usuarios u $cond");
        $st->execute($params);
        $total   = (int)$st->fetchColumn();
        $paginas = max(1, (int)ceil($total / self::POR_PAGINA));
        $pagina  = max(1, min($pagina, $paginas));
        $offset  = ($pagina - 1) * self::POR_PAGINA;

        $st = $this->db->prepare(
            "SELECT u.id, u.usuario, u.nombre, u.email, u.rol_id, r.nombre AS rol, u.activo,
                    u.es_superadmin, u.totp_activo, u.ultimo_acceso, u.fecha_registro,
                    (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS bloqueado,
                    u.bloqueado_hasta
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             $cond
             ORDER BY u.es_superadmin DESC, u.activo DESC, u.nombre ASC, u.id ASC
             LIMIT " . self::POR_PAGINA . " OFFSET $offset"
        );
        $st->execute($params);

        return ['filas' => $st->fetchAll(), 'total' => $total, 'paginas' => $paginas, 'pagina' => $pagina];
    }

    private function fallo(string $mensaje): array
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        return ['success' => false, 'mensaje' => $mensaje];
    }

    // Bloquea a los administradores activos mientras se decide, para que dos cambios
    // simultáneos no puedan dejar el sistema sin administrador.
    private function bloquearAdmins(): void
    {
        $this->db->query(
            "SELECT id FROM usuarios WHERE rol_id = (SELECT id FROM roles WHERE nombre = '" . NOMBRE_ROL_ADMIN . "') FOR UPDATE"
        );
    }

    private function obtenerObjetivo($id)
    {
        $st = $this->db->prepare(
            "SELECT u.id, u.usuario, u.nombre, u.activo, u.rol_id, u.es_superadmin,
                    r.nombre AS rol
             FROM usuarios u JOIN roles r ON r.id = u.rol_id
             WHERE u.id = :id"
        );
        $st->execute([':id' => $id]);
        return $st->fetch();
    }

    public function cambiarRol(int $actorId, bool $actorSuper, $targetId, $nuevoRolId): array
    {
        if (!ctype_digit((string)$targetId) || !ctype_digit((string)$nuevoRolId)) {
            return ['success' => false, 'mensaje' => 'Datos no válidos.'];
        }
        $targetId = (int)$targetId;
        $nuevoRolId = (int)$nuevoRolId;

        try {
            $this->db->beginTransaction();
            $this->bloquearAdmins();

            $t = $this->obtenerObjetivo($targetId);
            if (!$t) {
                return $this->fallo('El usuario no existe.');
            }
            if ($targetId === $actorId) {
                return $this->fallo('No puedes cambiar tu propio rol.');
            }
            if ($t['es_superadmin']) {
                return $this->fallo('No se puede cambiar el rol de un superadministrador.');
            }

            $st = $this->db->prepare("SELECT id, nombre FROM roles WHERE id = :id");
            $st->execute([':id' => $nuevoRolId]);
            $nuevo = $st->fetch();
            if (!$nuevo) {
                return $this->fallo('El rol elegido no existe.');
            }
            if ($nuevoRolId === (int)$t['rol_id']) {
                return $this->fallo('El usuario ya tiene ese rol.');
            }

            // Tocar a un administrador, o dar/quitar un rol con permisos de administración
            if (!$actorSuper && (rolEsPrivilegiado($this->db, $t['rol_id']) || rolEsPrivilegiado($this->db, $nuevoRolId))) {
                return $this->fallo('Solo un superadministrador puede asignar o quitar roles con permisos de administración.');
            }

            // No dejar el sistema sin administrador activo
            if ((int)$t['rol_id'] === idRolAdministrador($this->db) && $t['activo'] && contarAdminsActivos($this->db, $targetId) < 1) {
                return $this->fallo('No puedes quitar el rol al único administrador activo.');
            }

            $this->db->prepare("UPDATE usuarios SET rol_id = :r WHERE id = :id")
                     ->execute([':r' => $nuevoRolId, ':id' => $targetId]);

            // Un rol de tejedor (con "mis asignaciones") necesita su ficha de empleado para poder entrar
            $st = $this->db->prepare(
                "SELECT EXISTS (SELECT 1 FROM rol_permisos rp JOIN permisos p ON p.id = rp.permiso_id
                                WHERE rp.rol_id = :r AND p.area = 'mis_asignaciones' AND p.accion = 'lectura')"
            );
            $st->execute([':r' => $nuevoRolId]);
            if ($st->fetchColumn()) {
                $this->db->prepare("INSERT INTO empleados (usuario_id) VALUES (:id) ON CONFLICT (usuario_id) DO NOTHING")
                         ->execute([':id' => $targetId]);
            }

            $this->db->commit();
            return ['success' => true, 'id' => $targetId,
                    'detalle' => $t['usuario'] . ': ' . $t['rol'] . ' → ' . $nuevo['nombre'],
                    'mensaje' => 'Rol de «' . $t['usuario'] . '» cambiado a ' . $nuevo['nombre'] . '.'];
        } catch (Throwable $e) {
            error_log('cambiarRol: ' . $e->getMessage());
            return $this->fallo('No se pudo cambiar el rol. Inténtalo de nuevo.');
        }
    }

    public function cambiarEstado(int $actorId, bool $actorSuper, $targetId, bool $activar): array
    {
        if (!ctype_digit((string)$targetId)) {
            return ['success' => false, 'mensaje' => 'Datos no válidos.'];
        }
        $targetId = (int)$targetId;

        try {
            $this->db->beginTransaction();
            $this->bloquearAdmins();

            $t = $this->obtenerObjetivo($targetId);
            if (!$t) {
                return $this->fallo('El usuario no existe.');
            }
            if ($targetId === $actorId) {
                return $this->fallo('No puedes desactivar tu propia cuenta.');
            }
            if ($t['es_superadmin']) {
                return $this->fallo('No se puede modificar a un superadministrador.');
            }
            if (!$actorSuper && rolEsPrivilegiado($this->db, $t['rol_id'])) {
                return $this->fallo('Solo un superadministrador puede modificar cuentas con permisos de administración.');
            }
            if ((bool)$t['activo'] === $activar) {
                return $this->fallo($activar ? 'La cuenta ya está activa.' : 'La cuenta ya está desactivada.');
            }
            if (!$activar && (int)$t['rol_id'] === idRolAdministrador($this->db) && contarAdminsActivos($this->db, $targetId) < 1) {
                return $this->fallo('No puedes desactivar al único administrador activo.');
            }

            $this->db->prepare(
                "UPDATE usuarios SET activo = :a, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id"
            )->execute([':a' => $activar ? 'true' : 'false', ':id' => $targetId]);

            $this->db->commit();
            return ['success' => true, 'id' => $targetId, 'detalle' => $t['usuario'],
                    'mensaje' => 'Cuenta de «' . $t['usuario'] . '» ' . ($activar ? 'activada.' : 'desactivada.')];
        } catch (Throwable $e) {
            error_log('cambiarEstado: ' . $e->getMessage());
            return $this->fallo('No se pudo cambiar el estado. Inténtalo de nuevo.');
        }
    }

    public function desbloquear($targetId): array
    {
        if (!ctype_digit((string)$targetId)) {
            return ['success' => false, 'mensaje' => 'Datos no válidos.'];
        }
        $t = $this->obtenerObjetivo((int)$targetId);
        if (!$t) {
            return ['success' => false, 'mensaje' => 'El usuario no existe.'];
        }
        $this->db->prepare("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id")
                 ->execute([':id' => $t['id']]);
        return ['success' => true, 'id' => (int)$t['id'], 'detalle' => $t['usuario'],
                'mensaje' => 'Cuenta de «' . $t['usuario'] . '» desbloqueada.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    requierePermiso('usuarios', 'escritura');

    $ctrl       = new UsuariosCtrl();
    $actorId    = (int)$_SESSION['usuario_id'];
    $actorSuper = esSuperadmin($ctrl->db(), $actorId);
    $accion     = is_string($_POST['accion']) ? $_POST['accion'] : '';
    $uidObj     = is_string($_POST['usuario_id'] ?? null) ? $_POST['usuario_id'] : '';

    if ($accion === 'cambiar_rol') {
        $resultado = $ctrl->cambiarRol($actorId, $actorSuper, $uidObj, is_string($_POST['rol_id'] ?? null) ? $_POST['rol_id'] : '');
    } elseif ($accion === 'activar') {
        $resultado = $ctrl->cambiarEstado($actorId, $actorSuper, $uidObj, true);
    } elseif ($accion === 'desactivar') {
        $resultado = $ctrl->cambiarEstado($actorId, $actorSuper, $uidObj, false);
    } elseif ($accion === 'desbloquear') {
        $resultado = $ctrl->desbloquear($uidObj);
    } else {
        $resultado = ['success' => false, 'mensaje' => 'Acción no permitida'];
        auditar('acceso_denegado', 'usuarios', null, 'Acción no permitida: ' . substr($accion, 0, 40));
    }

    if (!empty($resultado['success'])) {
        auditar($accion, 'usuarios', $resultado['id'] ?? null, $resultado['detalle'] ?? $resultado['mensaje']);
    }
    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = !empty($resultado['success']) ? 'success' : 'error';
    header('Location: ../vista/usuarios.php');
    exit();
}
?>
