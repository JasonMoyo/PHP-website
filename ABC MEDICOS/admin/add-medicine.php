<?php
require_once("../config/cors-headers.php");
require_once("../config/env-loader.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$response = [];

if (
    isset($_POST['name'], $_POST['generic_name'], $_POST['category'], 
    $_POST['price'], $_POST['stock_quantity'], $_POST['manufacturer'])
) {
    $name = trim($_POST['name']);
    $generic_name = trim($_POST['generic_name']);
    $category = trim($_POST['category']);
    $manufacturer = trim($_POST['manufacturer']);
    $description = $_POST['description'] ?? '';
    $composition = $_POST['composition'] ?? '';
    $uses = $_POST['uses'] ?? '';
    $side_effects = $_POST['side_effects'] ?? '';
    $precautions = $_POST['precautions'] ?? '';
    $price = floatval($_POST['price']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $min_stock_level = intval($_POST['min_stock_level'] ?? 10);
    $requires_prescription = isset($_POST['requires_prescription']) ? 1 : 0;
    $expiry_date = $_POST['expiry_date'] ?? null;

    // Validate price and stock
    if ($price <= 0) {
        echo json_encode(["status" => "error", "message" => "Price must be greater than 0"]);
        exit;
    }

    if ($stock_quantity < 0) {
        echo json_encode(["status" => "error", "message" => "Stock quantity cannot be negative"]);
        exit;
    }

    // Insert medicine
    $stmt = $conn->prepare("INSERT INTO medicines (name, generic_name, category, manufacturer, description, composition, uses, side_effects, precautions, price, stock_quantity, min_stock_level, requires_prescription, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssdiiis", $name, $generic_name, $category, $manufacturer, $description, $composition, $uses, $side_effects, $precautions, $price, $stock_quantity, $min_stock_level, $requires_prescription, $expiry_date);
    
    if ($stmt->execute()) {
        $medicine_id = $stmt->insert_id;

        // Handle medicine image
        if (!empty($_FILES['image'])) {
            $upload_dir = "../uploads/medicines/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $fileType = mime_content_type($_FILES['image']['tmp_name']);
                
                if (in_array($fileType, $allowedTypes)) {
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
        }

        echo json_encode(["status" => "success", "message" => "Medicine added successfully", "medicine_id" => $medicine_id]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to add medicine: " . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
}

$conn->close();
?>