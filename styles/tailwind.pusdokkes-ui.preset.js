// Optional Tailwind preset for Pusdokkes UI (scoped)
// Usage (opt-in only):
//   import uiPreset from './styles/tailwind.pusdokkes-ui.preset.js'
//   export default { presets: [uiPreset], ... }
// This preset adds a 'ui:' variant so you can scope utilities to html[data-ui]
// Example: <div class="ui:bg-[var(--ui-color-surface)]">
// It does not add base styles or modify your theme.

/** @type {import('tailwindcss').Config} */
export default {
  corePlugins: {},
  theme: { extend: {} },
  plugins: [
    function({ addVariant }) {
      // Apply utilities only when within an opted-in document
      // Usage: class="ui:bg-white" becomes "html[data-ui] .bg-white"
      addVariant('ui', 'html[data-ui] &');
      addVariant('ui-dark', 'html[data-ui][data-theme="dark"] &');
      addVariant('ui-light', 'html[data-ui][data-theme="light"] &');
    },
  ],
};
