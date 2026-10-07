<?php
// vista/recuperar.php — solicitud de enlace para restablecer la contraseña
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_cuenta_layout.php';

cuentaInicio('Recuperar contraseña', 'Escribe el correo de tu cuenta y te enviaremos un enlace para crear una contraseña nueva.');
?>
    <form action="../controlador/recuperar_ctrl.php" method="POST">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="accion" value="solicitar">
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" required maxlength="150" autocomplete="email" placeholder="tucorreo@ejemplo.com">
        </div>
        <button type="submit" class="btn coral">Enviar enlace</button>
    </form>
    <div class="enlaces"><a href="../index.php">← Volver al inicio de sesión</a></div>
<?php cuentaFin(); ?>
