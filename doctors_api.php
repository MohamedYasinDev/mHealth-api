<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

include "conn.php";

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$headers = getallheaders();
error_log("=== DOCTORS API CALL ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Query String: " . $_SERVER['QUERY_STRING']);

$method = $_SERVER['REQUEST_METHOD'];

// ==================== VERIFY ADMIN FUNCTION ====================
function verifyAdmin($email, $conn) {
    if (empty($email)) return false;
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// ==================== GET ADMIN EMAIL FROM HEADERS ====================
$adminEmail = '';
if (isset($headers['X-Admin-Email']))          $adminEmail = $headers['X-Admin-Email'];
elseif (isset($headers['x-admin-email']))      $adminEmail = $headers['x-admin-email'];
elseif (isset($headers['Admin-Email']))        $adminEmail = $headers['Admin-Email'];
elseif (isset($_SERVER['HTTP_X_ADMIN_EMAIL'])) $adminEmail = $_SERVER['HTTP_X_ADMIN_EMAIL'];

// ==================== FORMAT DOCTOR ROW ====================
function formatDoctor($row) {
    $row['id']                        = (int)$row['id'];
    $row['user_id']                   = $row['user_id'] ? (int)$row['user_id'] : null;
    $row['hospital_id']               = $row['hospital_id'] ? (int)$row['hospital_id'] : null;
    $row['average_rating']            = (float)$row['average_rating'];
    $row['available_for_telemedicine'] = (bool)$row['available_for_telemedicine'];
    $row['is_active']                 = (bool)$row['is_active'];
    return $row;
}

// ==================================================================
// GET REQUESTS
// ==================================================================
if ($method === 'GET') {

    // ---- GET DOCTOR BY user_id (for logged-in doctor) ----
    if (isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
        $sql = "SELECT d.*, h.name as hospital_name
                FROM doctors d
                LEFT JOIN hospitals h ON d.hospital_id = h.id
                WHERE d.user_id = ?";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode([
                "success" => true,
                "doctor"  => formatDoctor($result->fetch_assoc())
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Doctor not found"]);
        }
        exit();
    }

    // ---- GET DOCTOR BY doctors.id ----
    if (isset($_GET['id'])) {
        $id  = intval($_GET['id']);
        $sql = "SELECT d.*, h.name as hospital_name
                FROM doctors d
                LEFT JOIN hospitals h ON d.hospital_id = h.id
                WHERE d.id = ? AND d.is_active = 1";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode([
                "success" => true,
                "doctor"  => formatDoctor($result->fetch_assoc())
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Doctor not found"]);
        }
        exit();
    }

    // ---- GET ALL DOCTORS (admin sees all, public sees active only) ----
    $isAdmin = verifyAdmin($adminEmail, $connectNow);

    if ($isAdmin) {
        // Admin: show all doctors including inactive
        $sql = "SELECT d.*, h.name as hospital_name
                FROM doctors d
                LEFT JOIN hospitals h ON d.hospital_id = h.id
                ORDER BY d.full_name ASC";
        $result = $connectNow->query($sql);
    } else {
        // Public / mother: active doctors only
        $sql = "SELECT d.*, h.name as hospital_name
                FROM doctors d
                LEFT JOIN hospitals h ON d.hospital_id = h.id
                WHERE d.is_active = 1
                ORDER BY d.full_name ASC";
        $result = $connectNow->query($sql);
    }

    $doctors = [];
    while ($row = $result->fetch_assoc()) {
        $doctors[] = formatDoctor($row);
    }

    echo json_encode([
        "success" => true,
        "doctors" => $doctors,
        "count"   => count($doctors)
    ]);
    exit();
}

