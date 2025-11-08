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

if (isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);

    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = 'customer'");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $response = ["status" => "success", "message" => "Customer deleted successfully"];
    } else {
        $response = ["status" => "error", "message" => "Failed to delete customer"];
    }
    $stmt->close();
} else {
    $response = ["status" => "error", "message" => "Missing user_id"];
}

echo json_encode($response);
?>