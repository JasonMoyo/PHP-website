<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get order number from request
$order_number = isset($_GET['order_number']) ? trim($_GET['order_number']) : '';

if (empty($order_number)) {
    echo json_encode(["status" => "error", "message" => "Order number required"]);
    exit;
}

// Get complete order details
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
        m.manufacturer,
        m.category,
        m.description,
        m.composition,
        m.uses,
        m.side_effects,
        m.precautions,
        m.price AS unit_price,
        m.requires_prescription,
        m.image_path,
        u.user_id,
        u.first_name,
        u.last_name,
        u.mobile_no,
        u.email,
        u.address AS customer_address,
        u.gender,
        f.packaging_rating,
        f.delivery_rating,
        f.quality_rating,
        p.prescription_id,
        p.image_path AS prescription_image,
        p.status AS prescription_status
    FROM orders o
    INNER JOIN medicines m ON o.medicine_id = m.medicine_id
    INNER JOIN users u ON o.user_id = u.user_id
    LEFT JOIN order_feedback f ON o.id = f.order_id
    LEFT JOIN prescriptions p ON o.prescription_id = p.prescription_id
    WHERE o.order_number = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $order_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Order not found"]);
    exit;
}

$order = $result->fetch_assoc();

// Format the response
$formatted_order = [
    'order_id' => $order['order_id'],
    'order_number' => $order['order_number'],
    'status' => $order['status'],
    'created_at' => $order['created_at'],
    'delivered_at' => $order['delivered_at'],
    'delivery_otp' => $order['delivery_otp'],
    'delivery_address' => $order['delivery_address'],
    
    'medicine_details' => [
        'medicine_id' => $order['medicine_id'],
        'name' => $order['medicine_name'],
        'generic_name' => $order['generic_name'],
        'manufacturer' => $order['manufacturer'],
        'category' => $order['category'],
        'unit_price' => (float)$order['unit_price'],
        'quantity' => (int)$order['quantity'],
        'total_price' => (float)$order['total_price'],
        'requires_prescription' => (bool)$order['requires_prescription'],
        'image_path' => $order['image_path'] ? "http://localhost/phpproj/ABC%20MEDICOS/" . $order['image_path'] : null,
        'description' => $order['description'],
        'composition' => $order['composition'],
        'uses' => $order['uses'],
        'side_effects' => $order['side_effects'],
        'precautions' => $order['precautions']
    ],
    
    'customer_details' => [
        'user_id' => $order['user_id'],
        'name' => $order['first_name'] . ' ' . $order['last_name'],
        'mobile_no' => $order['mobile_no'],
        'email' => $order['email'],
        'address' => $order['customer_address'],
        'gender' => $order['gender']
    ],
    
    'prescription_details' => $order['prescription_id'] ? [
        'prescription_id' => $order['prescription_id'],
        'image_path' => $order['prescription_image'] ? "http://localhost/phpproj/ABC%20MEDICOS/" . $order['prescription_image'] : null,
        'status' => $order['prescription_status']
    ] : null,
    
    'feedback' => $order['packaging_rating'] ? [
        'packaging_rating' => (int)$order['packaging_rating'],
        'delivery_rating' => (int)$order['delivery_rating'],
        'quality_rating' => (int)$order['quality_rating'],
        'overall_rating' => round(($order['packaging_rating'] + $order['delivery_rating'] + $order['quality_rating']) / 3, 1)
    ] : null,
    
    'timeline' => [
        'order_placed' => $order['created_at'],
        'estimated_delivery' => date('Y-m-d H:i:s', strtotime($order['created_at'] . ' +3 days')),
        'actual_delivery' => $order['delivered_at']
    ]
];

echo json_encode([
    "status" => "success",
    "order" => $formatted_order
]);

$stmt->close();
$conn->close();
?>