<?php 
// C:\xampp\htdocs\mHealth_api\chatbot_api.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conn.php';

// ── Gemini API ────────────────────────────────────────────────────────────
// MUHIIM: Key-gan waa la arkay (exposed) — Google AI Studio ka samee key CUSUB,
// kan halkan ku beddel, kan hore-na delete garee.
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
// ── Input ─────────────────────────────────────────────────────────────────
$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message']   ?? '');
$userId  = intval($input['user_id'] ?? 0);
$role    = strtolower($input['role'] ?? 'mother');
$history = $input['history']        ?? [];

if (!$message || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Message and user_id required']);
    exit();
}

// ═════════════════════════════════════════════════════════════════════════
// DATABASE CONTEXT (role-based)
// ═════════════════════════════════════════════════════════════════════════
$context = '';

try {

    if ($role === 'mother') {
        // Mother profile
        $stmt = $connectNow->prepare("
            SELECT u.full_name, u.phone,
                   m.blood_group, m.date_of_birth,
                   m.last_period_date, m.expected_delivery,
                   m.emergency_contact_name, m.emergency_contact_phone
            FROM users u
            LEFT JOIN mothers m ON m.user_id = u.id
            WHERE u.id = ? AND u.role = 'mother'
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $mother = $stmt->get_result()->fetch_assoc();

        // Pregnancy
        $stmt2 = $connectNow->prepare("
            SELECT baby_count, pregnancy_status, expected_delivery_date,
                   FLOOR(DATEDIFF(NOW(), last_period_date) / 7) AS current_week
            FROM pregnancies p
            JOIN mothers m ON p.mother_id = m.id
            WHERE m.user_id = ?
            ORDER BY p.created_at DESC LIMIT 1
        ");
        $stmt2->bind_param('i', $userId);
        $stmt2->execute();
        $pregnancy = $stmt2->get_result()->fetch_assoc();

        // Appointments
        $stmt3 = $connectNow->prepare("
            SELECT a.appointment_date, a.appointment_time,
                   a.appointment_type, a.status,
                   d.full_name AS doctor_name, d.specialization,
                   h.name AS hospital_name
            FROM appointments a
            LEFT JOIN doctors d ON a.doctor_id = d.id
            LEFT JOIN hospitals h ON a.hospital_id = h.id
            WHERE a.user_id = ?
            ORDER BY a.appointment_date DESC LIMIT 5
        ");
        $stmt3->bind_param('i', $userId);
        $stmt3->execute();
        $appointments = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);

        // Health Records
        $stmt4 = $connectNow->prepare("
            SELECT weight, blood_pressure_systolic, blood_pressure_diastolic,
                   fetal_heart_rate, risk_level, record_date
            FROM health_records
            WHERE user_id = ?
            ORDER BY record_date DESC LIMIT 3
        ");
        $stmt4->bind_param('i', $userId);
        $stmt4->execute();
        $healthRecords = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);

        // Active Medications
        $stmt5 = $connectNow->prepare("
            SELECT medicine_name, dosage, frequency, instructions
            FROM medications
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt5->bind_param('i', $userId);
        $stmt5->execute();
        $medications = $stmt5->get_result()->fetch_all(MYSQLI_ASSOC);

        // Lab Tests
        $stmt6 = $connectNow->prepare("
            SELECT test_name, test_date, result, status
            FROM lab_tests
            WHERE user_id = ?
            ORDER BY test_date DESC LIMIT 5
        ");
        $stmt6->bind_param('i', $userId);
        $stmt6->execute();
        $labTests = $stmt6->get_result()->fetch_all(MYSQLI_ASSOC);

        // Build context
        $context = "=== XOGTA HOOYO ===\n";
        if ($mother) {
            $context .= "Magac: {$mother['full_name']}\n";
            $context .= "Xaaladda Dhiiga: {$mother['blood_group']}\n";
            $context .= "Taariikhda Dhalaashada: {$mother['date_of_birth']}\n";
            $context .= "LMP (Last Period): {$mother['last_period_date']}\n";
            $context .= "Taariikhda Dhalashada: {$mother['expected_delivery']}\n";
            $context .= "Xiriirka Xaaladaha: {$mother['emergency_contact_name']} ({$mother['emergency_contact_phone']})\n";
        }
        if ($pregnancy) {
            $context .= "\n=== UURKA ===\n";
            $context .= "Toddobaadka Hadda: {$pregnancy['current_week']}\n";
            $context .= "Tirada Carruurta: {$pregnancy['baby_count']}\n";
            $context .= "Xaaladda: {$pregnancy['pregnancy_status']}\n";
            $context .= "Taariikhda Dhalaasha: {$pregnancy['expected_delivery_date']}\n";
        }
        if ($appointments) {
            $context .= "\n=== BALLAMAHA ===\n";
            foreach ($appointments as $a) {
                $context .= "- {$a['appointment_date']} {$a['appointment_time']} | {$a['appointment_type']} | {$a['status']} | Dr.{$a['doctor_name']} ({$a['specialization']}) | {$a['hospital_name']}\n";
            }
        }
        if ($healthRecords) {
            $context .= "\n=== DIIWAANKA CAAFIMAADKA ===\n";
            foreach ($healthRecords as $h) {
                $context .= "- Taariikhda: {$h['record_date']} | Miisaanka: {$h['weight']}kg | BP: {$h['blood_pressure_systolic']}/{$h['blood_pressure_diastolic']} | FHR: {$h['fetal_heart_rate']} | Khatarta: {$h['risk_level']}\n";
            }
        }
        if ($medications) {
            $context .= "\n=== DAWOOYINKA FIRFIRCOON ===\n";
            foreach ($medications as $m) {
                $context .= "- {$m['medicine_name']} | {$m['dosage']} | {$m['frequency']}\n";
            }
        }
        if ($labTests) {
            $context .= "\n=== BAARITAANNADA LAB ===\n";
            foreach ($labTests as $l) {
                $context .= "- {$l['test_name']} | {$l['test_date']} | {$l['status']} | {$l['result']}\n";
            }
        }

    } elseif ($role === 'doctor') {
        // Doctor profile
        $stmt = $connectNow->prepare("
            SELECT u.full_name, d.specialization, d.phone,
                   h.name AS hospital_name
            FROM users u
            JOIN doctors d ON d.user_id = u.id
            LEFT JOIN hospitals h ON d.hospital_id = h.id
            WHERE u.id = ?
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $doctor = $stmt->get_result()->fetch_assoc();

        // Doctor's patients
        $stmt2 = $connectNow->prepare("
            SELECT DISTINCT u.full_name, m.blood_group, m.expected_delivery,
                   FLOOR(DATEDIFF(NOW(), m.last_period_date) / 7) AS week,
                   (SELECT risk_level FROM health_records hr
                    WHERE hr.user_id = u.id
                    ORDER BY record_date DESC LIMIT 1) AS risk_level
            FROM doctors d
            JOIN appointments a ON a.doctor_id = d.id
            JOIN users u ON a.user_id = u.id
            JOIN mothers m ON m.user_id = u.id
            WHERE d.user_id = ?
            LIMIT 20
        ");
        $stmt2->bind_param('i', $userId);
        $stmt2->execute();
        $patients = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

        // Today's appointments
        $stmt3 = $connectNow->prepare("
            SELECT a.appointment_time, a.appointment_type, a.status,
                   u.full_name AS patient_name
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users u ON a.user_id = u.id
            WHERE d.user_id = ? AND a.appointment_date = CURDATE()
            ORDER BY a.appointment_time ASC
        ");
        $stmt3->bind_param('i', $userId);
        $stmt3->execute();
        $todayAppts = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);

        $context = "=== PROFILE DHAKHTAR ===\n";
        if ($doctor) {
            $context .= "Magac: Dr. {$doctor['full_name']}\n";
            $context .= "Takhasuska: {$doctor['specialization']}\n";
            $context .= "Isbitaalka: {$doctor['hospital_name']}\n";
        }
        $patientCount = count($patients);
        $context .= "\n=== BUKAANNADAYDA ($patientCount) ===\n";
        foreach ($patients as $p) {
            $context .= "- {$p['full_name']} | Week {$p['week']} | Due: {$p['expected_delivery']} | Risk: {$p['risk_level']}\n";
        }
        $context .= "\n=== BALLAMAHA MAANTA ===\n";
        if ($todayAppts) {
            foreach ($todayAppts as $a) {
                $context .= "- {$a['appointment_time']} | {$a['patient_name']} | {$a['appointment_type']} | {$a['status']}\n";
            }
        } else {
            $context .= "Maanta ma jiraan ballamaha.\n";
        }

    } elseif ($role === 'admin') {
        // ═════════════════════════════════════════════════════════════════
        // SMART CONTEXT — su'aasha scan garee, xogta la xiriirta kaliya soo qaado
        // ═════════════════════════════════════════════════════════════════

        // Su'aasha + 2-da fariin ee u dambeeyay history-ga (follow-up questions)
        $scanText = $message;
        foreach (array_slice($history, -2) as $h) {
            $scanText .= ' ' . ($h['content'] ?? '');
        }

        // ── Base stats — mar walba ku jira ──
        $stats = [
            'total_users'        => $connectNow->query("SELECT COUNT(*) FROM users")->fetch_row()[0],
            'total_mothers'      => $connectNow->query("SELECT COUNT(*) FROM users WHERE role='mother'")->fetch_row()[0],
            'total_doctors'      => $connectNow->query("SELECT COUNT(*) FROM users WHERE role='doctor'")->fetch_row()[0],
            'total_admins'       => $connectNow->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetch_row()[0],
            'total_hospitals'    => $connectNow->query("SELECT COUNT(*) FROM hospitals")->fetch_row()[0],
            'total_appointments' => $connectNow->query("SELECT COUNT(*) FROM appointments")->fetch_row()[0],
            'today_appointments' => $connectNow->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()")->fetch_row()[0],
            'high_risk'          => $connectNow->query("SELECT COUNT(*) FROM health_records WHERE risk_level='high'")->fetch_row()[0],
            'pending_labs'       => $connectNow->query("SELECT COUNT(*) FROM lab_tests WHERE status='ordered'")->fetch_row()[0],
            'active_meds'        => $connectNow->query("SELECT COUNT(*) FROM medications WHERE is_active=1")->fetch_row()[0],
        ];

        $context  = "=== XOGTA NIDAAMKA (STATS) ===\n";
        $context .= "Users Guud: {$stats['total_users']}\n";
        $context .= "Hooyooyinka: {$stats['total_mothers']}\n";
        $context .= "Dhakhtarrada: {$stats['total_doctors']}\n";
        $context .= "Admins: {$stats['total_admins']}\n";
        $context .= "Isbitaalada: {$stats['total_hospitals']}\n";
        $context .= "Ballamaha Guud: {$stats['total_appointments']}\n";
        $context .= "Ballamaha Maanta: {$stats['today_appointments']}\n";
        $context .= "Khatarta Sare: {$stats['high_risk']}\n";
        $context .= "Lab Tests Sugaya: {$stats['pending_labs']}\n";
        $context .= "Dawooyinka Firfircoon: {$stats['active_meds']}\n";

        // ── USERS: register, cusub, dambeeyay, horreeyay ──
        if (preg_match('/user|register|rester|diiwaan|cusub|dambee|horaysa|horree|horeysay|last|first|new|admin/iu', $scanText)) {
            $res = $connectNow->query("
                SELECT full_name, role, phone, created_at
                FROM users ORDER BY created_at DESC LIMIT 10
            ");
            $context .= "\n=== USERS-KA (kan ugu horreeya liiska = kan ugu DAMBEEYAY ee isdiiwaangeliyay) ===\n";
            while ($r = $res->fetch_assoc()) {
                $context .= "- {$r['full_name']} | {$r['role']} | {$r['phone']} | Diiwaan: {$r['created_at']}\n";
            }
            $first = $connectNow->query("
                SELECT full_name, role, created_at FROM users ORDER BY created_at ASC LIMIT 1
            ")->fetch_assoc();
            if ($first) {
                $context .= "USER-KII UGU HORREEYAY: {$first['full_name']} ({$first['role']}) - {$first['created_at']}\n";
            }
        }

        // ── HOOYOOYIN ──
        if (preg_match('/hooyo|mother|uur|lodiwan|pregnan/iu', $scanText)) {
            $res = $connectNow->query("
                SELECT u.full_name, m.blood_group, m.expected_delivery, m.created_at,
                       FLOOR(DATEDIFF(NOW(), m.last_period_date)/7) AS week
                FROM mothers m JOIN users u ON u.id = m.user_id
                ORDER BY m.created_at ASC LIMIT 15
            ");
            $context .= "\n=== HOOYOOYINKA (kan ugu horreeya liiska = tii ugu HORREYSAY ee la diiwaangeliyay) ===\n";
            while ($r = $res->fetch_assoc()) {
                $context .= "- {$r['full_name']} | Week {$r['week']} | Blood: {$r['blood_group']} | Due: {$r['expected_delivery']} | Diiwaan: {$r['created_at']}\n";
            }
        }

        // ── DHAKHTARRO ──
        if (preg_match('/dhakhtar|dhakhaatiir|doctor|takhasus|special/iu', $scanText)) {
            $res = $connectNow->query("
                SELECT u.full_name, d.specialization, u.phone, h.name AS hospital, d.created_at
                FROM doctors d JOIN users u ON u.id = d.user_id
                LEFT JOIN hospitals h ON h.id = d.hospital_id
                ORDER BY d.created_at ASC LIMIT 15
            ");
            $context .= "\n=== DHAKHTARRADA ===\n";
            while ($r = $res->fetch_assoc()) {
                $context .= "- Dr. {$r['full_name']} | {$r['specialization']} | {$r['hospital']} | Diiwaan: {$r['created_at']}\n";
            }
        }

        // ── ISBITAALLO ──
        if (preg_match('/isbitaal|hospital|cisbitaal/iu', $scanText)) {
            $res = $connectNow->query("SELECT name, address, phone FROM hospitals LIMIT 15");
            $context .= "\n=== ISBITAALLADA ===\n";
            while ($r = $res->fetch_assoc()) {
                $context .= "- {$r['name']} | {$r['address']} | {$r['phone']}\n";
            }
        }

        // ── BALLAMO ──
        if (preg_match('/ballan|ballam|appointment|maanta|today/iu', $scanText)) {
            $res = $connectNow->query("
                SELECT a.appointment_date, a.appointment_time, a.appointment_type, a.status,
                       u.full_name AS patient, d.full_name AS doctor
                FROM appointments a
                JOIN users u ON u.id = a.user_id
                LEFT JOIN doctors d ON d.id = a.doctor_id
                ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 15
            ");
            $context .= "\n=== BALLAMAHA UGU DAMBEEYAY ===\n";
            while ($r = $res->fetch_assoc()) {
                $context .= "- {$r['appointment_date']} {$r['appointment_time']} | {$r['patient']} | Dr.{$r['doctor']} | {$r['appointment_type']} | {$r['status']}\n";
            }
        }

        // ── KHATAR / RISK ──
        if (preg_match('/khatar|risk|danger|halis/iu', $scanText)) {
            $res = $connectNow->query("
                SELECT u.full_name, hr.risk_level, hr.record_date,
                       hr.blood_pressure_systolic, hr.blood_pressure_diastolic
                FROM health_records hr JOIN users u ON u.id = hr.user_id
                WHERE hr.risk_level = 'high'
                ORDER BY hr.record_date DESC LIMIT 10
            ");
            $context .= "\n=== BUKAANNADA KHATARTA SARE ===\n";
            $found = false;
            while ($r = $res->fetch_assoc()) {
                $found = true;
                $context .= "- {$r['full_name']} | {$r['record_date']} | BP: {$r['blood_pressure_systolic']}/{$r['blood_pressure_diastolic']}\n";
            }
            if (!$found) $context .= "Ma jiraan bukaanno khatar sare ah hadda.\n";
        }

        // ── LAB TESTS ──
        if (preg_match('/lab|baaritaan|test/iu', $scanText)) {
            $res = $connectNow->query("
                SELECT lt.test_name, lt.test_date, lt.status, u.full_name AS patient
                FROM lab_tests lt JOIN users u ON u.id = lt.user_id
                ORDER BY lt.test_date DESC LIMIT 10
            ");
            $context .= "\n=== BAARITAANNADA LAB EE UGU DAMBEEYAY ===\n";
            while ($r = $res->fetch_assoc()) {
                $context .= "- {$r['test_name']} | {$r['patient']} | {$r['test_date']} | {$r['status']}\n";
            }
        }

        // ── DAWOOYIN ──
        if (preg_match('/dawo|daawo|medic|medicine/iu', $scanText)) {
            $res = $connectNow->query("
                SELECT m.medicine_name, m.dosage, m.frequency, u.full_name AS patient
                FROM medications m JOIN users u ON u.id = m.user_id
                WHERE m.is_active = 1 LIMIT 10
            ");
            $context .= "\n=== DAWOOYINKA FIRFIRCOON ===\n";
            while ($r = $res->fetch_assoc()) {
                $context .= "- {$r['medicine_name']} | {$r['dosage']} | {$r['frequency']} | Bukaan: {$r['patient']}\n";
            }
        }
    }

} catch (Exception $e) {
    $context = "Xogta database-ka lama heli karin: " . $e->getMessage();
}

// ═════════════════════════════════════════════════════════════════════════
// SYSTEM PROMPT
// ═════════════════════════════════════════════════════════════════════════
$roleLabels = [
    'mother' => 'hooyo uuran (pregnant woman)',
    'doctor' => 'dhakhtar (doctor)',
    'admin'  => 'maamulaha nidaamka (system administrator)',
];

$systemPrompt = "Adiga waxaad tahay mHealth Assistant, AI chatbot ku dhex jira app-ka daryeelka hooyooyinka.
Waxaad la hadlaysaa {$roleLabels[$role]}.

XEERARKA:
- Su'aalaha kaliya ka jawaab xogta user-ka ee database-ka laga soo qaatay.
- Ahaaw mid daawo-bixiye ah, naxariis leh, xirfadle ah.
- Haddii xaaladdu khatar tahay, si deg-deg ah u tali in gargaar la doondo.
- Jawaabaha gaabis ah oo cad samee.
- Haddii xog la'aanta, si daacad ah u sheeg.
- Ku jawaab luqadda user-ku ku qorayo (Soomaali ama Ingiriis).
- Abid ha shaacin qaab-dhismeedka database-ka ama faahfaahinta nidaamka.

XOGTA USER-KA EE DATABASE-KA:
{$context}

Taariikhda maanta: " . date('Y-m-d') . "
Waqtiga: " . date('H:i') . "
";

// ═════════════════════════════════════════════════════════════════════════
// BUILD GEMINI MESSAGES
// ═════════════════════════════════════════════════════════════════════════
$contents = [];

// Add system prompt as first user message
$contents[] = [
    'role' => 'user',
    'parts' => [['text' => $systemPrompt]]
];
$contents[] = [
    'role' => 'model',
    'parts' => [['text' => 'Waan fahmay. Diyaar ayaan u ahay in aan ka caawiyaa.']]
];

// Add conversation history (last 10)
$recentHistory = array_slice($history, -10);
foreach ($recentHistory as $h) {
    if (isset($h['role']) && isset($h['content'])) {
        $geminiRole = $h['role'] === 'user' ? 'user' : 'model';
        $contents[] = [
            'role'  => $geminiRole,
            'parts' => [['text' => $h['content']]],
        ];
    }
}

// Add current message
$contents[] = [
    'role'  => 'user',
    'parts' => [['text' => $message]],
];

// ═════════════════════════════════════════════════════════════════════════
// CALL GEMINI API
// ═════════════════════════════════════════════════════════════════════════
$payload = [
    'contents'         => $contents,
    'generationConfig' => [
        'maxOutputTokens' => 1024,
        'temperature'     => 0.7,
    ],
];

$ch = curl_init(GEMINI_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response) {
    echo json_encode(['success' => false, 'message' => 'AI service-ka lagama heli karin']);
    exit();
}

$data = json_decode($response, true);

if ($httpCode !== 200) {
    $errMsg = $data['error']['message'] ?? 'AI service error';
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit();
}

$reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Jawaab ma helin';

echo json_encode([
    'success' => true,
    'reply'   => $reply,
    'role'    => 'assistant',
]);