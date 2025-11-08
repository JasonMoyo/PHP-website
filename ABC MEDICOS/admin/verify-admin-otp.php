<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start(); // Always start before session usage

$response = array();

// ✅ Step 1: Validate input
if (!empty($_POST['mobile_no']) && !empty($_POST['otp'])) {
    $mobile_no = trim($_POST['mobile_no']);
    $otp = trim($_POST['otp']);

    // ✅ Step 2: Fetch OTP record
    $sql = "SELECT * FROM otps WHERE mobile_no = ? AND otp_code = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $mobile_no, $otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $current_time = date("Y-m-d H:i:s");

        // ✅ Step 3: Check if OTP expired
        if ($current_time <= $row['expires_at']) {

            // ✅ Step 4: OTP valid → Delete it after use
            $delete_otp = $conn->prepare("DELETE FROM otps WHERE mobile_no = ?");
            $delete_otp->bind_param("s", $mobile_no);
            $delete_otp->execute();

            // ✅ Step 5: Get admin details
            $sql_user = "SELECT * FROM users WHERE mobile_no = ? AND role = 'admin' LIMIT 1";
            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->bind_param("s", $mobile_no);
            $stmt_user->execute();
            $admin_result = $stmt_user->get_result();

            if ($admin_result->num_rows === 1) {
                $admin = $admin_result->fetch_assoc();

                // ✅ Step 6: Start session
                $_SESSION['admin_id'] = $admin['user_id'];
                $_SESSION['role'] = 'admin';
                $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                $_SESSION['admin_mobile'] = $admin['mobile_no'];

                $response = [
                    "status" => "success",
                    "message" => "OTP verified successfully",
                    "admin" => [
                        "user_id" => $admin['user_id'],
                        "first_name" => $admin['first_name'],
                        "last_name" => $admin['last_name'],
                        "mobile_no" => $admin['mobile_no']
                    ]
                ];
            } else {
                $response = ["status" => "error", "message" => "Admin not found"];
            }

        } else {
            $response = ["status" => "error", "message" => "OTP expired"];
        }
    } else {
        $response = ["status" => "error", "message" => "Invalid OTP"];
    }
} else {
    $response = ["status" => "error", "message" => "Missing required fields"];
}

echo json_encode($response);
?>