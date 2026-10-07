<?php
// controlador/validar_usuario.php
// Inicio de sesión en dos fases: (1) usuario o correo + contraseña, (2) segundo factor si está activo.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';

class ValidarUsuario
{
    private $conexion;

    const MSG_GENERICO = 'Usuario o contraseña incorrectos';
    const MSG_BLOQUEO  = 'Demasiados intentos fallidos. Inténtalo de nuevo en unos minutos.';

    public function __construct()
    {
        $db = new Conexion();
        $this->conexion = $db->conectar();
    }

    // Fase 1. Con segundo factor activo NO abre la sesión: deja una verificación pendiente.
    public function validarLogin($identificador, $password)
    {
        if (!is_string($identificador) || !is_string($password) || trim($identificador) === '' || $password === '') {
            return ['success' => false, 'mensaje' => 'Usuario y contraseña son obligatorios'];
        }
        $identificador = trim($identificador);

        // Límite por IP: frena adivinar contraseñas probando muchos usuarios
        if ($this->fallosRecientesIp() >= LOGIN_MAX_FALLOS_IP) {
            registrarAcceso(null, $identificador, 'login_fallido', 'ip_limitada');
            return ['success' => false, 'mensaje' => self::MSG_BLOQUEO];
        }

        // Se permite entrar con el usuario o con el correo
        $stmt = $this->conexion->prepare(
            "SELECT u.id, u.usuario, u.nombre, u.email, u.password, u.rol_id, r.nombre AS rol,
                    u.activo, u.totp_activo,
                    (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS bloqueado
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             WHERE u.usuario = :ident1 OR LOWER(u.email) = LOWER(:ident2)
             ORDER BY (u.usuario = :ident3) DESC
             LIMIT 1"
        );
        $stmt->execute([':ident1' => $identificador, ':ident2' => $identificador, ':ident3' => $identificador]);
        $user = $stmt->fetch();

        if (!$user) {
            // Gasta el mismo tiempo que una verificación real para no revelar qué usuarios existen
            password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            registrarAcceso(null, $identificador, 'login_fallido', 'usuario_inexistente');
            return ['success' => false, 'mensaje' => self::MSG_GENERICO];
        }

        if ($user['bloqueado']) {
            registrarAcceso($user['id'], $identificador, 'login_fallido', 'cuenta_bloqueada');
            return ['success' => false, 'mensaje' => self::MSG_BLOQUEO];
        }

        // La contraseña se verifica antes de revelar el estado de la cuenta
        if (!password_verify($password, $user['password'])) {
            $this->registrarFallo($user['id']);
            registrarAcceso($user['id'], $identificador, 'login_fallido', 'password_incorrecta');
            return ['success' => false, 'mensaje' => self::MSG_GENERICO];
        }

        if (!$user['activo']) {
            registrarAcceso($user['id'], $identificador, 'login_fallido', 'cuenta_desactivada');
            return ['success' => false, 'mensaje' => 'Tu cuenta ha sido desactivada. Contacta al administrador.'];
        }

        // Un Editor (tejedor) debe tener su registro de empleado
        if ($user['rol'] === 'Editor') {
            $stmt2 = $this->conexion->prepare("SELECT id FROM empleados WHERE usuario_id = :uid");
            $stmt2->execute([':uid' => $user['id']]);
            if (!$stmt2->fetch()) {
                registrarAcceso($user['id'], $identificador, 'login_fallido', 'sin_registro_empleado');
                return ['success' => false, 'mensaje' => 'Tu cuenta aún no ha sido habilitada. Contacta al administrador.'];
            }
        }

        if ($user['totp_activo']) {
            session_regenerate_id(true);
            $_SESSION['pre2fa'] = ['uid' => (int)$user['id'], 't' => time(), 'intentos' => 0];
            return ['success' => true, 'requiere_2fa' => true, 'mensaje' => 'Verificación pendiente'];
        }

        $this->completarLogin($user);
        return ['success' => true, 'mensaje' => 'Login exitoso', 'rol' => $user['rol']];
    }

    // Fase 2: código de la app autenticadora o código de respaldo
    public function verificar2fa($codigo)
    {
        $reinicio = ['success' => false, 'reiniciar' => true,
                     'mensaje' => 'La verificación expiró. Inicia sesión de nuevo.'];

        $pre = $_SESSION['pre2fa'] ?? null;
        if (!is_array($pre) || (time() - (int)$pre['t']) > 300) {
            unset($_SESSION['pre2fa']);
            return $reinicio;
        }

        $stmt = $this->conexion->prepare(
            "SELECT u.id, u.usuario, u.nombre, u.email, u.rol_id, r.nombre AS rol,
                    u.activo, u.totp_secreto, u.totp_ultimo_paso,
                    (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS bloqueado
             FROM usuarios u JOIN roles r ON r.id = u.rol_id
             WHERE u.id = :id AND u.totp_activo = TRUE"
        );
        $stmt->execute([':id' => $pre['uid']]);
        $user = $stmt->fetch();

        if (!$user || !$user['activo'] || $user['bloqueado']) {
            unset($_SESSION['pre2fa']);
            return $reinicio;
        }

        if (!is_string($codigo) || !verificarSegundoFactor($this->conexion, $user, $codigo)) {
            $this->registrarFallo($user['id']);
            registrarAcceso($user['id'], $user['usuario'], 'login_fallido', '2fa_incorrecto');
            $_SESSION['pre2fa']['intentos']++;
            if ($_SESSION['pre2fa']['intentos'] >= LOGIN_MAX_INTENTOS) {
                unset($_SESSION['pre2fa']);
                return ['success' => false, 'reiniciar' => true,
                        'mensaje' => 'Demasiados códigos incorrectos. Inicia sesión de nuevo.'];
            }
            return ['success' => false, 'mensaje' => 'Código incorrecto. Inténtalo de nuevo.'];
        }

        unset($_SESSION['pre2fa']);
        $this->completarLogin($user);
        return ['success' => true, 'mensaje' => 'Login exitoso'];
    }

    private function completarLogin(array $user)
    {
        $this->conexion->prepare(
            "UPDATE usuarios SET ultimo_acceso = NOW(), intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id"
        )->execute([':id' => $user['id']]);

        session_regenerate_id(true);   // evita la fijación de sesión

        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario']    = $user['usuario'];
        $_SESSION['nombre']     = $user['nombre'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['rol_id']     = $user['rol_id'];
        $_SESSION['rol']        = $user['rol'];   // solo informativo; los permisos se leen de la BD
        $_SESSION['login_time'] = time();

        registrarAcceso($user['id'], $user['usuario'], 'login_ok');
    }

    private function registrarFallo($idUsuario)
    {
        registrarFalloCuenta($this->conexion, $idUsuario);
    }

    private function fallosRecientesIp()
    {
        $min  = (int)LOGIN_VENTANA_IP_MIN;
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM historial_accesos
             WHERE evento = 'login_fallido' AND ip = :ip AND fecha > NOW() - INTERVAL '$min minutes'"
        );
        $stmt->execute([':ip' => ipCliente()]);
        return (int)$stmt->fetchColumn();
    }

    public function cerrarSesion()
    {
        if (!empty($_SESSION['usuario_id'])) {
            registrarAcceso($_SESSION['usuario_id'], $_SESSION['usuario'] ?? null, 'logout');
        }
        session_unset();
        session_destroy();
    }
}

// Redirige tras un login completo
function irAlInicioTrasLogin()
{
    $destino = rutaInicio();
    if ($destino === null) {
        cerrarSesionLocal();
        session_start();
        $_SESSION['mensaje']      = 'Tu rol no tiene permisos asignados. Contacta al administrador.';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ../index.php');
        exit();
    }
    header('Location: ' . $destino);
    exit();
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $validar = new ValidarUsuario();
    $accion  = $_POST['accion'];

    if ($accion === 'login') {
        verificarCsrf('../index.php');
        $resultado = $validar->validarLogin($_POST['usuario'] ?? '', $_POST['password'] ?? '');

        if ($resultado['success'] && !empty($resultado['requiere_2fa'])) {
            header('Location: ../vista/verificar_2fa.php');
            exit();
        }
        if ($resultado['success']) {
            irAlInicioTrasLogin();
        }
        $_SESSION['mensaje']      = $resultado['mensaje'];
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ../index.php');
        exit();

    } elseif ($accion === 'verificar_2fa') {
        verificarCsrf('../index.php');
        $resultado = $validar->verificar2fa($_POST['codigo'] ?? '');

        if ($resultado['success']) {
            irAlInicioTrasLogin();
        }
        $_SESSION['mensaje']      = $resultado['mensaje'];
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: ' . (!empty($resultado['reiniciar']) ? '../index.php' : '../vista/verificar_2fa.php'));
        exit();

    } elseif ($accion === 'logout') {
        // Sin comprobación CSRF a propósito: cerrar sesión nunca debe quedar bloqueado
        $validar->cerrarSesion();
        header('Location: ../index.php');
        exit();
    }
}
?>
