<?php
// controlador/pedidos_ctrl.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';

class PedidosCtrl
{
    private $db;

    public function __construct()
    {
        $conn = new Conexion();
        $this->db = $conn->conectar();
    }

    // ── Listar pedidos por tipo ──
    public function listar($tipo = null)
    {
        $where = $tipo ? "WHERE p.tipo = :tipo" : "";
        $sql = "SELECT p.*, u.nombre AS creado_por_nombre,
                       c.nombre AS producto_nombre
                FROM pedidos p
                LEFT JOIN usuarios u ON p.creado_por = u.id
                LEFT JOIN catalogo c ON p.catalogo_id = c.id
                $where
                ORDER BY p.fecha_pedido DESC";
        $stmt = $this->db->prepare($sql);
        if ($tipo) $stmt->execute([':tipo' => $tipo]);
        else $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Obtener un pedido ──
    public function obtener($id)
    {
        $stmt = $this->db->prepare(
            "SELECT p.*, c.nombre AS producto_nombre
             FROM pedidos p
             LEFT JOIN catalogo c ON p.catalogo_id = c.id
             WHERE p.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // ── Crear pedido estándar ──
    public function crearEstandar($datos, $usuario_id)
    {
        $datos['catalogo_id'] = $datos['catalogo_id_estandar'] ?? $datos['catalogo_id'] ?? null;
        if (empty($datos['catalogo_id']))
            return ['success' => false, 'mensaje' => 'Debes seleccionar un producto del catálogo'];
        if (empty($datos['fecha_entrega']))
            return ['success' => false, 'mensaje' => 'La fecha de entrega es obligatoria'];

        try {
            $this->db->prepare(
                "INSERT INTO pedidos (tipo, catalogo_id, descripcion, fecha_entrega, estado, prioridad, creado_por)
                 VALUES ('estandar', :cat, :desc, :fe, :estado, :prioridad, :uid)"
            )->execute([
                ':cat'      => $datos['catalogo_id'],
                ':desc'     => $datos['descripcion'] ?? null,
                ':fe'       => $datos['fecha_entrega'],
                ':estado'   => $datos['estado'] ?? 'pendiente',
                ':prioridad'=> $datos['prioridad'] ?? 'normal',
                ':uid'      => $usuario_id
            ]);
            return ['success' => true, 'mensaje' => 'Pedido estándar creado correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    // ── Crear pedido personalizado ──
    public function crearPersonalizado($datos, $archivo, $usuario_id)
    {
        if (empty($datos['cliente_nombre']))
            return ['success' => false, 'mensaje' => 'El nombre del cliente es obligatorio'];
        if (empty($datos['fecha_entrega']))
            return ['success' => false, 'mensaje' => 'La fecha de entrega es obligatoria'];
        if (empty($datos['descripcion']))
            return ['success' => false, 'mensaje' => 'La descripción del pedido es obligatoria'];

        $imagen_ref = $this->subirImagen($archivo);
        if (isset($imagen_ref['error']))
            return ['success' => false, 'mensaje' => $imagen_ref['error']];

        $domicilio   = isset($datos['entrega_domicilio']) ? true : false;
        $costo_envio = $domicilio ? (float)($datos['costo_envio'] ?? 0) : 0;
        $direccion   = $domicilio ? ($datos['direccion_entrega'] ?? null) : null;

        try {
            $this->db->prepare(
                "INSERT INTO pedidos (tipo, catalogo_id, descripcion, imagen_referencia,
                 cliente_nombre, cliente_contacto, fecha_entrega, estado, prioridad,
                 entrega_domicilio, costo_envio, direccion_entrega, creado_por)
                 VALUES ('personalizado', :cat, :desc, :imgref,
                 :cn, :cc, :fe, :estado, :prioridad,
                 :dom, :cenv, :dir, :uid)"
            )->execute([
                ':cat'      => $datos['catalogo_id'] ?: null,
                ':desc'     => $datos['descripcion'],
                ':imgref'   => $imagen_ref,
                ':cn'       => $datos['cliente_nombre'],
                ':cc'       => $datos['cliente_contacto'] ?? null,
                ':fe'       => $datos['fecha_entrega'],
                ':estado'   => 'pendiente',
                ':prioridad'=> $datos['prioridad'] ?? 'normal',
                ':dom'      => $domicilio ? 'true' : 'false',
                ':cenv'     => $costo_envio,
                ':dir'      => $direccion,
                ':uid'      => $usuario_id
            ]);
            return ['success' => true, 'mensaje' => 'Pedido personalizado creado correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    // ── Editar pedido ──
    public function editar($id, $datos, $archivo = null)
    {
        $pedido = $this->obtener($id);
        if (!$pedido) return ['success' => false, 'mensaje' => 'Pedido no encontrado'];

        // Nueva imagen de referencia si se subió
        $imagen_ref = $pedido['imagen_referencia'];
        if ($archivo && $archivo['size'] > 0) {
            $nueva = $this->subirImagen($archivo);
            if (isset($nueva['error'])) return ['success' => false, 'mensaje' => $nueva['error']];
            $imagen_ref = $nueva;
        }

        $domicilio   = isset($datos['entrega_domicilio']) ? true : false;
        $costo_envio = $domicilio ? (float)($datos['costo_envio'] ?? 0) : 0;
        $direccion   = $domicilio ? ($datos['direccion_entrega'] ?? null) : null;

        try {
            $this->db->prepare(
                "UPDATE pedidos SET catalogo_id = :cat, descripcion = :desc,
                 imagen_referencia = :imgref, cliente_nombre = :cn, cliente_contacto = :cc,
                 fecha_entrega = :fe, estado = :estado, prioridad = :prioridad,
                 entrega_domicilio = :dom, costo_envio = :cenv, direccion_entrega = :dir
                 WHERE id = :id"
            )->execute([
                ':cat'      => $datos['catalogo_id'] ?: null,
                ':desc'     => $datos['descripcion'] ?? null,
                ':imgref'   => $imagen_ref,
                ':cn'       => $datos['cliente_nombre'] ?? null,
                ':cc'       => $datos['cliente_contacto'] ?? null,
                ':fe'       => $datos['fecha_entrega'],
                ':estado'   => $datos['estado'] ?? $pedido['estado'],
                ':prioridad'=> $datos['prioridad'] ?? $pedido['prioridad'],
                ':dom'      => $domicilio ? 'true' : 'false',
                ':cenv'     => $costo_envio,
                ':dir'      => $direccion,
                ':id'       => $id
            ]);
            return ['success' => true, 'mensaje' => 'Pedido actualizado correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()];
        }
    }

    // ── Cambiar estado ──
    public function cambiarEstado($id, $estado)
    {
        $estados = ['pendiente','en_proceso','completado','entregado','cancelado'];
        if (!in_array($estado, $estados))
            return ['success' => false, 'mensaje' => 'Estado no válido'];

        $this->db->prepare("UPDATE pedidos SET estado = :e WHERE id = :id")
                 ->execute([':e' => $estado, ':id' => $id]);
        return ['success' => true, 'mensaje' => 'Estado actualizado'];
    }

    // ── Subir imagen de referencia ──
    private function subirImagen($archivo)
    {
        if (!$archivo || $archivo['size'] === 0) return null;

        $permitidos = ['image/jpeg','image/png','image/webp'];
        if (!in_array($archivo['type'], $permitidos))
            return ['error' => 'Solo se permiten imágenes JPG, PNG o WEBP'];
        if ($archivo['size'] > 3 * 1024 * 1024)
            return ['error' => 'La imagen no debe superar 3MB'];

        $ext     = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombre  = 'pedido_ref_' . uniqid() . '.' . $ext;
        $destino = __DIR__ . '/../assets/uploads/pedidos/' . $nombre;

        if (!file_exists(dirname($destino)))
            mkdir(dirname($destino), 0777, true);

        if (!move_uploaded_file($archivo['tmp_name'], $destino))
            return ['error' => 'Error al guardar la imagen'];

        return 'assets/uploads/pedidos/' . $nombre;
    }
}

// ── Procesar POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
        header('Location: ../index.php'); exit();
    }

    $ctrl   = new PedidosCtrl();
    $accion = $_POST['accion'];

    if ($accion === 'crear_estandar') {
        $resultado = $ctrl->crearEstandar($_POST, $_SESSION['usuario_id']);
    } elseif ($accion === 'crear_personalizado') {
        $resultado = $ctrl->crearPersonalizado($_POST, $_FILES['imagen_referencia'] ?? null, $_SESSION['usuario_id']);
    } elseif ($accion === 'editar') {
        $resultado = $ctrl->editar($_POST['pedido_id'], $_POST, $_FILES['imagen_referencia'] ?? null);
    } elseif ($accion === 'cambiar_estado') {
        $resultado = $ctrl->cambiarEstado($_POST['pedido_id'], $_POST['estado']);
    }

    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';
    header('Location: ../vista/pedidos/index_pedidos.php');
    exit();
}
?>