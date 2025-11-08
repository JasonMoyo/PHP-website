<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php"); // include DB connection

$response = [];

// ✅ Step 1: Check if mobile_no and password are provided
if (isset($_POST['mobile_no']) && isset($_POST['password'])) {
    $mobile_no = trim($_POST['mobile_no']);
    $password = $_POST['password'];

    // ✅ Step 2: Find admin by mobile_no
    $sql = "SELECT * FROM users WHERE mobile_no = ? AND role = 'admin' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $mobile_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();

        // ✅ Step 3: Verify password
        if (password_verify($password, $admin['password'])) {

            // ✅ Step 4: Generate OTP (6 digits)
            $otp = rand(100000, 999999);
            $expires_at = date("Y-m-d H:i:s", strtotime("+5 minutes"));
            error_log("Generated OTP for $mobile_no: $otp"); // Log OTP for testing

            // ✅ Step 5: Remove any previous OTPs for this mobile number
            $delete_sql = "DELETE FROM otps WHERE mobile_no = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("s", $mobile_no);
            $delete_stmt->execute();

            // ✅ Step 6: Store new OTP
            $insert_sql = "INSERT INTO otps (user_id, mobile_no, otp_code, expires_at) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("isss", $admin['user_id'], $mobile_no, $otp, $expires_at);
            $insert_stmt->execute();

            // ✅ Step 7: (Simulate) Send OTP via SMS (for now, just return in response)
            // In real app: use an API like Fast2SMS, Twilio, etc.
            $response = [
                "status" => "success",
                "message" => "OTP sent successfully (for testing, OTP = $otp)",
                "mobile_no" => $mobile_no
            ];
        } else {
            $response = ["status" => "error", "message" => "Invalid password"];
        }
    } else {
        $response = ["status" => "error", "message" => "Admin not found or not authorized"];
    }

} else {
    $response = ["status" => "error", "message" => "Missing required fields"];
}

// ✅ Output response as JSON
echo json_encode($response);
?>