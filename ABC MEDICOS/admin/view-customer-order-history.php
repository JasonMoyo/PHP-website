<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get customer ID from request
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid customer ID"]);
    exit;
}

// Verify customer exists
$customer_check = $conn->prepare("SELECT user_id, first_name, last_name, mobile_no, email, created_at AS member_since FROM users WHERE user_id = ? AND role = 'customer'");
$customer_check->bind_param("i", $user_id);
$customer_check->execute();
$customer_result = $customer_check->get_result();

if ($customer_result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Customer not found"]);
    exit;
}

$customer = $customer_result->fetch_assoc();
$customer_check->close();

// Get customer's complete order history
$orders_sql = "
    SELECT 
        o.id AS order_id,
        o.order_number,
        o.medicine_id,
        o.quantity,
        o.total_price,
        o.status,
        o.delivery_otp,
        o.delivery_address,
        o.created_at,
        o.delivered_at,
        m.name AS medicine_name,
        m.generic_name,
        m.manufacturer,
        m.category,
        m.requires_prescription,
        m.image_path,
        f.packaging_rating,
        f.delivery_rating,
        f.quality_rating
    FROM orders o
    INNER JOIN medicines m ON o.medicine_id = m.medicine_id
    LEFT JOIN order_feedback f ON o.id = f.order_id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
";

$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

$orders = [];
$customer_stats = [
    'total_orders' => 0,
    'total_spent' => 0,
    'avg_order_value' => 0,
    'completed_orders' => 0,
    'cancelled_orders' => 0,
    'pending_orders' => 0,
    'first_order_date' => null,
    'last_order_date' => null,
    'member_since_days' => round((time() - strtotime($customer['member_since'])) / (60 * 60 * 24))
];

while ($row = $orders_result->fetch_assoc()) {
    $orders[] = [
        'order_id' => $row['order_id'],
        'order_number' => $row['order_number'],
        'medicine_id' => $row['medicine_id'],
        'medicine_name' => $row['medicine_name'],
        'generic_name' => $row['generic_name'],
        'manufacturer' => $row['manufacturer'],
        'category' => $row['category'],
        'quantity' => (int)$row['quantity'],
        'total_price' => (float)$row['total_price'],
        'status' => $row['status'],
        'delivery_address' => $row['delivery_address'],
        'requires_prescription' => (bool)$row['requires_prescription'],
        'created_at' => $row['created_at'],
        'delivered_at' => $row['delivered_at'],
        'image_path' => $row['image_path'] ? "http://localhost/phpproj/ABC%20MEDICOS/" . $row['image_path'] : null,
        'has_feedback' => !is_null($row['packaging_rating']),
        'ratings' => $row['packaging_rating'] ? [
            'packaging' => (int)$row['packaging_rating'],
            'delivery' => (int)$row['delivery_rating'],
            'quality' => (int)$row['quality_rating'],
            'overall' => round(($row['packaging_rating'] + $row['delivery_rating'] + $row['quality_rating']) / 3, 1)
        ] : null
    ];
    
    // Update customer statistics
    $customer_stats['total_orders']++;
    $customer_stats['total_spent'] += $row['total_price'];
    
    switch ($row['status']) {
        case 'Delivered':
            $customer_stats['completed_orders']++;
            break;
        case 'Cancelled':
            $customer_stats['cancelled_orders']++;
            break;
        default:
            $customer_stats['pending_orders']++;
            break;
    }
    
    // Track first and last order dates
    if (!$customer_stats['first_order_date'] || $row['created_at'] < $customer_stats['first_order_date']) {
        $customer_stats['first_order_date'] = $row['created_at'];
    }
    if (!$customer_stats['last_order_date'] || $row['created_at'] > $customer_stats['last_order_date']) {
        $customer_stats['last_order_date'] = $row['created_at'];
    }
}

$orders_stmt->close();

// Calculate averages
if ($customer_stats['total_orders'] > 0) {
    $customer_stats['avg_order_value'] = round($customer_stats['total_spent'] / $customer_stats['total_orders'], 2);
}

// Get customer's favorite categories
$categories_sql = "
    SELECT 
        m.category,
        COUNT(o.id) AS order_count,
        SUM(o.quantity) AS total_quantity,
        SUM(o.total_price) AS total_spent
    FROM orders o
    INNER JOIN medicines m ON o.medicine_id = m.medicine_id
    WHERE o.user_id = ? AND o.status != 'Cancelled'
    GROUP BY m.category
    ORDER BY order_count DESC
    LIMIT 5
";

$categories_stmt = $conn->prepare($categories_sql);
$categories_stmt->bind_param("i", $user_id);
$categories_stmt->execute();
$categories_result = $categories_stmt->get_result();

$favorite_categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $favorite_categories[] = [
        'category' => $row['category'] ?: 'Uncategorized',
        'order_count' => (int)$row['order_count'],
        'total_quantity' => (int)$row['total_quantity'],
        'total_spent' => round((float)$row['total_spent'], 2)
    ];
}

$categories_stmt->close();

echo json_encode([
    "status" => "success",
    "customer_info" => [
        "user_id" => $customer['user_id'],
        "name" => $customer['first_name'] . ' ' . $customer['last_name'],
        "mobile_no" => $customer['mobile_no'],
        "email" => $customer['email'],
        "member_since" => $customer['member_since']
    ],
    "customer_stats" => $customer_stats,
    "favorite_categories" => $favorite_categories,
    "order_history" => $orders
]);

$conn->close();
?>