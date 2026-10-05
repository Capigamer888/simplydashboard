<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', '/CarlosHub');
}
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', __DIR__);
}
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = $_GET['dbname'] ?? $_SESSION['dbname'] ?? '';

if ($dbname !== '') {
    $_SESSION['dbname'] = $dbname;
}

$dsn = "mysql:host={$servername};dbname={$dbname};charset=utf8mb4";

try {
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
