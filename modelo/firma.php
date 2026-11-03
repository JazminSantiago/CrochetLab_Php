<?php
// modelo/firma.php
// Firma digital (criptografía ASIMÉTRICA) de los comprobantes de pedido: RSA-2048 + SHA-256.
//
// Librería: extensión OpenSSL de PHP (openssl_sign / openssl_verify). Es la misma librería
// establecida que ya usa modelo/cifrado.php para AES-256-GCM; no se implementa ningún algoritmo a mano.
//
// Para qué sirve: cuando se firma un pedido se emite un comprobante que
//   - AUTENTICA: solo quien tiene la llave PRIVADA del sistema pudo producir esa firma;
//   - garantiza INTEGRIDAD: si alguien cambia un solo carácter del comprobante o de la firma
//     (por ejemplo editando la base de datos), la verificación con la llave PÚBLICA falla.
// La llave pública se puede entregar a cualquiera (cliente, auditor) para verificar sin confiar en el sistema.
//
// Dónde se guardan las llaves (nunca en el código ni en el repositorio):
//   - Llave PRIVADA: archivo PEM FUERA de la carpeta pública (por defecto <carpeta padre>/claves/firma_privada.pem),
//     cifrado con AES-256 y protegido por una frase de paso. Ruta y frase salen del .env
//     (FIRMA_CLAVE_PRIVADA, FIRMA_CLAVE_FRASE). Quien robe solo el archivo no puede firmar.
//   - Llave PÚBLICA: archivo PEM (FIRMA_CLAVE_PUBLICA); no es secreta.
// Se generan con: php database/generar_par_claves.php

require_once __DIR__ . '/env.php';

const FIRMA_ALGORITMO = OPENSSL_ALGO_SHA256;
const FIRMA_ALGORITMO_TEXTO = 'RSA-2048 con SHA-256 (PKCS#1 v1.5)';
const FIRMA_VERSION_FORMATO = 1;

function firmaRutaPrivada(): string
{
    $ruta = (string)env('FIRMA_CLAVE_PRIVADA', '');
    return $ruta !== '' ? $ruta : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'claves' . DIRECTORY_SEPARATOR . 'firma_privada.pem';
}

function firmaRutaPublica(): string
{
    $ruta = (string)env('FIRMA_CLAVE_PUBLICA', '');
    return $ruta !== '' ? $ruta : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'claves' . DIRECTORY_SEPARATOR . 'firma_publica.pem';
}

// openssl.cnf: en Windows (XAMPP) PHP a veces no lo encuentra y no puede generar llaves.
// Solo hace falta al GENERAR el par; firmar y verificar no lo usan.
function firmaConfigOpenssl(): array
{
    $candidatos = [
        getenv('OPENSSL_CONF') ?: '',
        (string)env('OPENSSL_CNF', ''),
        dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/apache/conf/openssl.cnf',
        '/etc/ssl/openssl.cnf',
    ];
    foreach ($candidatos as $c) {
        if ($c !== '' && is_readable($c)) {
            return ['config' => $c];
        }
    }
    return [];
}

// Genera el par de llaves. Devuelve [privadaPemCifrada, publicaPem].
function firmaGenerarPar(string $frase): array
{
    if (strlen($frase) < 12) {
        throw new RuntimeException('La frase de paso debe tener al menos 12 caracteres.');
    }
    $cfg = firmaConfigOpenssl() + [
        'private_key_bits'    => 2048,
        'private_key_type'    => OPENSSL_KEYTYPE_RSA,
        'digest_alg'          => 'sha256',
        'encrypt_key_cipher'  => OPENSSL_CIPHER_AES_256_CBC,
    ];
    $recurso = openssl_pkey_new($cfg);
    if ($recurso === false) {
        throw new RuntimeException('No se pudo generar el par de llaves: ' . firmaErrorOpenssl()
            . ' (en Windows revisa la ruta de openssl.cnf o define OPENSSL_CNF en el .env)');
    }
    $privada = '';
    if (!openssl_pkey_export($recurso, $privada, $frase, $cfg)) {
        throw new RuntimeException('No se pudo exportar la llave privada: ' . firmaErrorOpenssl());
    }
    $detalles = openssl_pkey_get_details($recurso);
    if ($detalles === false || empty($detalles['key'])) {
        throw new RuntimeException('No se pudo obtener la llave pública.');
    }
    return [$privada, $detalles['key']];
}

