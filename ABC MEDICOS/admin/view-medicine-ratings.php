<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get medicine ID from request (optional - if not provided, returns all medicines)
$medicine_id = isset($_GET['medicine_id']) ? intval($_GET['medicine_id']) : 0;

$sql = "
    SELECT 
        m.medicine_id,
        m.name AS medicine_name,
        m.generic_name,
        m.manufacturer,
        m.category,
        COUNT(f.id) AS total_reviews,
        COALESCE(AVG(f.packaging_rating), 0) AS avg_packaging,
        COALESCE(AVG(f.delivery_rating), 0) AS avg_delivery,
        COALESCE(AVG(f.quality_rating), 0) AS avg_quality,
        COALESCE((AVG(f.packaging_rating) + AVG(f.delivery_rating) + AVG(f.quality_rating)) / 3, 0) AS avg_overall
    FROM medicines m
    LEFT JOIN order_feedback f ON m.medicine_id = f.medicine_id
";

if ($medicine_id > 0) {
    $sql .= " WHERE m.medicine_id = ?";
}

$sql .= " GROUP BY m.medicine_id, m.name, m.generic_name, m.manufacturer, m.category
          HAVING total_reviews > 0
          ORDER BY avg_overall DESC, total_reviews DESC";

$stmt = $conn->prepare($sql);

if ($medicine_id > 0) {
    $stmt->bind_param("i", $medicine_id);
}

$stmt->execute();
$result = $stmt->get_result();

$medicine_ratings = [];
$overall_stats = [
    'total_medicines' => 0,
    'total_reviews' => 0,
    'overall_avg_packaging' => 0,
    'overall_avg_delivery' => 0,
    'overall_avg_quality' => 0,
    'overall_avg_rating' => 0
];

$total_packaging = 0;
$total_delivery = 0;
$total_quality = 0;
$medicine_count = 0;

while ($row = $result->fetch_assoc()) {
    $avg_packaging = round((float)$row['avg_packaging'], 1);
    $avg_delivery = round((float)$row['avg_delivery'], 1);
    $avg_quality = round((float)$row['avg_quality'], 1);
    $avg_overall = round((float)$row['avg_overall'], 1);
    
    $medicine_ratings[] = [
        'medicine_id' => $row['medicine_id'],
        'medicine_name' => $row['medicine_name'],
        'generic_name' => $row['generic_name'],
        'manufacturer' => $row['manufacturer'],
        'category' => $row['category'],
        'total_reviews' => (int)$row['total_reviews'],
        'avg_packaging' => $avg_packaging,
        'avg_delivery' => $avg_delivery,
        'avg_quality' => $avg_quality,
        'avg_overall' => $avg_overall,
        'rating_stars' => str_repeat('★', floor($avg_overall)) . str_repeat('☆', 5 - floor($avg_overall))
    ];
    
    // Calculate overall stats
    $overall_stats['total_reviews'] += $row['total_reviews'];
    $total_packaging += $avg_packaging;
    $total_delivery += $avg_delivery;
    $total_quality += $avg_quality;
    $medicine_count++;
}

// Calculate overall averages
if ($medicine_count > 0) {
    $overall_stats['total_medicines'] = $medicine_count;
    $overall_stats['overall_avg_packaging'] = round($total_packaging / $medicine_count, 1);
    $overall_stats['overall_avg_delivery'] = round($total_delivery / $medicine_count, 1);
    $overall_stats['overall_avg_quality'] = round($total_quality / $medicine_count, 1);
    $overall_stats['overall_avg_rating'] = round(($overall_stats['overall_avg_packaging'] + $overall_stats['overall_avg_delivery'] + $overall_stats['overall_avg_quality']) / 3, 1);
}

echo json_encode([
    "status" => "success",
    "overall_stats" => $overall_stats,
    "medicine_ratings" => $medicine_ratings,
    "filters" => [
        "medicine_id" => $medicine_id
    ]
]);

$stmt->close();
$conn->close();
?>