<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

// Fetch active ads
$sql = "SELECT ad_id, image_path, link 
        FROM ads 
        WHERE is_active = 1 
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Database query failed']);
    exit;
}

$ads = [];
while ($row = $result->fetch_assoc()) {
    // Add full image path
    $row['image_path'] = "http://localhost/phpproj/ABC%20MEDICOS/" . $row['image_path'];
    $ads[] = $row;
}

echo json_encode(['status' => 'success', 'ads' => $ads]);

$conn->close();
?>