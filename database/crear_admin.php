<?php
// database/crear_admin.php
// Crea el PRIMER administrador (superadministrador) de una base de datos nueva.
// Una base recién montada con el esquema y las migraciones no trae ningún usuario.
//
//   php database/crear_admin.php
//   php database/crear_admin.php --usuario=admin --nombre="Administrador" --email=admin@correo.mx
//
// La contraseña se pide por teclado (nunca como argumento: quedaría en el historial de la terminal).
// En Linux no se ve al escribirla; en Windows sí se ve, así que hazlo en privado.
// Solo funciona si todavía no existe un superadministrador (para crear otro: --otro).
// Si el rol Administrador exige verificación en dos pasos (ADMIN_REQUIERE_2FA=true), al primer
// inicio de sesión el sistema te llevará a "Mi cuenta" para activarla.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/seguridad.php';

function pedir(string $texto, bool $oculto = false): string
{
    echo $texto;
    $sttyOk = false;
    if ($oculto && PHP_OS_FAMILY !== 'Windows') {
        $sttyOk = (bool)@shell_exec('stty -echo 2>/dev/null; echo ok');
    }
    $linea = fgets(STDIN);
    if ($sttyOk) {
        @shell_exec('stty echo 2>/dev/null');
        echo PHP_EOL;
    }
    return $linea === false ? '' : trim($linea);
}

function salir(string $mensaje, int $codigo = 1): void
{
    fwrite($codigo === 0 ? STDOUT : STDERR, $mensaje . PHP_EOL);
    exit($codigo);
}

$opciones = getopt('', ['usuario::', 'nombre::', 'email::', 'otro']);

try {
    $db = (new Conexion())->conectar();

    $idRol = $db->query("SELECT id FROM roles WHERE nombre = 'Administrador'")->fetchColumn();
    if (!$idRol) {
        salir("No existe el rol Administrador. Aplica primero las migraciones (database/migraciones/migracion_01...).");
    }

    $hay = $db->query("SELECT usuario FROM usuarios WHERE es_superadmin = TRUE AND activo = TRUE LIMIT 1")->fetchColumn();
    if ($hay && !isset($opciones['otro'])) {
        salir("Ya existe un superadministrador (\"$hay\"). Si de verdad necesitas otro, ejecuta de nuevo con --otro.");
    }

    $usuario = trim((string)($opciones['usuario'] ?? '')) ?: pedir('Usuario (3 a 30 caracteres, letras/números/._-): ');
    $nombre  = trim((string)($opciones['nombre']  ?? '')) ?: pedir('Nombre completo: ');
    $email   = strtolower(trim((string)($opciones['email'] ?? '')) ?: pedir('Correo electrónico: '));

    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $usuario)) {
        salir('Usuario no válido: usa de 3 a 30 caracteres (letras, números, punto, guion o guion bajo).');
    }
    if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
        salir('El nombre debe tener entre 2 y 100 caracteres.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        salir('El correo no es válido.');
    }

    $st = $db->prepare("SELECT 1 FROM usuarios WHERE usuario = :u OR LOWER(email) = :e");
    $st->execute([':u' => $usuario, ':e' => $email]);
    if ($st->fetch()) {
        salir('Ese usuario o correo ya existe.');
    }

    $pass = pedir('Contraseña (mín. 8, con mayúsculas, minúsculas y números): ', true);
    if (($error = validarPassword($pass)) !== null) {
        salir($error);
    }
    if ($pass !== pedir('Repite la contraseña: ', true)) {
        salir('Las contraseñas no coinciden.');
    }

    $db->beginTransaction();
    $st = $db->prepare(
        "INSERT INTO usuarios (usuario, nombre, email, password, rol_id, activo, es_superadmin)
         VALUES (:u, :n, :e, :p, :r, TRUE, TRUE) RETURNING id"
    );
    $st->execute([':u' => $usuario, ':n' => $nombre, ':e' => $email, ':p' => hashPassword($pass), ':r' => $idRol]);
    $id = (int)$st->fetchColumn();

    $db->prepare(
        "INSERT INTO auditoria (usuario_id, usuario_nombre, accion, area, registro_id, detalle, ip)
         VALUES (:id, :u, 'crear_admin', 'usuarios', :id2, 'Superadministrador creado desde la línea de comandos', 'cli')"
    )->execute([':id' => $id, ':u' => $usuario, ':id2' => $id]);
    $db->commit();

    salir("Listo: superadministrador \"$usuario\" creado. Ya puedes iniciar sesión.", 0);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    salir('ERROR: ' . $e->getMessage());
}
