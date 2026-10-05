<?php
/**
 * SimplyDashboard - Panel Principal (Dashboard)
 * 
 * Este archivo consulta el catálogo del sistema `INFORMATION_SCHEMA.TABLES` de MySQL
 * para identificar y listar dinámicamente todas las tablas base que existen dentro
 * de la base de datos seleccionada actualmente.
 */

require_once __DIR__ . '/../inicializaciones.php';

// Si no hay una base de datos seleccionada, redirige al selector
if (empty($dbname)) {
    header("Location: " . BASE_URL . "/db_Slect.php");
    exit;
}

$activeDb = '';
$tables = [];

try {
    // Obtiene el nombre confirmado de la base de datos activa desde MySQL
    $stmtDb = $conn->query("SELECT DATABASE()");
    $activeDb = (string)($stmtDb->fetchColumn() ?: $dbname);

    // Consulta al diccionario INFORMATION_SCHEMA.TABLES para listar tablas base (excluyendo vistas)
    $stmtTables = $conn->prepare("
        SELECT TABLE_NAME 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME ASC
    ");
    $stmtTables->execute();
    $tables = $stmtTables->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (PDOException $e) {
    $tables = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablas en <?= e($activeDb) ?> | SimplyDashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Tablas en <span><?= e($activeDb) ?></span></h1>

        <!-- Botón para regresar a seleccionar otra base de datos -->
        <a class="table-link-backlink" style="display:inline-block;text-align:center;text-decoration:none;color:#ffffff;background:#2563eb;border:1px solid #2563eb;padding:12px 18px;border-radius:10px;font-weight:600;margin-bottom:20px;" href="<?= BASE_URL ?>/db_Slect.php">
            🔄 Cambiar de base de datos
        </a>

        <!-- Lista dinámica de tablas encontradas mediante INFORMATION_SCHEMA -->
        <div class="table-list">
            <?php if (!empty($tables)): ?>
                <?php foreach ($tables as $tableName): ?>
                    <a class="table-link" href="<?= BASE_URL ?>/Read/TablaUniversal.php?tbl=<?= urlencode($tableName) ?>">
                        📋 <?= e($tableName) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #64748b; padding: 30px; background: #ffffff; border-radius: 12px;">
                    No hay tablas creadas en la base de datos <strong><?= e($activeDb) ?></strong>.
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>