<?php
require_once("../config/cors-headers.php");
header('Content-Type: application/json');
require_once("../config/db_connect.php");

session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}

// Get parameters for date range and limit
$period = isset($_GET['period']) ? trim($_GET['period']) : 'month'; // day, week, month, year
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;

// Calculate date range based on period
$date_ranges = [
    'day' => '1 DAY',
    'week' => '7 DAY',
    'month' => '30 DAY',
    'year' => '365 DAY'
];

$date_range = $date_ranges[$period] ?? $date_ranges['month'];

// Query for most popular medicines by sales
$sales_sql = "
    SELECT 
        m.medicine_id,
        m.name AS medicine_name,
        m.generic_name,
        m.manufacturer,
        m.category,
        m.price,
        COUNT(o.id) AS total_orders,
        SUM(o.quantity) AS total_quantity_sold,
        SUM(o.total_price) AS total_revenue,
        AVG(o.total_price) AS avg_order_value,
        MAX(o.created_at) AS last_ordered
    FROM medicines m
    INNER JOIN orders o ON m.medicine_id = o.medicine_id
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL $date_range)
        AND o.status != 'Cancelled'
    GROUP BY m.medicine_id, m.name, m.generic_name, m.manufacturer, m.category, m.price
    ORDER BY total_quantity_sold DESC, total_revenue DESC
    LIMIT ?
";

$sales_stmt = $conn->prepare($sales_sql);
$sales_stmt->bind_param("i", $limit);
$sales_stmt->execute();
$sales_result = $sales_stmt->get_result();

$popular_medicines = [];
$total_revenue = 0;
$total_units_sold = 0;

while ($row = $sales_result->fetch_assoc()) {
    $popular_medicines[] = [
        'medicine_id' => $row['medicine_id'],
        'medicine_name' => $row['medicine_name'],
        'generic_name' => $row['generic_name'],
        'manufacturer' => $row['manufacturer'],
        'category' => $row['category'],
        'price' => (float)$row['price'],
        'total_orders' => (int)$row['total_orders'],
        'total_quantity_sold' => (int)$row['total_quantity_sold'],
        'total_revenue' => round((float)$row['total_revenue'], 2),
        'avg_order_value' => round((float)$row['avg_order_value'], 2),
        'last_ordered' => $row['last_ordered'],
        'popularity_score' => ($row['total_quantity_sold'] * 0.6) + ($row['total_orders'] * 0.4) // Weighted score
    ];
    
    $total_revenue += $row['total_revenue'];
    $total_units_sold += $row['total_quantity_sold'];
}

$sales_stmt->close();

// Query for category-wise sales
$category_sql = "
    SELECT 
        m.category,
        COUNT(o.id) AS total_orders,
        SUM(o.quantity) AS total_quantity,
        SUM(o.total_price) AS total_revenue
    FROM medicines m
    INNER JOIN orders o ON m.medicine_id = o.medicine_id
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL $date_range)
        AND o.status != 'Cancelled'
    GROUP BY m.category
    ORDER BY total_revenue DESC
";

$category_result = $conn->query($category_sql);
$category_sales = [];

while ($row = $category_result->fetch_assoc()) {
    $category_sales[] = [
        'category' => $row['category'] ?: 'Uncategorized',
        'total_orders' => (int)$row['total_orders'],
        'total_quantity' => (int)$row['total_quantity'],
        'total_revenue' => round((float)$row['total_revenue'], 2),
        'revenue_percentage' => $total_revenue > 0 ? round(($row['total_revenue'] / $total_revenue) * 100, 1) : 0
    ];
}

// Query for sales trend (last 7 days)
$trend_sql = "
    SELECT 
        DATE(o.created_at) AS sale_date,
        COUNT(o.id) AS daily_orders,
        SUM(o.quantity) AS daily_quantity,
        SUM(o.total_price) AS daily_revenue
    FROM orders o
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND o.status != 'Cancelled'
    GROUP BY DATE(o.created_at)
    ORDER BY sale_date ASC
";

$trend_result = $conn->query($trend_sql);
$sales_trend = [];

while ($row = $trend_result->fetch_assoc()) {
    $sales_trend[] = [
        'date' => $row['sale_date'],
        'daily_orders' => (int)$row['daily_orders'],
        'daily_quantity' => (int)$row['daily_quantity'],
        'daily_revenue' => round((float)$row['daily_revenue'], 2)
    ];
}

// Overall analytics summary
$analytics_summary = [
    'period' => $period,
    'date_range' => $date_range,
    'total_medicines_analyzed' => count($popular_medicines),
    'total_revenue' => round($total_revenue, 2),
    'total_units_sold' => $total_units_sold,
    'avg_revenue_per_medicine' => count($popular_medicines) > 0 ? round($total_revenue / count($popular_medicines), 2) : 0,
    'top_category' => count($category_sales) > 0 ? $category_sales[0]['category'] : 'N/A',
    'analysis_period' => [
        'start_date' => date('Y-m-d', strtotime("-$date_range")),
        'end_date' => date('Y-m-d')
    ]
];

echo json_encode([
    "status" => "success",
    "analytics_summary" => $analytics_summary,
    "popular_medicines" => $popular_medicines,
    "category_sales" => $category_sales,
    "sales_trend" => $sales_trend
]);

$conn->close();
?>