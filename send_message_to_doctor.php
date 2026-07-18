<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

ob_clean();
require_once "conn.php";

if (!isset($connectNow) || $connectNow->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit();
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data || !isset($data['mother_user_id']) || !isset($data['doctor_user_id'])) {
    echo json_encode(["success" => false, "message" => "mother_user_id and doctor_user_id required"]);
    exit();
}

$mother_user_id = intval($data['mother_user_id']);
$doctor_user_id = intval($data['doctor_user_id']);
$title = trim($data['title'] ?? '');
$message = trim($data['message'] ?? '');
$type = in_array($data['type'] ?? '', ['danger','medication','appointment','reminder','lab_test','general']) ? $data['type'] : 'general';

if (empty($title) || empty($message)) {
    echo json_encode(["success" => false, "message" => "Title and message required"]);
    exit();
}

// Check if doctor exists (users table with role='doctor')
$checkDoctor = $connectNow->prepare("SELECT id FROM users WHERE id = ? AND role = 'doctor'");
$checkDoctor->bind_param("i", $doctor_user_id);
$checkDoctor->execute();
$checkDoctor->store_result();
if ($checkDoctor->num_rows == 0) {
    echo json_encode(["success" => false, "message" => "Doctor not found"]);
    exit();
}
$checkDoctor->close();

// Insert notification (user_id = doctor, sender_id = mother)
$is_read = 0;
$sql = "INSERT INTO notifications (user_id, sender_id, title, message, type, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())";
$stmt = $connectNow->prepare($sql);
$stmt->bind_param("iisssi", $doctor_user_id, $mother_user_id, $title, $message, $type, $is_read);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Message sent to doctor"]);
} else {
    echo json_encode(["success" => false, "message" => "Insert failed: " . $stmt->error]);
}
$stmt->close();
$connectNow->close();
?>