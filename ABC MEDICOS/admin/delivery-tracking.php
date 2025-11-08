<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get order ID from request
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid order ID"]);
    exit;
}

// Get order details with delivery information
$sql = "
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
        u.user_id,
        u.first_name,
        u.last_name,
        u.mobile_no,
        u.email,
        DATEDIFF(NOW(), o.created_at) AS days_since_order,
        CASE 
            WHEN o.status = 'Placed' THEN 1
            WHEN o.status = 'Packaging' THEN 2
            WHEN o.status = 'Transported' THEN 3
            WHEN o.status = 'Delivered' THEN 4
            WHEN o.status = 'Cancelled' THEN 0
            WHEN o.status = 'Returned' THEN -1
            ELSE 1
        END AS status_order
    FROM orders o
    INNER JOIN medicines m ON o.medicine_id = m.medicine_id
    INNER JOIN users u ON o.user_id = u.user_id
    WHERE o.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Order not found"]);
    exit;
}

$order = $result->fetch_assoc();
$stmt->close();

// Define delivery stages with timestamps (in a real system, these would come from a tracking table)
$delivery_stages = [
    [
        'stage' => 'Order Placed',
        'status' => 'completed',
        'description' => 'Order has been received and confirmed',
        'timestamp' => $order['created_at'],
        'icon' => '📦'
    ],
    [
        'stage' => 'Processing',
        'status' => $order['status_order'] >= 2 ? 'completed' : 'pending',
        'description' => 'Order is being processed and prepared for shipment',
        'timestamp' => $order['status_order'] >= 2 ? date('Y-m-d H:i:s', strtotime($order['created_at'] . ' +1 hour')) : null,
        'icon' => '⚡'
    ]
];

// Add packaging stage if applicable
if ($order['status_order'] >= 2) {
    $delivery_stages[] = [
        'stage' => 'Packaging',
        'status' => $order['status_order'] >= 2 ? 'completed' : 'pending',
        'description' => 'Items are being carefully packaged',
        'timestamp' => $order['status_order'] >= 2 ? date('Y-m-d H:i:s', strtotime($order['created_at'] . ' +2 hours')) : null,
        'icon' => '📦'
    ];
}

// Add transportation stage if applicable
if ($order['status_order'] >= 3) {
    $delivery_stages[] = [
        'stage' => 'In Transit',
        'status' => $order['status_order'] >= 3 ? 'completed' : 'pending',
        'description' => 'Package is out for delivery',
        'timestamp' => $order['status_order'] >= 3 ? date('Y-m-d H:i:s', strtotime($order['created_at'] . ' +4 hours')) : null,
        'icon' => '🚚'
    ];
}

// Add delivery stage if applicable
if ($order['status_order'] >= 4) {
    $delivery_stages[] = [
        'stage' => 'Delivered',
        'status' => 'completed',
        'description' => 'Package has been delivered successfully',
        'timestamp' => $order['delivered_at'],
        'icon' => '✅'
    ];
}

// Calculate delivery statistics
$delivery_stats = [
    'current_status' => $order['status'],
    'days_since_order' => $order['days_since_order'],
    'estimated_delivery' => date('Y-m-d', strtotime($order['created_at'] . ' +3 days')),
    'delivery_otp' => $order['delivery_otp'],
    'is_delivered' => $order['status'] === 'Delivered',
    'is_cancelled' => $order['status'] === 'Cancelled',
    'is_returned' => $order['status'] === 'Returned'
];

echo json_encode([
    "status" => "success",
    "order_info" => [
        "order_id" => $order['order_id'],
        "order_number" => $order['order_number'],
        "medicine_name" => $order['medicine_name'],
        "generic_name" => $order['generic_name'],
        "quantity" => (int)$order['quantity'],
        "total_price" => (float)$order['total_price'],
        "customer_name" => $order['first_name'] . ' ' . $order['last_name'],
        "customer_mobile" => $order['mobile_no'],
        "delivery_address" => $order['delivery_address']
    ],
    "delivery_stats" => $delivery_stats,
    "tracking_timeline" => $delivery_stages
]);

$conn->close();
?>