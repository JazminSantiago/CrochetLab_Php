<?php
// vista/mi_cuenta.php
// Perfil del usuario: cambiar contraseña y gestionar la verificación en dos pasos (TOTP).
// Disponible para cualquier usuario con sesión.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/_cuenta_layout.php';
requiereSesion();

$db = (new Conexion())->conectar();
$st = $db->prepare(
    "SELECT u.usuario, u.nombre, u.email, u.totp_activo, r.nombre AS rol,
            (SELECT COUNT(*) FROM codigos_respaldo c WHERE c.usuario_id = u.id AND c.usado = FALSE) AS codigos_libres
     FROM usuarios u JOIN roles r ON r.id = u.rol_id
     WHERE u.id = :id"
);
$st->execute([':id' => $_SESSION['usuario_id']]);
$u = $st->fetch();

$obligatorio = ADMIN_REQUIERE_2FA && tienePermiso('usuarios', 'escritura');
$bloqueadoPor2fa = $obligatorio && !$u['totp_activo'];   // aún no puede usar el resto del sistema

// Secreto pendiente de confirmar (solo vive en la sesión hasta que el usuario escribe un código válido)
$secreto = '';
$uri     = '';
if (!$u['totp_activo']) {
    if (empty($_SESSION['totp_pendiente'])) {
        $_SESSION['totp_pendiente'] = totpGenerarSecreto();
    }
    $secreto = $_SESSION['totp_pendiente'];
    $uri     = totpUri($secreto, $u['usuario']);
}

// Los códigos de respaldo recién generados se muestran una sola vez
$codigosNuevos = $_SESSION['codigos_nuevos'] ?? null;
unset($_SESSION['codigos_nuevos']);

if (isset($_GET['forzar_2fa']) && $bloqueadoPor2fa && empty($_SESSION['mensaje'])) {
    $_SESSION['mensaje']      = 'Tu rol requiere la verificación en dos pasos. Actívala para continuar.';
    $_SESSION['tipo_mensaje'] = 'aviso';
}

