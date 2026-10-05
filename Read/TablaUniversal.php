<?php
include __DIR__ . '/../inicializaciones.php';

$table = isset($_GET['tbl']) ? $_GET['tbl'] : '';
$busqueda = isset($_GET['busqueda']) ? $_GET['busqueda'] : '';
$mostrar = isset($_GET['mostrar']) ? max(1, (int)$_GET['mostrar']) : 10;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabla: <?= htmlspecialchars($table) ?></title>
    <link rel="stylesheet" href="style.css?v=2">
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <h1>Tabla: <span><?= htmlspecialchars($table) ?></span></h1>
            <a href="<?= BASE_URL ?>/Dashboard/dashboard.php" class="back-link">&larr; Volver</a>
            
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
            <input
                type="number"
                name="mostrar"
                min="1"
                max="100"
                value="<?= htmlspecialchars((string)$mostrar) ?>"
                class="search-input"
                style="width: 120px;"
                placeholder="Mostrar"
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

            echo "<details>";
            echo "<summary>Otras tablas</summary>";
            for ($i = 0; $i < count($tablasResult); $i++) {
                $tableName = $tablasResult[$i]['TABLE_NAME'];
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
                foreach ($columns as $i => $a) {
                    $placeholder = ":busqueda$i";
                    $whereParts[] = "`$a` LIKE $placeholder";
                    $params[$placeholder] = '%' . $busqueda . '%';
                }
                $whereClause = implode(' OR ', $whereParts); //array_map no funciono

                if (!empty($busqueda)) {
                    $query = $conn->prepare("SELECT * FROM `$table` WHERE $whereClause");
                    $query->execute($params);
                    $results = $query->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $query = $conn->prepare("SELECT * FROM `$table`");
                    $query->execute();
                    $results = $query->fetchAll(PDO::FETCH_ASSOC);
                }

                if (!empty($results)) {
                    $results = array_slice($results, 0, $mostrar);
                }
            } else {
                $results = [];
            }

            $ruta_foto = BASE_URL . "/Foto/";
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!empty($results)) {
                echo "<div class='table-wrapper'>";
                echo "<table>";
                echo "<tr>";
                echo "<th>Numero</th>";
                $columns = array_keys($results[0]);
                for ($i = 0; $i < count($columns); $i++) {//toco cambiar el foreach por for para poder usar el indice y mostrar el numero de fila
                    echo "<th>" . htmlspecialchars($columns[$i]) . "</th>";
                }
                echo "<th>Editar</th>";
                echo "<th>Eliminar</th>";
                echo "</tr>";
                // Nombre de la primera columna (usada como id por defecto)
                $firstColumn = array_key_first($results[0]);
                for ($i = 0; $i < count($results); $i++) {
                    $row = $results[$i];
                    echo "<tr>";
                    echo "<td class='row-number'>" . ($i + 1) . "</td>";
                    $values = array_values($row);
                    for ($j = 0; $j < count($values); $j++) {
                        $value = $values[$j];
                        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', (string)$value, $coincidencias)) {
                            echo "<td><img style='width: 100px; height: 100px; object-fit: cover;' src='" . htmlspecialchars($ruta_foto . $value) . "' alt='Foto'></td>";
                        } else {
                            echo "<td>" . htmlspecialchars((string)$value) . "</td>";
                        }
                    }

                    // ID y URLs seguros
                    $id = $row[$firstColumn];
                    $editUrl = BASE_URL . '/Edit/EditarUniversal.php?id=' . urlencode((string)$id) . '&tbl=' . urlencode($table);
                    echo "<td><a class='action-link edit' href='" . htmlspecialchars($editUrl) . "'>Editar</a></td>";

                    // Construir onclick seguro con json_encode para evitar inyección
                    $onclick = 'eliminar(' . json_encode((string)$id) . ', ' . json_encode((string)$firstColumn) . ', ' . json_encode((string)$table) . ')';
                    echo "<td><button class='action-link delete' type='button' onclick='" . htmlspecialchars($onclick, ENT_QUOTES) . "'>Eliminar</button></td>";

                    echo "</tr>";
                }
                echo "</table>";
                echo "</div>";
            } else {
                echo "<p class='no-results'>No se encontraron resultados para la búsqueda en la tabla '" . htmlspecialchars($table) . "'.</p>";
            }
        ?>
        <script>
            function eliminar(id, col, table) {
                if (confirm('¿Desea eliminar la fila con id: ' + id + '?')) {
                    window.location.href = '<?= BASE_URL ?>/Delete/DeleteUniversal.php?tbl=' + encodeURIComponent(table) + '&col=' + encodeURIComponent(col) + '&id=' + encodeURIComponent(id);
                }
            }
        </script>
    </div>
</body>
</html>