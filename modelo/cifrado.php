<?php
// modelo/cifrado.php
// Cifrado de datos sensibles en la base de datos con AES-256-GCM (OpenSSL).
//
// Qué se cifra: teléfono y dirección de empleados, contacto y dirección de entrega de pedidos,
// y el secreto de la verificación en dos pasos (TOTP).
//
// Cómo funciona:
//   - La clave de 32 bytes está en el archivo .env (CIFRADO_CLAVE, en base64), NUNCA en la base
//     de datos ni en el código. Quien robe solo la base de datos no puede leer esos campos.
//   - Cada valor usa un IV aleatorio de 12 bytes: el mismo texto produce un cifrado distinto cada vez.
//   - GCM autentica el dato: si alguien modifica el valor cifrado en la base, el descifrado falla
//     en lugar de devolver basura.
//   - El "contexto" (tabla.columna) se autentica como dato asociado: un valor cifrado no se puede
//     copiar de una columna a otra y seguir siendo válido.
//   - Formato guardado:  enc:v1:BASE64( IV[12] + ETIQUETA[16] + TEXTO_CIFRADO )
//     El "v1" permite cambiar de algoritmo o de clave en el futuro.
//
// Los valores que aún no empiezan con "enc:v1:" se tratan como datos antiguos sin cifrar y se
// devuelven tal cual; así la aplicación sigue funcionando mientras se corre el script de
// migración (database/cifrar_datos_existentes.php).

require_once __DIR__ . '/env.php';

const CIFRADO_PREFIJO = 'enc:v1:';
const CIFRADO_ALGORITMO = 'aes-256-gcm';
const CIFRADO_IV_BYTES = 12;
const CIFRADO_TAG_BYTES = 16;

// Clave de 32 bytes tomada de CIFRADO_CLAVE (base64). Si falta o es inválida, se detiene con un
// mensaje claro: guardar los datos sin cifrar "en silencio" sería peor que fallar.
function cifradoClave(): string
{
    static $clave = null;
    if ($clave !== null) {
        return $clave;
    }
    $b64 = (string)env('CIFRADO_CLAVE', '');
    $raw = $b64 !== '' ? base64_decode($b64, true) : false;
    if ($raw === false || strlen($raw) !== 32) {
        throw new RuntimeException(
            'Falta la clave de cifrado (CIFRADO_CLAVE en el archivo .env) o no tiene 32 bytes en base64. '
            . 'Genera una con: php database/generar_clave.php'
        );
    }
    return $clave = $raw;
}

// Cifra un texto. null y '' se dejan igual (no hay nada que proteger).
function cifrar(?string $texto, string $contexto): ?string
{
    if ($texto === null || $texto === '') {
        return $texto;
    }
    $iv  = random_bytes(CIFRADO_IV_BYTES);
    $tag = '';
    $cifrado = openssl_encrypt($texto, CIFRADO_ALGORITMO, cifradoClave(), OPENSSL_RAW_DATA, $iv, $tag, $contexto, CIFRADO_TAG_BYTES);
    if ($cifrado === false || strlen($tag) !== CIFRADO_TAG_BYTES) {
        throw new RuntimeException('No se pudo cifrar el dato.');
    }
    return CIFRADO_PREFIJO . base64_encode($iv . $tag . $cifrado);
}

// Descifra un valor guardado. Devuelve null si el dato fue alterado o la clave no corresponde.
function descifrar(?string $valor, string $contexto): ?string
{
    if ($valor === null || $valor === '') {
        return $valor;
    }
    if (strncmp($valor, CIFRADO_PREFIJO, strlen(CIFRADO_PREFIJO)) !== 0) {
        return $valor; // dato antiguo, todavía sin cifrar
    }
    $bin = base64_decode(substr($valor, strlen(CIFRADO_PREFIJO)), true);
    if ($bin === false || strlen($bin) < CIFRADO_IV_BYTES + CIFRADO_TAG_BYTES + 1) {
        error_log("descifrar($contexto): formato no válido");
        return null;
    }
    $iv      = substr($bin, 0, CIFRADO_IV_BYTES);
    $tag     = substr($bin, CIFRADO_IV_BYTES, CIFRADO_TAG_BYTES);
    $cifrado = substr($bin, CIFRADO_IV_BYTES + CIFRADO_TAG_BYTES);

    $texto = openssl_decrypt($cifrado, CIFRADO_ALGORITMO, cifradoClave(), OPENSSL_RAW_DATA, $iv, $tag, $contexto);
    if ($texto === false) {
        error_log("descifrar($contexto): el dato fue alterado o la clave no coincide");
        return null;
    }
    return $texto;
}

function estaCifrado(?string $valor): bool
{
    return is_string($valor) && strncmp($valor, CIFRADO_PREFIJO, strlen(CIFRADO_PREFIJO)) === 0;
}

// Descifra varias columnas de una fila: descifrarCampos($fila, 'empleados', ['telefono', 'direccion']).
// Acepta false (fetch sin resultado) y lo devuelve igual.
function descifrarCampos($fila, string $tabla, array $columnas)
{
    if (!is_array($fila)) {
        return $fila;
    }
    // PDO entrega cada columna dos veces (por nombre y por número). Se descartan las claves
    // numéricas para que no quede una copia cifrada suelta (p. ej. al hacer json_encode de la fila).
    $fila = array_filter($fila, 'is_string', ARRAY_FILTER_USE_KEY);
    foreach ($columnas as $col) {
        if (array_key_exists($col, $fila)) {
            $fila[$col] = descifrar($fila[$col], $tabla . '.' . $col);
        }
    }
    return $fila;
}

function descifrarFilas(array $filas, string $tabla, array $columnas): array
{
    return array_map(fn($f) => descifrarCampos($f, $tabla, $columnas), $filas);
}
