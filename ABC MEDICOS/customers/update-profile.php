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
$data = json_decode(file_get_contents("php://input"), true);

$first = trim($data['first_name'] ?? '');
$last = trim($data['last_name'] ?? '');
$gender = trim($data['gender'] ?? '');
$address = trim($data['address'] ?? '');

if ($first === '' || $last === '' || $gender === '') {
    echo json_encode(['status' => 'error', 'message' => 'All fields required']);
    exit;
}

// Validate gender
$valid_genders = ['male', 'female', 'other'];
if (!in_array($gender, $valid_genders)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid gender value']);
    exit;
}

$sql = "UPDATE users SET first_name=?, last_name=?, gender=?, address=? WHERE user_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $first, $last, $gender, $address, $user_id);

if ($stmt->execute()) {
    // Update session name
    $_SESSION['name'] = $first . " " . $last;
    
    echo json_encode(['status' => 'success', 'message' => 'Profile updated']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();
?>