<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Preflight (browser) — mobile-ku uma baahna laakiin waa wanaagsan
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include "conn.php";

error_log("========== UPDATE MOTHER PROFILE ==========");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);

$method = $_SERVER['REQUEST_METHOD'];

// Kaliya POST ama PUT ayaa la aqbalayaa
if ($method !== 'POST' && $method !== 'PUT') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

// Akhri JSON body-ga
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);
error_log("Body: " . print_r($data, true));

if (!is_array($data)) {
    echo json_encode(["success" => false, "message" => "Invalid request body"]);
    exit();
}

// ── Validation ──────────────────────────────────────────────
$userId = isset($data['user_id']) ? intval($data['user_id']) : 0;
$fullName = isset($data['full_name']) ? trim($data['full_name']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$phone = isset($data['phone']) ? trim($data['phone']) : '';

if ($userId <= 0) {
    echo json_encode(["success" => false, "message" => "Valid user_id is required"]);
    exit();
}
if ($fullName === '') {
    echo json_encode(["success" => false, "message" => "Full name is required"]);
    exit();
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "A valid email is required"]);
    exit();
}
if ($phone === '') {
    echo json_encode(["success" => false, "message" => "Phone is required"]);
    exit();
}

// ── Hubi in user-ku jiro oo uu hooyo yahay ──────────────────
$checkUser = $connectNow->prepare(
    "SELECT id FROM users WHERE id = ? AND role = 'mother'"
);
$checkUser->bind_param("i", $userId);
$checkUser->execute();
$userRes = $checkUser->get_result();
if ($userRes->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Mother not found"]);
    exit();
}

// ── Hubi in email-ku uusan qof KALE u qabsanayn ─────────────
$checkEmail = $connectNow->prepare(
    "SELECT id FROM users WHERE email = ? AND id != ?"
);
$checkEmail->bind_param("si", $email, $userId);
$checkEmail->execute();
$emailRes = $checkEmail->get_result();
if ($emailRes->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "This email is already used by another account"
    ]);
    exit();
}

// ── Update ──────────────────────────────────────────────────
$sql = "UPDATE users SET full_name = ?, email = ?, phone = ? 
        WHERE id = ? AND role = 'mother'";
$stmt = $connectNow->prepare($sql);
$stmt->bind_param("sssi", $fullName, $email, $phone, $userId);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Update failed: " . $stmt->error
    ]);
    exit();
}

// Soo celi xogta cusub si Flutter-ku u cusboonaysiiyo
echo json_encode([
    "success" => true,
    "message" => "Profile updated successfully",
    "user" => [
        "id" => $userId,
        "full_name" => $fullName,
        "email" => $email,
        "phone" => $phone
    ]
]);

$connectNow->close();
?>