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

  // ---------- AJAX login ----------
  const form = document.getElementById('loginForm');
  if (!form) return;

  const alertBox = document.getElementById('loginAlert');
  const submitBtn = document.getElementById('loginSubmitBtn');
  const fields = {
    email: { input: document.getElementById('loginEmail'), error: document.getElementById('loginEmailError') },
    password: { input: document.getElementById('loginPassword'), error: document.getElementById('loginPasswordError') },
  };

  const hideAlert = () => alertBox.classList.add('d-none');
  const showAlert = (message) => {
    alertBox.textContent = message;
    alertBox.classList.remove('d-none');
  };

  const clearFieldErrors = () => {
    Object.values(fields).forEach(({ input, error }) => {
      input.classList.remove('is-invalid');
      error.textContent = '';
    });
  };

  const showFieldErrors = (errors) => {
    Object.entries(errors).forEach(([name, message]) => {
      const field = fields[name];
      if (!field) return;
      field.input.classList.add('is-invalid');
      field.error.textContent = message;
    });
  };

  const setLoading = (isLoading) => {
    submitBtn.disabled = isLoading;
    submitBtn.querySelector('.spinner-border').classList.toggle('d-none', !isLoading);
    submitBtn.querySelector('.auth-btn-primary__label').textContent = isLoading ? 'Signing in…' : 'Sign in';
  };

  const validateClientSide = () => {
    const errors = {};
    const email = fields.email.input.value.trim();
    const password = fields.password.input.value;

    if (!email) {
      errors.email = 'Email is required.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errors.email = 'Enter a valid email address.';
    }

    if (!password) {
      errors.password = 'Password is required.';
    } else if (password.length < 8) {
      errors.password = 'Password must be at least 8 characters.';
    }

    return errors;
  };

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();
    clearFieldErrors();

    const clientErrors = validateClientSide();
    if (Object.keys(clientErrors).length > 0) {
      showFieldErrors(clientErrors);
      return;
    }

    setLoading(true);

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: new FormData(form),
      });
      const data = await response.json();

      // The CSRF token rotates every request — carry the fresh one forward
      // so a second attempt after a failed one doesn't get rejected.
      if (data.csrfName && data.csrfHash) {
        const csrfField = document.getElementById('csrfTokenField');
        csrfField.name = data.csrfName;
        csrfField.value = data.csrfHash;
      }

      if (data.success) {
        window.location.href = data.redirect || '/dashboard';
        return;
      }

      if (data.errors) {
        showFieldErrors(data.errors);
      } else if (data.message) {
        showAlert(data.message);
      }
    } catch (err) {
      showAlert('Something went wrong. Please check your connection and try again.');
    } finally {
      setLoading(false);
    }
  });
})();
