<?php
/**
 * SimplyDashboard - Visualización Universal de Tablas (Read)
 * 
 * Este archivo inspecciona metadatos de MySQL mediante `INFORMATION_SCHEMA.COLUMNS`
 * e `INFORMATION_SCHEMA.KEY_COLUMN_USAGE` para:
 * 1. Validar que la tabla solicitada exista en la base de datos (prevención de inyección SQL).
 * 2. Obtener todas las columnas de la tabla para construir una consulta dinámica.
 * 3. Identificar la clave primaria (PRIMARY KEY) para las operaciones de edición y eliminación.
 * 4. Construir un buscador universal parametrizado que filtra por cualquier campo de texto.
 * 5. Renderizar imágenes automáticamente si los valores corresponden a formatos gráficos válidos.
 */

require_once __DIR__ . '/../inicializaciones.php';

// Sanitización de parámetros GET recibidos
$table    = sanitize_input($_GET['tbl'] ?? '');
$busqueda = sanitize_input($_GET['busqueda'] ?? '');
$mostrar  = isset($_GET['mostrar']) ? max(1, min(100, (int)$_GET['mostrar'])) : 10;

// Valida que el nombre de la tabla cumpla con el formato de identificador seguro
if ($table === '' || !is_valid_identifier($table)) {
    header("Location: " . BASE_URL . "/Dashboard/dashboard.php");
    exit;
}

