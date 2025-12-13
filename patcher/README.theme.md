# Pusdokkes Design System Integration Guide

This guide explains how to integrate the Pusdokkes Design System theme into your Laravel application without modifying existing markup or backend code.

## 📋 Overview

The Pusdokkes Design System provides:
- **Consistent styling** inspired by official Pusdokkes sites
- **Light/Dark/System theme support** with smooth transitions
- **Accessibility compliance** (WCAG AA standards)
- **Zero-breaking integration** - works as overlay system
- **Tailwind CSS integration** for utility-first development

## 🚀 Quick Start

### 1. CSS Integration

Add these lines to your main layout file (e.g., `resources/views/layouts/app.blade.php`):

```html
<!-- Add to <head> section in correct order -->
<link rel="stylesheet" href="{{ asset('styles/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('styles/base.css') }}">
<link rel="stylesheet" href="{{ asset('styles/components.css') }}">
<link rel="stylesheet" href="{{ asset('styles/a11y.css') }}">

<!-- Add before closing </body> tag -->
<script src="{{ asset('scripts/theme-toggle.js') }}" defer></script>
```

### 2. Theme Toggle (Optional)

Add a theme toggle to your navigation:

```html
<!-- Simple toggle button -->
<button data-theme-toggle aria-label="Toggle theme">🌙</button>

<!-- Or dropdown with all options -->
<div class="pd-theme-dropdown">
    <button data-theme-dropdown aria-expanded="false">
        💻 System
    </button>
    <div class="pd-theme-dropdown-menu">
        <button class="pd-theme-dropdown-item" data-theme="light">☀️ Light</button>
        <button class="pd-theme-dropdown-item" data-theme="dark">🌙 Dark</button>
        <button class="pd-theme-dropdown-item" data-theme="system">💻 System</button>
    </div>
</div>

<!-- Or individual buttons -->
<div class="pd-theme-buttons">
    <button class="pd-theme-button" data-theme="light">☀️</button>
    <button class="pd-theme-button" data-theme="dark">🌙</button>
    <button class="pd-theme-button" data-theme="system">💻</button>
</div>
```

### 3. Skip Links (Accessibility)

Add skip links at the very beginning of your `<body>`:

```html
<div class="skip-links">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <a href="#navigation" class="skip-link">Skip to navigation</a>
    <a href="#footer" class="skip-link">Skip to footer</a>
</div>
```

## 🎨 Using Design System Classes

### Component Classes

Apply these classes to existing elements without changing markup:

```html
<!-- Cards -->
<div class="pd-card">
    <div class="pd-card-header">
        <h3 class="pd-card-title">Card Title</h3>
        <p class="pd-card-description">Card description</p>
    </div>
    <div class="pd-card-content">
        <!-- Content -->
    </div>
    <div class="pd-card-footer">
        <button class="pd-btn pd-btn-primary">Action</button>
    </div>
</div>

<!-- Navigation -->
<nav class="pd-navbar">
    <div class="pd-navbar-container">
        <a href="/" class="pd-navbar-brand">Brand</a>
        <ul class="pd-navbar-nav">
            <li class="pd-navbar-item">
                <a href="/" class="pd-navbar-link active">Home</a>
            </li>
        </ul>
    </div>
</nav>

<!-- Hero Section -->
<section class="pd-hero">
    <div class="pd-hero-container">
        <h1 class="pd-hero-title">Hero Title</h1>
        <p class="pd-hero-subtitle">Hero subtitle</p>
        <div class="pd-hero-cta">
            <button class="pd-btn pd-btn-primary">Primary Action</button>
            <button class="pd-btn pd-btn-outline">Secondary Action</button>
        </div>
    </div>
</section>

<!-- Forms -->
<div class="pd-form-group">
    <label class="pd-form-label" for="email">Email</label>
    <input class="pd-form-input" type="email" id="email" name="email">
    <p class="pd-form-help">Help text here</p>
</div>

<!-- Alerts -->
<div class="pd-alert pd-alert-success">
    <p>Success message</p>
</div>
```

### Utility Classes

Use utility classes for quick styling:

```html
<!-- Layout -->
<div class="pd-container">
    <div class="pd-grid pd-grid-3">
        <div class="pd-card">Card 1</div>
        <div class="pd-card">Card 2</div>
        <div class="pd-card">Card 3</div>
    </div>
</div>

<!-- Spacing & Colors -->
<div class="pd-bg-surface pd-border pd-rounded pd-shadow">
    <h2 class="pd-text-primary">Primary Text</h2>
    <p class="pd-text-muted">Muted text</p>
</div>

<!-- Flexbox -->
<div class="pd-flex pd-justify-between pd-items-center pd-gap-4">
    <span>Left content</span>
    <span>Right content</span>
</div>
```

## 🎯 Tailwind CSS Integration

If using Tailwind CSS, you can use these extended utilities:

```html
<!-- Design system colors -->
<button class="bg-pd-primary text-white hover:bg-pd-secondary">
    Button with PD colors
</button>

<!-- Design system spacing -->
<div class="p-pd-4 m-pd-6 rounded-pd-lg shadow-pd-md">
    Container with PD spacing
</div>

<!-- Predefined component utilities -->
<div class="card-pd">Card with PD styling</div>
<button class="btn-pd btn-pd-primary">PD Button</button>
<input class="input-pd focus-pd" type="text">
```

## 🔧 Customization

### Updating Colors

Edit `styles/tokens.css` to customize colors:

