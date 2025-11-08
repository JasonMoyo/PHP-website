<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get parameters
$medicine_id = isset($_GET['medicine_id']) ? intval($_GET['medicine_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

if ($medicine_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid medicine ID"]);
    exit;
}

// Verify medicine exists
$medicine_check = $conn->prepare("SELECT medicine_id, name, generic_name FROM medicines WHERE medicine_id = ?");
$medicine_check->bind_param("i", $medicine_id);
$medicine_check->execute();
$medicine_result = $medicine_check->get_result();

if ($medicine_result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Medicine not found"]);
    exit;
}

$medicine = $medicine_result->fetch_assoc();
$medicine_check->close();

// In a real system, you would have a stock_history table
// For now, we'll simulate using order data and current stock

// Get current stock information
$current_stock_sql = "
    SELECT 
        stock_quantity,
        min_stock_level,
        created_at AS added_date
    FROM medicines 
    WHERE medicine_id = ?
";

$current_stmt = $conn->prepare($current_stock_sql);
$current_stmt->bind_param("i", $medicine_id);
$current_stmt->execute();
$current_result = $current_stmt->get_result();
$current_stock = $current_result->fetch_assoc();
$current_stmt->close();

// Get sales data (stock out)
$sales_sql = "
    SELECT 
        o.created_at AS date,
        o.quantity AS change_quantity,
        'sale' AS type,
        o.order_number AS reference,
        CONCAT('Order #', o.order_number, ' - ', u.first_name, ' ', u.last_name) AS description,
        -o.quantity AS quantity_change
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
    WHERE o.medicine_id = ? AND o.status != 'Cancelled'
    ORDER BY o.created_at DESC
    LIMIT ?
";

$sales_stmt = $conn->prepare($sales_sql);
$sales_stmt->bind_param("ii", $medicine_id, $limit);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();

$stock_history = [];
$current_quantity = $current_stock['stock_quantity'];

// Process sales as stock reductions
while ($row = $sales_result->fetch_assoc()) {
    $stock_history[] = [
        'date' => $row['date'],
        'type' => $row['type'],
        'reference' => $row['reference'],
        'description' => $row['description'],
        'quantity_change' => (int)$row['quantity_change'],
        'new_quantity' => $current_quantity, // This would be calculated properly in real system
        'change_type' => 'out'
    ];
    $current_quantity += abs($row['quantity_change']); // Add back the sold quantity
}

$sales_stmt->close();

// Add current stock as the latest entry
array_unshift($stock_history, [
    'date' => date('Y-m-d H:i:s'),
    'type' => 'current',
    'reference' => 'CURRENT',
    'description' => 'Current Stock Level',
    'quantity_change' => 0,
    'new_quantity' => (int)$current_stock['stock_quantity'],
    'change_type' => 'current'
]);

// Calculate stock statistics
$stock_stats = [
    'current_stock' => (int)$current_stock['stock_quantity'],
    'min_stock_level' => (int)$current_stock['min_stock_level'],
    'stock_status' => $current_stock['stock_quantity'] <= $current_stock['min_stock_level'] ? 'Low' : 'Adequate',
    'stock_buffer' => $current_stock['stock_quantity'] - $current_stock['min_stock_level'],
    'total_sales' => count($stock_history) - 1, // Exclude current entry
    'days_of_supply' => $current_stock['stock_quantity'] > 0 ? 
        round($current_stock['stock_quantity'] / (count($stock_history) / 30), 1) : 0 // Rough estimate
];

echo json_encode([
    "status" => "success",
    "medicine_info" => [
        "medicine_id" => $medicine['medicine_id'],
        "name" => $medicine['name'],
        "generic_name" => $medicine['generic_name']
    ],
    "stock_stats" => $stock_stats,
    "stock_history" => $stock_history,
    "filters" => [
        "medicine_id" => $medicine_id,
        "limit" => $limit
    ]
]);

$conn->close();
?>