// 1. Obtener la lista de tablas autorizadas usando INFORMATION_SCHEMA.TABLES (Lista blanca)
$stmtTables = $conn->prepare("
    SELECT TABLE_NAME 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
");
$stmtTables->execute();
$tablasValidas = $stmtTables->fetchAll(PDO::FETCH_COLUMN) ?: [];

if (!in_array($table, $tablasValidas, true)) {
    header("Location: " . BASE_URL . "/Dashboard/dashboard.php?error=" . urlencode("La tabla solicitada no existe."));
    exit;
}

// 2. Obtener las columnas de la tabla activa consultando INFORMATION_SCHEMA.COLUMNS
$stmtColumns = $conn->prepare("
    SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ?
    ORDER BY ORDINAL_POSITION ASC
");
$stmtColumns->execute([$table]);
$columnsInfo = $stmtColumns->fetchAll(PDO::FETCH_ASSOC) ?: [];
$columns = array_column($columnsInfo, 'COLUMN_NAME');

// 3. Detectar la Clave Primaria (PK) mediante INFORMATION_SCHEMA.KEY_COLUMN_USAGE
$stmtPk = $conn->prepare("
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = ? 
      AND CONSTRAINT_NAME = 'PRIMARY'
    LIMIT 1
");
$stmtPk->execute([$table]);
$primaryKeyCol = (string)($stmtPk->fetchColumn() ?: (!empty($columns) ? $columns[0] : 'id'));

$results = [];

// 4. Ejecución de la consulta con soporte para búsqueda universal parametrizada
if (!empty($columns)) {
    if ($busqueda !== '') {
        // Construcción dinámica de condiciones OR con parámetros nombrados seguros (:b0, :b1, etc.)
        $whereParts = [];
        $params = [];
        foreach ($columns as $idx => $colName) {
            $paramPlaceholder = ":b{$idx}";
            $whereParts[] = "`{$colName}` LIKE {$paramPlaceholder}";
            $params[$paramPlaceholder] = '%' . $busqueda . '%';
        }
        $whereSql = implode(' OR ', $whereParts);
        $sql = "SELECT * FROM `{$table}` WHERE {$whereSql} LIMIT {$mostrar}";
        $stmtQuery = $conn->prepare($sql);
        $stmtQuery->execute($params);
        $results = $stmtQuery->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $sql = "SELECT * FROM `{$table}` LIMIT {$mostrar}";
        $stmtQuery = $conn->prepare($sql);
        $stmtQuery->execute();
        $results = $stmtQuery->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

// Ruta física donde se almacenan las fotos subidas
$ruta_foto = BASE_URL . "/Foto/";

$errorMessage   = isset($_GET['error']) ? sanitize_input($_GET['error']) : '';
$successMessage = isset($_GET['success']) ? sanitize_input($_GET['success']) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabla: <?= e($table) ?> | SimplyDashboard</title>
    <link rel="stylesheet" href="style.css?v=2">
</head>
<body>
    <div class="container">
        <!-- Barra de cabecera con navegación -->
        <div class="header-bar">
            <h1>Tabla: <span><?= e($table) ?></span></h1>
            <a href="<?= BASE_URL ?>/Dashboard/dashboard.php" class="back-link">&larr; Volver al Dashboard</a>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert-banner error">
                <strong>⚠ Atención:</strong> <?= e($errorMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="alert-banner success">
                <strong>✓ Éxito:</strong> <?= e($successMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Formulario de búsqueda universal y límite de filas -->
        <form method="GET" class="search-bar">
            <input type="hidden" name="tbl" value="<?= e($table) ?>">
            <input
                type="text"
                name="busqueda"
                placeholder="Buscar en todos los campos..."
                value="<?= e($busqueda) ?>"
                class="search-input"
            >
            <input
                type="number"
                name="mostrar"
                min="1"
                max="100"
                value="<?= e((string)$mostrar) ?>"
                class="search-input"
                style="width: 120px;"
                title="Límite de registros a mostrar"
            >
            <button type="submit" class="search-btn">Buscar</button>
            <button type="button" class="search-btn" onclick="window.location.href='<?= BASE_URL ?>/Read/TablaUniversal.php?tbl=<?= urlencode($table) ?>';">Reiniciar</button>
            <button type="button" class="search-btn" style="background:#059669;" onclick="window.location.href='<?= BASE_URL ?>/Insert/InsertUniversal.php?tbl=<?= urlencode($table) ?>';">+ Insertar</button>
        </form>

        <!-- Menú lateral colapsable con las demás tablas de la base de datos -->
        <details>
            <summary>Otras tablas (<?= count($tablasValidas) ?>)</summary>
            <?php foreach ($tablasValidas as $tName): ?>
                <div>
                    <a href="<?= BASE_URL ?>/Read/TablaUniversal.php?tbl=<?= urlencode($tName) ?>" style="<?= ($tName === $table) ? 'font-weight: bold; color: #ffffff;' : '' ?>">
                        <?= e($tName) ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </details>

        <!-- Tabla dinámica de registros -->
        <?php if (!empty($results)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 45px; text-align: center;">#</th>
                            <?php foreach (array_keys($results[0]) as $colName): ?>
                                <th>
                                    <?= e($colName) ?>
                                    <?php if ($colName === $primaryKeyCol): ?>
                                        <span title="Clave Primaria">🔑</span>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                            <th style="width: 70px; text-align: center;">Editar</th>
                            <th style="width: 70px; text-align: center;">Eliminar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $index => $row): ?>
                            <tr>
                                <td class="row-number"><?= $index + 1 ?></td>
                                <?php foreach ($row as $colName => $value): ?>
                                    <td>
                                        <?php if ($value === null): ?>
                                            <span style="color: #94a3b8; font-style: italic;">NULL</span>
                                        <?php elseif (is_string($value) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $value)): ?>
                                            <!-- Previsualización de imagen si el campo termina en extensión gráfica -->
                                            <img style="width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1;" 
                                                 src="<?= e($ruta_foto . $value) ?>" 
                                                 alt="Foto"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                            <span style="display:none;"><?= e((string)$value) ?></span>
                                        <?php else: ?>
                                            <?= e((string)$value) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>

                                <?php
                                $rowId = (string)($row[$primaryKeyCol] ?? reset($row) ?? '');
                                $editUrl = BASE_URL . '/Edit/EditarUniversal.php?id=' . urlencode($rowId) . '&tbl=' . urlencode($table);
                                ?>
                                <!-- Enlace de edición seguro -->
                                <td style="text-align: center;">
                                    <a class="action-link edit" href="<?= e($editUrl) ?>">Editar</a>
                                </td>

                                <!-- Botón de eliminación seguro con confirmación JavaScript -->
                                <td style="text-align: center;">
                                    <button class="action-link delete" type="button" onclick="confirmarEliminar(<?= htmlspecialchars(json_encode($rowId), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($primaryKeyCol), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($table), ENT_QUOTES, 'UTF-8') ?>)">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-results">
                No se encontraron registros en la tabla <strong><?= e($table) ?></strong><?= $busqueda !== '' ? ' para el criterio de búsqueda ingresado.' : '.' ?>
            </p>
        <?php endif; ?>

        <script>
            /**
             * Muestra un diálogo de confirmación antes de eliminar una fila permanentemente.
             */
            function confirmarEliminar(id, col, table) {
                if (confirm('¿Desea eliminar la fila con ' + col + ' = "' + id + '"? Esta acción no se puede deshacer.')) {
                    window.location.href = '<?= BASE_URL ?>/Delete/DeleteUniversal.php?tbl=' + encodeURIComponent(table) + '&col=' + encodeURIComponent(col) + '&id=' + encodeURIComponent(id);
                }
            }
        </script>
    </div>
</body>
</html>