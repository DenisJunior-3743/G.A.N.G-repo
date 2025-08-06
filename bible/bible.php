<?php
session_start();

// Fallbacks for demo/testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['second_name'] = 'John';
    $_SESSION['picture'] = '/uploads/users/default.jpg'; // Replace with actual image path
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Bible Reading - G.A.N.G</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>

   <link href="/G.A.N.G/includes/css/main.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/header.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/footer.css" rel="stylesheet">
    
  <style>
    body { padding-top: 80px; }
    .verse { margin-bottom: 1rem; padding: 0.5rem 1rem; background: #f9f9f9; border-left: 4px solid #007bff; }
    .bible-header { margin-bottom: 1.5rem; }
  </style>
</head>
<body>

<div id="header"></div>

<?php include dirname(__DIR__,1) . '/includes/welcome_section.php'; ?>

  <!-- HEADER -->
  <div class="container">
    <div class="row bible-header align-items-center">
      <div class="col-md-3">
        <label for="versionSelect" class="form-label fw-bold">Bible Version</label>
        <select class="form-select" id="versionSelect">
          <option value="kjv">KJV</option>
          <option value="nkjv">NKJV</option>
          <option value="amp">Amplified</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="bookSelect" class="form-label fw-bold">Book</label>
        <select class="form-select" id="bookSelect">
          <!-- To be populated dynamically -->
        </select>
      </div>
      <div class="col-md-2">
        <label for="chapterSelect" class="form-label fw-bold">Chapter</label>
        <select class="form-select" id="chapterSelect">
          <!-- To be populated dynamically -->
        </select>
      </div>
      <div class="col-md-4 text-end mt-3 mt-md-0">
        <button id="prevChapter" class="btn btn-outline-primary me-2"><i class="fas fa-arrow-left"></i> Prev</button>
        <button id="nextChapter" class="btn btn-outline-primary"><i class="fas fa-arrow-right"></i> Next</button>
      </div>
    </div>

    <hr/>

    <!-- Bible Verses -->
    <div id="verseContainer">
      <p class="text-center text-muted">Select a book and chapter to begin reading.</p>
    </div>

    <!-- Actions -->
    <div class="mt-4 text-end">
      <button class="btn btn-success me-2" id="markReadBtn"><i class="fas fa-check-circle"></i> Mark Chapter as Read</button>
      <button class="btn btn-info" id="viewProgressBtn"><i class="fas fa-chart-line"></i> View Progress</button>
    </div>
  </div>

<!-- Progress Modal -->
<div class="modal fade" id="progressModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Your Bible Reading Progress</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Filled dynamically by JS -->
      </div>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999">
  <div id="toast" class="toast align-items-center text-white bg-success border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toastMessage">Message</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<div id="footer"></div>
  <!-- Bootstrap & JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/G.A.N.G/includes/js/include.js"></script>  
  
  <script src="/G.A.N.G/bible/js/bible.js"></script>
</body>
</html>
