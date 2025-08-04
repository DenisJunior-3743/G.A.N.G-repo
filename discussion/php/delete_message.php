<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['message_id'] ?? 0;
$userId = $_SESSION['user_id'];

// Only allow deleting own messages
$stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
$result = $stmt->execute([$id, $userId]);

echo json_encode(['success' => $result]);
