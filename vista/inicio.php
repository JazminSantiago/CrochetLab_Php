<?php
// vista/inicio.php
// Inicio genérico para roles creados por el administrador: muestra solo los módulos
// que el rol del usuario puede abrir.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/_cuenta_layout.php';
requiereSesion();

$modulos = modulosDisponibles();
if (!$modulos) {
    denegarAcceso();   // sin ningún permiso: cierra la sesión
}
$base = appBase();
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

cuentaInicio('Hola, ' . ($_SESSION['nombre'] ?? ''), 'Rol: ' . ($_SESSION['rol'] ?? '') . '. Estos son los módulos a los que tienes acceso.', 'ancha');
?>
    <div class="codigos" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));">
        <?php foreach ($modulos as $m): ?>
            <a class="btn sec" style="padding:16px;" href="<?php echo $h($base . '/' . $m['ruta']); ?>"><?php echo $m['icono'] . ' ' . $h($m['nombre']); ?></a>
        <?php endforeach; ?>
    </div>
    <div class="enlaces" style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a href="mi_cuenta.php">Mi cuenta</a>
        <form action="../controlador/validar_usuario.php" method="POST" style="margin:0;">
            <?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" value="logout">
            <button type="submit" class="btn sec auto">Cerrar sesión</button>
        </form>
    </div>
<?php cuentaFin(); ?>