cuentaInicio('Mi cuenta', '', 'ancha');
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
    <?php if (!$bloqueadoPor2fa): ?>
        <a class="volver" href="<?php echo $h(rutaInicio() ?? '../index.php'); ?>">← Volver a mi panel</a>
    <?php else: ?>
        <form action="../controlador/validar_usuario.php" method="POST" style="margin-bottom:12px;">
            <?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" value="logout">
            <button type="submit" class="btn sec auto">Cerrar sesión</button>
        </form>
    <?php endif; ?>

    <?php if ($codigosNuevos): ?>
    <div class="seccion" style="border-color:var(--coral);">
        <h3>🔐 Tus códigos de respaldo</h3>
        <p class="sub">Guárdalos ahora en un lugar seguro. Cada uno funciona <strong>una sola vez</strong> si pierdes tu teléfono, y no se volverán a mostrar.</p>
        <div class="codigos">
            <?php foreach ($codigosNuevos as $c): ?><code><?php echo $h($c); ?></code><?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="seccion">
        <h3>Datos de la cuenta</h3>
        <div class="fila"><span>Nombre</span><span><?php echo $h($u['nombre']); ?></span></div>
        <div class="fila"><span>Usuario</span><span><?php echo $h($u['usuario']); ?></span></div>
        <div class="fila"><span>Correo</span><span><?php echo $h($u['email']); ?></span></div>
        <div class="fila"><span>Rol</span><span><?php echo $h($u['rol']); ?></span></div>
    </div>

    <div class="seccion">
        <h3>Verificación en dos pasos
            <span class="badge <?php echo $u['totp_activo'] ? 'ok' : 'off'; ?>"><?php echo $u['totp_activo'] ? 'Activada' : 'Desactivada'; ?></span>
        </h3>

        <?php if (!$u['totp_activo']): ?>
            <p class="sub">Protege tu cuenta con un código que cambia cada 30 segundos. Usa una app como Google Authenticator, Microsoft Authenticator o Authy.</p>
            <ol class="sub" style="margin-left:18px;">
                <li>Abre la app y elige «Escanear código QR» (o «Ingresar clave de configuración»).</li>
                <li>Escribe aquí abajo el código de 6 dígitos que muestre la app.</li>
            </ol>
            <div class="qrbox">
                <div id="qr"></div>
                <div style="flex:1;min-width:200px;">
                    <div class="ayuda">¿No puedes escanear? Ingresa esta clave manualmente (tipo: basada en tiempo):</div>
                    <div class="clave"><?php echo $h(trim(chunk_split($secreto, 4, ' '))); ?></div>
                </div>
            </div>
            <form action="../controlador/cuenta_ctrl.php" method="POST" autocomplete="off">
                <?php echo campoCsrf(); ?>
                <input type="hidden" name="accion" value="activar_2fa">
                <div class="campo">
                    <label for="codigo_activar">Código de 6 dígitos</label>
                    <input type="text" id="codigo_activar" name="codigo" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="123456">
                </div>
                <button type="submit" class="btn coral">Activar verificación en dos pasos</button>
            </form>
        <?php else: ?>
            <p class="sub">Al iniciar sesión se te pedirá un código de tu app autenticadora. Códigos de respaldo disponibles: <strong><?php echo (int)$u['codigos_libres']; ?></strong>.</p>

            <form action="../controlador/cuenta_ctrl.php" method="POST" autocomplete="off" style="margin-bottom:14px;">
                <?php echo campoCsrf(); ?>
                <input type="hidden" name="accion" value="regenerar_codigos">
                <div class="campo">
                    <label for="pw_regen">Contraseña actual</label>
                    <input type="password" id="pw_regen" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn sec">Generar nuevos códigos de respaldo</button>
            </form>

            <?php if ($obligatorio): ?>
                <p class="ayuda">Tu rol requiere la verificación en dos pasos, por eso no se puede desactivar.</p>
            <?php else: ?>
            <form action="../controlador/cuenta_ctrl.php" method="POST" autocomplete="off">
                <?php echo campoCsrf(); ?>
                <input type="hidden" name="accion" value="desactivar_2fa">
                <div class="campo">
                    <label for="pw_des">Contraseña actual</label>
                    <input type="password" id="pw_des" name="password" required autocomplete="current-password">
                </div>
                <div class="campo">
                    <label for="cod_des">Código de verificación</label>
                    <input type="text" id="cod_des" name="codigo" required maxlength="20" placeholder="6 dígitos o código de respaldo">
                </div>
                <button type="submit" class="btn peligro">Desactivar verificación en dos pasos</button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!$bloqueadoPor2fa): ?>
    <div class="seccion">
        <h3>Cambiar contraseña</h3>
        <form action="../controlador/cuenta_ctrl.php" method="POST" autocomplete="off">
            <?php echo campoCsrf(); ?>
            <input type="hidden" name="accion" value="cambiar_password">
            <div class="campo">
                <label for="actual">Contraseña actual</label>
                <input type="password" id="actual" name="actual" required autocomplete="current-password">
            </div>
            <div class="campo">
                <label for="nueva">Nueva contraseña</label>
                <input type="password" id="nueva" name="nueva" required minlength="8" maxlength="72" autocomplete="new-password">
                <div class="ayuda">Mínimo 8 caracteres, con mayúsculas, minúsculas y números.</div>
            </div>
            <div class="campo">
                <label for="confirmar">Confirmar nueva contraseña</label>
                <input type="password" id="confirmar" name="confirmar" required minlength="8" maxlength="72" autocomplete="new-password">
            </div>
            <button type="submit" class="btn">Cambiar contraseña</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($uri !== ''): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // Si la librería no carga (sin internet), queda la clave manual de arriba
        if (window.QRCode) {
            new QRCode(document.getElementById('qr'), {
                text: <?php echo json_encode($uri, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M
            });
        }
    </script>
    <?php endif; ?>
<?php cuentaFin(); ?>
