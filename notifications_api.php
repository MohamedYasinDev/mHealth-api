<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

ob_clean();
include "conn.php";

if (!$connectNow || $connectNow->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// Helper: verify admin
function verifyAdmin($email, $conn) {
    if (empty($email)) return false;
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Get admin email from headers
$adminEmail = '';
$headers = getallheaders();
if (isset($headers['X-Admin-Email'])) $adminEmail = $headers['X-Admin-Email'];
elseif (isset($headers['x-admin-email'])) $adminEmail = $headers['x-admin-email'];
elseif (isset($headers['Admin-Email'])) $adminEmail = $headers['Admin-Email'];
elseif (isset($_SERVER['HTTP_X_ADMIN_EMAIL'])) $adminEmail = $_SERVER['HTTP_X_ADMIN_EMAIL'];

// ==================== GET ====================
if ($method === 'GET') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $sender_id = isset($_GET['sender_id']) ? intval($_GET['sender_id']) : 0;
    $type = isset($_GET['type']) ? trim($_GET['type']) : '';
    $is_read = isset($_GET['is_read']) ? intval($_GET['is_read']) : -1;

    // ── Single notification ──
    if ($id > 0) {
        $sql = "SELECT n.*,
                       u.full_name as user_name,
                       u.full_name as patient_name,
                       u_sender.full_name as sender_name
                FROM notifications n
                LEFT JOIN users u ON n.user_id = u.id
                LEFT JOIN users u_sender ON n.sender_id = u_sender.id
                WHERE n.id = ?";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['is_read'] = (bool)$row['is_read'];
            if (isset($row['sender_id'])) $row['sender_id'] = (int)$row['sender_id'];
            echo json_encode(["success" => true, "notification" => $row]);
        } else {
            echo json_encode(["success" => false, "message" => "Not found"]);
        }
        exit();
    }

    // ── SENT (sender_id = qofka) ──
    if ($sender_id > 0) {
        $sql = "SELECT n.*,
                       u_recipient.full_name as recipient_name,
                       u_recipient.full_name as patient_name,
                       u_sender.full_name as sender_name
                FROM notifications n
                LEFT JOIN users u_recipient ON n.user_id = u_recipient.id
                LEFT JOIN users u_sender ON n.sender_id = u_sender.id
                WHERE n.sender_id = ?
                ORDER BY n.created_at DESC";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $sender_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['is_read'] = (bool)$row['is_read'];
            if (isset($row['sender_id'])) $row['sender_id'] = (int)$row['sender_id'];
            $notifications[] = $row;
        }

        echo json_encode([
            "success" => true,
            "notifications" => $notifications,
            "count" => count($notifications)
        ]);
        exit();
    }

    // ── RECEIVED (user_id = qofka) ──
    if ($user_id > 0) {
        $checkUser = $connectNow->prepare("SELECT id FROM users WHERE id = ?");
        $checkUser->bind_param("i", $user_id);
        $checkUser->execute();
        if ($checkUser->get_result()->num_rows == 0) {
            echo json_encode(["success" => false, "message" => "User not found"]);
            exit();
        }

        $sql = "SELECT n.*,
                       u_sender.full_name as sender_name,
                       u_sender.full_name as patient_name
                FROM notifications n
                LEFT JOIN users u_sender ON n.sender_id = u_sender.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['is_read'] = (bool)$row['is_read'];
            if (isset($row['sender_id'])) $row['sender_id'] = (int)$row['sender_id'];
            $notifications[] = $row;
        }

        echo json_encode([
            "success" => true,
            "notifications" => $notifications,
            "count" => count($notifications)
        ]);
        exit();
    }

    // ── Admin: all notifications ──
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized"]);
        exit();
    }

    $sql = "SELECT n.*, u.full_name as user_name, u.phone as user_phone,
                   u.full_name as patient_name,
                   u_sender.full_name as sender_name
            FROM notifications n
            LEFT JOIN users u ON n.user_id = u.id
            LEFT JOIN users u_sender ON n.sender_id = u_sender.id
            WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($type)) {
        $sql .= " AND n.type = ?";
        $params[] = $type;
        $types .= "s";
    }
    if ($is_read !== -1) {
        $sql .= " AND n.is_read = ?";
        $params[] = $is_read;
        $types .= "i";
    }
    $sql .= " ORDER BY n.created_at DESC";

    $stmt = $connectNow->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['user_id'] = (int)$row['user_id'];
        $row['is_read'] = (bool)$row['is_read'];
        if (isset($row['sender_id'])) $row['sender_id'] = (int)$row['sender_id'];
        $notifications[] = $row;
    }

    echo json_encode(["success" => true, "notifications" => $notifications, "count" => count($notifications)]);
    exit();
}

