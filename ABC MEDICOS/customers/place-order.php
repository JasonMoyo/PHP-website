<?php
require_once("../config/cors-headers.php");
session_start();
header("Content-Type: application/json");
require_once("../config/db_connect.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request for debugging
error_log("Place order request received: " . print_r($_POST, true));

// 1️⃣ — Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in for order placement");
    echo json_encode(['status' => 'error', 'message' => 'login_required']);
    exit;
}

// Check both POST and JSON input
$input_data = $_POST;
if (empty($input_data)) {
    $input_data = json_decode(file_get_contents("php://input"), true) ?? [];
}

$user_id = (int)$_SESSION['user_id'];
$payment_method = trim($input_data['payment_method'] ?? '');
$address_id = isset($input_data['address_id']) ? (int)$input_data['address_id'] : 0;

error_log("User ID: $user_id, Payment Method: $payment_method, Address ID: $address_id");

// 2️⃣ — Validate payment method
$allowed_methods = ['COD', 'UPI', 'PAYTM', 'PHONEPE'];
if (!in_array(strtoupper($payment_method), $allowed_methods)) {
    error_log("Invalid payment method: $payment_method");
    echo json_encode(['status' => 'error', 'message' => 'Invalid payment method']);
    exit;
}

// 3️⃣ — Valaddress_idate address
if ($address_id <= 0) {
    error_log("Invalid address ID: $address_id");
    echo json_encode(['status' => 'error', 'message' => 'Please select a delivery address']);
    exit;
}

// 4️⃣ — Get address details with better debugging
$address_sql = $conn->prepare("SELECT address_id, full_address, city, state, pincode, landmark FROM addresses WHERE address_id = ? AND user_id = ?");
$address_sql->bind_param("ii", $address_id, $user_id);
$address_sql->execute();
$address_result = $address_sql->get_result();

error_log("Address query executed. Found rows: " . $address_result->num_rows);

if ($address_result->num_rows === 0) {
    // Let's check if the address exists but belongs to another user
    $check_any_sql = $conn->prepare("SELECT address_id FROM addresses WHERE address_id = ?");
    $check_any_sql->bind_param("i", $address_id);
    $check_any_sql->execute();
    $check_any_result = $check_any_sql->get_result();
    
    if ($check_any_result->num_rows === 0) {
        error_log("Address ID $address_id does not exist in database");
        echo json_encode(['status' => 'error', 'message' => 'Address not found in system']);
    } else {
        error_log("Address ID $address_id exists but doesn't belong to user $user_id");
        echo json_encode(['status' => 'error', 'message' => 'Address does not belong to current user']);
    }
    exit;
}

$address_data = $address_result->fetch_assoc();
$delivery_address = "{$address_data['full_address']}, {$address_data['city']}, {$address_data['state']} - {$address_data['pincode']}";
if (!empty($address_data['landmark'])) {
    $delivery_address .= " (Landmark: {$address_data['landmark']})";
}

error_log("Using delivery address: $delivery_address");

