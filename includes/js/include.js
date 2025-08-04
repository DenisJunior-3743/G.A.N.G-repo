// includes.js
document.addEventListener('DOMContentLoaded', () => {
  // Load Header
  fetch('/G.A.N.G/includes/header.htm')
    .then(res => res.text())
    .then(data => {
      document.getElementById('header').innerHTML = data;
    })
    .catch(err => console.error('Failed to load header:', err));

  // Load Footer
  fetch('/G.A.N.G/includes/footer.htm')
    .then(res => res.text())
    .then(data => {
      document.getElementById('footer').innerHTML = data;
    })
    .catch(err => console.error('Failed to load footer:', err));
});
