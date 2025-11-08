<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

if (!isset($_POST['prescription_id']) || !isset($_POST['action'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$prescription_id = intval($_POST['prescription_id']);
$action = $_POST['action'];
$notes = $_POST['notes'] ?? '';
$medicines = $_POST['medicines'] ?? []; // Array of medicines to dispense

// Get prescription details
$prescription_stmt = $conn->prepare("SELECT user_id, status FROM prescriptions WHERE prescription_id = ?");
$prescription_stmt->bind_param("i", $prescription_id);
$prescription_stmt->execute();
$prescription_result = $prescription_stmt->get_result();

if ($prescription_result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Prescription not found"]);
    exit;
}

$prescription = $prescription_result->fetch_assoc();
$user_id = $prescription['user_id'];
$current_status = $prescription['status'];

$conn->begin_transaction();

try {
    switch ($action) {
        case 'process':
            $new_status = 'Processing';
            // Check medicine availability
            foreach ($medicines as $medicine) {
                $med_id = $medicine['medicine_id'];
                $qty = $medicine['quantity'];
                
                $stock_stmt = $conn->prepare("SELECT stock_quantity, name FROM medicines WHERE medicine_id = ?");
                $stock_stmt->bind_param("i", $med_id);
                $stock_stmt->execute();
                $stock_result = $stock_stmt->get_result();
                
                if ($stock_row = $stock_result->fetch_assoc()) {
                    if ($stock_row['stock_quantity'] < $qty) {
                        throw new Exception("Insufficient stock for {$stock_row['name']}. Available: {$stock_row['stock_quantity']}, Required: $qty");
                    }
                } else {
                    throw new Exception("Medicine not found");
                }
            }
            break;
            
        case 'ready':
            $new_status = 'Ready for Pickup';
            // Deduct stock
            foreach ($medicines as $medicine) {
                $med_id = $medicine['medicine_id'];
                $qty = $medicine['quantity'];
                
                $update_stmt = $conn->prepare("UPDATE medicines SET stock_quantity = stock_quantity - ? WHERE medicine_id = ?");
                $update_stmt->bind_param("ii", $qty, $med_id);
                $update_stmt->execute();
            }
            break;
            
        case 'complete':
            $new_status = 'Completed';
            break;
            
        case 'reject':
            $new_status = 'Rejected';
            break;
            
        default:
            throw new Exception("Invalid action");
    }
    
    // Update prescription status
    $update_prescription = $conn->prepare("UPDATE prescriptions SET status = ?, notes = ? WHERE prescription_id = ?");
    $update_prescription->bind_param("ssi", $new_status, $notes, $prescription_id);
    $update_prescription->execute();
    
    // Save prescribed medicines
    if (!empty($medicines) && in_array($action, ['process', 'ready', 'complete'])) {
        foreach ($medicines as $medicine) {
            $med_stmt = $conn->prepare("INSERT INTO prescription_medicines (prescription_id, medicine_id, quantity, notes) VALUES (?, ?, ?, ?)");
            $med_stmt->bind_param("iiis", $prescription_id, $medicine['medicine_id'], $medicine['quantity'], $medicine['notes'] ?? '');
            $med_stmt->execute();
        }
    }
    
    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Prescription {$action}ed successfully"]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>