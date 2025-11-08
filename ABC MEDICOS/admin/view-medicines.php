<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

try {
    // Fetch medicines with search and filter
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $low_stock = $_GET['low_stock'] ?? '';
    
    $sql = "SELECT 
                medicine_id,
                name,
                generic_name,
                category,
                manufacturer,
                description,
                composition,
                uses,
                side_effects,
                precautions,
                price,
                stock_quantity,
                min_stock_level,
                requires_prescription,
                expiry_date,
                image_path,
                created_at
            FROM medicines 
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR generic_name LIKE ? OR manufacturer LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sss";
    }
    
    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    if (!empty($low_stock)) {
        $sql .= " AND stock_quantity <= min_stock_level";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $medicines = [];

    while ($row = $result->fetch_assoc()) {
        // Calculate if medicine is low stock
        $row['is_low_stock'] = $row['stock_quantity'] <= $row['min_stock_level'];
        
        // Format price
        $row['price'] = number_format($row['price'], 2, '.', '');
        
        $medicines[] = $row;
    }

    $stmt->close();
    
    echo json_encode([
        "status" => "success", 
        "count" => count($medicines),
        "medicines" => $medicines
    ]);

} catch (Exception $e) {
    error_log("Error fetching medicines: " . $e->getMessage());
    echo json_encode([
        "status" => "error", 
        "message" => "Failed to fetch medicines"
    ]);
}

$conn->close();
?>