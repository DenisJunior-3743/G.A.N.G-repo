<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (
    !$data ||
    !isset($data['discussion_id'], $data['type'], $data['content'])
) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$discussion_id = (int) $data['discussion_id'];
$sender_id = (int) $_SESSION['user_id'];
$type = trim($data['type']); // should be 'audio'
$content = $data['content'];
$reply_to = isset($data['reply_to']) && is_numeric($data['reply_to']) ? (int) $data['reply_to'] : null;

// Directory to save audio files
$uploadDir = dirname(__DIR__, 1) . '/audio/';

// Make sure directory exists
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Decode base64 data URL (e.g., data:audio/webm;base64,XXXXX)
if (preg_match('/^data:audio\/(\w+);base64,/', $content, $matches)) {
    $audioType = $matches[1]; // e.g. webm, wav, mp3
    $base64Str = substr($content, strpos($content, ',') + 1);
    $audioData = base64_decode($base64Str);

    if ($audioData === false) {
        echo json_encode(['success' => false, 'message' => 'Base64 decode failed']);
        exit;
    }

    // Generate unique filename
    $fileName = uniqid('audio_', true) . '.' . $audioType;
    $filePath = $uploadDir . $fileName;

    if (file_put_contents($filePath, $audioData) === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to save audio file']);
        exit;
    }

    // Save relative path for DB (assuming your web root is /G.A.N.G)
    $relativePath = '/G.A.N.G/discussion/audio/' . $fileName;

    // Insert message record in DB
    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages (discussion_id, sender_id, type, content, reply_to)
            VALUES (?, ?, ?, ?, ?)
        ");
        $result = $stmt->execute([
            $discussion_id,
            $sender_id,
            $type,
            $relativePath,
            $reply_to
        ]);

        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save message']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid audio data']);
}
