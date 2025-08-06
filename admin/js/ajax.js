// ajax.js
$(document).on('submit', '#assign-credentials-form', function (e) {
  e.preventDefault();

  const formData = $(this).serialize();

  $.ajax({
    url: '/G.A.N.G/admin/credentials/assign_credentials.php',
    method: 'POST',
    data: formData,
    dataType: 'json',
    timeout: 5000,
    success: function (response) {
      const msg = response.success
        ? `<div class="alert alert-success">${response.message}</div>`
        : `<div class="alert alert-danger">${response.message}</div>`;
      
      $('#assign-result').html(msg);

      if (response.success) {
        $('#assign-credentials-form')[0].reset();

        // Auto-clear message after 5 seconds
        setTimeout(() => {
          $('#assign-result').html('');
        }, 5000);
      }
    },
    error: function (xhr, status) {
      const message = status === 'timeout'
        ? 'Request timed out. Please try again.'
        : 'An error occurred while processing the request.';
      $('#assign-result').html(`<div class="alert alert-warning">${message}</div>`);

      // Optional: Clear error message too after a few seconds
      setTimeout(() => {
        $('#assign-result').html('');
      }, 5000);
    }
  });
});




  function setupPasswordToggle() {
  const togglePassword = document.getElementById('togglePassword');
  const passwordInput = document.getElementById('password');

  if (togglePassword && passwordInput) {
    togglePassword.addEventListener('click', function () {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      this.innerHTML = type === 'password' ? '<i class="fa fa-eye"></i>' : '<i class="fa fa-eye-slash"></i>';
    });
  }
}


