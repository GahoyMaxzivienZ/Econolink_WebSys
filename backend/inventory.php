<?php
session_start();

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

error_reporting(0);
ini_set('display_errors', 0);

/* HANDLE OPTIONS (IMPORTANT CORS FIX) */
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* DATABASE CONNECTION (THIS IS YOUR MISSING FIX) */
$conn = new mysqli("localhost", "root", "", "econolink_db");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

/* =========================
   ADD ITEM
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_item'])) {

    $product_name = trim($_POST['product_name']);
    $category = trim($_POST['category']);
    $quantity = (int) $_POST['quantity'];
    $price = (float) $_POST['price'];

    /* CHECK DUPLICATE */
    $check = $conn->prepare("SELECT id FROM inventory WHERE LOWER(product_name)=LOWER(?) LIMIT 1");
    $check->bind_param("s", $product_name);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode(["error" => "Duplicate item not allowed"]);
        exit;
    }
    $check->close();

    /* IMAGE UPLOAD */
    $image = "";

    if (!empty($_FILES['image']['name'])) {

        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $safe = preg_replace("/[^a-zA-Z0-9_-]/", "", pathinfo($_FILES["image"]["name"], PATHINFO_FILENAME));
        $filename = time() . "_" . $safe . "." . $ext;

        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image = $target_file;
        }
    }

    /* INSERT */
    $stmt = $conn->prepare("
        INSERT INTO inventory (product_name, category, quantity, price, image)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param("ssids", $product_name, $category, $quantity, $price, $image);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => "Item added"]);
    exit;
}

/* =========================
   UPDATE ITEM
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_item'])) {

    $id = (int) $_POST['item_id'];
    $product_name = trim($_POST['product_name']);
    $category = trim($_POST['category']);
    $quantity = (int) $_POST['quantity'];
    $price = (float) $_POST['price'];
    $image = $_POST['current_image'];

    /* NEW IMAGE */
    if (!empty($_FILES['image']['name'])) {

        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $filename = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image = $target_file;
        }
    }

    $stmt = $conn->prepare("
        UPDATE inventory 
        SET product_name=?, category=?, quantity=?, price=?, image=?
        WHERE id=?
    ");

    $stmt->bind_param("ssidsi", $product_name, $category, $quantity, $price, $image, $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => "Item updated"]);
    exit;
}

/* =========================
   DELETE ITEM
========================= */
if (isset($_GET['delete_id'])) {

    $id = (int) $_GET['delete_id'];

    /* DELETE IMAGE */
    $imgStmt = $conn->prepare("SELECT image FROM inventory WHERE id=?");
    $imgStmt->bind_param("i", $id);
    $imgStmt->execute();
    $imgStmt->bind_result($img);

    if ($imgStmt->fetch()) {
        if (!empty($img) && file_exists($img)) {
            unlink($img);
        }
    }
    $imgStmt->close();

    /* DELETE ROW */
    $stmt = $conn->prepare("DELETE FROM inventory WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => "Item deleted"]);
    exit;
}

/* =========================
   DEFAULT RESPONSE (optional)
========================= */
echo json_encode(["message" => "Inventory API running"]);