<?php
require_once("../config/cors-headers.php");
header("Content-Type: application/json");
require_once("../config/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$new_mobile = $_POST['new_mobile'] ?? '';

if ($new_mobile === '') {
    echo json_encode(['status' => 'error', 'message' => 'New mobile required']);
    exit;
}

// Validate mobile
if (!preg_match('/^[0-9]{10}$/', $new_mobile)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid mobile number format']);
    exit;
}

// Check if mobile already exists
$check_stmt = $conn->prepare("SELECT user_id FROM users WHERE mobile_no = ? AND user_id != ?");
$check_stmt->bind_param("si", $new_mobile, $user_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Mobile number already registered']);
    exit;
}
$check_stmt->close();

// Get old mobile
$stmt = $conn->prepare("SELECT mobile_no FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$old_mobile = $user['mobile_no'];

$otp = rand(100000, 999999);
$_SESSION['otp_mobile'] = $otp;
$_SESSION['new_mobile'] = $new_mobile;

// (In real app, integrate SMS API here)
echo json_encode([
    'status' => 'success',
    'message' => "OTP sent to old mobile: $old_mobile",
    'otp_demo' => $otp // 🔧 For testing only
]);

$stmt->close();
$conn->close();
?>