<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($search === '') {
    echo json_encode(['status' => 'error', 'message' => 'Empty search query']);
    exit;
}

// 🔍 Prepare SQL to match medicine name, generic name, manufacturer, or category
$sql = "
    SELECT 
        medicine_id,
        name,
        generic_name,
        manufacturer,
        category,
        price,
        stock_quantity,
        requires_prescription,
        image_path
    FROM medicines
    WHERE 
        (name LIKE ? OR generic_name LIKE ? OR manufacturer LIKE ? OR category LIKE ?)
        AND stock_quantity > 0
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);
$searchTerm = '%' . $search . '%';
$stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();

$result = $stmt->get_result();
$medicines = [];

$base_url = "http://localhost/phpproj/ABC%20MEDICOS/"; 

while ($row = $result->fetch_assoc()) {
    // Add full image path
    $row['image_path'] = $row['image_path'] ? $base_url . $row['image_path'] : null;
    $row['price'] = (float)$row['price'];
    $row['stock_quantity'] = (int)$row['stock_quantity'];
    $row['requires_prescription'] = (bool)$row['requires_prescription'];
    
    $medicines[] = $row;
}

echo json_encode([
    'status' => 'success',
    'count' => count($medicines),
    'search_term' => $search,
    'medicines' => $medicines
]);

$stmt->close();
$conn->close();
?>