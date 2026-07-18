<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include "conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

if (!is_array($data)) {
    echo json_encode(["success" => false, "message" => "Invalid request body"]);
    exit();
}

// ── 1. Validation ────────────────────────────────────────────
$userId = isset($data['user_id']) ? intval($data['user_id']) : 0;

if ($userId <= 0) {
    echo json_encode(["success" => false, "message" => "Valid user_id is required"]);
    exit();
}

// Mothers table fields
$dateOfBirth           = isset($data['date_of_birth'])           ? trim($data['date_of_birth'])           : null;
$bloodGroup            = isset($data['blood_group'])             ? trim($data['blood_group'])             : null;
$lastPeriodDate        = isset($data['last_period_date'])        ? trim($data['last_period_date'])        : null;
$expectedDelivery      = isset($data['expected_delivery'])       ? trim($data['expected_delivery'])       : null;
$emergencyContactName  = isset($data['emergency_contact_name'])  ? trim($data['emergency_contact_name'])  : null;
$emergencyContactPhone = isset($data['emergency_contact_phone']) ? trim($data['emergency_contact_phone']) : null;

// Pregnancies table fields
$pregnancyNumber = isset($data['pregnancy_number']) ? intval($data['pregnancy_number']) : 1;
$babyCount       = isset($data['baby_count'])       ? intval($data['baby_count'])       : 1;
$pregnancyStatus = isset($data['pregnancy_status']) ? trim($data['pregnancy_status'])   : 'ongoing';

// Haddii last_period_date la siiyay oo expected_delivery aanay jirin,
// xisaabi si toos ah: LMP + 280 maalmood
if ($lastPeriodDate && !$expectedDelivery) {
    try {
        $lmp = new DateTime($lastPeriodDate);
        $lmp->add(new DateInterval('P280D'));
        $expectedDelivery = $lmp->format('Y-m-d');
    } catch (Exception $e) {
        // iska daa — waa optional
    }
}

// ── 2. Hubi in user-ku jiro oo uu hooyo yahay ───────────────
$checkUser = $connectNow->prepare(
    "SELECT id FROM users WHERE id = ? AND role = 'mother'"
);
$checkUser->bind_param("i", $userId);
$checkUser->execute();
if ($checkUser->get_result()->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Mother user not found"]);
    exit();
}

// ── 3. Hubi in mothers row ay jirto ─────────────────────────
$checkMother = $connectNow->prepare(
    "SELECT id FROM mothers WHERE user_id = ?"
);
$checkMother->bind_param("i", $userId);
$checkMother->execute();
$motherRes = $checkMother->get_result();

if ($motherRes->num_rows > 0) {
    // UPDATE — row horey u jirtay
    $motherRow = $motherRes->fetch_assoc();
    $motherId  = $motherRow['id'];

    $sql = "UPDATE mothers SET
                date_of_birth           = ?,
                blood_group             = ?,
                last_period_date        = ?,
                expected_delivery       = ?,
                emergency_contact_name  = ?,
                emergency_contact_phone = ?
            WHERE user_id = ?";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param(
        "ssssssi",
        $dateOfBirth,
        $bloodGroup,
        $lastPeriodDate,
        $expectedDelivery,
        $emergencyContactName,
        $emergencyContactPhone,
        $userId
    );
} else {
    // INSERT — row ma jirin
    $sql = "INSERT INTO mothers
                (user_id, date_of_birth, blood_group, last_period_date,
                 expected_delivery, emergency_contact_name, emergency_contact_phone)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $connectNow->prepare($sql);
    $stmt->bind_param(
        "issssss",
        $userId,
        $dateOfBirth,
        $bloodGroup,
        $lastPeriodDate,
        $expectedDelivery,
        $emergencyContactName,
        $emergencyContactPhone
    );
}

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to save mother profile: " . $stmt->error
    ]);
    exit();
}

// ── 4. Pregnancies — isticmaal $userId toos ah ───────────────
// MUHIIM: pregnancies.mother_id = users.id (ma aha mothers.id)
// Si my_pregnancies_screen.dart uu xogta si sax ah u helo
if ($expectedDelivery) {
    $checkPreg = $connectNow->prepare(
        "SELECT id FROM pregnancies WHERE mother_id = ? AND pregnancy_status = 'ongoing'"
    );
    // ← $userId toos ah (users.id) — ma ahan $motherId (mothers.id)
    $checkPreg->bind_param("i", $userId);
    $checkPreg->execute();
    $pregRes = $checkPreg->get_result();

    if ($pregRes->num_rows > 0) {
        // UPDATE pregnancy jirta
        $pregRow = $pregRes->fetch_assoc();
        $pregId  = $pregRow['id'];

        $sqlP = "UPDATE pregnancies SET
                     pregnancy_number       = ?,
                     baby_count             = ?,
                     expected_delivery_date = ?,
                     pregnancy_status       = ?
                 WHERE id = ?";
        $stmtP = $connectNow->prepare($sqlP);
        $stmtP->bind_param(
            "iissi",
            $pregnancyNumber,
            $babyCount,
            $expectedDelivery,
            $pregnancyStatus,
            $pregId
        );
    } else {
        // INSERT pregnancy cusub — mother_id = $userId (users.id)
        $sqlP = "INSERT INTO pregnancies
                     (mother_id, pregnancy_number, baby_count,
                      expected_delivery_date, pregnancy_status)
                 VALUES (?, ?, ?, ?, ?)";
        $stmtP = $connectNow->prepare($sqlP);
        // ← $userId halkii $motherId
        $stmtP->bind_param(
            "iiiss",
            $userId,
            $pregnancyNumber,
            $babyCount,
            $expectedDelivery,
            $pregnancyStatus
        );
    }

    if (!$stmtP->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Profile saved but pregnancy record failed: " . $stmtP->error,
            "profile_complete" => true
        ]);
        exit();
    }
}

// ── 5. Soo celi natiijooyinka ────────────────────────────────
$isComplete = ($dateOfBirth && $bloodGroup && $lastPeriodDate && $expectedDelivery) ? 1 : 0;

echo json_encode([
    "success"           => true,
    "message"           => "Profile completed successfully",
    "profile_complete"  => (bool)$isComplete,
    "expected_delivery" => $expectedDelivery
]);

$connectNow->close();
?>