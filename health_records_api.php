<?php
// C:\xampp\htdocs\mHealth_api\health_records_api.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

error_reporting(E_ALL);
ini_set('display_errors', 0);

include "conn.php";

$method = $_SERVER['REQUEST_METHOD'];

// ================= VERIFY USER =================
function verifyUser($user_id, $conn) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// ================= VERIFY DOCTOR =================
function verifyDoctor($doctor_id, $conn) {
    if (!$doctor_id || $doctor_id == 0) return true;
    
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return true;
    }
    
    $stmt2 = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'doctor'");
    $stmt2->bind_param("i", $doctor_id);
    $stmt2->execute();
    return $stmt2->get_result()->num_rows > 0;
}

// ================= VERIFY PREGNANCY =================
function verifyPregnancy($pregnancy_id, $conn) {
    if (!$pregnancy_id || $pregnancy_id == 0) return true;
    
    $stmt = $conn->prepare("SELECT id FROM pregnancies WHERE id = ?");
    $stmt->bind_param("i", $pregnancy_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// ================= GET HEALTH RECORDS =================
if ($method === 'GET') {

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $pregnancy_id = isset($_GET['pregnancy_id']) ? intval($_GET['pregnancy_id']) : 0;

    // GET single health record
    if ($id > 0) {
        $sql = "SELECT hr.*, 
                u.full_name as user_name,
                d.full_name as doctor_name
                FROM health_records hr
                LEFT JOIN users u ON hr.user_id = u.id
                LEFT JOIN doctors d ON hr.recorded_by = d.id
                WHERE hr.id = ?";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode([
                "success" => true,
                "data" => $row
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Record not found"]);
        }
        exit();
    }

    // GET health records for specific user
    if ($user_id > 0) {
        $sql = "SELECT hr.*, 
                u.full_name as user_name,
                d.full_name as doctor_name
                FROM health_records hr
                LEFT JOIN users u ON hr.user_id = u.id
                LEFT JOIN doctors d ON hr.recorded_by = d.id
                WHERE hr.user_id = ?
                ORDER BY hr.record_date DESC, hr.created_at DESC";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
        }
        
        echo json_encode(["success" => true, "health_records" => $records]);
        exit();
    }

    // GET health records for specific pregnancy
    if ($pregnancy_id > 0) {
        $sql = "SELECT hr.*, 
                u.full_name as user_name,
                d.full_name as doctor_name
                FROM health_records hr
                LEFT JOIN users u ON hr.user_id = u.id
                LEFT JOIN doctors d ON hr.recorded_by = d.id
                WHERE hr.pregnancy_id = ?
                ORDER BY hr.record_date DESC";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $pregnancy_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
        }
        
        echo json_encode(["success" => true, "health_records" => $records]);
        exit();
    }

    // GET all health records - WITH DOCTOR NAME
    $sql = "SELECT hr.*, 
            u.full_name as user_name,
            d.full_name as doctor_name
            FROM health_records hr
            LEFT JOIN users u ON hr.user_id = u.id
            LEFT JOIN doctors d ON hr.recorded_by = d.id
            ORDER BY hr.record_date DESC, hr.created_at DESC";
    
    $result = $connectNow->query($sql);
    
    if (!$result) {
        echo json_encode([
            "success" => false, 
            "message" => "Query failed: " . $connectNow->error
        ]);
        exit();
    }
    
    $records = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    }
    
    echo json_encode(["success" => true, "health_records" => $records, "count" => count($records)]);
    exit();
}

