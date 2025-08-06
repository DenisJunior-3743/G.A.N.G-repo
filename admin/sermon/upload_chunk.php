<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/config/database.php';

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$member_id = $_SESSION['member_id'];
$title = $_POST['title'] ?? '';
$speaker = $_POST['speaker'] ?? 'Unknown';
$folder_name = $_POST['folder_name'] ?? '';
$chunk_index = (int)($_POST['chunk_index'] ?? 0);
$is_final = $_POST['is_final'] ?? '0';

if (!$title || !$folder_name || !isset($_FILES['audio_chunk'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$audio_file = $_FILES['audio_chunk'];

// Validate file type
$allowed_types = ['audio/webm', 'audio/wav', 'audio/mp3', 'audio/mpeg'];
$file_type = $audio_file['type'];

if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid audio file type']);
    exit;
}

// Create directory structure
$base_dir = dirname(__DIR__) . '/sermon/audio/';
$sermon_dir = $base_dir . $folder_name . '/';

if (!is_dir($base_dir)) {
    mkdir($base_dir, 0777, true);
}

if (!is_dir($sermon_dir)) {
    mkdir($sermon_dir, 0777, true);
}

// Generate chunk filename with proper numbering
$chunk_filename = sprintf('chunk_%03d.webm', $chunk_index);
$target_path = $sermon_dir . $chunk_filename;

try {
    // Move uploaded file
    if (!move_uploaded_file($audio_file['tmp_name'], $target_path)) {
        throw new Exception('Failed to save audio chunk');
    }

    $file_size = filesize($target_path);

    // Get or create sermon record
    $sermon_id = null;
    
    // Check if sermon exists
    $check_stmt = $pdo->prepare("SELECT id FROM sermons WHERE folder_name = ? AND member_id = ?");
    $check_stmt->execute([$folder_name, $member_id]);
    $existing_sermon = $check_stmt->fetch();
    
    if ($existing_sermon) {
        $sermon_id = $existing_sermon['id'];
    } else {
        // Create new sermon record (initial entry)
        $stmt = $pdo->prepare("
            INSERT INTO sermons (
                member_id, title, speaker, folder_name, total_chunks, 
                total_duration_seconds, total_size_bytes, created_at
            ) VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
        ");
        $stmt->execute([$member_id, $title, $speaker, $folder_name]);
        $sermon_id = $pdo->lastInsertId();
    }

    // Check if this chunk already exists in database
    $chunk_check_stmt = $pdo->prepare("SELECT id FROM sermon_chunks WHERE sermon_id = ? AND chunk_index = ?");
    $chunk_check_stmt->execute([$sermon_id, $chunk_index]);
    $existing_chunk = $chunk_check_stmt->fetch();

    if ($existing_chunk) {
        // Update existing chunk
        $chunk_stmt = $pdo->prepare("
            UPDATE sermon_chunks 
            SET filename = ?, file_size = ?, created_at = NOW() 
            WHERE id = ?
        ");
        $chunk_stmt->execute([$chunk_filename, $file_size, $existing_chunk['id']]);
    } else {
        // Insert new chunk record
        $chunk_stmt = $pdo->prepare("
            INSERT INTO sermon_chunks (sermon_id, chunk_index, filename, file_size) 
            VALUES (?, ?, ?, ?)
        ");
        $chunk_stmt->execute([$sermon_id, $chunk_index, $chunk_filename, $file_size]);
    }

    // Log chunk information
    $log_file = $sermon_dir . 'chunks.log';
    $log_entry = [
        'chunk_index' => $chunk_index,
        'filename' => $chunk_filename,
        'size' => $file_size,
        'timestamp' => date('Y-m-d H:i:s'),
        'is_final' => $is_final === '1',
        'sermon_id' => $sermon_id
    ];
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);

    // Create/update metadata file
    $metadata_file = $sermon_dir . 'metadata.json';
    $metadata = [
        'title' => $title,
        'speaker' => $speaker,
        'folder_name' => $folder_name,
        'member_id' => $member_id,
        'sermon_id' => $sermon_id,
        'started_at' => date('Y-m-d H:i:s'),
        'last_chunk_at' => date('Y-m-d H:i:s'),
        'current_chunk_index' => $chunk_index,
        'is_final_chunk' => $is_final === '1'
    ];
    
    // If metadata file exists, preserve started_at timestamp
    if (file_exists($metadata_file)) {
        $existing_metadata = json_decode(file_get_contents($metadata_file), true);
        if (isset($existing_metadata['started_at'])) {
            $metadata['started_at'] = $existing_metadata['started_at'];
        }
    }
    
    file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true, 
        'message' => 'Chunk uploaded successfully',
        'chunk_index' => $chunk_index,
        'filename' => $chunk_filename,
        'path' => $sermon_dir,
        'sermon_id' => $sermon_id,
        'file_size' => $file_size
    ]);

} catch (PDOException $e) {
    // Clean up file if database operation failed
    if (file_exists($target_path)) {
        unlink($target_path);
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Clean up file if upload failed
    if (file_exists($target_path)) {
        unlink($target_path);
    }
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $e->getMessage()]);
}