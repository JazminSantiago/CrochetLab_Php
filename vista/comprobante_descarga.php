<?php
// vista/comprobante_descarga.php
// Entrega los tres archivos que permiten verificar un comprobante SIN el sistema, con OpenSSL:
//   ?id=12&t=contenido  -> comprobante_12_v1.json   (lo que se firmó)
//   ?id=12&t=firma      -> comprobante_12_v1.firma  (la firma, en bytes)
//   ?id=12&t=publica    -> clave_publica.pem        (la llave pública)
// Verificación:  openssl dgst -sha256 -verify clave_publica.pem -signature comprobante_12_v1.firma comprobante_12_v1.json
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/../modelo/firma.php';
requierePermiso('pedidos', 'lectura');

$id      = (int)($_GET['id'] ?? 0);
$tipo    = (string)($_GET['t'] ?? '');
$version = isset($_GET['v']) ? (int)$_GET['v'] : null;

try {
    if ($tipo === 'publica') {
        $cuerpo = firmaLlavePublicaPem();
        $nombre = 'clave_publica.pem';
        $mime   = 'application/x-pem-file';
    } else {
        $fila = null;
        foreach (firmaHistorial((new Conexion())->conectar(), $id) as $f) {
            if ($version === null || (int)$f['version'] === $version) {
                $fila = $f;
                break;
            }
        }
        if (!$fila || !in_array($tipo, ['contenido', 'firma'], true)) {
            http_response_code(404);
            exit('No encontrado.');
        }
        $base = 'comprobante_' . $id . '_v' . (int)$fila['version'];
        if ($tipo === 'contenido') {
            $cuerpo = (string)$fila['contenido'];
            $nombre = $base . '.json';
            $mime   = 'application/json; charset=utf-8';
        } else {
            $cuerpo = (string)base64_decode((string)$fila['firma'], true);
            $nombre = $base . '.firma';
            $mime   = 'application/octet-stream';
        }
    }
} catch (Throwable $e) {
    error_log('comprobante_descarga: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo preparar el archivo.');
}

auditar('descargar_firma', 'pedidos', $id, "Descarga de $nombre");
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . strlen($cuerpo));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
echo $cuerpo;
