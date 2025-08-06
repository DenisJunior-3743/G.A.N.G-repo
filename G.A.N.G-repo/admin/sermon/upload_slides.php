<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$member_id = $_SESSION['member_id'];
$folder_name = $_POST['folder_name'] ?? '';

if (!$folder_name || !isset($_FILES['slides'])) {
    echo json_encode(['success' => false, 'message' => 'Missing folder name or slide files']);
    exit;
}

// Create directories
$sermon_dir = dirname(__DIR__) . '/sermon/audio/' . $folder_name . '/';
$slides_dir = $sermon_dir . 'slides/';

if (!is_dir($sermon_dir)) {
    if (!mkdir($sermon_dir, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create sermon directory']);
        exit;
    }
}

if (!is_dir($slides_dir)) {
    if (!mkdir($slides_dir, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create slides directory']);
        exit;
    }
}

$allowed_types = [
    'application/pdf',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'image/jpeg',
    'image/jpg', 
    'image/png',
    'image/gif',
    'image/webp'
];

$uploaded_files = [];
$errors = [];
$total_size = 0;

try {
    // Start transaction for consistency
    $pdo->beginTransaction();

    // Get or create sermon ID from database
    $sermon_stmt = $pdo->prepare("SELECT id, title, speaker FROM sermons WHERE folder_name = ? AND member_id = ?");
    $sermon_stmt->execute([$folder_name, $member_id]);
    $sermon = $sermon_stmt->fetch();
    
    $sermon_id = null;
    $title = 'Untitled Sermon';
    $speaker = 'Unknown';
    
    if (!$sermon) {
        // Check metadata file for sermon details
        $metadata_file = $sermon_dir . 'metadata.json';
        
        if (file_exists($metadata_file)) {
            $metadata = json_decode(file_get_contents($metadata_file), true);
            $title = $metadata['title'] ?? 'Untitled Sermon';
            $speaker = $metadata['speaker'] ?? 'Unknown'; 
        }
        
        // Create new sermon record
        $create_stmt = $pdo->prepare("
            INSERT INTO sermons (
                member_id, title, speaker, folder_name, total_chunks, 
                total_duration_seconds, total_size_bytes, created_at
            ) VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
        ");
        $create_stmt->execute([$member_id, $title, $speaker, $folder_name]);
        $sermon_id = $pdo->lastInsertId();
    } else {
        $sermon_id = $sermon['id'];
        $title = $sermon['title'];
        $speaker = $sermon['speaker'];
    }

    $files = $_FILES['slides'];
    
    // Handle multiple files
    if (is_array($files['name'])) {
        $file_count = count($files['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $result = processSlideFile([
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size' => $files['size'][$i]
                ], $slides_dir, $sermon_id, $allowed_types, $pdo);
                
                if ($result['success']) {
                    $uploaded_files[] = $result['file_info'];
                    $total_size += $result['file_info']['size'];
                } else {
                    $errors[] = $result['error'];
                }
            } else {
                $errors[] = "Upload error for {$files['name'][$i]}: " . getUploadErrorMessage($files['error'][$i]);
            }
        }
    } else {
        // Single file
        if ($files['error'] === UPLOAD_ERR_OK) {
            $result = processSlideFile([
                'name' => $files['name'],
                'type' => $files['type'],
                'tmp_name' => $files['tmp_name'],
                'size' => $files['size']
            ], $slides_dir, $sermon_id, $allowed_types, $pdo);
            
            if ($result['success']) {
                $uploaded_files[] = $result['file_info'];
                $total_size += $result['file_info']['size'];
            } else {
                $errors[] = $result['error'];
            }
        } else {
            $errors[] = "Upload error for {$files['name']}: " . getUploadErrorMessage($files['error']);
        }
    }

    // Update slides log and metadata
    if (!empty($uploaded_files)) {
        updateSlidesLog($slides_dir, $member_id, $sermon_id, $uploaded_files);
        updateSermonMetadata($sermon_dir, $title, $speaker, $folder_name, $member_id, $sermon_id);
    }

    // Commit transaction
    $pdo->commit();

    $success_count = count($uploaded_files);
    $error_count = count($errors);

    if ($success_count > 0) {
        $message = "{$success_count} slide(s) uploaded successfully";
        if ($error_count > 0) {
            $message .= " ({$error_count} failed)";
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'data' => [
                'sermon_id' => $sermon_id,
                'uploaded_count' => $success_count,
                'error_count' => $error_count,
                'total_size' => $total_size,
                'total_size_formatted' => round($total_size / (1024 * 1024), 2) . ' MB',
                'uploaded_files' => $uploaded_files,
                'errors' => $errors
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No files were uploaded successfully',
            'data' => [
                'errors' => $errors,
                'error_count' => $error_count
            ]
        ]);
    }

} catch (PDOException $e) {
    // Rollback transaction on database error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    // Clean up any uploaded files on database error
    foreach ($uploaded_files as $file) {
        $file_path = $slides_dir . $file['saved_name'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    error_log("Slides upload database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred while uploading slides']);
} catch (Exception $e) {
    // Rollback transaction on general error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Slides upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
}

/**
 * Process individual slide file
 */
function processSlideFile($file, $slides_dir, $sermon_id, $allowed_types, $pdo) {
    $file_name = $file['name'];
    $file_type = $file['type'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    
    // Validate file type
    if (!in_array($file_type, $allowed_types)) {
        return ['success' => false, 'error' => "Invalid file type for {$file_name}"];
    }
    
    // Validate file size (max 50MB)
    if ($file_size > 50 * 1024 * 1024) {
        return ['success' => false, 'error' => "File {$file_name} is too large (max 50MB)"];
    }
    
    // Additional validation for image files
    if (strpos($file_type, 'image/') === 0) {
        $image_info = getimagesize($file_tmp);
        if ($image_info === false) {
            return ['success' => false, 'error' => "Invalid image file: {$file_name}"];
        }
    }
    
    // Sanitize filename
    $safe_filename = sanitizeFilename($file_name);
    $target_path = $slides_dir . $safe_filename;
    
    // Handle duplicate filenames
    $final_path = handleDuplicateFilename($target_path);
    $saved_filename = basename($final_path);
    
    // Move uploaded file
    if (!move_uploaded_file($file_tmp, $final_path)) {
        return ['success' => false, 'error' => "Failed to save {$file_name}"];
    }
    
    try {
        // Check if slide already exists in database
        $slide_check_stmt = $pdo->prepare("SELECT id FROM sermon_slides WHERE sermon_id = ? AND saved_filename = ?");
        $slide_check_stmt->execute([$sermon_id, $saved_filename]);
        $existing_slide = $slide_check_stmt->fetch();
        
        if ($existing_slide) {
            // Update existing slide record
            $slide_stmt = $pdo->prepare("
                UPDATE sermon_slides 
                SET original_filename = ?, file_size = ?, file_type = ?, uploaded_at = NOW() 
                WHERE id = ?
            ");
            $slide_stmt->execute([$file_name, $file_size, $file_type, $existing_slide['id']]);
        } else {
            // Insert new slide record
            $slide_stmt = $pdo->prepare("
                INSERT INTO sermon_slides (sermon_id, original_filename, saved_filename, file_size, file_type) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $slide_stmt->execute([$sermon_id, $file_name, $saved_filename, $file_size, $file_type]);
        }
        
        return [
            'success' => true,
            'file_info' => [
                'original_name' => $file_name,
                'saved_name' => $saved_filename,
                'size' => $file_size,
                'type' => $file_type,
                'path' => $final_path
            ]
        ];
        
    } catch (Exception $e) {
        // Clean up file if database operation failed
        if (file_exists($final_path)) {
            unlink($final_path);
        }
        throw $e;
    }
}

/**
 * Sanitize filename for safe storage
 */
function sanitizeFilename($filename) {
    // Get file extension
    $path_info = pathinfo($filename);
    $extension = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';
    $name = $path_info['filename'];
    
    // Remove or replace unsafe characters
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    $safe_name = preg_replace('/_+/', '_', $safe_name); // Remove multiple underscores
    $safe_name = trim($safe_name, '_'); // Remove leading/trailing underscores
    
    // Ensure name is not empty
    if (empty($safe_name)) {
        $safe_name = 'slide_' . time();
    }
    
    // Limit length
    $safe_name = substr($safe_name, 0, 50);
    
    return $safe_name . $extension;
}

/**
 * Handle duplicate filenames by appending numbers
 */
function handleDuplicateFilename($target_path) {
    if (!file_exists($target_path)) {
        return $target_path;
    }
    
    $path_info = pathinfo($target_path);
    $directory = $path_info['dirname'];
    $filename = $path_info['filename'];
    $extension = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';
    
    $counter = 1;
    do {
        $new_path = $directory . '/' . $filename . '_' . $counter . $extension;
        $counter++;
    } while (file_exists($new_path));
    
    return $new_path;
}

/**
 * Update slides log file
 */
function updateSlidesLog($slides_dir, $member_id, $sermon_id, $uploaded_files) {
    $slides_log_file = $slides_dir . 'slides.log';
    $log_entry = [
        'uploaded_at' => date('Y-m-d H:i:s'),
        'member_id' => $member_id,
        'sermon_id' => $sermon_id,
        'batch_id' => uniqid(),
        'files' => $uploaded_files,
        'total_files' => count($uploaded_files),
        'total_size' => array_sum(array_column($uploaded_files, 'size'))
    ];
    
    file_put_contents($slides_log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Update sermon metadata file
 */
function updateSermonMetadata($sermon_dir, $title, $speaker, $folder_name, $member_id, $sermon_id) {
    $metadata_file = $sermon_dir . 'metadata.json';
    
    // Get existing metadata if it exists
    $metadata = [];
    if (file_exists($metadata_file)) {
        $existing_data = file_get_contents($metadata_file);
        $metadata = json_decode($existing_data, true) ?: [];
    }
    
    // Update with current information
    $metadata = array_merge($metadata, [
        'title' => $title,
        'speaker' => $speaker,
        'folder_name' => $folder_name,
        'member_id' => $member_id,
        'sermon_id' => $sermon_id,
        'slides_updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // Preserve important timestamps
    if (!isset($metadata['started_at'])) {
        $metadata['started_at'] = date('Y-m-d H:i:s');
    }
    
    file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));
}

/**
 * Get user-friendly upload error message
 */
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File is too large';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}