<?php
// vista/verificar_2fa.php — segunda fase del inicio de sesión
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_cuenta_layout.php';

$pre = $_SESSION['pre2fa'] ?? null;
if (!is_array($pre) || (time() - (int)$pre['t']) > 300) {
    unset($_SESSION['pre2fa']);
    header('Location: ../index.php');
    exit();
}

cuentaInicio('Verificación en dos pasos', 'Escribe el código de 6 dígitos de tu app autenticadora, o uno de tus códigos de respaldo.');
?>
    <form action="../controlador/validar_usuario.php" method="POST" autocomplete="off">
        <?php echo campoCsrf(); ?>
        <input type="hidden" name="accion" value="verificar_2fa">
        <div class="campo">
            <label for="codigo">Código</label>
            <input type="text" id="codigo" name="codigo" required autofocus maxlength="20"
                   inputmode="text" autocomplete="one-time-code" placeholder="123456">
        </div>
        <button type="submit" class="btn coral">Verificar</button>
    </form>
    <div class="enlaces"><a href="../index.php">← Cancelar</a></div>
<?php cuentaFin(); ?>
