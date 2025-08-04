<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

$discussionId = $_GET['discussion_id'] ?? 0;
$stmt = $pdo->prepare("SELECT 
                          m.id, m.sender_id, m.type, m.content, m.reply_to, m.created_at,
                          mem.second_name, mem.picture
                       FROM messages m
                       JOIN users u ON m.sender_id = u.id
                       JOIN members mem ON u.member_id = mem.id
                       WHERE m.discussion_id = ?
                       ORDER BY m.created_at ASC");
$stmt->execute([$discussionId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($messages);