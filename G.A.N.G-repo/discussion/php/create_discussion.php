<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = $_POST['topic'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;

    if (!$topic || !$userId) {
        echo json_encode(['success' => false, 'message' => 'Missing topic or user.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO discussions (topic, created_by) VALUES (:topic, :created_by)");
        $stmt->execute([
            'topic' => $topic,
            'created_by' => $userId
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
