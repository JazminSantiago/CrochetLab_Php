<?php
// controlador/ingresar_usuario.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';

class IngresarUsuario
{
    private $conexion;

    public function __construct()
    {
        $db = new Conexion();
        $this->conexion = $db->conectar();
    }

    public function registrarUsuario($usuario, $nombre, $email, $password)
    {
        if (empty($usuario) || empty($nombre) || empty($email) || empty($password)) {
            return ['success' => false, 'mensaje' => 'Todos los campos son obligatorios'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'mensaje' => 'El email no es válido'];
        }

        // Verificar usuario o email duplicado
        $sql = "SELECT id FROM usuarios WHERE usuario = :usuario OR email = :email";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':usuario' => $usuario, ':email' => $email]);

        if ($stmt->fetch()) {
            return ['success' => false, 'mensaje' => 'El usuario o email ya están registrados'];
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Siempre rol = 'tejedor' y activo = true — el admin no se puede crear desde aquí
        $sql = "INSERT INTO usuarios (usuario, nombre, email, password, rol, activo) 
                VALUES (:usuario, :nombre, :email, :password, 'tejedor', true)";

        try {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([
                ':usuario'  => $usuario,
                ':nombre'   => $nombre,
                ':email'    => $email,
                ':password' => $passwordHash
            ]);

            return ['success' => true, 'mensaje' => 'Cuenta creada exitosamente. Ya puedes iniciar sesión.'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensaje' => 'Error al registrar: ' . $e->getMessage()];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ingresarUsuario = new IngresarUsuario();

    $resultado = $ingresarUsuario->registrarUsuario(
        $_POST['usuario']  ?? '',
        $_POST['nombre']   ?? '',
        $_POST['email']    ?? '',
        $_POST['password'] ?? ''
    );

    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';

    header('Location: ../index.php?action=' . ($resultado['success'] ? 'login' : 'registro'));
    exit();
}
?>