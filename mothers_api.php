<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

include "conn.php";

// Log all incoming data for debugging
error_log("========== MOTHERS API CALL ==========");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);

$method = $_SERVER['REQUEST_METHOD'];

// Try multiple ways to get admin email
$adminEmail = '';

// 1. Try from headers (case sensitive)
$headers = getallheaders();
error_log("All Headers: " . print_r($headers, true));

if (isset($headers['X-Admin-Email'])) {
    $adminEmail = $headers['X-Admin-Email'];
    error_log("Found X-Admin-Email: " . $adminEmail);
} elseif (isset($headers['x-admin-email'])) {
    $adminEmail = $headers['x-admin-email'];
    error_log("Found x-admin-email: " . $adminEmail);
} elseif (isset($headers['Admin-Email'])) {
    $adminEmail = $headers['Admin-Email'];
    error_log("Found Admin-Email: " . $adminEmail);
}

// 2. Try from $_SERVER
if (empty($adminEmail) && isset($_SERVER['HTTP_X_ADMIN_EMAIL'])) {
    $adminEmail = $_SERVER['HTTP_X_ADMIN_EMAIL'];
    error_log("Found HTTP_X_ADMIN_EMAIL: " . $adminEmail);
}

// 3. Try from GET parameter (for testing)
if (empty($adminEmail) && isset($_GET['admin_email'])) {
    $adminEmail = $_GET['admin_email'];
    error_log("Found admin_email in GET: " . $adminEmail);
}

error_log("Final adminEmail: " . $adminEmail);

function verifyAdmin($email, $conn) {
    error_log("verifyAdmin called with email: " . $email);
    
    if (empty($email)) {
        error_log("Email is empty - returning false");
        return false;
    }
    
    $checkAdmin = "SELECT id, email, role FROM users WHERE email = ? AND role = 'admin'";
    $stmt = $conn->prepare($checkAdmin);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        error_log("Admin verified: " . $row['email'] . " - Role: " . $row['role']);
        return true;
    } else {
        error_log("No admin found with email: " . $email);
        return false;
    }
}

// ==================== GET ALL MOTHERS ====================
if ($method === 'GET') {
    error_log("Processing GET request - No admin verification needed");
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        $sql = "SELECT u.id, u.full_name, u.phone, u.email, u.role,
                       m.date_of_birth, m.blood_group, m.last_period_date, 
                       m.expected_delivery, m.emergency_contact_name, m.emergency_contact_phone
                FROM users u
                LEFT JOIN mothers m ON u.id = m.user_id
                WHERE u.id = ? AND u.role = 'mother'";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $mother = $result->fetch_assoc();
            $mother['id'] = (int)$mother['id'];
            echo json_encode(["success" => true, "mother" => $mother]);
        } else {
            echo json_encode(["success" => false, "message" => "Mother not found"]);
        }
        exit();
    }
    
    // Get all mothers
    $sql = "SELECT u.id, u.full_name, u.phone, u.email, u.role, u.created_at,
                   m.date_of_birth, m.blood_group, m.last_period_date, 
                   m.expected_delivery, m.emergency_contact_name, m.emergency_contact_phone
            FROM users u
            LEFT JOIN mothers m ON u.id = m.user_id
            WHERE u.role = 'mother'
            ORDER BY u.id DESC";
    $result = $connectNow->query($sql);
    
    $mothers = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $mothers[] = $row;
    }
    
    echo json_encode(["success" => true, "mothers" => $mothers, "count" => count($mothers)]);
    exit();
}

// ==================== ADD NEW MOTHER ====================
if ($method === 'POST') {
    error_log("Processing POST request - Checking admin");
    
    $isAdmin = verifyAdmin($adminEmail, $connectNow);
    error_log("isAdmin result: " . ($isAdmin ? "true" : "false"));
    
    if (!$isAdmin) {
        echo json_encode([
            "success" => false, 
            "message" => "Unauthorized. Only admin can add mothers. Email: " . $adminEmail
        ]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    error_log("POST Data: " . print_r($data, true));
    
    // Validate required fields
    if (!isset($data['full_name']) || empty(trim($data['full_name']))) {
        echo json_encode(["success" => false, "message" => "Full name required"]);
        exit();
    }
    if (!isset($data['phone']) || empty(trim($data['phone']))) {
        echo json_encode(["success" => false, "message" => "Phone number required"]);
        exit();
    }
    if (!isset($data['email']) || empty(trim($data['email']))) {
        echo json_encode(["success" => false, "message" => "Email required"]);
        exit();
    }
    if (!isset($data['password']) || empty(trim($data['password']))) {
        echo json_encode(["success" => false, "message" => "Password required"]);
        exit();
    }
    
    // Check if email exists
    $checkSql = "SELECT id FROM users WHERE email = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("s", $data['email']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Email already exists"]);
        exit();
    }
    
    // Insert into users table
    $full_name = trim($data['full_name']);
    $phone = trim($data['phone']);
    $email = trim($data['email']);
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $role = 'mother';
    
    $sql = "INSERT INTO users (full_name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("sssss", $full_name, $phone, $email, $password, $role);
    
    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => "Error creating user: " . $stmt->error]);
        exit();
    }
    
    $userId = $stmt->insert_id;
    
    // Insert into mothers table
    $date_of_birth = isset($data['date_of_birth']) && !empty($data['date_of_birth']) ? $data['date_of_birth'] : null;
    $blood_group = isset($data['blood_group']) && !empty($data['blood_group']) ? $data['blood_group'] : null;
    $last_period_date = isset($data['last_period_date']) && !empty($data['last_period_date']) ? $data['last_period_date'] : null;
    $expected_delivery = isset($data['expected_delivery']) && !empty($data['expected_delivery']) ? $data['expected_delivery'] : null;
    $emergency_contact_name = isset($data['emergency_contact_name']) && !empty($data['emergency_contact_name']) ? $data['emergency_contact_name'] : null;
    $emergency_contact_phone = isset($data['emergency_contact_phone']) && !empty($data['emergency_contact_phone']) ? $data['emergency_contact_phone'] : null;
    
    $sql2 = "INSERT INTO mothers (user_id, date_of_birth, blood_group, last_period_date, expected_delivery, emergency_contact_name, emergency_contact_phone) 
             VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt2 = $connectNow->prepare($sql2);
    $stmt2->bind_param("issssss", $userId, $date_of_birth, $blood_group, $last_period_date, $expected_delivery, $emergency_contact_name, $emergency_contact_phone);
    $stmt2->execute();
    
    echo json_encode(["success" => true, "message" => "Mother added successfully", "mother_id" => $userId]);
    exit();
}

