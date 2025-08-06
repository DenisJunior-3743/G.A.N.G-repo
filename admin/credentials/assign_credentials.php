<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

try {
  // Simulate delay (for testing timeout)
  // sleep(6); // Uncomment to test timeout

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role_id = $_POST['role_id'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $existing = $stmt->fetch();

    if ($existing) {
      $update = $pdo->prepare("UPDATE users SET username = ?, password_hash = ?, role_id = ? WHERE member_id = ?");
      $update->execute([$username, $password, $role_id, $member_id]);
      echo json_encode(['success' => true, 'message' => 'User credentials updated successfully.']);
    } else {
      $insert = $pdo->prepare("INSERT INTO users (member_id, username, password_hash, role_id) VALUES (?, ?, ?, ?)");
      $insert->execute([$member_id, $username, $password, $role_id]);
      echo json_encode(['success' => true, 'message' => 'User credentials assigned successfully.']);
    }
  } else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
  }
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
