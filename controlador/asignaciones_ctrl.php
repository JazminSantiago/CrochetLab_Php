<?php
// controlador/asignaciones_ctrl.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';

class AsignacionesCtrl
{
    private $db;

    public function __construct()
    {
        $conn = new Conexion();
        $this->db = $conn->conectar();
    }

    // ── Listar todas las asignaciones con info de pedido y empleado ──
    public function listar()
    {
        $sql = "SELECT a.*,
                       u.nombre AS empleado_nombre,
                       p.tipo AS pedido_tipo,
                       p.fecha_entrega,
                       p.estado AS pedido_estado,
                       p.prioridad,
                       COALESCE(c.nombre, p.cliente_nombre, '—') AS pedido_descripcion
                FROM asignaciones a
                JOIN usuarios u ON a.empleado_id = u.id
                JOIN pedidos p ON a.pedido_id = p.id
                LEFT JOIN catalogo c ON p.catalogo_id = c.id
                ORDER BY p.fecha_entrega ASC, a.fecha_asignacion DESC";
        return $this->db->query($sql)->fetchAll();
    }

    // ── Listar asignaciones de un empleado específico ──
    public function listarPorEmpleado($empleado_id)
    {
        $sql = "SELECT a.*,
                       p.tipo AS pedido_tipo,
                       p.fecha_entrega,
                       p.estado AS pedido_estado,
                       p.prioridad,
                       p.descripcion AS pedido_notas,
                       p.imagen_referencia,
                       COALESCE(c.nombre, p.cliente_nombre, '—') AS pedido_descripcion,
                       c.imagen_ruta AS producto_imagen
                FROM asignaciones a
                JOIN pedidos p ON a.pedido_id = p.id
                LEFT JOIN catalogo c ON p.catalogo_id = c.id
                WHERE a.empleado_id = :eid
                ORDER BY p.fecha_entrega ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':eid' => $empleado_id]);
        return $stmt->fetchAll();
    }

    // ── Obtener asignaciones de un pedido ──
    public function listarPorPedido($pedido_id)
    {
        $sql = "SELECT a.*, u.nombre AS empleado_nombre
                FROM asignaciones a
                JOIN usuarios u ON a.empleado_id = u.id
                WHERE a.pedido_id = :pid
                ORDER BY a.fecha_asignacion ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':pid' => $pedido_id]);
        return $stmt->fetchAll();
    }

    // ── Progreso promedio de un pedido ──
    public function progresoPedido($pedido_id)
    {
        $stmt = $this->db->prepare(
            "SELECT ROUND(AVG(progreso)) AS promedio FROM asignaciones WHERE pedido_id = :pid"
        );
        $stmt->execute([':pid' => $pedido_id]);
        $row = $stmt->fetch();
        return $row ? (int)$row['promedio'] : 0;
    }

    // ── Listar pedidos disponibles para asignar ──
    public function listarPedidosDisponibles()
    {
        $sql = "SELECT p.id,
                       p.tipo,
                       p.fecha_entrega,
                       p.estado,
                       p.prioridad,
                       COALESCE(c.nombre, p.cliente_nombre, 'Sin nombre') AS nombre_display
                FROM pedidos p
                LEFT JOIN catalogo c ON p.catalogo_id = c.id
                WHERE p.estado NOT IN ('completado', 'entregado', 'cancelado')
                ORDER BY p.fecha_entrega ASC";
        return $this->db->query($sql)->fetchAll();
    }

    // ── Listar tejedores ──
    public function listarTejedores()
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.nombre FROM usuarios u
             WHERE u.activo = true
               AND EXISTS (SELECT 1 FROM empleados e WHERE e.usuario_id = u.id)
               AND EXISTS (SELECT 1 FROM rol_permisos rp JOIN permisos p ON p.id = rp.permiso_id
                           WHERE rp.rol_id = u.rol_id AND p.area = 'mis_asignaciones' AND p.accion = 'lectura')
             ORDER BY u.nombre ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Crear asignación ──
    public function crear($datos)
    {
        if (empty($datos['pedido_id']))
            return ['success' => false, 'mensaje' => 'Debes seleccionar un pedido'];
        if (empty($datos['empleado_id']))
            return ['success' => false, 'mensaje' => 'Debes seleccionar un tejedor'];

        try {
            $this->db->prepare(
                "INSERT INTO asignaciones (pedido_id, empleado_id, progreso, notas)
                 VALUES (:pid, :eid, :prog, :notas)"
            )->execute([
                ':pid'   => $datos['pedido_id'],
                ':eid'   => $datos['empleado_id'],
                ':prog'  => $datos['progreso'] ?? 0,
                ':notas' => $datos['notas'] ?? null,
            ]);
            return ['success' => true, 'mensaje' => 'Asignación creada correctamente'];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'duplicate')) {
                return ['success' => false, 'mensaje' => 'Este tejedor ya está asignado a ese pedido'];
            }
            return ['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    // ── Editar asignación (admin) ──
    public function editar($id, $datos)
    {
        try {
            $this->db->prepare(
                "UPDATE asignaciones SET progreso = :prog, notas = :notas,
                 fecha_actualizacion = NOW() WHERE id = :id"
            )->execute([
                ':prog'  => $datos['progreso'] ?? 0,
                ':notas' => $datos['notas'] ?? null,
                ':id'    => $id,
            ]);
            return ['success' => true, 'mensaje' => 'Asignación actualizada'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    // ── Actualizar progreso (tejedor) ──
    public function actualizarProgreso($asignacion_id, $empleado_id, $progreso, $notas = null)
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM asignaciones WHERE id = :id AND empleado_id = :eid"
        );
        $stmt->execute([':id' => $asignacion_id, ':eid' => $empleado_id]);
        if (!$stmt->fetch())
            return ['success' => false, 'mensaje' => 'No tienes permiso para actualizar esta asignación'];

        $this->db->prepare(
            "UPDATE asignaciones SET progreso = :prog, notas = :notas,
             fecha_actualizacion = NOW() WHERE id = :id"
        )->execute([
            ':prog'  => max(0, min(100, (int)$progreso)),
            ':notas' => $notas,
            ':id'    => $asignacion_id,
        ]);
        return ['success' => true, 'mensaje' => 'Progreso actualizado'];
    }

    // ── Subir imagen de prueba (tejedor) ──
    public function subirImagenPrueba($asignacion_id, $empleado_id, $archivo)
    {
        // Verificar que la asignación pertenece al empleado
        $stmt = $this->db->prepare(
            "SELECT id, progreso FROM asignaciones WHERE id = :id AND empleado_id = :eid"
        );
        $stmt->execute([':id' => $asignacion_id, ':eid' => $empleado_id]);
        $asig = $stmt->fetch();

        if (!$asig)
            return ['success' => false, 'mensaje' => 'No tienes permiso para modificar esta asignación'];

        if (!$archivo || $archivo['size'] === 0)
            return ['success' => false, 'mensaje' => 'Debes seleccionar una imagen'];

        $permitidos = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($archivo['type'], $permitidos))
            return ['success' => false, 'mensaje' => 'Solo se permiten imágenes JPG, PNG o WEBP'];

        if ($archivo['size'] > 8 * 1024 * 1024)
            return ['success' => false, 'mensaje' => 'La imagen no debe superar 8MB'];

        $ext     = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombre  = 'prueba_' . $asignacion_id . '_' . uniqid() . '.' . $ext;
        $dir     = __DIR__ . '/../assets/uploads/pruebas/';
        $destino = $dir . $nombre;

        if (!file_exists($dir))
            mkdir($dir, 0777, true);

        if (!move_uploaded_file($archivo['tmp_name'], $destino))
            return ['success' => false, 'mensaje' => 'Error al guardar la imagen'];

        $ruta = 'assets/uploads/pruebas/' . $nombre;

        $this->db->prepare(
            "UPDATE asignaciones SET imagen_prueba = :img, fecha_actualizacion = NOW() WHERE id = :id"
        )->execute([':img' => $ruta, ':id' => $asignacion_id]);

        return ['success' => true, 'mensaje' => '¡Imagen de prueba subida correctamente! El admin la revisará pronto.'];
    }

    // ── Eliminar asignación ──
    public function eliminar($id)
    {
        $this->db->prepare("DELETE FROM asignaciones WHERE id = :id")->execute([':id' => $id]);
        return ['success' => true, 'mensaje' => 'Asignación eliminada'];
    }
}

