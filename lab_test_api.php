<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

include "conn.php";

// Log all incoming headers for debugging
$headers = getallheaders();
error_log("=== LAB TESTS API CALL ===");
error_log("All Headers: " . print_r($headers, true));

// Get admin email from headers
$adminEmail = '';

if (isset($headers['X-Admin-Email'])) {
    $adminEmail = $headers['X-Admin-Email'];
} elseif (isset($headers['x-admin-email'])) {
    $adminEmail = $headers['x-admin-email'];
} elseif (isset($headers['Admin-Email'])) {
    $adminEmail = $headers['Admin-Email'];
} elseif (isset($_SERVER['HTTP_X_ADMIN_EMAIL'])) {
    $adminEmail = $_SERVER['HTTP_X_ADMIN_EMAIL'];
}

error_log("Final adminEmail: " . $adminEmail);

// Get request method
$method = $_SERVER['REQUEST_METHOD'];
error_log("Request Method: " . $method);

// ==================== VERIFY ADMIN FUNCTION ====================
function verifyAdmin($email, $conn) {
    if (empty($email)) return false;
    
    $checkAdmin = "SELECT id, email, role FROM users WHERE email = ? AND role = 'admin'";
    $stmt = $conn->prepare($checkAdmin);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// ==================== VERIFY USER FUNCTION ====================
function verifyUser($user_id, $conn) {
    $checkUser = "SELECT id FROM users WHERE id = ?";
    $stmt = $conn->prepare($checkUser);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// ==================== VERIFY DOCTOR FUNCTION ====================
function verifyDoctor($doctor_id, $conn) {
    if ($doctor_id == 0 || $doctor_id == null) return true;
    
    $checkDoctor = "SELECT id FROM doctors WHERE id = ? AND is_active = TRUE";
    $stmt = $conn->prepare($checkDoctor);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// ==================== VERIFY APPOINTMENT FUNCTION ====================
function verifyAppointment($appointment_id, $conn) {
    if ($appointment_id == 0 || $appointment_id == null) return true;
    
    $checkAppointment = "SELECT id FROM appointments WHERE id = ?";
    $stmt = $conn->prepare($checkAppointment);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// ==================== GET ALL LAB TESTS ====================
if ($method === 'GET') {
    error_log("Processing GET request");
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
    $appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $test_type = isset($_GET['test_type']) ? trim($_GET['test_type']) : '';
    
    // Get single lab test by ID (Public - anyone can view a single test)
    if ($id > 0) {
        $sql = "SELECT lt.*, 
                u.full_name as user_name, u.phone as user_phone,
                d.full_name as doctor_name, d.specialization,
                a.appointment_date
                FROM lab_tests lt
                LEFT JOIN users u ON lt.user_id = u.id
                LEFT JOIN doctors d ON lt.doctor_id = d.id
                LEFT JOIN appointments a ON lt.appointment_id = a.id
                WHERE lt.id = ?";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $test = $result->fetch_assoc();
            $test['id'] = (int)$test['id'];
            $test['user_id'] = (int)$test['user_id'];
            $test['doctor_id'] = $test['doctor_id'] ? (int)$test['doctor_id'] : null;
            $test['appointment_id'] = $test['appointment_id'] ? (int)$test['appointment_id'] : null;
            
            echo json_encode(["success" => true, "lab_test" => $test]);
        } else {
            echo json_encode(["success" => false, "message" => "Lab test not found"]);
        }
        exit();
    }
    
    // Get lab tests for specific user (MOTHER - No admin required!)
    if ($user_id > 0) {
        $sql = "SELECT lt.*, 
                d.full_name as doctor_name, d.specialization,
                a.appointment_date
                FROM lab_tests lt
                LEFT JOIN doctors d ON lt.doctor_id = d.id
                LEFT JOIN appointments a ON lt.appointment_id = a.id
                WHERE lt.user_id = ?";
        $params = [$user_id];
        $types = "i";
        
        if (!empty($status)) {
            $sql .= " AND lt.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if (!empty($test_type)) {
            $sql .= " AND lt.test_type = ?";
            $params[] = $test_type;
            $types .= "s";
        }
        
        $sql .= " ORDER BY lt.created_at DESC";
        
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tests = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['doctor_id'] = $row['doctor_id'] ? (int)$row['doctor_id'] : null;
            $row['appointment_id'] = $row['appointment_id'] ? (int)$row['appointment_id'] : null;
            $tests[] = $row;
        }
        
        echo json_encode(["success" => true, "lab_tests" => $tests, "count" => count($tests)]);
        exit();
    }
    
    // Get lab tests for specific doctor (ADMIN ONLY)
    if ($doctor_id > 0) {
        if (!verifyAdmin($adminEmail, $connectNow)) {
            echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can view doctor's lab tests"]);
            exit();
        }
        
        $sql = "SELECT lt.*, 
                u.full_name as user_name,
                a.appointment_date
                FROM lab_tests lt
                LEFT JOIN users u ON lt.user_id = u.id
                LEFT JOIN appointments a ON lt.appointment_id = a.id
                WHERE lt.doctor_id = ?
                ORDER BY lt.created_at DESC";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tests = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['doctor_id'] = (int)$row['doctor_id'];
            $row['appointment_id'] = $row['appointment_id'] ? (int)$row['appointment_id'] : null;
            $tests[] = $row;
        }
        
        echo json_encode(["success" => true, "lab_tests" => $tests, "count" => count($tests)]);
        exit();
    }
    
    // Get lab tests for specific appointment (ADMIN ONLY)
    if ($appointment_id > 0) {
        if (!verifyAdmin($adminEmail, $connectNow)) {
            echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can view appointment lab tests"]);
            exit();
        }
        
        $sql = "SELECT lt.*, 
                u.full_name as user_name,
                d.full_name as doctor_name
                FROM lab_tests lt
                LEFT JOIN users u ON lt.user_id = u.id
                LEFT JOIN doctors d ON lt.doctor_id = d.id
                WHERE lt.appointment_id = ?
                ORDER BY lt.created_at DESC";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tests = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['doctor_id'] = $row['doctor_id'] ? (int)$row['doctor_id'] : null;
            $row['appointment_id'] = (int)$row['appointment_id'];
            $tests[] = $row;
        }
        
        echo json_encode(["success" => true, "lab_tests" => $tests, "count" => count($tests)]);
        exit();
    }
    
    // Get ALL lab tests (ADMIN ONLY - with optional filters)
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Admin access required to view all lab tests"]);
        exit();
    }
    
    $sql = "SELECT lt.*, 
            u.full_name as user_name, u.phone as user_phone,
            d.full_name as doctor_name, d.specialization,
            a.appointment_date
            FROM lab_tests lt
            LEFT JOIN users u ON lt.user_id = u.id
            LEFT JOIN doctors d ON lt.doctor_id = d.id
            LEFT JOIN appointments a ON lt.appointment_id = a.id
            WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($status)) {
        $sql .= " AND lt.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if (!empty($test_type)) {
        $sql .= " AND lt.test_type = ?";
        $params[] = $test_type;
        $types .= "s";
    }
    
    $sql .= " ORDER BY lt.created_at DESC";
    
    $stmt = $connectNow->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tests = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['user_id'] = (int)$row['user_id'];
        $row['doctor_id'] = $row['doctor_id'] ? (int)$row['doctor_id'] : null;
        $row['appointment_id'] = $row['appointment_id'] ? (int)$row['appointment_id'] : null;
        $tests[] = $row;
    }
    
    echo json_encode(["success" => true, "lab_tests" => $tests, "count" => count($tests)]);
    exit();
}

