<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get threshold parameter (optional)
$threshold = isset($_GET['threshold']) ? intval($_GET['threshold']) : 10;

$sql = "
    SELECT 
        medicine_id,
        name,
        generic_name,
        manufacturer,
        category,
        price,
        stock_quantity,
        min_stock_level,
        requires_prescription,
        expiry_date,
        image_path,
        created_at,
        (stock_quantity - min_stock_level) AS stock_buffer,
        CASE 
            WHEN stock_quantity = 0 THEN 'Out of Stock'
            WHEN stock_quantity <= min_stock_level THEN 'Critical'
            WHEN stock_quantity <= (min_stock_level * 2) THEN 'Low'
            ELSE 'Adequate'
        END AS stock_status
    FROM medicines
    WHERE stock_quantity <= (min_stock_level * 2)  -- Show critical and low stock items
    ORDER BY stock_quantity ASC, stock_status DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

$low_stock_items = [];
$summary = [
    'total_low_stock' => 0,
    'critical_count' => 0,
    'out_of_stock_count' => 0,
    'low_count' => 0,
    'total_value_at_risk' => 0
];

while ($row = $result->fetch_assoc()) {
    $value_at_risk = $row['price'] * $row['stock_quantity'];
    
    $low_stock_items[] = [
        'medicine_id' => $row['medicine_id'],
        'name' => $row['name'],
        'generic_name' => $row['generic_name'],
        'manufacturer' => $row['manufacturer'],
        'category' => $row['category'],
        'price' => (float)$row['price'],
        'stock_quantity' => (int)$row['stock_quantity'],
        'min_stock_level' => (int)$row['min_stock_level'],
        'stock_buffer' => (int)$row['stock_buffer'],
        'stock_status' => $row['stock_status'],
        'requires_prescription' => (bool)$row['requires_prescription'],
        'expiry_date' => $row['expiry_date'],
        'value_at_risk' => round($value_at_risk, 2),
        'image_path' => $row['image_path'] ? "http://localhost/phpproj/ABC%20MEDICOS/" . $row['image_path'] : null
    ];
    
    // Update summary
    $summary['total_low_stock']++;
    $summary['total_value_at_risk'] += $value_at_risk;
    
    switch ($row['stock_status']) {
        case 'Out of Stock':
            $summary['out_of_stock_count']++;
            break;
        case 'Critical':
            $summary['critical_count']++;
            break;
        case 'Low':
            $summary['low_count']++;
            break;
    }
}

$summary['total_value_at_risk'] = round($summary['total_value_at_risk'], 2);

echo json_encode([
    "status" => "success",
    "summary" => $summary,
    "low_stock_items" => $low_stock_items,
    "threshold" => $threshold
]);

$stmt->close();
$conn->close();
?>