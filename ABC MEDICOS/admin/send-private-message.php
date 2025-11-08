<?php
require_once("../config/cors-headers.php");
require_once("../config/env-loader.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

// PHPMailer imports at the top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require dirname(__DIR__) . '../vendor/autoload.php';

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

if (isset($_POST['user_id'], $_POST['subject'], $_POST['message'])) {
    $admin_id = $_SESSION['admin_id'];
    $user_id  = intval($_POST['user_id']);
    $subject  = trim($_POST['subject']);
    $message  = trim($_POST['message']);

    // Fetch recipient details
    $stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit;
    }

    $user = $result->fetch_assoc();
    $email = $user['email'];
    $user_name = $user['first_name'] . ' ' . $user['last_name'];

    if (empty($email)) {
        echo json_encode(["status" => "error", "message" => "User does not have an email address"]);
        exit;
    }

    // Save message to DB
    $insert = $conn->prepare("INSERT INTO messages (admin_id, user_id, subject, message, is_public) VALUES (?, ?, ?, ?, 0)");
    $insert->bind_param("iiss", $admin_id, $user_id, $subject, $message);
    
    if ($insert->execute()) {
        // Email sending with PHPMailer
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'];
            $mail->Password   = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $_ENV['SMTP_PORT'];

            // Recipients
            $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
            $mail->addAddress($email, $user_name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = nl2br($message);
            $mail->AltBody = strip_tags($message);

            $mail->send();
            echo json_encode(["status" => "success", "message" => "Message sent successfully to $user_name"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Message saved but email sending failed: " . $mail->ErrorInfo]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to save message"]);
    }
    
    $insert->close();
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
}

$conn->close();
?>