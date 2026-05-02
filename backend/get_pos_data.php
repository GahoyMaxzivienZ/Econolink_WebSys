<?php
// 🔥 CORS HEADERS (para gumana sa React)
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// 🔥 Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

$conn = new mysqli("localhost", "root", "", "econolink_db");

if ($conn->connect_error) {
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

// 🔥 IMPORTANT: wag redirect, JSON lang
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "unauthorized"]);
    exit;
}

// Items
$items_result = $conn->query("
    SELECT id, product_name, price, quantity, image, category
    FROM inventory
    ORDER BY product_name ASC
");

$items = $items_result ? $items_result->fetch_all(MYSQLI_ASSOC) : [];

// Categories
$categories_result = $conn->query("SELECT DISTINCT category FROM inventory");

$categories = [];
if ($categories_result) {
    while ($cat = $categories_result->fetch_assoc()) {
        $categories[] = $cat['category'];
    }
}

// Cashier
$user_id = $_SESSION['user_id'];

$user_result = $conn->query("
    SELECT full_name FROM user_details 
    WHERE account_id = $user_id LIMIT 1
");

$cashier_name = "";
if ($user_result && $user_result->num_rows > 0) {
    $cashier_name = $user_result->fetch_assoc()['full_name'];
}

// 🔥 FINAL RESPONSE
echo json_encode([
    "items" => $items,
    "categories" => $categories,
    "cashier_name" => $cashier_name
]);