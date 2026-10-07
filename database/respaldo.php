<?php
// database/respaldo.php
// Respaldo cifrado de la base de datos. Pensado para ejecutarse cada 24 horas (ver docs/RESPALDOS.txt).
//
//   php database/respaldo.php
//
// Configuración en el .env:
//   RESPALDO_DESTINO=E:\respaldos        carpeta en el USB o disco externo (Linux: /media/usb/respaldos)
//   RESPALDO_CONSERVAR=14                cuántos respaldos guardar (los más viejos se borran). Por defecto 14.
//   RESPALDO_CLAVE=...                   opcional; si no existe se usa CIFRADO_CLAVE
//   PG_DUMP_RUTA=C:\Program Files\PostgreSQL\18\bin\pg_dump.exe   opcional si pg_dump no está en el PATH
//
// Códigos de salida: 0 = respaldo correcto, 1 = error, 2 = el destino no está disponible (¿USB desconectado?).
// Cada ejecución deja una línea en respaldo.log (y en respaldos.log dentro del destino).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../modelo/respaldo.php';

$destino = rtrim((string)env('RESPALDO_DESTINO', ''), '/\\');

try {
    if ($destino === '') {
        throw new RuntimeException('Falta RESPALDO_DESTINO en el .env (carpeta del USB o disco externo).');
    }
    if (!is_dir($destino) || !is_writable($destino)) {
        respaldoRegistrar("ERROR: el destino \"$destino\" no está disponible (¿USB desconectado?). No se hizo respaldo.");
        fwrite(STDERR, "El destino \"$destino\" no está disponible. ¿Está conectado el USB o disco externo?\n");
        exit(2);
    }

    $pg = respaldoConexion();
    $clave = respaldoClave(); // falla pronto si no hay clave

    // 1) Volcado a memoria (sin escribir nada sin cifrar en disco)
    [$codigo, $volcado, $err] = respaldoEjecutar([
        respaldoHerramienta('pg_dump'),
        '-h', $pg['host'], '-p', $pg['port'], '-U', $pg['user'], '-d', $pg['name'],
        '-Fc', '--no-owner', '--no-privileges', '-w',
    ], $pg);
    if ($codigo !== 0 || strncmp($volcado, 'PGDMP', 5) !== 0) {
        throw new RuntimeException('pg_dump falló: ' . trim($err ?: 'salida no válida'));
    }

    // 2) Cifrado y escritura atómica (archivo .part que se renombra al final)
    $nombre = 'crochetlab_' . date('Ymd_His') . '.dump.enc';
    $ruta = $destino . DIRECTORY_SEPARATOR . $nombre;
    $parcial = $ruta . '.part';
    if (file_put_contents($parcial, respaldoCifrar($volcado)) === false) {
        throw new RuntimeException("No se pudo escribir en $destino.");
    }

    // 3) Verificación: se vuelve a leer, se descifra y se compara con el original
    $leido = file_get_contents($parcial);
    if ($leido === false || !hash_equals(hash('sha256', $volcado), hash('sha256', respaldoDescifrar($leido)))) {
        @unlink($parcial);
        throw new RuntimeException('La verificación del respaldo falló; se descartó el archivo.');
    }
    if (!rename($parcial, $ruta)) {
        @unlink($parcial);
        throw new RuntimeException('No se pudo guardar el archivo final.');
    }

    // 4) Retención: conservar solo los N más recientes
    $conservar = max(1, (int)env('RESPALDO_CONSERVAR', 14));
    $archivos = glob($destino . DIRECTORY_SEPARATOR . 'crochetlab_*.dump.enc') ?: [];
    $archivos = array_values(array_filter($archivos, fn($f) => preg_match('/crochetlab_\d{8}_\d{6}\.dump\.enc$/', $f)));
    sort($archivos); // el nombre lleva la fecha, así que el orden alfabético es el cronológico
    $borrados = 0;
    foreach (array_slice($archivos, 0, max(0, count($archivos) - $conservar)) as $viejo) {
        if (@unlink($viejo)) {
            $borrados++;
        }
    }

    $kb = number_format(filesize($ruta) / 1024, 1);
    $msg = "OK: $nombre ($kb KB, verificado). Respaldos conservados: " . min(count($archivos), $conservar)
         . ($borrados ? ", eliminados por antigüedad: $borrados" : '') . '.';
    respaldoRegistrar($msg, $destino);
    echo $msg . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    respaldoRegistrar('ERROR: ' . $e->getMessage(), $destino !== '' ? $destino : null);
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
