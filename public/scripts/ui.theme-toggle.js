/* Copied for public serving; keep in sync with scripts/ui.theme-toggle.js */
(function(){
  const root = document.documentElement;
  if (!root.hasAttribute('data-ui')) return;
  const STORAGE_KEY = 'ui-theme';
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const saved = localStorage.getItem(STORAGE_KEY);
  const theme = saved || (prefersDark ? 'dark' : 'light');
  root.dataset.theme = theme;
  window.PusdokkesUI = window.PusdokkesUI || {};
  window.PusdokkesUI.setTheme = (t) => { root.dataset.theme = t; localStorage.setItem(STORAGE_KEY, t); };
  window.PusdokkesUI.toggleTheme = () => { const next = root.dataset.theme === 'dark' ? 'light' : 'dark'; window.PusdokkesUI.setTheme(next); };
})();
