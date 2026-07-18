<?php
// C:\xampp\htdocs\mHealth_api\danger_signs_api.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

error_reporting(E_ALL);
ini_set('display_errors', 0);

include "conn.php";

$method = $_SERVER['REQUEST_METHOD'];

// ================= GET ALL DANGER SIGNS =================
if ($method === 'GET') {

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $trimester = isset($_GET['trimester']) ? $_GET['trimester'] : '';

    // GET single danger sign
    if ($id > 0) {
        $sql = "SELECT * FROM danger_signs WHERE id = ?";
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
            echo json_encode(["success" => false, "message" => "Danger sign not found"]);
        }
        exit();
    }

    // GET danger signs by trimester
    if (!empty($trimester)) {
        $sql = "SELECT * FROM danger_signs WHERE trimester = ? AND is_active = 1 ORDER BY id ASC";
        $stmt = $connectNow->prepare($sql);
        $stmt->bind_param("s", $trimester);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $signs = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $signs[] = $row;
            }
        }
        
        echo json_encode(["success" => true, "danger_signs" => $signs, "count" => count($signs)]);
        exit();
    }

    // GET all danger signs
    $sql = "SELECT * FROM danger_signs ORDER BY trimester ASC, id ASC";
    $result = $connectNow->query($sql);
    
    if (!$result) {
        echo json_encode([
            "success" => false, 
            "message" => "Query failed: " . $connectNow->error
        ]);
        exit();
    }
    
    $signs = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $signs[] = $row;
        }
    }
    
    echo json_encode(["success" => true, "danger_signs" => $signs, "count" => count($signs)]);
    exit();
}

// ================= POST (ADD DANGER SIGN) =================
if ($method === 'POST') {

    $data = json_decode(file_get_contents("php://input"), true);

    // Validate required fields
    if (!isset($data['trimester']) || empty($data['trimester'])) {
        echo json_encode(["success" => false, "message" => "Trimester required"]);
        exit();
    }
    
    if (!isset($data['title']) || empty(trim($data['title']))) {
        echo json_encode(["success" => false, "message" => "Title required"]);
        exit();
    }
    
    if (!isset($data['description']) || empty(trim($data['description']))) {
        echo json_encode(["success" => false, "message" => "Description required"]);
        exit();
    }
    
    if (!isset($data['emergency_action']) || empty(trim($data['emergency_action']))) {
        echo json_encode(["success" => false, "message" => "Emergency action required"]);
        exit();
    }

    $trimester = $data['trimester'];
    $title = trim($data['title']);
    $description = trim($data['description']);
    $emergency_action = trim($data['emergency_action']);
    $call_doctor_if = isset($data['call_doctor_if']) ? trim($data['call_doctor_if']) : null;
    $go_to_hospital_if = isset($data['go_to_hospital_if']) ? trim($data['go_to_hospital_if']) : null;
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

    $stmt = $connectNow->prepare("
        INSERT INTO danger_signs (trimester, title, description, emergency_action, call_doctor_if, go_to_hospital_if, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Prepare failed: " . $connectNow->error]);
        exit();
    }

    $stmt->bind_param("ssssssi", $trimester, $title, $description, $emergency_action, $call_doctor_if, $go_to_hospital_if, $is_active);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true, 
            "id" => $stmt->insert_id, 
            "message" => "Danger sign added successfully"
        ]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }

    exit();
}

// ================= PUT (UPDATE DANGER SIGN) =================
if ($method === 'PUT') {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Danger sign ID required"]);
        exit();
    }

    $id = intval($data['id']);

    // Check if exists
    $checkSql = "SELECT id FROM danger_signs WHERE id = ?";
    $checkStmt = $connectNow->prepare($checkSql);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Danger sign not found"]);
        exit();
    }

    // Build dynamic update query
    $updateFields = [];
    $params = [];
    $types = "";

    if (isset($data['trimester'])) {
        $updateFields[] = "trimester = ?";
        $params[] = $data['trimester'];
        $types .= "s";
    }
    if (isset($data['title'])) {
        $updateFields[] = "title = ?";
        $params[] = trim($data['title']);
        $types .= "s";
    }
    if (isset($data['description'])) {
        $updateFields[] = "description = ?";
        $params[] = trim($data['description']);
        $types .= "s";
    }
    if (isset($data['emergency_action'])) {
        $updateFields[] = "emergency_action = ?";
        $params[] = trim($data['emergency_action']);
        $types .= "s";
    }
    if (isset($data['call_doctor_if'])) {
        $updateFields[] = "call_doctor_if = ?";
        $params[] = trim($data['call_doctor_if']);
        $types .= "s";
    }
    if (isset($data['go_to_hospital_if'])) {
        $updateFields[] = "go_to_hospital_if = ?";
        $params[] = trim($data['go_to_hospital_if']);
        $types .= "s";
    }
    if (isset($data['is_active'])) {
        $updateFields[] = "is_active = ?";
        $params[] = (int)$data['is_active'];
        $types .= "i";
    }

    if (empty($updateFields)) {
        echo json_encode(["success" => false, "message" => "No fields to update"]);
        exit();
    }

    $params[] = $id;
    $types .= "i";

    $sql = "UPDATE danger_signs SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Danger sign updated successfully"]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }

    exit();
}

// ================= DELETE DANGER SIGN =================
if ($method === 'DELETE') {

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id'])) {
        echo json_encode(["success" => false, "message" => "Danger sign ID required"]);
        exit();
    }

    $id = intval($data['id']);

    $stmt = $connectNow->prepare("DELETE FROM danger_signs WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Danger sign deleted successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Delete failed"]);
    }

    exit();
}

echo json_encode(["success" => false, "message" => "Invalid request"]);
?>