<?php
session_start();

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
header("Content-Type: application/json");
$conn = new mysqli("localhost", "root", "", "econolink_db");

if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed"]));
}

/* =========================
   SESSION CHECK (ADMIN ONLY)
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

/* =========================
   STATS
========================= */

// Sales this month
$sql = "SELECT IFNULL(SUM(total),0) AS total_sales_month 
        FROM sales 
        WHERE MONTH(date_sold)=MONTH(CURRENT_DATE()) 
        AND YEAR(date_sold)=YEAR(CURRENT_DATE())";
$total_sales_month = $conn->query($sql)->fetch_assoc()['total_sales_month'];

// Total sales all time
$sql = "SELECT IFNULL(SUM(total),0) AS total_sales FROM sales";
$total_sales = $conn->query($sql)->fetch_assoc()['total_sales'];

// Active employees
$sql = "SELECT COUNT(*) AS active FROM user_details WHERE status='Active'";
$active = $conn->query($sql)->fetch_assoc()['active'];

// Inactive employees
$sql = "SELECT COUNT(*) AS inactive FROM user_details WHERE status='Inactive'";
$inactive = $conn->query($sql)->fetch_assoc()['inactive'];

// Low stock
$sql = "SELECT COUNT(*) AS low_stock FROM inventory WHERE quantity <= 5";
$low_stock = $conn->query($sql)->fetch_assoc()['low_stock'];

/* =========================
   SALES TABLE
========================= */
$sales = [];
$sql = "SELECT s.receipt_id, p.product_name, s.quantity, s.price, s.discount, s.total, s.cashier, s.date_sold
        FROM sales s
        LEFT JOIN inventory p ON s.product_id = p.id
        ORDER BY s.date_sold DESC";

$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $sales[] = $row;
}

/* =========================
   TRANSACTIONS
========================= */
$transactions = [];
$sql = "SELECT receipt_id, COUNT(*) AS item_count, SUM(total) AS total_amount, MAX(date_sold) AS date_sold
        FROM sales
        GROUP BY receipt_id
        ORDER BY date_sold DESC";

$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $transactions[] = $row;
}

/* =========================
   EMPLOYEES
========================= */
$employees = [];
$sql = "SELECT * FROM user_details WHERE status='Active' ORDER BY full_name ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $employees[] = $row;
}

/* =========================
   INACTIVE EMPLOYEES
========================= */
$inactiveEmployees = [];
$sql = "SELECT * FROM user_details WHERE status='Inactive' ORDER BY full_name ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $inactiveEmployees[] = $row;
}

/* =========================
   LOW STOCK
========================= */
$lowStock = [];
$sql = "SELECT * FROM inventory WHERE quantity <= 5 ORDER BY quantity ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $lowStock[] = $row;
}

/* =========================
   CHART DATA
========================= */

// Monthly sales
$monthlyLabels = [];
$monthlyData = [];

$sql = "SELECT DATE_FORMAT(date_sold,'%Y-%m') as month, SUM(total) as total
        FROM sales
        GROUP BY month
        ORDER BY month ASC";

$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $monthlyLabels[] = date("M Y", strtotime($row['month'] . "-01"));
    $monthlyData[] = (float) $row['total'];
}

// Category sales
$categoryNames = [];
$categorySales = [];

$sql = "SELECT i.category, IFNULL(SUM(s.total),0) AS total
        FROM inventory i
        LEFT JOIN sales s ON i.id = s.product_id
        GROUP BY i.category
        ORDER BY total DESC";

$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $categoryNames[] = $row['category'];
    $categorySales[] = (float) $row['total'];
}

/* =========================
   FINAL RESPONSE
========================= */

echo json_encode([
    "stats" => [
        "sales_month" => $total_sales_month,
        "total_sales" => $total_sales,
        "active_employees" => $active,
        "inactive_employees" => $inactive,
        "low_stock" => $low_stock
    ],
    "sales" => $sales,
    "transactions" => $transactions,
    "employees" => $employees,
    "inactiveEmployees" => $inactiveEmployees,
    "lowStock" => $lowStock,
    "charts" => [
        "monthlyLabels" => $monthlyLabels,
        "monthlyData" => $monthlyData,
        "categoryNames" => $categoryNames,
        "categorySales" => $categorySales,

    ]

]);

$conn->close();
?>