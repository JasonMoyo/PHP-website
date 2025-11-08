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
    $user_id   = intval($_POST['user_id']);
    $first_name = $_POST['first_name'] ?? null;
    $last_name  = $_POST['last_name'] ?? null;
    $mobile_no  = $_POST['mobile_no'] ?? null;
    $email      = $_POST['email'] ?? null;
    $address    = $_POST['address'] ?? null;
    $gender     = $_POST['gender'] ?? null;

    // Validate mobile number
    if ($mobile_no && !preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $response = ["status" => "error", "message" => "Invalid mobile number"];
        echo json_encode($response);
        exit;
    }

    $sql = "UPDATE users SET 
                first_name = ?, 
                last_name = ?, 
                mobile_no = ?, 
                email = ?, 
                address = ?, 
                gender = ? 
            WHERE user_id = ? AND role = 'customer'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $first_name, $last_name, $mobile_no, $email, $address, $gender, $user_id);

    if ($stmt->execute()) {
        $response = ["status" => "success", "message" => "Customer updated successfully"];
    } else {
        $response = ["status" => "error", "message" => "Failed to update customer"];
    }
    $stmt->close();
} else {
    $response = ["status" => "error", "message" => "Missing user_id"];
}

echo json_encode($response);
?>