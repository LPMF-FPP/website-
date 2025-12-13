# Frontend Audit System - User Guide

## 📋 Overview

This automated audit system provides comprehensive analysis of your Laravel frontend code (CSS, JS, accessibility, performance). All audits are **read-only** and never modify your source files.

## 🚀 Quick Start

### 1. Install Dependencies

```bash
npm install
```

This installs all audit tools: Stylelint, ESLint, Puppeteer, Lighthouse CI, axe-core, PostCSS analyzers, and more.

### 2. Start Laravel Server

Most audits require your app to be running:

```bash
php artisan serve
```

Keep this running in a separate terminal. Default URL: `http://127.0.0.1:8000`

### 3. Run Audits

```bash
# Run all audits (comprehensive)
npm run audit:all

# Or run individual audits:
npm run audit:stylelint    # CSS linting
npm run audit:eslint       # JavaScript linting
npm run audit:cascade      # CSS specificity analysis
npm run audit:guard        # Overlay layout property check
npm run audit:contrast     # Color contrast & theme parity
npm run audit:zindex       # Z-index topology
npm run audit:a11y         # Accessibility scan (requires server)
npm run audit:coverage     # Unused CSS detection (requires server)

# Quick critical checks only:
npm run audit:critical
```

## 📊 Reports Generated

All reports are saved to `report/` directory:

| Report | Format | Description |
|--------|--------|-------------|
| `axe.md` | Markdown | Accessibility violations (WCAG) |
| `axe.json` | JSON | Detailed a11y data |
| `coverage-css.md` | Markdown | Unused CSS analysis |
| `coverage-css.json` | JSON | Coverage data per file |
| `cascade-map.md` | Markdown | Specificity & cascade issues |
| `cascade-map.json` | JSON | Detailed CSS rule analysis |
| `nonlayout-violations.md` | Markdown | Layout property violations in overlays |
| `nonlayout-violations.json` | JSON | Guard violations data |
| `contrast.md` | Markdown | Color contrast & theme parity |
| `contrast.json` | JSON | Color palette data |
| `zindex-map.md` | Markdown | Z-index stacking analysis |
| `zindex-map.json` | JSON | Z-index topology data |
| `lighthouse/` | HTML/JSON | Lighthouse performance reports |

## 🔍 Audit Details

### 1. Stylelint (`audit:stylelint`)

**What it checks:**
- Global aggressive selectors that modify layout
- Excessive specificity (>0,4,0)
- !important abuse
- Low-performance animations (width/height transitions)
- Browser compatibility issues

**Config:** `.stylelintrc.cjs`

**Output:** Console + individual file errors

**Fix failures:**
```bash
# See violations
npm run audit:stylelint

# Auto-fix some issues (be careful!)
npx stylelint "styles/**/*.css" --fix
```

### 2. ESLint (`audit:eslint`)

**What it checks:**
- DOM thrashing patterns (layout read-write loops)
- Event listener leaks
- Security issues (eval, innerHTML)
- Import order and duplicates
- Vue/React/JSX accessibility (if applicable)

**Config:** `.eslintrc.cjs`

**Output:** Console with file/line numbers

**Fix failures:**
```bash
# See violations
npm run audit:eslint

# Auto-fix some issues
npx eslint "resources/js/**/*.js" --fix
```

### 3. CSS Cascade Analysis (`audit:cascade`)

**What it checks:**
- High specificity selectors (hard to override)
- Deep nesting (>4 levels)
- ID selectors (prefer classes)
- Property conflicts (same property, different values)
- Layout properties in overlay files (pd-*.css)
- CSS variables without fallbacks
- Inconsistent @layer usage

**Output:** `report/cascade-map.md` + JSON

**Severity levels:**
- **Critical**: Layout in overlay, must fix immediately
- **Major**: High specificity, !important abuse
- **Minor**: ID selectors, deep nesting

### 4. Non-Layout Guard (`audit:guard`)

**What it checks:**
- Overlay files (pd-*.css) for layout-modifying properties
- Validates Safe Mode v2 compliance

**Forbidden properties:**
- display, position, width, height, margin, padding
- flex*, grid*, transform, overflow
- All box-model and positioning properties

**Allowed properties:**
- color, background-color, border-color, border-radius
- box-shadow, opacity, outline, filter
- transition, animation

**Output:** `report/nonlayout-violations.md` + JSON

**Critical:** This audit **fails the build** if violations found!

### 5. Color Contrast (`audit:contrast`)

**What it checks:**
- WCAG 2.1 AA contrast ratios (4.5:1 minimum)
- Theme parity (variables present in both light/dark)
- Missing fallback values for CSS variables

**Output:** `report/contrast.md` + JSON

**WCAG Requirements:**
- Normal text: 4.5:1
- Large text (18px+): 3.0:1

**Fix low contrast:**
1. Check report for failing pairs
2. Adjust colors in theme tokens
3. Re-run audit to verify

### 6. Z-Index Topology (`audit:zindex`)

**What it checks:**
- All z-index declarations across CSS
- Potential stacking conflicts (close values)
- Z-index range distribution

**Output:** `report/zindex-map.md` + JSON

**Recommended scale:**
- Base: 0-9
- Content: 10-99
- Dropdowns: 100-999
- Modals: 1000-9999
- Tooltips/Toasts: 10000+

### 7. Accessibility Scan (`audit:a11y`)

**What it checks (via axe-core):**
- Missing alt text
- Form labels
- ARIA attributes
- Heading hierarchy
- Color contrast (automated)
- Keyboard navigation issues

