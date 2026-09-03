// Auth pages - password visibility toggle (ported from Orchid's auth.js, trimmed to what this app uses)
(function () {
  'use strict';

  document.querySelectorAll('[data-auth-password-toggle]').forEach((btn) => {
    const targetId = btn.getAttribute('data-auth-password-toggle');
    const input = document.getElementById(targetId);
    if (!input) return;
    btn.addEventListener('click', () => {
      const isPwd = input.type === 'password';
      input.type = isPwd ? 'text' : 'password';
      const icon = btn.querySelector('i');
      if (icon) {
        icon.classList.toggle('bi-eye', !isPwd);
        icon.classList.toggle('bi-eye-slash', isPwd);
      }
      btn.setAttribute('aria-label', isPwd ? 'Hide password' : 'Show password');
    });
  });
})();
