<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$memberId = $_SESSION['member_id'];

try {
    // Fetch chapters marked read by member and their testament
    $stmt = $pdo->prepare("
        SELECT 
            bp.book, 
            COUNT(*) AS read_count, 
            bb.testament
        FROM bible_progress bp
        JOIN bible_books bb ON bp.book = bb.name
        WHERE bp.member_id = :member_id
        GROUP BY bp.book
    ");
    $stmt->execute(['member_id' => $memberId]);
    $reads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total chapters in OT and NT
    $totalsStmt = $pdo->query("
        SELECT 
            testament,
            SUM(chapters) as total_chapters
        FROM bible_books
        GROUP BY testament
    ");
    $totals = $totalsStmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['OT' => 929, 'NT' => 260]

    // Counters
    $otRead = 0;
    $ntRead = 0;
    $totalRead = 0;

    foreach ($reads as $entry) {
        $count = (int)$entry['read_count'];
        $totalRead += $count;

        if ($entry['testament'] === 'OT') {
            $otRead += $count;
        } elseif ($entry['testament'] === 'NT') {
            $ntRead += $count;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'ot_read' => $otRead,
            'nt_read' => $ntRead,
            'total_read' => $totalRead,
            'ot_total' => $totals['OT'] ?? 929,
            'nt_total' => $totals['NT'] ?? 260,
            'overall_total' => ($totals['OT'] ?? 929) + ($totals['NT'] ?? 260)
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
