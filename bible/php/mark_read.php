<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$member_id = $_SESSION['member_id'];
$book = $data['book'] ?? '';
$chapter = $data['chapter'] ?? '';

if (!$book || !$chapter) {
    echo json_encode(['success' => false, 'message' => 'Missing data.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO bible_progress (member_id, book, chapter) VALUES (?, ?, ?)");
    $stmt->execute([$member_id, $book, $chapter]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
