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
    $name = trim($_POST['name'] ?? '');
    $generic_name = trim($_POST['generic_name'] ?? '');
    $category = $_POST['category'] ?? '';
    $manufacturer = $_POST['manufacturer'] ?? '';
    $description = $_POST['description'] ?? '';
    $composition = $_POST['composition'] ?? '';
    $uses = $_POST['uses'] ?? '';
    $side_effects = $_POST['side_effects'] ?? '';
    $precautions = $_POST['precautions'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $min_stock_level = intval($_POST['min_stock_level'] ?? 10);
    $requires_prescription = isset($_POST['requires_prescription']) ? 1 : 0;
    $expiry_date = $_POST['expiry_date'] ?? null;

    // Validate price
    if ($price <= 0) {
        echo json_encode(["status" => "error", "message" => "Price must be greater than 0"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE medicines SET name=?, generic_name=?, category=?, manufacturer=?, description=?, composition=?, uses=?, side_effects=?, precautions=?, price=?, stock_quantity=?, min_stock_level=?, requires_prescription=?, expiry_date=? WHERE medicine_id=?");
    $stmt->bind_param("sssssssssdiiisi", $name, $generic_name, $category, $manufacturer, $description, $composition, $uses, $side_effects, $precautions, $price, $stock_quantity, $min_stock_level, $requires_prescription, $expiry_date, $medicine_id);
    
    if ($stmt->execute()) {
        // Handle image update if new image is uploaded
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../uploads/medicines/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['image']['tmp_name']);
            
            if (in_array($fileType, $allowedTypes)) {
                // Delete old image if exists
                $old_img_stmt = $conn->prepare("SELECT image_path FROM medicines WHERE medicine_id = ?");
                $old_img_stmt->bind_param("i", $medicine_id);
                $old_img_stmt->execute();
                $old_img_result = $old_img_stmt->get_result();
                
                if ($old_img = $old_img_result->fetch_assoc() && !empty($old_img['image_path'])) {
                    $old_image_path = "../" . $old_img['image_path'];
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
                $old_img_stmt->close();
                
                // Upload new image
                $filename = time() . "_" . uniqid() . "." . pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $target = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $path = "uploads/medicines/" . $filename;
                    $img_stmt = $conn->prepare("UPDATE medicines SET image_path = ? WHERE medicine_id = ?");
                    $img_stmt->bind_param("si", $path, $medicine_id);
                    $img_stmt->execute();
                    $img_stmt->close();
                }
            }
        }

        echo json_encode(["status" => "success", "message" => "Medicine updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update medicine: " . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing medicine_id"]);
}

$conn->close();
?>