// ================= POST (ADD HEALTH RECORD) =================
if ($method === 'POST') {

    $data = json_decode(file_get_contents("php://input"), true);

    // Validate required fields
    if (!isset($data['user_id']) || empty($data['user_id'])) {
        echo json_encode(["success" => false, "message" => "User ID required"]);
        exit();
    }

    if (!verifyUser($data['user_id'], $connectNow)) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit();
    }

    // Verify pregnancy if provided
    $pregnancy_id = null;
    if (isset($data['pregnancy_id']) && !empty($data['pregnancy_id']) && $data['pregnancy_id'] != 0) {
        $pregnancy_id = $data['pregnancy_id'];
        if (!verifyPregnancy($pregnancy_id, $connectNow)) {
            echo json_encode(["success" => false, "message" => "Pregnancy not found"]);
            exit();
        }
    }

    // Handle recorded_by (doctor)
    $recorded_by = null;
    if (isset($data['recorded_by']) && !empty($data['recorded_by']) && $data['recorded_by'] != 0) {
        $recorded_by = $data['recorded_by'];
        if (!verifyDoctor($recorded_by, $connectNow)) {
            echo json_encode(["success" => false, "message" => "Doctor not found"]);
            exit();
        }
    }

    // Get record date (default to today if not provided)
    $record_date = isset($data['record_date']) && !empty($data['record_date']) 
        ? $data['record_date'] 
        : date('Y-m-d');

    // Assign variables for bind_param
    $user_id = $data['user_id'];
    $weight = isset($data['weight']) && $data['weight'] !== '' ? $data['weight'] : null;
    $blood_pressure_systolic = isset($data['blood_pressure_systolic']) && $data['blood_pressure_systolic'] !== '' ? $data['blood_pressure_systolic'] : null;
    $blood_pressure_diastolic = isset($data['blood_pressure_diastolic']) && $data['blood_pressure_diastolic'] !== '' ? $data['blood_pressure_diastolic'] : null;
    $blood_sugar = isset($data['blood_sugar']) && $data['blood_sugar'] !== '' ? $data['blood_sugar'] : null;
    $hemoglobin = isset($data['hemoglobin']) && $data['hemoglobin'] !== '' ? $data['hemoglobin'] : null;
    $fetal_heart_rate = isset($data['fetal_heart_rate']) && $data['fetal_heart_rate'] !== '' ? $data['fetal_heart_rate'] : null;
    $fundal_height = isset($data['fundal_height']) && $data['fundal_height'] !== '' ? $data['fundal_height'] : null;
    $risk_level = isset($data['risk_level']) ? $data['risk_level'] : 'low';
    $notes = isset($data['notes']) ? $data['notes'] : null;

    // Prepare statement
    $stmt = $connectNow->prepare("
        INSERT INTO health_records 
        (user_id, pregnancy_id, weight, blood_pressure_systolic, blood_pressure_diastolic, 
         blood_sugar, hemoglobin, fetal_heart_rate, fundal_height, risk_level, notes, record_date, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Prepare failed: " . $connectNow->error]);
        exit();
    }

    $stmt->bind_param(
        "iidiiidddsssi",
        $user_id,
        $pregnancy_id,
        $weight,
        $blood_pressure_systolic,
        $blood_pressure_diastolic,
        $blood_sugar,
        $hemoglobin,
        $fetal_heart_rate,
        $fundal_height,
        $risk_level,
        $notes,
        $record_date,
        $recorded_by
    );

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true, 
            "id" => $stmt->insert_id, 
            "message" => "Health record added successfully"
        ]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }

    exit();
}

// ================= PUT (UPDATE HEALTH RECORD) =================
if ($method === 'PUT') {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Health record ID required"]);
        exit();
    }

    $id = intval($data['id']);

    // Check if record exists
    $checkSql = "SELECT id FROM health_records WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Health record not found"]);
        exit();
    }

    // Build dynamic update query
    $updateFields = [];
    $params = [];
    $types = "";

    if (isset($data['weight']) && $data['weight'] !== '') {
        $updateFields[] = "weight = ?";
        $params[] = $data['weight'];
        $types .= "d";
    }
    if (isset($data['blood_pressure_systolic']) && $data['blood_pressure_systolic'] !== '') {
        $updateFields[] = "blood_pressure_systolic = ?";
        $params[] = $data['blood_pressure_systolic'];
        $types .= "i";
    }
    if (isset($data['blood_pressure_diastolic']) && $data['blood_pressure_diastolic'] !== '') {
        $updateFields[] = "blood_pressure_diastolic = ?";
        $params[] = $data['blood_pressure_diastolic'];
        $types .= "i";
    }
    if (isset($data['blood_sugar']) && $data['blood_sugar'] !== '') {
        $updateFields[] = "blood_sugar = ?";
        $params[] = $data['blood_sugar'];
        $types .= "d";
    }
    if (isset($data['hemoglobin']) && $data['hemoglobin'] !== '') {
        $updateFields[] = "hemoglobin = ?";
        $params[] = $data['hemoglobin'];
        $types .= "d";
    }
    if (isset($data['fetal_heart_rate']) && $data['fetal_heart_rate'] !== '') {
        $updateFields[] = "fetal_heart_rate = ?";
        $params[] = $data['fetal_heart_rate'];
        $types .= "i";
    }
    if (isset($data['fundal_height']) && $data['fundal_height'] !== '') {
        $updateFields[] = "fundal_height = ?";
        $params[] = $data['fundal_height'];
        $types .= "d";
    }
    if (isset($data['risk_level'])) {
        $updateFields[] = "risk_level = ?";
        $params[] = $data['risk_level'];
        $types .= "s";
    }
    if (isset($data['notes'])) {
        $updateFields[] = "notes = ?";
        $params[] = $data['notes'];
        $types .= "s";
    }
    if (isset($data['record_date']) && $data['record_date'] !== '') {
        $updateFields[] = "record_date = ?";
        $params[] = $data['record_date'];
        $types .= "s";
    }

    if (empty($updateFields)) {
        echo json_encode(["success" => false, "message" => "No fields to update"]);
        exit();
    }

    $params[] = $id;
    $types .= "i";

    $sql = "UPDATE health_records SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Health record updated successfully"]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }

    exit();
}

// ================= DELETE HEALTH RECORD =================
if ($method === 'DELETE') {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Health record ID required"]);
        exit();
    }

    $id = intval($data['id']);

    // Check if record exists
    $checkSql = "SELECT id FROM health_records WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Health record not found"]);
        exit();
    }

    $stmt = $connectNow->prepare("DELETE FROM health_records WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Health record deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Delete failed"]);
    }

    exit();
}

echo json_encode(["success" => false, "message" => "Invalid request"]);
?>