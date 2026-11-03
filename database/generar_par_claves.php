<?php
// database/generar_par_claves.php
// Genera el par de llaves RSA-2048 para la firma digital de comprobantes (modelo/firma.php).
//
// Uso:   php database/generar_par_claves.php
//
// - La llave PRIVADA se guarda cifrada (AES-256) con una frase de paso, FUERA de la carpeta pública:
//   <carpeta padre del proyecto>/claves/firma_privada.pem   (permisos 600 en Linux)
// - La llave PUBLICA se guarda al lado: firma_publica.pem (no es secreta; se puede compartir).
// - Al terminar muestra las líneas que debes pegar en el .env. La frase de paso NUNCA se guarda en un archivo del proyecto
//   salvo el .env, que no se sube al repositorio.
// No sobrescribe llaves existentes (si las cambias, los comprobantes ya firmados dejarían de verificarse con la llave nueva).

if (PHP_SAPI !== 'cli') {
    exit('Solo desde la línea de comandos.');
}
require_once __DIR__ . '/../modelo/firma.php';

try {
    $privada = firmaRutaPrivada();
    $publica = firmaRutaPublica();
    if (file_exists($privada) || file_exists($publica)) {
        fwrite(STDERR, "Ya existen llaves en:\n  $privada\n  $publica\nBórralas a mano si de verdad quieres generar otras.\n");
        exit(1);
    }

    // Frase de paso: la pasada por variable de entorno o una aleatoria de 24 caracteres
    $frase = getenv('FIRMA_CLAVE_FRASE') ?: rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    [$pemPrivada, $pemPublica] = firmaGenerarPar($frase);

    $carpeta = dirname($privada);
    if (!is_dir($carpeta) && !mkdir($carpeta, 0700, true)) {
        throw new RuntimeException("No se pudo crear la carpeta $carpeta");
    }
    file_put_contents($privada, $pemPrivada);
    @chmod($privada, 0600);
    file_put_contents($publica, $pemPublica);

    echo "Par de llaves generado.\n";
    echo "  Privada (cifrada): $privada\n";
    echo "  Publica          : $publica\n";
    echo "  Huella publica   : " . firmaHuella($pemPublica) . "\n\n";
    echo "Agrega estas líneas al archivo .env (carpeta padre del proyecto):\n\n";
    echo "FIRMA_CLAVE_PRIVADA=$privada\n";
    echo "FIRMA_CLAVE_PUBLICA=$publica\n";
    echo "FIRMA_CLAVE_FRASE=$frase\n\n";
    echo "Guarda una copia de la llave privada y de la frase en un lugar seguro, fuera de la computadora.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
