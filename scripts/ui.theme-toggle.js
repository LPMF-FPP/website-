/*
 Pusdokkes UI Theme Toggle (scoped)
 - Scope: Only affects pages where html[data-ui] exists
 - Behavior: Persist theme in localStorage('ui-theme')
*/
(function(){
  const root = document.documentElement;
  if (!root.hasAttribute('data-ui')) return; // not opted-in

  const STORAGE_KEY = 'ui-theme';
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;

  // Initialize
  const saved = localStorage.getItem(STORAGE_KEY);
  const theme = saved || (prefersDark ? 'dark' : 'light');
  root.dataset.theme = theme;

  // Provide a simple API
  window.PusdokkesUI = window.PusdokkesUI || {};
  window.PusdokkesUI.setTheme = (t) => {
    root.dataset.theme = t;
    localStorage.setItem(STORAGE_KEY, t);
  };
  window.PusdokkesUI.toggleTheme = () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    window.PusdokkesUI.setTheme(next);
  };
})();
