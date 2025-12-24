# Pusdokkes Scoped UI Kit

A non-breaking, opt-in UI layer for Pusdokkes. It ships CSS tokens, minimal components, and layout utilities that only apply when you opt-in via `data-ui` on the `<html>` element. No global resets. Safe to include alongside your existing styling.

## Files

- `styles/ui.tokens.css` — Design tokens (colors, spacing, radii, shadows, motion, fonts) scoped to `[data-ui]`.
- `styles/ui.minimal.css` — Minimal components (`.ui-btn`, `.ui-card`, `.ui-input`, `.ui-badge`) and base helpers (links, muted text), all scoped.
- `styles/ui.layout.css` — Optional layout utilities (`.ui-shell`, `.ui-header`, `.ui-main`, `.ui-footer`, `.ui-container`, grid helpers).
- `scripts/ui.theme-toggle.js` — A tiny theme toggle that persists `data-theme` (light/dark) when `[data-ui]` is present.
- `public/styleguide.html` — Demo page to preview components without touching the app.

## Activation (page-level)

1. Add the link tags on the page where you want to opt-in (Blade layout or specific view):

```html
<html lang="id" data-ui="minimal" data-theme="light">
  <head>
    <link rel="stylesheet" href="/styles/ui.tokens.css">
    <link rel="stylesheet" href="/styles/ui.minimal.css">
    <link rel="stylesheet" href="/styles/ui.layout.css">
  </head>
  <body>
    <!-- your content -->
    <script src="/scripts/ui.theme-toggle.js" type="module"></script>
  </body>
</html>
```

- Remove `data-theme` to let the script pick the user preference.
- Omit `ui.layout.css` if you don't need the shell/header/footer utilities.

## Usage examples

Buttons:
```html
<button class="ui-btn">Default</button>
<button class="ui-btn ui-btn--primary">Primary</button>
<button class="ui-btn ui-btn--ghost">Ghost</button>
```

Card:
```html
<div class="ui-card">
  <h3>Ringkasan</h3>
  <p class="ui-text-muted">Teks sekunder.</p>
</div>
```

Input:
```html
<input class="ui-input" placeholder="Cari...">
```

Badge:
```html
<span class="ui-badge ui-badge--success">Selesai</span>
```

Layout shell:
```html
<div class="ui-shell">
  <header class="ui-header">
    <div class="ui-container">Header</div>
  </header>
  <main class="ui-main ui-container">
    <section class="ui-section">
      <h2 class="ui-section__title">Judul</h2>
      <p class="ui-section__sub">Subjudul</p>
    </section>
  </main>
  <footer class="ui-footer">
    <div class="ui-container">Footer</div>
  </footer>
</div>
```

## Non-breaking guarantees

- Scoped: Everything is under `:where(html[data-ui])` and `.ui-*` classes.
- No element resets or global typography changes.
- Can be removed by deleting the three `<link>` tags; no CSS stays active elsewhere.

## Theming

- Use `data-theme="light"` or `data-theme="dark"` on `<html data-ui>`.
- Or include `scripts/ui.theme-toggle.js` and call `PusdokkesUI.toggleTheme()`.

## Tailwind coexistence

- This CSS does not require Tailwind.
- If you need Tailwind to read the same tokens, map variables in `tailwind.config.js` using your existing `extend` fields (e.g., use CSS variables like `var(--ui-color-primary)` in custom plugin rules only inside `[data-ui]`).
- We intentionally avoid editing your current Tailwind config to keep changes non-destructive.

## QA and rollback

- To preview safely, open `/public/styleguide.html` in your dev server.
- Rollback = remove the three `<link>` tags and the script. No build steps affected.

## Roadmap (optional)

- Additional components (tables, tabs, toast).
- Utilities for density and spacing scale.
- Color system alignment with brand tokens.
