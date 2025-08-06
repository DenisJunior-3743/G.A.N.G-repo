<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';

// Check if user is logged in
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error occurred']);
    exit;
}

$file = $_FILES['profile_picture'];
$member_id = $_SESSION['member_id'];

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$file_type = mime_content_type($file['tmp_name']);

if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF files are allowed.']);
    exit;
}

// Validate file size (5MB max)
$max_size = 5 * 1024 * 1024; // 5MB in bytes
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size too large. Maximum size is 5MB.']);
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = dirname(__DIR__) . '/registration/profile_pics/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$unique_filename = 'profile_' . uniqid() . '_' . time() . '.' . $file_extension;
$upload_path = $upload_dir . $unique_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
    exit;
}

try {
    // Update database with new picture path
    $stmt = $pdo->prepare("UPDATE members SET picture = ? WHERE id = ?");
    $stmt->execute([$unique_filename, $member_id]);
    
    // Update session with new picture
    $_SESSION['picture'] = $unique_filename;
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Profile picture updated successfully!',
        'picture_url' => '/G.A.N.G/registration/profile_pics/' . $unique_filename
    ]);
    
} catch (PDOException $e) {
    // Delete uploaded file if database update fails
    if (file_exists($upload_path)) {
        unlink($upload_path);
    }
    
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 