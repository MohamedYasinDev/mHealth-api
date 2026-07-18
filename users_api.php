<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

include "conn.php";

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get headers for admin authentication
$headers = getallheaders();

// Get admin email from headers
$adminEmail = '';
if (isset($headers['X-Admin-Email'])) {
    $adminEmail = $headers['X-Admin-Email'];
} elseif (isset($headers['x-admin-email'])) {
    $adminEmail = $headers['x-admin-email'];
} elseif (isset($headers['Admin-Email'])) {
    $adminEmail = $headers['Admin-Email'];
}

// Debug logging
error_log("Admin Email received: " . $adminEmail);

// Verify if requester is admin (for all operations)
function verifyAdmin($email, $conn) {
    if (empty($email)) {
        error_log("Email is empty");
        return false;
    }
    
    $checkAdmin = "SELECT id, email, role FROM users WHERE email = ? AND role = 'admin'";
    $stmt = $conn->prepare($checkAdmin);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        error_log("Admin found: " . $row['email'] . " - Role: " . $row['role']);
        return true;
    } else {
        error_log("No admin found with email: " . $email);
        return false;
    }
}

// ==================== GET ONLY ADMINS ====================
if ($method === 'GET') {
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode([
            "success" => false, 
            "message" => "Unauthorized. Only admin can access. Email: " . $adminEmail
        ]);
        exit();
    }
    
    // GET ONLY USERS WITH ROLE = 'admin'
    $sql = "SELECT id, full_name, phone, email, role, created_at 
            FROM users 
            WHERE role = 'admin' 
            ORDER BY id DESC";
    $result = $connectNow->query($sql);
    
    $admins = [];
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "users" => $admins,
        "count" => count($admins)
    ]);
    exit();
}

// ==================== ADD NEW USER (POST) - ANY ROLE ====================
if ($method === 'POST') {
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can add users"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    // Validate required fields
    if (!isset($data['full_name']) || empty(trim($data['full_name']))) {
        echo json_encode(["success" => false, "message" => "Full name required"]);
        exit();
    }
    
    if (!isset($data['phone']) || empty(trim($data['phone']))) {
        echo json_encode(["success" => false, "message" => "Phone required"]);
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
    
    if (!isset($data['role']) || empty(trim($data['role']))) {
        echo json_encode(["success" => false, "message" => "Role required"]);
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
    
    // Insert new user (can be mother, doctor, or admin)
    $full_name = trim($data['full_name']);
    $phone = trim($data['phone']);
    $email = trim($data['email']);
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $role = trim($data['role']);
    
    $sql = "INSERT INTO users (full_name, phone, email, password, role) VALUES (?, ?, ?, ?, ?)";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("sssss", $full_name, $phone, $email, $password, $role);
    
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "User created successfully",
            "user" => [
                "id" => $stmt->insert_id,
                "full_name" => $full_name,
                "phone" => $phone,
                "email" => $email,
                "role" => $role
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

// ==================== UPDATE USER (PUT) - ANY ROLE ====================
if ($method === 'PUT') {
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can update users"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "User ID required"]);
        exit();
    }
    
    $id = $data['id'];
    $full_name = isset($data['full_name']) ? trim($data['full_name']) : '';
    $phone = isset($data['phone']) ? trim($data['phone']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $role = isset($data['role']) ? trim($data['role']) : '';
    
    // Build dynamic update query
    $updateFields = [];
    $params = [];
    $types = "";
    
    if (!empty($full_name)) {
        $updateFields[] = "full_name = ?";
        $params[] = $full_name;
        $types .= "s";
    }
    
    if (!empty($phone)) {
        $updateFields[] = "phone = ?";
        $params[] = $phone;
        $types .= "s";
    }
    
    if (!empty($email)) {
        $updateFields[] = "email = ?";
        $params[] = $email;
        $types .= "s";
    }
    
    if (!empty($role)) {
        $updateFields[] = "role = ?";
        $params[] = $role;
        $types .= "s";
    }
    
    if (empty($updateFields)) {
        echo json_encode(["success" => false, "message" => "No fields to update"]);
        exit();
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "User updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

// ==================== DELETE USER - ANY ROLE ====================
if ($method === 'DELETE') {
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can delete users"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "User ID required"]);
        exit();
    }
    
    $id = $data['id'];
    
    // Get current admin ID
    $getAdminId = "SELECT id FROM users WHERE email = ? AND role = 'admin'";
    $stmt = $connectNow->prepare($getAdminId);
    $stmt->bind_param("s", $adminEmail);
    $stmt->execute();
    $adminResult = $stmt->get_result();
    $currentAdmin = $adminResult->fetch_assoc();
    
    // Don't allow admin to delete themselves
    if ($currentAdmin['id'] == $id) {
        echo json_encode(["success" => false, "message" => "You cannot delete yourself"]);
        exit();
    }
    
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "User deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
$connectNow->close();
?>