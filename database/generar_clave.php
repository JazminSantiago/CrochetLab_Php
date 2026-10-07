<?php
// database/generar_clave.php
// Genera una clave aleatoria de 32 bytes (AES-256) lista para pegar en el archivo .env.
//
//   php database/generar_clave.php
//
// Guarda la línea resultante SOLO en el .env (nunca en git). Si la pierdes, los datos cifrados
// no se pueden recuperar; guarda una copia en un lugar seguro, separada de las copias de la base.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

echo 'CIFRADO_CLAVE=' . base64_encode(random_bytes(32)) . PHP_EOL;
