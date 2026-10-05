<?php
/**
 * SimplyDashboard - Procesamiento de Actualización de Registros (Update)
 * 
 * Este archivo procesa la actualización de datos enviada desde el formulario de edición:
 * 1. Sanitiza los parámetros de tabla y clave primaria.
 * 2. Consulta `INFORMATION_SCHEMA.COLUMNS` para crear una lista blanca estricta de columnas válidas.
 * 3. Valida y procesa la subida de imágenes (revisión de extensión, tipo MIME real y nombre único).
 * 4. Construye y ejecuta una sentencia `UPDATE` completamente parametrizada con PDO.
 */

require_once __DIR__ . '/../inicializaciones.php';

// Validación estricta del método de envío
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/Dashboard/dashboard.php");
    exit;
}

// Sanitización de parámetros GET
$table = sanitize_input($_GET['tbl'] ?? '');
$idv   = sanitize_input($_GET['id'] ?? '');
$pkCol = sanitize_input($_GET['col'] ?? '');

if ($table === '' || $idv === '' || !is_valid_identifier($table)) {
    die("Error: Parámetros inválidos para actualizar el registro.");
}

// 1. Obtener lista blanca de columnas de la tabla mediante INFORMATION_SCHEMA.COLUMNS
$stmtCols = $conn->prepare("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ?
");
$stmtCols->execute([$table]);
$validColumns = $stmtCols->fetchAll(PDO::FETCH_COLUMN) ?: [];

if (empty($validColumns)) {
    die("Error: La tabla especificada no existe o no tiene columnas.");
}

// Si la clave primaria no fue provista, se detecta desde INFORMATION_SCHEMA.KEY_COLUMN_USAGE
if ($pkCol === '' || !in_array($pkCol, $validColumns, true)) {
    $stmtPk = $conn->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = ? 
          AND CONSTRAINT_NAME = 'PRIMARY' 
        LIMIT 1
    ");
    $stmtPk->execute([$table]);
    $pkCol = (string)($stmtPk->fetchColumn() ?: $validColumns[0]);
}

// 2. Procesamiento seguro de subida de imágenes
$fotoDir = PROJECT_ROOT . DIRECTORY_SEPARATOR . 'Foto';
$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

foreach ($_FILES as $fotoCol => $archivo) {
    if (!in_array($fotoCol, $validColumns, true)) {
        continue;
    }

    // Si el usuario no subió una nueva foto, conserva la anterior
    if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
        if (!empty($_POST['current_foto_' . $fotoCol])) {
            $_POST[$fotoCol] = sanitize_input($_POST['current_foto_' . $fotoCol]);
        }
        continue;
    }

    // Verificación de subida correcta
    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        die("Error al subir el archivo de imagen.");
    }

    // Verificación del contenido real del archivo gráfico
    if (@getimagesize($archivo['tmp_name']) === false) {
        die("El archivo subido no es una imagen válida.");
    }

    // Verificación estricta de la extensión
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        die("Extensión no permitida: " . e($ext));
    }

    // Generación de un nombre de archivo seguro y aleatorio para evitar colisiones
    $safeTable  = preg_replace('/[^A-Za-z0-9_-]/', '_', $table);
    $safeId     = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$idv);
    $nombreFoto = $safeTable . '_' . $safeId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;

    if (!is_dir($fotoDir) && !mkdir($fotoDir, 0755, true)) {
        die("No se pudo crear la carpeta para almacenar fotos.");
    }

    if (!move_uploaded_file($archivo['tmp_name'], $fotoDir . DIRECTORY_SEPARATOR . $nombreFoto)) {
        die("Error al guardar la imagen en el servidor.");
    }

    $_POST[$fotoCol] = $nombreFoto;
}

// 3. Filtrar datos a actualizar usando la lista blanca (excluyendo la clave primaria)
$updateData = [];
foreach ($_POST as $col => $val) {
    if (in_array($col, $validColumns, true) && $col !== $pkCol) {
        $updateData[$col] = is_string($val) ? trim($val) : $val;
    }
}

if (empty($updateData)) {
    header("Location: " . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($table));
    exit;
}

// 4. Construcción y ejecución segura de la sentencia UPDATE con PDO
try {
    $setParts = [];
    $params = [];
    $idx = 0;

    foreach ($updateData as $col => $value) {
        $paramName = ":val_{$idx}";
        $setParts[] = "`{$col}` = {$paramName}";
        $params[$paramName] = ($value === '' ? null : $value);
        $idx++;
    }

    $setSql = implode(', ', $setParts);
    $params[':old_pk_val'] = $idv;

    $sql = "UPDATE `{$table}` SET {$setSql} WHERE `{$pkCol}` = :old_pk_val";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    header("Location: " . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($table));
    exit;
} catch (PDOException $e) {
    die("Error al actualizar el registro: " . e($e->getMessage()));
}