<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");
session_start();

// ✅ Ensure user logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

// Handle both POST and JSON input
$input_data = $_POST;
if (empty($input_data)) {
    $input_data = json_decode(file_get_contents("php://input"), true) ?? [];
}

$order_id = intval($input_data['order_id'] ?? 0);
$reason = trim($input_data['reason'] ?? '');
$user_id = $_SESSION['user_id'];

if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit;
}

if ($reason === '') {
    echo json_encode(['status' => 'error', 'message' => 'Return reason is required']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // ✅ Step 1: Check order ownership & status AND get medicine quantity
    $sql = "SELECT status, delivered_at, medicine_id, quantity FROM orders WHERE id=? AND user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception('Order not found');
    }

    $order = $res->fetch_assoc();

    // ✅ Step 2: Ensure it's delivered
    if ($order['status'] !== 'Delivered') {
        throw new Exception('Only delivered orders can be returned');
    }

    // ✅ Step 3: Check return window (3 days)
    $delivered_at = strtotime($order['delivered_at']);
    if (time() - $delivered_at > 3 * 24 * 60 * 60) {
        throw new Exception('Return window expired (3 days)');
    }

    // ✅ Step 4: Return medicine quantity to stock
    $return_stock = $conn->prepare("UPDATE medicines SET stock_quantity = stock_quantity + ? WHERE medicine_id = ?");
    $return_stock->bind_param("ii", $order['quantity'], $order['medicine_id']);
    if (!$return_stock->execute()) {
        throw new Exception('Failed to return stock');
    }

    // ✅ Step 5: Mark order as Returned
    $update = $conn->prepare("UPDATE orders SET status='Returned' WHERE id=? AND user_id=?");
    $update->bind_param("ii", $order_id, $user_id);
    if (!$update->execute()) {
        throw new Exception('Failed to update order status');
    }

    // ✅ Step 6: Log message in messages
    $message = "I am requesting to return order $order_id due to: $reason";
    $subject = "Return Request for Order #$order_id";
    $is_public = 0;

    $stmt3 = $conn->prepare("INSERT INTO messages (admin_id, user_id, subject, message, is_public) VALUES (NULL, ?, ?, ?, ?)");
    $stmt3->bind_param("issi", $user_id, $subject, $message, $is_public);
    if (!$stmt3->execute()) {
        throw new Exception('Failed to log return request');
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'Return request submitted successfully']);

} catch (Exception $e) {
    // Rollback on any error
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// Close connections
if (isset($stmt)) $stmt->close();
if (isset($update)) $update->close();
if (isset($return_stock)) $return_stock->close();
if (isset($stmt3)) $stmt3->close();
$conn->close();
?>