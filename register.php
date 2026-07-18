<?php
header("Content-Type: application/json");
include "conn.php";

// ── Hubi xogta la keenay ──
$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$phone     = isset($_POST['phone'])     ? trim($_POST['phone'])     : '';
$email     = isset($_POST['email'])     ? trim($_POST['email'])     : '';
$rawPass   = isset($_POST['password'])  ? $_POST['password']        : '';

if ($full_name === '' || $phone === '' || $email === '' || $rawPass === '') {
    echo json_encode(["success" => false, "message" => "All fields are required"]);
    exit();
}

// ✅ ILAALIN AMNI: is-diiwaangelinta caamka ah waa MOTHER kaliya.
// Xataa haddii la diro role=doctor/admin (Postman, iwm.) waa la diidayaa.
$role = 'mother';

$password = password_hash($rawPass, PASSWORD_DEFAULT);

// ── Hubi in email-ku horey loo isticmaalin ──
$check = $connectNow->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Email already registered"]);
    exit();
}

// ── Geli user-ka cusub ──
$sql = "INSERT INTO users (full_name, phone, email, password, role)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $connectNow->prepare($sql);
$stmt->bind_param("sssss", $full_name, $phone, $email, $password, $role);

if ($stmt->execute()) {
    $userId = $stmt->insert_id;

    // ✅ Saf madhan mothers ah u abuur si admin-ku markii dambe
    //    xogta uurka (date of birth, blood group, iwm.) uga buuxiyo.
    $mStmt = $connectNow->prepare("INSERT INTO mothers (user_id) VALUES (?)");
    $mStmt->bind_param("i", $userId);
    $mStmt->execute();

    echo json_encode([
        "success"  => true,
        "message"  => "User registered",
        "user_id"  => $userId,
        "role"     => $role
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error registering user"
    ]);
}

$connectNow->close();
?>