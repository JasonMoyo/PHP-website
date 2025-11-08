<?php
require_once("../config/cors-headers.php");
header("Content-Type: application/json");
require_once("../config/db_connect.php");

$response = [];

// To this (more robust):
$input_data = $_POST;
if (empty($input_data)) {
    $input_data = json_decode(file_get_contents("php://input"), true) ?? [];
}

if (isset($input_data['mobile_no'])) {
    $mobile_no = trim($input_data['mobile_no']);

    // Validate number (10 digits)
    if (!preg_match('/^[0-9]{10}$/', $mobile_no)) {
        $response['status'] = 'error';
        $response['message'] = 'Invalid mobile number.';
        echo json_encode($response);
        exit;
    }

    // 2️⃣ — Check if user exists (customer or admin)
    $check_sql = "SELECT user_id, role, first_name, last_name FROM users WHERE mobile_no = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $mobile_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $response['status'] = 'error';
        $response['message'] = 'Mobile number not registered.';
        echo json_encode($response);
        exit;
    }

    $user = $result->fetch_assoc();
    $user_id = $user['user_id'];
    $role = $user['role'];

    // 3️⃣ — Generate a 6-digit OTP
    $otp_code = rand(100000, 999999);
    error_log("Generated OTP for $mobile_no: $otp_code");

    // 4️⃣ — Insert OTP into otps table
    $insert_sql = "INSERT INTO otps (user_id, mobile_no, otp_code, is_used) VALUES (?, ?, ?, 0)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iss", $user_id, $mobile_no, $otp_code);

    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'OTP sent successfully.';
        $response['mobile_no'] = $mobile_no;
        $response['otp'] = $otp_code; // ⚠️ REMOVE THIS in production
        $response['role'] = $role;
        $response['name'] = $user['first_name'] . ' ' . $user['last_name'];
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Failed to generate OTP.';
    }

    $stmt->close();
} else {
    $response['status'] = 'error';
    $response['message'] = 'Mobile number required.';
}

$conn->close();
echo json_encode($response);
?>