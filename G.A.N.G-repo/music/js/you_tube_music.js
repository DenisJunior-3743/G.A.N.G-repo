
  const apiKey = 'AIzaSyB4zvPsNRAbjLqIFja9y_XoV-lTXWocb-g';

  const themeToggle = document.getElementById('themeToggle');
  const body = document.body;

  const searchInput = document.getElementById('searchInput');
  const searchBtn = document.getElementById('searchBtn');
  const clearBtn = document.getElementById('clearBtn');
  const resultsDiv = document.getElementById('results');
  const playerDiv = document.getElementById('player');

  let debounceTimeout;
  let currentlyPlayingVideoId = null;

  // Theme toggle
  themeToggle.addEventListener('click', () => {
    body.classList.toggle('light');
    if (body.classList.contains('light')) {
      themeToggle.textContent = 'Dark Mode';
    } else {
      themeToggle.textContent = 'Light Mode';
    }
  });

  // Search functions
  searchBtn.addEventListener('click', doSearch);
  clearBtn.addEventListener('click', () => {
    searchInput.value = '';
    resultsDiv.innerHTML = '';
    playerDiv.innerHTML = '';
    currentlyPlayingVideoId = null;
    searchInput.focus();
  });
  searchInput.addEventListener('input', () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(doSearch, 500);
  });
  searchInput.addEventListener('keypress', e => {
    if (e.key === 'Enter') doSearch();
  });

  async function doSearch() {
  const query = searchInput.value.trim();
  if (!query) {
    resultsDiv.innerHTML = '';
    playerDiv.innerHTML = '';
    currentlyPlayingVideoId = null;
    return;
  }
  resultsDiv.innerHTML = `<div id="loadingSpinner"></div>`;
  playerDiv.innerHTML = '';

  const refinedQuery = `${query} christian gospel worship`;

  try {
    const response = await fetch(`https://www.googleapis.com/youtube/v3/search?part=snippet&maxResults=12&q=${encodeURIComponent(refinedQuery)}&type=video&videoCategoryId=10&key=${apiKey}`);
    const data = await response.json();

   
      if (!data.items || data.items.length === 0) {
        resultsDiv.innerHTML = `<div id="noResults">No results found for "<strong>${query}</strong>".</div>`;
        return;
      }

      resultsDiv.innerHTML = '';
      currentlyPlayingVideoId = null;

      data.items.forEach(item => {
        const videoId = item.id.videoId;
        const { title, channelTitle, publishedAt, thumbnails } = item.snippet;

        const videoDiv = document.createElement('div');
        videoDiv.className = 'video';
        videoDiv.dataset.videoId = videoId;

        videoDiv.innerHTML = `
          <img src="${thumbnails.medium.url}" alt="${title}" />
          <h4>${title}</h4>
          <p>${channelTitle} • ${new Date(publishedAt).toLocaleDateString()}</p>
        `;

        videoDiv.onclick = () => playVideo(videoId, videoDiv);

        resultsDiv.appendChild(videoDiv);
      });

    } catch (error) {
      console.error(error);
      resultsDiv.innerHTML = `<div id="noResults">Error fetching videos. Please try again later.</div>`;
    }
  }

  function playVideo(videoId, videoDiv) {
    if (currentlyPlayingVideoId) {
      const prevPlaying = document.querySelector(`.video.playing`);
      if (prevPlaying) prevPlaying.classList.remove('playing');
    }
    currentlyPlayingVideoId = videoId;
    videoDiv.classList.add('playing');

    playerDiv.innerHTML = `
      <iframe src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0"
              allow="autoplay; encrypted-media" allowfullscreen></iframe>
    `;
  }
