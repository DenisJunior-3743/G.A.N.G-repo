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
  <title>Admin Dashboard | G.A.N.G</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <script src="https://unpkg.com/wavesurfer.js@7.0.3/dist/wavesurfer.min.js"></script>

   <link href="/G.A.N.G/includes/css/main.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/header.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/footer.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/layout.css" rel="stylesheet">
    <link href="/G.A.N.G/admin/css/admin.css" rel="stylesheet">

 
</head>
<body>

<div id="header"></div>

<?php include dirname(__DIR__) . '/includes/welcome_section.php'; ?>
  <!-- Sidebar -->
  <div class="sidebar">
    <h4 class="text-center py-3">Admin Panel</h4>

    <a href="/G.A.N.G/admin/pages/dashboard.php" class="menu-link" data-page="dashboard">Dashboard Overview</a>

    <div class="has-submenu">
      <a href="#" class="toggle-submenu">Manage Credentials <i class="fa fa-caret-down float-end"></i></a>
      <div class="submenu">
        <a href="/G.A.N.G/admin/credentials/assign_roles.php" class="menu-link" data-page="credentials/assign_roles">Assign Roles</a>
      </div>
    </div>
    
    <div class="has-submenu">
      <a href="#" class="toggle-submenu">Sermons <i class="fa fa-caret-down float-end"></i></a>
      <div class="submenu">
        <a href="/G.A.N.G/admin/sermon/record-sermon.php" class="menu-link" data-page="sermon/record-sermon">Record Sermon</a>
        <a href="#" class="menu-link" data-page="upload-slide">Upload Slides</a>
      </div>
    </div>
    
    <a href="#" class="menu-link" data-page="announcements">Make Announcements</a>
    <a href="#" class="menu-link" data-page="members">Manage Members</a>
  </div>

  <!-- Main Content -->
  <div class="main" id="main-content">
    <h3>Welcome Admin</h3>
    <p>Select a section from the sidebar.</p>
  </div>

  <!-- Footer -->
  <div id="footer"></div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/G.A.N.G/admin/js/ajax.js"></script>
  <script src="/G.A.N.G/includes/js/include.js"></script>  
  <script>
    // Sidebar toggle
    $('.toggle-submenu').on('click', function () {
      $(this).parent().toggleClass('open');
    });

    // Load pages via AJAX
    // Example of your AJAX page loader snippet
$('.menu-link').on('click', function (e) {
  e.preventDefault();
  const page = $(this).data('page');
  $('#main-content').html('<p>Loading...</p>');
  $.get(`${page}.php`, function (data) {
    $('#main-content').html(data);
    // Initialize password toggle on the loaded content
    setupPasswordToggle();
  }).fail(function () {
    $('#main-content').html('<div class="alert alert-danger">Failed to load page.</div>');
  });
});


  </script>
</body>
</html>
