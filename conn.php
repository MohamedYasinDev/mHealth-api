<?php
header("Content-Type: application/json");

$host = "localhost";
$user = "root";
$password = "";
$database = "mobile_health"; // BADAL magaca DB-gaaga

$connectNow = new mysqli($host, $user, $password, $database, 3308);

if ($connectNow->connect_error) {
    die(json_encode([
        "success" => false,
        "message" => "Database connection failed mohamed497!123"
    ]));
}
?>