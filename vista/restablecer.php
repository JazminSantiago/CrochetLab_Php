<?php
// vista/restablecer.php — nueva contraseña a partir del enlace recibido por correo
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_cuenta_layout.php';
require_once __DIR__ . '/../controlador/recuperar_ctrl.php';

$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$fila  = (new RecuperarCtrl())->buscarToken($token);

if (!$fila) {
    cuentaInicio('Enlace no válido', 'El enlace no es válido o ya expiró. Puedes solicitar uno nuevo.');
    echo '<a class="btn coral" href="recuperar.php">Solicitar otro enlace</a>';
    cuentaFin();
    exit();
}

cuentaInicio('Nueva contraseña', 'Elige una contraseña nueva para la cuenta «' . $fila['usuario'] . '».');
?>
    <form action="../controlador/recuperar_ctrl.php" method="POST" autocomplete="off">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="accion" value="restablecer">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="campo">
            <label for="nueva">Nueva contraseña</label>
            <input type="password" id="nueva" name="nueva" required minlength="8" maxlength="72" autocomplete="new-password">
            <div class="ayuda">Mínimo 8 caracteres, con mayúsculas, minúsculas y números.</div>
        </div>
        <div class="campo">
            <label for="confirmar">Confirmar contraseña</label>
            <input type="password" id="confirmar" name="confirmar" required minlength="8" maxlength="72" autocomplete="new-password">
        </div>
        <?php if ($fila['totp_activo']): ?>
        <div class="campo">
            <label for="codigo">Código de verificación</label>
            <input type="text" id="codigo" name="codigo" required maxlength="20" autocomplete="one-time-code" placeholder="6 dígitos o código de respaldo">
            <div class="ayuda">Tu cuenta tiene verificación en dos pasos: escribe el código de tu app autenticadora.</div>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn coral">Cambiar contraseña</button>
    </form>
<?php cuentaFin(); ?>
