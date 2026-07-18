<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

include "conn.php";

// Log all incoming headers for debugging
$headers = getallheaders();
error_log("=== APPOINTMENTS API CALL ===");
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
    if ($doctor_id == 0 || $doctor_id == null) return true; // Doctor is optional
    
    $checkDoctor = "SELECT id FROM doctors WHERE id = ? AND is_active = TRUE";
    $stmt = $conn->prepare($checkDoctor);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// ==================== VERIFY HOSPITAL FUNCTION ====================
function verifyHospital($hospital_id, $conn) {
    $checkHospital = "SELECT id FROM hospitals WHERE id = ?";
    $stmt = $conn->prepare($checkHospital);
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// ==================== GET ALL APPOINTMENTS ====================
if ($method === 'GET') {
    error_log("Processing GET request");
    
    // Check if requesting specific appointment by ID
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
    $hospital_id = isset($_GET['hospital_id']) ? intval($_GET['hospital_id']) : 0;
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $date = isset($_GET['date']) ? trim($_GET['date']) : '';
    
    if ($id > 0) {
        // Get single appointment
        $sql = "SELECT a.*, 
                u.full_name as user_name, u.phone as user_phone,
                d.full_name as doctor_name, d.specialization,
                h.name as hospital_name, h.region, h.address
                FROM appointments a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN hospitals h ON a.hospital_id = h.id
                WHERE a.id = ?";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $appointment = $result->fetch_assoc();
            $appointment['id'] = (int)$appointment['id'];
            $appointment['user_id'] = (int)$appointment['user_id'];
            $appointment['doctor_id'] = $appointment['doctor_id'] ? (int)$appointment['doctor_id'] : null;
            $appointment['hospital_id'] = (int)$appointment['hospital_id'];
            
            echo json_encode([
                "success" => true,
                "appointment" => $appointment
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Appointment not found"]);
        }
        exit();
    }
    
    if ($user_id > 0) {
        // Get appointments for specific user/mother
        $sql = "SELECT a.*, 
                d.full_name as doctor_name, d.specialization,
                h.name as hospital_name, h.region
                FROM appointments a
                LEFT JOIN doctors d ON a.doctor_id = d.id
                LEFT JOIN hospitals h ON a.hospital_id = h.id
                WHERE a.user_id = ?
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $appointments = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['doctor_id'] = $row['doctor_id'] ? (int)$row['doctor_id'] : null;
            $row['hospital_id'] = (int)$row['hospital_id'];
            $appointments[] = $row;
        }
        
        echo json_encode([
            "success" => true,
            "appointments" => $appointments,
            "count" => count($appointments)
        ]);
        exit();
    }
    
    if ($doctor_id > 0) {
        // Get appointments for specific doctor
        $sql = "SELECT a.*, 
                u.full_name as user_name, u.phone as user_phone,
                h.name as hospital_name
                FROM appointments a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN hospitals h ON a.hospital_id = h.id
                WHERE a.doctor_id = ?
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $appointments = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['doctor_id'] = (int)$row['doctor_id'];
            $row['hospital_id'] = (int)$row['hospital_id'];
            $appointments[] = $row;
        }
        
        echo json_encode([
            "success" => true,
            "appointments" => $appointments,
            "count" => count($appointments)
        ]);
        exit();
    }
    
    // Build query with filters
    $sql = "SELECT a.*, 
            u.full_name as user_name, u.phone as user_phone,
            d.full_name as doctor_name, d.specialization,
            h.name as hospital_name, h.region, h.address
            FROM appointments a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN doctors d ON a.doctor_id = d.id
            LEFT JOIN hospitals h ON a.hospital_id = h.id
            WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($hospital_id > 0) {
        $sql .= " AND a.hospital_id = ?";
        $params[] = $hospital_id;
        $types .= "i";
    }
    
    if (!empty($status)) {
        $sql .= " AND a.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if (!empty($date)) {
        $sql .= " AND a.appointment_date = ?";
        $params[] = $date;
        $types .= "s";
    }
    
    $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
    
    $stmt = $connectNow->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['user_id'] = (int)$row['user_id'];
        $row['doctor_id'] = $row['doctor_id'] ? (int)$row['doctor_id'] : null;
        $row['hospital_id'] = (int)$row['hospital_id'];
        $appointments[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "appointments" => $appointments,
        "count" => count($appointments)
    ]);
    exit();
}

