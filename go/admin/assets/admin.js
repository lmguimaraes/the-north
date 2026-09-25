'use strict';

document.querySelectorAll('.js-delete-form').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    var message = form.getAttribute('data-confirm') || 'Are you sure?';
    if (!window.confirm(message)) {
      event.preventDefault();
    }
  });
});
