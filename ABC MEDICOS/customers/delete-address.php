<?php
require_once("../config/cors-headers.php");
header("Content-Type: application/json");
require_once("../config/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$address_id = intval($_POST['address_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($address_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid address']);
    exit;
}

// Verify address belongs to the user
$stmt = $conn->prepare("SELECT address_id FROM addresses WHERE address_id=? AND user_id=?");
$stmt->bind_param("ii", $address_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Address not found']);
    exit;
}

// Delete it
$delete = $conn->prepare("DELETE FROM addresses WHERE address_id=? AND user_id=?");
$delete->bind_param("ii", $address_id, $user_id);

if ($delete->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Address deleted']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Deletion failed']);
}

$stmt->close();
$delete->close();
$conn->close();
?>