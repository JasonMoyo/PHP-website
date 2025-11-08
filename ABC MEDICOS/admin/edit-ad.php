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
    $link = $_POST['link'] ?? null;
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    // Handle image (optional)
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $targetDir = "../../uploads/ads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($_FILES['image']['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            $response = ["status" => "error", "message" => "Invalid file type. Only JPG, PNG, GIF allowed."];
            echo json_encode($response);
            exit;
        }

        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $imagePath = "uploads/ads/" . $fileName;
            
            // Delete old image
            $old_img_stmt = $conn->prepare("SELECT image_path FROM ads WHERE ad_id = ?");
            $old_img_stmt->bind_param("i", $ad_id);
            $old_img_stmt->execute();
            $old_img_result = $old_img_stmt->get_result();
            if ($old_img_result->num_rows > 0) {
                $old_ad = $old_img_result->fetch_assoc();
                $old_image_path = "../../" . $old_ad['image_path'];
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
            $old_img_stmt->close();
        }
    }

    if ($imagePath) {
        $sql = "UPDATE ads SET image_path = ?, link = ?, is_active = ? WHERE ad_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $imagePath, $link, $is_active, $ad_id);
    } else {
        $sql = "UPDATE ads SET link = ?, is_active = ? WHERE ad_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $link, $is_active, $ad_id);
    }

    if ($stmt->execute()) {
        $response = ["status" => "success", "message" => "Ad updated successfully"];
    } else {
        $response = ["status" => "error", "message" => "Failed to update ad"];
    }
    $stmt->close();
} else {
    $response = ["status" => "error", "message" => "Missing ad_id"];
}

echo json_encode($response);
?>