<?php
require_once("../config/cors-headers.php");
header("Content-Type: application/json");
require_once("../config/db_connect.php");

$response = [];

// Handle both POST form data and JSON input
$input_data = $_POST;
if (empty($input_data)) {
    $input_data = json_decode(file_get_contents("php://input"), true) ?? [];
}

// 1️⃣ — Check required fields
if (
    isset($input_data['first_name'], $input_data['last_name'], $input_data['mobile_no'], $input_data['address'], $input_data['gender'])
) {
    // 2️⃣ — Get and sanitize input
    $first_name = trim($input_data['first_name']);
    $last_name  = trim($input_data['last_name']);
    $mobile_no  = trim($input_data['mobile_no']);
    $email      = isset($input_data['email']) && $input_data['email'] !== '' ? trim($input_data['email']) : NULL;
    $address    = trim($input_data['address']);
    $gender     = strtolower(trim($input_data['gender']));

    // 3️⃣ — Validate mobile number (must be 10 digits)
    if (!preg_match('/^[0-9]{10}$/', $mobile_no)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid mobile number. Must be 10 digits.'
        ]);
        exit;
    }

    // 4️⃣ — Validate email format if provided
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid email format.'
        ]);
        exit;
    }

    // 5️⃣ — Validate gender
    $valid_genders = ['male', 'female', 'other'];
    if (!in_array($gender, $valid_genders)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid gender value.'
        ]);
        exit;
    }

    // 6️⃣ — Check if mobile number already exists
    $check_sql = "SELECT user_id FROM users WHERE mobile_no = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $mobile_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'Mobile number already registered.'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // 7️⃣ — Generate a temporary password
    $temp_password = substr(str_shuffle('ABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 6);
    $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

    // 8️⃣ — Insert user record
    $insert_sql = "INSERT INTO users (first_name, last_name, mobile_no, email, password, address, gender, role)
                   VALUES (?, ?, ?, ?, ?, ?, ?, 'customer')";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sssssss", $first_name, $last_name, $mobile_no, $email, $hashed_password, $address, $gender);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;

        // 9️⃣ — Generate OTP for the new user
        $otp_code = rand(100000, 999999);
        error_log("Generated OTP for $mobile_no: $otp_code");
        $otp_sql = "INSERT INTO otps (user_id, mobile_no, otp_code, is_used) VALUES (?, ?, ?, 0)";
        $otp_stmt = $conn->prepare($otp_sql);
        $otp_stmt->bind_param("iss", $user_id, $mobile_no, $otp_code);
        
        if ($otp_stmt->execute()) {
            http_response_code(201);
            $response = [
                'status' => 'success',
                'message' => 'Registration successful! OTP sent to your mobile.',
                'user_id' => $user_id,
                'mobile_no' => $mobile_no,
                'temp_password' => $temp_password, // Remove in production
                'otp_demo' => $otp_code // Remove in production - for testing only
            ];
        } else {
            // Registration successful but OTP failed - still return success but with warning
            http_response_code(201);
            $response = [
                'status' => 'success',
                'message' => 'Registration successful! Please login to receive OTP.',
                'user_id' => $user_id,
                'mobile_no' => $mobile_no,
                'temp_password' => $temp_password, // Remove in production
                'warning' => 'OTP generation failed, please login manually'
            ];
        }
        $otp_stmt->close();
    } else {
        http_response_code(500);
        $response = [
            'status' => 'error',
            'message' => 'Failed to register user. Please try again.'
        ];
    }

    $stmt->close();
} else {
    http_response_code(400);
    $response = [
        'status' => 'error',
        'message' => 'Missing required fields.'
    ];
}

$conn->close();
echo json_encode($response);
?>