<?php
require_once("../config/cors-headers.php");
header("Content-Type: application/json");
require_once("../config/db_connect.php");
session_start();

// 1️⃣ Check login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// 2️⃣ Fetch user's orders
$sql = "SELECT 
            o.id,
            o.order_number,
            m.name AS medicine_name,
            m.medicine_id,
            o.quantity,
            o.total_price,
            o.status,
            o.delivery_otp,
            o.created_at,
            o.delivered_at,
            m.image_path AS medicine_image
        FROM orders o
        JOIN medicines m ON o.medicine_id = m.medicine_id
        WHERE o.user_id = ?
        ORDER BY o.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$orders = [];
while ($row = $res->fetch_assoc()) {
    $orders[] = [
        'id' => $row['id'],
        'order_number' => $row['order_number'],
        'medicine_id' => $row['medicine_id'],
        'medicine_name' => $row['medicine_name'],
        'medicine_image' => $row['medicine_image'],
        'quantity' => (int)$row['quantity'],
        'total_price' => (float)$row['total_price'],
        'status' => $row['status'],
        'delivery_otp' => $row['delivery_otp'],
        'created_at' => $row['created_at'],
        'delivered_at' => $row['delivered_at']
    ];
}

echo json_encode(['status' => 'success', 'orders' => $orders]);

$stmt->close();
$conn->close();
?>