<?php
// C:\xampp\htdocs\mHealth_api\dashboard_api.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, X-Admin-Email");

include "conn.php";

$method = $_SERVER['REQUEST_METHOD'];

// Get admin email from headers
$adminEmail = '';
$headers = getallheaders();
if (isset($headers['X-Admin-Email'])) {
    $adminEmail = $headers['X-Admin-Email'];
} elseif (isset($headers['x-admin-email'])) {
    $adminEmail = $headers['x-admin-email'];
} elseif (isset($_SERVER['HTTP_X_ADMIN_EMAIL'])) {
    $adminEmail = $_SERVER['HTTP_X_ADMIN_EMAIL'];
}

// Verify admin
function verifyAdmin($email, $conn) {
    if (empty($email)) return false;
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

if ($method === 'GET') {
    if (!verifyAdmin($adminEmail, $connectNow)) {
        echo json_encode(["success" => false, "message" => "Unauthorized"]);
        exit();
    }
    
    $data = [];
    
    // 1. Total Users
    $result = $connectNow->query("SELECT COUNT(*) as count FROM users");
    $data['total_users'] = (int)$result->fetch_assoc()['count'];
    
    // 2. Total Mothers
    $result = $connectNow->query("SELECT COUNT(*) as count FROM users WHERE role = 'mother'");
    $data['total_mothers'] = (int)$result->fetch_assoc()['count'];
    
    // 3. Total Doctors
    $result = $connectNow->query("SELECT COUNT(*) as count FROM doctors");
    $data['total_doctors'] = (int)$result->fetch_assoc()['count'];
    
    // 4. Total Hospitals
    $result = $connectNow->query("SELECT COUNT(*) as count FROM hospitals");
    $data['total_hospitals'] = (int)$result->fetch_assoc()['count'];
    
    // 5. Total Appointments
    $result = $connectNow->query("SELECT COUNT(*) as count FROM appointments");
    $data['total_appointments'] = (int)$result->fetch_assoc()['count'];
    
    // 6. Today's Appointments
    $today = date('Y-m-d');
    $result = $connectNow->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = '$today'");
    $data['today_appointments'] = (int)$result->fetch_assoc()['count'];
    
    // 7. Pending Appointments (scheduled for today)
    $result = $connectNow->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = '$today' AND status = 'scheduled'");
    $data['pending_appointments'] = (int)$result->fetch_assoc()['count'];
    
    // 8. Percentage changes (compared to last month)
    $lastMonth = date('Y-m', strtotime('-1 month'));
    $currentMonth = date('Y-m');
    
    // Mothers growth
    $result = $connectNow->query("SELECT COUNT(*) as count FROM users WHERE role = 'mother' AND DATE_FORMAT(created_at, '%Y-%m') = '$lastMonth'");
    $lastMonthMothers = (int)$result->fetch_assoc()['count'];
    $result = $connectNow->query("SELECT COUNT(*) as count FROM users WHERE role = 'mother' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'");
    $currentMonthMothers = (int)$result->fetch_assoc()['count'];
    
    if ($lastMonthMothers > 0) {
        $mothersGrowth = round(($currentMonthMothers - $lastMonthMothers) / $lastMonthMothers * 100);
    } else {
        $mothersGrowth = $currentMonthMothers > 0 ? 100 : 0;
    }
    $data['mothers_growth'] = $mothersGrowth;
    
    // Appointments growth
    $result = $connectNow->query("SELECT COUNT(*) as count FROM appointments WHERE DATE_FORMAT(created_at, '%Y-%m') = '$lastMonth'");
    $lastMonthAppointments = (int)$result->fetch_assoc()['count'];
    $result = $connectNow->query("SELECT COUNT(*) as count FROM appointments WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'");
    $currentMonthAppointments = (int)$result->fetch_assoc()['count'];
    
    if ($lastMonthAppointments > 0) {
        $appointmentsGrowth = round(($currentMonthAppointments - $lastMonthAppointments) / $lastMonthAppointments * 100);
    } else {
        $appointmentsGrowth = $currentMonthAppointments > 0 ? 100 : 0;
    }
    $data['appointments_growth'] = $appointmentsGrowth;
    
    // 9. Recent Users (last 5 mothers)
    $recentUsers = [];
    $result = $connectNow->query("SELECT id, full_name, email, phone, created_at FROM users WHERE role = 'mother' ORDER BY created_at DESC LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        $recentUsers[] = [
            'id' => (int)$row['id'],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'created_at' => $row['created_at']
        ];
    }
    $data['recent_users'] = $recentUsers;
    
    // 10. Recent Appointments (last 5)
    $recentAppointments = [];
    $result = $connectNow->query("SELECT a.*, u.full_name as user_name, d.full_name as doctor_name 
                                   FROM appointments a 
                                   LEFT JOIN users u ON a.user_id = u.id 
                                   LEFT JOIN doctors d ON a.doctor_id = d.id 
                                   ORDER BY a.created_at DESC LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        $recentAppointments[] = [
            'id' => (int)$row['id'],
            'user_name' => $row['user_name'],
            'doctor_name' => $row['doctor_name'],
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'status' => $row['status']
        ];
    }
    $data['recent_appointments'] = $recentAppointments;
    
    // 11. Active vs Inactive Doctors
    $result = $connectNow->query("SELECT COUNT(*) as count FROM doctors WHERE is_active = 1");
    $data['active_doctors'] = (int)$result->fetch_assoc()['count'];
    $result = $connectNow->query("SELECT COUNT(*) as count FROM doctors WHERE is_active = 0");
    $data['inactive_doctors'] = (int)$result->fetch_assoc()['count'];
    // 12. Active Medications
$result = $connectNow->query("
    SELECT COUNT(*) as count 
    FROM medications 
    WHERE is_active = 1 
    AND (end_date IS NULL OR end_date = '' OR end_date >= CURDATE())
");
$data['active_medications'] = (int)$result->fetch_assoc()['count'];
    
    // 12. Appointment Status Summary
    $statusSummary = [];
    $result = $connectNow->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
    while ($row = $result->fetch_assoc()) {
        $statusSummary[$row['status']] = (int)$row['count'];
    }
    $data['appointment_status'] = $statusSummary;
    
    echo json_encode(["success" => true, "data" => $data]);
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
?>