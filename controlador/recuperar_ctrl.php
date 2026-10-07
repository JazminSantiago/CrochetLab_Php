<?php
// controlador/recuperar_ctrl.php
// Recuperación de contraseña: enlace de un solo uso enviado al correo (15 min de vigencia).
// Si la cuenta tiene verificación en dos pasos, también se exige el código al restablecer.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/autorizacion.php';
require_once __DIR__ . '/../modelo/correo.php';

class RecuperarCtrl
{
    private $db;
    const MINUTOS_VALIDEZ = 15;

    public function __construct()
    {
        $this->db = (new Conexion())->conectar();
    }

    // Nunca revela si el correo existe: la respuesta al usuario es siempre la misma.
    public function solicitar($email): void
    {
        if (!is_string($email)) {
            return;
        }
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // Límite por IP: 10 solicitudes por hora
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM auditoria
             WHERE accion = 'recuperacion_solicitada' AND ip = :ip AND fecha > NOW() - INTERVAL '1 hour'"
        );
        $st->execute([':ip' => ipCliente()]);
        if ((int)$st->fetchColumn() >= 10) {
            return;
        }
        auditar('recuperacion_solicitada', 'usuarios', null, 'Solicitud de recuperación de contraseña');

        $st = $this->db->prepare(
            "SELECT id, usuario, nombre, email FROM usuarios WHERE LOWER(email) = :e AND activo = TRUE LIMIT 1"
        );
        $st->execute([':e' => $email]);
        $u = $st->fetch();
        if (!$u) {
            return;
        }

        // Límite por cuenta: 3 enlaces por hora
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM recuperacion_tokens WHERE usuario_id = :u AND fecha > NOW() - INTERVAL '1 hour'"
        );
        $st->execute([':u' => $u['id']]);
        if ((int)$st->fetchColumn() >= 3) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $min   = (int)self::MINUTOS_VALIDEZ;
        $this->db->prepare(
            "INSERT INTO recuperacion_tokens (usuario_id, token_hash, expira, ip)
             VALUES (:u, :h, NOW() + INTERVAL '$min minutes', :ip)"
        )->execute([':u' => $u['id'], ':h' => hash('sha256', $token), ':ip' => ipCliente()]);

        // La URL sale de APP_URL (.env), nunca de la cabecera Host que envía el cliente
        $url  = rtrim((string)env('APP_URL', 'http://localhost:8000'), '/') . '/vista/restablecer.php?token=' . $token;
        $html = plantillaCorreo(
            'Restablece tu contraseña',
            'Hola ' . htmlspecialchars($u['nombre']) . ', recibimos una solicitud para restablecer la contraseña de tu cuenta. '
            . 'El enlace es válido por ' . $min . ' minutos y solo puede usarse una vez.',
            'Crear nueva contraseña',
            $url,
            'Si tú no lo solicitaste, ignora este mensaje: tu contraseña actual no cambiará.'
        );
        enviarCorreo($u['email'], $u['nombre'], 'Recupera tu contraseña de CrochetLab', $html);
    }

    // Devuelve la fila del usuario dueño de un token vigente, o null
    public function buscarToken($token)
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $st = $this->db->prepare(
            "SELECT u.id, u.usuario, u.activo, u.totp_activo, u.totp_secreto, u.totp_ultimo_paso,
                    (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS bloqueado
             FROM recuperacion_tokens t
             JOIN usuarios u ON u.id = t.usuario_id
             WHERE t.token_hash = :h AND t.usado = FALSE AND t.expira > NOW()"
        );
        $st->execute([':h' => hash('sha256', $token)]);
        $fila = $st->fetch();
        return ($fila && $fila['activo']) ? $fila : null;
    }

    public function restablecer($token, $nueva, $confirmar, $codigo)
    {
        $fila = $this->buscarToken($token);
        if (!$fila) {
            return ['success' => false, 'invalido' => true,
                    'mensaje' => 'El enlace no es válido o ya expiró. Solicita uno nuevo.'];
        }
        if (!is_string($nueva) || !is_string($confirmar) || $nueva === '') {
            return ['success' => false, 'mensaje' => 'Escribe y confirma la nueva contraseña.'];
        }
        if ($nueva !== $confirmar) {
            return ['success' => false, 'mensaje' => 'Las contraseñas no coinciden.'];
        }
        if (($error = validarPassword($nueva)) !== null) {
            return ['success' => false, 'mensaje' => $error];
        }

        // Con 2FA activo, el correo por sí solo no basta
        if ($fila['totp_activo']) {
            if ($fila['bloqueado']) {
                return ['success' => false, 'mensaje' => 'Demasiados intentos. Inténtalo de nuevo en unos minutos.'];
            }
            if (!is_string($codigo) || !verificarSegundoFactor($this->db, $fila, $codigo)) {
                registrarFalloCuenta($this->db, $fila['id']);
                return ['success' => false, 'mensaje' => 'El código de verificación es incorrecto.'];
            }
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE usuarios SET password = :p, intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id"
            )->execute([':p' => hashPassword($nueva), ':id' => $fila['id']]);
            // Invalida este y cualquier otro enlace pendiente de la cuenta
            $this->db->prepare(
                "UPDATE recuperacion_tokens SET usado = TRUE WHERE usuario_id = :id AND usado = FALSE"
            )->execute([':id' => $fila['id']]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('restablecer: ' . $e->getMessage());
            return ['success' => false, 'mensaje' => 'No se pudo cambiar la contraseña. Inténtalo de nuevo.'];
        }

        auditar('password_restablecida', 'usuarios', $fila['id'], 'Contraseña restablecida con enlace de correo', $fila['usuario']);
        return ['success' => true, 'mensaje' => 'Contraseña actualizada. Ya puedes iniciar sesión.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $ctrl   = new RecuperarCtrl();
    $accion = $_POST['accion'];

    if ($accion === 'solicitar') {
        verificarCsrf('../vista/recuperar.php');
        $ctrl->solicitar($_POST['email'] ?? '');
        $_SESSION['mensaje']      = 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja (y el spam).';
        $_SESSION['tipo_mensaje'] = 'success';
        header('Location: ../vista/recuperar.php');
        exit();

    } elseif ($accion === 'restablecer') {
        verificarCsrf('../vista/recuperar.php');
        $token     = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
        $resultado = $ctrl->restablecer($token, $_POST['nueva'] ?? '', $_POST['confirmar'] ?? '', $_POST['codigo'] ?? '');

        $_SESSION['mensaje']      = $resultado['mensaje'];
        $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';

        if ($resultado['success']) {
            header('Location: ../index.php');
        } elseif (!empty($resultado['invalido']) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            header('Location: ../vista/recuperar.php');
        } else {
            header('Location: ../vista/restablecer.php?token=' . $token);
        }
        exit();
    }
}
?>
