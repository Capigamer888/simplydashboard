<?php
include __DIR__ . '/../inicializaciones.php';
$table = $_GET["tbl"];
$idv = $_GET["id"];

/*TESTS
$i=0;
foreach($_POST as $key => $value) {
    $i++ ;
    echo $i.".<b> post </b>". $key .": ". $value;

    echo"<br>";
};
echo "<br><hr><br>";
$e=0;
foreach($_GET as $key => $value) {
    $e++ ;
    echo $e.". <i>get</i> ". $key .": ". $value ."";
    echo"<br>";
};
*/

$foto_dir = PROJECT_ROOT . DIRECTORY_SEPARATOR . 'Foto';

foreach ($_FILES as $foto_columna => $archivo) {
    if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
        die('Error al subir la foto.');
    }

    if (@getimagesize($archivo['tmp_name']) === false) {
        die('El archivo seleccionado no es una imagen válida.');
    }

    $nombre_original = basename($archivo['name']);
    $nombre_original = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre_original);
    $nombre_tabla = preg_replace('/[^A-Za-z0-9_-]/', '_', $table);
    $nombre_id = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$idv);
    $nombre_foto = $nombre_tabla . '_' . $nombre_id . '_' . $nombre_original;

    if (!is_dir($foto_dir) && !mkdir($foto_dir, 0755, true)) {
        die('No se pudo crear la carpeta Foto.');
    }

    if (!move_uploaded_file($archivo['tmp_name'], $foto_dir . DIRECTORY_SEPARATOR . $nombre_foto)) {
        die('No se pudo guardar la foto.');
    }

    $_POST[$foto_columna] = $nombre_foto;
}
/*
echo "<br><hr><br>";
echo "post count: ".count($_POST);
echo "<br>";
echo "get count: ". count($_GET);

------siendo en get ?key=value-----

echo "<br><hr><br>";
$sin_pk= array_slice($_POST,1);
$tik= array_keys($sin_pk);
$pk_key=array_key_first($_POST);
echo $set = implode(', ', array_map(fn($col) => "`$col` = :$col", $tik));
`tipo_usuario` = :tipo_usuario, `genero` = :genero, `nombre` = :nombre, `apellido` = :apellido, `password` = :password, `telefono` = :telefono, `fecha_nacimiento` = :fecha_nacimiento, `fecha_registro` = :fecha_registro


//Si funciona no lo toques

if (!($idv == array_key_first($_POST))) {
    $col = isset($_GET['col']) ? (string)$_GET['col'] : '';
    $check = $conn->prepare("SELECT COUNT(*) AS c FROM `$table` WHERE `$col` = ?");
    $check->execute([$idv]);
    $exists = (int)($check->fetch()['c'] ?? 0);
    if ($exists > 0) {
        echo "<br><a href='" . BASE_URL . "/Read/TablaUniversal.php?tbl=".$table."'>Regresar</a><br>";        
        die('Error: ya existe un registro con ' . htmlspecialchars($col) . ' = ' . htmlspecialchars($idv));
    }
}
*/
if (!empty($_POST) && !empty($idv) && !empty($table)) {
    try {
        $pk_key = isset($_GET['col']) ? trim((string)$_GET['col']) : '';
        if ($pk_key === '') {
            $pk = $conn->prepare("SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND CONSTRAINT_NAME = 'PRIMARY'
                LIMIT 1");
            $pk->execute([$table]);
            $pk_key = (string)($pk->fetchColumn() ?: '');
        }

        if ($pk_key === '') {
            throw new RuntimeException('No se pudo detectar la columna primaria de la tabla.');
        }

        $update_data = $_POST;
        unset($update_data[$pk_key]);

        if (empty($update_data)) {
            echo "⚠ No hay columnas suficientes para actualizar.";
        } else {
            $set = implode(', ', array_map(fn($col) => "`$col` = :$col", array_keys($update_data)));
            $update = $conn->prepare("UPDATE `$table` SET $set WHERE `$pk_key` = :old_pk_val");

            foreach ($update_data as $key => $value) {
                $update->bindValue(':' . $key, $value, PDO::PARAM_STR);
            }
            $update->bindValue(':old_pk_val', $idv, PDO::PARAM_STR);
            $update->execute();

            if ($update->rowCount() > 0) {
                echo "✓ Registro actualizado correctamente.";
                echo "<a href='" . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($table) . "'>Regresar</a>";
            } else {
                echo "⚠ No se realizó ningún cambio (los datos ya eran idénticos).";
            }
        }
    } catch (Throwable $e) {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
} else {
    echo "⚠ Falta ID/Tabla en GET o datos en POST";
}