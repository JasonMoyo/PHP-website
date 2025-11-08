<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get search term for customers
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($search_term)) {
    echo json_encode(["status" => "error", "message" => "Search term required"]);
    exit;
}

// Search customers by name, mobile, or email
$sql = "
    SELECT 
        user_id,
        first_name,
        last_name,
        mobile_no,
        email,
        address,
        gender,
        created_at
    FROM users 
    WHERE role = 'customer' AND (
        first_name LIKE ? OR 
        last_name LIKE ? OR 
        mobile_no LIKE ? OR 
        email LIKE ? OR
        CONCAT(first_name, ' ', last_name) LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 20
";

$search_pattern = "%$search_term%";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", 
    $search_pattern, $search_pattern, $search_pattern, 
    $search_pattern, $search_pattern
);

$stmt->execute();
$result = $stmt->get_result();

$customers = [];
while ($row = $result->fetch_assoc()) {
    // Get order count for each customer
    $order_count_sql = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?";
    $count_stmt = $conn->prepare($order_count_sql);
    $count_stmt->bind_param("i", $row['user_id']);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $order_count = $count_result->fetch_assoc()['order_count'];
    $count_stmt->close();
    
    $customers[] = [
        'user_id' => $row['user_id'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'mobile_no' => $row['mobile_no'],
        'email' => $row['email'],
        'address' => $row['address'],
        'gender' => $row['gender'],
        'member_since' => $row['created_at'],
        'order_count' => (int)$order_count,
        'display_text' => $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['mobile_no'] . ') - ' . $order_count . ' orders'
    ];
}

echo json_encode([
    "status" => "success",
    "search_term" => $search_term,
    "results_count" => count($customers),
    "customers" => $customers
]);

$stmt->close();
$conn->close();
?>