// ==================== UPDATE MOTHER ====================
if ($method === 'PUT') {
    error_log("Processing PUT request - Checking admin");
    
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can update mothers"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Mother ID required"]);
        exit();
    }
    
    $id = intval($data['id']);
    
    // Update users table
    $updateFields = [];
    $params = [];
    $types = "";
    
    if (isset($data['full_name']) && !empty($data['full_name'])) {
        $updateFields[] = "full_name = ?";
        $params[] = trim($data['full_name']);
        $types .= "s";
    }
    if (isset($data['phone']) && !empty($data['phone'])) {
        $updateFields[] = "phone = ?";
        $params[] = trim($data['phone']);
        $types .= "s";
    }
    if (isset($data['email']) && !empty($data['email'])) {
        $updateFields[] = "email = ?";
        $params[] = trim($data['email']);
        $types .= "s";
    }
    
    if (!empty($updateFields)) {
        $params[] = $id;
        $types .= "i";
        $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ? AND role = 'mother'";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }
    
    // Update mothers table
    $motherFields = [];
    $motherParams = [];
    $motherTypes = "";
    
    if (isset($data['date_of_birth'])) {
        $motherFields[] = "date_of_birth = ?";
        $motherParams[] = !empty($data['date_of_birth']) ? $data['date_of_birth'] : null;
        $motherTypes .= "s";
    }
    if (isset($data['blood_group'])) {
        $motherFields[] = "blood_group = ?";
        $motherParams[] = !empty($data['blood_group']) ? $data['blood_group'] : null;
        $motherTypes .= "s";
    }
    if (isset($data['last_period_date'])) {
        $motherFields[] = "last_period_date = ?";
        $motherParams[] = !empty($data['last_period_date']) ? $data['last_period_date'] : null;
        $motherTypes .= "s";
    }
    if (isset($data['expected_delivery'])) {
        $motherFields[] = "expected_delivery = ?";
        $motherParams[] = !empty($data['expected_delivery']) ? $data['expected_delivery'] : null;
        $motherTypes .= "s";
    }
    if (isset($data['emergency_contact_name'])) {
        $motherFields[] = "emergency_contact_name = ?";
        $motherParams[] = !empty($data['emergency_contact_name']) ? $data['emergency_contact_name'] : null;
        $motherTypes .= "s";
    }
    if (isset($data['emergency_contact_phone'])) {
        $motherFields[] = "emergency_contact_phone = ?";
        $motherParams[] = !empty($data['emergency_contact_phone']) ? $data['emergency_contact_phone'] : null;
        $motherTypes .= "s";
    }
    
    if (!empty($motherFields)) {
        $motherParams[] = $id;
        $motherTypes .= "i";
        $sql2 = "UPDATE mothers SET " . implode(", ", $motherFields) . " WHERE user_id = ?";
        $stmt2 = $connectNow->prepare($sql2);
        $stmt2->bind_param($motherTypes, ...$motherParams);
        $stmt2->execute();
    }
    
    echo json_encode(["success" => true, "message" => "Mother updated successfully"]);
    exit();
}

// ==================== DELETE MOTHER ====================
if ($method === 'DELETE') {
    error_log("Processing DELETE request - Checking admin");
    
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can delete mothers"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Mother ID required"]);
        exit();
    }
    
    $id = intval($data['id']);
    
    // Delete from mothers table first (due to foreign key)
    $sql1 = "DELETE FROM mothers WHERE user_id = ?";
    $stmt1 = $connectNow->prepare($sql1);
    $stmt1->bind_param("i", $id);
    $stmt1->execute();
    
    // Delete from users table
    $sql2 = "DELETE FROM users WHERE id = ? AND role = 'mother'";
    $stmt2 = $connectNow->prepare($sql2);
    $stmt2->bind_param("i", $id);
    
    if ($stmt2->execute()) {
        echo json_encode(["success" => true, "message" => "Mother deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt2->error]);
    }
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
$connectNow->close();
?>