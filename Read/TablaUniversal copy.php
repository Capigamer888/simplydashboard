<?php
include __DIR__ . '/../inicializaciones.php';

$table = isset($_GET['tbl']) ? $_GET['tbl'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabla: <?= htmlspecialchars($table) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h1>Tabla: <span><?= htmlspecialchars($table) ?></span></h1>
            <a href="index.php" class="back-link">&larr; Volver</a>
            
        </div>

        <form method="GET" class="search-bar">
            <input type="hidden" name="tbl" value="<?= htmlspecialchars($table) ?>">
            <input
                type="text"
                name="busqueda"
                placeholder="Buscar por nombre..."
                value="<?= htmlspecialchars($busqueda) ?>"
                class="search-input"
            >
            <button type="submit" class="search-btn">Buscar</button>
            <button type="button" class="search-btn" onclick="window.location.href='<?= BASE_URL ?>/Read/TablaUniversal.php?tbl=<?php echo urlencode($table); ?>';">Reiniciar</button>
            <button type="button" class="search-btn" onclick="window.location.href='<?= BASE_URL ?>/Insert/InsertUniversal.php?tbl=<?php echo urlencode($table); ?>';">Insertar</button>
        </form>
        <?php
            $tablas = $conn->prepare(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()"
            );
            $tablas->execute();
            $tablasResult = $tablas->fetchAll(PDO::FETCH_ASSOC);

            echo "<details open>";
            echo "<summary>Otras tablas</summary>";
            foreach ($tablasResult as $tabla) {
                $tableName = $tabla['TABLE_NAME'];
                echo "<div><a href='" . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($tableName) . "'>" . htmlspecialchars($tableName) . "</a></div>";
            }
            echo "</details>";
        ?>

        <?php
            if (!empty($table)) {
                $key = $conn->prepare("SELECT TABLE_NAME, COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?");
            $key->execute([$table]);
            $key_result = $key->fetchAll();
                $columns = array_column($key_result, 'COLUMN_NAME');

                // Build a unique named placeholder per column
                $whereParts = [];
                $params = [];
                foreach ($columns as $i => $col) {
                    $placeholder = ":busqueda$i";
                    $whereParts[] = "`$col` LIKE $placeholder";
                    $params[$placeholder] = '%' . $busqueda . '%';
                }
                $whereClause = implode(' OR ', $whereParts);

                if (!empty($busqueda)) {
                    $query = $conn->prepare("SELECT * FROM `$table` WHERE $whereClause");
                    $query->execute($params);
                    $results = $query->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $query = $conn->prepare("SELECT * FROM `$table`");
                    $query->execute();
                    $results = $query->fetchAll(PDO::FETCH_ASSOC);
            
                }
    
            }else{
                $results = [];
            }
            

            if (!empty($results)) {
                echo "<div class='table-wrapper'>";
                echo "<table>";
                echo "<tr>";
                foreach (array_keys($results[0]) as $column) {
                    echo "<th>" . htmlspecialchars($column) . "</th>";
                }
                echo "<th>Editar</th>";
                echo "<th>Eliminar</th>";
                echo "</tr>";
                foreach ($results as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value) . "</td>";
                    }
                    echo "<td><a class='action-link edit' href='" . BASE_URL . "/Edit/EditarUniversal.php?id=".reset($row)."&tbl=".$table."'>Editar</a></td>";
                    echo "<td><a class='action-link delete' href='" . BASE_URL . "/Delete/DeleteUniversal.php?id=".reset($row)."&tbl=".$table."&col=".htmlspecialchars(array_key_first(reset($results)))."'>Eliminar</a></td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "</div>";
            } else {
                echo "<p class='no-results'>No se encontraron resultados para la búsqueda en la tabla '" . htmlspecialchars($table) . "'.</p>";
            }
        ?>
    </div>
</body>
</html>