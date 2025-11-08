<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

if (!isset($_POST['order_id']) || !isset($_POST['action'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$order_id = intval($_POST['order_id']);
$action = trim($_POST['action']); // 'approve' or 'reject'
$admin_notes = $_POST['admin_notes'] ?? '';
$refund_amount = isset($_POST['refund_amount']) ? floatval($_POST['refund_amount']) : null;

// Get order details
$order_sql = "
    SELECT 
        o.id, o.medicine_id, o.quantity, o.total_price, o.user_id, o.status,
        m.name AS medicine_name, u.first_name, u.last_name, u.email
    FROM orders o
    INNER JOIN medicines m ON o.medicine_id = m.medicine_id
    INNER JOIN users u ON o.user_id = u.user_id
    WHERE o.id = ? AND o.status = 'Returned'
";

$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Return request not found or already processed"]);
    exit;
}

$order = $order_result->fetch_assoc();
$order_stmt->close();

$conn->begin_transaction();

try {
    if ($action === 'approve') {
        // For approved returns, we keep status as Returned but add refund notes
        // In a real system, you might integrate with payment gateway here
        
        $final_refund_amount = $refund_amount ?? $order['total_price'];
        
        // Update order with refund details
        $update_sql = "UPDATE orders SET admin_notes = ?, refund_amount = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sdi", $admin_notes, $final_refund_amount, $order_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Send notification to customer (in real system, send email/SMS)
        $notification_subject = "Return Request Approved - Order #" . $order_id;
        $notification_message = "Dear " . $order['first_name'] . ",\n\n" .
                               "Your return request for order #" . $order_id . " has been approved.\n" .
                               "Medicine: " . $order['medicine_name'] . "\n" .
                               "Refund Amount: ₹" . $final_refund_amount . "\n" .
                               "Admin Notes: " . $admin_notes . "\n\n" .
                               "Thank you for your patience.";
        
        // Log notification in messages table
        $message_sql = "INSERT INTO messages (admin_id, user_id, subject, message, is_public) VALUES (?, ?, ?, ?, 0)";
        $message_stmt = $conn->prepare($message_sql);
        $message_stmt->bind_param("iiss", $_SESSION['admin_id'], $order['user_id'], $notification_subject, $notification_message);
        $message_stmt->execute();
        $message_stmt->close();
        
        $response_message = "Return approved successfully. Refund of ₹" . $final_refund_amount . " processed.";
        
    } elseif ($action === 'reject') {
        // For rejected returns, revert status to Delivered and restore stock
        $revert_sql = "UPDATE orders SET status = 'Delivered' WHERE id = ?";
        $revert_stmt = $conn->prepare($revert_sql);
        $revert_stmt->bind_param("i", $order_id);
        $revert_stmt->execute();
        $revert_stmt->close();
        
        // Restore stock (deducted during return process)
        $restore_stock_sql = "UPDATE medicines SET stock_quantity = stock_quantity - ? WHERE medicine_id = ?";
        $restore_stmt = $conn->prepare($restore_stock_sql);
        $restore_stmt->bind_param("ii", $order['quantity'], $order['medicine_id']);
        $restore_stmt->execute();
        $restore_stmt->close();
        
        // Send rejection notification
        $rejection_subject = "Return Request Rejected - Order #" . $order_id;
        $rejection_message = "Dear " . $order['first_name'] . ",\n\n" .
                            "Your return request for order #" . $order_id . " has been rejected.\n" .
                            "Medicine: " . $order['medicine_name'] . "\n" .
                            "Reason: " . $admin_notes . "\n\n" .
                            "If you have any questions, please contact our support team.";
        
        $reject_message_sql = "INSERT INTO messages (admin_id, user_id, subject, message, is_public) VALUES (?, ?, ?, ?, 0)";
        $reject_message_stmt = $conn->prepare($reject_message_sql);
        $reject_message_stmt->bind_param("iiss", $_SESSION['admin_id'], $order['user_id'], $rejection_subject, $rejection_message);
        $reject_message_stmt->execute();
        $reject_message_stmt->close();
        
        $response_message = "Return rejected successfully. Order status reverted to Delivered.";
        
    } else {
        throw new Exception("Invalid action. Use 'approve' or 'reject'.");
    }
    
    $conn->commit();
    echo json_encode(["status" => "success", "message" => $response_message]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>