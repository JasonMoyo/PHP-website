<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

$response = [];

// Get customer ID from request
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid customer ID"]);
    exit;
}

// Verify customer exists
$customer_check = $conn->prepare("SELECT user_id, first_name, last_name FROM users WHERE user_id = ? AND role = 'customer'");
$customer_check->bind_param("i", $user_id);
$customer_check->execute();
$customer_result = $customer_check->get_result();

if ($customer_result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Customer not found"]);
    exit;
}

$customer = $customer_result->fetch_assoc();
$customer_check->close();

// Fetch customer addresses
$sql = "
    SELECT 
        address_id,
        full_address,
        city,
        state,
        pincode,
        landmark,
        is_default,
        created_at
    FROM addresses 
    WHERE user_id = ?
    ORDER BY is_default DESC, created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$addresses = [];
while ($row = $result->fetch_assoc()) {
    $addresses[] = $row;
}

$response = [
    "status" => "success",
    "customer" => [
        "user_id" => $customer['user_id'],
        "name" => $customer['first_name'] . ' ' . $customer['last_name']
    ],
    "addresses" => $addresses,
    "count" => count($addresses)
];

echo json_encode($response);

$stmt->close();
$conn->close();
?>