<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Fetch all messages that came from customers
// (i.e., messages with user_id NOT NULL and admin_id IS NULL)
$sql = "
    SELECT 
        m.id AS message_id,
        m.subject,
        m.message,
        m.is_public,
        m.user_id,
        m.created_at,
        u.first_name,
        u.last_name,
        u.email,
        u.mobile_no,
        u.address,
        u.gender
    FROM messages m
    INNER JOIN users u ON m.user_id = u.user_id
    WHERE m.user_id IS NOT NULL AND m.admin_id IS NULL
    ORDER BY m.created_at DESC
";

$result = $conn->query($sql);

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $messages
]);
?>