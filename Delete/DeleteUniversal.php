<?php
/**
 * SimplyDashboard - Eliminación Universal de Registros (Delete Action)
 * 
 * Este archivo elimina de forma segura un registro específico de una tabla:
 * 1. Sanitiza los parámetros de tabla (`tbl`), columna clave (`col`) y valor de ID (`id`).
 * 2. Valida la existencia de la tabla y la columna contra `INFORMATION_SCHEMA` de MySQL
 *    para garantizar que no se inyecten identificadores maliciosos.
 * 3. Ejecuta una sentencia `DELETE` parametrizada mediante PDO.
 * 4. Redirige de vuelta a la vista de la tabla.
 */

require_once __DIR__ . '/../inicializaciones.php';

// Sanitización de parámetros recibidos por GET
$table = sanitize_input($_GET['tbl'] ?? '');
$col   = sanitize_input($_GET['col'] ?? '');
$id    = sanitize_input($_GET['id'] ?? '');

// Validación de presencia de parámetros obligatorios
if ($table === '' || $col === '' || $id === '' || !is_valid_identifier($table) || !is_valid_identifier($col)) {
    header("Location: " . BASE_URL . "/Dashboard/dashboard.php");
    exit;
}

// 1. Validación de tabla autorizada mediante INFORMATION_SCHEMA.TABLES
$stmtTable = $conn->prepare("
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ?
");
$stmtTable->execute([$table]);
if ((int)$stmtTable->fetchColumn() === 0) {
    die("Error de seguridad: La tabla especificada no existe.");
}

// 2. Validación de columna autorizada mediante INFORMATION_SCHEMA.COLUMNS
$stmtCol = $conn->prepare("
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ? 
      AND COLUMN_NAME = ?
");
$stmtCol->execute([$table, $col]);
if ((int)$stmtCol->fetchColumn() === 0) {
    die("Error de seguridad: La columna clave especificada no pertenece a la tabla.");
}

// 3. Ejecución segura de la eliminación mediante sentencia preparada parametrizada
try {
    $sql = "DELETE FROM `{$table}` WHERE `{$col}` = :id";
    $stmtDelete = $conn->prepare($sql);
    $stmtDelete->bindValue(':id', $id, PDO::PARAM_STR);
    $stmtDelete->execute();

    // Redirección exitosa de vuelta a la tabla con mensaje de confirmación
    header("Location: " . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($table) . "&success=" . urlencode("Registro eliminado correctamente."));
    exit;
} catch (PDOException $e) {
    header("Location: " . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($table) . "&error=" . urlencode("No se pudo eliminar el registro: " . $e->getMessage()));
    exit;
}