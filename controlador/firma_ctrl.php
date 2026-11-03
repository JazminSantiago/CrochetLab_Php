<?php
// controlador/firma_ctrl.php
// Emite una versión firmada digitalmente del comprobante de un pedido (solo POST).
// Requiere permiso de ESCRITURA en pedidos; el token CSRF lo comprueba requierePermiso().
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/../modelo/firma.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ../vista/pedidos/index_pedidos.php');
    exit();
}
requierePermiso('pedidos', 'escritura');

$pedidoId = (int)($_POST['pedido_id'] ?? 0);
$destino  = '../vista/comprobante_pedido.php?id=' . $pedidoId;

try {
    $db      = (new Conexion())->conectar();
    $version = firmaEmitir($db, $pedidoId, (int)$_SESSION['usuario_id']);
    auditar('firmar_comprobante', 'pedidos', $pedidoId, "Comprobante firmado digitalmente (versión $version)");
    $_SESSION['mensaje']      = "Comprobante firmado correctamente (versión $version).";
    $_SESSION['tipo_mensaje'] = 'success';
} catch (Throwable $e) {
    error_log('firma_ctrl: ' . $e->getMessage());
    $_SESSION['mensaje']      = 'No se pudo firmar el comprobante: ' . $e->getMessage();
    $_SESSION['tipo_mensaje'] = 'error';
    if ($pedidoId <= 0) {
        $destino = '../vista/pedidos/index_pedidos.php';
    }
}
header('Location: ' . $destino);
exit();
