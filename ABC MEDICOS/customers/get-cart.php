<?php
require_once("../config/cors-headers.php");
session_start();
header('Content-Type: application/json');
require_once("../config/db_connect.php");

// 1️⃣ Check user session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2️⃣ Fetch items from cart
$sql = "
SELECT 
    c.cart_id,
    c.medicine_id,
    c.quantity,
    m.name AS medicine_name,
    m.price,
    m.stock_quantity,
    m.image_path,
    m.requires_prescription
FROM cart c
JOIN medicines m ON c.medicine_id = m.medicine_id
WHERE c.user_id = ?
ORDER BY c.cart_id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$total_price = 0;
$total_items = 0;

// 3️⃣ Build response data
while ($row = $result->fetch_assoc()) {
    $price = (float)$row['price'];
    $subtotal = $price * (int)$row['quantity'];
    $total_price += $subtotal;
    $total_items += (int)$row['quantity'];

    // Add full image path
    $image_path = $row['image_path'] ? "http://localhost/phpproj/ABC%20MEDICOS/" . $row['image_path'] : null;

    $cart_items[] = [
        'cart_id'        => (int)$row['cart_id'],
        'medicine_id'    => (int)$row['medicine_id'],
        'medicine_name'  => $row['medicine_name'],
        'price'          => $price,
        'quantity'       => (int)$row['quantity'],
        'stock_quantity' => (int)$row['stock_quantity'],
        'requires_prescription' => (bool)$row['requires_prescription'],
        'image'          => $image_path,
        'subtotal'       => round($subtotal, 2)
    ];
}

// 4️⃣ Return response
echo json_encode([
    'status' => 'success',
    'total_items' => $total_items,
    'total_price' => round($total_price, 2),
    'cart' => $cart_items
]);

$stmt->close();
$conn->close();
?>