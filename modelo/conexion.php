<?php
class Conexion {
    private $conexion;

    public function conectar() {
        try {
            $this->conexion = new PDO(
                "pgsql:host=localhost;port=5432;dbname=sistema_login",
                'postgres',
                'Tamarindo123',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            return $this->conexion;
        } catch(PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public function desconectar() {
        $this->conexion = null;
    }
}
?>