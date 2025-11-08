<?php
require_once("../config/cors-headers.php");
header("Content-Type: application/json");
require_once("../config/db_connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// Handle both JSON, form data, and multipart form data
$data = $_POST;

// If no POST data, try to read JSON input
if (empty($data)) {
    $input = file_get_contents("php://input");
    if (!empty($input)) {
        $data = json_decode($input, true);
    }
}

// Debug logging - see what we're receiving
error_log("Received data: " . print_r($data, true));

$full_address = trim($data['full_address'] ?? '');
$city = trim($data['city'] ?? '');
$state = trim($data['state'] ?? '');
$pincode = trim($data['pincode'] ?? '');
$landmark = trim($data['landmark'] ?? '');
$is_default = isset($data['is_default']) ? intval($data['is_default']) : 0;
$user_id = $_SESSION['user_id'];

// Debug logging
error_log("Address data received - full_address: $full_address, city: $city, state: $state, pincode: $pincode");

// Input validation
if ($full_address === '' || $city === '' || $state === '' || $pincode === '') {
    echo json_encode([
        'status' => 'error', 
        'message' => 'All required fields must be filled'
    ]);
    exit;
}

// Validate field lengths
if (strlen($full_address) > 255) {
    echo json_encode(['status' => 'error', 'message' => 'Address too long (max 255 characters)']);
    exit;
}

if (strlen($city) > 100) {
    echo json_encode(['status' => 'error', 'message' => 'City name too long (max 100 characters)']);
    exit;
}

if (strlen($state) > 50) {
    echo json_encode(['status' => 'error', 'message' => 'State name too long (max 50 characters)']);
    exit;
}

if (strlen($landmark) > 100) {
    echo json_encode(['status' => 'error', 'message' => 'Landmark too long (max 100 characters)']);
    exit;
}

// Validate pincode
if (!preg_match('/^[1-9][0-9]{5}$/', $pincode)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid pincode format']);
    exit;
}

// Check address limit (e.g., max 10 addresses per user)
$count_sql = "SELECT COUNT(*) as address_count FROM addresses WHERE user_id = ?";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result()->fetch_assoc();

if ($count_result['address_count'] >= 10) {
    echo json_encode(['status' => 'error', 'message' => 'Maximum address limit (10) reached']);
    exit;
}
$count_stmt->close();

// Begin transaction
$conn->begin_transaction();

try {
    // If user marks this as default, unset previous default - FIXED SQL INJECTION
    if ($is_default === 1) {
        $update_default = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
        $update_default->bind_param("i", $user_id);
        if (!$update_default->execute()) {
            throw new Exception('Failed to update default addresses');
        }
        $update_default->close();
    }

    $sql = "INSERT INTO addresses (user_id, full_address, city, state, pincode, landmark, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssi", $user_id, $full_address, $city, $state, $pincode, $landmark, $is_default);

    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Address added successfully', 
            'address_id' => $stmt->insert_id
        ]);
    } else {
        throw new Exception('Failed to add address: ' . $stmt->error);
    }

    $stmt->close();
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>