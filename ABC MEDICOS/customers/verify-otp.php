<?php
require_once("../config/cors-headers.php");
session_start();
header("Content-Type: application/json");
require_once("../config/db_connect.php");

$response = [];

// Handle both POST form data and JSON input
$input_data = $_POST;
if (empty($input_data)) {
    $input_data = json_decode(file_get_contents("php://input"), true) ?? [];
}

if (isset($input_data['mobile_no']) && isset($input_data['otp_code'])) {
    $mobile_no = trim($input_data['mobile_no']);
    $otp_code  = trim($input_data['otp_code']);

    // Debug logging (remove in production)
    error_log("OTP Verification Attempt: mobile=$mobile_no, otp=$otp_code");

    // 1️⃣ — Check if OTP exists and valid (not used, created within 10 mins)
    $sql = "SELECT o.id, o.user_id, u.role, u.first_name, u.last_name 
            FROM otps o 
            JOIN users u ON o.user_id = u.user_id 
            WHERE o.mobile_no = ? AND o.otp_code = ? AND o.is_used = 0 
            AND o.created_at >= (NOW() - INTERVAL 10 MINUTE)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $mobile_no, $otp_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $data = $result->fetch_assoc();

        // 2️⃣ — Mark OTP as used
        $update_sql = "UPDATE otps SET is_used = 1 WHERE id = ?";
        $stmt2 = $conn->prepare($update_sql);
        $stmt2->bind_param("i", $data['id']);
        $stmt2->execute();

        // 3️⃣ — Create session for logged-in user
        $_SESSION['user_id'] = $data['user_id'];
        $_SESSION['role'] = $data['role'];
        $_SESSION['name'] = $data['first_name'] . " " . $data['last_name'];
        $_SESSION['mobile_no'] = $mobile_no;

        $response['status'] = 'success';
        $response['message'] = 'Login successful!';
        $response['role'] = $data['role'];
        $response['name'] = $data['first_name'] . " " . $data['last_name'];
        $response['user_id'] = $data['user_id'];
        
        error_log("OTP Verification SUCCESS: user_id=" . $data['user_id']);
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Invalid or expired OTP.';
        error_log("OTP Verification FAILED: Invalid OTP for mobile=$mobile_no");
    }
    
    $stmt->close();
    if (isset($stmt2)) $stmt2->close();
} else {
    $response['status'] = 'error';
    $response['message'] = 'Mobile number and OTP required.';
    error_log("OTP Verification MISSING DATA: mobile_no=" . (isset($input_data['mobile_no']) ? 'set' : 'not set') . ", otp_code=" . (isset($input_data['otp_code']) ? 'set' : 'not set'));
}

$conn->close();
echo json_encode($response);
?>