// 5️⃣ — Fetch cart items
$cart_sql = $conn->prepare("
    SELECT c.cart_id, c.medicine_id, c.quantity,
           m.price, m.stock_quantity, m.name, m.requires_prescription
    FROM cart c
    JOIN medicines m ON c.medicine_id = m.medicine_id
    WHERE c.user_id = ?
");
$cart_sql->bind_param("i", $user_id);
$cart_sql->execute();
$cart_result = $cart_sql->get_result();

error_log("Cart items found: " . $cart_result->num_rows);

if ($cart_result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Cart is empty']);
    exit;
}

// ✅ PRESCRIPTION VALIDATION: Check if prescription is required for any item
$prescription_required = false;
$prescription_required_medicines = [];
$cart_result_temp = $cart_result->fetch_all(MYSQLI_ASSOC);

foreach ($cart_result_temp as $item) {
    if ($item['requires_prescription']) {
        $prescription_required = true;
        $prescription_required_medicines[] = $item['name'];
    }
}

// ✅ If prescription required, get user's approved prescriptions
$prescription_id = null;
if ($prescription_required) {
    error_log("Prescription required for medicines: " . implode(", ", $prescription_required_medicines));
    
    $prescription_check = $conn->prepare("
        SELECT prescription_id FROM prescriptions 
        WHERE user_id = ? AND status = 'Approved'
        ORDER BY created_at DESC LIMIT 1
    ");
    $prescription_check->bind_param("i", $user_id);
    $prescription_check->execute();
    $prescription_result = $prescription_check->get_result();
    
    if ($prescription_result->num_rows === 0) {
        $error_msg = "Prescription required for: " . implode(", ", $prescription_required_medicines) . ". Please upload an approved prescription first.";
        error_log("No approved prescription found for user $user_id");
        echo json_encode(['status' => 'error', 'message' => $error_msg]);
        exit;
    }
    
    $prescription_data = $prescription_result->fetch_assoc();
    $prescription_id = $prescription_data['prescription_id'];
    $prescription_check->close();
    error_log("Using prescription ID: $prescription_id for order");
}

// Reset cart result pointer for processing
$cart_result->data_seek(0);

// Begin transaction
$conn->begin_transaction();

try {
    $total_order_value = 0;
    $placed_orders = [];

    while ($item = $cart_result->fetch_assoc()) {
        $price = (float)$item['price'];
        $item_total = $price * $item['quantity'];
        $total_order_value += $item_total;

        error_log("Processing cart item: {$item['name']}, Qty: {$item['quantity']}, Stock: {$item['stock_quantity']}, Price: $price, Total: $item_total");

        // Check stock availability
        if ($item['quantity'] > $item['stock_quantity']) {
            throw new Exception("Insufficient stock for {$item['name']}. Available: {$item['stock_quantity']}, Requested: {$item['quantity']}");
        }

        // ✅ PRESCRIPTION CHECK: Ensure prescription is available if required
        if ($item['requires_prescription'] && !$prescription_id) {
            throw new Exception("Prescription required for {$item['name']} but no approved prescription found.");
        }

        // Deduct stock
        $new_stock = $item['stock_quantity'] - $item['quantity'];
        $upd_stock = $conn->prepare("UPDATE medicines SET stock_quantity = ? WHERE medicine_id = ?");
        $upd_stock->bind_param("ii", $new_stock, $item['medicine_id']);
        if (!$upd_stock->execute()) {
            throw new Exception("Failed to update stock for medicine {$item['medicine_id']}");
        }
        $upd_stock->close();

        // Generate unique order number & OTP
        $order_number = "ORD" . time() . rand(100, 999);
        $otp = str_pad(rand(0, 999999), 6, "0", STR_PAD_LEFT);

        // ✅ Insert order with prescription_id if required
        $insert = $conn->prepare("
            INSERT INTO orders (user_id, order_number, medicine_id, quantity, total_price, status, delivery_otp, created_at, delivery_address, prescription_id)
            VALUES (?, ?, ?, ?, ?, 'Placed', ?, NOW(), ?, ?)
        ");
        
        if (!$insert) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Use prescription_id only if medicine requires prescription
        $order_prescription_id = $item['requires_prescription'] ? $prescription_id : NULL;
        
        $insert->bind_param(
            "isiiissi",
            $user_id,
            $order_number,
            $item['medicine_id'],
            $item['quantity'],
            $item_total,
            $otp,
            $delivery_address,
            $order_prescription_id
        );
        
        if (!$insert->execute()) {
            throw new Exception("Execute failed: " . $insert->error);
        }
        
        $order_id = $insert->insert_id;
        $insert->close();
        
        error_log("Order created: $order_number for medicine {$item['medicine_id']} with prescription: " . ($order_prescription_id ?: 'NULL'));

        $placed_orders[] = [
            'order_id' => $order_id,
            'order_number' => $order_number,
            'medicine_id' => $item['medicine_id'],
            'medicine_name' => $item['name'],
            'quantity' => $item['quantity'],
            'total_price' => round($item_total, 2),
            'otp' => $otp,
            'delivery_address' => $delivery_address,
            'requires_prescription' => (bool)$item['requires_prescription'],
            'prescription_id' => $order_prescription_id
        ];
    }

    // Clear cart
    $clear = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $clear->bind_param("i", $user_id);
    if (!$clear->execute()) {
        throw new Exception("Failed to clear cart");
    }
    $clear->close();

    // Commit all
    $conn->commit();

    error_log("Order placement successful for user $user_id. Total orders: " . count($placed_orders));
    
    $response = [
        'status' => 'success',
        'message' => 'Order placed successfully',
        'orders' => $placed_orders,
        'total_value' => round($total_order_value, 2),
        'prescription_used' => $prescription_id ? true : false,
        'prescription_id' => $prescription_id
    ];
    
    if ($prescription_required) {
        $response['message'] .= ' (Prescription verified and linked to order)';
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    // Log the actual error for debugging
    error_log("Order placement error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to place order: ' . $e->getMessage()]);
}

$address_sql->close();
$cart_sql->close();
if (isset($check_any_sql)) $check_any_sql->close();
$conn->close();
?>