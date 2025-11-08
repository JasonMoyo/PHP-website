<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

if (!isset($_GET['medicine_id'])) {
    echo json_encode(['status'=>'error','message'=>'medicine_id required']);
    exit;
}

$medicine_id = (int)$_GET['medicine_id'];

// Medicine basic info
$sql = "SELECT medicine_id, name, generic_name, manufacturer, description, composition, uses, side_effects, precautions, category, price, stock_quantity, requires_prescription, expiry_date, image_path
        FROM medicines WHERE medicine_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $medicine_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status'=>'error','message'=>'Medicine not found']);
    exit;
}

$medicine = $result->fetch_assoc();

// Add full image path
$base_url = "http://localhost/phpproj/ABC%20MEDICOS/";
$medicine['image_path'] = $medicine['image_path'] ? $base_url . $medicine['image_path'] : null;

// IMPROVED: Average ratings with better NULL handling
$ratingSql = "
    SELECT 
        COALESCE(AVG(NULLIF(f.packaging_rating, 0)), 0) as avg_pack,
        COALESCE(AVG(NULLIF(f.delivery_rating, 0)), 0) as avg_del,
        COALESCE(AVG(NULLIF(f.quality_rating, 0)), 0) as avg_qual,
        COUNT(f.id) as total_reviews
    FROM order_feedback f
    INNER JOIN orders o ON f.order_id = o.id
    WHERE o.medicine_id = ? 
    AND f.packaging_rating > 0 
    AND f.delivery_rating > 0 
    AND f.quality_rating > 0";
    
$stmt3 = $conn->prepare($ratingSql);
$stmt3->bind_param("i", $medicine_id);
$stmt3->execute();
$ratingRes = $stmt3->get_result()->fetch_assoc();

// Process the results
$ratingRes['avg_pack'] = round((float)$ratingRes['avg_pack'], 1);
$ratingRes['avg_del'] = round((float)$ratingRes['avg_del'], 1);
$ratingRes['avg_qual'] = round((float)$ratingRes['avg_qual'], 1);
$ratingRes['total_reviews'] = (int)$ratingRes['total_reviews'];

// Calculate overall rating
if ($ratingRes['total_reviews'] > 0) {
    $ratingRes['overall'] = round(($ratingRes['avg_pack'] + $ratingRes['avg_del'] + $ratingRes['avg_qual']) / 3, 1);
} else {
    $ratingRes['overall'] = 0;
    $ratingRes['avg_pack'] = 0;
    $ratingRes['avg_del'] = 0;
    $ratingRes['avg_qual'] = 0;
}

$response = [
    'status' => 'success',
    'medicine' => $medicine,
    'ratings' => $ratingRes
];

echo json_encode($response);
$conn->close();
?>