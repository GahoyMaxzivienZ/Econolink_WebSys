<?php
session_start();

/* ===============================
   CORS FIX (IMPORTANT FOR REACT)
=============================== */
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

/* ===============================
   DB CONNECTION
=============================== */
$conn = new mysqli("localhost", "root", "", "econolink_db");

if ($conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Database connection failed'
    ]);
    exit;
}

/* ===============================
   READ INPUT
=============================== */
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Not logged in (session missing)'
    ]);
    exit;
}

$cashier_account_id = $_SESSION['user_id'];
$discount_applied = $data['discount_applied'] ?? 0;
$discount_amount = $data['discount_amount'] ?? 0;
$total = $data['total'] ?? 0;
$items = $data['items'] ?? [];

/* ===============================
   VALIDATION
=============================== */
if (!$cashier_account_id || !is_array($items) || empty($items)) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Invalid payload'
    ]);
    exit;
}

/* ===============================
   GET CASHIER NAME
=============================== */
$stmt = $conn->prepare("
    SELECT id, full_name 
    FROM user_details 
    WHERE account_id = ?
");
$stmt->bind_param("i", $cashier_account_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Cashier not found'
    ]);
    exit;
}

$cashier = $res->fetch_assoc();

$cashier_id = $cashier['id'];
$cashier_name = $cashier['full_name'];
$stmt->close();

/* ===============================
   TRANSACTION START
=============================== */
$conn->begin_transaction();

try {

    /* ===========================
       1. INSERT RECEIPT
    =========================== */
    $stmt = $conn->prepare("
        INSERT INTO receipts 
        (cashier_id, discount_applied, discount_amount, total) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iidd", $cashier_id, $discount_applied, $discount_amount, $total);
    $stmt->execute();

    $receipt_id = $stmt->insert_id;
    $stmt->close();

    /* ===========================
       2. PREPARE QUERIES
    =========================== */
    $saleStmt = $conn->prepare("
        INSERT INTO sales 
        (receipt_id, product_id, quantity, price, discount, total, cashier) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stockStmt = $conn->prepare("
        UPDATE inventory 
        SET quantity = quantity - ? 
        WHERE id = ? AND quantity >= ?
    ");

    /* ===========================
       3. LOOP ITEMS
    =========================== */
    foreach ($items as $item) {

        $product_id = $item['product_id'];
        $qty = $item['quantity'];
        $price = $item['price'];
        $discount = $item['discount'];
        $line_total = $item['total'];

        // SAVE SALE
        $saleStmt->bind_param(
            "iiiddds",
            $receipt_id,
            $product_id,
            $qty,
            $price,
            $discount,
            $line_total,
            $cashier_name
        );

        if (!$saleStmt->execute()) {
            throw new Exception("Failed to save sale item");
        }

        // UPDATE STOCK
        $stockStmt->bind_param("iii", $qty, $product_id, $qty);

        if (!$stockStmt->execute() || $stockStmt->affected_rows === 0) {
            throw new Exception("Insufficient stock for product ID: $product_id");
        }
    }

    $saleStmt->close();
    $stockStmt->close();

    /* ===========================
       COMMIT
    =========================== */
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'msg' => 'Sale completed',
        'receipt' => [
            'receipt_id' => $receipt_id,
            'cashier_id' => $cashier_id,
            'cashier_name' => $cashier_name,
            'items' => $items,
            'total' => $total,
            'date' => date("Y-m-d H:i:s")
        ]
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ]);
}

$conn->close();
?>