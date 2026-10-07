<?php
// modelo/seguridad.php
// Utilidades de seguridad: CSRF, política de contraseñas, segundo factor (TOTP) y códigos de respaldo.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/registro.php';

// ── Parámetros ──
if (!defined('LOGIN_MAX_INTENTOS'))    define('LOGIN_MAX_INTENTOS', 5);     // fallos por cuenta antes de bloquear
if (!defined('LOGIN_BLOQUEO_MINUTOS')) define('LOGIN_BLOQUEO_MINUTOS', 15); // duración del bloqueo
if (!defined('LOGIN_MAX_FALLOS_IP'))   define('LOGIN_MAX_FALLOS_IP', 20);   // fallos por IP en la ventana
if (!defined('LOGIN_VENTANA_IP_MIN'))  define('LOGIN_VENTANA_IP_MIN', 15);
if (!defined('ADMIN_REQUIERE_2FA'))    define('ADMIN_REQUIERE_2FA', strtolower((string)env('ADMIN_REQUIERE_2FA', 'true')) !== 'false');

// ═══════════════ Bloqueo de cuentas ═══════════════

// Suma un fallo; al llegar al máximo bloquea la cuenta unos minutos y reinicia el contador.
function registrarFalloCuenta(PDO $db, $idUsuario): void
{
    $max = (int)LOGIN_MAX_INTENTOS;
    $min = (int)LOGIN_BLOQUEO_MINUTOS;
    $db->prepare(
        "UPDATE usuarios SET
            bloqueado_hasta   = CASE WHEN intentos_fallidos + 1 >= $max
                                     THEN NOW() + INTERVAL '$min minutes' ELSE bloqueado_hasta END,
            intentos_fallidos = CASE WHEN intentos_fallidos + 1 >= $max
                                     THEN 0 ELSE intentos_fallidos + 1 END
         WHERE id = :id"
    )->execute([':id' => $idUsuario]);
}

function cuentaBloqueada(PDO $db, $idUsuario): bool
{
    $st = $db->prepare("SELECT (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) FROM usuarios WHERE id = :id");
    $st->execute([':id' => $idUsuario]);
    return (bool)$st->fetchColumn();
}

// ═══════════════ CSRF ═══════════════

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') . '">';
}

// Corta la petición POST si el token no coincide. Con $redirigirA vuelve a esa página con un aviso.
function verificarCsrf(?string $redirigirA = null): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $esperado = $_SESSION['csrf_token'] ?? '';
    $recibido = $_POST['csrf_token'] ?? '';
    if ($esperado !== '' && is_string($recibido) && hash_equals($esperado, $recibido)) {
        return;
    }

    auditar('csrf_invalido', 'seguridad', null, 'Formulario sin token válido en ' . ($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($redirigirA !== null) {
        $_SESSION['mensaje']      = 'Tu sesión expiró o el formulario no es válido. Inténtalo de nuevo.';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ' . $redirigirA);
        exit();
    }

    http_response_code(419);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Solicitud no válida</title></head>'
       . '<body style="font-family:sans-serif;text-align:center;padding:60px 20px;color:#2C3E6B;background:#f0f8fa;">'
       . '<h1>Solicitud no válida</h1>'
       . '<p>La página expiró o el formulario no es auténtico. Vuelve atrás, recarga la página e inténtalo de nuevo.</p>'
       . '</body></html>';
    exit();
}

// ═══════════════ Política de contraseñas ═══════════════

// Devuelve el mensaje de error, o null si la contraseña es aceptable.
function validarPassword(string $p): ?string
{
    if (strlen($p) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (strlen($p) > 72) {
        return 'La contraseña no puede superar 72 caracteres.';
    }
    if (!preg_match('/[a-z]/', $p) || !preg_match('/[A-Z]/', $p) || !preg_match('/\d/', $p)) {
        return 'La contraseña debe incluir mayúsculas, minúsculas y números.';
    }
    return null;
}

function hashPassword(string $p): string
{
    return password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]);
}

