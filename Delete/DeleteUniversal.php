<?php
include __DIR__ . '/../inicializaciones.php';
$table = $_GET['tbl'] ?? '';
$id = $_GET['id'] ?? '';
$col = $_GET['col'] ??'';
try{
    if (!empty($table) && !empty($id) && !empty($col)) {
        if ($id) {
        $sql = "DELETE FROM `$table` WHERE `$col` = :id";
        $delete = $conn->prepare($sql);
        
        $delete->bindValue(":id", $id); 
        $delete->execute();
        }
        if ($delete->rowCount() > 0) {
            header("Location: " . BASE_URL . "/Read/TablaUniversal.php?tbl=" . urlencode($table));
        } else {
            echo "No matching records found to delete.<br>";
        }
    } else {
        echo "No table, column, or ID specified.";
    }
}
catch(PDOException $e) {
    die("". $e->getMessage());
}