<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "
    SELECT 
        p.prescription_id,
        p.image_path,
        p.status,
        p.notes,
        p.created_at,
        u.user_id,
        u.first_name,
        u.last_name,
        u.mobile_no,
        u.email,
        u.address
    FROM prescriptions p
    INNER JOIN users u ON p.user_id = u.user_id
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($status)) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.mobile_no LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$prescriptions = [];
while ($row = $result->fetch_assoc()) {
    $prescriptions[] = $row;
}

echo json_encode([
    "status" => "success",
    "count" => count($prescriptions),
    "data" => $prescriptions
]);

$stmt->close();
$conn->close();
?>