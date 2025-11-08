<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if (isset($_POST['medicine_id'])) {
    $medicine_id = intval($_POST['medicine_id']);
    
    // Get image path before deletion
    $select_stmt = $conn->prepare("SELECT image_path FROM medicines WHERE medicine_id = ?");
    $select_stmt->bind_param("i", $medicine_id);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $medicine = $result->fetch_assoc();
        $image_path = "../" . $medicine['image_path'];
    }
    
    // Delete medicine
    $stmt = $conn->prepare("DELETE FROM medicines WHERE medicine_id = ?");
    $stmt->bind_param("i", $medicine_id);
    
    if ($stmt->execute()) {
        // Delete image file if exists
        if (!empty($image_path) && file_exists($image_path)) {
            unlink($image_path);
        }
        echo json_encode(["status" => "success", "message" => "Medicine deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete medicine"]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing medicine_id"]);
}
?>