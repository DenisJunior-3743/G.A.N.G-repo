<?php
require_once __DIR__ . '/../../config/database.php';
// Get total members
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM members");
$members = $stmt->fetch()['total'];

// Get total enquiries (assuming enquiries table exists)
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM enquiries");
$enquiries = $stmt->fetch()['total'];

// Get total discussions
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM discussions");
$discussions = $stmt->fetch()['total'];
?>

<div class="container">
  <h4 class="mb-4">Dashboard Overview</h4>
  <div class="row g-4">

    <div class="col-md-4">
      <div class="card text-white bg-primary shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Total Members</h5>
          <p class="card-text display-6"><?= $members ?></p>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card text-white bg-success shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Total Enquiries</h5>
          <p class="card-text display-6"><?= $enquiries ?></p>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card text-white bg-warning shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Discussions</h5>
          <p class="card-text display-6"><?= $discussions ?></p>
        </div>
      </div>
    </div>

  </div>

  <div class="mt-5">
    <h5>Bible Reading Stats (Coming soon)</h5>
    <p class="text-muted">Chart or table showing reading frequency, bookmarks, and time spent will appear here.</p>
  </div>
</div>
