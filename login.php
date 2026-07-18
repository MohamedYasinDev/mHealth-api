<?php
header("Content-Type: application/json");
include "conn.php";

if (!isset($_POST['email']) || !isset($_POST['password'])) {
    echo json_encode([
        "success" => false,
        "message" => "Email and password required"
    ]);
    exit();
}

$email = $_POST['email'];
$password = $_POST['password'];

// Query user by email
$sql = "SELECT id, full_name, email, phone, password, role 
        FROM users 
        WHERE email = ?";

$stmt = $connectNow->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    $user = $result->fetch_assoc();

    // CHECK PASSWORD
    if (password_verify($password, $user['password'])) {

        unset($user['password']);

        echo json_encode([
            "success" => true,
            "userData" => $user
        ]);

    } else {
        echo json_encode([
            "success" => false,
            "message" => "Wrong email or password"
        ]);
    }

} else {

    echo json_encode([
        "success" => false,
        "message" => "Wrong email or password"
    ]);
}
?>