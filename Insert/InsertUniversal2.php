<?php
/**
 * SimplyDashboard - Procesamiento de Inserción de Registros (Insert Action)
 * 
 * Este archivo recibe los datos del formulario de inserción y realiza:
 * 1. Sanitización de parámetros y validación de la tabla contra INFORMATION_SCHEMA.
 * 2. Cálculo o verificación de clave primaria única para evitar duplicados.
 * 3. Procesamiento y almacenamiento seguro de imágenes en la carpeta `Foto/`.
 * 4. Detección de columnas que admiten valores nulos (`IS_NULLABLE`) para insertar `NULL` en campos vacíos.
 * 5. Inserción de datos utilizando sentencias preparadas parametrizadas en PDO.
 */

require_once __DIR__ . '/../inicializaciones.php';

// Validación del método HTTP POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Método no permitido.");
}

// Sanitización de parámetros GET
$table = sanitize_input($_GET['tbl'] ?? '');
$col   = sanitize_input($_GET['col'] ?? '');

if ($table === '' || !is_valid_identifier($table)) {
    die("Error: Parámetros de tabla no válidos.");
}

// 1. Validar columnas autorizadas y nulabilidad mediante INFORMATION_SCHEMA.COLUMNS
$stmtCols = $conn->prepare("
    SELECT COLUMN_NAME, IS_NULLABLE 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ?
");
$stmtCols->execute([$table]);
$colsInfo = $stmtCols->fetchAll(PDO::FETCH_ASSOC) ?: [];
$validColumns = array_column($colsInfo, 'COLUMN_NAME');

if (empty($validColumns)) {
    die("Error: La tabla especificada no existe.");
}

$nullableMap = [];
foreach ($colsInfo as $c) {
    $nullableMap[$c['COLUMN_NAME']] = (strtoupper($c['IS_NULLABLE'] ?? '') === 'YES');
}

// 2. Manejo de Clave Primaria: Si el usuario dejó el ID vacío, calcular el siguiente
$id = isset($_POST[$col]) ? trim((string)$_POST[$col]) : '';
if ($id === '' && $col !== '' && is_valid_identifier($col)) {
    try {
        $stmtNext = $conn->query("SELECT COALESCE(MAX(CAST(`{$col}` AS UNSIGNED)), 0) + 1 AS next_id FROM `{$table}`");
        $id = (string)($stmtNext->fetchColumn() ?: '1');
        $_POST[$col] = $id;
    } catch (PDOException $e) {
        $id = '';
    }
}

// 3. Procesamiento seguro de imágenes enviadas mediante $_FILES
$fotoDir = PROJECT_ROOT . DIRECTORY_SEPARATOR . 'Foto';
$allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

foreach ($_FILES as $fotoCol => $archivo) {
    if (!in_array($fotoCol, $validColumns, true)) {
        continue;
    }

    if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        die("Error al subir el archivo de imagen.");
    }

    // Comprobación de imagen gráfica válida
    if (@getimagesize($archivo['tmp_name']) === false) {
        die("El archivo subido no es una imagen válida.");
    }

    // Comprobación de extensión permitida
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        die("Extensión de imagen no permitida: ." . e($ext));
    }

    // Nombre único aleatorio para evitar sobreescritura accidental
    $safeTable  = preg_replace('/[^A-Za-z0-9_-]/', '_', $table);
    $safeId     = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$id);
    $nombreFoto = $safeTable . '_' . $safeId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;

    if (!is_dir($fotoDir) && !mkdir($fotoDir, 0755, true)) {
        die("No se pudo crear la carpeta para almacenar fotos.");
    }

    if (!move_uploaded_file($archivo['tmp_name'], $fotoDir . DIRECTORY_SEPARATOR . $nombreFoto)) {
        die("Error al guardar la imagen en el servidor.");
    }

    $_POST[$fotoCol] = $nombreFoto;
}

// 4. Verificación de duplicados para la clave primaria
if ($col !== '' && in_array($col, $validColumns, true) && $id !== '') {
    $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` = ?");
    $stmtCheck->execute([$id]);
    if ((int)$stmtCheck->fetchColumn() > 0) {
        die("Error: Ya existe un registro con " . e($col) . " = '" . e($id) . "'.");
    }
}

// 5. Construcción dinámica de la sentencia INSERT con parámetros seguros
$insertFields = [];
$insertValues = [];
$params = [];
$idx = 0;

foreach ($_POST as $colName => $val) {
    if (!in_array($colName, $validColumns, true)) {
        continue;
    }

    $cleanVal = is_string($val) ? trim($val) : $val;

    // Si el ID está vacío y la columna es autoincremental, se omite para que MySQL la asigne
    if ($colName === $col && $cleanVal === '') {
        continue;
    }

    $placeholder = ":p_{$idx}";
    $insertFields[] = "`{$colName}`";
    $insertValues[] = $placeholder;

    if ($cleanVal === '') {
        $params[$placeholder] = ($nullableMap[$colName] ?? true) ? null : '';
    } else {
        $params[$placeholder] = $cleanVal;
    }

    $idx++;
}

if (empty($insertFields)) {
    die("Error: No se proporcionaron campos válidos para insertar.");
}

try {
    $colsSql = implode(', ', $insertFields);
    $valsSql = implode(', ', $insertValues);

    $sql = "INSERT INTO `{$table}` ({$colsSql}) VALUES ({$valsSql})";
    $stmtInsert = $conn->prepare($sql);
    $stmtInsert->execute($params);

    header("Location: " . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($table));
    exit;
} catch (PDOException $e) {
    die("Error al insertar el registro en la base de datos: " . e($e->getMessage()));
}