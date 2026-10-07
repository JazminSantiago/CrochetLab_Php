<?php
// controlador/ingresar_usuario.php
// Registro público de cuentas nuevas (siempre con el rol "Usuario Regular").

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/registro.php';
require_once __DIR__ . '/../modelo/seguridad.php';

class IngresarUsuario
{
    private $conexion;

    public function __construct()
    {
        $db = new Conexion();
        $this->conexion = $db->conectar();
    }

    public function registrarUsuario($usuario, $nombre, $email, $password, $confirmar)
    {
        foreach ([$usuario, $nombre, $email, $password, $confirmar] as $campo) {
            if (!is_string($campo) || trim($campo) === '') {
                return ['success' => false, 'mensaje' => 'Todos los campos son obligatorios'];
            }
        }
        $usuario = trim($usuario);
        $nombre  = trim($nombre);
        $email   = strtolower(trim($email));

        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $usuario)) {
            return ['success' => false, 'mensaje' => 'El usuario debe tener de 3 a 30 caracteres (letras, números, punto, guion o guion bajo).'];
        }
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) {
            return ['success' => false, 'mensaje' => 'El nombre debe tener entre 2 y 100 caracteres.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            return ['success' => false, 'mensaje' => 'El email no es válido'];
        }
        if ($password !== $confirmar) {
            return ['success' => false, 'mensaje' => 'Las contraseñas no coinciden.'];
        }
        if (($error = validarPassword($password)) !== null) {
            return ['success' => false, 'mensaje' => $error];
        }

        // Límite por IP: máximo 5 cuentas nuevas por hora
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM auditoria
             WHERE accion = 'registro' AND ip = :ip AND fecha > NOW() - INTERVAL '1 hour'"
        );
        $stmt->execute([':ip' => ipCliente()]);
        if ((int)$stmt->fetchColumn() >= 5) {
            return ['success' => false, 'mensaje' => 'Demasiados registros desde este equipo. Inténtalo más tarde.'];
        }

        $stmt = $this->conexion->prepare("SELECT id FROM usuarios WHERE usuario = :usuario OR LOWER(email) = :email");
        $stmt->execute([':usuario' => $usuario, ':email' => $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'mensaje' => 'El usuario o email ya están registrados'];
        }

        // El registro público siempre crea un 'Usuario Regular' activo:
        // los demás roles los asigna un administrador.
        $sql = "INSERT INTO usuarios (usuario, nombre, email, password, rol_id, activo)
                VALUES (:usuario, :nombre, :email, :password,
                        (SELECT id FROM roles WHERE nombre = 'Usuario Regular'), true)";
        try {
            $this->conexion->prepare($sql)->execute([
                ':usuario'  => $usuario,
                ':nombre'   => $nombre,
                ':email'    => $email,
                ':password' => hashPassword($password),
            ]);
            return ['success' => true, 'mensaje' => 'Cuenta creada exitosamente. Ya puedes iniciar sesión.', 'usuario' => $usuario];
        } catch (PDOException $e) {
            error_log('registrarUsuario: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo crear la cuenta. Inténtalo de nuevo.'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf('../vista/registro.php');

    $ingresarUsuario = new IngresarUsuario();
    $resultado = $ingresarUsuario->registrarUsuario(
        $_POST['usuario']   ?? '',
        $_POST['nombre']    ?? '',
        $_POST['email']     ?? '',
        $_POST['password']  ?? '',
        $_POST['confirmar'] ?? ''
    );

    if ($resultado['success']) {
        auditar('registro', 'usuarios', null, 'Registro de la cuenta ' . $resultado['usuario'], $resultado['usuario']);
    }

    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';

    header('Location: ' . ($resultado['success'] ? '../index.php' : '../vista/registro.php'));
    exit();
}
?>
