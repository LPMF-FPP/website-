/**
 * Pusdokkes Design System - Theme Toggle (Safe Mode v2)
 * Non-invasive theme switching - only sets attributes, no DOM manipulation
 */
(function () {
  'use strict';

  const doc = document.documentElement;

  // Ensure data-pd-safe attribute exists
  if (!doc.hasAttribute('data-pd-safe')) {
    doc.setAttribute('data-pd-safe', '');
  }

  // Detect system preference
  const prefersDark = matchMedia('(prefers-color-scheme: dark)').matches;
  const saved = localStorage.getItem('pd-theme');

  // Apply initial theme
  set(saved || (prefersDark ? 'dark' : 'light'));

  // Expose public API
  window.pdTheme = {
    set: set,
    toggle: function () {
      set(doc.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    },
    system: function () {
      localStorage.removeItem('pd-theme');
      set(matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    },
    get: function () {
      return doc.getAttribute('data-theme');
    }
  };

  // Watch for system preference changes
  matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
    if (!localStorage.getItem('pd-theme')) {
      set(e.matches ? 'dark' : 'light');
    }
  });

  /**
   * Set theme - only updates attribute
   * @param {string} theme - 'light' or 'dark'
   */
  function set(theme) {
    doc.setAttribute('data-theme', theme);
    localStorage.setItem('pd-theme', theme);

    // Update meta theme-color for mobile browsers (non-invasive)
    updateMetaThemeColor(theme);

    // Dispatch event for other scripts
    window.dispatchEvent(new CustomEvent('pd-theme-change', {
      detail: { theme: theme }
    }));
  }

  /**
   * Update meta theme-color tag
   * @param {string} theme - Current theme
   */
  function updateMetaThemeColor(theme) {
    let meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.setAttribute('name', 'theme-color');
      document.head.appendChild(meta);
    }
    meta.setAttribute('content', theme === 'dark' ? '#0b0c0f' : '#ffffff');
  }

  // Setup toggle buttons if present
  document.addEventListener('DOMContentLoaded', function () {
    const toggles = document.querySelectorAll('[data-pd-theme-toggle]');
    toggles.forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        window.pdTheme.toggle();
      });

      // Keyboard accessibility
      toggle.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          window.pdTheme.toggle();
        }
      });

      // Update aria-label
      updateToggleLabel(toggle);
    });

    // Listen for theme changes to update labels
    window.addEventListener('pd-theme-change', function () {
      toggles.forEach(updateToggleLabel);
    });
  });

  /**
   * Update toggle button aria-label
   * @param {HTMLElement} toggle - Toggle button element
   */
  function updateToggleLabel(toggle) {
    const theme = doc.getAttribute('data-theme');
    toggle.setAttribute('aria-label', 'Toggle theme (current: ' + theme + ')');
  }
})();
