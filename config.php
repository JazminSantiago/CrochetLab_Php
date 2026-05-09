<?php
// config.php - PostgreSQL

define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'sistema_login');
define('DB_USER', 'postgres');
define('DB_PASS', 'Tamarindo123'); 

define('APP_NAME', 'Sistema de Login');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>