// Sidebar (mobile off-canvas + desktop collapse), ported from Orchid's sidebar.js.
// Trimmed: the active-nav-link JS from the original demo is dropped — this app
// does real server-rendered navigation, so the active link is computed in
// partials/sidebar.php from the current route, not re-marked on click.
(function () {
  'use strict';

  const MOBILE_BP = 992;
  const COLLAPSED_KEY = 'orchid-sidebar-collapsed';

  const sidebar = document.getElementById('orchidSidebar');
  const backdrop = document.querySelector('.orchid-backdrop');
  const body = document.body;

  const isMobile = () => window.innerWidth < MOBILE_BP;

  const openMobile = () => {
    sidebar?.classList.add('is-open');
    backdrop?.classList.add('is-visible');
    document.querySelectorAll('[data-orchid-sidebar-toggle]').forEach((t) => t.setAttribute('aria-expanded', 'true'));
  };

  const closeMobile = () => {
    sidebar?.classList.remove('is-open');
    backdrop?.classList.remove('is-visible');
    document.querySelectorAll('[data-orchid-sidebar-toggle]').forEach((t) => t.setAttribute('aria-expanded', 'false'));
  };

  const toggleDesktopCollapse = () => {
    body.classList.toggle('orchid-sidebar-collapsed');
    localStorage.setItem(COLLAPSED_KEY, body.classList.contains('orchid-sidebar-collapsed') ? '1' : '0');
  };

  if (localStorage.getItem(COLLAPSED_KEY) === '1') {
    body.classList.add('orchid-sidebar-collapsed');
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-orchid-sidebar-toggle]').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (isMobile()) {
          sidebar?.classList.contains('is-open') ? closeMobile() : openMobile();
        } else {
          toggleDesktopCollapse();
        }
      });
    });

    document.querySelectorAll('[data-orchid-sidebar-close]').forEach((el) => {
      el.addEventListener('click', closeMobile);
    });

    backdrop?.addEventListener('click', closeMobile);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sidebar?.classList.contains('is-open')) closeMobile();
    });

    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => {
        if (!isMobile()) closeMobile();
      }, 120);
    });
  });
})();
