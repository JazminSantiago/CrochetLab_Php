<?php
// vista/registro.php — creación de cuenta (rol "Usuario Regular")
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_cuenta_layout.php';

if (!empty($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit();
}

cuentaInicio('Crear cuenta', 'Regístrate para consultar el catálogo y los patrones de CrochetLab.');
?>
    <form action="../controlador/ingresar_usuario.php" method="POST" autocomplete="off">
        <?php echo campoCsrf(); ?>
        <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" required maxlength="100" placeholder="Tu nombre completo">
        </div>
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" required maxlength="150" placeholder="tucorreo@ejemplo.com">
        </div>
        <div class="campo">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_.\-]+" placeholder="Elige un usuario">
            <div class="ayuda">De 3 a 30 caracteres: letras, números, punto, guion o guion bajo.</div>
        </div>
        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
            <div class="ayuda">Mínimo 8 caracteres, con mayúsculas, minúsculas y números.</div>
        </div>
        <div class="campo">
            <label for="confirmar">Confirmar contraseña</label>
            <input type="password" id="confirmar" name="confirmar" required minlength="8" maxlength="72" autocomplete="new-password">
        </div>
        <button type="submit" class="btn coral">Crear cuenta</button>
    </form>
    <div class="enlaces">¿Ya tienes cuenta? <a href="../index.php">Inicia sesión</a></div>
<?php cuentaFin(); ?>
