<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");

/* handle preflight request */
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

session_start();

$conn = new mysqli("localhost", "root", "", "econolink_db");

if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$account_id = $_SESSION['user_id'];

/* =========================
   ADMIN INFO
========================= */
$stmt = $conn->prepare("
    SELECT full_name, profile_image 
    FROM user_details 
    WHERE account_id = ?
");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$admin_name = $user['full_name'] ?? "Admin";
$profile_image = !empty($user['profile_image'])
    ? $user['profile_image']
    : "profile-placeholder.png";

/* =========================
   DASHBOARD STATS
========================= */

// Sales Today
$sales_today = $conn->query("
    SELECT IFNULL(SUM(total),0) AS total_sales 
    FROM sales 
    WHERE DATE(date_sold) = CURDATE()
")->fetch_assoc()['total_sales'];

// Transactions Today
$tx_today = $conn->query("
    SELECT COUNT(*) AS tx_count 
    FROM sales 
    WHERE DATE(date_sold) = CURDATE()
")->fetch_assoc()['tx_count'];

// Inventory Count
$total_items = $conn->query("
    SELECT COUNT(*) AS total_items 
    FROM inventory
")->fetch_assoc()['total_items'];

// Low Stock
$low_stock = $conn->query("
    SELECT COUNT(*) AS low_stock 
    FROM inventory 
    WHERE quantity <= 5
")->fetch_assoc()['low_stock'];

/* =========================
   TOP PRODUCTS (CHART)
========================= */
$top_products = [];
$top_quantities = [];

$result = $conn->query("
    SELECT i.product_name, SUM(s.quantity) AS total_sold
    FROM sales s
    JOIN inventory i ON s.product_id = i.id
    WHERE MONTH(s.date_sold) = MONTH(CURDATE())
    AND YEAR(s.date_sold) = YEAR(CURDATE())
    GROUP BY i.product_name
    ORDER BY total_sold DESC
    LIMIT 3
");

while ($row = $result->fetch_assoc()) {
    $top_products[] = $row['product_name'];
    $top_quantities[] = $row['total_sold'];
}

/* =========================
   INVENTORY STATUS (CHART)
========================= */
$status = $conn->query("
    SELECT 
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) AS no_stock,
        SUM(CASE WHEN quantity > 0 AND quantity <= 5 THEN 1 ELSE 0 END) AS low_stock,
        SUM(CASE WHEN quantity > 5 THEN 1 ELSE 0 END) AS in_stock
    FROM inventory
")->fetch_assoc();

/* =========================
   RECENT TRANSACTIONS
========================= */
$recent = [];

$res = $conn->query("
    SELECT receipt_id, SUM(total) AS total, COUNT(*) AS item_count,
    DATE_FORMAT(MAX(date_sold), '%b %d, %Y') AS formatted_date
    FROM sales
    GROUP BY receipt_id
    ORDER BY MAX(date_sold) DESC
    LIMIT 10
");

while ($row = $res->fetch_assoc()) {
    $recent[] = $row;
}

/* =========================
   FINAL OUTPUT
========================= */
echo json_encode([
    "user" => [
        "name" => $admin_name,
        "profile_image" => $profile_image
    ],
    "stats" => [
        "sales_today" => $sales_today,
        "transactions_today" => $tx_today,
        "inventory" => $total_items,
        "low_stock" => $low_stock
    ],
    "charts" => [
        "top_products" => $top_products,
        "top_quantities" => $top_quantities,
        "inventory_status" => $status
    ],
    "recent_transactions" => $recent
]);

$conn->close();