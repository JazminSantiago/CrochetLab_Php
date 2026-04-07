<?php
// controlador/empleados_ctrl.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';

class EmpleadosCtrl
{
    private $db;

    public function __construct()
    {
        $conn = new Conexion();
        $this->db = $conn->conectar();
    }

    // ── Listar todos los empleados con datos de usuario ──
    public function listar()
    {
        $sql = "SELECT e.id, e.telefono, e.direccion, e.fecha_ingreso, e.especialidad,
                       u.id AS usuario_id, u.nombre, u.usuario, u.email, u.activo
                FROM empleados e
                JOIN usuarios u ON e.usuario_id = u.id
                ORDER BY u.nombre ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    // ── Crear usuario + empleado en una transacción ──
    public function crear($datos)
    {
        // Validaciones básicas
        $requeridos = ['usuario','nombre','email','password','especialidad'];
        foreach ($requeridos as $campo) {
            if (empty($datos[$campo])) {
                return ['success' => false, 'mensaje' => 'Todos los campos obligatorios deben llenarse'];
            }
        }

        if (strlen($datos['password']) < 6) {
            return ['success' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres'];
        }

        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'mensaje' => 'El email no es válido'];
        }

        // Verificar duplicados
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE usuario = :u OR email = :e");
        $stmt->execute([':u' => $datos['usuario'], ':e' => $datos['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'mensaje' => 'El usuario o email ya están registrados'];
        }

        try {
            $this->db->beginTransaction();

            // 1. Crear usuario con rol tejedor
            $hash = password_hash($datos['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $this->db->prepare(
                "INSERT INTO usuarios (usuario, nombre, email, password, rol, activo)
                 VALUES (:usuario, :nombre, :email, :password, 'tejedor', true)
                 RETURNING id"
            );
            $stmt->execute([
                ':usuario'  => $datos['usuario'],
                ':nombre'   => $datos['nombre'],
                ':email'    => $datos['email'],
                ':password' => $hash
            ]);
            $usuario_id = $stmt->fetchColumn();

            // 2. Crear registro de empleado
            $stmt = $this->db->prepare(
                "INSERT INTO empleados (usuario_id, telefono, direccion, fecha_ingreso, especialidad)
                 VALUES (:uid, :tel, :dir, :fi, :esp)"
            );
            $stmt->execute([
                ':uid' => $usuario_id,
                ':tel' => $datos['telefono'] ?? null,
                ':dir' => $datos['direccion'] ?? null,
                ':fi'  => !empty($datos['fecha_ingreso']) ? $datos['fecha_ingreso'] : date('Y-m-d'),
                ':esp' => $datos['especialidad']
            ]);

            $this->db->commit();
            return ['success' => true, 'mensaje' => 'Empleado registrado exitosamente'];

        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'mensaje' => 'Error al registrar: ' . $e->getMessage()];
        }
    }

    // ── Obtener un empleado por su empleado.id ──
    public function obtener($id)
    {
        $stmt = $this->db->prepare(
            "SELECT e.id, e.telefono, e.direccion, e.fecha_ingreso, e.especialidad,
                    u.id AS usuario_id, u.nombre, u.usuario, u.email, u.activo
             FROM empleados e
             JOIN usuarios u ON e.usuario_id = u.id
             WHERE e.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // ── Editar datos laborales y de usuario ──
    public function editar($id, $datos)
    {
        $requeridos = ['nombre','email','especialidad'];
        foreach ($requeridos as $campo) {
            if (empty($datos[$campo])) {
                return ['success' => false, 'mensaje' => 'Nombre, email y especialidad son obligatorios'];
            }
        }

        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'mensaje' => 'El email no es válido'];
        }

        // Obtener usuario_id
        $emp = $this->obtener($id);
        if (!$emp) return ['success' => false, 'mensaje' => 'Empleado no encontrado'];

        // Verificar email duplicado (excluyendo al mismo usuario)
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = :e AND id != :uid");
        $stmt->execute([':e' => $datos['email'], ':uid' => $emp['usuario_id']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'mensaje' => 'Ese email ya está en uso'];
        }

        try {
            $this->db->beginTransaction();

            // Actualizar usuario
            $sqlU = "UPDATE usuarios SET nombre = :nombre, email = :email";
            $params = [':nombre' => $datos['nombre'], ':email' => $datos['email'], ':uid' => $emp['usuario_id']];

            // Si se envió nueva contraseña
            if (!empty($datos['password'])) {
                if (strlen($datos['password']) < 6) {
                    return ['success' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres'];
                }
                $sqlU .= ", password = :password";
                $params[':password'] = password_hash($datos['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            }

            $sqlU .= " WHERE id = :uid";
            $this->db->prepare($sqlU)->execute($params);

            // Actualizar empleado
            $this->db->prepare(
                "UPDATE empleados SET telefono = :tel, direccion = :dir,
                 fecha_ingreso = :fi, especialidad = :esp WHERE id = :id"
            )->execute([
                ':tel' => $datos['telefono'] ?? null,
                ':dir' => $datos['direccion'] ?? null,
                ':fi'  => !empty($datos['fecha_ingreso']) ? $datos['fecha_ingreso'] : $emp['fecha_ingreso'],
                ':esp' => $datos['especialidad'],
                ':id'  => $id
            ]);

            $this->db->commit();
            return ['success' => true, 'mensaje' => 'Empleado actualizado correctamente'];

        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'mensaje' => 'Error al actualizar: ' . $e->getMessage()];
        }
    }

    // ── Activar / Desactivar ──
    public function toggleActivo($id)
    {
        $emp = $this->obtener($id);
        if (!$emp) return ['success' => false, 'mensaje' => 'Empleado no encontrado'];

        $nuevoEstado = $emp['activo'] ? false : true;
        $this->db->prepare("UPDATE usuarios SET activo = :a WHERE id = :uid")
                 ->execute([':a' => $nuevoEstado ? 'true' : 'false', ':uid' => $emp['usuario_id']]);

        $msg = $nuevoEstado ? 'Empleado activado' : 'Empleado desactivado';
        return ['success' => true, 'mensaje' => $msg];
    }
}

// ── Procesar peticiones POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // Solo admins
    if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
        header('Location: ../index.php');
        exit();
    }

    $ctrl = new EmpleadosCtrl();
    $accion = $_POST['accion'];

    if ($accion === 'crear') {
        $resultado = $ctrl->crear($_POST);

    } elseif ($accion === 'editar') {
        $resultado = $ctrl->editar($_POST['empleado_id'], $_POST);

    } elseif ($accion === 'toggle_activo') {
        $resultado = $ctrl->toggleActivo($_POST['empleado_id']);
    }

    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';
    header('Location: ../vista/empleados/index.php');
    exit();
}
?>