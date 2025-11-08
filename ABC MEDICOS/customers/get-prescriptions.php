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

$sql = "SELECT p.prescription_id, p.medicine_id, p.image_path, p.status, p.notes, p.created_at, 
               m.name as medicine_name
        FROM prescriptions p
        LEFT JOIN medicines m ON p.medicine_id = m.medicine_id
        WHERE p.user_id = ? 
        ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$prescriptions = [];
$base_url = "http://localhost/phpproj/ABC%20MEDICOS/";

while ($row = $result->fetch_assoc()) {
    // Add full image path
    $row['image_path'] = $base_url . $row['image_path'];
    $row['medicine_name'] = $row['medicine_name'] ?: 'General Prescription';
    $prescriptions[] = $row;
}

echo json_encode(['status' => 'success', 'prescriptions' => $prescriptions]);

$stmt->close();
$conn->close();
?>