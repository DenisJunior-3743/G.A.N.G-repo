$(function () {
  const requiredFields = [
    'input[name="first_name"]',
    'input[name="second_name"]',
    'select[name="gender"]',
    'input[name="dob"]',
    'input[name="phone"]',
    'input[name="email"]',
    'input[name="year_joined"]',
    'input[name="address"]'
  ];

  function checkFields() {
    const allFilled = requiredFields.every(selector => {
      const el = $(selector);
      if (!el.length) {
        console.warn(`Missing element: ${selector}`);
        return false;
      }
      const val = el.val();
      return typeof val === 'string' && val.trim().length > 0;
    });

    $('#submitBtn').toggle(allFilled);
  }

  $('input, select').on('input change', checkFields);

  $('#signupForm').on('submit', function (e) {
    e.preventDefault();
    
    // Show loading state
    const $submitBtn = $('#submitBtn');
    const originalText = $submitBtn.html();
    $submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Registering...').prop('disabled', true);

    const formData = new FormData(this);

    $.ajax({
      url: '/G.A.N.G/registration/php/signup.php',
      type: 'POST',
      data: formData,
      dataType: 'json',
      contentType: false,
      processData: false,
      success: function (response) {
        console.log('Server response:', response); // Debug log
        
        // Reset button
        $submitBtn.html(originalText).prop('disabled', false);
        
        const msgClass = response.status === 'success' ? 'alert-success' : 'alert-danger';
        const $responseMsg = $('#responseMsg');
        
        // Clear all previous classes and add new ones
        $responseMsg
          .removeClass('d-none alert-success alert-danger')
          .addClass(msgClass)
          .text(response.message || 'Registration completed')
          .show()
          .fadeIn();

        if (response.status === 'success') {
          $('#signupForm')[0].reset();
          $('#submitBtn').hide();
          checkFields(); // Update button state
        }

        // Auto-hide message after 5 seconds
        setTimeout(() => {
          $responseMsg.fadeOut();
        }, 5000);
      },
      error: function (xhr, status, error) {
        console.error('AJAX Error:', { xhr, status, error }); // Debug log
        
        // Reset button
        $submitBtn.html(originalText).prop('disabled', false);
        
        const $responseMsg = $('#responseMsg');
        
        $responseMsg
          .removeClass('d-none alert-success')
          .addClass('alert-danger')
          .text('Network error. Please try again.')
          .show()
          .fadeIn();

        setTimeout(() => {
          $responseMsg.fadeOut();
        }, 5000);
      }
    });
  });

  // Call it once to initialize button state
  checkFields();
});