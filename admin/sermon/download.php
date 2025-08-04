<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

// Check authentication
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$member_id = $_SESSION['member_id'];
$type = $_GET['type'] ?? '';
$sermon_id = (int)($_GET['sermon_id'] ?? 0);

if (!$sermon_id || !in_array($type, ['audio', 'slides', 'complete'])) {
    http_response_code(400);
    exit('Invalid parameters');
}

try {
    // Get sermon details
    $sermon_stmt = $pdo->prepare("
        SELECT s.*, m.first_name, m.second_name, m.third_name 
        FROM sermons s 
        LEFT JOIN members m ON s.member_id = m.id 
        WHERE s.id = ?
    ");
    $sermon_stmt->execute([$sermon_id]);
    $sermon = $sermon_stmt->fetch();

    if (!$sermon) {
        http_response_code(404);
        exit('Sermon not found');
    }

    $sermon_dir = dirname(__DIR__) . '/sermon/audio/' . $sermon['folder_name'] . '/';
    $slides_dir = $sermon_dir . 'slides/';

    if (!is_dir($sermon_dir)) {
        http_response_code(404);
        exit('Sermon files not found');
    }

    // Set up ZIP file for download
    $zip = new ZipArchive();
    $temp_zip = tempnam(sys_get_temp_dir(), 'sermon_') . '.zip';
    
    if ($zip->open($temp_zip, ZipArchive::CREATE) !== TRUE) {
        http_response_code(500);
        exit('Could not create download package');
    }

    $download_name = '';
    $total_size = 0;

    switch ($type) {
        case 'audio':
            $download_name = sanitizeFilename($sermon['title']) . '_Audio.zip';
            
            // Get audio chunks
            $chunks_stmt = $pdo->prepare("SELECT * FROM sermon_chunks WHERE sermon_id = ? ORDER BY chunk_index ASC");
            $chunks_stmt->execute([$sermon_id]);
            $chunks = $chunks_stmt->fetchAll();
            
            foreach ($chunks as $chunk) {
                $file_path = $sermon_dir . $chunk['filename'];
                if (file_exists($file_path)) {
                    $zip->addFile($file_path, 'audio/' . $chunk['filename']);
                    $total_size += filesize($file_path);
                }
            }
            
            // Add metadata
            addMetadataToZip($zip, $sermon, 'audio');
            break;

        case 'slides':
            $download_name = sanitizeFilename($sermon['title']) . '_Slides.zip';
            
            // Get slides
            $slides_stmt = $pdo->prepare("SELECT * FROM sermon_slides WHERE sermon_id = ? ORDER BY uploaded_at ASC");
            $slides_stmt->execute([$sermon_id]);
            $slides = $slides_stmt->fetchAll();
            
            foreach ($slides as $slide) {
                $file_path = $slides_dir . $slide['saved_filename'];
                if (file_exists($file_path)) {
                    // Use original filename in zip
                    $zip->addFile($file_path, 'slides/' . $slide['original_filename']);
                    $total_size += filesize($file_path);
                }
            }
            
            // Add metadata
            addMetadataToZip($zip, $sermon, 'slides');
            break;

        case 'complete':
            $download_name = sanitizeFilename($sermon['title']) . '_Complete.zip';
            
            // Add audio chunks
            $chunks_stmt = $pdo->prepare("SELECT * FROM sermon_chunks WHERE sermon_id = ? ORDER BY chunk_index ASC");
            $chunks_stmt->execute([$sermon_id]);
            $chunks = $chunks_stmt->fetchAll();
            
            foreach ($chunks as $chunk) {
                $file_path = $sermon_dir . $chunk['filename'];
                if (file_exists($file_path)) {
                    $zip->addFile($file_path, 'audio/' . $chunk['filename']);
                    $total_size += filesize($file_path);
                }
            }
            
            // Add slides
            $slides_stmt = $pdo->prepare("SELECT * FROM sermon_slides WHERE sermon_id = ? ORDER BY uploaded_at ASC");
            $slides_stmt->execute([$sermon_id]);
            $slides = $slides_stmt->fetchAll();
            
            foreach ($slides as $slide) {
                $file_path = $slides_dir . $slide['saved_filename'];
                if (file_exists($file_path)) {
                    $zip->addFile($file_path, 'slides/' . $slide['original_filename']);
                    $total_size += filesize($file_path);
                }
            }
            
            // Add comprehensive metadata
            addMetadataToZip($zip, $sermon, 'complete');
            break;
    }

    // Add README file
    $readme_content = generateReadmeContent($sermon, $type);
    $zip->addFromString('README.txt', $readme_content);

    $zip->close();

    // Verify ZIP was created successfully
    if (!file_exists($temp_zip) || filesize($temp_zip) === 0) {
        http_response_code(500);
        exit('Failed to create download package');
    }

    // Log download activity
    logDownloadActivity($pdo, $member_id, $sermon_id, $type, filesize($temp_zip));

    // Send file for download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $download_name . '"');
    header('Content-Length: ' . filesize($temp_zip));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // Output file and clean up
    readfile($temp_zip);
    unlink($temp_zip);
    exit;

} catch (PDOException $e) {
    error_log("Download database error: " . $e->getMessage());
    http_response_code(500);
    exit('Database error occurred');
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    http_response_code(500);
    exit('Download error occurred');
}

/**
 * Sanitize filename for safe download
 */
function sanitizeFilename($filename) {
    // Remove or replace unsafe characters
    $safe = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $filename);
    $safe = preg_replace('/\s+/', '_', $safe);
    $safe = trim($safe, '_-');
    
    // Ensure name is not empty
    if (empty($safe)) {
        $safe = 'Sermon_' . date('Y-m-d');
    }
    
    // Limit length
    return substr($safe, 0, 50);
}