**Requirements:**
- Laravel server must be running
- URLs defined in `.env` or defaults to localhost:8000

**Output:** `report/axe.md` + JSON

**Severity:**
- Critical: Blocks access to content
- Serious: Major usability impact
- Moderate: Inconvenience for users
- Minor: Best practice violations

### 8. CSS Coverage (`audit:coverage`)

**What it checks:**
- Unused CSS rules on initial page load
- File size analysis
- Purge candidates (>50% unused, >5KB)

**Requirements:**
- Laravel server running
- Puppeteer downloads Chromium on first run (~150MB)

**Output:** `report/coverage-css.md` + JSON

**Note:** Interactive states (hover, modals) may show as unused.

### 9. Lighthouse CI (`audit:lh`)

**What it checks:**
- Performance (First Contentful Paint, Speed Index, etc.)
- Accessibility (overlaps with axe-core)
- Best Practices (HTTPS, console errors)
- SEO (meta tags, mobile-friendly)

**Requirements:**
- Laravel server running

**Output:** `report/lighthouse/*.html` (open in browser)

**Thresholds (configurable in `lighthouserc.json`):**
- Performance: 75+
- Accessibility: 90+
- Best Practices: 85+
- SEO: 80+

## 🔧 Configuration

### Change Audit URLs

Create `.env` or export:

```bash
export AUDIT_URLS="http://127.0.0.1:8000,http://127.0.0.1:8000/dashboard,http://127.0.0.1:8000/requests"
```

Or inline:

```bash
AUDIT_URLS="http://127.0.0.1:8000/custom-page" npm run audit:a11y
```

### Customize Stylelint Rules

Edit `.stylelintrc.cjs`:

```js
rules: {
  'selector-max-specificity': ['0,3,0'], // More strict
  'declaration-no-important': null,      // Allow !important
}
```

### Customize ESLint Rules

Edit `.eslintrc.cjs`:

```js
rules: {
  'no-console': 'off', // Allow console.log
}
```

### Adjust Lighthouse Thresholds

Edit `lighthouserc.json`:

```json
{
  "ci": {
    "assert": {
      "assertions": {
        "categories:performance": ["warn", {"minScore": 0.80}]
      }
    }
  }
}
```

## 🐛 Troubleshooting

### "Server not running" errors

**Problem:** axe-scan, coverage, or Lighthouse fail with timeout

**Solution:**
```bash
# In separate terminal:
php artisan serve

# Then run audit
npm run audit:a11y
```

### Puppeteer download fails

**Problem:** Chromium download interrupted

**Solution:**
```bash
# Manual install
npx puppeteer browsers install chrome

# Or skip chromium:
PUPPETEER_SKIP_DOWNLOAD=true npm install
```

### Stylelint "No files matching pattern"

**Problem:** No CSS files found

**Solution:** Check paths in `package.json` match your structure:
```json
"audit:stylelint": "stylelint \"resources/**/*.css\" \"styles/**/*.css\""
```

### ESLint config errors

**Problem:** Plugin not found

**Solution:**
```bash
npm install eslint-plugin-vue --save-dev  # If using Vue
npm install eslint-plugin-jsx-a11y --save-dev  # If using React
```

### Permission denied (Windows)

**Problem:** `EACCES` or `EPERM` errors

**Solution:** Run terminal as Administrator, or:
```bash
npm cache clean --force
npm install
```

## 📦 CI/CD Integration

### GitHub Actions

```yaml
name: Frontend Audit

on: [push, pull_request]

jobs:
  audit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '20'
      - run: npm ci
      - run: npm run audit:critical
```

### GitLab CI

```yaml
audit:
  stage: test
  script:
    - npm ci
    - npm run audit:critical
  artifacts:
    paths:
      - report/
```

### Pre-commit Hook

`.git/hooks/pre-commit`:

```bash
#!/bin/bash
npm run audit:guard
```

## 📝 Interpreting Results

### Priority Guide

1. **Critical (🔴):** Fix immediately
   - Layout in overlays (breaks Safe Mode)
   - Critical a11y issues (blocks users)
   - Security vulnerabilities

2. **Major (🟠):** Fix in current sprint
   - High specificity (maintenance burden)
   - !important abuse
   - Serious a11y issues
   - Poor performance metrics

3. **Minor (🟡):** Address during refactoring
   - ID selectors
   - Deep nesting
   - Minor a11y improvements
   - Code style issues

### When to Re-run

- **Before every deploy:** `npm run audit:critical`
- **Weekly:** `npm run audit:all`
- **After major CSS changes:** `npm run audit:cascade audit:guard`
- **After theme changes:** `npm run audit:contrast audit:guard`
- **After adding pages:** Update AUDIT_URLS and run `npm run audit:a11y`

## 🎯 Goals

These audits help achieve:
- ✅ **Zero layout shifts** from theme system
- ✅ **WCAG AA compliance** (accessibility)
- ✅ **Maintainable CSS** (low specificity, no conflicts)
- ✅ **Fast page loads** (minimal unused CSS)
- ✅ **No visual regressions** (consistent z-index, contrast)

## 📞 Support

**Report issues:**
- Check `report/*.md` files for detailed explanations
- JSON files contain raw data for custom processing
- All scripts are in `scripts/audit/` - feel free to modify!

**Need help?**
- Read inline comments in audit scripts
- Check tool documentation:
  - Stylelint: https://stylelint.io/
  - ESLint: https://eslint.org/
  - axe-core: https://github.com/dequelabs/axe-core
  - Lighthouse: https://developer.chrome.com/docs/lighthouse

---

**Happy Auditing!** 🚀
