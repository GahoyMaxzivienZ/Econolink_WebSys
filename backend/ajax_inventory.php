<?php
session_start();

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

error_reporting(0);
ini_set('display_errors', 0);

/* HANDLE CORS PRE-FLIGHT */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* DB CONNECTION (MISSING MO ITO) */
$conn = new mysqli("localhost", "root", "", "econolink_db");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

/* AUTH */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$filter = $_GET['filter'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = [];

/* =========================
   STOCK FILTER
========================= */
if ($filter === "lowstock") {
    $where[] = "quantity > 0 AND quantity <= 5";
} elseif ($filter === "nostock") {
    $where[] = "quantity = 0";
} elseif ($filter === "goodstock") {
    $where[] = "quantity > 5";
}

/* =========================
   CATEGORY FILTER
========================= */
if ($category !== 'all') {
    $safe = $conn->real_escape_string($category);
    $where[] = "category = '$safe'";
}

/* =========================
   SEARCH FILTER
========================= */
if ($search !== '') {
    $safe = $conn->real_escape_string($search);
    $where[] = "(product_name LIKE '%$safe%' OR category LIKE '%$safe%')";
}

/* =========================
   BUILD QUERY
========================= */
$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT id, product_name, category, quantity, price, image
    FROM inventory
    $whereSQL
    ORDER BY product_name ASC
    LIMIT 50
";

$res = $conn->query($sql);

$data = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {

        $qty = (int) $row['quantity'];

        $stock =
            $qty == 0 ? "stock-none" :
            ($qty <= 5 ? "stock-low" : "stock-ok");

        $data[] = [
            "id" => $row['id'],
            "product_name" => $row['product_name'],
            "category" => $row['category'],
            "quantity" => $qty,
            "price" => $row['price'],
            "image" => $row['image'],
            "stock_class" => $stock
        ];
    }
}

echo json_encode($data);