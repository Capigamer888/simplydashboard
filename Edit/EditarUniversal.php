<?php
include __DIR__ . '/../inicializaciones.php';
$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
$table = isset($_GET['tbl']) ? (string)$_GET['tbl'] :'';

// Primary Key de la tabla
//    SELECT TABLE_NAME, COLUMN_NAME
//    FROM INFORMATION_SCHEMA.COLUMNS
//    WHERE TABLE_SCHEMA = DATABASE()
//      AND TABLE_NAME = ?
//      AND COLUMN_KEY = 'PRI'

$pk = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = database()
      AND TABLE_NAME = ?
      AND CONSTRAINT_NAME = 'PRIMARY'");
$pk->execute([ $table]);
$pk_result = $pk->fetchAll();

$row = [];
if (!empty($pk_result) && $id !== '') {
    $pk_column = $pk_result[0]['COLUMN_NAME']; 

    $row_stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$pk_column` = ?");
    $row_stmt->execute([$id]);
    $row = $row_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

// Foreign Keys de la tabla (incluye tabla y columna referenciada)
$fk = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = database()
      AND TABLE_NAME = ?
      AND REFERENCED_TABLE_NAME IS NOT NULL");
$fk->execute([ $table]);
$fk_result = $fk->fetchAll();
$pk_columns = array_values(array_unique(array_filter(array_column($pk_result, 'COLUMN_NAME'), fn($value) => is_string($value) && $value !== '')));
$fk_columns = array_values(array_unique(array_filter(array_column($fk_result, 'COLUMN_NAME'), fn($value) => is_string($value) && $value !== '')));

// Columnas que NO son PK ni FK, solo de esa tabla
$non_key = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_KEY IN ('', 'UNI')
      AND DATA_TYPE NOT IN ('blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary')
    AND COLUMN_COMMENT NOT IN ('foto')
    AND LOWER(COLUMN_NAME) NOT IN ('foto', 'imagen', 'image')
      
");/*Aunque un if $non_key_result[DATA_TYPE] para identificar los tipo foto ubiera servido*/
$non_key->execute([ $table]);
$non_key_result = $non_key->fetchAll();
$non_key_result = array_values(array_filter($non_key_result, function ($row) use ($pk_columns, $fk_columns) {
    $col_name = (string)($row['COLUMN_NAME'] ?? '');
    return $col_name !== ''
        && !in_array($col_name, $pk_columns, true)
        && !in_array($col_name, $fk_columns, true);
}));

$fotos = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_KEY = ''
    AND (COLUMN_COMMENT = 'foto' OR LOWER(COLUMN_NAME) IN ('foto', 'imagen', 'image'))
");
$fotos->execute([$table]);
$fotos_result = $fotos->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(input.dataset.preview).src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
    <a class="action-link" href='<?= BASE_URL ?>/Read/TablaUniversal.php?tbl=<?php echo urlencode($table) ?>'>Regresar</a>
    <h1 ><?php echo $table ?></h1>
    
    <form method="POST" enctype="multipart/form-data" action="<?= BASE_URL ?>/Edit/UpdateUniversal.php?id=<?php echo urlencode($id)?>&tbl=<?php echo urlencode($table)?>&col=<?php echo urlencode($pk_result[0]['COLUMN_NAME'] ?? '') ?>">
    <?php

    $pk_columns = array_column($pk_result, 'COLUMN_NAME');
    $fk_columns = array_column($fk_result, 'COLUMN_NAME');
    $fk_columns = array_values(array_unique(array_filter($fk_columns, fn($col) => $col !== null && $col !== '')));

    foreach ($pk_result as $pk_row) {
        $col_name = (string)($pk_row['COLUMN_NAME'] ?? '');
        if ($col_name === '') {
            continue;
        }

        $is_fk_too = in_array($col_name, $fk_columns, true);
        if ($is_fk_too) {
            continue;
        }

        echo '<label for="' . htmlspecialchars($col_name) . '">' . htmlspecialchars($col_name) . ':</label>';
        echo '<input type="text" name="' . htmlspecialchars($col_name) . '" value="' . htmlspecialchars((string)($id ?? '')) . '" readonly required>';
        echo '<br>';
    }

    foreach ($fk_result as $fk_row) {
        $col_name = (string)($fk_row['COLUMN_NAME'] ?? '');
        if ($col_name === '' || in_array($col_name, $pk_columns, true)) {
            continue;
        }

        $ref_table  = $fk_row['REFERENCED_TABLE_NAME'];
        $ref_column = $fk_row['REFERENCED_COLUMN_NAME'];
        $current_value = isset($row[$col_name]) ? (string)$row[$col_name] : '';

        $stmt = $conn->prepare("SELECT * FROM `$ref_table`");
        $stmt->execute();
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $opciones = [];
        foreach ($options as $option) {
            $value = trim((string)($option[$ref_column] ?? ''));
            if ($value === '') {
                continue;
            }

            $label_field = null;
            foreach (array_keys($option) as $candidate) {
                if ($candidate !== $ref_column) {
                    $label_field = $candidate;
                    break;
                }
            }
            $label = $label_field !== null ? trim((string)($option[$label_field] ?? $value)) : $value;
            $opciones[$value] = $label !== '' ? $label : $value;
        }

        if ($current_value !== '' && !isset($opciones[$current_value])) {
            $opciones = [$current_value => 'Actual: ' . $current_value] + $opciones;
        }

        echo '<label for="' . htmlspecialchars($col_name) . '">' . htmlspecialchars($col_name) . ':</label>';
        echo '<select name="' . htmlspecialchars($col_name) . '" id="' . htmlspecialchars($col_name) . '">';
        echo '<option value="">-- Selecciona un valor --</option>';

        foreach ($opciones as $value => $label) {
            $selected = (trim((string)$value) === trim((string)$current_value)) ? ' selected' : '';
            $text = $label . ': ' . $value;
            echo '<option value="' . htmlspecialchars((string)$value) . '"' . $selected . '>';
            echo htmlspecialchars((string)$text);
            echo '</option>';
        }

        echo '</select><br>';
    }

    foreach ($non_key_result as $non_key_row) {
        $col_name = $non_key_row['COLUMN_NAME'];
        $current_value = $row[$col_name] ?? '';
        echo '<label for="' . htmlspecialchars($col_name) . '">' . htmlspecialchars($col_name) . ':</label>';
        echo '<input type="text" name="' . htmlspecialchars($col_name) . '" value="' . htmlspecialchars($current_value) . '"><br>';
    }
    foreach($fotos_result as $fotos_row) {
        $col_name = $fotos_row['COLUMN_NAME'];
        $current_value = $row[$col_name] ??'';
        $foto_archivo = !empty($current_value)
            ? BASE_URL . '/Foto/' . rawurlencode($current_value)
            : '';
        echo '<label for="' . htmlspecialchars($col_name) . '">' . htmlspecialchars($col_name) . ':</label>';
        echo '<div class="avatar-container" onclick="document.getElementById(`foto-upload-' . htmlspecialchars($col_name) . '`).click();">';
        echo '<img style="width: auto; height: 150px;" id="avatar-preview-' . htmlspecialchars($col_name) . '" src="'. htmlspecialchars($foto_archivo) .'" alt="Foto de Perfil">';
        echo '</div>';
        echo '<input type="file" id="foto-upload-' . htmlspecialchars($col_name) . '" name="'. htmlspecialchars($col_name) .'" data-preview="avatar-preview-' . htmlspecialchars($col_name) . '" accept="image/*" onchange="previewImage(this);">';
        
    }
    echo '<input type="submit" value="Actualizar">';
    ?>
    </form>
    <?php
    if (empty($row)) {
        echo "No se encontró el registro con id = " . htmlspecialchars($id);
        header("Location: " . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($table));
        echo '<script>window.history.back();</script>';
        exit;
    }
    ?>
</body>
</html>

<?php

