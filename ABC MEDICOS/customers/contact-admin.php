<?php
require_once("../config/cors-headers.php");
session_start();
header('Content-Type: application/json');
require_once("../config/db_connect.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Handle both POST and JSON input
$input_data = $_POST;
if (empty($input_data)) {
    $input_data = json_decode(file_get_contents("php://input"), true) ?? [];
}

$user_id = $_SESSION['user_id'];
$message = trim($input_data['message'] ?? '');
$subject = trim($input_data['subject'] ?? 'General Inquiry');

// Input validation
if ($message === '') {
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit;
}

// Validate message length
if (strlen($message) > 1000) {
    echo json_encode(['status' => 'error', 'message' => 'Message too long (max 1000 characters)']);
    exit;
}

// Validate subject length
if (strlen($subject) > 255) {
    echo json_encode(['status' => 'error', 'message' => 'Subject too long (max 255 characters)']);
    exit;
}

// Sanitize inputs
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');

try {
    // Insert message into database
    $sql = "INSERT INTO messages (user_id, subject, message, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $subject, $message);

    if ($stmt->execute()) {
        error_log("Message sent successfully by user $user_id");
        echo json_encode(['status' => 'success', 'message' => 'Message sent successfully']);
    } else {
        error_log("Failed to send message: " . $stmt->error);
        echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Contact form error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'System error occurred']);
}

$conn->close();
?>