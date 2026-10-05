<?php
/**
 * CarlosHub - Formulario de Edición Universal de Registros (Edit)
 * 
 * Este archivo utiliza los metadatos de MySQL (`INFORMATION_SCHEMA.KEY_COLUMN_USAGE`
 * e `INFORMATION_SCHEMA.COLUMNS`) para:
 * 1. Validar la tabla y obtener el registro a modificar mediante su clave primaria.
 * 2. Identificar las claves foráneas (FK) y consultar sus tablas padre para construir
 *    automáticamente menús desplegables (<select>) con opciones relacionadas.
 * 3. Identificar campos destinados a fotos (por comentario o nombre de columna)
 *    para permitir reemplazo de imágenes con previsualización en vivo.
 * 4. Generar campos de edición para todas las demás columnas ordinarias.
 */

require_once __DIR__ . '/../inicializaciones.php';

// Sanitización de parámetros recibidos por la URL
$id    = sanitize_input($_GET['id'] ?? '');
$table = sanitize_input($_GET['tbl'] ?? '');

if ($id === '' || $table === '' || !is_valid_identifier($table)) {
    header("Location: " . BASE_URL . "/Dashboard/dashboard.php");
    exit;
}

// 1. Validar que la tabla exista mediante INFORMATION_SCHEMA.TABLES
$stmtTableCheck = $conn->prepare("
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ?
");
$stmtTableCheck->execute([$table]);
if ((int)$stmtTableCheck->fetchColumn() === 0) {
    die("Error: La tabla especificada no existe.");
}

// 2. Obtener la Clave Primaria (PK) mediante INFORMATION_SCHEMA.KEY_COLUMN_USAGE
$stmtPk = $conn->prepare("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ? 
      AND CONSTRAINT_NAME = 'PRIMARY'
");
$stmtPk->execute([$table]);
$pkColumns = $stmtPk->fetchAll(PDO::FETCH_COLUMN) ?: [];
$primaryKeyCol = !empty($pkColumns) ? $pkColumns[0] : 'id';

// 3. Consultar el registro actual a editar mediante una sentencia preparada
$stmtRow = $conn->prepare("SELECT * FROM `{$table}` WHERE `{$primaryKeyCol}` = ?");
$stmtRow->execute([$id]);
$row = $stmtRow->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("Error: No se encontró el registro con el ID especificado.");
}

// 4. Obtener las Claves Foráneas (FK) mediante INFORMATION_SCHEMA.KEY_COLUMN_USAGE
$stmtFk = $conn->prepare("
    SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND REFERENCED_TABLE_NAME IS NOT NULL
");
$stmtFk->execute([$table]);
$fkResult = $stmtFk->fetchAll(PDO::FETCH_ASSOC) ?: [];
$fkColumns = array_column($fkResult, 'COLUMN_NAME');

// 5. Identificar columnas de fotos y columnas normales mediante INFORMATION_SCHEMA.COLUMNS
$stmtCols = $conn->prepare("
    SELECT COLUMN_NAME, COLUMN_KEY, DATA_TYPE, COLUMN_COMMENT 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ?
    ORDER BY ORDINAL_POSITION ASC
");
$stmtCols->execute([$table]);
$allColsInfo = $stmtCols->fetchAll(PDO::FETCH_ASSOC) ?: [];

$fotoColumns = [];
$nonKeyColumns = [];

foreach ($allColsInfo as $colInfo) {
    $cName    = $colInfo['COLUMN_NAME'];
    $comment  = strtolower((string)($colInfo['COLUMN_COMMENT'] ?? ''));
    $cNameLow = strtolower($cName);

    // Omitir si ya es parte de la clave primaria o clave foránea
    if (in_array($cName, $pkColumns, true) || in_array($cName, $fkColumns, true)) {
        continue;
    }

    // Clasificar como foto si el comentario de columna es 'foto' o su nombre coincide
    if ($comment === 'foto' || in_array($cNameLow, ['foto', 'imagen', 'image', 'avatar'], true)) {
        $fotoColumns[] = $cName;
    } else {
        $nonKeyColumns[] = $cName;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar en <?= e($table) ?> | CarlosHub</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <script>
        /**
         * Permite visualizar la imagen seleccionada localmente antes de guardar los cambios.
         */
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var preview = document.getElementById(input.dataset.preview);
                    if (preview) {
                        preview.src = e.target.result;
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

    <a class="action-link" href="<?= BASE_URL ?>/Read/TablaUniversal.php?tbl=<?= urlencode($table) ?>">&larr; Regresar a <?= e($table) ?></a>
    <h1>Editar registro en <?= e($table) ?></h1>

    <form method="POST" enctype="multipart/form-data" action="<?= BASE_URL ?>/Edit/UpdateUniversal.php?id=<?= urlencode($id) ?>&tbl=<?= urlencode($table) ?>&col=<?= urlencode($primaryKeyCol) ?>">
        <?php
        // Renderizado del campo Clave Primaria (solo lectura)
        foreach ($pkColumns as $pkName) {
            echo '<label for="' . e($pkName) . '">' . e($pkName) . ' (Clave Primaria):</label>';
            echo '<input type="text" id="' . e($pkName) . '" name="' . e($pkName) . '" value="' . e((string)($row[$pkName] ?? $id)) . '" readonly required>';
            echo '<br>';
        }

        // Renderizado de campos con Claves Foráneas: Consulta opciones en la tabla padre referenciada
        foreach ($fkResult as $fkRow) {
            $colName   = $fkRow['COLUMN_NAME'];
            $refTable  = $fkRow['REFERENCED_TABLE_NAME'];
            $refColumn = $fkRow['REFERENCED_COLUMN_NAME'];
            $currentVal = (string)($row[$colName] ?? '');

            // Consulta las opciones disponibles en la tabla relacionada
            $options = [];
            try {
                $stmtRef = $conn->query("SELECT * FROM `{$refTable}` LIMIT 100");
                $options = $stmtRef->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                $options = [];
            }

            echo '<label for="' . e($colName) . '">' . e($colName) . ' (Relación con ' . e($refTable) . '):</label>';
            echo '<select name="' . e($colName) . '" id="' . e($colName) . '">';
            echo '<option value="">-- Selecciona un valor --</option>';

            foreach ($options as $opt) {
                $val = trim((string)($opt[$refColumn] ?? ''));
                if ($val === '') continue;

                // Encuentra un campo descriptivo para la etiqueta del dropdown
                $label = $val;
                foreach ($opt as $k => $v) {
                    if ($k !== $refColumn && $v !== null && trim((string)$v) !== '') {
                        $label = trim((string)$v);
                        break;
                    }
                }

                $selected = ($val === $currentVal) ? ' selected' : '';
                echo '<option value="' . e($val) . '"' . $selected . '>' . e($label . ' (' . $val . ')') . '</option>';
            }

            echo '</select><br>';
        }

        // Renderizado de columnas ordinarias
        foreach ($nonKeyColumns as $colName) {
            $currentVal = (string)($row[$colName] ?? '');
            echo '<label for="' . e($colName) . '">' . e($colName) . ':</label>';
            echo '<input type="text" id="' . e($colName) . '" name="' . e($colName) . '" value="' . e($currentVal) . '"><br>';
        }

        // Renderizado de campos de fotos
        foreach ($fotoColumns as $colName) {
            $currentVal = (string)($row[$colName] ?? '');
            $fotoArchivo = !empty($currentVal) ? BASE_URL . '/Foto/' . rawurlencode($currentVal) : '';

            echo '<label for="foto-upload-' . e($colName) . '">' . e($colName) . ':</label>';
            echo '<input type="hidden" name="current_foto_' . e($colName) . '" value="' . e($currentVal) . '">';
            echo '<div class="avatar-container" onclick="document.getElementById(\'foto-upload-' . e($colName) . '\').click();">';
            echo '<img style="width: auto; height: 140px; border-radius: 8px; border: 1px solid #cbd5e1; object-fit: cover;" id="avatar-preview-' . e($colName) . '" src="' . e($fotoArchivo) . '" alt="Previsualización">';
            echo '</div>';
            echo '<input type="file" id="foto-upload-' . e($colName) . '" name="' . e($colName) . '" data-preview="avatar-preview-' . e($colName) . '" accept="image/*" onchange="previewImage(this);">';
        }
        ?>
        <input type="submit" value="Guardar Cambios" style="margin-top: 20px;">
    </form>
</body>
</html>
