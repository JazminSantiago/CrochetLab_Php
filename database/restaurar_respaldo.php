<?php
// database/restaurar_respaldo.php
// Restaura un respaldo cifrado (.dump.enc) en una base de datos.
//
//   php database/restaurar_respaldo.php "E:\respaldos\crochetlab_20261007_020000.dump.enc" --db=sistema_login_restaurada
//
// - Por seguridad NO toca la base en uso: --db es obligatorio y se crea si no existe.
//   Para restaurar sobre una base que ya existe (se borran sus tablas) agrega --sobrescribir.
// - Necesita la MISMA clave con la que se hizo el respaldo (RESPALDO_CLAVE o CIFRADO_CLAVE del .env).
// - Para usar la base restaurada, cambia DB_NAME en el .env.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../modelo/respaldo.php';

function fin(string $m, int $c): void
{
    fwrite($c === 0 ? STDOUT : STDERR, $m . PHP_EOL);
    exit($c);
}

// Argumentos en cualquier orden (getopt() se detiene en el primer argumento sin guion, por eso no se usa)
$archivo = '';
$opciones = [];
foreach (array_slice($argv, 1) as $arg) {
    if (strncmp($arg, '--db=', 5) === 0) {
        $opciones['db'] = substr($arg, 5);
    } elseif ($arg === '--sobrescribir') {
        $opciones['sobrescribir'] = true;
    } elseif ($archivo === '' && strncmp($arg, '--', 2) !== 0) {
        $archivo = $arg;
    }
}
if ($archivo === '' || empty($opciones['db'])) {
    fin('Uso: php database/restaurar_respaldo.php <archivo.dump.enc> --db=<nombre_de_la_base> [--sobrescribir]', 1);
}
$destinoDb = (string)$opciones['db'];
if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $destinoDb)) {
    fin('Nombre de base no válido (usa letras, números y guion bajo).', 1);
}

$tmp = null;
try {
    if (!is_readable($archivo)) {
        fin("No se puede leer el archivo: $archivo", 1);
    }
    $volcado = respaldoDescifrar((string)file_get_contents($archivo)); // falla si fue alterado o la clave no coincide
    if (strncmp($volcado, 'PGDMP', 5) !== 0) {
        fin('El contenido descifrado no es un volcado de PostgreSQL.', 1);
    }

    $pg = respaldoConexion();

    // Crear la base destino si no existe (conectando a la base de mantenimiento "postgres")
    $mant = new PDO("pgsql:host={$pg['host']};port={$pg['port']};dbname=postgres;sslmode={$pg['ssl']}", $pg['user'], $pg['pass']);
    $mant->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $st = $mant->prepare('SELECT 1 FROM pg_database WHERE datname = :n');
    $st->execute([':n' => $destinoDb]);
    $existe = (bool)$st->fetchColumn();
    if ($existe && !isset($opciones['sobrescribir'])) {
        fin("La base \"$destinoDb\" ya existe. Elige otro nombre o agrega --sobrescribir (borra sus tablas).", 1);
    }
    if (!$existe) {
        $mant->exec('CREATE DATABASE "' . $destinoDb . '" ENCODING \'UTF8\' TEMPLATE template0');
    }

    // pg_restore lee un archivo; el temporal queda solo para el dueño y se borra siempre al terminar
    $tmp = tempnam(sys_get_temp_dir(), 'clbk');
    chmod($tmp, 0600);
    file_put_contents($tmp, $volcado);
    unset($volcado);

    $cmd = [respaldoHerramienta('pg_restore'), '-h', $pg['host'], '-p', $pg['port'], '-U', $pg['user'],
            '-d', $destinoDb, '--no-owner', '--no-privileges', '-w'];
    if ($existe) {
        array_push($cmd, '--clean', '--if-exists');
    }
    $cmd[] = $tmp;
    [$codigo, , $err] = respaldoEjecutar($cmd, $pg);
    @unlink($tmp); // exit() no ejecuta "finally", así que el temporal se borra aquí antes de salir
    if ($codigo !== 0) {
        fin('pg_restore terminó con errores: ' . trim($err), 1);
    }
    fin("Respaldo restaurado en la base \"$destinoDb\". Para usarla, pon DB_NAME=$destinoDb en el .env.", 0);
} catch (Throwable $e) {
    fin('ERROR: ' . $e->getMessage(), 1);
} finally {
    if ($tmp !== null && is_file($tmp)) {
        @unlink($tmp);
    }
}