```css
:root {
  --pd-color-primary: #your-primary-color;
  --pd-color-secondary: #your-secondary-color;
  /* ... other colors */
}

html[data-theme="dark"] {
  --pd-color-primary: #your-dark-primary-color;
  /* ... other dark colors */
}
```

### Adding Custom Components

Create new component styles in `styles/components.css`:

```css
.pd-your-component {
  background-color: var(--pd-color-surface);
  border: 1px solid var(--pd-color-border);
  border-radius: var(--pd-radius-md);
  padding: var(--pd-spacing-4);
  /* Use design tokens instead of hard-coded values */
}
```

### Regenerating Tokens

To extract fresh tokens from reference sites:

```bash
# Extract tokens from reference sites
node scripts/extract-tokens.mjs

# Build theme CSS from extracted tokens
node scripts/build-theme.mjs
```

## 🌙 Theme System

### JavaScript API

```javascript
// Set specific theme
setTheme('light');   // or 'dark', 'system'

// Toggle between light/dark
toggleTheme();

// Get current theme
const current = getCurrentTheme();  // 'light', 'dark', or 'system'

// Get effective theme (resolves 'system')
const effective = getEffectiveTheme();  // 'light' or 'dark'

// Listen for theme changes
document.addEventListener('themechange', (e) => {
    console.log('Theme changed to:', e.detail.theme);
    console.log('Effective theme:', e.detail.effectiveTheme);
});
```

### CSS Theme Detection

```css
/* Apply styles only in dark theme */
html[data-theme="dark"] .my-component {
    /* dark theme styles */
}

/* Apply styles only in light theme */
html[data-theme="light"] .my-component,
:root .my-component {
    /* light theme styles */
}

/* Responsive to system theme */
html[data-theme="system"] .my-component {
    /* Will follow system preference */
}
```

## ♿ Accessibility Features

### Built-in Features

- **Focus management**: Enhanced focus rings for keyboard navigation
- **Skip links**: Jump to main content areas
- **Screen reader support**: Announcements for theme changes
- **High contrast mode**: Automatic adjustments for better visibility
- **Reduced motion**: Respects user's motion preferences
- **ARIA attributes**: Proper labeling and states

### Best Practices

```html
<!-- Proper form labeling -->
<label for="search">Search</label>
<input id="search" type="search" aria-describedby="search-help">
<div id="search-help">Enter keywords to search</div>

<!-- Modal with proper focus management -->
<div class="pd-modal-overlay" role="dialog" aria-labelledby="modal-title" aria-hidden="false">
    <div class="pd-modal">
        <button class="pd-modal-close" aria-label="Close modal">×</button>
        <h2 id="modal-title">Modal Title</h2>
        <!-- Modal content -->
    </div>
</div>

<!-- Live region for dynamic content -->
<div aria-live="polite" aria-atomic="true" class="sr-only" id="status">
    <!-- Status messages will be announced -->
</div>
```

## 🔍 Browser Support

- **Modern browsers**: Chrome 88+, Firefox 87+, Safari 14+, Edge 88+
- **CSS Variables**: Full support in target browsers
- **CSS Grid/Flexbox**: Excellent support
- **JavaScript**: ES6+ features used with graceful degradation

## 📱 Responsive Design

The system includes responsive breakpoints:

```css
/* Mobile first approach */
.my-component {
    /* Mobile styles */
}

@media (min-width: 640px) {
    /* Tablet styles */
}

@media (min-width: 1024px) {
    /* Desktop styles */
}
```

Tailwind breakpoints are also available:
- `pd-sm`: 640px
- `pd-md`: 768px  
- `pd-lg`: 1024px
- `pd-xl`: 1280px
- `pd-2xl`: 1536px

## 🐛 Troubleshooting

### Common Issues

1. **Theme not applying**: Check CSS import order
2. **Toggle not working**: Ensure JavaScript is loaded after DOM
3. **Colors not changing**: Verify CSS variables are properly defined
4. **Accessibility issues**: Test with keyboard navigation and screen readers

### Debug Mode

Add this to check theme system status:

```javascript
console.log('Theme Manager:', window.themeManager);
console.log('Current theme:', getCurrentTheme());
console.log('Effective theme:', getEffectiveTheme());
```

## 📦 File Structure

```
styles/
├── tokens.css      # CSS variables and design tokens
├── base.css        # Reset, typography, base elements
├── components.css  # Component styles
└── a11y.css        # Accessibility enhancements

scripts/
├── extract-tokens.mjs  # Token extraction from reference sites
├── build-theme.mjs     # Theme generation
└── theme-toggle.js     # Theme switching functionality

temp/
├── raw-tokens.json     # Extracted raw tokens
└── theme-data.json     # Processed theme data
```

## 🚫 Important Restrictions

### Legal & Ethical Compliance

- **No copyrighted content**: Only styling patterns extracted, no logos/images
- **Public CSS only**: Only publicly available stylesheets processed
- **Original implementation**: All code is original, not copied
- **Respectful extraction**: Includes delays and limits requests

### Technical Limitations

- **No HTML changes required**: Works as overlay system
- **No breaking changes**: Existing functionality preserved
- **Namespace isolation**: All classes prefixed with `pd-`
- **Graceful degradation**: Works without JavaScript for core features

## 📞 Support

For questions or issues:

1. Check this documentation first
2. Inspect browser console for errors
3. Verify CSS import order and file paths
4. Test with a minimal example
5. Check accessibility with keyboard navigation

---

**Made with ❤️ for the Pusdokkes project**
