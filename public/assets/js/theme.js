// =====================================================
// Orchid - Theme Manager (light/dark, persisted)
// =====================================================

const STORAGE_KEY = 'orchid-theme';
const root = document.documentElement;

const getStoredTheme = () => localStorage.getItem(STORAGE_KEY);
const getPreferredTheme = () => {
  const stored = getStoredTheme();
  if (stored) return stored;
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const applyTheme = (theme) => {
  root.setAttribute('data-bs-theme', theme);
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.setAttribute('content', theme === 'dark' ? '#0f1220' : '#4f46e5');
  document.dispatchEvent(new CustomEvent('orchid:themechange', { detail: { theme } }));
};

const setTheme = (theme) => {
  applyTheme(theme);
  localStorage.setItem(STORAGE_KEY, theme);
};

// Init early to avoid FOUC
applyTheme(getPreferredTheme());

document.addEventListener('DOMContentLoaded', () => {
  const toggles = document.querySelectorAll('[data-orchid-theme-toggle]');
  toggles.forEach((btn) => {
    btn.addEventListener('click', () => {
      const current = root.getAttribute('data-bs-theme') || 'light';
      setTheme(current === 'dark' ? 'light' : 'dark');
    });
  });

  // Respond to OS-level changes when the user hasn't explicitly chosen
  const mq = window.matchMedia('(prefers-color-scheme: dark)');
  mq.addEventListener('change', (e) => {
    if (!getStoredTheme()) applyTheme(e.matches ? 'dark' : 'light');
  });
});

