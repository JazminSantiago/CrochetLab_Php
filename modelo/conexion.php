<?php
require_once __DIR__ . '/../config.php';

class Conexion {
    private $conexion;

    public function conectar() {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $this->conexion = new PDO($dsn, DB_USER, DB_PASS);
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->conexion;
        } catch(PDOException $e) {
            die("Error PostgreSQL: " . $e->getMessage());
        }
    }

    public function desconectar() {
        $this->conexion = null;
    }
}
?>