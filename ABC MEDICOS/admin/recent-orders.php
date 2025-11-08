<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

$sql = "
    SELECT 
        o.id AS order_id,
        o.order_number,
        o.quantity,
        o.total_price,
        o.status,
        o.created_at,
        m.name AS medicine_name,
        u.first_name,
        u.last_name,
        u.mobile_no
    FROM orders o
    INNER JOIN medicines m ON o.medicine_id = m.medicine_id
    INNER JOIN users u ON o.user_id = u.user_id
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($status) && $status !== 'all') {
    $sql .= " AND o.status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql .= " ORDER BY o.created_at DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = [
        'order_id' => $row['order_id'],
        'order_number' => $row['order_number'],
        'medicine_name' => $row['medicine_name'],
        'quantity' => (int)$row['quantity'],
        'total_price' => (float)$row['total_price'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'customer_name' => $row['first_name'] . ' ' . $row['last_name'],
        'customer_mobile' => $row['mobile_no'],
        'time_ago' => getTimeAgo($row['created_at'])
    ];
}

echo json_encode([
    "status" => "success",
    "limit" => $limit,
    "status_filter" => $status,
    "orders" => $orders
]);

$stmt->close();
$conn->close();

// Helper function for time ago
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $time_difference = time() - $time;

    if ($time_difference < 1) { return 'less than 1 second ago'; }
    $condition = array( 
        12 * 30 * 24 * 60 * 60 => 'year',
        30 * 24 * 60 * 60      => 'month',
        24 * 60 * 60           => 'day',
        60 * 60                => 'hour',
        60                     => 'minute',
        1                      => 'second'
    );

    foreach ($condition as $secs => $str) {
        $d = $time_difference / $secs;
        if ($d >= 1) {
            $t = round($d);
            return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
        }
    }
}
?>