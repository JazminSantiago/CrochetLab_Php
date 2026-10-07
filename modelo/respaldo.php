<?php
// modelo/respaldo.php
// Funciones compartidas por database/respaldo.php y database/restaurar_respaldo.php.
//
// Un respaldo es un volcado de PostgreSQL (pg_dump, formato personalizado) cifrado con AES-256-GCM:
//   archivo = "CLBK1" + IV[12] + ETIQUETA[16] + TEXTO_CIFRADO
// El volcado nunca se escribe en disco sin cifrar: pg_dump lo entrega por una tubería a la memoria,
// se cifra y solo entonces se guarda en el destino (USB, disco externo...).
// La clave es RESPALDO_CLAVE del .env; si no existe se usa CIFRADO_CLAVE.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/env.php';

const RESPALDO_MAGIA = 'CLBK1';
const RESPALDO_AAD   = 'crochetlab-respaldo-v1';
const RESPALDO_MAX_BYTES = 400 * 1024 * 1024; // el cifrado se hace en memoria

function respaldoClave(): string
{
    foreach (['RESPALDO_CLAVE', 'CIFRADO_CLAVE'] as $nombre) {
        $b64 = (string)env($nombre, '');
        if ($b64 === '') {
            continue;
        }
        $raw = base64_decode($b64, true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException("$nombre no es válida (debe ser 32 bytes en base64).");
        }
        return $raw;
    }
    throw new RuntimeException('Falta la clave: define RESPALDO_CLAVE o CIFRADO_CLAVE en el .env (php database/generar_clave.php).');
}

function respaldoCifrar(string $datos): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($datos, 'aes-256-gcm', respaldoClave(), OPENSSL_RAW_DATA, $iv, $tag, RESPALDO_AAD, 16);
    if ($ct === false) {
        throw new RuntimeException('No se pudo cifrar el respaldo.');
    }
    return RESPALDO_MAGIA . $iv . $tag . $ct;
}

// Devuelve el volcado original o lanza excepción si el archivo no es un respaldo,
// fue alterado o la clave no corresponde.
function respaldoDescifrar(string $archivo): string
{
    $m = strlen(RESPALDO_MAGIA);
    if (strlen($archivo) < $m + 12 + 16 + 1 || substr($archivo, 0, $m) !== RESPALDO_MAGIA) {
        throw new RuntimeException('El archivo no es un respaldo de CrochetLab.');
    }
    $iv  = substr($archivo, $m, 12);
    $tag = substr($archivo, $m + 12, 16);
    $ct  = substr($archivo, $m + 28);
    $datos = openssl_decrypt($ct, 'aes-256-gcm', respaldoClave(), OPENSSL_RAW_DATA, $iv, $tag, RESPALDO_AAD);
    if ($datos === false) {
        throw new RuntimeException('No se pudo descifrar: el archivo está dañado/alterado o la clave no es la correcta.');
    }
    return $datos;
}

// Parámetros de conexión: .env primero, constantes de config.php como alternativa
function respaldoConexion(): array
{
    return [
        'host' => (string)env('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost'),
        'port' => (string)env('DB_PORT', defined('DB_PORT') ? DB_PORT : '5432'),
        'name' => (string)env('DB_NAME', defined('DB_NAME') ? DB_NAME : 'sistema_login'),
        'user' => (string)env('DB_USER', defined('DB_USER') ? DB_USER : 'postgres'),
        'pass' => (string)env('DB_PASS', defined('DB_PASS') ? DB_PASS : ''),
        'ssl'  => (string)env('DB_SSLMODE', 'prefer'),
    ];
}

function respaldoHerramienta(string $nombre): string
{
    // PG_DUMP_RUTA / PG_RESTORE_RUTA permiten apuntar al .exe de Windows
    $clave = $nombre === 'pg_dump' ? 'PG_DUMP_RUTA' : 'PG_RESTORE_RUTA';
    $ruta = (string)env($clave, '');
    return $ruta !== '' ? $ruta : $nombre;
}

// Ejecuta un programa sin pasar por la shell. Devuelve [código, stdout, stderr].
// stdout se lee de una tubería con lecturas bloqueantes (así funciona igual en Windows y Linux);
// stderr (texto corto) va a un archivo temporal para que ninguna de las dos salidas pueda bloquear a la otra.
function respaldoEjecutar(array $cmd, array $pg, ?string $stdin = null): array
{
    $env = array_merge(getenv() ?: [], ['PGPASSWORD' => $pg['pass'], 'PGSSLMODE' => $pg['ssl'] ?: 'prefer']);
    $errArchivo = tempnam(sys_get_temp_dir(), 'clerr');
    $p = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errArchivo, 'w']], $pipes, null, $env);
    if (!is_resource($p)) {
        @unlink($errArchivo);
        throw new RuntimeException('No se pudo ejecutar ' . basename((string)$cmd[0]) . '. ¿Está instalado y en el PATH? (o define PG_DUMP_RUTA en el .env)');
    }
    if ($stdin !== null) {
        fwrite($pipes[0], $stdin);
    }
    fclose($pipes[0]);
    $out = '';
    while (!feof($pipes[1])) {
        $trozo = fread($pipes[1], 1 << 20);
        if ($trozo === false) {
            break;
        }
        $out .= $trozo;
        if (strlen($out) > RESPALDO_MAX_BYTES) {
            proc_terminate($p);
            fclose($pipes[1]);
            proc_close($p);
            @unlink($errArchivo);
            throw new RuntimeException('La base de datos es demasiado grande para este método de respaldo.');
        }
    }
    fclose($pipes[1]);
    $codigo = proc_close($p);
    $err = (string)@file_get_contents($errArchivo);
    @unlink($errArchivo);
    return [$codigo, $out, $err];
}

function respaldoRegistrar(string $mensaje, ?string $destino = null): void
{
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL;
    @file_put_contents(__DIR__ . '/../respaldo.log', $linea, FILE_APPEND);
    if ($destino !== null && is_dir($destino) && is_writable($destino)) {
        @file_put_contents(rtrim($destino, '/\\') . DIRECTORY_SEPARATOR . 'respaldos.log', $linea, FILE_APPEND);
    }
}