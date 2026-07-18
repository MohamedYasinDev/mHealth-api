<?php
// C:\xampp\htdocs\mHealth_api\mother_dashboard_api.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include "conn.php";

$method = $_SERVER['REQUEST_METHOD'];

// Get user ID from request
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($method === 'GET' && $user_id > 0) {
    
    // 1. Get mother's basic info
    $sql = "SELECT id, full_name, email, phone FROM users WHERE id = ? AND role = 'mother'";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        echo json_encode(["success" => false, "message" => "Mother not found"]);
        exit();
    }
    
    $data['user'] = $user;
    
    // 2. Get active pregnancy
    $sql = "SELECT id, pregnancy_number, baby_count, expected_delivery_date, created_at
            FROM pregnancies 
            WHERE mother_id = ? AND pregnancy_status = 'ongoing'
            ORDER BY id DESC LIMIT 1";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pregnancy = $result->fetch_assoc();
    
    if ($pregnancy) {
        // Calculate week based on expected delivery date (40 weeks pregnancy)
        $expectedDate = new DateTime($pregnancy['expected_delivery_date']);
        $today = new DateTime();
        $daysRemaining = $today->diff($expectedDate)->days;
        $week = 40 - floor($daysRemaining / 7);
        if ($week < 1) $week = 1;
        if ($week > 40) $week = 40;
        
        $data['pregnancy_week'] = $week;
        $data['expected_delivery_date'] = $pregnancy['expected_delivery_date'];
        $data['days_remaining'] = $daysRemaining;
        $data['baby_count'] = $pregnancy['baby_count'];
     } else {
        // Pregnancy ma jirto — null soo celi
        $data['pregnancy_week'] = 0;
        $data['expected_delivery_date'] = null;
        $data['days_remaining'] = 0;
        $data['baby_count'] = 0;
    }
    
    // 3. Get next appointment
    $sql = "SELECT a.*, 
                   d.full_name as doctor_name, 
                   d.specialization as doctor_specialization,
                   h.name as hospital_name
            FROM appointments a
            LEFT JOIN doctors d ON a.doctor_id = d.id
            LEFT JOIN hospitals h ON a.hospital_id = h.id
            WHERE a.user_id = ? 
              AND a.appointment_date >= CURDATE() 
              AND a.status = 'scheduled'
            ORDER BY a.appointment_date ASC, a.appointment_time ASC
            LIMIT 1";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $nextAppointment = $result->fetch_assoc();
    
    if ($nextAppointment) {
        $data['next_appointment'] = $nextAppointment['appointment_date'];
        $data['next_appointment_time'] = date('h:i A', strtotime($nextAppointment['appointment_time']));
        $data['doctor_name'] = $nextAppointment['doctor_name'];
        $data['doctor_specialization'] = $nextAppointment['doctor_specialization'];
        $data['hospital_name'] = $nextAppointment['hospital_name'];
    } else {
        $data['next_appointment'] = null;
        $data['next_appointment_time'] = null;
        $data['doctor_name'] = null;
        $data['doctor_specialization'] = null;
        $data['hospital_name'] = null;
    }
    
    // 4. Get active medications count
    $sql = "SELECT COUNT(*) as count 
            FROM medications 
            WHERE user_id = ? 
              AND is_active = 1 
              AND (end_date IS NULL OR end_date >= CURDATE())";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data['medications_count'] = (int)$result->fetch_assoc()['count'];
    
    // 5. Get health records count
    $sql = "SELECT COUNT(*) as count FROM health_records WHERE user_id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data['health_records_count'] = (int)$result->fetch_assoc()['count'];
    
    // 6. Get upcoming appointments count
    $sql = "SELECT COUNT(*) as count 
            FROM appointments 
            WHERE user_id = ? 
              AND appointment_date >= CURDATE() 
              AND status = 'scheduled'";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data['appointments_count'] = (int)$result->fetch_assoc()['count'];
    
    // 7. Get recent health record
    $sql = "SELECT weight, blood_pressure_systolic, blood_pressure_diastolic, record_date
            FROM health_records 
            WHERE user_id = ? 
            ORDER BY record_date DESC 
            LIMIT 1";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recentRecord = $result->fetch_assoc();
    
    if ($recentRecord) {
        $data['recent_weight'] = (float)$recentRecord['weight'];
        $data['recent_bp_systolic'] = (int)$recentRecord['blood_pressure_systolic'];
        $data['recent_bp_diastolic'] = (int)$recentRecord['blood_pressure_diastolic'];
        $data['recent_record_date'] = $recentRecord['record_date'];
    } else {
        $data['recent_weight'] = null;
        $data['recent_bp_systolic'] = null;
        $data['recent_bp_diastolic'] = null;
        $data['recent_record_date'] = null;
    }
    
    // 8. Calculate baby weight based on week
    $week = $data['pregnancy_week'];
    if ($week <= 12) {
        $data['baby_weight'] = '15g';
    } elseif ($week <= 16) {
        $data['baby_weight'] = '100g';
    } elseif ($week <= 20) {
        $data['baby_weight'] = '300g';
    } elseif ($week <= 24) {
        $data['baby_weight'] = '600g';
    } elseif ($week <= 28) {
        $data['baby_weight'] = '1kg';
    } elseif ($week <= 32) {
        $data['baby_weight'] = '1.8kg';
    } elseif ($week <= 36) {
        $data['baby_weight'] = '2.5kg';
    } else {
        $data['baby_weight'] = '3kg+';
    }
    
    echo json_encode(["success" => true, "data" => $data]);
    exit();
}

echo json_encode(["success" => false, "message" => "Invalid request. User ID required"]);
?>