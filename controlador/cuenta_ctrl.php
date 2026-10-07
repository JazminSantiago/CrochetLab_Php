<?php
// controlador/cuenta_ctrl.php
// Acciones de "Mi cuenta" para cualquier usuario con sesión: cambiar contraseña y
// gestionar la verificación en dos pasos (activar, desactivar, regenerar códigos de respaldo).

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';

class CuentaCtrl
{
    private $db;

    public function __construct()
    {
        $this->db = (new Conexion())->conectar();
    }

    private function usuario($uid)
    {
        $st = $this->db->prepare(
            "SELECT id, usuario, password, totp_activo, totp_secreto, totp_ultimo_paso FROM usuarios WHERE id = :id"
        );
        $st->execute([':id' => $uid]);
        return $st->fetch();
    }

    // Comprueba la contraseña actual; los fallos cuentan para el bloqueo de la cuenta
    private function confirmarPassword($u, $password)
    {
        if (cuentaBloqueada($this->db, $u['id'])) {
            return 'Demasiados intentos. Inténtalo de nuevo en unos minutos.';
        }
        if (!is_string($password) || !password_verify($password, $u['password'])) {
            registrarFalloCuenta($this->db, $u['id']);
            return 'La contraseña actual es incorrecta.';
        }
        return null;
    }

    public function cambiarPassword($uid, $actual, $nueva, $confirmar)
    {
        $u = $this->usuario($uid);
        if (!is_string($nueva) || !is_string($confirmar) || $nueva === '') {
            return ['success' => false, 'mensaje' => 'Completa todos los campos.'];
        }
        if ($nueva !== $confirmar) {
            return ['success' => false, 'mensaje' => 'La nueva contraseña y su confirmación no coinciden.'];
        }
        if (($error = validarPassword($nueva)) !== null) {
            return ['success' => false, 'mensaje' => $error];
        }
        if (($error = $this->confirmarPassword($u, $actual)) !== null) {
            return ['success' => false, 'mensaje' => $error];
        }
        if (password_verify($nueva, $u['password'])) {
            return ['success' => false, 'mensaje' => 'La nueva contraseña debe ser distinta de la actual.'];
        }

        $this->db->prepare("UPDATE usuarios SET password = :p, intentos_fallidos = 0 WHERE id = :id")
                 ->execute([':p' => hashPassword($nueva), ':id' => $uid]);
        session_regenerate_id(true);
        auditar('password_cambiada', 'usuarios', $uid, 'El usuario cambió su contraseña');
        return ['success' => true, 'mensaje' => 'Contraseña actualizada correctamente.'];
    }

    public function activar2fa($uid, $codigo)
    {
        $secreto = $_SESSION['totp_pendiente'] ?? '';
        $paso    = (is_string($codigo) && $secreto !== '') ? totpVerificar($secreto, trim($codigo)) : null;
        if ($paso === null) {
            return ['success' => false, 'mensaje' => 'El código no es correcto. Revisa la hora de tu teléfono e inténtalo de nuevo.'];
        }

        $this->db->prepare(
            "UPDATE usuarios SET totp_secreto = :s, totp_activo = TRUE, totp_ultimo_paso = :p WHERE id = :id"
        )->execute([':s' => cifrar($secreto, 'usuarios.totp_secreto'), ':p' => $paso, ':id' => $uid]);

        $_SESSION['codigos_nuevos'] = generarCodigosRespaldo($this->db, (int)$uid);
        unset($_SESSION['totp_pendiente']);
        auditar('2fa_activado', 'usuarios', $uid, 'Verificación en dos pasos activada');
        return ['success' => true, 'mensaje' => 'Verificación en dos pasos activada. Guarda tus códigos de respaldo.'];
    }

    public function desactivar2fa($uid, $password, $codigo)
    {
        if (ADMIN_REQUIERE_2FA && tienePermiso('usuarios', 'escritura')) {
            return ['success' => false, 'mensaje' => 'Tu rol requiere la verificación en dos pasos; no se puede desactivar.'];
        }
        $u = $this->usuario($uid);
        if (!$u['totp_activo']) {
            return ['success' => false, 'mensaje' => 'La verificación en dos pasos ya está desactivada.'];
        }
        if (($error = $this->confirmarPassword($u, $password)) !== null) {
            return ['success' => false, 'mensaje' => $error];
        }
        if (!is_string($codigo) || !verificarSegundoFactor($this->db, $u, $codigo)) {
            registrarFalloCuenta($this->db, $u['id']);
            return ['success' => false, 'mensaje' => 'El código de verificación es incorrecto.'];
        }

        $this->db->prepare(
            "UPDATE usuarios SET totp_activo = FALSE, totp_secreto = NULL, totp_ultimo_paso = 0 WHERE id = :id"
        )->execute([':id' => $uid]);
        $this->db->prepare("DELETE FROM codigos_respaldo WHERE usuario_id = :id")->execute([':id' => $uid]);
        auditar('2fa_desactivado', 'usuarios', $uid, 'Verificación en dos pasos desactivada');
        return ['success' => true, 'mensaje' => 'Verificación en dos pasos desactivada.'];
    }

    public function regenerarCodigos($uid, $password)
    {
        $u = $this->usuario($uid);
        if (!$u['totp_activo']) {
            return ['success' => false, 'mensaje' => 'Primero activa la verificación en dos pasos.'];
        }
        if (($error = $this->confirmarPassword($u, $password)) !== null) {
            return ['success' => false, 'mensaje' => $error];
        }
        $_SESSION['codigos_nuevos'] = generarCodigosRespaldo($this->db, (int)$uid);
        auditar('2fa_codigos_regenerados', 'usuarios', $uid, 'Se generaron nuevos códigos de respaldo');
        return ['success' => true, 'mensaje' => 'Se generaron nuevos códigos de respaldo. Los anteriores ya no sirven.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    requiereSesion();
    verificarCsrf('../vista/mi_cuenta.php');

    $ctrl   = new CuentaCtrl();
    $uid    = $_SESSION['usuario_id'];
    $accion = $_POST['accion'];

    if ($accion === 'cambiar_password') {
        $resultado = $ctrl->cambiarPassword($uid, $_POST['actual'] ?? '', $_POST['nueva'] ?? '', $_POST['confirmar'] ?? '');
    } elseif ($accion === 'activar_2fa') {
        $resultado = $ctrl->activar2fa($uid, $_POST['codigo'] ?? '');
    } elseif ($accion === 'desactivar_2fa') {
        $resultado = $ctrl->desactivar2fa($uid, $_POST['password'] ?? '', $_POST['codigo'] ?? '');
    } elseif ($accion === 'regenerar_codigos') {
        $resultado = $ctrl->regenerarCodigos($uid, $_POST['password'] ?? '');
    } else {
        $resultado = ['success' => false, 'mensaje' => 'Acción no válida'];
    }

    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';
    header('Location: ../vista/mi_cuenta.php');
    exit();
}
?>
