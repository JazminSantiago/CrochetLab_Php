<?php
// vista/comprobante_pedido.php
// Comprobante de un pedido con su firma digital y el resultado de la verificación.
// Ver requiere lectura en pedidos; firmar (emitir versión nueva) requiere escritura.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/../modelo/firma.php';
require_once __DIR__ . '/_admin_layout.php';
requierePermiso('pedidos', 'lectura');

$id = (int)($_GET['id'] ?? 0);
$db = (new Conexion())->conectar();

$pedido = $id > 0 ? firmaPedidoConsulta($db, $id) : null;
if (!$pedido) {
    $_SESSION['mensaje']      = 'El pedido no existe.';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: pedidos/index_pedidos.php');
    exit();
}

$version  = isset($_GET['v']) ? (int)$_GET['v'] : null;
$error    = null;
$res      = ['estado' => 'sin_firma', 'firma' => null, 'diferencias' => []];
$historial = [];
try {
    $historial = firmaHistorial($db, $id);
    $res       = firmaVerificarPedido($db, $id, $version);
} catch (Throwable $e) {
    error_log('comprobante_pedido: ' . $e->getMessage());
    $error = $e->getMessage();
}

$puedeFirmar = tienePermiso('pedidos', 'escritura');
$datosHoy    = firmaDatosPedido($pedido);
$fila        = $res['firma'];
$verFirmada  = $fila ? (int)$fila['version'] : null;

$etiquetas = [
    'pedido_id' => 'Pedido', 'tipo' => 'Tipo', 'producto' => 'Producto', 'descripcion' => 'Descripción',
    'cliente' => 'Cliente', 'fecha_pedido' => 'Fecha del pedido', 'fecha_entrega' => 'Fecha de entrega',
    'entrega_domicilio' => 'Entrega a domicilio', 'costo_envio' => 'Costo de envío',
];
function valorLegible($v): string
{
    if (is_bool($v)) {
        return $v ? 'Sí' : 'No';
    }
    return (string)$v === '' ? '—' : (string)$v;
}

