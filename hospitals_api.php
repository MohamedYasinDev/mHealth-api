<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

include "conn.php";

// Log all incoming headers for debugging
$headers = getallheaders();
error_log("=== HOSPITALS API CALL ===");
error_log("All Headers: " . print_r($headers, true));

// Get admin email from headers - try multiple ways
$adminEmail = '';

// Try X-Admin-Email (case sensitive)
if (isset($headers['X-Admin-Email'])) {
    $adminEmail = $headers['X-Admin-Email'];
    error_log("Found X-Admin-Email: " . $adminEmail);
}
// Try x-admin-email (lowercase)
elseif (isset($headers['x-admin-email'])) {
    $adminEmail = $headers['x-admin-email'];
    error_log("Found x-admin-email: " . $adminEmail);
}
// Try Admin-Email
elseif (isset($headers['Admin-Email'])) {
    $adminEmail = $headers['Admin-Email'];
    error_log("Found Admin-Email: " . $adminEmail);
}
// Try HTTP_X_ADMIN_EMAIL (Apache style)
elseif (isset($_SERVER['HTTP_X_ADMIN_EMAIL'])) {
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

// ==================== GET ALL HOSPITALS ====================
if ($method === 'GET') {
    error_log("Processing GET request");
    
    // Check if requesting specific hospital by ID
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id > 0) {
        // Get single hospital
        $sql = "SELECT id, name, type, phone, emergency_phone, region, address, 
                       latitude, longitude, has_maternity, has_emergency_24h, has_ambulance, created_at 
                FROM hospitals WHERE id = ?";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $hospital = $result->fetch_assoc();
            // Convert boolean fields
            $hospital['has_maternity'] = (bool)$hospital['has_maternity'];
            $hospital['has_emergency_24h'] = (bool)$hospital['has_emergency_24h'];
            $hospital['has_ambulance'] = (bool)$hospital['has_ambulance'];
            $hospital['id'] = (int)$hospital['id'];
            
            echo json_encode([
                "success" => true,
                "hospital" => $hospital
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Hospital not found"]);
        }
        exit();
    }
    
    // Get all hospitals - NO ADMIN VERIFICATION NEEDED FOR GET
    $sql = "SELECT id, name, type, phone, emergency_phone, region, address, 
                   latitude, longitude, has_maternity, has_emergency_24h, has_ambulance, created_at 
            FROM hospitals ORDER BY name ASC";
    $result = $connectNow->query($sql);
    
    $hospitals = [];
    while ($row = $result->fetch_assoc()) {
        // Convert boolean fields and ID to proper types
        $row['has_maternity'] = (bool)$row['has_maternity'];
        $row['has_emergency_24h'] = (bool)$row['has_emergency_24h'];
        $row['has_ambulance'] = (bool)$row['has_ambulance'];
        $row['id'] = (int)$row['id'];
        $hospitals[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "hospitals" => $hospitals,
        "count" => count($hospitals)
    ]);
    exit();
}

// ==================== ADD NEW HOSPITAL (POST) ====================
if ($method === 'POST') {
    error_log("Processing POST request");
    
    // Verify admin
    if (!verifyAdmin($adminEmail, $connectNow)) {
        error_log("Admin verification failed for email: " . $adminEmail);
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can add hospitals. Email: " . $adminEmail]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    // Validate required fields
    if (!isset($data['name']) || empty(trim($data['name']))) {
        echo json_encode(["success" => false, "message" => "Hospital name required"]);
        exit();
    }
    
    if (!isset($data['phone']) || empty(trim($data['phone']))) {
        echo json_encode(["success" => false, "message" => "Phone number required"]);
        exit();
    }
    
    if (!isset($data['region']) || empty(trim($data['region']))) {
        echo json_encode(["success" => false, "message" => "Region required"]);
        exit();
    }
    
    // Get data
    $name = trim($data['name']);
    $type = isset($data['type']) ? trim($data['type']) : 'hospital';
    $phone = trim($data['phone']);
    $emergency_phone = isset($data['emergency_phone']) ? trim($data['emergency_phone']) : null;
    $region = trim($data['region']);
    $address = isset($data['address']) ? trim($data['address']) : null;
    $latitude = isset($data['latitude']) ? floatval($data['latitude']) : null;
    $longitude = isset($data['longitude']) ? floatval($data['longitude']) : null;
    $has_maternity = isset($data['has_maternity']) ? (int)$data['has_maternity'] : 0;
    $has_emergency_24h = isset($data['has_emergency_24h']) ? (int)$data['has_emergency_24h'] : 0;
    $has_ambulance = isset($data['has_ambulance']) ? (int)$data['has_ambulance'] : 0;
    
    error_log("Inserting hospital: Name=$name, Phone=$phone, Region=$region");
    
    // Insert
    $sql = "INSERT INTO hospitals (name, type, phone, emergency_phone, region, address, 
                                   latitude, longitude, has_maternity, has_emergency_24h, has_ambulance) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("sssssssddii", $name, $type, $phone, $emergency_phone, $region, 
                      $address, $latitude, $longitude, $has_maternity, $has_emergency_24h, $has_ambulance);
    
    if ($stmt->execute()) {
        error_log("Hospital inserted successfully, ID: " . $stmt->insert_id);
        echo json_encode([
            "success" => true,
            "message" => "Hospital added successfully",
            "hospital_id" => $stmt->insert_id
        ]);
    } else {
        error_log("Error inserting hospital: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

// ==================== UPDATE HOSPITAL (PUT) ====================
if ($method === 'PUT') {
    error_log("Processing PUT request");
    
    // Verify admin
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can update hospitals"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Hospital ID required"]);
        exit();
    }
    
    $id = intval($data['id']);
    
    // Check if hospital exists
    $checkSql = "SELECT id FROM hospitals WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Hospital not found"]);
        exit();
    }
    
    // Build dynamic update query
    $updateFields = [];
    $params = [];
    $types = "";
    
    if (isset($data['name'])) {
        $updateFields[] = "name = ?";
        $params[] = trim($data['name']);
        $types .= "s";
    }
    
    if (isset($data['type'])) {
        $updateFields[] = "type = ?";
        $params[] = trim($data['type']);
        $types .= "s";
    }
    
    if (isset($data['phone'])) {
        $updateFields[] = "phone = ?";
        $params[] = trim($data['phone']);
        $types .= "s";
    }
    
    if (isset($data['emergency_phone'])) {
        $updateFields[] = "emergency_phone = ?";
        $params[] = trim($data['emergency_phone']);
        $types .= "s";
    }
    
    if (isset($data['region'])) {
        $updateFields[] = "region = ?";
        $params[] = trim($data['region']);
        $types .= "s";
    }
    
    if (isset($data['address'])) {
        $updateFields[] = "address = ?";
        $params[] = trim($data['address']);
        $types .= "s";
    }
    
    if (isset($data['latitude'])) {
        $updateFields[] = "latitude = ?";
        $params[] = floatval($data['latitude']);
        $types .= "d";
    }
    
    if (isset($data['longitude'])) {
        $updateFields[] = "longitude = ?";
        $params[] = floatval($data['longitude']);
        $types .= "d";
    }
    
    if (isset($data['has_maternity'])) {
        $updateFields[] = "has_maternity = ?";
        $params[] = (int)$data['has_maternity'];
        $types .= "i";
    }
    
    if (isset($data['has_emergency_24h'])) {
        $updateFields[] = "has_emergency_24h = ?";
        $params[] = (int)$data['has_emergency_24h'];
        $types .= "i";
    }
    
    if (isset($data['has_ambulance'])) {
        $updateFields[] = "has_ambulance = ?";
        $params[] = (int)$data['has_ambulance'];
        $types .= "i";
    }
    
    if (empty($updateFields)) {
        echo json_encode(["success" => false, "message" => "No fields to update"]);
        exit();
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE hospitals SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Hospital updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

// ==================== DELETE HOSPITAL ====================
if ($method === 'DELETE') {
    error_log("Processing DELETE request");
    
    // Verify admin
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can delete hospitals"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Hospital ID required"]);
        exit();
    }
    
    $id = intval($data['id']);
    
    // Check if hospital exists
    $checkSql = "SELECT id FROM hospitals WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Hospital not found"]);
        exit();
    }
    
    $sql = "DELETE FROM hospitals WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Hospital deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
$connectNow->close();
?>