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

$sql = "SELECT prescription_id, medicine_id, image_path, created_at 
        FROM prescriptions 
        WHERE user_id = ? AND status = 'Approved'
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$prescriptions = [];
$base_url = "http://localhost/phpproj/ABC%20MEDICOS/";

while ($row = $result->fetch_assoc()) {
    // Add full image path
    $row['image_path'] = $base_url . $row['image_path'];
    $prescriptions[] = $row;
}

echo json_encode([
    'status' => 'success', 
    'prescriptions' => $prescriptions,
    'count' => count($prescriptions)
]);

$stmt->close();
$conn->close();
?>