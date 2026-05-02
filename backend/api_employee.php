<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

include "db.php";

$method = $_SERVER['REQUEST_METHOD'];

/* ===============================
   GET EMPLOYEES
=============================== */
if ($method === "GET") {
    $sql = "SELECT ud.*, ua.username 
            FROM user_details ud 
            LEFT JOIN user_accounts ua ON ud.account_id = ua.id 
            ORDER BY ud.id DESC";

    $result = $conn->query($sql);

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $employees
    ]);
    exit;
}

/* ===============================
   CREATE EMPLOYEE
=============================== */
if ($method === "POST") {

    $action = $_POST['action_type'] ?? 'add';

    $full_name = $_POST['full_name'] ?? '';
    $age = $_POST['age'] ?? '';
    $address = $_POST['address'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $hire_date = $_POST['hire_date'] ?? '';
    $status = $_POST['status'] ?? 'Active';

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $employee_id = $_POST['employee_id'] ?? null;

    $profile_image = null;

    // =========================
    // IMAGE UPLOAD
    // =========================
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {

        $uploadDir = "uploads/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $uploadDir . $fileName;

        move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile);

        $profile_image = $targetFile;
    }

    // =========================
    // ADD EMPLOYEE
    // =========================
    if ($action === "add") {

        // check duplicate username
        $check = $conn->prepare("SELECT id FROM user_accounts WHERE username=?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            echo json_encode([
                "status" => "error",
                "field" => "username",
                "message" => "Username already exists"
            ]);
            exit;
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // insert account
        $stmt = $conn->prepare("INSERT INTO user_accounts (username, password, role) VALUES (?,?, 'employee')");
        $stmt->bind_param("ss", $username, $hashed);
        $stmt->execute();
        $account_id = $stmt->insert_id;

        // insert details
        $stmt2 = $conn->prepare("INSERT INTO user_details 
        (account_id, full_name, age, address, phone, hire_date, status, profile_image)
        VALUES (?,?,?,?,?,?,?,?)");

        $stmt2->bind_param(
            "isisssss",
            $account_id,
            $full_name,
            $age,
            $address,
            $phone,
            $hire_date,
            $status,
            $profile_image
        );

        $stmt2->execute();

        echo json_encode([
            "status" => "success",
            "message" => "Employee added successfully"
        ]);
        exit;
    }

    // =========================
    // EDIT EMPLOYEE
    // =========================
    if ($action === "edit") {

        $id = $employee_id;

        // update details
        $stmt = $conn->prepare("UPDATE user_details 
            SET full_name=?, age=?, address=?, phone=?, hire_date=?, status=?
            WHERE id=?");

        $stmt->bind_param(
            "sissssi",
            $full_name,
            $age,
            $address,
            $phone,
            $hire_date,
            $status,
            $id
        );

        $stmt->execute();

        // get account id
        $acc = $conn->prepare("SELECT account_id FROM user_details WHERE id=?");
        $acc->bind_param("i", $id);
        $acc->execute();
        $res = $acc->get_result()->fetch_assoc();
        $account_id = $res['account_id'] ?? null;

        // update username only (no duplicate account creation)
        if ($account_id) {

            $stmt2 = $conn->prepare("UPDATE user_accounts SET username=? WHERE id=?");
            $stmt2->bind_param("si", $username, $account_id);
            $stmt2->execute();
        }

        echo json_encode([
            "status" => "success",
            "message" => "Employee updated successfully"
        ]);
        exit;
    }
}

/* ===============================
   UPDATE EMPLOYEE
=============================== */
if ($method === "PUT") {

    parse_str(file_get_contents("php://input"), $data);

    $id = $data['employee_id'] ?? 0;

    $full_name = $data['full_name'] ?? '';
    $age = $data['age'] ?? '';
    $address = $data['address'] ?? '';
    $phone = $data['phone'] ?? '';
    $hire_date = $data['hire_date'] ?? '';
    $status = $data['status'] ?? '';
    $username = $data['username'] ?? '';

    // update details
    $stmt = $conn->prepare("UPDATE user_details 
        SET full_name=?, age=?, address=?, phone=?, hire_date=?, status=? 
        WHERE id=?");

    $stmt->bind_param("sissssi", $full_name, $age, $address, $phone, $hire_date, $status, $id);
    $stmt->execute();

    // get account id
    $acc = $conn->prepare("SELECT account_id FROM user_details WHERE id=?");
    $acc->bind_param("i", $id);
    $acc->execute();
    $res = $acc->get_result()->fetch_assoc();
    $account_id = $res['account_id'] ?? null;

    if ($account_id) {
        $stmt2 = $conn->prepare("UPDATE user_accounts SET username=? WHERE id=?");
        $stmt2->bind_param("si", $username, $account_id);
        $stmt2->execute();
    }

    echo json_encode([
        "status" => "success",
        "message" => "Employee updated successfully"
    ]);
    exit;
}

/* ===============================
   DELETE EMPLOYEE
=============================== */
if ($method === "DELETE") {

    // ✅ NEW (get from URL)
    $id = $_GET['id'] ?? 0;
    $admin_password = $_GET['admin_password'] ?? '';
    if (!$admin_password) {
        echo json_encode([
            "status" => "error",
            "message" => "Admin password required"
        ]);
        exit;
    }

    // kunin admin password sa database
// kunin lahat ng admin passwords
    $result = $conn->query("SELECT password FROM user_accounts WHERE role='admin'");

    $valid = false;

    while ($row = $result->fetch_assoc()) {
        if (password_verify($admin_password, $row['password'])) {
            $valid = true;
            break;
        }
    }

    if (!$valid) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid admin password"
        ]);
        exit;
    }

    // get account id
    $stmt = $conn->prepare("SELECT account_id FROM user_details WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $account_id = $res['account_id'] ?? null;

    // delete details
    $del1 = $conn->prepare("DELETE FROM user_details WHERE id=?");
    $del1->bind_param("i", $id);
    $del1->execute();

    // delete account
    if ($account_id) {
        $del2 = $conn->prepare("DELETE FROM user_accounts WHERE id=?");
        $del2->bind_param("i", $account_id);
        $del2->execute();
    }

    echo json_encode([
        "status" => "success",
        "message" => "Employee deleted successfully"
    ]);
    exit;
}

echo json_encode([
    "status" => "error",
    "message" => "Invalid request"
]);

