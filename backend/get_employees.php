<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include "db.php";

$sql = "SELECT ud.*, ua.username 
        FROM user_details ud 
        LEFT JOIN user_accounts ua ON ud.account_id = ua.id 
        ORDER BY ud.id DESC";

$result = $conn->query($sql);

$employees = [];

while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

echo json_encode($employees);