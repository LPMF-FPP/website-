// Safe theme toggle - non-invasive approach
// Only sets attributes on <html>, no content/markup changes
(function () {
  const doc = document.documentElement;

  // Activate safe scope
  if (!doc.hasAttribute('data-pd-safe')) doc.setAttribute('data-pd-safe', '');

  // Theme: system default
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const saved = localStorage.getItem('pd-theme');
  const theme = saved || (prefersDark ? 'dark' : 'light');
  setTheme(theme);

  // Optional: expose API for toggle buttons (if any)
  window.pdTheme = {
    set: setTheme,
    toggle() { setTheme(doc.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); },
    system() { localStorage.removeItem('pd-theme'); setTheme(prefersDark ? 'dark' : 'light'); }
  };

  // Sync with OS changes
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
    if (!localStorage.getItem('pd-theme')) setTheme(e.matches ? 'dark' : 'light');
  });

  function setTheme(next) {
    doc.setAttribute('data-theme', next);
    localStorage.setItem('pd-theme', next);
  }
})();
