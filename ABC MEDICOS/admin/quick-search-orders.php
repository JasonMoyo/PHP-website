<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get quick search term
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($search_term)) {
    echo json_encode(["status" => "error", "message" => "Search term required"]);
    exit;
}

// Quick search across multiple fields
$sql = "
    SELECT 
        o.id AS order_id,
        o.order_number,
        o.quantity,
        o.total_price,
        o.status,
        o.created_at,
        m.name AS medicine_name,
        m.generic_name,
        u.first_name,
        u.last_name,
        u.mobile_no
    FROM orders o
    INNER JOIN medicines m ON o.medicine_id = m.medicine_id
    INNER JOIN users u ON o.user_id = u.user_id
    WHERE 
        o.order_number LIKE ? OR 
        m.name LIKE ? OR 
        m.generic_name LIKE ? OR 
        u.first_name LIKE ? OR 
        u.last_name LIKE ? OR 
        u.mobile_no LIKE ? OR
        CONCAT(u.first_name, ' ', u.last_name) LIKE ?
    ORDER BY o.created_at DESC
    LIMIT 20
";

$search_pattern = "%$search_term%";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssss", 
    $search_pattern, $search_pattern, $search_pattern, 
    $search_pattern, $search_pattern, $search_pattern,
    $search_pattern
);

$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = [
        'order_id' => $row['order_id'],
        'order_number' => $row['order_number'],
        'medicine_name' => $row['medicine_name'],
        'generic_name' => $row['generic_name'],
        'quantity' => (int)$row['quantity'],
        'total_price' => (float)$row['total_price'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'customer_name' => $row['first_name'] . ' ' . $row['last_name'],
        'customer_mobile' => $row['mobile_no'],
        'status_badge' => getStatusBadge($row['status'])
    ];
}

echo json_encode([
    "status" => "success",
    "search_term" => $search_term,
    "results_count" => count($orders),
    "orders" => $orders
]);

$stmt->close();
$conn->close();

// Helper function for status badges
function getStatusBadge($status) {
    $badges = [
        'Placed' => 'primary',
        'Packaging' => 'info',
        'Transported' => 'warning',
        'Delivered' => 'success',
        'Cancelled' => 'danger',
        'Returned' => 'secondary'
    ];
    return $badges[$status] ?? 'secondary';
}
?>