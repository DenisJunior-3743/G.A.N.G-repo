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
$folder_name = $_POST['folder_name'] ?? '';
$session_type = $_POST['session_type'] ?? 'complete'; // 'complete', 'audio_only', 'slides_only'

if (!$folder_name) {
    echo json_encode(['success' => false, 'message' => 'Folder name required']);
    exit;
}

$sermon_dir = dirname(__DIR__) . '/sermon/audio/' . $folder_name . '/';

if (!is_dir($sermon_dir)) {
    echo json_encode(['success' => false, 'message' => 'Session folder not found']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get sermon record
    $sermon_stmt = $pdo->prepare("SELECT id, title, speaker FROM sermons WHERE folder_name = ? AND member_id = ?");
    $sermon_stmt->execute([$folder_name, $member_id]);
    $sermon = $sermon_stmt->fetch();
    
    if (!$sermon) {
        echo json_encode(['success' => false, 'message' => 'Session record not found']);
        exit;
    }
    
    $sermon_id = $sermon['id'];
    $results = [];
    
    // Process based on session type
    switch ($session_type) {
        case 'complete':
            $results['audio'] = processAudioFiles($sermon_id, $sermon_dir, $pdo);
            $results['slides'] = processSlideFiles($sermon_id, $sermon_dir, $pdo);
            break;
            
        case 'audio_only':
            $results['audio'] = processAudioFiles($sermon_id, $sermon_dir, $pdo);
            $results['slides'] = ['processed' => 0, 'total_size' => 0];
            break;
            
        case 'slides_only':
            $results['audio'] = ['processed' => 0, 'total_duration' => 0, 'total_size' => 0];
            $results['slides'] = processSlideFiles($sermon_id, $sermon_dir, $pdo);
            break;
            
        default:
            throw new Exception('Invalid session type');
    }
    
    // Update sermon record with final stats
    $total_size = $results['audio']['total_size'] + $results['slides']['total_size'];
    $total_duration = $results['audio']['total_duration'] ?? 0;
    $total_chunks = $results['audio']['processed'] ?? 0;
    
    $update_stmt = $pdo->prepare("
        UPDATE sermons 
        SET total_chunks = ?, total_duration_seconds = ?, total_size_bytes = ?, 
            completed_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([$total_chunks, $total_duration, $total_size, $sermon_id]);
    
    // Create comprehensive session summary
    $session_summary = createSessionSummary($sermon_id, $sermon_dir, $results, $pdo);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Session completed successfully',
        'session_type' => $session_type,
        'data' => $session_summary
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("Session completion database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("Session completion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Process audio files and chunks
 */
function processAudioFiles($sermon_id, $sermon_dir, $pdo) {
    $chunks_stmt = $pdo->prepare("
        SELECT chunk_index, filename, file_size 
        FROM sermon_chunks 
        WHERE sermon_id = ? 
        ORDER BY chunk_index ASC
    ");
    $chunks_stmt->execute([$sermon_id]);
    $chunk_records = $chunks_stmt->fetchAll();
    
    $total_size = 0;
    $total_duration = 0;
    $processed_chunks = 0;
    $chunk_files = [];
    
    foreach ($chunk_records as $chunk) {
        $file_path = $sermon_dir . $chunk['filename'];
        if (file_exists($file_path)) {
            $actual_size = filesize($file_path);
            $chunk_files[] = [
                'filename' => $chunk['filename'],
                'index' => $chunk['chunk_index'],
                'size' => $actual_size
            ];
            $total_size += $actual_size;
            $processed_chunks++;
        }
    }
    
    // Calculate duration
    if ($processed_chunks > 0) {
        if ($processed_chunks > 1) {
            $total_duration = ($processed_chunks - 1) * 25 * 60; // 25 minutes per full chunk
            
            // Estimate last chunk
            $avg_chunk_size = 0;
            for ($i = 0; $i < $processed_chunks - 1; $i++) {
                $avg_chunk_size += $chunk_files[$i]['size'];
            }
            if ($processed_chunks > 1) {
                $avg_chunk_size = $avg_chunk_size / ($processed_chunks - 1);
                $last_chunk = end($chunk_files);
                $last_chunk_ratio = $avg_chunk_size > 0 ? $last_chunk['size'] / $avg_chunk_size : 1;
                $total_duration += (25 * 60 * min($last_chunk_ratio, 1));
            }
        } else {
            // Single chunk estimation
            $size_mb = $total_size / (1024 * 1024);
            $total_duration = min($size_mb * 2 * 60, 25 * 60);
        }
    }
    
    return [
        'processed' => $processed_chunks,
        'total_size' => $total_size,
        'total_duration' => round($total_duration),
        'chunk_files' => $chunk_files
    ];
}

/**
 * Process slide files
 */
function processSlideFiles($sermon_id, $sermon_dir, $pdo) {
    $slides_stmt = $pdo->prepare("
        SELECT original_filename, saved_filename, file_size, file_type
        FROM sermon_slides 
        WHERE sermon_id = ?
        ORDER BY uploaded_at ASC
    ");
    $slides_stmt->execute([$sermon_id]);
    $slide_records = $slides_stmt->fetchAll();
    
    $total_size = 0;
    $processed_slides = 0;
    $slide_files = [];
    $slides_dir = $sermon_dir . 'slides/';
    
    foreach ($slide_records as $slide) {
        $file_path = $slides_dir . $slide['saved_filename'];
        if (file_exists($file_path)) {
            $actual_size = filesize($file_path);
            $slide_files[] = [
                'original_name' => $slide['original_filename'],
                'saved_name' => $slide['saved_filename'],
                'size' => $actual_size,
                'type' => $slide['file_type']
            ];
            $total_size += $actual_size;
            $processed_slides++;
        }
    }
    
    return [
        'processed' => $processed_slides,
        'total_size' => $total_size,
        'slide_files' => $slide_files
    ];
}

/**
 * Create comprehensive session summary
 */
function createSessionSummary($sermon_id, $sermon_dir, $results, $pdo) {
    // Get sermon details
    $sermon_stmt = $pdo->prepare("SELECT * FROM sermons WHERE id = ?");
    $sermon_stmt->execute([$sermon_id]);
    $sermon = $sermon_stmt->fetch();
    
    // Calculate totals
    $total_size = $results['audio']['total_size'] + $results['slides']['total_size'];
    $total_duration = $results['audio']['total_duration'] ?? 0;
    
    // Create metadata
    $metadata = [
        'session_completed_at' => date('Y-m-d H:i:s'),
        'sermon_id' => $sermon_id,
        'title' => $sermon['title'],
        'speaker' => $sermon['speaker'],
        'folder_name' => $sermon['folder_name'],
        'member_id' => $sermon['member_id'],
        'audio_stats' => [
            'chunks' => $results['audio']['processed'],
            'duration_seconds' => $total_duration,
            'size_bytes' => $results['audio']['total_size'],
            'files' => $results['audio']['chunk_files'] ?? []
        ],
        'slides_stats' => [
            'count' => $results['slides']['processed'],
            'size_bytes' => $results['slides']['total_size'],
            'files' => $results['slides']['slide_files'] ?? []
        ],
        'totals' => [
            'size_bytes' => $total_size,
            'size_formatted' => formatBytes($total_size),
            'duration_formatted' => gmdate('H:i:s', $total_duration)
        ]
    ];
    
    // Save metadata
    $metadata_file = $sermon_dir . 'session_complete.json';
    file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));
    
    return [
        'sermon_id' => $sermon_id,
        'title' => $sermon['title'],
        'speaker' => $sermon['speaker'],
        'folder_name' => $sermon['folder_name'],
        'audio' => [
            'chunks' => $results['audio']['processed'],
            'duration' => $total_duration,
            'duration_formatted' => gmdate('H:i:s', $total_duration),
            'size_bytes' => $results['audio']['total_size'],
            'size_formatted' => formatBytes($results['audio']['total_size'])
        ],
        'slides' => [
            'count' => $results['slides']['processed'],
            'size_bytes' => $results['slides']['total_size'],
            'size_formatted' => formatBytes($results['slides']['total_size'])
        ],
        'totals' => [
            'size_bytes' => $total_size,
            'size_formatted' => formatBytes($total_size),
            'items_count' => $results['audio']['processed'] + $results['slides']['processed']
        ],
        'paths' => [
            'sermon_folder' => $sermon_dir,
            'slides_folder' => $sermon_dir . 'slides/',
            'metadata_file' => $metadata_file
        ],
        'timestamps' => [
            'created_at' => $sermon['created_at'],
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => $sermon['updated_at']
        ]
    ];
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < 4; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
            