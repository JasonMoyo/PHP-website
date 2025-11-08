<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

// Fetch active announcements
$sql = "SELECT announcement_id, message, created_at 
        FROM announcements 
        WHERE is_active = 1 
        ORDER BY created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Database query failed']);
    exit;
}

$announcements = [];
while ($row = $result->fetch_assoc()) {
    // Format date
    $row['formatted_date'] = date('d M Y', strtotime($row['created_at']));
    $announcements[] = $row;
}

echo json_encode([
    'status' => 'success',
    'announcements' => $announcements
]);

$conn->close();
?>