// ═══════════════ TOTP (RFC 6238) ═══════════════

const B32_ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32Encode(string $bin): string
{
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $trozo) {
        $out .= B32_ALFABETO[bindec(str_pad($trozo, 5, '0'))];
    }
    return $out;
}

function base32Decode(string $b32): string
{
    $b32  = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $c) {
        $bits .= str_pad(decbin(strpos(B32_ALFABETO, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totpGenerarSecreto(): string
{
    return base32Encode(random_bytes(20));   // 160 bits → 32 caracteres
}

function totpCodigo(string $secretoB32, int $paso): string
{
    $hash   = hash_hmac('sha1', pack('J', $paso), base32Decode($secretoB32), true);
    $offset = ord($hash[19]) & 0x0F;
    $num    = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            |  ord($hash[$offset + 3]);
    return str_pad((string)($num % 1000000), 6, '0', STR_PAD_LEFT);
}

// Devuelve el paso de 30 s que coincidió (para evitar reutilizar el código) o null.
function totpVerificar(string $secretoB32, string $codigo, int $ultimoPaso = 0, int $ventana = 1): ?int
{
    if (!preg_match('/^\d{6}$/', $codigo) || $secretoB32 === '') {
        return null;
    }
    $ahora = intdiv(time(), 30);
    for ($d = -$ventana; $d <= $ventana; $d++) {
        $paso = $ahora + $d;
        if ($paso <= $ultimoPaso) {
            continue;
        }
        if (hash_equals(totpCodigo($secretoB32, $paso), $codigo)) {
            return $paso;
        }
    }
    return null;
}

function totpUri(string $secretoB32, string $usuario): string
{
    return 'otpauth://totp/' . rawurlencode('CrochetLab:' . $usuario)
         . '?secret=' . $secretoB32 . '&issuer=CrochetLab&algorithm=SHA1&digits=6&period=30';
}

// Comprueba un código TOTP del usuario (y guarda el paso usado) o, si no son 6 dígitos,
// un código de respaldo (que queda consumido).
function verificarSegundoFactor(PDO $db, array $usuario, string $codigo): bool
{
    $codigo = preg_replace('/[\s-]/', '', $codigo);

    if (preg_match('/^\d{6}$/', $codigo)) {
        $paso = totpVerificar((string)$usuario['totp_secreto'], $codigo, (int)$usuario['totp_ultimo_paso']);
        if ($paso === null) {
            return false;
        }
        $db->prepare("UPDATE usuarios SET totp_ultimo_paso = :p WHERE id = :id")
           ->execute([':p' => $paso, ':id' => $usuario['id']]);
        return true;
    }
    return consumirCodigoRespaldo($db, (int)$usuario['id'], $codigo);
}

// ═══════════════ Códigos de respaldo ═══════════════

function generarCodigosRespaldo(PDO $db, int $uid, int $cantidad = 10): array
{
    $db->prepare("DELETE FROM codigos_respaldo WHERE usuario_id = :u")->execute([':u' => $uid]);

    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // sin I ni O para no confundir
    $ins      = $db->prepare("INSERT INTO codigos_respaldo (usuario_id, codigo_hash) VALUES (:u, :h)");
    $codigos  = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $c = '';
        for ($j = 0; $j < 10; $j++) {
            $c .= $alfabeto[random_int(0, 31)];
        }
        $ins->execute([':u' => $uid, ':h' => hash('sha256', $c)]);
        $codigos[] = substr($c, 0, 5) . '-' . substr($c, 5);
    }
    return $codigos;
}

function consumirCodigoRespaldo(PDO $db, int $uid, string $codigo): bool
{
    $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
    if (strlen($c) !== 10) {
        return false;
    }
    $st = $db->prepare(
        "UPDATE codigos_respaldo SET usado = TRUE, fecha_uso = NOW()
         WHERE usuario_id = :u AND codigo_hash = :h AND usado = FALSE"
    );
    $st->execute([':u' => $uid, ':h' => hash('sha256', $c)]);
    return $st->rowCount() === 1;
}
