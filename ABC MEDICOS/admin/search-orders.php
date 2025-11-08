<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get search parameters
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$medicine_id = isset($_GET['medicine_id']) ? intval($_GET['medicine_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

// Build the SQL query with filters
$sql = "
    SELECT 
        o.id AS order_id,
        o.order_number,
        o.medicine_id,
        o.quantity,
        o.total_price,
        o.status,
        o.delivery_otp,
        o.delivery_address,
        o.created_at,
        o.delivered_at,
        m.name AS medicine_name,
        m.generic_name,
        m.manufacturer,
        m.category,
        m.requires_prescription,
        m.image_path,
        u.user_id,
        u.first_name,
        u.last_name,
        u.mobile_no,
        u.email,
        f.packaging_rating,
        f.delivery_rating,
        f.quality_rating
    FROM orders o
    INNER JOIN medicines m ON o.medicine_id = m.medicine_id
    INNER JOIN users u ON o.user_id = u.user_id
    LEFT JOIN order_feedback f ON o.id = f.order_id
    WHERE 1=1
";

$params = [];
$types = "";

// Search term filter (searches multiple fields)
if (!empty($search_term)) {
    $sql .= " AND (
        o.order_number LIKE ? OR 
        m.name LIKE ? OR 
        m.generic_name LIKE ? OR 
        u.first_name LIKE ? OR 
        u.last_name LIKE ? OR 
        u.mobile_no LIKE ? OR
        u.email LIKE ? OR
        o.delivery_address LIKE ?
    )";
    $search_pattern = "%$search_term%";
    $params = array_merge($params, [
        $search_pattern, $search_pattern, $search_pattern, 
        $search_pattern, $search_pattern, $search_pattern,
        $search_pattern, $search_pattern
    ]);
    $types .= str_repeat("s", 8);
}

// Status filter
if (!empty($status) && $status !== 'all') {
    $sql .= " AND o.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Date range filter
if (!empty($date_from)) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Customer ID filter
if ($customer_id > 0) {
    $sql .= " AND u.user_id = ?";
    $params[] = $customer_id;
    $types .= "i";
}

// Medicine ID filter
if ($medicine_id > 0) {
    $sql .= " AND m.medicine_id = ?";
    $params[] = $medicine_id;
    $types .= "i";
}

$sql .= " ORDER BY o.created_at DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

// Prepare and execute query
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$orders = [];
$search_summary = [
    'total_found' => 0,
    'search_term' => $search_term,
    'filters_applied' => []
];

while ($row = $result->fetch_assoc()) {
    $orders[] = [
        'order_id' => $row['order_id'],
        'order_number' => $row['order_number'],
        'medicine_id' => $row['medicine_id'],
        'medicine_name' => $row['medicine_name'],
        'generic_name' => $row['generic_name'],
        'manufacturer' => $row['manufacturer'],
        'category' => $row['category'],
        'quantity' => (int)$row['quantity'],
        'total_price' => (float)$row['total_price'],
        'status' => $row['status'],
        'delivery_address' => $row['delivery_address'],
        'requires_prescription' => (bool)$row['requires_prescription'],
        'created_at' => $row['created_at'],
        'delivered_at' => $row['delivered_at'],
        'customer' => [
            'user_id' => $row['user_id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'mobile_no' => $row['mobile_no'],
            'email' => $row['email']
        ],
        'image_path' => $row['image_path'] ? "http://localhost/phpproj/ABC%20MEDICOS/" . $row['image_path'] : null,
        'has_feedback' => !is_null($row['packaging_rating']),
        'ratings' => $row['packaging_rating'] ? [
            'packaging' => (int)$row['packaging_rating'],
            'delivery' => (int)$row['delivery_rating'],
            'quality' => (int)$row['quality_rating'],
            'overall' => round(($row['packaging_rating'] + $row['delivery_rating'] + $row['quality_rating']) / 3, 1)
        ] : null
    ];
}

$search_summary['total_found'] = count($orders);

// Add applied filters to summary
if (!empty($search_term)) $search_summary['filters_applied'][] = "Search: $search_term";
if (!empty($status) && $status !== 'all') $search_summary['filters_applied'][] = "Status: $status";
if (!empty($date_from)) $search_summary['filters_applied'][] = "From: $date_from";
if (!empty($date_to)) $search_summary['filters_applied'][] = "To: $date_to";
if ($customer_id > 0) $search_summary['filters_applied'][] = "Customer ID: $customer_id";
if ($medicine_id > 0) $search_summary['filters_applied'][] = "Medicine ID: $medicine_id";

echo json_encode([
    "status" => "success",
    "search_summary" => $search_summary,
    "orders" => $orders
]);

$stmt->close();
$conn->close();
?>