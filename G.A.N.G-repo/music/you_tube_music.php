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
  <title>God's Appointed YouTube Music Player - G.A.N.G</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  
  <!-- Custom CSS -->
  <link href="/G.A.N.G/includes/css/main.css" rel="stylesheet">
  <link href="/G.A.N.G/includes/css/header.css" rel="stylesheet">
  <link href="/G.A.N.G/includes/css/footer.css" rel="stylesheet">
  <link href="/G.A.N.G/includes/css/layout.css" rel="stylesheet">
  
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="page-container">
  <!-- Header -->
  <div id="header"></div>
  
  <!-- Welcome Section -->
  <?php include dirname(__DIR__) . '/includes/welcome_section.php'; ?>

  <!-- Theme Toggle Button - Removed, using header toggle instead -->

  <!-- Main Content -->
  <main class="main-content">
    <div id="musicPlayerContainer" class="music-player-container">
      <div class="content-wrapper">
        <h1 class="page-title slide-in-top">
          <i class="fas fa-music me-3"></i>
          God's Appointed YouTube Music Player
        </h1>

        <!-- Search Container -->
        <div class="music-search-container slide-in-top" style="animation-delay: 0.2s;">
          <input type="text" id="searchInput" placeholder="Search Christian songs, worship music, hymns..." autocomplete="off" />
          <button id="searchBtn">
            <i class="fas fa-search me-1"></i> Search
          </button>
          <button id="clearBtn" title="Clear search">
            <i class="fas fa-times me-1"></i> Clear
          </button>
        </div>

        <!-- Results Container -->
        <div id="results" class="video-grid fade-in-content"></div>

        <!-- Player Container -->
        <div id="player" class="player-container fade-in-content" style="display: none;"></div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <div id="footer"></div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Custom Scripts -->
  <script src="/G.A.N.G/music/js/you_tube_music.js"></script>
  <script src="/G.A.N.G/includes/js/include.js"></script>

  <!-- Enhanced Music Player Script -->
  <script>
    // Theme toggle functionality - Now handled by header toggle
    let isDarkTheme = true;

    // Listen for theme changes from header toggle
    document.addEventListener('DOMContentLoaded', function() {
      const themeToggle = document.querySelector('#themeToggle');
      if (themeToggle) {
        themeToggle.addEventListener('click', function() {
          const container = document.getElementById('musicPlayerContainer');
          isDarkTheme = !isDarkTheme;
          
          if (isDarkTheme) {
            container.classList.remove('light-theme');
          } else {
            container.classList.add('light-theme');
          }
        });
      }
    });

    // Enhanced search functionality
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        document.getElementById('searchBtn').click();
      }
    });

    // Clear functionality
    document.getElementById('clearBtn').addEventListener('click', function() {
      document.getElementById('searchInput').value = '';
      document.getElementById('results').innerHTML = '';
      const player = document.getElementById('player');
      player.style.display = 'none';
      player.innerHTML = '';
    });

    // Add loading spinner function
    function showLoadingSpinner() {
      const results = document.getElementById('results');
      results.innerHTML = '<div class="loading-spinner"></div>';
    }

    // Add no results function
    function showNoResults() {
      const results = document.getElementById('results');
      results.innerHTML = `
        <div class="no-results">
          <i class="fas fa-search fa-3x mb-3 text-muted"></i>
          <p>No music found. Try searching for Christian songs, worship music, or hymns.</p>
        </div>
      `;
    }

    // Enhanced video click handling
    function playVideo(videoId, title) {
      // Remove playing class from all videos
      document.querySelectorAll('.video-item').forEach(video => {
        video.classList.remove('playing');
      });
      
      // Add playing class to clicked video
      event.currentTarget.classList.add('playing');
      
      // Show player
      const player = document.getElementById('player');
      player.style.display = 'block';
      player.innerHTML = `
        <iframe 
          src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1" 
          title="${title}"
          frameborder="0" 
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
          allowfullscreen>
        </iframe>
      `;
      
      // Scroll to player
      player.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Page load animations
    document.addEventListener('DOMContentLoaded', function() {
      // Add staggered animations to elements
      const elements = document.querySelectorAll('.slide-in-top, .fade-in-content');
      elements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
      });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      // Focus search input with Ctrl+F
      if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.getElementById('searchInput').focus();
      }
      
      // Clear search with Escape
      if (e.key === 'Escape') {
        document.getElementById('clearBtn').click();
      }
    });

    // Search suggestions (basic implementation)
    const searchSuggestions = [
      'Hillsong United', 'Chris Tomlin', 'Bethel Music', 'Elevation Worship',
      'Planetshakers', 'Jesus Culture', 'Kari Jobe', 'Matt Redman',
      'How Great Is Our God', 'Amazing Grace', 'Way Maker', 'Goodness of God',
      'Reckless Love', 'What A Beautiful Name', 'Oceans', 'Great Are You Lord'
    ];

    // Add search input focus effects
    document.getElementById('searchInput').addEventListener('focus', function() {
      this.style.boxShadow = '0 0 20px rgba(76, 175, 80, 0.4)';
    });

    document.getElementById('searchInput').addEventListener('blur', function() {
      this.style.boxShadow = '0 0 10px rgba(76, 175, 80, 0.3)';
    });
  </script>
</body>
</html>

