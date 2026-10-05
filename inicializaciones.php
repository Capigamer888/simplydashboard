<?php
/**
 * SimplyDashboard - Inicializaciones y Configuración Base
 * 
 * Este archivo centraliza la configuración del entorno, la sesión y la conexión
 * PDO a la base de datos MySQL local (típicamente bajo el entorno XAMPP).
 * Además, provee funciones globales de sanitización y escape para proteger
 * la aplicación contra vulnerabilidades de inyección SQL y XSS.
 */

// Define la ruta base URL relativa para enlaces y recursos estáticos
if (!defined('BASE_URL')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $subdirs = ['/Dashboard', '/Read', '/Edit', '/Insert', '/Delete'];
    foreach ($subdirs as $sub) {
        if (preg_match('/' . preg_quote($sub, '/') . '$/i', $scriptDir)) {
            $scriptDir = substr($scriptDir, 0, -strlen($sub));
            break;
        }
    }
    $detectedBase = rtrim($scriptDir, '/');
    define('BASE_URL', ($detectedBase !== '' && $detectedBase !== '.') ? $detectedBase : '/simplydashboard');
}

// Define la ruta física absoluta en el disco del servidor
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', __DIR__);
}

// Inicia la sesión de forma segura sin emitir advertencias si ya fue iniciada
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Sanitiza recursivamente datos de entrada eliminando espacios en blanco innecesarios.
 *
 * @param mixed $data Datos provenientes de $_GET, $_POST o entradas de usuario.
 * @return mixed Datos limpios.
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    if (is_string($data)) {
        return trim($data);
    }
    return $data;
}

/**
 * Escapa cadenas para su salida segura en HTML, evitando ataques de Cross-Site Scripting (XSS).
 *
 * @param string|null $value Valor a imprimir en el navegador.
 * @return string Texto seguro con entidades HTML convertidas.
 */
function e(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Valida que un identificador SQL (nombre de tabla o columna) cumpla con el estándar alfanumérico seguro.
 *
 * @param string $identifier Nombre de tabla o columna.
 * @return bool True si es válido y seguro, False en caso contrario.
 */
function is_valid_identifier(string $identifier): bool {
    return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $identifier);
}

// Parámetros de conexión a MySQL local
$servername = "localhost";
$username   = "root";
$password   = "";

// Captura y sanitización del nombre de la base de datos seleccionada
$dbname = sanitize_input($_GET['dbname'] ?? $_SESSION['dbname'] ?? '');

// Si el usuario seleccionó una base de datos válida, la persistimos en la sesión
if ($dbname !== '' && is_valid_identifier($dbname)) {
    $_SESSION['dbname'] = $dbname;
} else {
    $dbname = $_SESSION['dbname'] ?? '';
}

// Cadena de conexión (DSN) para MySQL con codificación UTF-8 multibyte
$dsn = "mysql:host={$servername};charset=utf8mb4";
if ($dbname !== '') {
    $dsn .= ";dbname={$dbname}";
}

$conn = null;

try {
    // Establecimiento de conexión PDO con opciones estrictas de seguridad
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones ante cualquier error SQL
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Retorna resultados como arreglos asociativos
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Utiliza sentencias preparadas nativas del motor MySQL
    ]);
} catch (PDOException $e) {
    // Si la conexión falla, se notifica de forma segura sin exponer credenciales
    die("Error al conectar con la base de datos MySQL: " . e($e->getMessage()));
}
