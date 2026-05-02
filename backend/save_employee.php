<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include "db.php";

$data = $_POST;

$full_name = $data['full_name'] ?? '';
$age = $data['age'] ?? '';
$address = $data['address'] ?? '';
$phone = $data['phone'] ?? '';
$hire_date = $data['hire_date'] ?? '';
$status = $data['status'] ?? '';
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$action = $data['action_type'] ?? '';

if (!$action) {
    echo json_encode(["status" => "error", "msg" => "No action provided"]);
    exit;
}

// CHECK DUPLICATE USERNAME
$check = $conn->prepare("SELECT id FROM user_accounts WHERE username=?");
$check->bind_param("s", $username);
$check->execute();
$check->store_result();

if ($check->num_rows > 0 && $action === "add") {
    echo json_encode(["status" => "error", "msg" => "Username already exists"]);
    exit;
}

// ADD
if ($action === "add") {

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO user_accounts (username,password,role) VALUES (?,?, 'employee')");
    $stmt->bind_param("ss", $username, $hashed);
    $stmt->execute();
    $account_id = $stmt->insert_id;

    $stmt2 = $conn->prepare("INSERT INTO user_details (account_id,full_name,age,address,phone,hire_date,status) VALUES (?,?,?,?,?,?,?)");
    $stmt2->bind_param("isissss", $account_id, $full_name, $age, $address, $phone, $hire_date, $status);
    $stmt2->execute();

    echo json_encode(["status" => "success"]);
    exit;
}

// UPDATE
if ($action === "update") {

    $id = $data['employee_id'] ?? 0;

    $stmt = $conn->prepare("UPDATE user_details SET full_name=?,age=?,address=?,phone=?,hire_date=?,status=? WHERE id=?");
    $stmt->bind_param("sissssi", $full_name, $age, $address, $phone, $hire_date, $status, $id);
    $stmt->execute();

    echo json_encode(["status" => "success"]);
    exit;
}

echo json_encode(["status" => "error", "msg" => "Invalid action"]);