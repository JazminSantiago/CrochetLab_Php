<?php
// controlador/patrones_ctrl.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';

class PatronesCtrl
{
    private $db;

    public function __construct()
    {
        $conn = new Conexion();
        $this->db = $conn->conectar();
    }

    // ── Listar todos (admin) ──
    public function listar($estado = null)
    {
        $where = $estado ? "WHERE p.estado = :estado" : "";
        $sql = "SELECT p.*, c.nombre AS producto_nombre, u.nombre AS creado_por_nombre
                FROM patrones p
                LEFT JOIN catalogo c ON p.catalogo_id = c.id
                LEFT JOIN usuarios u ON p.creado_por = u.id
                $where
                ORDER BY p.fecha_creacion DESC";
        $stmt = $this->db->prepare($sql);
        if ($estado) $stmt->execute([':estado' => $estado]);
        else $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Listar aprobados (tejedores) ──
    public function listarAprobados()
    {
        $sql = "SELECT p.*, c.nombre AS producto_nombre, c.imagen_ruta AS producto_imagen,
                       u.nombre AS creado_por_nombre
                FROM patrones p
                LEFT JOIN catalogo c ON p.catalogo_id = c.id
                LEFT JOIN usuarios u ON p.creado_por = u.id
                WHERE p.estado = 'aprobado'
                ORDER BY c.nombre ASC, p.titulo ASC";
        return $this->db->query($sql)->fetchAll();
    }

    // ── Obtener uno ──
    public function obtener($id)
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, c.nombre AS producto_nombre, u.nombre AS creado_por_nombre
             FROM patrones p
             LEFT JOIN catalogo c ON p.catalogo_id = c.id
             LEFT JOIN usuarios u ON p.creado_por = u.id
             WHERE p.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // ── Crear (admin) ──
    public function crear($datos, $archivo, $usuario_id)
    {
        if (empty($datos['titulo']))
            return ['success' => false, 'mensaje' => 'El título es obligatorio'];

        $imagen = $this->subirImagen($archivo);
        if (isset($imagen['error']))
            return ['success' => false, 'mensaje' => $imagen['error']];

        try {
            $this->db->prepare(
                "INSERT INTO patrones (catalogo_id, titulo, instrucciones, imagen_ruta, estado, creado_por, es_contribucion)
                 VALUES (:cat, :tit, :inst, :img, 'aprobado', :uid, false)"
            )->execute([
                ':cat'  => $datos['catalogo_id'] ?: null,
                ':tit'  => $datos['titulo'],
                ':inst' => $datos['instrucciones'] ?? null,
                ':img'  => $imagen,
                ':uid'  => $usuario_id,
            ]);
            return ['success' => true, 'mensaje' => 'Patrón creado correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    // ── Editar (admin) ──
    public function editar($id, $datos, $archivo)
    {
        if (empty($datos['titulo']))
            return ['success' => false, 'mensaje' => 'El título es obligatorio'];

        $patron = $this->obtener($id);
        if (!$patron) return ['success' => false, 'mensaje' => 'Patrón no encontrado'];

        $imagen = $patron['imagen_ruta'];
        if ($archivo && $archivo['size'] > 0) {
            $nueva = $this->subirImagen($archivo);
            if (isset($nueva['error'])) return ['success' => false, 'mensaje' => $nueva['error']];
            $imagen = $nueva;
        }

        try {
            $this->db->prepare(
                "UPDATE patrones SET catalogo_id = :cat, titulo = :tit, instrucciones = :inst,
                 imagen_ruta = :img, fecha_actualizacion = NOW() WHERE id = :id"
            )->execute([
                ':cat'  => $datos['catalogo_id'] ?: null,
                ':tit'  => $datos['titulo'],
                ':inst' => $datos['instrucciones'] ?? null,
                ':img'  => $imagen,
                ':id'   => $id,
            ]);
            return ['success' => true, 'mensaje' => 'Patrón actualizado correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    // ── Eliminar (admin) ──
    public function eliminar($id)
    {
        $this->db->prepare("DELETE FROM patrones WHERE id = :id")->execute([':id' => $id]);
        return ['success' => true, 'mensaje' => 'Patrón eliminado'];
    }

    // ── Aprobar contribución (admin) ──
    public function aprobar($id)
    {
        $patron = $this->obtener($id);
        if (!$patron) return ['success' => false, 'mensaje' => 'Patrón no encontrado'];

        $this->db->prepare(
            "UPDATE patrones SET estado = 'aprobado', fecha_actualizacion = NOW() WHERE id = :id"
        )->execute([':id' => $id]);

        // Registrar bonus al tejedor
        if ($patron['creado_por'] && $patron['es_contribucion']) {
            $this->db->prepare(
                "INSERT INTO bonuses (empleado_id, patron_id, motivo) VALUES (:eid, :pid, :mot)"
            )->execute([
                ':eid' => $patron['creado_por'],
                ':pid' => $id,
                ':mot' => 'Contribución de patrón: ' . $patron['titulo'],
            ]);
        }

        return ['success' => true, 'mensaje' => 'Patrón aprobado y bonus registrado al tejedor'];
    }

    // ── Rechazar contribución (admin) ──
    public function rechazar($id)
    {
        $this->db->prepare(
            "UPDATE patrones SET estado = 'rechazado', fecha_actualizacion = NOW() WHERE id = :id"
        )->execute([':id' => $id]);
        return ['success' => true, 'mensaje' => 'Contribución rechazada'];
    }

    // ── Subir contribución (tejedor) ──
    public function subirContribucion($datos, $archivo, $usuario_id)
    {
        if (empty($datos['titulo']))
            return ['success' => false, 'mensaje' => 'El título es obligatorio'];
        if (empty($datos['instrucciones']))
            return ['success' => false, 'mensaje' => 'Las instrucciones son obligatorias'];

        $imagen = $this->subirImagen($archivo);
        if (isset($imagen['error']))
            return ['success' => false, 'mensaje' => $imagen['error']];

        try {
            $this->db->prepare(
                "INSERT INTO patrones (catalogo_id, titulo, instrucciones, imagen_ruta, estado, creado_por, es_contribucion)
                 VALUES (:cat, :tit, :inst, :img, 'pendiente', :uid, true)"
            )->execute([
                ':cat'  => $datos['catalogo_id'] ?: null,
                ':tit'  => $datos['titulo'],
                ':inst' => $datos['instrucciones'],
                ':img'  => $imagen,
                ':uid'  => $usuario_id,
            ]);
            return ['success' => true, 'mensaje' => '¡Patrón enviado! El administrador lo revisará pronto. Si es aprobado, recibirás un bonus 🎉'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    // ── Bonuses de un empleado ──
    public function bonusesPorEmpleado($empleado_id)
    {
        $stmt = $this->db->prepare(
            "SELECT b.*, p.titulo AS patron_titulo
             FROM bonuses b
             LEFT JOIN patrones p ON b.patron_id = p.id
             WHERE b.empleado_id = :eid
             ORDER BY b.fecha DESC"
        );
        $stmt->execute([':eid' => $empleado_id]);
        return $stmt->fetchAll();
    }

    // ── Contar pendientes ──
    public function contarPendientes()
    {
        return (int)$this->db->query(
            "SELECT COUNT(*) FROM patrones WHERE estado = 'pendiente'"
        )->fetchColumn();
    }

    // ── Subir imagen ──
    private function subirImagen($archivo)
    {
        if (!$archivo || $archivo['size'] === 0) return null;

        $permitidos = ['image/jpeg','image/png','image/webp'];
        if (!in_array($archivo['type'], $permitidos))
            return ['error' => 'Solo se permiten imágenes JPG, PNG o WEBP'];
        if ($archivo['size'] > 5 * 1024 * 1024)
            return ['error' => 'La imagen no debe superar 5MB'];

        $ext     = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombre  = 'patron_' . uniqid() . '.' . $ext;
        $destino = __DIR__ . '/../assets/uploads/patrones/' . $nombre;

        if (!file_exists(dirname($destino)))
            mkdir(dirname($destino), 0777, true);

        if (!move_uploaded_file($archivo['tmp_name'], $destino))
            return ['error' => 'Error al guardar la imagen'];

        return 'assets/uploads/patrones/' . $nombre;
    }
}

// ── Procesar POST (solo si se accede directamente) ──
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ../index.php'); exit();
    }

    require_once __DIR__ . '/../modelo/autorizacion.php';
    verificarCsrf();

    $ctrl   = new PatronesCtrl();
    $accion = $_POST['accion'];
    $uid    = $_SESSION['usuario_id'];

    if ($accion === 'crear' && tienePermiso('patrones', 'escritura')) {
        $resultado = $ctrl->crear($_POST, $_FILES['imagen'] ?? null, $uid);
        header('Location: ../vista/patrones/index_patrones.php');
    } elseif ($accion === 'editar' && tienePermiso('patrones', 'escritura')) {
        $resultado = $ctrl->editar($_POST['patron_id'], $_POST, $_FILES['imagen'] ?? null);
        header('Location: ../vista/patrones/index_patrones.php');
    } elseif ($accion === 'eliminar' && tienePermiso('patrones', 'eliminacion')) {
        $resultado = $ctrl->eliminar($_POST['patron_id']);
        header('Location: ../vista/patrones/index_patrones.php');
    } elseif ($accion === 'aprobar' && tienePermiso('patrones', 'escritura')) {
        $resultado = $ctrl->aprobar($_POST['patron_id']);
        header('Location: ../vista/patrones/index_patrones.php');
    } elseif ($accion === 'rechazar' && tienePermiso('patrones', 'escritura')) {
        $resultado = $ctrl->rechazar($_POST['patron_id']);
        header('Location: ../vista/patrones/index_patrones.php');
    } elseif ($accion === 'contribuir' && tienePermiso('mis_patrones', 'escritura')) {
        $resultado = $ctrl->subirContribucion($_POST, $_FILES['imagen'] ?? null, $uid);
        header('Location: ../vista/patrones/mis_patrones.php');
    } else {
        $resultado = ['success' => false, 'mensaje' => 'Acción no permitida'];
        auditar('acceso_denegado', 'patrones', null, 'Acción no permitida: ' . $accion);
        header('Location: ../index.php');
    }

    if (!empty($resultado['success'])) {
        auditar($accion, $accion === 'contribuir' ? 'mis_patrones' : 'patrones', $_POST['patron_id'] ?? null, $resultado['mensaje']);
    }
    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';
    exit();
}
?>