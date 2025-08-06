<?php
require_once dirname(__DIR__, 2) . '/config/database.php';

$stmt = $pdo->query("SELECT d.id, d.topic, d.created_at, m.second_name 
                     FROM discussions d
                     JOIN members m ON d.created_by = m.id
                     ORDER BY d.created_at DESC");

$discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($discussions);