// ==================== ADD NEW LAB TEST (POST) ====================
if ($method === 'POST') {
    error_log("Processing POST request");
    
    // Verify admin
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can add lab tests"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    // Validate required fields
    if (!isset($data['user_id']) || empty($data['user_id'])) {
        echo json_encode(["success" => false, "message" => "User ID required"]);
        exit();
    }
    
    if (!isset($data['test_name']) || empty(trim($data['test_name']))) {
        echo json_encode(["success" => false, "message" => "Test name required"]);
        exit();
    }
    
    // Verify user exists
    if (!verifyUser($data['user_id'], $connectNow)) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit();
    }
    
    // Get data
    $user_id = intval($data['user_id']);
    $doctor_id = isset($data['doctor_id']) && !empty($data['doctor_id']) ? intval($data['doctor_id']) : null;
    $appointment_id = isset($data['appointment_id']) && !empty($data['appointment_id']) ? intval($data['appointment_id']) : null;
    $test_name = trim($data['test_name']);
    $test_date = isset($data['test_date']) && !empty($data['test_date']) ? trim($data['test_date']) : date('Y-m-d');
    $result = isset($data['result']) ? trim($data['result']) : null;
    $status = isset($data['status']) ? trim($data['status']) : 'ordered';
    $notes = isset($data['notes']) ? trim($data['notes']) : null;
    $test_type = isset($data['test_type']) ? trim($data['test_type']) : 'text';
    $result_value = isset($data['result_value']) && !empty($data['result_value']) ? floatval($data['result_value']) : null;
    $unit = isset($data['unit']) ? trim($data['unit']) : null;
    $report_file = isset($data['report_file']) ? trim($data['report_file']) : null;
    
    // Verify doctor if provided
    if ($doctor_id !== null && !verifyDoctor($doctor_id, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Doctor not found or inactive"]);
        exit();
    }
    
    // Verify appointment if provided
    if ($appointment_id !== null && !verifyAppointment($appointment_id, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Appointment not found"]);
        exit();
    }
    
    error_log("Inserting lab test: user_id=$user_id, test_name=$test_name");
    
    // Insert
    $sql = "INSERT INTO lab_tests (user_id, doctor_id, appointment_id, test_name, test_date, result, status, notes, test_type, result_value, unit, report_file) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("iiisssssssds", $user_id, $doctor_id, $appointment_id, $test_name, $test_date, $result, $status, $notes, $test_type, $result_value, $unit, $report_file);
    
    if ($stmt->execute()) {
        error_log("Lab test inserted successfully, ID: " . $stmt->insert_id);
        echo json_encode([
            "success" => true,
            "message" => "Lab test created successfully",
            "lab_test_id" => $stmt->insert_id
        ]);
    } else {
        error_log("Error inserting lab test: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

// ==================== UPDATE LAB TEST (PUT) ====================
if ($method === 'PUT') {
    error_log("Processing PUT request");
    
    // Verify admin
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can update lab tests"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Lab test ID required"]);
        exit();
    }
    
    $id = intval($data['id']);
    
    // Check if lab test exists
    $checkSql = "SELECT id FROM lab_tests WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Lab test not found"]);
        exit();
    }
    
    // Build dynamic update query
    $updateFields = [];
    $params = [];
    $types = "";
    
    if (isset($data['user_id'])) {
        if (!verifyUser($data['user_id'], $connectNow)) {
            echo json_encode(["success" => false, "message" => "User not found"]);
            exit();
        }
        $updateFields[] = "user_id = ?";
        $params[] = intval($data['user_id']);
        $types .= "i";
    }
    
    if (isset($data['doctor_id'])) {
        $doctor_id = !empty($data['doctor_id']) ? intval($data['doctor_id']) : null;
        if ($doctor_id !== null && !verifyDoctor($doctor_id, $connectNow)) {
            echo json_encode(["success" => false, "message" => "Doctor not found or inactive"]);
            exit();
        }
        $updateFields[] = "doctor_id = ?";
        $params[] = $doctor_id;
        $types .= "i";
    }
    
    if (isset($data['appointment_id'])) {
        $appointment_id = !empty($data['appointment_id']) ? intval($data['appointment_id']) : null;
        if ($appointment_id !== null && !verifyAppointment($appointment_id, $connectNow)) {
            echo json_encode(["success" => false, "message" => "Appointment not found"]);
            exit();
        }
        $updateFields[] = "appointment_id = ?";
        $params[] = $appointment_id;
        $types .= "i";
    }
    
    if (isset($data['test_name'])) {
        $updateFields[] = "test_name = ?";
        $params[] = trim($data['test_name']);
        $types .= "s";
    }
    
    if (isset($data['test_date'])) {
        $updateFields[] = "test_date = ?";
        $params[] = trim($data['test_date']);
        $types .= "s";
    }
    
    if (isset($data['result'])) {
        $updateFields[] = "result = ?";
        $params[] = trim($data['result']);
        $types .= "s";
    }
    
    if (isset($data['status'])) {
        $validStatus = ['ordered', 'completed'];
        if (!in_array($data['status'], $validStatus)) {
            echo json_encode(["success" => false, "message" => "Invalid status. Allowed: ordered, completed"]);
            exit();
        }
        $updateFields[] = "status = ?";
        $params[] = trim($data['status']);
        $types .= "s";
    }
    
    if (isset($data['notes'])) {
        $updateFields[] = "notes = ?";
        $params[] = trim($data['notes']);
        $types .= "s";
    }
    
    if (isset($data['test_type'])) {
        $updateFields[] = "test_type = ?";
        $params[] = trim($data['test_type']);
        $types .= "s";
    }
    
    if (isset($data['result_value'])) {
        $updateFields[] = "result_value = ?";
        $params[] = !empty($data['result_value']) ? floatval($data['result_value']) : null;
        $types .= "d";
    }
    
    if (isset($data['unit'])) {
        $updateFields[] = "unit = ?";
        $params[] = trim($data['unit']);
        $types .= "s";
    }
    
    if (isset($data['report_file'])) {
        $updateFields[] = "report_file = ?";
        $params[] = trim($data['report_file']);
        $types .= "s";
    }
    
    if (empty($updateFields)) {
        echo json_encode(["success" => false, "message" => "No fields to update"]);
        exit();
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE lab_tests SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Lab test updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

// ==================== DELETE LAB TEST ====================
if ($method === 'DELETE') {
    error_log("Processing DELETE request");
    
    // Verify admin
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Only admin can delete lab tests"]);
        exit();
    }
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Lab test ID required"]);
        exit();
    }
    
    $id = intval($data['id']);
    
    // Check if lab test exists
    $checkSql = "SELECT id FROM lab_tests WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Lab test not found"]);
        exit();
    }
    
    $sql = "DELETE FROM lab_tests WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Lab test deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
$connectNow->close();
?>