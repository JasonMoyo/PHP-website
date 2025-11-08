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

if (!isset($_SESSION['otp_email']) || $_SESSION['otp_email'] != $otp_input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid OTP']);
    exit;
}

$user_id = $_SESSION['user_id'];
$new_email = $_SESSION['new_email'];

$stmt = $conn->prepare("UPDATE users SET email=? WHERE user_id=?");
$stmt->bind_param("si", $new_email, $user_id);

if ($stmt->execute()) {
    unset($_SESSION['otp_email'], $_SESSION['new_email']);
    echo json_encode(['status' => 'success', 'message' => 'Email updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();
?>