<?php
// C:\xampp\htdocs\mHealth_api\doctor_create_lab_test_api.php
// Dhakhtarku wuxuu ku dari karaa lab test cusub bukaankiisa — admin looma baahna.
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include "conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);
if (!is_array($data)) {
    echo json_encode(["success" => false, "message" => "Invalid request body"]);
    exit();
}

// ── Validation ──────────────────────────────────────────────
$doctorId = isset($data['doctor_id']) ? intval($data['doctor_id']) : 0;
$userId   = isset($data['user_id']) ? intval($data['user_id']) : 0;
$testName = isset($data['test_name']) ? trim($data['test_name']) : '';

if ($doctorId <= 0) {
    echo json_encode(["success" => false, "message" => "Valid doctor_id is required"]);
    exit();
}
if ($userId <= 0) {
    echo json_encode(["success" => false, "message" => "Valid user_id (patient) is required"]);
    exit();
}
if ($testName === '') {
    echo json_encode(["success" => false, "message" => "Test name is required"]);
    exit();
}

// Hubi in dhakhtarku jiro oo firfircoon yahay
$cd = $connectNow->prepare("SELECT id FROM doctors WHERE id = ? AND is_active = TRUE");
$cd->bind_param("i", $doctorId);
$cd->execute();
if ($cd->get_result()->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Doctor not found or inactive"]);
    exit();
}

// Hubi in bukaanku jiro
$cu = $connectNow->prepare("SELECT id FROM users WHERE id = ?");
$cu->bind_param("i", $userId);
$cu->execute();
if ($cu->get_result()->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Patient not found"]);
    exit();
}

// ── Fields ──────────────────────────────────────────────────
$testDate    = (isset($data['test_date']) && trim($data['test_date']) !== '')
                ? trim($data['test_date']) : date('Y-m-d');
$result      = isset($data['result']) && trim($data['result']) !== '' ? trim($data['result']) : null;
$status      = isset($data['status']) && trim($data['status']) !== '' ? trim($data['status']) : 'ordered';
$notes       = isset($data['notes']) && trim($data['notes']) !== '' ? trim($data['notes']) : null;
$testType    = isset($data['test_type']) && trim($data['test_type']) !== '' ? trim($data['test_type']) : 'text';
$resultValue = (isset($data['result_value']) && $data['result_value'] !== '' && $data['result_value'] !== null)
                ? floatval($data['result_value']) : null;
$unit        = isset($data['unit']) && trim($data['unit']) !== '' ? trim($data['unit']) : null;

// Hubi status sax ah
if (!in_array($status, ['ordered', 'completed'])) {
    $status = 'ordered';
}

// ── Insert ──────────────────────────────────────────────────
$sql = "INSERT INTO lab_tests
        (user_id, doctor_id, test_name, test_date, result, status, notes, test_type, result_value, unit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $connectNow->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "SQL error: " . $connectNow->error]);
    exit();
}

// types: user_id i, doctor_id i, test_name s, test_date s, result s,
//        status s, notes s, test_type s, result_value d, unit s
$stmt->bind_param(
    "iissssssds",
    $userId, $doctorId, $testName, $testDate, $result,
    $status, $notes, $testType, $resultValue, $unit
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Lab test created successfully",
        "lab_test_id" => $stmt->insert_id
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
}

$connectNow->close();
?>