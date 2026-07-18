<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

include "conn.php";

// Log all incoming headers for debugging
$headers = getallheaders();
error_log("=== PREGNANCIES API CALL ===");
error_log("All Headers: " . print_r($headers, true));

// Get admin email from headers - try multiple ways
$adminEmail = '';

if (isset($headers['X-Admin-Email'])) {
    $adminEmail = $headers['X-Admin-Email'];
    error_log("Found X-Admin-Email: " . $adminEmail);
} elseif (isset($headers['x-admin-email'])) {
    $adminEmail = $headers['x-admin-email'];
    error_log("Found x-admin-email: " . $adminEmail);
} elseif (isset($headers['Admin-Email'])) {
    $adminEmail = $headers['Admin-Email'];
    error_log("Found Admin-Email: " . $adminEmail);
} elseif (isset($_SERVER['HTTP_X_ADMIN_EMAIL'])) {
    $adminEmail = $_SERVER['HTTP_X_ADMIN_EMAIL'];
    error_log("Found HTTP_X_ADMIN_EMAIL: " . $adminEmail);
}

error_log("Final adminEmail: " . $adminEmail);

// Get request method
$method = $_SERVER['REQUEST_METHOD'];
error_log("Request Method: " . $method);

// ==================== VERIFY ADMIN FUNCTION ====================
function verifyAdmin($email, $conn) {
    error_log("verifyAdmin called with email: " . $email);
    
    if (empty($email)) {
        error_log("Email is empty in verifyAdmin");
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

// ==================== GET ALL PREGNANCIES ====================
if ($method === 'GET') {
    error_log("Processing GET request");
    
    // Check if requesting specific pregnancy by ID
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $mother_id = isset($_GET['mother_id']) ? intval($_GET['mother_id']) : 0;
    
    if ($id > 0) {
        // Get single pregnancy
        $sql = "SELECT p.*, u.full_name as mother_name 
                FROM pregnancies p
                LEFT JOIN users u ON p.mother_id = u.id
                WHERE p.id = ?";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $pregnancy = $result->fetch_assoc();
            $pregnancy['id'] = (int)$pregnancy['id'];
            $pregnancy['mother_id'] = (int)$pregnancy['mother_id'];
            $pregnancy['pregnancy_number'] = (int)$pregnancy['pregnancy_number'];
            $pregnancy['baby_count'] = (int)$pregnancy['baby_count'];
            
            echo json_encode([
                "success" => true,
                "pregnancy" => $pregnancy
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Pregnancy record not found"]);
        }
        exit();
    }
    
    if ($mother_id > 0) {
        // Get pregnancies for specific mother
        $sql = "SELECT p.*, u.full_name as mother_name 
                FROM pregnancies p
                LEFT JOIN users u ON p.mother_id = u.id
                WHERE p.mother_id = ?
                ORDER BY p.id DESC";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $mother_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $pregnancies = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['mother_id'] = (int)$row['mother_id'];
            $row['pregnancy_number'] = (int)$row['pregnancy_number'];
            $row['baby_count'] = (int)$row['baby_count'];
            $pregnancies[] = $row;
        }
        
        echo json_encode([
            "success" => true,
            "pregnancies" => $pregnancies,
            "count" => count($pregnancies)
        ]);
        exit();
    }
    
    // Get all pregnancies (admin only)
    // Verify admin for full list
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can view all pregnancies"]);
        exit();
    }
    
    $sql = "SELECT p.*, u.full_name as mother_name 
            FROM pregnancies p
            LEFT JOIN users u ON p.mother_id = u.id
            ORDER BY p.id DESC";
    $result = $connectNow->query($sql);
    
    $pregnancies = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['mother_id'] = (int)$row['mother_id'];
        $row['pregnancy_number'] = (int)$row['pregnancy_number'];
        $row['baby_count'] = (int)$row['baby_count'];
        $pregnancies[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "pregnancies" => $pregnancies,
        "count" => count($pregnancies)
    ]);
    exit();
}

