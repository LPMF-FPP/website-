/**
 * Theme Toggle System
 * Handles Light/Dark/System theme switching with localStorage persistence
 * Supports keyboard navigation and accessibility
 */

(function() {
  'use strict';

  // Theme constants
  const THEMES = {
    LIGHT: 'light',
    DARK: 'dark',
    SYSTEM: 'system'
  };

  const STORAGE_KEY = 'pd-theme-preference';
  const ATTRIBUTE_NAME = 'data-theme';

  // Theme manager class
  class ThemeManager {
    constructor() {
      this.currentTheme = this.getStoredTheme() || THEMES.SYSTEM;
      this.systemTheme = this.getSystemTheme();
      this.mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

      this.init();
    }

    init() {
      // Apply initial theme
      this.applyTheme(this.currentTheme);

      // Listen for system theme changes
      this.mediaQuery.addEventListener('change', (e) => {
        this.systemTheme = e.matches ? THEMES.DARK : THEMES.LIGHT;
        if (this.currentTheme === THEMES.SYSTEM) {
          this.applyTheme(THEMES.SYSTEM);
        }
        this.updateToggleButtons();
      });

      // Initialize toggle buttons
      this.initToggleButtons();

      // Announce theme to screen readers
      this.announceTheme();
    }

    getSystemTheme() {
      return this.mediaQuery.matches ? THEMES.DARK : THEMES.LIGHT;
    }

    getStoredTheme() {
      try {
        return localStorage.getItem(STORAGE_KEY);
      } catch (e) {
        console.warn('localStorage not available, using system theme');
        return null;
      }
    }

    setStoredTheme(theme) {
      try {
        localStorage.setItem(STORAGE_KEY, theme);
      } catch (e) {
        console.warn('Could not save theme preference');
      }
    }

    applyTheme(theme) {
      const html = document.documentElement;

      // Remove existing theme attributes
      html.removeAttribute(ATTRIBUTE_NAME);

      if (theme === THEMES.SYSTEM) {
        // Let CSS handle system theme via media query
        html.setAttribute(ATTRIBUTE_NAME, THEMES.SYSTEM);
      } else {
        // Apply specific theme
        html.setAttribute(ATTRIBUTE_NAME, theme);
      }

      // Update meta theme-color for mobile browsers
      this.updateMetaThemeColor(theme);

      // Trigger custom event for other scripts
      this.dispatchThemeChangeEvent(theme);
    }

    updateMetaThemeColor(theme) {
      let metaThemeColor = document.querySelector('meta[name="theme-color"]');
      if (!metaThemeColor) {
        metaThemeColor = document.createElement('meta');
        metaThemeColor.name = 'theme-color';
        document.head.appendChild(metaThemeColor);
      }

      // Get computed CSS variable values
      const style = getComputedStyle(document.documentElement);
      const effectiveTheme = theme === THEMES.SYSTEM ? this.systemTheme : theme;

      // Apply theme first to get correct color
      this.applyTheme(theme);

      // Get the computed surface color
      const surfaceColor = style.getPropertyValue('--pd-color-surface').trim();
      metaThemeColor.content = surfaceColor || (effectiveTheme === THEMES.DARK ? '#1e293b' : '#f8fafc');
    }

    dispatchThemeChangeEvent(theme) {
      const event = new CustomEvent('themechange', {
        detail: {
          theme: theme,
          effectiveTheme: theme === THEMES.SYSTEM ? this.systemTheme : theme
        }
      });
      document.dispatchEvent(event);
    }

    setTheme(theme) {
      if (!Object.values(THEMES).includes(theme)) {
        console.warn(`Invalid theme: ${theme}`);
        return;
      }

      this.currentTheme = theme;
      this.setStoredTheme(theme);
      this.applyTheme(theme);
      this.updateToggleButtons();
      this.announceTheme();
    }

    getEffectiveTheme() {
      return this.currentTheme === THEMES.SYSTEM ? this.systemTheme : this.currentTheme;
    }

    initToggleButtons() {
      // Initialize dropdown toggle
      this.initDropdownToggle();

      // Initialize simple toggle buttons
      this.initSimpleToggle();

      // Initialize individual theme buttons
      this.initThemeButtons();

      // Update button states
      this.updateToggleButtons();
    }

    initDropdownToggle() {
      const dropdownToggle = document.querySelector('[data-theme-dropdown]');
      if (!dropdownToggle) return;

      const dropdown = dropdownToggle.nextElementSibling;
      if (!dropdown) return;

      // Toggle dropdown
      dropdownToggle.addEventListener('click', (e) => {
        e.preventDefault();
        const isOpen = dropdown.style.display === 'block';
        dropdown.style.display = isOpen ? 'none' : 'block';
        dropdownToggle.setAttribute('aria-expanded', !isOpen);
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', (e) => {
        if (!dropdownToggle.contains(e.target) && !dropdown.contains(e.target)) {
          dropdown.style.display = 'none';
          dropdownToggle.setAttribute('aria-expanded', 'false');
        }
      });

      // Keyboard navigation
      dropdownToggle.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          dropdownToggle.click();
        }
      });

      // Theme selection
      dropdown.addEventListener('click', (e) => {
        if (e.target.dataset.theme) {
          e.preventDefault();
          this.setTheme(e.target.dataset.theme);
          dropdown.style.display = 'none';
          dropdownToggle.setAttribute('aria-expanded', 'false');
          dropdownToggle.focus();
        }
      });
    }

    initSimpleToggle() {
      const toggleButton = document.querySelector('[data-theme-toggle]');
      if (!toggleButton) return;

      toggleButton.addEventListener('click', (e) => {
        e.preventDefault();
        const currentEffective = this.getEffectiveTheme();
        const nextTheme = currentEffective === THEMES.LIGHT ? THEMES.DARK : THEMES.LIGHT;
        this.setTheme(nextTheme);
      });

      toggleButton.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          toggleButton.click();
        }
      });
    }

    initThemeButtons() {
      const themeButtons = document.querySelectorAll('[data-theme]');

      themeButtons.forEach(button => {
        button.addEventListener('click', (e) => {
          e.preventDefault();
          const theme = button.dataset.theme;
          this.setTheme(theme);
        });

        button.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            button.click();
          }
        });
      });
    }

    updateToggleButtons() {
      // Update dropdown toggle text
      const dropdownToggle = document.querySelector('[data-theme-dropdown]');
      if (dropdownToggle) {
        const themeLabels = {
          [THEMES.LIGHT]: '☀️ Light',
          [THEMES.DARK]: '🌙 Dark',
          [THEMES.SYSTEM]: '💻 System'
        };

        const currentLabel = themeLabels[this.currentTheme] || themeLabels[THEMES.SYSTEM];
        dropdownToggle.textContent = currentLabel;
        dropdownToggle.setAttribute('aria-label', `Current theme: ${currentLabel}. Click to change theme.`);
      }

      // Update simple toggle
      const toggleButton = document.querySelector('[data-theme-toggle]');
      if (toggleButton) {
        const effectiveTheme = this.getEffectiveTheme();
        const nextTheme = effectiveTheme === THEMES.LIGHT ? THEMES.DARK : THEMES.LIGHT;
        const nextIcon = nextTheme === THEMES.DARK ? '🌙' : '☀️';
        const nextLabel = nextTheme === THEMES.DARK ? 'Dark' : 'Light';

        toggleButton.innerHTML = nextIcon;
        toggleButton.setAttribute('aria-label', `Switch to ${nextLabel} theme`);
        toggleButton.setAttribute('title', `Switch to ${nextLabel} theme`);
      }

      // Update individual theme buttons
      const themeButtons = document.querySelectorAll('[data-theme]');
      themeButtons.forEach(button => {
        const isActive = button.dataset.theme === this.currentTheme;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-pressed', isActive);

        if (isActive) {
          button.setAttribute('aria-current', 'true');
        } else {
          button.removeAttribute('aria-current');
        }
      });
    }

    announceTheme() {
      // Create or update live region for screen readers
      let liveRegion = document.querySelector('#theme-announcement');
      if (!liveRegion) {
        liveRegion = document.createElement('div');
        liveRegion.id = 'theme-announcement';
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.className = 'sr-only';
        document.body.appendChild(liveRegion);
      }

      const effectiveTheme = this.getEffectiveTheme();
      const themeLabels = {
        [THEMES.LIGHT]: 'light theme',
        [THEMES.DARK]: 'dark theme'
      };

      const announcement = this.currentTheme === THEMES.SYSTEM
        ? `System theme active. Currently using ${themeLabels[effectiveTheme]}.`
        : `${themeLabels[effectiveTheme]} activated.`;

      liveRegion.textContent = announcement;

      // Clear announcement after a delay
      setTimeout(() => {
        liveRegion.textContent = '';
      }, 1000);
    }

    // Public API
    getCurrentTheme() {
      return this.currentTheme;
    }

    getEffectiveThemeValue() {
      return this.getEffectiveTheme();
    }

    isSystemTheme() {
      return this.currentTheme === THEMES.SYSTEM;
    }

    toggle() {
      const currentEffective = this.getEffectiveTheme();
      const nextTheme = currentEffective === THEMES.LIGHT ? THEMES.DARK : THEMES.LIGHT;
      this.setTheme(nextTheme);
    }

    reset() {
      this.setTheme(THEMES.SYSTEM);
    }
  }

  // Auto-initialize when DOM is ready
  function initThemeSystem() {
    // Create global theme manager instance
    window.themeManager = new ThemeManager();

    // Add theme toggle styles if they don't exist
    addToggleStyles();

    // Create default toggle if none exists
    createDefaultToggle();

    // Expose public API
    window.setTheme = (theme) => window.themeManager.setTheme(theme);
    window.toggleTheme = () => window.themeManager.toggle();
    window.getCurrentTheme = () => window.themeManager.getCurrentTheme();
    window.getEffectiveTheme = () => window.themeManager.getEffectiveThemeValue();
  }

  function addToggleStyles() {
    if (document.querySelector('#pd-theme-toggle-styles')) return;

    const styles = document.createElement('style');
    styles.id = 'pd-theme-toggle-styles';
    styles.textContent = `
      .pd-theme-toggle {
        background: var(--pd-color-surface);
        border: 1px solid var(--pd-color-border);
        border-radius: var(--pd-radius-md);
        padding: var(--pd-spacing-2);
        cursor: pointer;
        font-size: 1.25rem;
        color: var(--pd-color-text);
        transition: all var(--pd-motion-fast) var(--pd-motion-easing);
        position: relative;
      }

      .pd-theme-toggle:hover {
        background: var(--pd-color-border);
        transform: scale(1.05);
      }

      .pd-theme-toggle:focus {
        outline: 2px solid var(--pd-color-primary);
        outline-offset: 2px;
      }

      .pd-theme-dropdown {
        position: relative;
        display: inline-block;
      }

      .pd-theme-dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: var(--pd-color-surface);
        border: 1px solid var(--pd-color-border);
        border-radius: var(--pd-radius-md);
        box-shadow: var(--pd-shadow-lg);
        min-width: 120px;
        z-index: var(--pd-z-overlay);
        display: none;
      }

      .pd-theme-dropdown-item {
        display: block;
        width: 100%;
        text-align: left;
        padding: var(--pd-spacing-3);
        border: none;
        background: none;
        color: var(--pd-color-text);
        cursor: pointer;
        transition: background-color var(--pd-motion-fast) var(--pd-motion-easing);
      }

      .pd-theme-dropdown-item:hover {
        background: var(--pd-color-border);
      }

      .pd-theme-dropdown-item.active {
        background: rgba(var(--pd-color-primary-rgb), 0.1);
        color: var(--pd-color-primary);
        font-weight: 500;
      }

      .pd-theme-buttons {
        display: inline-flex;
        border: 1px solid var(--pd-color-border);
        border-radius: var(--pd-radius-md);
        overflow: hidden;
      }

      .pd-theme-button {
        padding: var(--pd-spacing-2) var(--pd-spacing-3);
        background: var(--pd-color-surface);
        border: none;
        color: var(--pd-color-text);
        cursor: pointer;
        transition: all var(--pd-motion-fast) var(--pd-motion-easing);
        font-size: 0.875rem;
      }

      .pd-theme-button:hover {
        background: var(--pd-color-border);
      }

      .pd-theme-button.active {
        background: var(--pd-color-primary);
        color: white;
      }

      .pd-theme-button:not(:last-child) {
        border-right: 1px solid var(--pd-color-border);
      }
    `;
    document.head.appendChild(styles);
  }

  function createDefaultToggle() {
    // Only create if no theme controls exist
    if (document.querySelector('[data-theme], [data-theme-toggle], [data-theme-dropdown]')) {
      return;
    }

    const toggle = document.createElement('button');
    toggle.className = 'pd-theme-toggle';
    toggle.setAttribute('data-theme-toggle', '');
    toggle.setAttribute('aria-label', 'Toggle theme');
    toggle.innerHTML = '🌙';
    toggle.style.position = 'fixed';
    toggle.style.top = '20px';
    toggle.style.right = '20px';
    toggle.style.zIndex = 'var(--pd-z-nav)';

    document.body.appendChild(toggle);
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeSystem);
  } else {
    initThemeSystem();
  }

  // Expose theme constants
  window.THEMES = THEMES;

})();
