<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$order_id = intval($data['order_id'] ?? 0);
$packaging = intval($data['packaging_rating'] ?? 0);
$delivery = intval($data['delivery_rating'] ?? 0);
$quality = intval($data['quality_rating'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order']);
    exit;
}

// Validate ratings (1-5)
if ($packaging < 1 || $packaging > 5 || $delivery < 1 || $delivery > 5 || $quality < 1 || $quality > 5) {
    echo json_encode(['status' => 'error', 'message' => 'Ratings must be between 1 and 5']);
    exit;
}

$sql = "SELECT id, medicine_id FROM orders WHERE id=? AND user_id=? AND status='Delivered'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Only delivered orders can be rated']);
    exit;
}

$order = $res->fetch_assoc();
$medicine_id = $order['medicine_id'];

$stmt2 = $conn->prepare("INSERT INTO order_feedback (order_id, medicine_id, packaging_rating, delivery_rating, quality_rating)
                         VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE packaging_rating=?, delivery_rating=?, quality_rating=?");
$stmt2->bind_param("iiiiiiii", $order_id, $medicine_id, $packaging, $delivery, $quality, $packaging, $delivery, $quality);
$stmt2->execute();

echo json_encode(['status' => 'success', 'message' => 'Rating submitted successfully']);

$stmt->close();
$stmt2->close();
$conn->close();
?>