// ==================== ADD NEW PREGNANCY (POST) - UPDATED ====================
if ($method === 'POST') {
    error_log("Processing POST request");
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    // Check if mother is adding her own pregnancy
    $isAdmin = verifyAdmin($adminEmail, $connectNow);
    $isMother = false;
    
    // If there's a mother_id and no admin email (or admin email is empty)
    if (isset($data['mother_id']) && empty($adminEmail)) {
        $isMother = true;
        error_log("Mother is trying to add pregnancy for mother_id: " . $data['mother_id']);
        
        // Verify that the mother exists
        $checkMother = "SELECT id FROM users WHERE id = ? AND role = 'mother'";
        $stmt = $connectNow->prepare($checkMother);
        $stmt->bind_param("i", $data['mother_id']);
        $stmt->execute();
        $motherResult = $stmt->get_result();
        
        if ($motherResult->num_rows === 0) {
            echo json_encode(["success" => false, "message" => "Mother not found"]);
            exit();
        }
    }
    
    // Allow if admin OR mother
    if (!$isAdmin && !$isMother) {
        error_log("Unauthorized: Not admin and not mother");
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin or mother can add pregnancies"]);
        exit();
    }
    
    // Validate required fields
    if (!isset($data['mother_id']) || empty($data['mother_id'])) {
        echo json_encode(["success" => false, "message" => "Mother ID required"]);
        exit();
    }
    
    if (!isset($data['expected_delivery_date']) || empty(trim($data['expected_delivery_date']))) {
        echo json_encode(["success" => false, "message" => "Expected delivery date required"]);
        exit();
    }
    
    // Verify mother exists (if admin is adding)
    if ($isAdmin && !$isMother) {
        $checkMother = "SELECT id FROM users WHERE id = ? AND role = 'mother'";
        $stmt = $connectNow->prepare($checkMother);
        $stmt->bind_param("i", $data['mother_id']);
        $stmt->execute();
        $motherResult = $stmt->get_result();
        
        if ($motherResult->num_rows === 0) {
            echo json_encode(["success" => false, "message" => "Mother not found"]);
            exit();
        }
    }
    
    // Get data
    $mother_id = intval($data['mother_id']);
    $pregnancy_number = isset($data['pregnancy_number']) ? intval($data['pregnancy_number']) : 1;
    $baby_count = isset($data['baby_count']) ? intval($data['baby_count']) : 1;
    $expected_delivery_date = trim($data['expected_delivery_date']);
    $pregnancy_status = isset($data['pregnancy_status']) ? trim($data['pregnancy_status']) : 'ongoing';
    
    error_log("Inserting pregnancy: mother_id=$mother_id, pregnancy_number=$pregnancy_number, expected_delivery=$expected_delivery_date");
    
    // Insert
    $sql = "INSERT INTO pregnancies (mother_id, pregnancy_number, baby_count, expected_delivery_date, pregnancy_status) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("iiiss", $mother_id, $pregnancy_number, $baby_count, $expected_delivery_date, $pregnancy_status);
    
    if ($stmt->execute()) {
        error_log("Pregnancy inserted successfully, ID: " . $stmt->insert_id);
        echo json_encode([
            "success" => true,
            "message" => "Pregnancy record added successfully",
            "pregnancy_id" => $stmt->insert_id
        ]);
    } else {
        error_log("Error inserting pregnancy: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

// ==================== UPDATE PREGNANCY (PUT) ====================
if ($method === 'PUT') {
    error_log("Processing PUT request");
    
    // Verify admin
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can update pregnancies"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Pregnancy ID required"]);
        exit();
    }
    
    $id = intval($data['id']);
    
    // Check if pregnancy exists
    $checkSql = "SELECT id FROM pregnancies WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Pregnancy record not found"]);
        exit();
    }
    
    // Build dynamic update query
    $updateFields = [];
    $params = [];
    $types = "";
    
    if (isset($data['mother_id'])) {
        // Verify mother exists
        $checkMother = "SELECT id FROM users WHERE id = ? AND role = 'mother'";
        $stmt = $connectNow->prepare($checkMother);
        $stmt->bind_param("i", $data['mother_id']);
        $stmt->execute();
        $motherResult = $stmt->get_result();
        
        if ($motherResult->num_rows === 0) {
            echo json_encode(["success" => false, "message" => "Mother not found"]);
            exit();
        }
        $updateFields[] = "mother_id = ?";
        $params[] = intval($data['mother_id']);
        $types .= "i";
    }
    
    if (isset($data['pregnancy_number'])) {
        $updateFields[] = "pregnancy_number = ?";
        $params[] = intval($data['pregnancy_number']);
        $types .= "i";
    }
    
    if (isset($data['baby_count'])) {
        $updateFields[] = "baby_count = ?";
        $params[] = intval($data['baby_count']);
        $types .= "i";
    }
    
    if (isset($data['expected_delivery_date'])) {
        $updateFields[] = "expected_delivery_date = ?";
        $params[] = trim($data['expected_delivery_date']);
        $types .= "s";
    }
    
    if (isset($data['pregnancy_status'])) {
        $updateFields[] = "pregnancy_status = ?";
        $params[] = trim($data['pregnancy_status']);
        $types .= "s";
    }
    
    if (empty($updateFields)) {
        echo json_encode(["success" => false, "message" => "No fields to update"]);
        exit();
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE pregnancies SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Pregnancy record updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

// ==================== DELETE PREGNANCY ====================
if ($method === 'DELETE') {
    error_log("Processing DELETE request");
    
    // Verify admin
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can delete pregnancies"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Pregnancy ID required"]);
        exit();
    }
    
    $id = intval($data['id']);
    
    // Check if pregnancy exists
    $checkSql = "SELECT id FROM pregnancies WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Pregnancy record not found"]);
        exit();
    }
    
    $sql = "DELETE FROM pregnancies WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Pregnancy record deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
$connectNow->close();
?>