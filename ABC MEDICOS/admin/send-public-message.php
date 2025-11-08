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

if (!isset($_POST['subject']) || !isset($_POST['message'])) {
    echo json_encode(["status" => "error", "message" => "Missing subject or message"]);
    exit;
}

$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

// Save message to database
$stmt = $conn->prepare("INSERT INTO messages (subject, message, is_public) VALUES (?, ?, 1)");
$stmt->bind_param("ss", $subject, $message);
$stmt->execute();

// Fetch all customer emails
$result = $conn->query("SELECT email, first_name, last_name FROM users WHERE role='customer' AND email IS NOT NULL AND email != ''");
$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

$success_count = 0;
$error_count = 0;
$errors = [];

// Send to each customer individually for personalization
foreach ($customers as $customer) {
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
        $mail->addAddress($customer['email'], $customer['first_name'] . ' ' . $customer['last_name']);

        // Personalized content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $personalized_message = "Dear {$customer['first_name']},<br><br>" . nl2br($message);
        $mail->Body = $personalized_message;
        $mail->AltBody = strip_tags($personalized_message);

        if ($mail->send()) {
            $success_count++;
        } else {
            $error_count++;
            $errors[] = "Failed to send to: " . $customer['email'];
        }
    } catch (Exception $e) {
        $error_count++;
        $errors[] = "Error sending to " . $customer['email'] . ": " . $mail->ErrorInfo;
    }
}

if ($error_count === 0) {
    echo json_encode([
        "status" => "success",
        "message" => "Public message sent successfully to $success_count customers."
    ]);
} else if ($success_count > 0) {
    echo json_encode([
        "status" => "partial",
        "message" => "Message sent to $success_count customers, but failed for $error_count customers.",
        "failed_count" => $error_count
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to send message to any customers.",
        "errors" => $errors
    ]);
}

$stmt->close();
$conn->close();
?>