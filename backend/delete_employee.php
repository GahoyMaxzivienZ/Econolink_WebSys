<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "db.php";

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(["status" => "error", "msg" => "No ID provided"]);
    exit;
}

// GET ACCOUNT ID
$stmt = $conn->prepare("SELECT account_id FROM user_details WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res) {
    echo json_encode(["status" => "error", "msg" => "Employee not found"]);
    exit;
}

$account_id = $res['account_id'];

// DELETE SAFE (prepared statements)
$stmt1 = $conn->prepare("DELETE FROM user_details WHERE id=?");
$stmt1->bind_param("i", $id);
$stmt1->execute();

$stmt2 = $conn->prepare("DELETE FROM user_accounts WHERE id=?");
$stmt2->bind_param("i", $account_id);
$stmt2->execute();

echo json_encode(["status" => "success"]);
exit;