<?php
// C:\xampp\htdocs\mHealth_api\doctor_home_api.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include "conn.php";

$method = $_SERVER['REQUEST_METHOD'];

// Get doctor user_id from request
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($method === 'GET' && $user_id > 0) {
    
    // 1️⃣ Get doctor's basic info from doctors table using user_id
    $sql = "SELECT d.*, 
                   h.name as hospital_name,
                   h.region as hospital_region,
                   u.email,
                   u.phone
            FROM doctors d
            LEFT JOIN hospitals h ON d.hospital_id = h.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE d.user_id = ? AND d.is_active = 1";
    $stmt = $connectNow->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "SQL Error: " . $connectNow->error]);
        exit();
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    
    if (!$doctor) {
        echo json_encode(["success" => false, "message" => "Doctor not found"]);
        exit();
    }
    
    $data['doctor'] = [
        'id' => (int)$doctor['id'],
        'user_id' => (int)$doctor['user_id'],
        'full_name' => $doctor['full_name'],
        'email' => $doctor['email'],
        'phone' => $doctor['phone'],
        'specialization' => $doctor['specialization'],
        'hospital_id' => $doctor['hospital_id'] ? (int)$doctor['hospital_id'] : null,
        'hospital_name' => $doctor['hospital_name'] ?? 'No Hospital Assigned',
        'hospital_region' => $doctor['hospital_region'] ?? '',
        'available_for_telemedicine' => (bool)$doctor['available_for_telemedicine'],
        'average_rating' => (float)$doctor['average_rating'],
        'is_active' => (bool)$doctor['is_active'],
    ];
    
    // 2️⃣ Get today's appointments count and list
    $today = date('Y-m-d');
    
    // Check if appointments table exists
    $checkTable = $connectNow->query("SHOW TABLES LIKE 'appointments'");
    if ($checkTable->num_rows > 0) {
        
        // Today's appointments count
        $sql = "SELECT COUNT(*) as count 
                FROM appointments 
                WHERE doctor_id = ? 
                  AND appointment_date = ? 
                  AND status = 'scheduled'";
        $stmt = $connectNow->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $doctor['id'], $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $data['today_appointments_count'] = (int)$result->fetch_assoc()['count'];
        } else {
            $data['today_appointments_count'] = 0;
        }
        
        // Today's appointments list
        $sql = "SELECT a.*, 
                       u.full_name as patient_name,
                       u.phone as patient_phone
                FROM appointments a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.doctor_id = ? 
                  AND a.appointment_date = ? 
                  AND a.status = 'scheduled'
                ORDER BY a.appointment_time ASC
                LIMIT 5";
        $stmt = $connectNow->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $doctor['id'], $today);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $todayAppointments = [];
            while ($row = $result->fetch_assoc()) {
                $todayAppointments[] = [
                    'id' => (int)$row['id'],
                    'patient_name' => $row['patient_name'] ?? 'Unknown',
                    'patient_phone' => $row['patient_phone'] ?? '',
                    'appointment_time' => date('h:i A', strtotime($row['appointment_time'])),
                    'appointment_type' => $row['appointment_type'] ?? 'checkup',
                    'reason' => $row['reason'],
                ];
            }
            $data['today_appointments'] = $todayAppointments;
        } else {
            $data['today_appointments'] = [];
        }
        
        // Total patients count
        $sql = "SELECT COUNT(DISTINCT a.user_id) as count 
                FROM appointments a
                WHERE a.doctor_id = ? 
                  AND a.status IN ('scheduled', 'completed')";
        $stmt = $connectNow->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $doctor['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $data['total_patients_count'] = (int)$result->fetch_assoc()['count'];
        } else {
            $data['total_patients_count'] = 0;
        }
        
        // Pending lab tests count
        $sql = "SELECT COUNT(*) as count 
                FROM lab_tests lt
                JOIN appointments a ON lt.appointment_id = a.id
                WHERE a.doctor_id = ? 
                  AND lt.status = 'ordered'";
        $stmt = $connectNow->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $doctor['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $data['pending_lab_tests_count'] = (int)$result->fetch_assoc()['count'];
        } else {
            $data['pending_lab_tests_count'] = 0;
        }
        
        // Upcoming appointments count
        $sql = "SELECT COUNT(*) as count 
                FROM appointments 
                WHERE doctor_id = ? 
                  AND appointment_date > ? 
                  AND status = 'scheduled'";
        $stmt = $connectNow->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $doctor['id'], $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $data['upcoming_appointments_count'] = (int)$result->fetch_assoc()['count'];
        } else {
            $data['upcoming_appointments_count'] = 0;
        }
        
        // Recent patients
        $sql = "SELECT DISTINCT a.user_id, 
                       u.full_name as patient_name,
                       a.appointment_date,
                       a.status
                FROM appointments a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.doctor_id = ? 
                ORDER BY a.appointment_date DESC, a.id DESC
                LIMIT 5";
        $stmt = $connectNow->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $doctor['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $recentPatients = [];
            while ($row = $result->fetch_assoc()) {
                $recentPatients[] = [
                    'user_id' => (int)$row['user_id'],
                    'patient_name' => $row['patient_name'] ?? 'Unknown',
                    'appointment_date' => $row['appointment_date'],
                    'status' => $row['status'] ?? 'scheduled',
                ];
            }
            $data['recent_patients'] = $recentPatients;
        } else {
            $data['recent_patients'] = [];
        }
        
    } else {
        // Appointments table doesn't exist - set defaults
        $data['today_appointments_count'] = 0;
        $data['today_appointments'] = [];
        $data['total_patients_count'] = 0;
        $data['pending_lab_tests_count'] = 0;
        $data['upcoming_appointments_count'] = 0;
        $data['recent_patients'] = [];
    }
    
    // 8️⃣ Get unread notifications count
    $checkTable = $connectNow->query("SHOW TABLES LIKE 'notifications'");
    if ($checkTable->num_rows > 0) {
        $sql = "SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? 
                  AND is_read = 0";
        $stmt = $connectNow->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data['unread_notifications_count'] = (int)$result->fetch_assoc()['count'];
        } else {
            $data['unread_notifications_count'] = 0;
        }
    } else {
        $data['unread_notifications_count'] = 0;
    }
    
    // 9️⃣ Get doctor's rating distribution
    $checkTable = $connectNow->query("SHOW TABLES LIKE 'doctor_reviews'");
    if ($checkTable->num_rows > 0) {
        $sql = "SELECT 
                    COUNT(*) as total_reviews,
                    AVG(rating) as average_rating,
                    SUM(CASE WHEN rating >= 4.5 THEN 1 ELSE 0 END) as excellent,
                    SUM(CASE WHEN rating >= 3.5 AND rating < 4.5 THEN 1 ELSE 0 END) as good,
                    SUM(CASE WHEN rating >= 2.5 AND rating < 3.5 THEN 1 ELSE 0 END) as average,
                    SUM(CASE WHEN rating < 2.5 THEN 1 ELSE 0 END) as poor
                FROM doctor_reviews 
                WHERE doctor_id = ?";
        $stmt = $connectNow->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $doctor['id']);
            $stmt->execute();
            $reviews = $stmt->get_result()->fetch_assoc();
            $data['reviews'] = [
                'total_reviews' => (int)($reviews['total_reviews'] ?? 0),
                'average_rating' => (float)($reviews['average_rating'] ?? $doctor['average_rating']),
                'excellent' => (int)($reviews['excellent'] ?? 0),
                'good' => (int)($reviews['good'] ?? 0),
                'average' => (int)($reviews['average'] ?? 0),
                'poor' => (int)($reviews['poor'] ?? 0),
            ];
        } else {
            $data['reviews'] = [
                'total_reviews' => 0,
                'average_rating' => $doctor['average_rating'],
                'excellent' => 0,
                'good' => 0,
                'average' => 0,
                'poor' => 0,
            ];
        }
    } else {
        $data['reviews'] = [
            'total_reviews' => 0,
            'average_rating' => $doctor['average_rating'],
            'excellent' => 0,
            'good' => 0,
            'average' => 0,
            'poor' => 0,
        ];
    }
    
    // 🔟 Get doctor's schedule
    $checkTable = $connectNow->query("SHOW TABLES LIKE 'doctor_schedules'");
    if ($checkTable->num_rows > 0) {
        $sql = "SELECT * FROM doctor_schedules WHERE doctor_id = ?";
        $stmt = $connectNow->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $doctor['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $schedule = $result->fetch_assoc();
                $data['schedule'] = [
                    'monday' => $schedule['monday'] ?? null,
                    'tuesday' => $schedule['tuesday'] ?? null,
                    'wednesday' => $schedule['wednesday'] ?? null,
                    'thursday' => $schedule['thursday'] ?? null,
                    'friday' => $schedule['friday'] ?? null,
                    'saturday' => $schedule['saturday'] ?? null,
                    'sunday' => $schedule['sunday'] ?? null,
                ];
            } else {
                $data['schedule'] = null;
            }
        } else {
            $data['schedule'] = null;
        }
    } else {
        $data['schedule'] = null;
    }
    
    echo json_encode(["success" => true, "data" => $data]);
    exit();
}

echo json_encode(["success" => false, "message" => "Invalid request. User ID required"]);
$connectNow->close();
?>