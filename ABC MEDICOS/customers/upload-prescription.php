<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get medicine_id if provided (for product-specific prescriptions)
$medicine_id = isset($_POST['medicine_id']) ? (int)$_POST['medicine_id'] : null;

// Check if file was uploaded
if (!isset($_FILES['prescription_image']) || $_FILES['prescription_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'Please upload a prescription image']);
    exit;
}

// File upload setup
$targetDir = "../uploads/prescriptions/";
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0777, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create upload directory']);
        exit;
    }
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
$fileType = mime_content_type($_FILES['prescription_image']['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, GIF, PDF allowed.']);
    exit;
}

// Generate unique filename
$fileExtension = pathinfo($_FILES['prescription_image']['name'], PATHINFO_EXTENSION);
$fileName = time() . "_" . uniqid() . "." . $fileExtension;
$targetFile = $targetDir . $fileName;

if (move_uploaded_file($_FILES['prescription_image']['tmp_name'], $targetFile)) {
    $imagePath = "uploads/prescriptions/" . $fileName;

    // Insert prescription record with optional medicine_id
    $stmt = $conn->prepare("INSERT INTO prescriptions (user_id, medicine_id, image_path, status) VALUES (?, ?, ?, 'Pending')");
    $stmt->bind_param("iis", $user_id, $medicine_id, $imagePath);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Prescription uploaded successfully',
            'prescription_id' => $stmt->insert_id,
            'medicine_id' => $medicine_id
        ]);
    } else {
        // Delete the uploaded file if database insert fails
        unlink($targetFile);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save prescription']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to upload prescription']);
}

$conn->close();
?>