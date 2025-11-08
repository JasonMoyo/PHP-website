<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if (!isset($_POST['prescription_id']) || !isset($_POST['status'])) {
    echo json_encode(["status" => "error", "message" => "Missing prescription_id or status"]);
    exit;
}

$prescription_id = intval($_POST['prescription_id']);
$status = trim($_POST['status']);
$notes = $_POST['notes'] ?? '';

// Allowed status values
$allowed_statuses = ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(["status" => "error", "message" => "Invalid status"]);
    exit;
}

$stmt = $conn->prepare("UPDATE prescriptions SET status = ?, notes = ? WHERE prescription_id = ?");
$stmt->bind_param("ssi", $status, $notes, $prescription_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Prescription status updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update prescription status"]);
}

$stmt->close();
$conn->close();
?>