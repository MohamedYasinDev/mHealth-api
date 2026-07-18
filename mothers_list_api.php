<?php
// C:\xampp\htdocs\mHealth_api\mothers_list_api.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 1);

include "conn.php";

if (!$connectNow) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed'
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // OPTION 1: Get ALL mothers from users table (regardless of mothers table)
    // This will return ALL users with role = 'mother'
    $sql = "SELECT u.id, u.full_name, u.email, u.phone, u.role, u.created_at
            FROM users u
            WHERE u.role = 'mother'
            ORDER BY u.full_name ASC";
    
    $result = $connectNow->query($sql);
    
    if (!$result) {
        echo json_encode([
            'success' => false, 
            'message' => 'Query failed: ' . $connectNow->error
        ]);
        exit();
    }
    
    $mothers = [];
    while ($row = $result->fetch_assoc()) {
        $mothers[] = [
            'id' => (int)$row['id'],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'role' => $row['role']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'mothers' => $mothers,
        'count' => count($mothers),
        'message' => 'All mothers from users table'
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>