<?php

include __DIR__ . '/../inicializaciones.php';
$table = isset($_GET['tbl']) ? (string)$_GET['tbl'] : '';
$col = isset($_GET['col']) ? (string)$_GET['col'] : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Método no permitido');
}

if ($table === '' || $col === '') {
    die('Falta la tabla o la columna primaria para insertar.');
}

// Datos del formulario
$id = $_POST[$col] ?? ''; //id = $_POST[$pk_column], $pk_column es la columna con pk entonces 
if ($id === '') {
    $row = $conn->query("SELECT COALESCE(MAX(CAST(`$col` AS UNSIGNED)), 0) + 1 AS next_id FROM `$table`")->fetch();
    $id = (string)($row['next_id'] ?? '1');
}

$foto_dir = PROJECT_ROOT . DIRECTORY_SEPARATOR . 'Foto';
foreach ($_FILES as $foto_columna => $archivo) {
    if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        die('Error al subir la foto.');
    }

    $imagen = @getimagesize($archivo['tmp_name']);
    if ($imagen === false) {
        die('El archivo seleccionado no es una imagen válida.');
    }

    $nombre_original = basename($archivo['name']);
    $nombre_original = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre_original);
    $nombre_tabla = preg_replace('/[^A-Za-z0-9_-]/', '_', $table);
    $nombre_id = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$id);
    $nombre_foto = $nombre_tabla . '_' . $nombre_id . '_' . $nombre_original;

    if (!is_dir($foto_dir) && !mkdir($foto_dir, 0755, true)) {
        die('No se pudo crear la carpeta Foto.');
    }

    if (!move_uploaded_file($archivo['tmp_name'], $foto_dir . DIRECTORY_SEPARATOR . $nombre_foto)) {
        die('No se pudo guardar la foto.');
    }

    $_POST[$foto_columna] = $nombre_foto;
}

// Evitar duplicados: si el id ya existe, no insertar
$check = $conn->prepare("SELECT COUNT(*) AS c FROM `$table` WHERE `$col` = ?");
$check->execute([$id]);
$exists = (int)($check->fetch()['c'] ?? 0);
if ($exists > 0) {
    die('Error: ya existe un registro con ' . htmlspecialchars($col) . ' = ' . htmlspecialchars($id));
}
$camposObligatorios = [];
foreach($_POST as $key => $value) {
    $camposObligatorios[$key] = $value;
}
foreach ($camposObligatorios as $k => $v) {
    if ($v === '') {
        die('Falta el campo obligatorio: ' . $k);
    }
}

$columnas = implode(', ', array_keys($camposObligatorios));
$valores = ':' . implode(', :', array_keys($camposObligatorios));
try {
    $sql = $conn->prepare("INSERT INTO $table ($columnas) VALUES ($valores)");
    $sql->execute($camposObligatorios);/*PDO recibe el array $camposObligatorios dentro de execute() y asume la responsabilidad de escapar las comillas de la hora 08:30:00*/
    header("Location: " . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($table));
} catch (PDOException $e) {
    die('Error al insertar en la base de datos: ' . $e->getMessage());
}
/* Despues de 2 hr, 30 minutos de investigacion de funciones y 50gr de azucar el codigo fue completado con exito */