function firmaErrorOpenssl(): string
{
    $errores = [];
    while (($e = openssl_error_string()) !== false) {
        $errores[] = $e;
    }
    return $errores ? implode(' | ', $errores) : 'sin detalle';
}

// Llave privada ya descifrada con la frase del .env (solo en memoria, mientras dura la petición).
function firmaLlavePrivada()
{
    $ruta  = firmaRutaPrivada();
    $frase = (string)env('FIRMA_CLAVE_FRASE', '');
    if (!is_readable($ruta)) {
        throw new RuntimeException('No se encuentra la llave privada de firma. Genera el par con: php database/generar_par_claves.php');
    }
    if ($frase === '') {
        throw new RuntimeException('Falta FIRMA_CLAVE_FRASE en el archivo .env.');
    }
    $llave = openssl_pkey_get_private((string)file_get_contents($ruta), $frase);
    if ($llave === false) {
        throw new RuntimeException('No se pudo abrir la llave privada (¿frase de paso incorrecta?).');
    }
    return $llave;
}

function firmaLlavePublicaPem(): string
{
    $ruta = firmaRutaPublica();
    if (!is_readable($ruta)) {
        throw new RuntimeException('No se encuentra la llave pública de firma. Genera el par con: php database/generar_par_claves.php');
    }
    return (string)file_get_contents($ruta);
}

// Huella de la llave pública: SHA-256 del DER, en hexadecimal con ":" (sirve para identificar con qué llave se firmó).
function firmaHuella(string $publicaPem): string
{
    $limpio = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s+/', '', $publicaPem);
    $der = base64_decode((string)$limpio, true);
    if ($der === false || $der === '') {
        return '';
    }
    return implode(':', str_split(hash('sha256', $der), 2));
}

// Firma un texto con la llave privada. Devuelve la firma en bytes crudos.
function firmaFirmar(string $texto): string
{
    $firma = '';
    if (!openssl_sign($texto, $firma, firmaLlavePrivada(), FIRMA_ALGORITMO)) {
        throw new RuntimeException('No se pudo firmar: ' . firmaErrorOpenssl());
    }
    return $firma;
}

// true solo si la firma corresponde EXACTAMENTE a ese texto y a esa llave pública.
function firmaVerificar(string $texto, string $firmaBinaria, string $publicaPem): bool
{
    $llave = openssl_pkey_get_public($publicaPem);
    if ($llave === false || $firmaBinaria === '') {
        return false;
    }
    return openssl_verify($texto, $firmaBinaria, $llave, FIRMA_ALGORITMO) === 1;
}

// ───────────────────────── Comprobante de pedido ─────────────────────────

// Datos del pedido que se firman. NO incluye el contacto ni la dirección (están cifrados y son privados)
// ni el estado/prioridad (cambian durante el trabajo normal). Orden y formato fijos para que sea reproducible.
function firmaDatosPedido(array $p): array
{
    return [
        'pedido_id'         => (int)$p['id'],
        'tipo'              => (string)$p['tipo'],
        'producto'          => (string)($p['producto_nombre'] ?? ''),
        'descripcion'       => (string)($p['descripcion'] ?? ''),
        'cliente'           => (string)($p['cliente_nombre'] ?? ''),
        'fecha_pedido'      => substr((string)$p['fecha_pedido'], 0, 19),
        'fecha_entrega'     => (string)$p['fecha_entrega'],
        'entrega_domicilio' => in_array($p['entrega_domicilio'], [true, 1, '1', 't', 'true'], true),
        'costo_envio'       => number_format((float)$p['costo_envio'], 2, '.', ''),
    ];
}

