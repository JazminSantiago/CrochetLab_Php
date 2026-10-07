<?php
// controlador/mis_pedidos_ctrl.php
// Pedidos del cliente (rol "Usuario Regular"): crear y cancelar SUS propios pedidos.
// Toda consulta y modificación se filtra por creado_por = usuario actual, así que un cliente
// nunca puede ver ni tocar los pedidos de otra persona.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/../modelo/cifrado.php';

class MisPedidosCtrl
{
    private $db;
    const MAX_POR_HORA = 10;

    public function __construct()
    {
        $this->db = (new Conexion())->conectar();
    }

    public function productos()
    {
        return $this->db->query(
            "SELECT id, nombre, precio FROM catalogo WHERE activo = TRUE ORDER BY nombre ASC"
        )->fetchAll();
    }

    public function listar($uid)
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.tipo, p.descripcion, p.fecha_entrega, p.estado, p.fecha_pedido,
                    c.nombre AS producto_nombre
             FROM pedidos p
             LEFT JOIN catalogo c ON c.id = p.catalogo_id
             WHERE p.creado_por = :uid
             ORDER BY p.fecha_pedido DESC, p.id DESC
             LIMIT 100"
        );
        $st->execute([':uid' => $uid]);
        return $st->fetchAll();
    }

    public function crear($datos, $uid, $nombre, $email)
    {
        $tipo        = (($datos['tipo'] ?? '') === 'personalizado') ? 'personalizado' : 'estandar';
        $fecha       = is_string($datos['fecha_entrega'] ?? null) ? trim($datos['fecha_entrega']) : '';
        $descripcion = is_string($datos['descripcion'] ?? null) ? trim($datos['descripcion']) : '';
        $catalogoId  = (is_string($datos['catalogo_id'] ?? null) && ctype_digit($datos['catalogo_id'])) ? (int)$datos['catalogo_id'] : null;

        // Fecha de entrega: válida, desde hoy y como máximo dentro de un año
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            return ['success' => false, 'mensaje' => 'Elige una fecha de entrega válida.'];
        }
        $hoy = new DateTime('today');
        if ($d < $hoy) {
            return ['success' => false, 'mensaje' => 'La fecha de entrega no puede ser anterior a hoy.'];
        }
        if ($d > (clone $hoy)->modify('+1 year')) {
            return ['success' => false, 'mensaje' => 'La fecha de entrega no puede superar un año.'];
        }

        if (mb_strlen($descripcion) > 500) {
            return ['success' => false, 'mensaje' => 'La descripción no puede superar 500 caracteres.'];
        }

        // Producto del catálogo (obligatorio en pedidos estándar; opcional como referencia en personalizados)
        if ($catalogoId !== null) {
            $st = $this->db->prepare("SELECT id FROM catalogo WHERE id = :id AND activo = TRUE");
            $st->execute([':id' => $catalogoId]);
            if (!$st->fetch()) {
                return ['success' => false, 'mensaje' => 'El producto elegido no está disponible.'];
            }
        }
        if ($tipo === 'estandar' && $catalogoId === null) {
            return ['success' => false, 'mensaje' => 'Elige un producto del catálogo.'];
        }
        if ($tipo === 'personalizado' && mb_strlen($descripcion) < 10) {
            return ['success' => false, 'mensaje' => 'Describe tu pedido personalizado (al menos 10 caracteres).'];
        }

        // Límite para evitar el abuso: 10 pedidos por hora por cuenta
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM pedidos WHERE creado_por = :uid AND fecha_pedido > NOW() - INTERVAL '1 hour'"
        );
        $st->execute([':uid' => $uid]);
        if ((int)$st->fetchColumn() >= self::MAX_POR_HORA) {
            return ['success' => false, 'mensaje' => 'Hiciste demasiados pedidos seguidos. Inténtalo más tarde.'];
        }

        try {
            $st = $this->db->prepare(
                "INSERT INTO pedidos (tipo, catalogo_id, descripcion, cliente_nombre, cliente_contacto,
                                      fecha_entrega, estado, prioridad, creado_por)
                 VALUES (:tipo, :cat, :desc, :cn, :cc, :fe, 'pendiente', 'normal', :uid)
                 RETURNING id"
            );
            $st->execute([
                ':tipo' => $tipo,
                ':cat'  => $catalogoId,
                ':desc' => $descripcion !== '' ? $descripcion : null,
                ':cn'   => $nombre,
                ':cc'   => cifrar($email, 'pedidos.cliente_contacto'),
                ':fe'   => $fecha,
                ':uid'  => $uid,
            ]);
            return ['success' => true, 'id' => (int)$st->fetchColumn(),
                    'mensaje' => 'Pedido enviado. Te contactaremos para confirmarlo.'];
        } catch (PDOException $e) {
            error_log('MisPedidos crear: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo registrar el pedido. Inténtalo de nuevo.'];
        }
    }

    // Solo se puede cancelar un pedido propio que siga pendiente
    public function cancelar($id, $uid)
    {
        if (!ctype_digit((string)$id)) {
            return ['success' => false, 'mensaje' => 'Pedido no válido.'];
        }
        $st = $this->db->prepare(
            "UPDATE pedidos SET estado = 'cancelado'
             WHERE id = :id AND creado_por = :uid AND estado = 'pendiente'"
        );
        $st->execute([':id' => (int)$id, ':uid' => $uid]);
        if ($st->rowCount() !== 1) {
            return ['success' => false, 'mensaje' => 'Solo puedes cancelar tus pedidos que sigan pendientes.'];
        }
        return ['success' => true, 'mensaje' => 'Pedido cancelado.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    requierePermiso('mis_pedidos', 'escritura');

    $ctrl   = new MisPedidosCtrl();
    $uid    = (int)$_SESSION['usuario_id'];
    $accion = $_POST['accion'];

    if ($accion === 'crear') {
        $resultado = $ctrl->crear($_POST, $uid, (string)($_SESSION['nombre'] ?? ''), (string)($_SESSION['email'] ?? ''));
    } elseif ($accion === 'cancelar') {
        $resultado = $ctrl->cancelar(is_string($_POST['pedido_id'] ?? null) ? $_POST['pedido_id'] : '', $uid);
    } else {
        $resultado = ['success' => false, 'mensaje' => 'Acción no permitida'];
    }

    if (!empty($resultado['success'])) {
        auditar($accion === 'crear' ? 'crear_pedido' : 'cancelar_pedido', 'mis_pedidos',
                $resultado['id'] ?? ($_POST['pedido_id'] ?? null), $resultado['mensaje']);
    }
    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = !empty($resultado['success']) ? 'success' : 'error';
    header('Location: ../vista/mis_pedidos.php');
    exit();
}
?>
