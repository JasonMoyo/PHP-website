<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

if (isset($_POST['announcement_id'])) {
    $announcement_id = intval($_POST['announcement_id']);

    $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param("i", $announcement_id);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Announcement deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete announcement"]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing announcement_id"]);
}
?>