function firmaJson(array $datos): string
{
    return json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function firmaPedidoConsulta(PDO $db, int $pedidoId): ?array
{
    $st = $db->prepare(
        "SELECT p.*, c.nombre AS producto_nombre
         FROM pedidos p LEFT JOIN catalogo c ON c.id = p.catalogo_id
         WHERE p.id = :id"
    );
    $st->execute([':id' => $pedidoId]);
    $fila = $st->fetch(PDO::FETCH_ASSOC);
    return $fila ?: null;
}

// Emite una nueva versión firmada del comprobante. Devuelve el número de versión.
function firmaEmitir(PDO $db, int $pedidoId, ?int $usuarioId): int
{
    $pedido = firmaPedidoConsulta($db, $pedidoId);
    if (!$pedido) {
        throw new RuntimeException('El pedido no existe.');
    }
    $db->beginTransaction();
    try {
        // Bloquea el pedido para que dos firmas simultáneas no repitan el número de versión
        $db->prepare("SELECT id FROM pedidos WHERE id = :id FOR UPDATE")->execute([':id' => $pedidoId]);
        $st = $db->prepare("SELECT COALESCE(MAX(version), 0) + 1 FROM pedido_firmas WHERE pedido_id = :id");
        $st->execute([':id' => $pedidoId]);
        $version = (int)$st->fetchColumn();

        $texto = firmaJson([
            'formato'   => FIRMA_VERSION_FORMATO,
            'version'   => $version,
            'emitido'   => gmdate('Y-m-d\TH:i:s\Z'),
            'datos'     => firmaDatosPedido($pedido),
        ]);
        $firma = firmaFirmar($texto);
        $huella = firmaHuella(firmaLlavePublicaPem());

        $db->prepare(
            "INSERT INTO pedido_firmas (pedido_id, version, contenido, firma, huella_llave, firmado_por)
             VALUES (:p, :v, :c, :f, :h, :u)"
        )->execute([':p' => $pedidoId, ':v' => $version, ':c' => $texto, ':f' => base64_encode($firma),
                    ':h' => $huella, ':u' => $usuarioId]);
        $db->commit();
        return $version;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

// Versiones firmadas de un pedido, de la más nueva a la más vieja.
function firmaHistorial(PDO $db, int $pedidoId): array
{
    $st = $db->prepare(
        "SELECT f.*, u.usuario AS firmado_por_usuario
         FROM pedido_firmas f LEFT JOIN usuarios u ON u.id = f.firmado_por
         WHERE f.pedido_id = :id ORDER BY f.version DESC"
    );
    $st->execute([':id' => $pedidoId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

// Revisa una versión firmada (la última por defecto). Resultado:
//   estado 'sin_firma'  : el pedido nunca se firmó
//   estado 'valida'     : firma auténtica y el pedido no ha cambiado desde que se firmó
//   estado 'modificado' : firma auténtica, pero el pedido cambió después (conviene emitir una versión nueva)
//   estado 'invalida'   : la firma NO corresponde al contenido guardado (alguien lo alteró) o a la llave pública
function firmaVerificarPedido(PDO $db, int $pedidoId, ?int $version = null): array
{
    $historial = firmaHistorial($db, $pedidoId);
    if (!$historial) {
        return ['estado' => 'sin_firma', 'firma' => null, 'diferencias' => []];
    }
    $fila = $historial[0];
    if ($version !== null) {
        foreach ($historial as $h) {
            if ((int)$h['version'] === $version) {
                $fila = $h;
                break;
            }
        }
    }

    $publica   = firmaLlavePublicaPem();
    $firmaBin  = base64_decode((string)$fila['firma'], true) ?: '';
    $autentica = firmaVerificar((string)$fila['contenido'], $firmaBin, $publica);
    $huellaActual = firmaHuella($publica);

    $resultado = [
        'estado'        => 'invalida',
        'firma'         => $fila,
        'autentica'     => $autentica,
        'misma_llave'   => hash_equals((string)$fila['huella_llave'], $huellaActual),
        'huella_actual' => $huellaActual,
        'diferencias'   => [],
    ];
    if (!$autentica) {
        return $resultado;
    }

    // La firma es auténtica: ahora se compara lo firmado con lo que hay HOY en la base de datos
    $pedido = firmaPedidoConsulta($db, $pedidoId);
    $firmado = json_decode((string)$fila['contenido'], true)['datos'] ?? [];
    $actual  = $pedido ? firmaDatosPedido($pedido) : [];
    foreach ($firmado as $campo => $valor) {
        if (!array_key_exists($campo, $actual) || $actual[$campo] !== $valor) {
            $resultado['diferencias'][$campo] = ['firmado' => $valor, 'actual' => $actual[$campo] ?? null];
        }
    }
    $resultado['estado'] = $resultado['diferencias'] ? 'modificado' : 'valida';
    return $resultado;
}
