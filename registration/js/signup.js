$(document).ready(function () {
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
      return el.length && el.val().trim().length > 0;
    });
    $('#submitBtn').toggle(allFilled);
  }

  $('input, select').on('input change', checkFields);

$('#signupForm').on('submit', function (e) {
  e.preventDefault();
  
  const formData = new FormData(this);

  $.ajax({
    url: '/G.A.N.G/registration/php/signup.php',
    type: 'POST',
    data: formData,
    dataType: 'json',
    contentType: false,
    processData: false,
    success: function (response) {
      const msgClass = response.status === 'success' ? 'alert-success' : 'alert-danger';
      $('#responseMsg')
        .removeClass('d-none alert-success alert-danger')
        .addClass(msgClass)
        .text(response.message)
        .fadeIn();

      if (response.status === 'success') {
        $('#signupForm')[0].reset();
        $('#submitBtn').hide();
      }

      setTimeout(() => {
        $('#responseMsg').fadeOut();
      }, 5000);
    },
    error: function () {
      $('#responseMsg')
        .removeClass('d-none alert-success')
        .addClass('alert-danger')
        .text('Network error. Please try again.')
        .fadeIn();

      setTimeout(() => {
        $('#responseMsg').fadeOut();
      }, 5000);
    }
  });
});
});