// ==================== ADD NEW APPOINTMENT (POST) ====================
if ($method === 'POST') {
    error_log("Processing POST request");
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    // Check if admin or user is creating
    $isAdmin = verifyAdmin($adminEmail, $connectNow);
    
    // Validate required fields
    if (!isset($data['user_id']) || empty($data['user_id'])) {
        echo json_encode(["success" => false, "message" => "User ID required"]);
        exit();
    }
    
    if (!isset($data['hospital_id']) || empty($data['hospital_id'])) {
        echo json_encode(["success" => false, "message" => "Hospital ID required"]);
        exit();
    }
    
    if (!isset($data['appointment_date']) || empty(trim($data['appointment_date']))) {
        echo json_encode(["success" => false, "message" => "Appointment date required"]);
        exit();
    }
    
    if (!isset($data['appointment_time']) || empty(trim($data['appointment_time']))) {
        echo json_encode(["success" => false, "message" => "Appointment time required"]);
        exit();
    }
    
    // Verify user exists
    if (!verifyUser($data['user_id'], $connectNow)) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit();
    }
    
    // Verify hospital exists
    if (!verifyHospital($data['hospital_id'], $connectNow)) {
        echo json_encode(["success" => false, "message" => "Hospital not found"]);
        exit();
    }
    
    // Get data
    $user_id = intval($data['user_id']);
    $doctor_id = isset($data['doctor_id']) && !empty($data['doctor_id']) ? intval($data['doctor_id']) : null;
    $hospital_id = intval($data['hospital_id']);
    $appointment_type = isset($data['appointment_type']) ? trim($data['appointment_type']) : 'checkup';
    $appointment_date = trim($data['appointment_date']);
    $appointment_time = trim($data['appointment_time']);
    $reason = isset($data['reason']) ? trim($data['reason']) : null;
    $status = isset($data['status']) ? trim($data['status']) : 'scheduled';
    
    // Verify doctor if provided
    if ($doctor_id !== null && !verifyDoctor($doctor_id, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Doctor not found or inactive"]);
        exit();
    }
    
    error_log("Inserting appointment: user_id=$user_id, hospital_id=$hospital_id, date=$appointment_date, time=$appointment_time");
    
    // Insert
    $sql = "INSERT INTO appointments (user_id, doctor_id, hospital_id, appointment_type, appointment_date, appointment_time, reason, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("iiisssss", $user_id, $doctor_id, $hospital_id, $appointment_type, $appointment_date, $appointment_time, $reason, $status);
    
    if ($stmt->execute()) {
        error_log("Appointment inserted successfully, ID: " . $stmt->insert_id);
        echo json_encode([
            "success" => true,
            "message" => "Appointment created successfully",
            "appointment_id" => $stmt->insert_id
        ]);
    } else {
        error_log("Error inserting appointment: " . $stmt->error);
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

// ==================== UPDATE APPOINTMENT (PUT) ====================
if ($method === 'PUT') {
    error_log("Processing PUT request");
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Appointment ID required"]);
        exit();
    }
    
    $id = intval($data['id']);
    
    // Check if appointment exists
    $checkSql = "SELECT id FROM appointments WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Appointment not found"]);
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
    
    if (isset($data['hospital_id'])) {
        if (!verifyHospital($data['hospital_id'], $connectNow)) {
            echo json_encode(["success" => false, "message" => "Hospital not found"]);
            exit();
        }
        $updateFields[] = "hospital_id = ?";
        $params[] = intval($data['hospital_id']);
        $types .= "i";
    }
    
    if (isset($data['appointment_type'])) {
        $updateFields[] = "appointment_type = ?";
        $params[] = trim($data['appointment_type']);
        $types .= "s";
    }
    
    if (isset($data['appointment_date'])) {
        $updateFields[] = "appointment_date = ?";
        $params[] = trim($data['appointment_date']);
        $types .= "s";
    }
    
    if (isset($data['appointment_time'])) {
        $updateFields[] = "appointment_time = ?";
        $params[] = trim($data['appointment_time']);
        $types .= "s";
    }
    
    if (isset($data['reason'])) {
        $updateFields[] = "reason = ?";
        $params[] = trim($data['reason']);
        $types .= "s";
    }
    
    if (isset($data['status'])) {
        $validStatus = ['scheduled', 'completed', 'cancelled', 'missed'];
        if (!in_array($data['status'], $validStatus)) {
            echo json_encode(["success" => false, "message" => "Invalid status. Allowed: scheduled, completed, cancelled, missed"]);
            exit();
        }
        $updateFields[] = "status = ?";
        $params[] = trim($data['status']);
        $types .= "s";
    }
    
    if (empty($updateFields)) {
        echo json_encode(["success" => false, "message" => "No fields to update"]);
        exit();
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE appointments SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Appointment updated successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

// ==================== DELETE APPOINTMENT ====================
if ($method === 'DELETE') {
    error_log("Processing DELETE request");
    
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);
    
    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Appointment ID required"]);
        exit();
    }
    
    $id = intval($data['id']);
    
    // Check if appointment exists
    $checkSql = "SELECT id FROM appointments WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Appointment not found"]);
        exit();
    }
    
    $sql = "DELETE FROM appointments WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Appointment deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
    }
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
$connectNow->close();
?>