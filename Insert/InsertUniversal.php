<?php
/**
 * SimplyDashboard - Formulario de Inserción Universal de Registros (Insert)
 * 
 * Este archivo consulta el diccionario de datos de MySQL (`INFORMATION_SCHEMA`) para:
 * 1. Validar la tabla en la que se insertará el nuevo registro.
 * 2. Identificar la Clave Primaria (PK) y calcular de forma predictiva el siguiente ID secuencial.
 * 3. Identificar las Claves Foráneas (FK) y poblar menús desplegables con las filas existentes en las tablas padre.
 * 4. Clasificar columnas para la subida de imágenes y columnas de captura estándar.
 */

require_once __DIR__ . '/../inicializaciones.php';

// Sanitización del nombre de la tabla recibido por GET
$table = sanitize_input($_GET['tbl'] ?? '');

if ($table === '' || !is_valid_identifier($table)) {
    header("Location: " . BASE_URL . "/Dashboard/dashboard.php");
    exit;
}

// 1. Obtener la Clave Primaria (PK) consultando INFORMATION_SCHEMA.KEY_COLUMN_USAGE
$stmtPk = $conn->prepare("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ? 
      AND CONSTRAINT_NAME = 'PRIMARY'
");
$stmtPk->execute([$table]);
$pkColumns = $stmtPk->fetchAll(PDO::FETCH_COLUMN) ?: [];
$primaryKeyCol = !empty($pkColumns) ? $pkColumns[0] : '';

// 2. Obtener las Claves Foráneas (FK) consultando INFORMATION_SCHEMA.KEY_COLUMN_USAGE
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

// 3. Obtener todas las columnas y clasificar fotos vs columnas normales mediante INFORMATION_SCHEMA.COLUMNS
$stmtCols = $conn->prepare("
    SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, COLUMN_COMMENT 
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

    if (in_array($cName, $pkColumns, true) || in_array($cName, $fkColumns, true)) {
        continue;
    }

    if ($comment === 'foto' || in_array($cNameLow, ['foto', 'imagen', 'image', 'avatar'], true)) {
        $fotoColumns[] = $cName;
    } else {
        $nonKeyColumns[] = $cName;
    }
}

/**
 * Calcula de forma automática el siguiente ID disponible para la tabla si es numérico o alfanumérico secuencial.
 */
function calcularSiguienteId(PDO $conn, string $table, string $pkCol): string {
    if ($pkCol === '' || !is_valid_identifier($table) || !is_valid_identifier($pkCol)) {
        return '1';
    }
    try {
        $stmt = $conn->query("SELECT `{$pkCol}` FROM `{$table}` ORDER BY `{$pkCol}` DESC LIMIT 1");
        $lastVal = (string)($stmt->fetchColumn() ?: '');

        if ($lastVal === '') {
            return '1';
        }

        if (preg_match('/^([a-zA-Z_-]+)(\d+)$/', $lastVal, $matches)) {
            $prefix = $matches[1];
            $num = (int)$matches[2] + 1;
            $length = strlen($matches[2]);
            return $prefix . str_pad((string)$num, $length, '0', STR_PAD_LEFT);
        }

        if (is_numeric($lastVal)) {
            return (string)((int)$lastVal + 1);
        }

        return '';
    } catch (PDOException $e) {
        return '';
    }
}

$siguienteId = ($primaryKeyCol !== '') ? calcularSiguienteId($conn, $table, $primaryKeyCol) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insertar en <?= e($table) ?> | SimplyDashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <script>
        /**
         * Permite previsualizar la imagen cargada antes de enviar el formulario.
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
    <h1>Insertar en <?= e($table) ?></h1>

    <form method="POST" enctype="multipart/form-data" action="<?= BASE_URL ?>/Insert/InsertUniversal2.php?tbl=<?= urlencode($table) ?>&col=<?= urlencode($primaryKeyCol) ?>">
        <?php
        // Renderizado del campo Clave Primaria (con sugerencia automática del siguiente ID)
        foreach ($pkColumns as $pkName) {
            if (in_array($pkName, $fkColumns, true)) {
                continue;
            }
            echo '<label for="' . e($pkName) . '">' . e($pkName) . ' (Clave Primaria):</label>';
            echo '<input type="text" id="' . e($pkName) . '" name="' . e($pkName) . '" value="' . e($siguienteId) . '" placeholder="ID sugerido o escribe uno">';
            echo '<br>';
        }

        // Renderizado de campos con Claves Foráneas: Menús desplegables generados dinámicamente
        foreach ($fkResult as $fkRow) {
            $colName   = $fkRow['COLUMN_NAME'];
            $refTable  = $fkRow['REFERENCED_TABLE_NAME'];
            $refColumn = $fkRow['REFERENCED_COLUMN_NAME'];

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

                $label = $val;
                foreach ($opt as $k => $v) {
                    if ($k !== $refColumn && $v !== null && trim((string)$v) !== '') {
                        $label = trim((string)$v);
                        break;
                    }
                }
                echo '<option value="' . e($val) . '">' . e($label . ' (' . $val . ')') . '</option>';
            }

            echo '</select><br>';
        }

        // Renderizado de columnas normales de la tabla
        foreach ($nonKeyColumns as $colName) {
            echo '<label for="' . e($colName) . '">' . e($colName) . ':</label>';
            echo '<input type="text" id="' . e($colName) . '" name="' . e($colName) . '"><br>';
        }

        // Renderizado de campos para subir imágenes
        foreach ($fotoColumns as $colName) {
            echo '<label for="foto-upload-' . e($colName) . '">' . e($colName) . ':</label>';
            echo '<div class="avatar-container" onclick="document.getElementById(\'foto-upload-' . e($colName) . '\').click();">';
            echo '<img style="width: auto; height: 140px; border-radius: 8px; border: 1px solid #cbd5e1; object-fit: cover;" id="avatar-preview-' . e($colName) . '" alt="Haz clic para seleccionar imagen">';
            echo '</div>';
            echo '<input type="file" id="foto-upload-' . e($colName) . '" name="' . e($colName) . '" data-preview="avatar-preview-' . e($colName) . '" accept="image/*" onchange="previewImage(this);">';
        }
        ?>
        <input type="submit" value="Insertar Registro" style="margin-top: 20px;">
    </form>
</body>
</html>