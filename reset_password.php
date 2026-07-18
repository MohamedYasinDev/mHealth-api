<?php
// C:\xampp\htdocs\mHealth_api\reset_password.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include "conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$data        = json_decode(file_get_contents("php://input"), true);
$token       = trim($data['token']        ?? '');
$newPassword = trim($data['new_password'] ?? '');
$confirmPass = trim($data['confirm_password'] ?? '');

// Validation
if (empty($token)) {
    echo json_encode(["success" => false, "message" => "Reset token required"]);
    exit();
}
if (empty($newPassword)) {
    echo json_encode(["success" => false, "message" => "New password required"]);
    exit();
}
if (strlen($newPassword) < 6) {
    echo json_encode(["success" => false, "message" => "Password must be at least 6 characters"]);
    exit();
}
if ($newPassword !== $confirmPass) {
    echo json_encode(["success" => false, "message" => "Passwords do not match"]);
    exit();
}

// Check token
$stmt = $connectNow->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Invalid or expired reset link. Please request a new one."]);
    exit();
}

$reset     = $result->fetch_assoc();
$expiresAt = strtotime($reset['expires_at']);

if (time() > $expiresAt) {
    // Delete expired token
    $del = $connectNow->prepare("DELETE FROM password_resets WHERE token = ?");
    $del->bind_param("s", $token);
    $del->execute();
    echo json_encode(["success" => false, "message" => "Reset link has expired. Please request a new one."]);
    exit();
}

$userId = $reset['user_id'];

// Update password
$hashed = password_hash($newPassword, PASSWORD_DEFAULT);
$upd    = $connectNow->prepare("UPDATE users SET password = ? WHERE id = ?");
$upd->bind_param("si", $hashed, $userId);

if (!$upd->execute()) {
    echo json_encode(["success" => false, "message" => "Failed to update password. Please try again."]);
    exit();
}

// Delete used token
$del2 = $connectNow->prepare("DELETE FROM password_resets WHERE token = ?");
$del2->bind_param("s", $token);
$del2->execute();

echo json_encode([
    "success" => true,
    "message" => "Password has been reset successfully! You can now login with your new password."
]);