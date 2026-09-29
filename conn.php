<?php
// header("Content-Type: application/json");

// $host = "localhost";
// $user = "root";
// $password = "";
// $database = "mobile_health"; // BADAL magaca DB-gaaga

// $connectNow = new mysqli($host, $user, $password, $database, 3308);

// if ($connectNow->connect_error) {
//     die(json_encode([
//         "success" => false,
//         "message" => "Database connection failed mohamed497!123"
//     ]));
// }



header("Content-Type: application/json");

$host = getenv("MYSQLHOST");
$user = getenv("MYSQLUSER");
$password = getenv("MYSQLPASSWORD");
$database = getenv("MYSQL_DATABASE");
$port = getenv("MYSQLPORT") ?: 3306;

$connectNow = new mysqli(
    $host,
    $user,
    $password,
    $database,
    (int)$port
);

if ($connectNow->connect_error) {
    die(json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]));
}

$connectNow->set_charset("utf8mb4");


?>