<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

$response = [];

if (isset($_POST['ad_id'])) {
    $ad_id = intval($_POST['ad_id']);

    // First get the image path to delete the file
    $select_stmt = $conn->prepare("SELECT image_path FROM ads WHERE ad_id = ?");
    $select_stmt->bind_param("i", $ad_id);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ad = $result->fetch_assoc();
        $image_path = "../../" . $ad['image_path'];
        
        // Delete the ad from database
        $stmt = $conn->prepare("DELETE FROM ads WHERE ad_id = ?");
        $stmt->bind_param("i", $ad_id);
        
        if ($stmt->execute()) {
            // Delete the image file
            if (file_exists($image_path)) {
                unlink($image_path);
            }
            $response = ["status" => "success", "message" => "Ad deleted successfully"];
        } else {
            $response = ["status" => "error", "message" => "Failed to delete ad"];
        }
        $stmt->close();
    } else {
        $response = ["status" => "error", "message" => "Ad not found"];
    }
    $select_stmt->close();
} else {
    $response = ["status" => "error", "message" => "Missing ad_id"];
}

echo json_encode($response);
?>