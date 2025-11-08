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
$new_email = $_POST['new_email'] ?? '';

if ($new_email === '') {
    echo json_encode(['status' => 'error', 'message' => 'New email required']);
    exit;
}

// Validate email
if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
    exit;
}

// Check if email already exists
$check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$check_stmt->bind_param("si", $new_email, $user_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
    exit;
}
$check_stmt->close();

// Get old email
$stmt = $conn->prepare("SELECT email FROM users WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$old_email = $user['email'];

$otp = rand(100000, 999999);
$_SESSION['otp_email'] = $otp;
$_SESSION['new_email'] = $new_email;

// In real case, send email via PHPMailer
echo json_encode([
    'status' => 'success',
    'message' => "OTP sent to old email: $old_email",
    'otp_demo' => $otp // Remove in production
]);

$stmt->close();
$conn->close();
?>