adminInicio('Comprobante del pedido #' . $id, 'Firma digital RSA-2048 con SHA-256: autentica el comprobante y detecta cualquier alteración.');
?>
        <style>
            .estado-firma { padding:16px 18px; border-radius:14px; border:1.5px solid; margin-bottom:16px; font-weight:700; line-height:1.5; }
            .estado-firma small { display:block; font-weight:600; opacity:.85; margin-top:4px; }
            .estado-firma.valida     { background:#eaf8f4; color:#2f8a78; border-color:var(--mint); }
            .estado-firma.modificado { background:#fff8e6; color:#8a6a10; border-color:#f3d27a; }
            .estado-firma.invalida   { background:#fff0ed; color:#b04a37; border-color:var(--coral); }
            .estado-firma.sin_firma  { background:var(--sky-pale); color:var(--navy-light); border-color:var(--border); }
            .kv th { width:34%; text-transform:none; letter-spacing:0; font-size:13px; }
            .mono { font-family:ui-monospace,Consolas,monospace; font-size:12px; word-break:break-all; color:var(--navy); }
            pre.mono { background:var(--bg); border:1.5px solid var(--border); border-radius:10px; padding:12px; overflow-x:auto; white-space:pre-wrap; }
            .dif td:nth-child(2) { color:#2f8a78; } .dif td:nth-child(3) { color:#c0513c; }
        </style>

        <div class="acciones" style="margin-bottom:16px;">
            <a class="btn sec" href="pedidos/index_pedidos.php<?php echo $pedido['tipo'] === 'personalizado' ? '?tab=personalizado' : ''; ?>">← Pedidos</a>
            <?php if ($puedeFirmar): ?>
            <form method="post" action="../controlador/firma_ctrl.php">
                <?php echo campoCsrf(); ?>
                <input type="hidden" name="pedido_id" value="<?php echo $id; ?>">
                <button class="btn" type="submit">🔏 <?php echo $res['estado'] === 'sin_firma' ? 'Firmar comprobante' : 'Emitir nueva versión firmada'; ?></button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="mensaje error">No se pudo verificar: <?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if ($res['estado'] === 'valida'): ?>
            <div class="estado-firma valida">✔ Firma válida: el comprobante es auténtico y el pedido no ha cambiado desde que se firmó.
                <small>Versión <?php echo $verFirmada; ?> · verificada ahora mismo con la llave pública.</small></div>
        <?php elseif ($res['estado'] === 'modificado'): ?>
            <div class="estado-firma modificado">⚠ La firma es auténtica, pero el pedido cambió después de firmarse.
                <small>Lo firmado (versión <?php echo $verFirmada; ?>) ya no coincide con los datos actuales. Revisa las diferencias y emite una versión nueva si el cambio es correcto.</small></div>
        <?php elseif ($res['estado'] === 'invalida'): ?>
            <div class="estado-firma invalida">✖ Firma NO válida: el contenido firmado o la firma fueron alterados, o no corresponden a la llave pública del sistema.
                <small>No confíes en este comprobante.</small></div>
        <?php else: ?>
            <div class="estado-firma sin_firma">Este pedido todavía no tiene comprobante firmado.
                <small><?php echo $puedeFirmar ? 'Usa «Firmar comprobante» para emitirlo.' : 'Pide a una persona con permiso de escritura que lo firme.'; ?></small></div>
        <?php endif; ?>

        <?php if ($res['estado'] === 'modificado'): ?>
        <div class="panel">
            <h2>Qué cambió</h2>
            <div class="tabla-wrap"><table class="dif">
                <thead><tr><th>Campo</th><th>Firmado</th><th>Ahora</th></tr></thead>
                <tbody>
                <?php foreach ($res['diferencias'] as $campo => $d): ?>
                    <tr><td><?php echo h($etiquetas[$campo] ?? $campo); ?></td>
                        <td><?php echo h(valorLegible($d['firmado'])); ?></td>
                        <td><?php echo h(valorLegible($d['actual'])); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
        <?php endif; ?>

        <div class="panel">
            <h2>Datos del comprobante</h2>
            <div class="tabla-wrap"><table class="kv"><tbody>
                <?php foreach ($etiquetas as $campo => $etq): ?>
                    <tr><th><?php echo h($etq); ?></th><td><?php echo h(valorLegible($datosHoy[$campo])); ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <p class="sub" style="margin:10px 0 0;">El contacto y la dirección del cliente están cifrados en la base y no forman parte del comprobante.</p>
        </div>

        <?php if ($fila): ?>
        <div class="panel">
            <h2>Firma digital (versión <?php echo $verFirmada; ?>)</h2>
            <div class="tabla-wrap"><table class="kv"><tbody>
                <tr><th>Algoritmo</th><td><?php echo h(FIRMA_ALGORITMO_TEXTO); ?></td></tr>
                <tr><th>Firmado por</th><td><?php echo h($fila['firmado_por_usuario'] ?? '—'); ?> · <?php echo h($fila['firmado_en']); ?></td></tr>
                <tr><th>Huella de la llave pública</th><td class="mono"><?php echo h($fila['huella_llave']); ?>
                    <?php if (isset($res['misma_llave']) && !$res['misma_llave']): ?><br><span class="badge mal">no coincide con la llave pública actual</span><?php endif; ?></td></tr>
                <tr><th>Firma (base64)</th><td class="mono"><?php echo h($fila['firma']); ?></td></tr>
            </tbody></table></div>
            <p class="sub" style="margin:14px 0 6px;">Contenido exacto que se firmó:</p>
            <pre class="mono"><?php echo h($fila['contenido']); ?></pre>

            <p class="sub" style="margin:14px 0 6px;">Verificación independiente (sin este sistema), con OpenSSL:</p>
            <div class="acciones" style="margin-bottom:10px;">
                <a class="btn sec" href="comprobante_descarga.php?id=<?php echo $id; ?>&amp;v=<?php echo $verFirmada; ?>&amp;t=contenido">⬇ Contenido (.json)</a>
                <a class="btn sec" href="comprobante_descarga.php?id=<?php echo $id; ?>&amp;v=<?php echo $verFirmada; ?>&amp;t=firma">⬇ Firma (.firma)</a>
                <a class="btn sec" href="comprobante_descarga.php?id=<?php echo $id; ?>&amp;t=publica">⬇ Llave pública (.pem)</a>
            </div>
            <pre class="mono">openssl dgst -sha256 -verify clave_publica.pem -signature comprobante_<?php echo $id; ?>_v<?php echo $verFirmada; ?>.firma comprobante_<?php echo $id; ?>_v<?php echo $verFirmada; ?>.json</pre>
            <p class="sub" style="margin:6px 0 0;">Debe responder «Verified OK». Si se cambia una sola letra del .json, responde «Verification failure».</p>
        </div>
        <?php endif; ?>

        <?php if (count($historial) > 1): ?>
        <div class="panel">
            <h2>Versiones firmadas</h2>
            <div class="tabla-wrap"><table>
                <thead><tr><th>Versión</th><th>Firmado por</th><th>Fecha</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($historial as $hv): ?>
                    <tr><td>v<?php echo (int)$hv['version']; ?></td>
                        <td><?php echo h($hv['firmado_por_usuario'] ?? '—'); ?></td>
                        <td><?php echo h($hv['firmado_en']); ?></td>
                        <td><a class="btn sec" href="comprobante_pedido.php?id=<?php echo $id; ?>&amp;v=<?php echo (int)$hv['version']; ?>">Verificar esta versión</a></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
        <?php endif; ?>
<?php adminFin(); ?>
