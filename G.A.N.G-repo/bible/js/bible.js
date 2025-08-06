const API_KEY = '51582f07513fe631b1740b7a1f48ed4d';
const BASE_URL = 'https://api.scripture.api.bible/v1';
const KJV_ID = 'de4e12af7f28f599-01';

const versionSelect = document.getElementById('versionSelect');
const bookSelect = document.getElementById('bookSelect');
const chapterSelect = document.getElementById('chapterSelect');
const verseContainer = document.getElementById('verseContainer');

// Disable version change
versionSelect.innerHTML = `<option value="${KJV_ID}" selected>KJV</option>`;
versionSelect.disabled = true;

// Fetch Books
async function fetchBooks() {
  try {
    const res = await fetch(`${BASE_URL}/bibles/${KJV_ID}/books`, {
      headers: { 'api-key': API_KEY }
    });
    const data = await res.json();

    bookSelect.innerHTML = '';
    data.data.forEach(book => {
      const opt = document.createElement('option');
      opt.value = book.id;
      opt.textContent = book.name;
      bookSelect.appendChild(opt);
    });

    bookSelect.dispatchEvent(new Event('change'));
  } catch (err) {
    verseContainer.textContent = '❌ Failed to load books.';
    console.error(err);
  }
}

// Fetch Chapters for selected Book
async function fetchChapters(bookId) {
  try {
    const res = await fetch(`${BASE_URL}/bibles/${KJV_ID}/books/${bookId}/chapters`, {
      headers: { 'api-key': API_KEY }
    });
    const data = await res.json();

    chapterSelect.innerHTML = '';
    data.data.forEach(chapter => {
      if (chapter.number !== 'intro') {
        const opt = document.createElement('option');
        opt.value = chapter.id; // Format: GEN.1
        opt.textContent = chapter.number;
        chapterSelect.appendChild(opt);
      }
    });

    chapterSelect.dispatchEvent(new Event('change'));
  } catch (err) {
    verseContainer.textContent = '❌ Failed to load chapters.';
    console.error(err);
  }
}

// Fetch Full Passage
async function fetchPassage(chapterId) {
  try {
    verseContainer.innerHTML = '📖 Loading verses...';
    const res = await fetch(`${BASE_URL}/bibles/${KJV_ID}/passages/${chapterId}`, {
      headers: { 'api-key': API_KEY }
    });
    const data = await res.json();

    if (!data.data || !data.data.content) {
      verseContainer.innerHTML = '⚠️ No verses found.';
      return;
    }

    verseContainer.innerHTML = data.data.content;
  } catch (err) {
    verseContainer.innerHTML = '❌ Failed to load passage.';
    console.error(err);
  }
}

// Event: Book changed
bookSelect.addEventListener('change', () => {
  const bookId = bookSelect.value;
  if (bookId) fetchChapters(bookId);
});

// Event: Chapter changed
chapterSelect.addEventListener('change', () => {
  const chapterId = chapterSelect.value;
  if (chapterId) fetchPassage(chapterId);
});

// Chapter Navigation
document.getElementById('prevChapter').addEventListener('click', () => {
  if (chapterSelect.selectedIndex > 0) {
    chapterSelect.selectedIndex -= 1;
    chapterSelect.dispatchEvent(new Event('change'));
  }
});

document.getElementById('nextChapter').addEventListener('click', () => {
  if (chapterSelect.selectedIndex < chapterSelect.length - 1) {
    chapterSelect.selectedIndex += 1;
    chapterSelect.dispatchEvent(new Event('change'));
  }
});

// ✅ Load books initially
fetchBooks();

// Mark Chapter as Read
document.getElementById('markReadBtn').addEventListener('click', () => {
  const book = bookSelect.options[bookSelect.selectedIndex].text;
  const chapter = chapterSelect.options[chapterSelect.selectedIndex].text;

  fetch('/G.A.N.G/bible/php/mark_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ book, chapter })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert("✅ Chapter marked as read!");
      } else {
        alert("⚠️ " + data.message);
      }
    })
    .catch(err => {
      console.error(err);
      alert("❌ Failed to mark as read.");
    });
});

// View Progress
document.getElementById('viewProgressBtn').addEventListener('click', async () => {
  try {
    const res = await fetch('/G.A.N.G/bible/php/progress.php');
    const result = await res.json();

    if (!result.success) return showToast("Failed to load progress", "danger");

    const data = result.data;
    const otPercent = ((data.ot_read / data.ot_total) * 100).toFixed(1);
    const ntPercent = ((data.nt_read / data.nt_total) * 100).toFixed(1);
    const totalPercent = ((data.total_read / data.overall_total) * 100).toFixed(1);

    const html = `
      <div class="mb-3">
        <strong>Old Testament:</strong> ${data.ot_read} / ${data.ot_total} chapters (${otPercent}%)
        <div class="progress mt-1">
          <div class="progress-bar bg-warning" style="width: ${otPercent}%" role="progressbar"></div>
        </div>
      </div>
      <div class="mb-3">
        <strong>New Testament:</strong> ${data.nt_read} / ${data.nt_total} chapters (${ntPercent}%)
        <div class="progress mt-1">
          <div class="progress-bar bg-success" style="width: ${ntPercent}%" role="progressbar"></div>
        </div>
      </div>
      <div class="mb-2">
        <strong>Overall:</strong> ${data.total_read} / ${data.overall_total} chapters (${totalPercent}%)
        <div class="progress mt-1">
          <div class="progress-bar bg-primary" style="width: ${totalPercent}%" role="progressbar"></div>
        </div>
      </div>
    `;

    document.querySelector('#progressModal .modal-body').innerHTML = html;
    new bootstrap.Modal(document.getElementById('progressModal')).show();

  } catch (err) {
    showToast("Something went wrong.", "danger");
  }
});

function showToast(message, type = 'success') {
  const toast = document.getElementById('toast');
  const toastMessage = document.getElementById('toastMessage');

  toast.classList.remove('bg-success', 'bg-danger', 'bg-info', 'bg-warning');
  toast.classList.add(`bg-${type}`);
  toastMessage.textContent = message;

  const bsToast = new bootstrap.Toast(toast);
  bsToast.show();
}
