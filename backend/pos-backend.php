<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli("localhost", "root", "", "econolink_db");
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'inventory':
        getInventory();
        break;
    case 'categories':
        getCategories();
        break;
    case 'cashier':
        getCashier();
        break;
    case 'save':
        saveTransaction();
        break;
    case 'verify_admin':
        verifyAdmin();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function getInventory()
{
    global $conn;
    $result = $conn->query("SELECT id, product_name, price, quantity, image, category FROM inventory ORDER BY product_name ASC");
    $items = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($items);
}

function getCategories()
{
    global $conn;
    $result = $conn->query("SELECT DISTINCT category FROM inventory");
    $categories = [];
    while ($cat = $result->fetch_assoc()) {
        $categories[] = $cat['category'];
    }
    echo json_encode($categories);
}

function getCashier()
{
    global $conn;
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['name' => 'Unknown']);
        return;
    }

    $user_id = $_SESSION['user_id'];
    $result = $conn->query("SELECT full_name FROM user_details WHERE account_id = $user_id LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['name' => $row['full_name']]);
    } else {
        echo json_encode(['name' => 'Unknown']);
    }
}

function saveTransaction()
{
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['items']) || empty($data['items'])) {
        echo json_encode(['status' => 'error', 'message' => 'No items provided']);
        return;
    }

    $cashier_id = $_SESSION['user_id'];
    $items = $data['items'];
    $discount = $data['discount'] ?? 0;
    $vat = $data['vat'] ?? 0;
    $total = $data['total'] ?? 0;
    $cash = $data['cash'] ?? 0;
    $sc_discount = $data['scDiscount'] ?? false;

    // Calculate totals
    $subtotal_net = 0;
    $vat_total = 0;

    foreach ($items as $item) {
        $line_total_inc = $item['price'] * $item['qty'];
        $vat_portion = $line_total_inc * (12 / 112);
        $net_portion = $line_total_inc - $vat_portion;
        $subtotal_net += $net_portion;
        $vat_total += $vat_portion;
    }

    $discount_amount = $sc_discount ? $subtotal_net * 0.20 : 0;
    $subtotal_after_discount = $subtotal_net - $discount_amount;
    $final_total = $subtotal_after_discount + $vat_total;

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert sale record
        $stmt = $conn->prepare("INSERT INTO sales (cashier_id, discount_applied, discount_amount, vat_amount, total, date_created) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iidd", $cashier_id, $sc_discount, $discount_amount, $vat_total, $final_total);
        $stmt->execute();
        $sale_id = $conn->insert_id;
        $stmt->close();

        // Insert sale items
        $stmt = $conn->prepare("INSERT INTO transaction_items (sale_id, product_id, quantity, price, vat, discount, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $line_total = $item['price'] * $item['qty'];
            $item_vat = $line_total * (12 / 112);
            $item_discount = $sc_discount ? $line_total * 0.20 : 0;
            $stmt->bind_param("iiidddd", $sale_id, $item['id'], $item['qty'], $item['price'], $item_vat, $item_discount, $line_total);
            $stmt->execute();

            // Update inventory
            $update_stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $update_stmt->bind_param("ii", $item['qty'], $item['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        $stmt->close();

        $conn->commit();
        echo json_encode(['status' => 'success', 'sale_id' => $sale_id]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function verifyAdmin()
{
    global $conn;

    $data = json_decode(file_get_contents('php://input'), true);
    $password = $data['password'] ?? '';

    // Check admin password (you might want to hash this in production)
    $result = $conn->query("SELECT id FROM user_details WHERE password = '$password' AND role = 'admin' LIMIT 1");

    if ($result && $result->num_rows > 0) {
        echo json_encode(['valid' => true]);
    } else {
        echo json_encode(['valid' => false]);
    }
}

$conn->close();
?>