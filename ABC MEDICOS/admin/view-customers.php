<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

$sql = "SELECT user_id, first_name, last_name, mobile_no, email, address, gender, created_at 
        FROM users WHERE role = 'customer' ORDER BY created_at DESC";
$result = $conn->query($sql);

$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $customers
]);
?>