// ==================================================================
// POST — ADD NEW DOCTOR (Admin only)
// ==================================================================
if ($method === 'POST') {
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can add doctors"]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) $data = $_POST;

    // Validate required fields
    $required = ['full_name', 'email', 'specialization', 'phone'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            echo json_encode(["success" => false, "message" => ucfirst(str_replace('_', ' ', $field)) . " required"]);
            exit();
        }
    }

    $full_name     = trim($data['full_name']);
    $email         = trim($data['email']);
    $specialization = trim($data['specialization']);
    $phone         = trim($data['phone']);
    $hospital_id   = isset($data['hospital_id']) && !empty($data['hospital_id']) ? intval($data['hospital_id']) : null;
    $available_for_telemedicine = isset($data['available_for_telemedicine']) ? (int)$data['available_for_telemedicine'] : 0;
    $average_rating = isset($data['average_rating']) ? floatval($data['average_rating']) : 0;
    $is_active     = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    
    // ✅ NEW: Get password from request or use default
    $plainPassword = isset($data['password']) && !empty(trim($data['password'])) 
        ? trim($data['password']) 
        : 'Doctor@123';

    // Validate password length if provided
    if ($plainPassword !== 'Doctor@123' && strlen($plainPassword) < 6) {
        echo json_encode(["success" => false, "message" => "Password must be at least 6 characters"]);
        exit();
    }

    // Check duplicate phone in doctors
    $stmt = $connectNow->prepare("SELECT id FROM doctors WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Phone number already exists"]);
        exit();
    }

    // Check duplicate email in doctors
    $stmt = $connectNow->prepare("SELECT id FROM doctors WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Email already exists in doctors"]);
        exit();
    }

    // Check duplicate email in users
    $stmt = $connectNow->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Email already exists in users"]);
        exit();
    }

    // ✅ Step 1: Create user account for doctor
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    $role         = 'doctor';

    $stmt = $connectNow->prepare("INSERT INTO users (full_name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $full_name, $phone, $email, $hashedPassword, $role);

    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => "Error creating user account: " . $stmt->error]);
        exit();
    }

    $user_id = $connectNow->insert_id;

    // ✅ Step 2: Insert into doctors table with user_id
    $sql  = "INSERT INTO doctors (user_id, hospital_id, full_name, email, specialization, phone, available_for_telemedicine, average_rating, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("iissssidi",
        $user_id, $hospital_id, $full_name, $email,
        $specialization, $phone, $available_for_telemedicine,
        $average_rating, $is_active
    );

    if ($stmt->execute()) {
        echo json_encode([
            "success"       => true,
            "message"       => "Doctor added successfully",
            "doctor_id"     => $stmt->insert_id,
            "user_id"       => $user_id,
            "temp_password" => $plainPassword
        ]);
    } else {
        // Rollback: delete user if doctor insert failed
        $connectNow->query("DELETE FROM users WHERE id = $user_id");
        echo json_encode(["success" => false, "message" => "Error adding doctor: " . $stmt->error]);
    }
    exit();
}

// ==================================================================
// PUT — UPDATE DOCTOR (Admin only)
// ==================================================================
if ($method === 'PUT') {
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can update doctors"]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Doctor ID required"]);
        exit();
    }

    $id             = intval($data['id']);
    $full_name      = trim($data['full_name'] ?? '');
    $email          = trim($data['email'] ?? '');
    $specialization = trim($data['specialization'] ?? '');
    $phone          = trim($data['phone'] ?? '');
    $hospital_id    = isset($data['hospital_id']) && !empty($data['hospital_id']) ? intval($data['hospital_id']) : null;
    $available_for_telemedicine = isset($data['available_for_telemedicine']) ? (int)$data['available_for_telemedicine'] : 0;
    $is_active      = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    
    // Optional: Update password if provided
    $newPassword = isset($data['password']) && !empty(trim($data['password'])) ? trim($data['password']) : null;

    // Get current doctor to find user_id
    $stmt = $connectNow->prepare("SELECT user_id FROM doctors WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $doctorRow = $stmt->get_result()->fetch_assoc();

    if (!$doctorRow) {
        echo json_encode(["success" => false, "message" => "Doctor not found"]);
        exit();
    }

    $user_id = $doctorRow['user_id'];

    // ✅ Update doctors table
    $sql  = "UPDATE doctors SET
                full_name = ?, email = ?, specialization = ?, phone = ?,
                hospital_id = ?, available_for_telemedicine = ?, is_active = ?
             WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("ssssiiii",
        $full_name, $email, $specialization, $phone,
        $hospital_id, $available_for_telemedicine, $is_active, $id
    );
    $stmt->execute();

    // ✅ Update password if provided
    if ($newPassword !== null && $user_id) {
        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt2 = $connectNow->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt2->bind_param("si", $hashedNewPassword, $user_id);
        $stmt2->execute();
    }

    // ✅ Also update users table (keep in sync)
    if ($user_id) {
        $stmt2 = $connectNow->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?");
        $stmt2->bind_param("sssi", $full_name, $phone, $email, $user_id);
        $stmt2->execute();
    }

    echo json_encode(["success" => true, "message" => "Doctor updated successfully"]);
    exit();
}

// ==================================================================
// DELETE — DELETE DOCTOR (Admin only)
// ==================================================================
if ($method === 'DELETE') {
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can delete doctors"]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Doctor ID required"]);
        exit();
    }

    $id = intval($data['id']);

    // Get user_id before deleting
    $stmt = $connectNow->prepare("SELECT user_id FROM doctors WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $doctorRow = $stmt->get_result()->fetch_assoc();

    if (!$doctorRow) {
        echo json_encode(["success" => false, "message" => "Doctor not found"]);
        exit();
    }

    $user_id = $doctorRow['user_id'];

    // ✅ Delete from doctors table
    $stmt = $connectNow->prepare("DELETE FROM doctors WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // ✅ Also delete from users table
        if ($user_id) {
            $connectNow->query("DELETE FROM users WHERE id = $user_id");
        }
        echo json_encode(["success" => true, "message" => "Doctor deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error deleting doctor: " . $stmt->error]);
    }
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
$connectNow->close();
?>