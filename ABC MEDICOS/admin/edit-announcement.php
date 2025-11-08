<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

if (isset($_POST['announcement_id']) && isset($_POST['message'])) {
    $announcement_id = intval($_POST['announcement_id']);
    $message = trim($_POST['message']);
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    $stmt = $conn->prepare("UPDATE announcements SET message = ?, is_active = ? WHERE announcement_id = ?");
    $stmt->bind_param("sii", $message, $is_active, $announcement_id);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Announcement updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update announcement"]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
}
?>