<?php
// config.php
// Configuración general. NO contiene credenciales: todo se lee del archivo .env
// (carpeta padre del proyecto; ver env.ejemplo y modelo/env.php).

require_once __DIR__ . '/modelo/env.php';

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '5432'));
define('DB_NAME', env('DB_NAME', 'sistema_login'));
define('DB_USER', env('DB_USER', 'postgres'));
define('DB_PASS', env('DB_PASS', ''));

define('APP_NAME', 'Sistema de Login');

// Errores: en producción NUNCA se muestran en pantalla (revelan rutas y consultas); van al registro del servidor.
// Para verlos mientras desarrollas, pon APP_DEBUG=true en el .env.
$__depurar = strtolower((string)env('APP_DEBUG', 'false')) === 'true';
error_reporting(E_ALL);
ini_set('display_errors', $__depurar ? '1' : '0');
ini_set('log_errors', '1');
unset($__depurar);

// Sesión: cookie solo por HTTP(S) (no accesible desde JavaScript), sin enviarse desde otros sitios,
// y "Secure" cuando la conexión es HTTPS. Evita también que se acepte un identificador de sesión inventado.
if (session_status() === PHP_SESSION_NONE) {
    $__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $__https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($__https);
    session_start();
}
?>
