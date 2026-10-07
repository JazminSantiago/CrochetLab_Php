<?php
// vista/mis_pedidos.php
// Pedidos del cliente: formulario para pedir y lista de SUS pedidos.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/_cuenta_layout.php';
require_once __DIR__ . '/../controlador/mis_pedidos_ctrl.php';
requierePermiso('mis_pedidos', 'lectura');

$ctrl      = new MisPedidosCtrl();
$uid       = (int)$_SESSION['usuario_id'];
$puedePedir = tienePermiso('mis_pedidos', 'escritura');
$pedidos   = $ctrl->listar($uid);
$productos = $puedePedir ? $ctrl->productos() : [];
$preElegido = ctype_digit((string)($_GET['producto'] ?? '')) ? (int)$_GET['producto'] : 0;

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$estados = [
    'pendiente'  => ['Pendiente',  'warn'],
    'en_proceso' => ['En proceso', 'neu'],
    'completado' => ['Listo',      'ok'],
    'entregado'  => ['Entregado',  'ok'],
    'cancelado'  => ['Cancelado',  'off'],
];

cuentaInicio('Mis pedidos', 'Haz un pedido del catálogo o describe uno personalizado, y sigue su estado.', 'ancha');
?>
    <a class="volver" href="<?php echo $h(rutaInicio() ?? '../index.php'); ?>">← Volver a mi panel</a>

    <?php if ($puedePedir): ?>
    <div class="seccion">
        <h3>Nuevo pedido</h3>
        <?php if (!$productos): ?>
            <p class="sub">Todavía no hay productos disponibles en el catálogo, pero puedes enviar un pedido personalizado.</p>
        <?php endif; ?>
        <form action="../controlador/mis_pedidos_ctrl.php" method="POST" autocomplete="off">
            <?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" value="crear">
            <div class="campo">
                <label for="tipo">Tipo de pedido</label>
                <select id="tipo" name="tipo">
                    <option value="estandar" <?php echo $productos ? '' : 'disabled'; ?>>Producto del catálogo</option>
                    <option value="personalizado" <?php echo $productos ? '' : 'selected'; ?>>Pedido personalizado</option>
                </select>
            </div>
            <div class="campo">
                <label for="catalogo_id">Producto</label>
                <select id="catalogo_id" name="catalogo_id">
                    <option value="">— Elige un producto —</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>" <?php echo $preElegido === (int)$p['id'] ? 'selected' : ''; ?>>
                            <?php echo $h($p['nombre']); ?><?php echo $p['precio'] !== null ? ' — $' . number_format((float)$p['precio'], 2) : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="ayuda">Obligatorio en pedidos del catálogo; opcional como referencia en uno personalizado.</div>
            </div>
            <div class="campo">
                <label for="fecha_entrega">Fecha de entrega deseada</label>
                <input type="date" id="fecha_entrega" name="fecha_entrega" required min="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="campo">
                <label for="descripcion">Descripción o notas</label>
                <textarea id="descripcion" name="descripcion" maxlength="500" placeholder="Colores, tamaño, detalles…"></textarea>
                <div class="ayuda">Obligatoria en pedidos personalizados (mínimo 10 caracteres). Máximo 500.</div>
            </div>
            <button type="submit" class="btn coral">Enviar pedido</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="seccion">
        <h3>Mis pedidos</h3>
        <?php if (!$pedidos): ?>
            <div class="vacio">Aún no has hecho ningún pedido.</div>
        <?php else: ?>
        <div class="tabla-wrap">
            <table class="tabla">
                <thead><tr><th>#</th><th>Pedido</th><th>Entrega</th><th>Estado</th><th>Hecho el</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pedidos as $p):
                    [$etq, $clase] = $estados[$p['estado']] ?? [$p['estado'], 'neu']; ?>
                    <tr>
                        <td><?php echo (int)$p['id']; ?></td>
                        <td>
                            <strong><?php echo $h($p['producto_nombre'] ?? ($p['tipo'] === 'personalizado' ? 'Personalizado' : '—')); ?></strong>
                            <?php if (!empty($p['descripcion'])): ?><div class="ayuda"><?php echo $h($p['descripcion']); ?></div><?php endif; ?>
                        </td>
                        <td><?php echo $h($p['fecha_entrega']); ?></td>
                        <td><span class="badge <?php echo $clase; ?>"><?php echo $h($etq); ?></span></td>
                        <td><?php echo $h(substr((string)$p['fecha_pedido'], 0, 16)); ?></td>
                        <td>
                            <?php if ($puedePedir && $p['estado'] === 'pendiente'): ?>
                            <form action="../controlador/mis_pedidos_ctrl.php" method="POST" onsubmit="return confirm('¿Cancelar este pedido?');">
                                <?php echo campoCsrf(); ?>
                                <input type="hidden" name="accion" value="cancelar">
                                <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                                <button type="submit" class="btn peligro auto">Cancelar</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
<?php cuentaFin(); ?>
