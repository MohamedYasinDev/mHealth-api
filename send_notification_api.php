<?php
// C:\xampp\htdocs\mHealth_api\send_notification_api.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include "conn.php";

if (!$connectNow) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// ==================== GET — Fetch sent notifications by sender_id ====================
if ($method === 'GET') {
    $sender_id = isset($_GET['sender_id']) ? intval($_GET['sender_id']) : 0;

    if ($sender_id <= 0) {
        echo json_encode(["success" => false, "message" => "sender_id required"]);
        exit();
    }

    $sql = "SELECT n.*,
                   u.full_name as patient_name,
                   u.phone     as patient_phone
            FROM notifications n
            LEFT JOIN users u ON n.user_id = u.id
            WHERE n.sender_id = ?
            ORDER BY n.created_at DESC";

    $stmt = $connectNow->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "SQL Error: " . $connectNow->error]);
        exit();
    }

    $stmt->bind_param("i", $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id'           => (int)$row['id'],
            'user_id'      => (int)$row['user_id'],
            'sender_id'    => (int)$row['sender_id'],
            'title'        => $row['title'],
            'message'      => $row['message'],
            'type'         => $row['type'],
            'is_read'      => (int)$row['is_read'],
            'created_at'   => $row['created_at'],
            'patient_name' => $row['patient_name'] ?? 'Unknown',
            'patient_phone'=> $row['patient_phone'] ?? '',
        ];
    }

    echo json_encode([
        "success"       => true,
        "notifications" => $notifications,
        "count"         => count($notifications)
    ]);
    exit();
}

// ==================== POST — Doctor sends notification to patient ====================
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode(["success" => false, "message" => "Invalid JSON data"]);
        exit();
    }

    $user_id   = isset($data['user_id'])   ? intval($data['user_id'])   : 0;
    $sender_id = isset($data['sender_id']) ? intval($data['sender_id']) : null;
    $title     = isset($data['title'])     ? trim($data['title'])       : '';
    $message   = isset($data['message'])   ? trim($data['message'])     : '';
    $type      = isset($data['type'])      ? trim($data['type'])        : 'general';

    // Validate required fields
    if ($user_id <= 0 || empty($title) || empty($message)) {
        echo json_encode([
            "success" => false,
            "message" => "Missing required fields: user_id, title, message"
        ]);
        exit();
    }

    // Validate type
    $validTypes = ['danger', 'medication', 'appointment', 'reminder', 'lab_test', 'general'];
    if (!in_array($type, $validTypes)) {
        $type = 'general';
    }

    // Check patient exists
    $check = $connectNow->prepare("SELECT id FROM users WHERE id = ? AND role = 'mother'");
    $check->bind_param("i", $user_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Patient not found"]);
        exit();
    }

    // Insert notification with sender_id
    $sql  = "INSERT INTO notifications
                (user_id, sender_id, title, message, type, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())";
    $stmt = $connectNow->prepare($sql);

    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "SQL Error: " . $connectNow->error]);
        exit();
    }

    $stmt->bind_param("iisss", $user_id, $sender_id, $title, $message, $type);

    if ($stmt->execute()) {
        echo json_encode([
            "success"         => true,
            "message"         => "Notification sent successfully",
            "notification_id" => $stmt->insert_id
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to send: " . $stmt->error
        ]);
    }
    exit();
}

// ==================== PATCH — Mark sent notification as read ====================
if ($method === 'PATCH') {
    $data = json_decode(file_get_contents("php://input"), true);

    $id        = isset($data['id'])        ? intval($data['id'])        : 0;
    $sender_id = isset($data['sender_id']) ? intval($data['sender_id']) : 0;

    if ($id <= 0) {
        echo json_encode(["success" => false, "message" => "Notification ID required"]);
        exit();
    }

    $sql  = "UPDATE notifications SET is_read = 1 WHERE id = ? AND sender_id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("ii", $id, $sender_id);
    $stmt->execute();

    echo json_encode([
        "success" => $stmt->affected_rows > 0,
        "message" => $stmt->affected_rows > 0
            ? "Marked as read"
            : "Not found or already read"
    ]);
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
$connectNow->close();
?>