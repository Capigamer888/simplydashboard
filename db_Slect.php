<?php
/**
 * CarlosHub - Selector de Base de Datos MySQL
 * 
 * Este archivo permite al usuario listar y seleccionar una de las bases de datos
 * disponibles en el servidor MySQL local. Filtra automáticamente las bases de datos
 * internas del sistema para mostrar únicamente las creadas por el usuario.
 */

require_once __DIR__ . '/inicializaciones.php';

$databases = [];

try {
    // Consulta al servidor MySQL para obtener las bases de datos excluyendo esquemas del sistema
    $query = "SHOW DATABASES WHERE `Database` NOT IN (
        'information_schema', 
        'mysql', 
        'performance_schema', 
        'sys', 
        'phpmyadmin', 
        'test'
    )";
    $stmt = $conn->query($query);
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (PDOException $e) {
    $databases = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Base de Datos | CarlosHub</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
</head>
<body>
    <!-- Pantalla de bienvenida interactiva (desaparece al hacer clic) -->
    <div class="welcome-overlay" id="welcomeOverlay" onclick="this.classList.add('hidden')">
        <div class="welcome-content">
            <h1>CarlosHub</h1>
            <h2>Gestor Dinámico de Bases de Datos MySQL</h2>
            <p style="margin-top: 15px; opacity: 0.8; font-size: 0.95rem;">Haz clic para comenzar</p>
        </div>
    </div>

    <div class="page-shell">
        <div class="card">
            <h1>Selecciona una base de datos</h1>
            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 20px;">
                Bases de datos detectadas en tu servidor MySQL local (XAMPP).
            </p>

            <?php if (!empty($databases)): ?>
                <!-- Rejilla con las bases de datos disponibles -->
                <div class="database-grid">
                    <?php foreach ($databases as $database): ?>
                        <a href="<?= BASE_URL ?>/Dashboard/dashboard.php?dbname=<?= urlencode($database) ?>">
                            🗄️ <?= e($database) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Estado vacío en caso de que no haya bases de datos creadas -->
                <p class="empty-state">
                    No se encontraron bases de datos de usuario en MySQL. Puedes crear una desde phpMyAdmin.
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>