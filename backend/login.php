<?php
session_start();

/* =========================
   HEADERS (IMPORTANT FOR REACT)
========================= */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

/* handle preflight request */
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

/* =========================
   DB CONNECTION
========================= */
$conn = new mysqli("localhost", "root", "", "econolink_db");

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

/* =========================
   INPUT
========================= */
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === "" || $password === "") {
    echo json_encode([
        "status" => "error",
        "message" => "Empty fields"
    ]);
    exit;
}

/* =========================
   QUERY USER
========================= */
$stmt = $conn->prepare("
    SELECT ua.id, ua.username, ua.password, ua.role, ud.status
    FROM user_accounts ua
    LEFT JOIN user_details ud ON ua.id = ud.account_id
    WHERE ua.username = ?
");

$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* =========================
   VALIDATIONS
========================= */
if (!$user) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid username"
    ]);
    exit;
}

/* employee check */
if ($user['role'] === 'employee' && strtolower($user['status']) !== 'active') {
    echo json_encode([
        "status" => "error",
        "message" => "Account inactive"
    ]);
    exit;
}

/* password check */
if (password_verify($password, $user['password']) || $password === $user['password']) {

    /* SESSION SET */
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = strtolower($user['role']);

    echo json_encode([
        "status" => "success",
        "role" => strtolower($user['role'])
    ]);

} else {
    echo json_encode([
        "status" => "error",
        "message" => "Wrong password"
    ]);
}

$conn->close();