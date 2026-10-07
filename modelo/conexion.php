<?php
// modelo/conexion.php
// Conexión a PostgreSQL. Las credenciales se leen del archivo .env (fuera de la carpeta pública);
// si una variable no está en .env se usa la constante equivalente de config.php, así el proyecto
// sigue funcionando en equipos que aún no migraron sus credenciales.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/env.php';

class Conexion {
    private $conexion;

    public function conectar() {
        $host = env('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost');
        $port = env('DB_PORT', defined('DB_PORT') ? DB_PORT : '5432');
        $name = env('DB_NAME', defined('DB_NAME') ? DB_NAME : 'sistema_login');
        $user = env('DB_USER', defined('DB_USER') ? DB_USER : 'postgres');
        $pass = env('DB_PASS', defined('DB_PASS') ? DB_PASS : '');

        try {
            $dsn = "pgsql:host=$host;port=$port;dbname=$name";
            $this->conexion = new PDO($dsn, $user, $pass);
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->conexion;
        } catch (PDOException $e) {
            // El detalle puede incluir usuario y servidor: va al registro, no a la pantalla
            error_log('Conexion PostgreSQL: ' . $e->getMessage());
            if (strtolower((string)env('APP_DEBUG', 'false')) === 'true') {
                die('Error PostgreSQL: ' . htmlspecialchars($e->getMessage()));
            }
            http_response_code(500);
            die('No se pudo conectar a la base de datos.');
        }
    }

    public function desconectar() {
        $this->conexion = null;
    }
}
?>
