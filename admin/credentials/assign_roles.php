<?php
require_once __DIR__ . '/../../config/database.php';

// Fetch members
$stmt = $pdo->query("SELECT id, first_name, second_name FROM members ORDER BY first_name ASC");
$members = $stmt->fetchAll();

// Fetch roles (assuming there's a roles table)
$rolesStmt = $pdo->query("SELECT id, role_name FROM roles ORDER BY role_name ASC");
$roles = $rolesStmt->fetchAll();
?>

<div class="container">
  <h4 class="mb-4">Assign Login Credentials</h4>

  <form id="assign-credentials-form">
    <div class="mb-3">
      <label for="member_id" class="form-label">Select Member</label>
      <select class="form-select" name="member_id" id="member_id" required>
        <option value="">-- Select Member --</option>
        <?php foreach ($members as $member): ?>
          <option value="<?= $member['id'] ?>">
            <?= $member['id'] ?> - <?= $member['first_name'] ?> <?= $member['second_name'] ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label for="username" class="form-label">Username</label>
      <input type="text" name="username" id="username" class="form-control" required />
    </div>

    <div class="mb-3 position-relative">
  <label for="password" class="form-label">Password</label>
  <input type="password" name="password" id="password" class="form-control" required />
  <span id="togglePassword" 
        style="position: absolute; top: 38px; right: 12px; cursor: pointer; user-select: none;">
    <i class="fa fa-eye"></i>
  </span>
</div>


    <div class="mb-3">
      <label for="role_id" class="form-label">Select Role</label>
      <select class="form-select" name="role_id" id="role_id" required>
        <option value="">-- Select Role --</option>
        <?php foreach ($roles as $role): ?>
          <option value="<?= $role['id'] ?>"><?= $role['role_name'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <button type="submit" class="btn btn-primary">Save Credentials</button>
    <div id="assign-result" class="mt-3"></div>
  </form>
</div>
