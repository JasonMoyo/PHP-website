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
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

$address_id = isset($data['address_id']) ? intval($data['address_id']) : 0;
$full_address = trim($data['full_address'] ?? '');
$city = trim($data['city'] ?? '');
$state = trim($data['state'] ?? '');
$pincode = trim($data['pincode'] ?? '');
$landmark = trim($data['landmark'] ?? '');
$is_default = isset($data['is_default']) ? intval($data['is_default']) : 0;
$user_id = $_SESSION['user_id'];

// Debug logging
error_log("Update address data - address_id: $address_id, full_address: $full_address, city: $city, state: $state, pincode: $pincode, is_default: $is_default");

// Input validation
if ($address_id <= 0 || $full_address === '' || $city === '' || $state === '' || $pincode === '') {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid input - all required fields must be filled'
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

// Begin transaction
$conn->begin_transaction();

try {
    // Verify ownership
    $check = $conn->prepare("SELECT address_id FROM addresses WHERE address_id=? AND user_id=?");
    $check->bind_param("ii", $address_id, $user_id);
    $check->execute();
    $res = $check->get_result();
    
    if ($res->num_rows === 0) {
        throw new Exception('Address not found');
    }
    $check->close();

    // If marked default, reset previous default with error handling
    if ($is_default === 1) {
        $reset_stmt = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ? AND address_id != ?");
        $reset_stmt->bind_param("ii", $user_id, $address_id);
        if (!$reset_stmt->execute()) {
            throw new Exception('Failed to reset default addresses');
        }
        $reset_stmt->close();
    }

    $sql = "UPDATE addresses 
            SET full_address=?, city=?, state=?, pincode=?, landmark=?, is_default=? 
            WHERE address_id=? AND user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssiii", $full_address, $city, $state, $pincode, $landmark, $is_default, $address_id, $user_id);

    if ($stmt->execute()) {
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Address updated successfully']);
    } else {
        throw new Exception('Update failed: ' . $stmt->error);
    }

    $stmt->close();
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Update address error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>