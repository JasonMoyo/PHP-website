<?php
require_once("../config/cors-headers.php");
require_once("../config/env-loader.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

$response = [];

if (isset($_FILES['image']) && isset($_POST['link'])) {
    $link = trim($_POST['link']);

    // Check if file was uploaded without errors
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize directive in php.ini",
            UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE directive in HTML form",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
            UPLOAD_ERR_NO_FILE => "No file was uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload"
        ];
        
        $errorMessage = $errorMessages[$_FILES['image']['error']] ?? "Unknown upload error";
        $response = ["status" => "error", "message" => "Upload error: " . $errorMessage];
        echo json_encode($response);
        exit;
    }

    // File upload setup
    $targetDir = "../uploads/ads/";
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            $response = ["status" => "error", "message" => "Failed to create upload directory"];
            echo json_encode($response);
            exit;
        }
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $fileType = mime_content_type($_FILES['image']['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        $response = ["status" => "error", "message" => "Invalid file type. Only JPG, PNG, GIF allowed."];
        echo json_encode($response);
        exit;
    }

    // Generate unique filename
    $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $fileName = time() . "_" . uniqid() . "." . $fileExtension;
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        $imagePath = "uploads/ads/" . $fileName;

        $stmt = $conn->prepare("INSERT INTO ads (image_path, link) VALUES (?, ?)");
        $stmt->bind_param("ss", $imagePath, $link);
        
        if ($stmt->execute()) {
            $response = ["status" => "success", "message" => "Ad posted successfully"];
        } else {
            // Delete the uploaded file if database insert fails
            unlink($targetFile);
            $response = ["status" => "error", "message" => "Database error: " . $stmt->error];
        }
        $stmt->close();
    } else {
        $response = ["status" => "error", "message" => "Failed to move uploaded file"];
    }
} else {
    $response = ["status" => "error", "message" => "Missing image or link"];
}

echo json_encode($response);
?>