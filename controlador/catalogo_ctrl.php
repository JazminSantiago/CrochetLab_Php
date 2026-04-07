<?php
// controlador/catalogo_ctrl.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modelo/conexion.php';

class CatalogoCtrl
{
    private $db;

    public function __construct()
    {
        $conn = new Conexion();
        $this->db = $conn->conectar();
    }

    // ── Listar productos con categoría ──
    public function listar()
    {
        $sql = "SELECT c.id, c.nombre, c.descripcion, c.precio, c.stock_actual,
                       c.stock_minimo, c.tiempo_elaboracion_dias, c.activo, c.imagen_ruta,
                       cat.nombre AS categoria_nombre, cat.id AS categoria_id
                FROM catalogo c
                LEFT JOIN categorias cat ON c.categoria_id = cat.id
                ORDER BY cat.nombre ASC, c.nombre ASC";
        return $this->db->query($sql)->fetchAll();
    }

    // ── Listar categorías (para el select del formulario) ──
    public function listarCategorias()
    {
        return $this->db->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC")->fetchAll();
    }

    // ── Crear producto ──
    public function crear($datos, $archivo = null)
    {
        $err = $this->validar($datos);
        if ($err) return ['success' => false, 'mensaje' => $err];

        $imagen_ruta = $this->subirImagen($archivo);
        if (isset($imagen_ruta['error'])) return ['success' => false, 'mensaje' => $imagen_ruta['error']];

        try {
            $this->db->prepare(
                "INSERT INTO catalogo (categoria_id, nombre, descripcion, precio, stock_actual, stock_minimo, tiempo_elaboracion_dias, imagen_ruta, activo)
                 VALUES (:cat, :nom, :desc, :precio, :sa, :sm, :dias, :img, true)"
            )->execute([
                ':cat'   => $datos['categoria_id'] ?: null,
                ':nom'   => $datos['nombre'],
                ':desc'  => $datos['descripcion'] ?? null,
                ':precio'=> $datos['precio'] ?: null,
                ':sa'    => (int)($datos['stock_actual'] ?? 0),
                ':sm'    => (int)($datos['stock_minimo'] ?? 5),
                ':dias'  => $datos['tiempo_elaboracion_dias'] ?: null,
                ':img'   => $imagen_ruta,
            ]);
            return ['success' => true, 'mensaje' => 'Producto agregado al catálogo'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensaje' => 'Error al crear: ' . $e->getMessage()];
        }
    }

    // ── Obtener un producto ──
    public function obtener($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM catalogo WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // ── Editar producto ──
    public function editar($id, $datos, $archivo = null)
    {
        $err = $this->validar($datos);
        if ($err) return ['success' => false, 'mensaje' => $err];

        // Solo subir nueva imagen si se envió una
        $imagen_ruta = null;
        if ($archivo && $archivo['size'] > 0) {
            $imagen_ruta = $this->subirImagen($archivo);
            if (isset($imagen_ruta['error'])) return ['success' => false, 'mensaje' => $imagen_ruta['error']];
        }

        try {
            // Si hay nueva imagen, actualizarla; si no, conservar la anterior
            if ($imagen_ruta) {
                $this->db->prepare(
                    "UPDATE catalogo SET categoria_id = :cat, nombre = :nom, descripcion = :desc,
                     precio = :precio, stock_actual = :sa, stock_minimo = :sm,
                     tiempo_elaboracion_dias = :dias, imagen_ruta = :img WHERE id = :id"
                )->execute([
                    ':cat'   => $datos['categoria_id'] ?: null,
                    ':nom'   => $datos['nombre'],
                    ':desc'  => $datos['descripcion'] ?? null,
                    ':precio'=> $datos['precio'] ?: null,
                    ':sa'    => (int)($datos['stock_actual'] ?? 0),
                    ':sm'    => (int)($datos['stock_minimo'] ?? 5),
                    ':dias'  => $datos['tiempo_elaboracion_dias'] ?: null,
                    ':img'   => $imagen_ruta,
                    ':id'    => $id
                ]);
            } else {
                $this->db->prepare(
                    "UPDATE catalogo SET categoria_id = :cat, nombre = :nom, descripcion = :desc,
                     precio = :precio, stock_actual = :sa, stock_minimo = :sm,
                     tiempo_elaboracion_dias = :dias WHERE id = :id"
                )->execute([
                    ':cat'   => $datos['categoria_id'] ?: null,
                    ':nom'   => $datos['nombre'],
                    ':desc'  => $datos['descripcion'] ?? null,
                    ':precio'=> $datos['precio'] ?: null,
                    ':sa'    => (int)($datos['stock_actual'] ?? 0),
                    ':sm'    => (int)($datos['stock_minimo'] ?? 5),
                    ':dias'  => $datos['tiempo_elaboracion_dias'] ?: null,
                    ':id'    => $id
                ]);
            }
            return ['success' => true, 'mensaje' => 'Producto actualizado correctamente'];
        } catch (PDOException $e) {
            return ['success' => false, 'mensaje' => 'Error al actualizar: ' . $e->getMessage()];
        }
    }

    // ── Activar / Desactivar ──
    public function toggleActivo($id)
    {
         $prod = $this->obtener($id);
        if (!$prod) return ['success' => false, 'mensaje' => 'Producto no encontrado'];

        // PostgreSQL devuelve booleanos como 't'/'f' con PDO
        $estaActivo = ($prod['activo'] === true || $prod['activo'] === 't');
        $nuevo = $estaActivo ? 'false' : 'true';

         $this->db->prepare("UPDATE catalogo SET activo = :a WHERE id = :id")
                ->execute([':a' => $nuevo, ':id' => $id]);

        $msg = $estaActivo ? 'Producto desactivado' : 'Producto activado';
        return ['success' => true, 'mensaje' => $msg];
    }

    // ── Subir imagen ──
    private function subirImagen($archivo)
    {
        if (!$archivo || $archivo['size'] === 0) return null;

        $permitidos = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($archivo['type'], $permitidos)) {
            return ['error' => 'Solo se permiten imágenes JPG, PNG o WEBP'];
        }

        if ($archivo['size'] > 2 * 1024 * 1024) {
            return ['error' => 'La imagen no debe superar 2MB'];
        }

        $ext      = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombre   = 'catalogo_' . uniqid() . '.' . $ext;
        $destino  = __DIR__ . '/../assets/uploads/catalogo/' . $nombre;

        // Crear carpeta si no existe
        if (!file_exists(dirname($destino))) {
            mkdir(dirname($destino), 0777, true);
        }

        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            return ['error' => 'Error al guardar la imagen'];
        }

        return 'assets/uploads/catalogo/' . $nombre;
    }