// ── Procesar POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ../index.php'); exit();
    }

    require_once __DIR__ . '/../modelo/autorizacion.php';
    verificarCsrf();

    $ctrl   = new AsignacionesCtrl();
    $accion = $_POST['accion'];

    if ($accion === 'crear' && tienePermiso('asignaciones', 'escritura')) {
        $resultado = $ctrl->crear($_POST);
        header('Location: ../vista/asignaciones/index_asignaciones.php');

    } elseif ($accion === 'editar' && tienePermiso('asignaciones', 'escritura')) {
        $resultado = $ctrl->editar($_POST['asignacion_id'], $_POST);
        header('Location: ../vista/asignaciones/index_asignaciones.php');

    } elseif ($accion === 'eliminar' && tienePermiso('asignaciones', 'eliminacion')) {
        $resultado = $ctrl->eliminar($_POST['asignacion_id']);
        header('Location: ../vista/asignaciones/index_asignaciones.php');

    } elseif ($accion === 'actualizar_progreso' && tienePermiso('mis_asignaciones', 'escritura')) {
        $resultado = $ctrl->actualizarProgreso(
            $_POST['asignacion_id'],
            $_SESSION['usuario_id'],
            $_POST['progreso'],
            $_POST['notas'] ?? null
        );
        header('Location: ../vista/asignaciones/mis_asignaciones.php');

    } elseif ($accion === 'subir_prueba' && tienePermiso('mis_asignaciones', 'escritura')) {
        $resultado = $ctrl->subirImagenPrueba(
            $_POST['asignacion_id'],
            $_SESSION['usuario_id'],
            $_FILES['imagen_prueba'] ?? null
        );
        header('Location: ../vista/asignaciones/mis_asignaciones.php');

    } else {
        $resultado = ['success' => false, 'mensaje' => 'Acción no permitida'];
        auditar('acceso_denegado', 'asignaciones', null, 'Acción no permitida: ' . $accion);
        header('Location: ../index.php');
    }

    if (!empty($resultado['success'])) {
        $areaAud = in_array($accion, ['actualizar_progreso', 'subir_prueba'], true) ? 'mis_asignaciones' : 'asignaciones';
        auditar($accion, $areaAud, $_POST['asignacion_id'] ?? null, $resultado['mensaje']);
    }
    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';
    exit();
}
?>