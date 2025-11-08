<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// ✅ Updated for medicines instead of products
$sql = "
    SELECT 
        o.id AS order_id,
        o.order_number,
        o.quantity,
        o.total_price,
        o.status,
        o.delivery_otp,
        o.delivered_at,
        o.created_at,
        m.medicine_id,
        m.name AS medicine_name,
        m.generic_name,
        m.manufacturer,
        m.price,
        m.requires_prescription,
        u.user_id,
        u.first_name,
        u.last_name,
        u.email,
        u.mobile_no,
        u.address,
        m.image_path AS medicine_image
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
    INNER JOIN medicines m ON o.medicine_id = m.medicine_id
    ORDER BY o.created_at DESC
";

$res = $conn->query($sql);

$orders = [];
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $orders
]);
?>