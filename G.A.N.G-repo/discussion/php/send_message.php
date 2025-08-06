<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
session_start();

header('Content-Type: application/json');

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Decode the incoming JSON payload
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (
    !$data ||
    !isset($data['discussion_id'], $data['type'], $data['content'])
) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Extract values
$discussion_id = (int) $data['discussion_id'];
$sender_id = (int) $_SESSION['user_id'];
$type = trim($data['type']); // expected: 'text' or 'audio'
$content = trim($data['content']);
$reply_to = isset($data['reply_to']) && is_numeric($data['reply_to']) ? (int) $data['reply_to'] : null;

try {
    // Prepare and execute insert statement
    $stmt = $pdo->prepare("
        INSERT INTO messages (discussion_id, sender_id, type, content, reply_to)
        VALUES (?, ?, ?, ?, ?)
    ");
    $result = $stmt->execute([
        $discussion_id,
        $sender_id,
        $type,
        $content,
        $reply_to
    ]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save message']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
