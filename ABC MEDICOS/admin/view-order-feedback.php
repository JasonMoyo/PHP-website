<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get parameters for filtering
$medicine_id = isset($_GET['medicine_id']) ? intval($_GET['medicine_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

// Build query with filters
$sql = "
    SELECT 
        f.id AS feedback_id,
        f.order_id,
        f.medicine_id,
        f.packaging_rating,
        f.delivery_rating,
        f.quality_rating,
        f.created_at,
        o.order_number,
        o.total_price,
        o.delivered_at,
        m.name AS medicine_name,
        m.generic_name,
        u.user_id,
        u.first_name,
        u.last_name,
        u.mobile_no
    FROM order_feedback f
    INNER JOIN orders o ON f.order_id = o.id
    INNER JOIN medicines m ON f.medicine_id = m.medicine_id
    INNER JOIN users u ON o.user_id = u.user_id
    WHERE 1=1
";

$params = [];
$types = "";

if ($medicine_id > 0) {
    $sql .= " AND f.medicine_id = ?";
    $params[] = $medicine_id;
    $types .= "i";
}

if ($user_id > 0) {
    $sql .= " AND u.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$sql .= " ORDER BY f.created_at DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$feedback = [];
$summary = [
    'total_feedback' => 0,
    'avg_packaging' => 0,
    'avg_delivery' => 0,
    'avg_quality' => 0,
    'avg_overall' => 0
];

while ($row = $result->fetch_assoc()) {
    $overall_rating = ($row['packaging_rating'] + $row['delivery_rating'] + $row['quality_rating']) / 3;
    
    $feedback[] = [
        'feedback_id' => $row['feedback_id'],
        'order_id' => $row['order_id'],
        'order_number' => $row['order_number'],
        'medicine_id' => $row['medicine_id'],
        'medicine_name' => $row['medicine_name'],
        'generic_name' => $row['generic_name'],
        'user_id' => $row['user_id'],
        'user_name' => $row['first_name'] . ' ' . $row['last_name'],
        'mobile_no' => $row['mobile_no'],
        'packaging_rating' => (int)$row['packaging_rating'],
        'delivery_rating' => (int)$row['delivery_rating'],
        'quality_rating' => (int)$row['quality_rating'],
        'overall_rating' => round($overall_rating, 1),
        'total_price' => (float)$row['total_price'],
        'delivered_at' => $row['delivered_at'],
        'created_at' => $row['created_at']
    ];
}

// Calculate summary statistics
if (count($feedback) > 0) {
    $total_packaging = 0;
    $total_delivery = 0;
    $total_quality = 0;
    
    foreach ($feedback as $item) {
        $total_packaging += $item['packaging_rating'];
        $total_delivery += $item['delivery_rating'];
        $total_quality += $item['quality_rating'];
    }
    
    $summary['total_feedback'] = count($feedback);
    $summary['avg_packaging'] = round($total_packaging / count($feedback), 1);
    $summary['avg_delivery'] = round($total_delivery / count($feedback), 1);
    $summary['avg_quality'] = round($total_quality / count($feedback), 1);
    $summary['avg_overall'] = round(($summary['avg_packaging'] + $summary['avg_delivery'] + $summary['avg_quality']) / 3, 1);
}

echo json_encode([
    "status" => "success",
    "summary" => $summary,
    "feedback" => $feedback,
    "filters" => [
        "medicine_id" => $medicine_id,
        "user_id" => $user_id,
        "limit" => $limit
    ]
]);

$stmt->close();
$conn->close();
?>