<?php
require_once("../config/cors-headers.php");
session_start();
header('Content-Type: application/json');
require_once("../config/db_connect.php");

// ✅ Read input from POST or JSON
$input_data = $_POST;
if (empty($input_data)) {
    $input = file_get_contents("php://input");
    if (!empty($input)) {
        $input_data = json_decode($input, true);
    }
}

// ✅ Extract medicine_id and quantity from input
$medicine_id = isset($input_data['medicine_id']) ? (int)$input_data['medicine_id'] : 0;
$quantity = isset($input_data['quantity']) ? max(1, (int)$input_data['quantity']) : 1;

// ✅ Check user session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'login_required']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ✅ Validate medicine_id
if (!$medicine_id) {
    echo json_encode(['status' => 'error', 'message' => 'medicine_id required']);
    exit;
}

// ✅ Fetch medicine details
$check = $conn->prepare("SELECT stock_quantity, price, name, requires_prescription FROM medicines WHERE medicine_id = ?");
$check->bind_param("i", $medicine_id);
$check->execute();
$medicine = $check->get_result()->fetch_assoc();

if (!$medicine) {
    echo json_encode(['status' => 'error', 'message' => 'medicine_not_found']);
    exit;
}

if ($quantity > (int)$medicine['stock_quantity']) {
    echo json_encode(['status' => 'error', 'message' => 'not_enough_stock']);
    exit;
}

// ✅ Check prescription requirement
if ($medicine['requires_prescription']) {
    $prescription_check = $conn->prepare("
        SELECT prescription_id FROM prescriptions 
        WHERE user_id = ? AND status = 'Approved'
        LIMIT 1
    ");
    $prescription_check->bind_param("i", $user_id);
    $prescription_check->execute();
    $prescription_result = $prescription_check->get_result();

    if ($prescription_result->num_rows === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'prescription_required',
            'medicine_id' => $medicine_id,
            'medicine_name' => $medicine['name']
        ]);
        exit;
    }
    $prescription_check->close();
}

// ✅ Check if item already in cart
$exists = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = ? AND medicine_id = ?");
$exists->bind_param("ii", $user_id, $medicine_id);
$exists->execute();
$r = $exists->get_result();

if ($r->num_rows) {
    $row = $r->fetch_assoc();
    $newQ = $row['quantity'] + $quantity;

    if ($newQ > (int)$medicine['stock_quantity']) {
        echo json_encode(['status' => 'error', 'message' => 'not_enough_stock']);
        exit;
    }

    $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
    $upd->bind_param("ii", $newQ, $row['cart_id']);

    if ($upd->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'added_to_cart']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'failed_to_update_cart']);
    }
    $upd->close();
} else {
    $ins = $conn->prepare("INSERT INTO cart (user_id, medicine_id, quantity) VALUES (?, ?, ?)");
    $ins->bind_param("iii", $user_id, $medicine_id, $quantity);

    if ($ins->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'added_to_cart']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'failed_to_add_to_cart']);
    }
    $ins->close();
}

$check->close();
$exists->close();
$conn->close();
?>