    // ── Validación compartida ──
    private function validar($datos)
    {
        if (empty($datos['nombre'])) return 'El nombre del producto es obligatorio';
        if (isset($datos['precio']) && $datos['precio'] !== '' && !is_numeric($datos['precio']))
            return 'El precio debe ser un número válido';
        if (isset($datos['stock_actual']) && $datos['stock_actual'] !== '' && !is_numeric($datos['stock_actual']))
            return 'El stock actual debe ser un número válido';
        if (isset($datos['stock_minimo']) && $datos['stock_minimo'] !== '' && !is_numeric($datos['stock_minimo']))
            return 'El stock mínimo debe ser un número válido';
        return null;
    }
}

// ── Procesar peticiones POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
        header('Location: ../index.php');
        exit();
    }

    $ctrl   = new CatalogoCtrl();
    $accion = $_POST['accion'];

    if ($accion === 'crear') {
        $resultado = $ctrl->crear($_POST, $_FILES['imagen'] ?? null);
    } elseif ($accion === 'editar') {
        $resultado = $ctrl->editar($_POST['producto_id'], $_POST, $_FILES['imagen'] ?? null);
    } elseif ($accion === 'toggle_activo') {
        $resultado = $ctrl->toggleActivo($_POST['producto_id']);
    }

    $_SESSION['mensaje']      = $resultado['mensaje'];
    $_SESSION['tipo_mensaje'] = $resultado['success'] ? 'success' : 'error';
    header('Location: ../vista/catalogo/index_catalogo.php');
    exit();
}
?>