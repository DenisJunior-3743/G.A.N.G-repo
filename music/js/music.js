const clientId = "9a6e7536";

const searchInput = document.getElementById("searchInput");
const searchBtn = document.getElementById("searchBtn");
const tracksContainer = document.getElementById("tracks");

searchBtn.addEventListener("click", () => {
  const query = searchInput.value.trim();
  if (!query) {
    alert("Please enter a search term.");
    return;
  }
  fetchSongs(query);
});

// Optionally, fetch songs on Enter key press
searchInput.addEventListener("keypress", (e) => {
  if (e.key === "Enter") {
    searchBtn.click();
  }
});

async function fetchSongs(query) {
  tracksContainer.innerHTML = "Loading songs...";
  try {
    const url = `https://api.jamendo.com/v3.0/tracks/?client_id=${clientId}&format=json&limit=10&lang=en&search=${encodeURIComponent(query)}`;
    const res = await fetch(url);
    const data = await res.json();

    if (!data.results || data.results.length === 0) {
      tracksContainer.innerHTML = `<div id="no-results">No songs found for "<strong>${query}</strong>". Try another search.</div>`;
      return;
    }

    tracksContainer.innerHTML = "";

    data.results.forEach(track => {
      const trackDiv = document.createElement("div");
      trackDiv.className = "track";
      trackDiv.innerHTML = `
        <h3>${track.name}</h3>
        <p>By ${track.artist_name}</p>
        <audio controls>
          <source src="${track.audio}" type="audio/mpeg" />
          Your browser does not support the audio element.
        </audio>
      `;
      tracksContainer.appendChild(trackDiv);
    });

  } catch (error) {
    tracksContainer.innerHTML = "<div id='no-results'>Error fetching songs. Please try again.</div>";
    console.error("Error fetching songs:", error);
  }
}
