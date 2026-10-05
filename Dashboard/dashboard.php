<?php
include __DIR__ . '/../inicializaciones.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablas de la base de datos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Tablas en <span><?= htmlspecialchars($conn->query("SELECT DATABASE()")->fetchColumn()) //$dbname?></span></h1>
        <a class='table-link-backlink' style='display:inline-block;text-align:center;text-decoration:none;color:#ffffff;background:#2563eb;border:1px solid #2563eb;padding:12px 16px;border-radius:10px;font-weight:600;margin-bottom:18px;' href='<?= BASE_URL ?>/db_Slect.php'>Cambiar de base de datos</a>
        <div class="table-list">
            <?php
            $select = $conn->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");
            $select->execute();
            $result = $select->fetchAll(PDO::FETCH_COLUMN);
            foreach ($result as $tableName) {
                echo "<a class='table-link' href='" . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($tableName) . "'>$tableName</a>";
            }
            ?>
        </div>
    </div>
</body>
</html>