/**
 * Add metadata to ZIP package
 */
function addMetadataToZip($zip, $sermon, $type) {
    $metadata = [
        'sermon_info' => [
            'id' => $sermon['id'],
            'title' => $sermon['title'],
            'speaker' => $sermon['speaker'],
            'folder_name' => $sermon['folder_name'],
            'created_at' => $sermon['created_at'],
            'completed_at' => $sermon['completed_at'],
            'total_duration_seconds' => $sermon['total_duration_seconds'],
            'total_size_bytes' => $sermon['total_size_bytes']
        ],
        'download_info' => [
            'type' => $type,
            'downloaded_at' => date('Y-m-d H:i:s'),
            'downloaded_by' => trim(($sermon['first_name'] ?? '') . ' ' . ($sermon['second_name'] ?? ''))
        ],
        'technical_info' => [
            'audio_format' => 'WebM/Opus',
            'audio_bitrate' => '128kbps',
            'chunk_duration' => '25 minutes (except final chunk)',
            'file_structure' => $type === 'complete' ? 'audio/ and slides/ folders' : ($type . '/ folder')
        ]
    ];

    $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
}

/**
 * Generate README content for download package
 */
function generateReadmeContent($sermon, $type) {
    $content = "=== SERMON DOWNLOAD PACKAGE ===\n\n";
    $content .= "Title: " . $sermon['title'] . "\n";
    $content .= "Speaker: " . $sermon['speaker'] . "\n";
    $content .= "Recorded: " . date('F j, Y \a\t g:i A', strtotime($sermon['created_at'])) . "\n";
    
    if ($sermon['completed_at']) {
        $content .= "Completed: " . date('F j, Y \a\t g:i A', strtotime($sermon['completed_at'])) . "\n";
    }
    
    $content .= "Duration: " . formatDuration($sermon['total_duration_seconds']) . "\n";
    $content .= "Total Size: " . formatFileSize($sermon['total_size_bytes']) . "\n\n";

    $content .= "=== PACKAGE CONTENTS ===\n\n";

    switch ($type) {
        case 'audio':
            $content .= "This package contains:\n";
            $content .= "- Audio chunks in WebM format (Opus codec, 128kbps)\n";
            $content .= "- Each chunk is approximately 25 minutes long\n";
            $content .= "- Files are numbered sequentially (chunk_000.webm, chunk_001.webm, etc.)\n";
            $content .= "- Play chunks in order for complete sermon\n\n";
            break;

        case 'slides':
            $content .= "This package contains:\n";
            $content .= "- Presentation slides in original formats\n";
            $content .= "- Supported formats: PDF, PowerPoint, Images\n";
            $content .= "- Files maintain original names and quality\n\n";
            break;

        case 'complete':
            $content .= "This package contains:\n";
            $content .= "- audio/ folder: Complete sermon audio in chunks\n";
            $content .= "- slides/ folder: All presentation slides\n";
            $content .= "- Audio: WebM format (Opus codec, 128kbps)\n";
            $content .= "- Slides: Original formats maintained\n\n";
            break;
    }

    $content .= "=== PLAYBACK INSTRUCTIONS ===\n\n";
    $content .= "Audio Files:\n";
    $content .= "- WebM files can be played in most modern media players\n";
    $content .= "- Recommended players: VLC, Chrome, Firefox, Edge\n";
    $content .= "- For best experience, play chunks in numerical order\n";
    $content .= "- Each chunk starts where the previous one ended\n\n";

    if ($type !== 'audio') {
        $content .= "Slide Files:\n";
        $content .= "- PDF files: Any PDF reader\n";
        $content .= "- PowerPoint files: Microsoft Office, LibreOffice, Google Slides\n";
        $content .= "- Image files: Any image viewer\n\n";
    }

    $content .= "=== TECHNICAL INFORMATION ===\n\n";
    $content .= "Audio Specifications:\n";
    $content .= "- Format: WebM container with Opus audio codec\n";
    $content .= "- Bitrate: 128 kbps\n";
    $content .= "- Sample Rate: 44.1 kHz\n";
    $content .= "- Channels: Mono\n";
    $content .= "- Chunk Size: ~25 minutes each\n\n";

    $content .= "File Organization:\n";
    $content .= "- Audio chunks are numbered sequentially starting from 000\n";
    $content .= "- Slides maintain their original filenames\n";
    $content .= "- metadata.json contains detailed technical information\n\n";

    $content .= "=== SUPPORT ===\n\n";
    $content .= "If you experience any issues with these files:\n";
    $content .= "1. Ensure you have a compatible media player installed\n";
    $content .= "2. Check that all files downloaded completely\n";
    $content .= "3. Try playing files in a different media player\n";
    $content .= "4. Contact your system administrator for technical support\n\n";

    $content .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
    $content .= "Package Type: " . ucfirst($type) . "\n";

    return $content;
}

/**
 * Log download activity for analytics
 */
function logDownloadActivity($pdo, $member_id, $sermon_id, $type, $download_size) {
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO download_logs (member_id, sermon_id, download_type, file_size, downloaded_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $log_stmt->execute([$member_id, $sermon_id, $type, $download_size]);
    } catch (PDOException $e) {
        // Log error but don't stop download
        error_log("Download logging error: " . $e->getMessage());
    }
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Format duration
 */
function formatDuration($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
}
?>