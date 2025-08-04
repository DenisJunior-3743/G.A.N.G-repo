const API_KEY = 'YOUR_API_KEY_HERE'; // Put your key here
const BASE_URL = 'https://api.scripture.api.bible/v1';

const versionSelect = document.getElementById('versionSelect');
const bookSelect = document.getElementById('bookSelect');
const chapterSelect = document.getElementById('chapterSelect');
const verseContainer = document.getElementById('verseContainer');

let kjvBibleId = null;
let books = [];

async function fetchKJVBible() {
  try {
    const res = await fetch(`${BASE_URL}/bibles?abbreviation=engKJV`, {
      headers: { 'api-key': API_KEY }
    });
    const data = await res.json();

    if (!res.ok || !data.data.length) throw new Error('KJV Bible not found');

    // We take the first matching KJV Bible
    kjvBibleId = data.data[0].id;

    // Set KJV as the only option in versionSelect and disable changing it
    versionSelect.innerHTML = `<option value="${kjvBibleId}" selected>KJV</option>`;
    versionSelect.disabled = true;

    await fetchBooks(kjvBibleId);
  } catch (err) {
    verseContainer.textContent = 'Failed to load KJV Bible.';
    console.error(err);
  }
}

async function fetchBooks(bibleId) {
  try {
    const res = await fetch(`${BASE_URL}/bibles/${bibleId}/books`, {
      headers: { 'api-key': API_KEY }
    });
    const data = await res.json();
    if (!res.ok) throw new Error('Failed to fetch books');

    books = data.data;
    bookSelect.innerHTML = '';
    books.forEach(book => {
      const opt = document.createElement('option');
      opt.value = book.id; // API book id
      opt.textContent = book.name;
      bookSelect.appendChild(opt);
    });

    if (books.length) {
      await fetchChapters(bibleId, books[0].id);
    }
  } catch (err) {
    verseContainer.textContent = 'Failed to load books.';
    console.error(err);
  }
}

async function fetchChapters(bibleId, bookId) {
  try {
    const res = await fetch(`${BASE_URL}/bibles/${bibleId}/books/${bookId}/chapters`, {
      headers: { 'api-key': API_KEY }
    });
    const data = await res.json();
    if (!res.ok) throw new Error('Failed to fetch chapters');

    chapterSelect.innerHTML = '';
    data.data.forEach(chapter => {
      const opt = document.createElement('option');
      opt.value = chapter.id; // API chapter id
      opt.textContent = chapter.number;
      chapterSelect.appendChild(opt);
    });

    if (data.data.length) {
      fetchVerses(bibleId, data.data[0].id);
    }
  } catch (err) {
    verseContainer.textContent = 'Failed to load chapters.';
    console.error(err);
  }
}

async function fetchVerses(bibleId, chapterId) {
  try {
    verseContainer.innerHTML = 'Loading verses...';
    const res = await fetch(`${BASE_URL}/bibles/${bibleId}/chapters/${chapterId}/verses`, {
      headers: { 'api-key': API_KEY }
    });
    const data = await res.json();
    if (!res.ok) throw new Error('Failed to fetch verses');

    verseContainer.innerHTML = '';
    data.data.forEach(verse => {
      const verseDiv = document.createElement('div');
      verseDiv.classList.add('verse');
      verseDiv.innerHTML = `<sup>${verse.verse}</sup> ${verse.content}`;
      verseContainer.appendChild(verseDiv);
    });
  } catch (err) {
    verseContainer.textContent = 'Failed to load verses.';
    console.error(err);
  }
}

// Event listeners for book & chapter selects
bookSelect.addEventListener('change', () => {
  const bookId = bookSelect.value;
  if (kjvBibleId && bookId) {
    fetchChapters(kjvBibleId, bookId);
  }
});

chapterSelect.addEventListener('change', () => {
  const chapterId = chapterSelect.value;
  if (kjvBibleId && chapterId) {
    fetchVerses(kjvBibleId, chapterId);
  }
});

// Initial load
fetchKJVBible();