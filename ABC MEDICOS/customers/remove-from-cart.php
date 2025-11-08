<?php
require_once("../config/cors-headers.php");
session_start();
header('Content-Type: application/json');
require_once("../config/db_connect.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request for debugging
error_log("Remove from cart request received: " . print_r($_POST, true));
error_log("Raw input: " . file_get_contents("php://input"));

// 1️⃣ Ensure user logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in for cart removal");
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
error_log("User ID: $user_id");

// Handle both POST form data and JSON input
$input_data = $_POST;
if (empty($input_data)) {
    $input_data = json_decode(file_get_contents("php://input"), true) ?? [];
}

error_log("Processed input data: " . print_r($input_data, true));

$cart_id = isset($input_data['cart_id']) ? intval($input_data['cart_id']) : 0;
error_log("Cart ID received: $cart_id");

if ($cart_id <= 0) {
    error_log("Invalid cart ID: $cart_id");
    echo json_encode(['status' => 'error', 'message' => 'Invalid cart item']);
    exit;
}

// 2️⃣ Verify item belongs to this user
$check = $conn->prepare("SELECT cart_id FROM cart WHERE cart_id = ? AND user_id = ?");
$check->bind_param("ii", $cart_id, $user_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows === 0) {
    error_log("Cart item $cart_id not found for user $user_id");
    echo json_encode(['status' => 'error', 'message' => 'Item not found in your cart']);
    exit;
}

// 3️⃣ Delete item
$delete = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
$delete->bind_param("ii", $cart_id, $user_id);

if ($delete->execute()) {
    error_log("Successfully removed cart item $cart_id for user $user_id");
    echo json_encode(['status' => 'success', 'message' => 'Item removed from cart']);
} else {
    error_log("Failed to remove cart item: " . $delete->error);
    echo json_encode(['status' => 'error', 'message' => 'Failed to remove item']);
}

$check->close();
$delete->close();
$conn->close();
?>