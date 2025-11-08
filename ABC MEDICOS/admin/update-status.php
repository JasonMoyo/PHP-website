<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
    echo json_encode(["status" => "error", "message" => "Missing order_id or status"]);
    exit;
}

$order_id = intval($_POST['order_id']);
$status = trim($_POST['status']);

// Allowed transitions
$allowed = ['Placed', 'Packaging', 'Transported', 'Delivered', 'Cancelled', 'Returned'];
if (!in_array($status, $allowed)) {
    echo json_encode(["status" => "error", "message" => "Invalid status"]);
    exit;
}

// ✅ If Delivered, OTP verification is required
if ($status === 'Delivered') {
    if (empty($_POST['delivery_otp'])) {
        echo json_encode(["status" => "error", "message" => "Delivery OTP is required for marking as Delivered"]);
        exit;
    }

    $entered_otp = trim($_POST['delivery_otp']);

    // Get the stored OTP for this order
    $otp_check = $conn->prepare("SELECT delivery_otp FROM orders WHERE id=?");
    $otp_check->bind_param("i", $order_id);
    $otp_check->execute();
    $otp_result = $otp_check->get_result();

    if ($otp_result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Order not found"]);
        exit;
    }

    $row = $otp_result->fetch_assoc();
    $stored_otp = $row['delivery_otp'];

    // Compare OTPs
    if ($entered_otp !== $stored_otp) {
        echo json_encode(["status" => "error", "message" => "Invalid delivery OTP"]);
        exit;
    }

    // ✅ OTP verified — mark as delivered
    $sql = "UPDATE orders SET status=?, delivered_at=NOW() WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $order_id);

} else {
    // For other statuses (no OTP check)
    $sql = "UPDATE orders SET status=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $order_id);
}

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Order status updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update order"]);
}

$stmt->close();
if (isset($otp_check)) $otp_check->close();
?>