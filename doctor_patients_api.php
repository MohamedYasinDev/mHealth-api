<?php
// C:\xampp\htdocs\mHealth_api\doctor_patients_api.php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Email");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include "conn.php";

if (!$connectNow) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;

if ($doctor_id <= 0) {
    echo json_encode(["success" => false, "message" => "Doctor ID required"]);
    exit();
}

// Check doctor exists
$checkStmt = $connectNow->prepare("SELECT id FROM doctors WHERE id = ?");
$checkStmt->bind_param("i", $doctor_id);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Doctor not found"]);
    $checkStmt->close();
    exit();
}
$checkStmt->close();

// ✅ FIXED QUERY — JOIN mothers table to get blood_group, expected_delivery, etc.
$sql = "SELECT DISTINCT
            u.id                    AS user_id,
            u.full_name,
            u.phone,
            u.email,

            -- From mothers table
            m.blood_group,
            m.date_of_birth,
            m.last_period_date,
            m.expected_delivery,
            m.emergency_contact_name,
            m.emergency_contact_phone,

            -- Calculate pregnancy week from last_period_date
            CASE 
                WHEN m.last_period_date IS NOT NULL 
                THEN FLOOR(DATEDIFF(CURDATE(), m.last_period_date) / 7)
                ELSE 24
            END AS pregnancy_week,

            -- Risk level from latest health record
            (
                SELECT hr.risk_level 
                FROM health_records hr 
                WHERE hr.user_id = u.id 
                ORDER BY hr.record_date DESC 
                LIMIT 1
            ) AS risk_level,

            -- Last visit (last completed appointment)
            MAX(CASE WHEN a.status = 'completed' THEN a.appointment_date END) AS last_visit_date,

            -- Next appointment
            MIN(CASE WHEN a.status = 'scheduled' AND a.appointment_date >= CURDATE() 
                THEN a.appointment_date END)                                   AS next_appointment_date,

            COUNT(DISTINCT a.id)                                               AS total_visits,
            COUNT(DISTINCT CASE WHEN a.status = 'completed' THEN a.id END)    AS completed_visits

        FROM appointments a
        INNER JOIN users   u ON a.user_id  = u.id
        LEFT  JOIN mothers m ON m.user_id  = u.id
        WHERE a.doctor_id = ?
          AND u.role      = 'mother'
        GROUP BY
            u.id, u.full_name, u.phone, u.email,
            m.blood_group, m.date_of_birth, m.last_period_date,
            m.expected_delivery, m.emergency_contact_name, m.emergency_contact_phone
        ORDER BY u.full_name ASC";

$stmt = $connectNow->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "SQL error: " . $connectNow->error]);
    exit();
}

$stmt->bind_param("i", $doctor_id);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Execute error: " . $stmt->error]);
    $stmt->close();
    exit();
}

$result = $stmt->get_result();
$patients = [];

while ($row = $result->fetch_assoc()) {

    // Calculate age
    $age = null;
    if (!empty($row['date_of_birth']) && $row['date_of_birth'] !== '0000-00-00') {
        $age = (new DateTime($row['date_of_birth']))->diff(new DateTime())->y;
    }

    // Pregnancy week (cap between 1–42)
    $week = max(1, min(42, (int)$row['pregnancy_week']));

    // Trimester
    if ($week <= 12)      $trimester = '1st';
    elseif ($week <= 28)  $trimester = '2nd';
    else                  $trimester = '3rd';

    // Due date — prefer expected_delivery from mothers table, else calculate
    if (!empty($row['expected_delivery'])) {
        $dueDate = $row['expected_delivery'];
    } elseif (!empty($row['last_period_date'])) {
        $dueDate = date('Y-m-d', strtotime($row['last_period_date'] . ' +280 days'));
    } else {
        $dueDate = date('Y-m-d', strtotime('+' . (40 - $week) . ' weeks'));
    }

    // Risk level — default to 'low' if no health record yet
    $riskLevel = !empty($row['risk_level']) ? $row['risk_level'] : 'low';

    // Blood type — from mothers.blood_group
    $bloodType = !empty($row['blood_group']) ? $row['blood_group'] : 'Unknown';

    // Avatar (first letter)
    $avatar = !empty($row['full_name']) ? strtoupper(substr($row['full_name'], 0, 1)) : '?';

    $patients[] = [
        'user_id'               => (int)$row['user_id'],
        'name'                  => $row['full_name'] ?? 'Unknown',
        'phone'                 => $row['phone']     ?? '',
        'email'                 => $row['email']     ?? '',
        'age'                   => $age,
        'pregnancy_week'        => $week,
        'trimester'             => $trimester,
        'due_date'              => $dueDate,
        'risk_level'            => $riskLevel,
        'blood_type'            => $bloodType,
        'last_visit'            => $row['last_visit_date']       ?? null,
        'next_appointment'      => $row['next_appointment_date'] ?? null,
        'total_visits'          => (int)($row['total_visits']    ?? 0),
        'completed_visits'      => (int)($row['completed_visits']?? 0),
        'emergency_contact'     => $row['emergency_contact_name']  ?? null,
        'emergency_phone'       => $row['emergency_contact_phone'] ?? null,
        'avatar'                => $avatar,
    ];
}

$stmt->close();

// Statistics
$high   = count(array_filter($patients, fn($p) => $p['risk_level'] === 'high'));
$medium = count(array_filter($patients, fn($p) => $p['risk_level'] === 'medium'));
$low    = count(array_filter($patients, fn($p) => $p['risk_level'] === 'low'));

echo json_encode([
    "success"    => true,
    "patients"   => $patients,
    "count"      => count($patients),
    "doctor_id"  => $doctor_id,
    "statistics" => [
        "total"                  => count($patients),
        "high_risk"              => $high,
        "medium_risk"            => $medium,
        "low_risk"               => $low,
        "active_last_30_days"    => count($patients),
        "upcoming_appointments"  => 0
    ]
]);

$connectNow->close();
?>