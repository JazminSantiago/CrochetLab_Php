<?php
// controlador/validar_usuario.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';

class ValidarUsuario
{
    private $conexion;

    public function __construct()
    {
        $db = new Conexion();
        $this->conexion = $db->conectar();
    }

    public function validarLogin($usuario, $password)
    {
        if (empty($usuario) || empty($password)) {
            return ['success' => false, 'mensaje' => 'Usuario y contraseña son obligatorios'];
        }

        // Ahora también traemos rol y activo
        $sql = "SELECT id, usuario, nombre, email, password, rol, activo 
                FROM usuarios 
                WHERE usuario = :usuario";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':usuario' => $usuario]);
        $user = $stmt->fetch();

        if (!$user) {
            file_put_contents(__DIR__ . '/../debug_log.txt',
                date('Y-m-d H:i:s') . " - Login Failed: User '$usuario' not found.\n", FILE_APPEND);
            return ['success' => false, 'mensaje' => 'Usuario o contraseña incorrectos'];
        }

        // Verificar si la cuenta está activa
        if (!$user['activo']) {
            return ['success' => false, 'mensaje' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'];
        }

        // Si es tejedor, verificar que tenga registro en empleados
        if ($user['rol'] === 'tejedor') {
            $stmt2 = $this->conexion->prepare("SELECT id FROM empleados WHERE usuario_id = :uid");
            $stmt2->execute([':uid' => $user['id']]);
            if (!$stmt2->fetch()) {
                return ['success' => false, 'mensaje' => 'Tu cuenta aún no ha sido habilitada. Contacta al administrador.'];
            }
        }

        if (!password_verify($password, $user['password'])) {
            file_put_contents(__DIR__ . '/../debug_log.txt',
                date('Y-m-d H:i:s') . " - Login Failed: Password mismatch for '$usuario'.\n", FILE_APPEND);
            return ['success' => false, 'mensaje' => 'Usuario o contraseña incorrectos'];
        }

        // Actualizar último acceso
        $sqlUpdate = "UPDATE usuarios SET ultimo_acceso = :ahora WHERE id = :id";
        $stmtUpdate = $this->conexion->prepare($sqlUpdate);
        $stmtUpdate->execute([
            ':id'    => $user['id'],
            ':ahora' => date('Y-m-d H:i:s')
        ]);

        // Guardar sesión incluyendo rol
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario']    = $user['usuario'];
        $_SESSION['nombre']     = $user['nombre'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['rol']        = $user['rol'];
        $_SESSION['login_time'] = time();

        return ['success' => true, 'mensaje' => 'Login exitoso', 'rol' => $user['rol']];
    }

    public function cerrarSesion()
    {
        session_unset();
        session_destroy();
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $validar = new ValidarUsuario();

    if ($_POST['accion'] === 'login') {
        $resultado = $validar->validarLogin(
            $_POST['usuario'] ?? '',
            $_POST['password'] ?? ''
        );

        if ($resultado['success']) {
            // Redirigir según el rol
            if ($resultado['rol'] === 'admin') {
                header('Location: ../vista/menu_principal.php');
            } else {
                header('Location: ../vista/menu_tejedor.php');
            }
            exit();
        } else {
            $_SESSION['mensaje']      = $resultado['mensaje'];
            $_SESSION['tipo_mensaje'] = 'error';
            header('Location: ../index.php');
            exit();
        }

    } elseif ($_POST['accion'] === 'logout') {
        $validar->cerrarSesion();
        header('Location: ../index.php');
        exit();
    }
}
?>