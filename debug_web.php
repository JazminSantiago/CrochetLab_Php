<?php
// debug_web.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'modelo/conexion.php';

echo "<pre>";
echo "--- Debug User Login via Web ---\n";

$usuario = 'prueba3'; // User from the error log
$password = '1234567'; // Pass from the error log

try {
    $db = new Conexion();
    $conn = $db->conectar();

    echo "Checking user: $usuario\n";

    $sql = "SELECT * FROM usuarios WHERE usuario = :usuario";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':usuario' => $usuario]);
    $user = $stmt->fetch();

    if ($user) {
        echo "User found! ID: " . $user['id'] . "\n";
        echo "Stored Hash: " . $user['password'] . "\n";
        echo "Hash Length: " . strlen($user['password']) . "\n";

        echo "Verifying password '$password'...\n";
        if (password_verify($password, $user['password'])) {
            echo "[SUCCESS] Password matches!\n";
        } else {
            echo "[FAILED] Password does NOT match.\n";
            echo "Test Hash of input: " . password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]) . "\n";
        }
    } else {
        echo "User '$usuario' not found in database.\n";

        // Check all users
        $stmt = $conn->query("SELECT id, usuario FROM usuarios");
        echo "Listing all users:\n";
        while ($row = $stmt->fetch()) {
            echo "- " . $row['id'] . ": " . $row['usuario'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
echo "</pre>";
?>