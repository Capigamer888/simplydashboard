<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname ='';

$dsn = "mysql:host={$servername};dbname={$dbname};charset=utf8mb4";

try {
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar base de datos</title>
    <link rel="stylesheet" href="/CarlosHub/style.css">
</head>
<body>
    <div class="welcome-overlay" id="welcomeOverlay" onclick="this.classList.add('hidden')">
        <div class="welcome-content">
            <h1>Dashboard</h1>
            <h2>Carlos Hub</h2>
        </div>
    </div>

    <div class="page-shell">
        <div class="card">
            <h1>Selecciona una base de datos</h1>
            <?php
            $select = $conn->query("SHOW DATABASES WHERE `Database` NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin', 'test')");
            $databases = $select->fetchAll(PDO::FETCH_COLUMN);
            ?>

            <?php if (!empty($databases)): ?>
                <div class="database-grid">
                    <?php foreach ($databases as $database): ?>
                        <a href="/CarlosHub/Dashboard/dashboard.php?dbname=<?= urlencode($database) ?>">
                            <?= htmlspecialchars($database) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">No se encontraron bases de datos disponibles.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>