// ==================== POST ====================
if ($method === 'POST') {
    // ── Mark all as read (per user OR admin) ──
    if (strpos($_SERVER['REQUEST_URI'], 'mark_all_read') !== false) {
        $data = json_decode(file_get_contents("php://input"), true);
        $uid = isset($_GET['user_id']) ? intval($_GET['user_id'])
             : (isset($data['user_id']) ? intval($data['user_id']) : 0);

        if ($uid > 0) {
            $stmt = $connectNow->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->bind_param("i", $uid);
        } else {
            if (!verifyAdmin($adminEmail, $connectNow)) {
                echo json_encode(["success" => false, "message" => "Unauthorized"]);
                exit();
            }
            $stmt = $connectNow->prepare("UPDATE notifications SET is_read = 1");
        }
        if ($stmt->execute()) echo json_encode(["success" => true, "message" => "All marked read"]);
        else echo json_encode(["success" => false, "message" => $stmt->error]);
        exit();
    }

    // ── Create notification (admin only) ──
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized"]);
        exit();
    }
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['user_id']) || !isset($data['title']) || !isset($data['message']) || !isset($data['type'])) {
        echo json_encode(["success" => false, "message" => "Missing fields"]);
        exit();
    }
    $user_id = intval($data['user_id']);
    $type = trim($data['type']);
    $title = trim($data['title']);
    $message = trim($data['message']);
    $is_read = isset($data['is_read']) ? intval($data['is_read']) : 0;
    $scheduled_for = isset($data['scheduled_for']) ? $data['scheduled_for'] : null;

    // ✅ SENDER_ID — admin-ka diray (sidaa Sent tab uu uga muuqdo)
    $sender_id = isset($data['sender_id']) && intval($data['sender_id']) > 0
        ? intval($data['sender_id'])
        : null;

    $stmt = $connectNow->prepare(
        "INSERT INTO notifications
            (user_id, sender_id, type, title, message, is_read, scheduled_for)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    // i = int, s = string
    // types:  user_id(i)  sender_id(i)  type(s)  title(s)  message(s)  is_read(i)  scheduled_for(s)
    $stmt->bind_param("iisssis",
        $user_id, $sender_id, $type, $title, $message, $is_read, $scheduled_for);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Created",
            "notification_id" => $stmt->insert_id,
            "sender_id" => $sender_id
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Insert failed: " . $stmt->error]);
    }
    exit();
}

// ==================== PUT ====================
if ($method === 'PUT') {
    if (!verifyAdmin($adminEmail, $connectNow)) { echo json_encode(["success"=>false,"message"=>"Unauthorized"]); exit(); }
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) { echo json_encode(["success"=>false,"message"=>"ID required"]); exit(); }
    $id = intval($data['id']);
    $fields = [];
    $params = [];
    $types = "";
    if (isset($data['user_id'])) { $fields[] = "user_id=?"; $params[] = intval($data['user_id']); $types .= "i"; }
    if (isset($data['sender_id'])) { $fields[] = "sender_id=?"; $params[] = intval($data['sender_id']); $types .= "i"; }
    if (isset($data['type'])) { $fields[] = "type=?"; $params[] = $data['type']; $types .= "s"; }
    if (isset($data['title'])) { $fields[] = "title=?"; $params[] = $data['title']; $types .= "s"; }
    if (isset($data['message'])) { $fields[] = "message=?"; $params[] = $data['message']; $types .= "s"; }
    if (isset($data['is_read'])) { $fields[] = "is_read=?"; $params[] = intval($data['is_read']); $types .= "i"; }
    if (isset($data['scheduled_for'])) { $fields[] = "scheduled_for=?"; $params[] = $data['scheduled_for']; $types .= "s"; }
    if (empty($fields)) { echo json_encode(["success"=>false,"message"=>"No fields"]); exit(); }
    $params[] = $id;
    $types .= "i";
    $sql = "UPDATE notifications SET " . implode(", ", $fields) . " WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) echo json_encode(["success"=>true,"message"=>"Updated"]);
    else echo json_encode(["success"=>false,"message"=>$stmt->error]);
    exit();
}

// ==================== DELETE ====================
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) { echo json_encode(["success"=>false,"message"=>"ID required"]); exit(); }
    $id = intval($data['id']);
    $requester_id = isset($data['requester_id']) ? intval($data['requester_id']) : 0;

    if (verifyAdmin($adminEmail, $connectNow)) {
        $stmt = $connectNow->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->bind_param("i", $id);
    } elseif ($requester_id > 0) {
        $stmt = $connectNow->prepare(
            "DELETE FROM notifications WHERE id = ? AND (user_id = ? OR sender_id = ?)");
        $stmt->bind_param("iii", $id, $requester_id, $requester_id);
    } else {
        echo json_encode(["success"=>false,"message"=>"Unauthorized"]);
        exit();
    }

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success"=>true,"message"=>"Deleted"]);
        } else {
            echo json_encode(["success"=>false,"message"=>"Not found or not allowed"]);
        }
    } else {
        echo json_encode(["success"=>false,"message"=>$stmt->error]);
    }
    exit();
}

// ==================== PATCH (mark as read) ====================
if ($method === 'PATCH') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) { echo json_encode(["success"=>false,"message"=>"ID required"]); exit(); }
    $id = intval($data['id']);
    $stmt = $connectNow->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) echo json_encode(["success"=>true,"message"=>"Marked as read"]);
    else echo json_encode(["success"=>false,"message"=>$stmt->error]);
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
$connectNow->close();
?>