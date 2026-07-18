<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include "conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit();
}

$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);
if (!$data) $data = $_POST;

// ========== 1. CHECK ADMIN (ONLY EMAIL, NO PASSWORD) ==========
$currentAdminEmail = isset($data['admin_email']) ? trim($data['admin_email']) : '';

if (empty($currentAdminEmail)) {
    echo json_encode(["success" => false, "message" => "Admin email required"]);
    exit();
}

$adminCheckSql = "SELECT id, role FROM users WHERE email = ? AND role = 'admin'";
$adminStmt = $connectNow->prepare($adminCheckSql);
$adminStmt->bind_param("s", $currentAdminEmail);
$adminStmt->execute();
$adminResult = $adminStmt->get_result();

if ($adminResult->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can create new admin accounts"]);
    exit();
}

// ========== 2. VALIDATE NEW ADMIN DATA ==========
$requiredFields = ['full_name', 'phone', 'email', 'password'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        echo json_encode(["success" => false, "message" => ucfirst(str_replace('_', ' ', $field)) . " is required"]);
        exit();
    }
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email format"]);
    exit();
}
if (strlen($data['phone']) < 10) {
    echo json_encode(["success" => false, "message" => "Phone must be at least 10 digits"]);
    exit();
}
if (strlen($data['password']) < 6) {
    echo json_encode(["success" => false, "message" => "Password must be at least 6 characters"]);
    exit();
}

// ========== 3. CHECK IF EMAIL/PHONE EXISTS ==========
$checkSql = "SELECT id FROM users WHERE email = ? OR phone = ?";
$checkStmt = $connectNow->prepare($checkSql);
$checkStmt->bind_param("ss", $data['email'], $data['phone']);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Email or phone already exists"]);
    exit();
}

// ========== 4. CREATE NEW ADMIN ==========
$full_name = trim($data['full_name']);
$phone = trim($data['phone']);
$email = trim($data['email']);
$hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
$role = 'admin';

$sql = "INSERT INTO users (full_name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)";
$stmt = $connectNow->prepare($sql);
$stmt->bind_param("sssss", $full_name, $phone, $email, $hashedPassword, $role);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "New admin account created successfully",
        "user" => [
            "id" => $stmt->insert_id,
            "full_name" => $full_name,
            "email" => $email,
            "phone" => $phone,
            "role" => "admin"
        ]
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
}

$stmt->close();
$connectNow->close();
?>