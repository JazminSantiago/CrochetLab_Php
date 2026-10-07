<?php
// controlador/permisos_ctrl.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';

class PermisosCtrl
{
    private $db;

    public function __construct()
    {
        $this->db = (new Conexion())->conectar();
    }

    // ── Verificar si el usuario actual es superadmin ──
    public function esSuperadmin($usuario_id)
    {
        $stmt = $this->db->prepare("SELECT es_superadmin FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $usuario_id]);
        $row = $stmt->fetch();
        return $row && $row['es_superadmin'];
    }

    // ── Cambiar contraseña ──
    public function cambiarPassword($usuario_id, $actual, $nueva, $confirmar)
    {
        if (empty($actual) || empty($nueva) || empty($confirmar))
            return ['success' => false, 'mensaje' => 'Todos los campos son obligatorios'];

        if ($nueva !== $confirmar)
            return ['success' => false, 'mensaje' => 'La nueva contraseña y su confirmación no coinciden'];

        if (($errorPolitica = validarPassword($nueva)) !== null)
            return ['success' => false, 'mensaje' => $errorPolitica];

        $stmt = $this->db->prepare("SELECT password FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $usuario_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($actual, $user['password']))
            return ['success' => false, 'mensaje' => 'La contraseña actual es incorrecta'];

        $hash = password_hash($nueva, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare("UPDATE usuarios SET password = :p WHERE id = :id")
                 ->execute([':p' => $hash, ':id' => $usuario_id]);

        return ['success' => true, 'mensaje' => 'Contraseña actualizada correctamente'];
    }

    // ── Listar todos los admins y superadmin ──
    public function listarAdmins()
    {
        $stmt = $this->db->query(
            "SELECT id, nombre, usuario, email, es_superadmin, ultimo_acceso
             FROM usuarios
             WHERE rol_id = (SELECT id FROM roles WHERE nombre = 'Administrador')
             ORDER BY es_superadmin DESC, nombre ASC"
        );
        return $stmt->fetchAll();
    }

    // ── Listar tejedores que pueden ser promovidos ──
    public function listarTejedores()
    {
        $stmt = $this->db->query(
            "SELECT id, nombre, usuario, email, ultimo_acceso
             FROM usuarios
             WHERE rol_id = (SELECT id FROM roles WHERE nombre = 'Editor') AND activo = true
             ORDER BY nombre ASC"
        );
        return $stmt->fetchAll();
    }

    // ── Promover tejedor a admin ──
    public function promoverAdmin($target_id, $solicitante_id, $password_confirm)
    {
        if (!$this->esSuperadmin($solicitante_id))
            return ['success' => false, 'mensaje' => 'Solo el superadmin puede promover usuarios'];

        // Verificar contraseña del superadmin
        $stmt = $this->db->prepare("SELECT password FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $solicitante_id]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password_confirm, $user['password']))
            return ['success' => false, 'mensaje' => 'Contraseña incorrecta'];

        $stmt = $this->db->prepare("SELECT u.nombre, r.nombre AS rol FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = :id");
        $stmt->execute([':id' => $target_id]);
        $target = $stmt->fetch();
        if (!$target) return ['success' => false, 'mensaje' => 'Usuario no encontrado'];
        if ($target['rol'] === 'Administrador') return ['success' => false, 'mensaje' => 'El usuario ya es admin'];

        $this->db->prepare("UPDATE usuarios SET rol_id = (SELECT id FROM roles WHERE nombre = 'Administrador') WHERE id = :id")
                 ->execute([':id' => $target_id]);

        $this->registrarLog($solicitante_id, 'promover_admin', "Promovió a {$target['nombre']} (ID:{$target_id}) a admin");
        return ['success' => true, 'mensaje' => "{$target['nombre']} ahora es administrador"];
    }

    // ── Degradar admin a tejedor ──
    public function degradarAdmin($target_id, $solicitante_id, $password_confirm)
    {
        if (!$this->esSuperadmin($solicitante_id))
            return ['success' => false, 'mensaje' => 'Solo el superadmin puede cambiar roles'];

        // No puede degradarse a sí mismo
        if ($target_id == $solicitante_id)
            return ['success' => false, 'mensaje' => 'No puedes degradarte a ti mismo'];

        // Verificar contraseña
        $stmt = $this->db->prepare("SELECT password FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $solicitante_id]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password_confirm, $user['password']))
            return ['success' => false, 'mensaje' => 'Contraseña incorrecta'];

        $stmt = $this->db->prepare("SELECT u.nombre, r.nombre AS rol, u.es_superadmin FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = :id");
        $stmt->execute([':id' => $target_id]);
        $target = $stmt->fetch();
        if (!$target) return ['success' => false, 'mensaje' => 'Usuario no encontrado'];
        if ($target['es_superadmin']) return ['success' => false, 'mensaje' => 'No puedes degradar al superadmin'];
        if ($target['rol'] !== 'Administrador') return ['success' => false, 'mensaje' => 'El usuario no es admin'];

        $this->db->prepare("UPDATE usuarios SET rol_id = (SELECT id FROM roles WHERE nombre = 'Editor') WHERE id = :id")
                 ->execute([':id' => $target_id]);

        $this->registrarLog($solicitante_id, 'degradar_admin', "Degradó a {$target['nombre']} (ID:{$target_id}) a tejedor");
        return ['success' => true, 'mensaje' => "{$target['nombre']} ahora es tejedor"];
    }

    // ── Registrar log ──
    private function registrarLog($usuario_id, $accion, $detalle)
    {
        try {
            $this->db->prepare(
                "INSERT INTO log_permisos (usuario_id, accion, detalle, fecha)
                 VALUES (:uid, :acc, :det, NOW())"
            )->execute([':uid' => $usuario_id, ':acc' => $accion, ':det' => $detalle]);
        } catch (PDOException $e) {
            // Si la tabla no existe aún, no interrumpir
        }
    }
}

// ── Procesar POST ──
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME']) &&
    $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    require_once __DIR__ . '/../modelo/autorizacion.php';
    requierePermiso('usuarios', 'lectura');

    $ctrl   = new PermisosCtrl();
    $uid    = $_SESSION['usuario_id'];
    $accion = $_POST['accion'];

    if ($accion === 'cambiar_password') {
        $resultado = $ctrl->cambiarPassword($uid, $_POST['actual'], $_POST['nueva'], $_POST['confirmar']);

    } elseif ($accion === 'promover' && tienePermiso('usuarios', 'escritura') && $ctrl->esSuperadmin($uid)) {
        $resultado = $ctrl->promoverAdmin($_POST['target_id'], $uid, $_POST['password_confirm']);

    } elseif ($accion === 'degradar' && tienePermiso('usuarios', 'escritura') && $ctrl->esSuperadmin($uid)) {
        $resultado = $ctrl->degradarAdmin($_POST['target_id'], $uid, $_POST['password_confirm']);

    } else {
        $resultado = ['success' => false, 'mensaje' => 'Acción no permitida'];
        auditar('acceso_denegado', 'usuarios', $_POST['target_id'] ?? null, 'Acción no permitida: ' . $accion);
    }

    if (!empty($resultado['success'])) {
        auditar($accion, 'usuarios', $_POST['target_id'] ?? null, $resultado['mensaje']);
    }

    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';
    header('Location: ../vista/permisos_acceso.php'); exit();
}
?>