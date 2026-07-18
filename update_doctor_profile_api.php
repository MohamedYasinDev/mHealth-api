<?php
// C:\xampp\htdocs\mHealth_api\update_doctor_profile_api.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include "conn.php";

error_log("========== UPDATE DOCTOR PROFILE ==========");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);

$method = $_SERVER['REQUEST_METHOD'];

// Kaliya POST ama PUT
if ($method !== 'POST' && $method !== 'PUT') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

// Akhri JSON body
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);
error_log("Body: " . print_r($data, true));

if (!is_array($data)) {
    echo json_encode(["success" => false, "message" => "Invalid request body"]);
    exit();
}

// ── Validation ──────────────────────────────────────────────
// user_id ayaa la isticmaalaa (MAAHA doctors.id) — wuxuu la mid yahay
// qaabka dashboard-ka (getDoctorProfile wuxuu u gudbiyaa user_id).
$userId = isset($data['user_id']) ? intval($data['user_id']) : 0;
$fullName = isset($data['full_name']) ? trim($data['full_name']) : '';
$phone = isset($data['phone']) ? trim($data['phone']) : '';
$specialization = isset($data['specialization']) ? trim($data['specialization']) : '';

if ($userId <= 0) {
    echo json_encode(["success" => false, "message" => "Valid user_id is required"]);
    exit();
}
if ($fullName === '') {
    echo json_encode(["success" => false, "message" => "Full name is required"]);
    exit();
}
if ($specialization === '') {
    echo json_encode(["success" => false, "message" => "Specialization is required"]);
    exit();
}
if ($phone === '') {
    echo json_encode(["success" => false, "message" => "Phone is required"]);
    exit();
}

// ── Hubi in dhakhtarku jiro (users + doctors) ───────────────
$check = $connectNow->prepare(
    "SELECT d.id AS doctor_id, d.user_id
     FROM doctors d
     JOIN users u ON d.user_id = u.id
     WHERE d.user_id = ? AND u.role = 'doctor'"
);
$check->bind_param("i", $userId);
$check->execute();
$res = $check->get_result();
if ($res->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Doctor not found"]);
    exit();
}
$row = $res->fetch_assoc();
$doctorId = (int)$row['doctor_id'];

// ── Update users table (full_name, phone) ───────────────────
$sqlUser = "UPDATE users SET full_name = ?, phone = ? WHERE id = ?";
$stmtUser = $connectNow->prepare($sqlUser);
$stmtUser->bind_param("ssi", $fullName, $phone, $userId);
if (!$stmtUser->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Update failed (users): " . $stmtUser->error
    ]);
    exit();
}

// ── Update doctors table (specialization) ───────────────────
$sqlDoc = "UPDATE doctors SET specialization = ? WHERE id = ?";
$stmtDoc = $connectNow->prepare($sqlDoc);
$stmtDoc->bind_param("si", $specialization, $doctorId);
if (!$stmtDoc->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Update failed (doctors): " . $stmtDoc->error
    ]);
    exit();
}

// Soo celi xogta cusub
echo json_encode([
    "success" => true,
    "message" => "Profile updated successfully",
    "profile" => [
        "id" => $doctorId,
        "user_id" => $userId,
        "full_name" => $fullName,
        "phone" => $phone,
        "specialization" => $specialization
    ]
]);

$connectNow->close();
?>