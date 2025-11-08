<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();

$response = [];

// ✅ Step 1: Ensure admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// ✅ Step 2: Total Customers
$sql_users = "SELECT COUNT(*) AS total_users FROM users WHERE role = 'customer'";
$total_users = $conn->query($sql_users)->fetch_assoc()['total_users'] ?? 0;

// ✅ Step 3: Total Medicines
$sql_medicines = "SELECT COUNT(*) AS total_medicines FROM medicines";
$total_medicines = $conn->query($sql_medicines)->fetch_assoc()['total_medicines'] ?? 0;

// ✅ Step 4: Total Prescriptions
$sql_prescriptions = "SELECT COUNT(*) AS total_prescriptions FROM prescriptions";
$total_prescriptions = $conn->query($sql_prescriptions)->fetch_assoc()['total_prescriptions'] ?? 0;

// ✅ Step 5: Total Income (Delivered orders only)
$sql_income = "SELECT SUM(total_price) AS total_income FROM orders WHERE status = 'Delivered'";
$total_income = $conn->query($sql_income)->fetch_assoc()['total_income'] ?? 0;

// ✅ Step 6: Monthly Income (for last 12 months)
$sql_monthly = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        SUM(total_price) AS income
    FROM orders
    WHERE status = 'Delivered'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
    LIMIT 12
";
$result_monthly = $conn->query($sql_monthly);

$monthly_income = [];
while ($row = $result_monthly->fetch_assoc()) {
    $monthly_income[] = [
        "month" => $row['month'],
        "income" => (float)$row['income']
    ];
}

// ✅ Step 7: Low Stock Alert
$sql_low_stock = "SELECT COUNT(*) AS low_stock_count FROM medicines WHERE stock_quantity < min_stock_level";
$low_stock_count = $conn->query($sql_low_stock)->fetch_assoc()['low_stock_count'] ?? 0;

// ✅ Step 8: Prepare response
$response = [
    "status" => "success",
    "data" => [
        "total_customers" => (int)$total_users,
        "total_medicines" => (int)$total_medicines,
        "total_prescriptions" => (int)$total_prescriptions,
        "total_orders" => (int)$conn->query("SELECT COUNT(*) FROM orders")->fetch_assoc()['COUNT(*)'] ?? 0,        "total_income" => (float)$total_income,
        "low_stock_alerts" => (int)$low_stock_count,
        "monthly_income" => $monthly_income
    ]
];

echo json_encode($response);
?>