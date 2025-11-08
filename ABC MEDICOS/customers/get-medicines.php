<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

$sql = "SELECT medicine_id, name, generic_name, manufacturer, price, stock_quantity, category, image_path, requires_prescription
        FROM medicines 
        WHERE stock_quantity > 0
        ORDER BY created_at DESC";

$res = $conn->query($sql);
$medicines = [];

$base_url = "http://localhost/phpproj/ABC%20MEDICOS/";

while ($row = $res->fetch_assoc()) {
    $row['price'] = (float)$row['price'];
    $row['stock_quantity'] = (int)$row['stock_quantity'];
    $row['requires_prescription'] = (bool)$row['requires_prescription'];
    
    // Add full image path
    $row['image_path'] = $row['image_path'] ? $base_url . $row['image_path'] : null;
    
    $medicines[] = $row;
}

echo json_encode(['status'=>'success','medicines'=>$medicines]);
$conn->close();
?>