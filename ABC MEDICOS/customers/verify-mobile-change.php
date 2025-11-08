<?php
require_once("../config/cors-headers.php");
header("Content-Type: application/json");
require_once("../config/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$otp_input = $_POST['otp'] ?? '';

if (!isset($_SESSION['otp_mobile']) || $_SESSION['otp_mobile'] != $otp_input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid OTP']);
    exit;
}

$user_id = $_SESSION['user_id'];
$new_mobile = $_SESSION['new_mobile'];

// Update mobile
$stmt = $conn->prepare("UPDATE users SET mobile_no=? WHERE user_id=?");
$stmt->bind_param("si", $new_mobile, $user_id);

if ($stmt->execute()) {
    unset($_SESSION['otp_mobile'], $_SESSION['new_mobile']);
    $_SESSION['mobile_no'] = $new_mobile;
    echo json_encode(['status' => 'success', 'message' => 'Mobile number updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();
?>