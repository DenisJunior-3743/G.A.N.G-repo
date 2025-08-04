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
$total_chunks = (int)($_POST['total_chunks'] ?? 0);

if (!$title || !$folder_name) {
    echo json_encode(['success' => false, 'message' => 'Title and folder name required']);
    exit;
}

$sermon_dir = dirname(__DIR__) . '/sermon/audio/' . $folder_name . '/';

if (!is_dir($sermon_dir)) {
    echo json_encode(['success' => false, 'message' => 'Sermon folder not found']);
    exit;
}

try {
    // Start transaction for data consistency
    $pdo->beginTransaction();

    // Get sermon record
    $sermon_stmt = $pdo->prepare("SELECT id FROM sermons WHERE folder_name = ? AND member_id = ?");
    $sermon_stmt->execute([$folder_name, $member_id]);
    $sermon = $sermon_stmt->fetch();

    if (!$sermon) {
        echo json_encode(['success' => false, 'message' => 'Sermon record not found']);
        exit;
    }

    $sermon_id = $sermon['id'];

    // Get chunk information from database
    $chunks_stmt = $pdo->prepare("
        SELECT chunk_index, filename, file_size 
        FROM sermon_chunks 
        WHERE sermon_id = ? 
        ORDER BY chunk_index ASC
    ");
    $chunks_stmt->execute([$sermon_id]);
    $chunk_records = $chunks_stmt->fetchAll();

    // Calculate total duration and file size from actual chunks
    $total_duration = 0;
    $total_size = 0;
    $chunk_files = [];
    $processed_chunks = 0;
    
    foreach ($chunk_records as $chunk) {
        $file_path = $sermon_dir . $chunk['filename'];
        if (file_exists($file_path)) {
            $actual_size = filesize($file_path);
            $chunk_files[] = [
                'filename' => $chunk['filename'],
                'index' => $chunk['chunk_index'],
                'size' => $actual_size,
                'path' => $file_path
            ];
            $total_size += $actual_size;
            $processed_chunks++;
        }
    }
    
    // Sort chunks by index
    usort($chunk_files, function($a, $b) {
        return $a['index'] - $b['index'];
    });
    
    // Enhanced duration calculation
    $chunk_count = count($chunk_files);
    if ($chunk_count > 0) {
        if ($chunk_count > 1) {
            // Calculate based on actual file analysis if possible
            $total_duration = ($chunk_count - 1) * 25 * 60; // 25 minutes per full chunk
            
            // More accurate estimation for last chunk
            $avg_chunk_size = 0;
            for ($i = 0; $i < $chunk_count - 1; $i++) {
                $avg_chunk_size += $chunk_files[$i]['size'];
            }
            if ($chunk_count > 1) {
                $avg_chunk_size = $avg_chunk_size / ($chunk_count - 1);
                $last_chunk = end($chunk_files);
                $last_chunk_ratio = $avg_chunk_size > 0 ? $last_chunk['size'] / $avg_chunk_size : 1;
                $total_duration += (25 * 60 * min($last_chunk_ratio, 1));
            }
        } else {
            // Single chunk estimation (more accurate based on file size)
            $size_mb = $total_size / (1024 * 1024);
            // Assume ~0.5MB per minute for webm/opus at 128kbps
            $total_duration = min($size_mb * 2 * 60, 25 * 60);
        }
    }

    // Get slide information
    $slides_stmt = $pdo->prepare("
        SELECT COUNT(*) as slide_count, 
               COALESCE(SUM(file_size), 0) as total_slide_size
        FROM sermon_slides 
        WHERE sermon_id = ?
    ");
    $slides_stmt->execute([$sermon_id]);
    $slide_result = $slides_stmt->fetch();
    $slide_count = $slide_result['slide_count'] ?? 0;
    $total_slide_size = $slide_result['total_slide_size'] ?? 0;

    // Comprehensive metadata
    $metadata_file = $sermon_dir . 'metadata.json';
    $metadata = [
        'title' => $title,
        'speaker' => $speaker,
        'folder_name' => $folder_name,
        'member_id' => $member_id,
        'sermon_id' => $sermon_id,
        'started_at' => date('Y-m-d H:i:s'),
        'completed_at' => date('Y-m-d H:i:s'),
        'total_chunks' => $processed_chunks,
        'total_duration_seconds' => round($total_duration),
        'total_size_bytes' => $total_size,
        'slide_count' => $slide_count,
        'total_slide_size_bytes' => $total_slide_size,
        'chunk_files' => $chunk_files,
        'processing_stats' => [
            'chunks_processed' => $processed_chunks,
            'chunks_expected' => $total_chunks,
            'average_chunk_size_mb' => $processed_chunks > 0 ? round($total_size / $processed_chunks / (1024 * 1024), 2) : 0,
            'estimated_bitrate_kbps' => $total_duration > 0 ? round(($total_size * 8) / ($total_duration * 1000)) : 0
        ]
    ];
    
    // Preserve original start time if metadata exists
    if (file_exists($metadata_file)) {
        $existing_metadata = json_decode(file_get_contents($metadata_file), true);
        if (isset($existing_metadata['started_at'])) {
            $metadata['started_at'] = $existing_metadata['started_at'];
        }
    }
    
    file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));

    // Update sermon record with comprehensive information
    $update_stmt = $pdo->prepare("
        UPDATE sermons 
        SET title = ?, speaker = ?, total_chunks = ?, total_duration_seconds = ?, 
            total_size_bytes = ?, completed_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([
        $title, 
        $speaker, 
        $processed_chunks, 
        round($total_duration), 
        $total_size, 
        $sermon_id
    ]);

    // Create completion summary
    $completion_summary = [
        'sermon_id' => $sermon_id,
        'folder_name' => $folder_name,
        'total_chunks' => $processed_chunks,
        'total_duration' => round($total_duration),
        'total_size' => $total_size,
        'slide_count' => $slide_count,
        'total_slide_size' => $total_slide_size,
        'details' => [
            'title' => $title,
            'speaker' => $speaker,
            'duration_formatted' => gmdate('H:i:s', round($total_duration)),
            'size_formatted' => round($total_size / (1024 * 1024), 2) . ' MB',
            'slides_size_formatted' => round($total_slide_size / (1024 * 1024), 2) . ' MB'
        ],
        'file_paths' => [
            'audio_folder' => $sermon_dir,
            'slides_folder' => $sermon_dir . 'slides/',
            'metadata_file' => $metadata_file
        ]
    ];

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Sermon finalized and saved successfully',
        'data' => $completion_summary
    ]);

} catch (PDOException $e) {
    // Rollback transaction on database error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Sermon save database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred while saving sermon']);
} catch (Exception $e) {
    // Rollback transaction on general error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Sermon save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}