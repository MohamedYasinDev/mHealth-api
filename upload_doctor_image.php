<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

$target_dir = "uploads/doctors/";

// Create folder if not exists
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['profile_image'])) {
        $file = $_FILES['profile_image'];
        $file_name = time() . '_' . basename($file['name']);
        $target_file = $target_dir . $file_name;
        
        // Check file type
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                echo json_encode([
                    'success' => true,
                    'image_url' => $file_name,
                    'message' => 'Image uploaded successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to upload image'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Only JPG, JPEG, PNG, GIF files are allowed'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No image file provided'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>