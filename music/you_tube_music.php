<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>God's Appointed YouTube Music Player</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
   <link href="/G.A.N.G/includes/css/main.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/header.css" rel="stylesheet">
    <link href="/G.A.N.G/includes/css/footer.css" rel="stylesheet">
    
  <style>
    :root {
      --bg-light: #f0f0f0;
      --text-light: #121212;
      --bg-dark: #121212;
      --text-dark: #eee;
      --primary-color: #4caf50;
    }

    body {
      font-family: Arial, sans-serif;
      background: var(--bg-dark);
      color: var(--text-dark);
      transition: background-color 0.3s, color 0.3s;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px 0 0;
    }

    body.light {
      background: var(--bg-light);
      color: var(--text-light);
    }

    h1 {
      color: var(--primary-color);
      text-shadow: 0 0 10px var(--primary-color);
      user-select: none;
      text-align: center;
      margin-bottom: 2rem;
    }

    #themeToggle {
      position: fixed;
      top: 10px;
      right: 10px;
      background: var(--primary-color);
      color: #fff;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      cursor: pointer;
      font-weight: bold;
      user-select: none;
      transition: background 0.3s;
    }

    #themeToggle:hover {
      background: #388e3c;
    }

    .content-wrapper {
      width: 100%;
      max-width: 1000px;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px;
    }

    #searchContainer {
      display: flex;
      gap: 0.5rem;
      width: 100%;
      max-width: 600px;
      margin-bottom: 30px;
      flex-wrap: wrap;
      justify-content: center;
    }

    #searchInput {
      flex-grow: 1;
      padding: 0.6rem 1rem;
      font-size: 1rem;
      border-radius: 30px;
      border: none;
      outline: none;
      box-shadow: 0 0 10px var(--primary-color);
      background-color: var(--bg-dark);
      color: var(--text-dark);
      transition: background-color 0.3s, color 0.3s;
    }

    body.light #searchInput {
      background-color: var(--bg-light);
      color: var(--text-light);
    }

    #searchInput::placeholder {
      color: #aaa;
    }

    #searchBtn, #clearBtn {
      padding: 0 1.2rem;
      border: none;
      border-radius: 30px;
      font-weight: bold;
      background-color: var(--primary-color);
      color: white;
      cursor: pointer;
      user-select: none;
      transition: background-color 0.3s;
    }

    #searchBtn:hover, #clearBtn:hover {
      background-color: #388e3c;
    }

    #results {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
      width: 100%;
      max-width: 1000px;
      margin-top: 20px;
    }

    .video {
      background: var(--bg-dark);
      border-radius: 12px;
      box-shadow: 0 0 10px #0008;
      padding: 10px;
      cursor: pointer;
      transition: box-shadow 0.3s, transform 0.3s;
      user-select: none;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    body.light .video {
      background: var(--bg-light);
      box-shadow: 0 0 15px #aaa8;
    }

    .video:hover {
      box-shadow: 0 0 20px var(--primary-color);
      transform: scale(1.03);
    }

    .video img {
      width: 100%;
      border-radius: 12px;
      user-select: none;
    }

    .video h4 {
      margin: 10px 0 5px;
      text-align: center;
      color: var(--primary-color);
    }

    .video p {
      margin: 0 0 10px;
      font-size: 0.9rem;
      text-align: center;
      color: #aaa;
      user-select: text;
    }

    .video.playing {
      box-shadow: 0 0 30px var(--primary-color);
      border: 2px solid var(--primary-color);
      transform: scale(1.05);
      pointer-events: none;
    }

    #player {
      margin-top: 30px;
      width: 100%;
      max-width: 800px;
      aspect-ratio: 16 / 9;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 0 30px var(--primary-color);
      user-select: none;
    }

    iframe {
      width: 100%;
      height: 100%;
      border: none;
    }

    #loadingSpinner {
      margin: 50px auto;
      border: 6px solid #ccc;
      border-top: 6px solid var(--primary-color);
      border-radius: 50%;
      width: 50px;
      height: 50px;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg);}
      100% { transform: rotate(360deg);}
    }

    #noResults {
      grid-column: 1 / -1;
      text-align: center;
      color: #888;
      font-size: 1.2rem;
      user-select: none;
    }

    .footer {
      width: 100%;
    }
  </style>
</head>

<body>
  <div id="header"></div>
  <?php include dirname(__DIR__, 1) . '/includes/welcome_section.php'; ?>

  <button id="themeToggle" title="Toggle Dark/Light Theme">Light Mode</button>

  <div class="content-wrapper">
    <h1>God's Appointed YouTube Music Player</h1>

    <div id="searchContainer">
      <input type="text" id="searchInput" placeholder="Search songs, e.g. Hillsong, worship..." autocomplete="off" />
      <button id="searchBtn">Search</button>
      <button id="clearBtn" title="Clear search">Clear</button>
    </div>

    <div id="results"></div>

    <div id="player"></div>
  </div>

  <div id="footer" class="footer bg-dark text-white py-4"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="/G.A.N.G/music/js/you_tube_music.js"></script>
  <script src="/G.A.N.G/includes/js/include.js"></script>
</body>
</html>
