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

if (
    isset($_POST['first_name']) &&
    isset($_POST['last_name']) &&
    isset($_POST['mobile_no']) &&
    isset($_POST['password'])
) {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $mobile_no  = trim($_POST['mobile_no']);
    $email      = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $address    = !empty($_POST['address']) ? trim($_POST['address']) : null;
    $gender     = !empty($_POST['gender']) ? trim($_POST['gender']) : null;
    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role       = 'customer';

    // Validate mobile number
    if (!preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $response = ["status" => "error", "message" => "Invalid mobile number"];
        echo json_encode($response);
        exit;
    }

    // Validate email format if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ["status" => "error", "message" => "Invalid email format"];
        echo json_encode($response);
        exit;
    }

    // Check for mobile number duplicate
    $check_mobile = $conn->prepare("SELECT user_id FROM users WHERE mobile_no = ?");
    $check_mobile->bind_param("s", $mobile_no);
    $check_mobile->execute();
    $mobile_result = $check_mobile->get_result();

    // Check for email duplicate (only if email is provided)
    $email_exists = false;
    if (!empty($email)) {
        $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $email_result = $check_email->get_result();
        $email_exists = $email_result->num_rows > 0;
        $check_email->close();
    }

    if ($mobile_result->num_rows > 0 && $email_exists) {
        $response = ["status" => "error", "message" => "Both mobile number and email address are already registered"];
    } elseif ($mobile_result->num_rows > 0) {
        $response = ["status" => "error", "message" => "Mobile number already exists"];
    } elseif ($email_exists) {
        $response = ["status" => "error", "message" => "Email address already registered"];
    } else {
        $sql = "INSERT INTO users (first_name, last_name, mobile_no, email, password, address, gender, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", $first_name, $last_name, $mobile_no, $email, $password, $address, $gender, $role);

        if ($stmt->execute()) {
            $response = ["status" => "success", "message" => "Customer added successfully"];
        } else {
            $response = ["status" => "error", "message" => "Failed to add customer: " . $stmt->error];
        }
        $stmt->close();
    }
    $check_mobile->close();
} else {
    $response = ["status" => "error", "message" => "Missing required fields"];
}

echo json_encode($response);
?>