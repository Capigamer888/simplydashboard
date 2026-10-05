<?php
include __DIR__ . '/../inicializaciones.php';

// Primary Key de la tabla
//    SELECT TABLE_NAME, COLUMN_NAME
//    FROM INFORMATION_SCHEMA.COLUMNS
//    WHERE TABLE_SCHEMA = DATABASE()
//      AND TABLE_NAME = ?
//      AND COLUMN_KEY = 'PRI'
$table = $_GET['tbl'];
$pk = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = database()
      AND TABLE_NAME = ?
      AND CONSTRAINT_NAME = 'PRIMARY'");
$pk->execute([ $table]);
$pk_result = $pk->fetchAll();
$pk_column = !empty($pk_result) ? (string)$pk_result[0]['COLUMN_NAME'] : '';


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
//echo json_encode(array_values($pk_result)[0]['COLUMN_NAME']);
//echo json_encode(array_values($fk_result)[1]['COLUMN_NAME']);
// Columnas que NO son PK ni FK, solo de esa tabla
$non_key = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_KEY IN ('', 'UNI')
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
    <h1><?php echo $table ?></h1>
    
    <form method="POST" enctype="multipart/form-data" action="<?= BASE_URL ?>/Insert/InsertUniversal2.php?tbl=<?= urlencode($table) ?>&col=<?= urlencode($pk_column) ?>">
    <?php

    function obtenerSiguienteId($idActual) {
        /* 1. Separar el texto de los números usando una expresión regular*/
        if (preg_match('/^([a-zA-Z]+-)(\d+)$/', $idActual, $coincidencias)) {
            $prefijo = $coincidencias[1]; // Captura id
            $numeroActual = (int)$coincidencias[2]; // Captura 001 y lo convierte a entero (1)
            $longitudDigitos = strlen($coincidencias[2]); // Mide cuántos dígitos tiene (3)

            // 2. Incrementar el número en 1
            $siguienteNumero = $numeroActual + 1;

            // 3. Rellenar con ceros a la izquierda y unir con el prefijo
            $numeroFormateado = str_pad($siguienteNumero, $longitudDigitos, 0, STR_PAD_LEFT);
            
            return $prefijo . $numeroFormateado;
        }
        
        return (int)$idActual + 1; // Retorna null si el formato inicial no era válido
    }
    //por si fk y pk a la vez, cuenta la cantidad de pks
    $fks = array_column($fk_result, 'COLUMN_NAME');
    $pks = array_column($pk_result, 'COLUMN_NAME');    

    foreach ($pk_result as $index => $pk_row) {
        // estas son las consecuencias de no haber usado COULUMN_KEY = MULTI
        //$tabla_refi=array_column($fk_result, 'REFERENCED_TABLE_NAME')[$index] ?? null;
        //$col_refi=array_column($fk_result, 'REFERENCED_COLUMN_NAME')[$index] ?? null;

        $pkName = $pks[$index] ?? null;
        $fkName = $fks[$index] ?? null;
        // Calcula el siguiente ID para la clave primaria.
        $a = $pk_row['COLUMN_NAME'];
        $ultima_pk = $conn->query("SELECT `$a` FROM `$table` ORDER BY `$a` DESC LIMIT 1");
        $t = $ultima_pk->fetchColumn();
        $t = obtenerSiguienteId($t);
        
        /*  if (!($pks[$contador_pkfk] === $fks[$contador_pkfk])){
            echo '<p>' . htmlspecialchars($pk_row['COLUMN_NAME']) .'</p>';
            echo '<input type="text" name="' . htmlspecialchars($pk_row['COLUMN_NAME']) . '" value="' . htmlspecialchars($t) . '" required>';        echo '<br>';
            
        }
        // Se tuvo que cambiar dado a que soltaba el warning aunque funcionaba completamente normal
        */
        //porque hice esto, quien invento las pks y fks a la vez, como es que siquiera tiene sentido 
        $colit = $pk_row['COLUMN_NAME'];
        if (in_array($colit, $fks)) {
            $hay_pkfk=TRUE;
        } else {
            $hay_pkfk=FALSE;
        }

        if ($hay_pkfk === FALSE ){
            echo '<p>' . htmlspecialchars($pk_row['COLUMN_NAME']) . '</p>';
            echo '<input type="text" name="' . htmlspecialchars($pk_row['COLUMN_NAME']) . '" value="' . htmlspecialchars($t) . '" required>';
            echo '<br>';
        }
    }


    foreach ($fk_result as $index => $fk_row) {  
        $pkName = $pks[$index] ?? null;
        $fkName = $fks[$index] ?? null;

        if ($pkName !== null && $fkName !== null && $pkName === $fkName) {
            continue;
        } 

        $ref_table  = $fk_row['REFERENCED_TABLE_NAME'];
        $ref_column = $fk_row['REFERENCED_COLUMN_NAME'];
        $col_name   = $fk_row['COLUMN_NAME'];

        $stmt = $conn->prepare("SELECT * FROM `$ref_table`");
        $stmt ->execute();
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo '<label for="' . htmlspecialchars($col_name) . '">' . htmlspecialchars($col_name) . ':</label>';
        echo '<select name="' . htmlspecialchars($col_name) . '">';
        echo '<option value="">-- Selecciona un valor --</option>';

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
            $display = $label !== '' ? $label . ': ' . $value : $value;

            echo '<option value="' . htmlspecialchars((string)$value) . '">' . htmlspecialchars((string)$display) . '</option>';
        }
        echo '</select><br>';
        
    }


    foreach ($non_key_result as $non_key_row) {
        $col_name = $non_key_row['COLUMN_NAME'];

        echo '<label for="' . htmlspecialchars($col_name) . '">' . htmlspecialchars($col_name) . ':</label>';
        echo '<input type="text" name="' . htmlspecialchars($col_name) . '"><br>';
    }
    foreach($fotos_result as $fotos_row) {
        $col_name = $fotos_row['COLUMN_NAME'];

        echo '<label for="' . htmlspecialchars($col_name) . '">' . htmlspecialchars($col_name) . ':</label>';
        echo '<div class="avatar-container" onclick="document.getElementById(`foto-upload-' . htmlspecialchars($col_name) . '`).click();">';
        echo '<img style="width: auto; height: 150px;" id="avatar-preview-' . htmlspecialchars($col_name) . '" alt="Foto de Perfil">';
        echo '</div>';
        echo '<input type="file" id="foto-upload-' . htmlspecialchars($col_name) . '" name="'. htmlspecialchars($col_name) . '" data-preview="avatar-preview-' . htmlspecialchars($col_name) . '" accept="image/*" onchange="previewImage(this);">';
        
    }
    echo '<input type="submit" value="Insertar">';
    ?>
    </form>
    <?php
        if (empty($table)) {
            header("Location: " . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($table));
            exit;
        }
    ?>
</body>
</html>