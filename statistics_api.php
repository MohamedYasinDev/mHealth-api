<?php
// C:\xampp\htdocs\mHealth_api\statistics_api.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

include "conn.php";

$method = $_SERVER['REQUEST_METHOD'];

// Get admin email from headers
$adminEmail = '';
$headers = getallheaders();
if (isset($headers['X-Admin-Email'])) {
    $adminEmail = $headers['X-Admin-Email'];
} elseif (isset($headers['x-admin-email'])) {
    $adminEmail = $headers['x-admin-email'];
} elseif (isset($headers['Admin-Email'])) {
    $adminEmail = $headers['Admin-Email'];
} elseif (isset($_SERVER['HTTP_X_ADMIN_EMAIL'])) {
    $adminEmail = $_SERVER['HTTP_X_ADMIN_EMAIL'];
}

// Verify admin
function verifyAdmin($email, $conn) {
    if (empty($email)) return false;
    $checkAdmin = "SELECT id FROM users WHERE email = ? AND role = 'admin'";
    $stmt = $conn->prepare($checkAdmin);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

if ($method === 'GET') {
    $isAdmin = verifyAdmin($adminEmail, $connectNow);
    if (!$isAdmin) {
        echo json_encode(["success" => false, "message" => "Unauthorized. Admin only"]);
        exit();
    }
    
    $stats = [];
    
    // 1. Total Users
    $result = $connectNow->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = (int)$result->fetch_assoc()['count'];
    
    // 2. Total Mothers (role = 'mother')
    $result = $connectNow->query("SELECT COUNT(*) as count FROM users WHERE role = 'mother'");
    $stats['total_mothers'] = (int)$result->fetch_assoc()['count'];
    
    // 3. Total Doctors
    $result = $connectNow->query("SELECT COUNT(*) as count FROM doctors");
    $stats['total_doctors'] = (int)$result->fetch_assoc()['count'];
    
    // 4. Total Hospitals
    $result = $connectNow->query("SELECT COUNT(*) as count FROM hospitals");
    $stats['total_hospitals'] = (int)$result->fetch_assoc()['count'];
    
    // 5. Total Pregnancies
    $result = $connectNow->query("SELECT COUNT(*) as count FROM pregnancies");
    $stats['total_pregnancies'] = (int)$result->fetch_assoc()['count'];
    
    // 6. Total Appointments
    $result = $connectNow->query("SELECT COUNT(*) as count FROM appointments");
    $stats['total_appointments'] = (int)$result->fetch_assoc()['count'];
    
    // 7. Total Medications
    $result = $connectNow->query("SELECT COUNT(*) as count FROM medications");
    $stats['total_medications'] = (int)$result->fetch_assoc()['count'];
    
    // 8. Total Health Records
    $result = $connectNow->query("SELECT COUNT(*) as count FROM health_records");
    $stats['total_health_records'] = (int)$result->fetch_assoc()['count'];
    
    // 9. Total Lab Tests
    $result = $connectNow->query("SELECT COUNT(*) as count FROM lab_tests");
    $stats['total_lab_tests'] = (int)$result->fetch_assoc()['count'];
    
    // 10. Total Danger Signs
    $result = $connectNow->query("SELECT COUNT(*) as count FROM danger_signs");
    $stats['total_danger_signs'] = (int)$result->fetch_assoc()['count'];
    
    // 11. Total Notifications
    $result = $connectNow->query("SELECT COUNT(*) as count FROM notifications");
    $stats['total_notifications'] = (int)$result->fetch_assoc()['count'];
    
    // 12. Unread Notifications
    $result = $connectNow->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
    $stats['unread_notifications'] = (int)$result->fetch_assoc()['count'];
    
    // 13. Active Medications
    $result = $connectNow->query("SELECT COUNT(*) as count FROM medications WHERE is_active = 1");
    $stats['active_medications'] = (int)$result->fetch_assoc()['count'];
    
    // 14. Monthly Trends (last 6 months)
    $monthlyTrends = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthName = date('M', strtotime("-$i months"));
        
        // Mothers registered this month
        $result = $connectNow->query("SELECT COUNT(*) as count FROM users WHERE role = 'mother' AND DATE_FORMAT(created_at, '%Y-%m') = '$month'");
        $mothers = (int)$result->fetch_assoc()['count'];
        
        // Pregnancies this month
        $result = $connectNow->query("SELECT COUNT(*) as count FROM pregnancies WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'");
        $pregnancies = (int)$result->fetch_assoc()['count'];
        
        // Appointments this month
        $result = $connectNow->query("SELECT COUNT(*) as count FROM appointments WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'");
        $appointments = (int)$result->fetch_assoc()['count'];
        
        $monthlyTrends[] = [
            'month' => $monthName,
            'mothers' => $mothers,
            'pregnancies' => $pregnancies,
            'appointments' => $appointments
        ];
    }
    $stats['monthly_trends'] = $monthlyTrends;
    
    // 15. Appointment Status Distribution
    $statusDistribution = [];
    $result = $connectNow->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
    while ($row = $result->fetch_assoc()) {
        $statusDistribution[$row['status']] = (int)$row['count'];
    }
    $stats['appointment_status'] = $statusDistribution;
    
    // 16. Recent Activities (last 10 activities)
    $recentActivities = [];
    
    // New mothers
    $result = $connectNow->query("SELECT id, full_name, created_at FROM users WHERE role = 'mother' ORDER BY created_at DESC LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = [
            'title' => 'New mother registered',
            'subtitle' => $row['full_name'],
            'time' => $row['created_at'],
            'icon' => 'person_add',
            'color' => 'green'
        ];
    }
    
    // Recent appointments
    $result = $connectNow->query("SELECT a.*, u.full_name as user_name, d.full_name as doctor_name 
                                   FROM appointments a 
                                   LEFT JOIN users u ON a.user_id = u.id 
                                   LEFT JOIN doctors d ON a.doctor_id = d.id 
                                   ORDER BY a.created_at DESC LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = [
            'title' => 'Appointment scheduled',
            'subtitle' => $row['user_name'] . ' with Dr. ' . ($row['doctor_name'] ?? 'Unknown'),
            'time' => $row['created_at'],
            'icon' => 'calendar_today',
            'color' => 'orange'
        ];
    }
    
    // Recent health records
    $result = $connectNow->query("SELECT hr.*, u.full_name as user_name 
                                   FROM health_records hr 
                                   LEFT JOIN users u ON hr.user_id = u.id 
                                   ORDER BY hr.created_at DESC LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = [
            'title' => 'Health record updated',
            'subtitle' => $row['user_name'] . ' - Prenatal checkup',
            'time' => $row['created_at'],
            'icon' => 'favorite',
            'color' => 'pink'
        ];
    }
    
    // Sort by time (most recent first)
    usort($recentActivities, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    $stats['recent_activities'] = array_slice($recentActivities, 0, 10);
    
    // 17. Risk Level Distribution
    $riskDistribution = [];
    $result = $connectNow->query("SELECT risk_level, COUNT(*) as count FROM health_records GROUP BY risk_level");
    while ($row = $result->fetch_assoc()) {
        $riskDistribution[$row['risk_level']] = (int)$row['count'];
    }
    $stats['risk_distribution'] = $riskDistribution;
    
    // 18. Notification Type Distribution
    $notifTypeDistribution = [];
    $result = $connectNow->query("SELECT type, COUNT(*) as count FROM notifications GROUP BY type");
    while ($row = $result->fetch_assoc()) {
        $notifTypeDistribution[$row['type']] = (int)$row['count'];
    }
    $stats['notification_types'] = $notifTypeDistribution;
    
    echo json_encode(["success" => true, "statistics" => $stats]);
    exit();
}

echo json_encode(["success" => false, "message" => "Method not allowed"]);
$connectNow->close();
?>