<?php
require_once("../config/cors-headers.php");
require_once("../config/db_connect.php");
header('Content-Type: application/json');
session_start();

// ✅ Parse input from POST or JSON
$input_data = $_POST;
if (empty($input_data)) {
    $input_data = json_decode(file_get_contents("php://input"), true) ?? [];
}
$order_id = intval($input_data['order_id'] ?? 0);

// ✅ Check user session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// ✅ Validate order ID
if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit;
}

// ✅ Check if order exists and belongs to user
$sql = "SELECT status FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    $stmt->close();
    $conn->close();
    exit;
}

$order = $res->fetch_assoc();

// ✅ Only allow cancellation if status is 'Placed'
if ($order['status'] !== 'Placed') {
    echo json_encode(['status' => 'error', 'message' => 'Cannot cancel this order']);
    $stmt->close();
    $conn->close();
    exit;
}

// ✅ Update order status to 'Cancelled'
$update = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
$update->bind_param("ii", $order_id, $user_id);
if ($update->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Order cancelled successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancel order']);
}

// ✅ Cleanup
$stmt->close();
$update->close();
$conn->close();
?>
