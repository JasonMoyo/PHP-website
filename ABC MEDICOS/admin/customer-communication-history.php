<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get customer ID from request
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid customer ID"]);
    exit;
}

// Verify customer exists
$customer_check = $conn->prepare("SELECT user_id, first_name, last_name, mobile_no, email FROM users WHERE user_id = ? AND role = 'customer'");
$customer_check->bind_param("i", $user_id);
$customer_check->execute();
$customer_result = $customer_check->get_result();

if ($customer_result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Customer not found"]);
    exit;
}

$customer = $customer_result->fetch_assoc();
$customer_check->close();

// Fetch communication history
$sql = "
    SELECT 
        m.id AS message_id,
        m.subject,
        m.message,
        m.is_public,
        m.admin_id,
        m.user_id,
        m.created_at,
        a.first_name AS admin_first_name,
        a.last_name AS admin_last_name,
        CASE 
            WHEN m.admin_id IS NOT NULL THEN 'Admin to Customer'
            WHEN m.user_id IS NOT NULL AND m.admin_id IS NULL THEN 'Customer to Admin'
            ELSE 'System'
        END AS direction
    FROM messages m
    LEFT JOIN users a ON m.admin_id = a.user_id
    WHERE m.user_id = ? OR (m.is_public = 1 AND m.admin_id IS NOT NULL)
    ORDER BY m.created_at DESC
    LIMIT 100
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$communications = [];
$summary = [
    'total_messages' => 0,
    'customer_messages' => 0,
    'admin_messages' => 0,
    'public_messages' => 0,
    'first_contact' => null,
    'last_contact' => null
];

while ($row = $result->fetch_assoc()) {
    $sender = '';
    $message_type = '';
    
    if ($row['direction'] === 'Admin to Customer') {
        $sender = 'Admin: ' . $row['admin_first_name'] . ' ' . $row['admin_last_name'];
        $message_type = 'outgoing';
        $summary['admin_messages']++;
    } else if ($row['direction'] === 'Customer to Admin') {
        $sender = 'Customer: ' . $customer['first_name'] . ' ' . $customer['last_name'];
        $message_type = 'incoming';
        $summary['customer_messages']++;
    } else {
        $sender = 'System';
        $message_type = 'system';
    }
    
    if ($row['is_public']) {
        $summary['public_messages']++;
    }
    
    $communications[] = [
        'message_id' => $row['message_id'],
        'subject' => $row['subject'],
        'message' => $row['message'],
        'sender' => $sender,
        'direction' => $row['direction'],
        'message_type' => $message_type,
        'is_public' => (bool)$row['is_public'],
        'created_at' => $row['created_at'],
        'formatted_date' => date('M j, Y g:i A', strtotime($row['created_at']))
    ];
    
    // Track first and last contact
    if (!$summary['first_contact'] || $row['created_at'] < $summary['first_contact']) {
        $summary['first_contact'] = $row['created_at'];
    }
    if (!$summary['last_contact'] || $row['created_at'] > $summary['last_contact']) {
        $summary['last_contact'] = $row['created_at'];
    }
}

$summary['total_messages'] = count($communications);

echo json_encode([
    "status" => "success",
    "customer" => [
        "user_id" => $customer['user_id'],
        "name" => $customer['first_name'] . ' ' . $customer['last_name'],
        "mobile_no" => $customer['mobile_no'],
        "email" => $customer['email']
    ],
    "summary" => $summary,
    "communications" => $communications
]);

$stmt->close();
$conn->close();
?>