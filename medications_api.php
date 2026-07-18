<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

include "conn.php";

$method = $_SERVER['REQUEST_METHOD'];

// ================= VERIFY USER =================
function verifyUser($user_id, $conn) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// ================= VERIFY DOCTOR (UPDATED) =================
function verifyDoctor($doctor_id, $conn) {
    // If no doctor selected, return true (optional)
    if (!$doctor_id || $doctor_id == 0) return true;
    
    // Check in doctors table first
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return true;
    }
    
    // If not found in doctors table, check in users table with role 'doctor'
    $stmt2 = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'doctor'");
    $stmt2->bind_param("i", $doctor_id);
    $stmt2->execute();
    return $stmt2->get_result()->num_rows > 0;
}

// ================= GET ALL MEDICATIONS WITH DOCTOR NAMES =================
if ($method === 'GET') {

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

    // GET single medication
    if ($id > 0) {
        $sql = "SELECT m.*, 
                u.full_name as user_name,
                d.full_name as doctor_name
                FROM medications m
                LEFT JOIN users u ON m.user_id = u.id
                LEFT JOIN doctors d ON m.prescribed_by = d.id
                WHERE m.id = ?";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        echo json_encode([
            "success" => true,
            "data" => $result->fetch_assoc()
        ]);
        exit();
    }

    // GET medications for user
    if ($user_id > 0) {
        $sql = "SELECT m.*, d.full_name as doctor_name
                FROM medications m
                LEFT JOIN doctors d ON m.prescribed_by = d.id
                WHERE m.user_id = ?
                ORDER BY m.created_at DESC";

        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode(["success" => true, "medications" => $data]);
        exit();
    }

    // GET all medications with doctor names from doctors table
    $sql = "SELECT m.*, 
            u.full_name as user_name,
            d.full_name as doctor_name
            FROM medications m
            LEFT JOIN users u ON m.user_id = u.id
            LEFT JOIN doctors d ON m.prescribed_by = d.id
            ORDER BY m.created_at DESC";
    
    $result = $connectNow->query($sql);
    
    $medications = [];
    while ($row = $result->fetch_assoc()) {
        $medications[] = $row;
    }
    
    echo json_encode(["success" => true, "medications" => $medications]);
    exit();
}

// ================= POST (ADD) - UPDATED =================
if ($method === 'POST') {

    $data = json_decode(file_get_contents("php://input"), true);

    // Verify user exists
    if (!verifyUser($data['user_id'], $connectNow)) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit();
    }

    // Handle prescribed_by - allow null/empty
    $prescribed_by = null;
    if (isset($data['prescribed_by']) && !empty($data['prescribed_by']) && $data['prescribed_by'] != 0) {
        $prescribed_by = $data['prescribed_by'];
        
        // Verify doctor exists in doctors table
        if (!verifyDoctor($prescribed_by, $connectNow)) {
            echo json_encode(["success" => false, "message" => "Doctor not found with ID: " . $prescribed_by]);
            exit();
        }
    }

    $stmt = $connectNow->prepare("
        INSERT INTO medications 
        (user_id, medicine_name, dosage, frequency, prescribed_by, start_date, end_date, instructions, reminder_times)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "isssissss",
        $data['user_id'],
        $data['medicine_name'],
        $data['dosage'],
        $data['frequency'],
        $prescribed_by,
        $data['start_date'],
        $data['end_date'],
        $data['instructions'],
        $data['reminder_times']
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "id" => $stmt->insert_id, "message" => "Medication added successfully"]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }

    exit();
}

// ================= PUT (UPDATE) =================
if ($method === 'PUT') {

    $data = json_decode(file_get_contents("php://input"), true);

    // Handle prescribed_by - allow null/empty
    $prescribed_by = null;
    if (isset($data['prescribed_by']) && !empty($data['prescribed_by']) && $data['prescribed_by'] != 0) {
        $prescribed_by = $data['prescribed_by'];
    }

    $stmt = $connectNow->prepare("
        UPDATE medications SET
        medicine_name=?, dosage=?, frequency=?, 
        prescribed_by=?,
        end_date=?, instructions=?, reminder_times=?, is_active=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "sssssssii",
        $data['medicine_name'],
        $data['dosage'],
        $data['frequency'],
        $prescribed_by,
        $data['end_date'],
        $data['instructions'],
        $data['reminder_times'],
        $data['is_active'],
        $data['id']
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Medication updated successfully"]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }

    exit();
}

// ================= DELETE =================
if ($method === 'DELETE') {

    $data = json_decode(file_get_contents("php://input"), true);

    $stmt = $connectNow->prepare("DELETE FROM medications WHERE id=?");
    $stmt->bind_param("i", $data['id']);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Medication deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Delete failed"]);
    }

    exit();
}

echo json_encode(["success" => false, "message" => "Invalid request"]);
?>