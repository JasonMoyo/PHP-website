<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

if (isset($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (empty($message)) {
        echo json_encode(["status" => "error", "message" => "Message cannot be empty"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO announcements (message) VALUES (?)");
    $stmt->bind_param("s", $message);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Announcement added successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to add announcement"]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing message"]);
}
?>