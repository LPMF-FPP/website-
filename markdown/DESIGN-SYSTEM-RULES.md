# Design System Rules (v1)

> Status: Draft foundation for LIMS UI (Governmental / Scientific / Modern Minimal)

## 1. Brand & Tone
Formal institutional trust + scientific clarity + approachable microcopy.
Primary user tiers: Lab Staff, Supervisors, External Requestors, Public (limited).
Core goals: Fast sample intake · Clear document generation · Audit transparency.

## 2. Information Architecture (Snapshot)
Sidebar (persistent): Dashboard • Requests • Samples • Documents (LHU, BA) • Statistics • Settings • (Audit Logs) • Profile.
Top bar (utility): Search (future) • Language Switch (id/en) • User menu.

## 3. Layout Templates
| Template | Purpose | Key Regions |
|----------|---------|-------------|
| Dashboard | KPI + recent activity | KPI Grid, Activity Feed, Pending Actions |
| List Index | Data management | Filter Bar, Data Table, Bulk Actions, Empty State |
| Detail View | Request/Sample/Document | Header w/ Status, Metadata Grid, Tabs, Timeline |
| Settings | Configure system | Nav (vertical), Section Forms, Sticky Save |
| Statistics | Analytics & export | Filters, Chart Grid, Exports |
| Auth | Entry point | Card, Logo, Language toggle |

## 4. Component Inventory (Initial)
Buttons, Inputs, Selects, Date/Range (future), Status Badge, Alert/Toast, Table, Pagination, Tabs, Modal, Card, KPI Card, Filter Chip, Timeline Entry, Skeletons, Language Switcher.

### Button API
```
<x-button variant="primary|secondary|ghost|destructive" size="sm|md|lg" :loading="false">Label</x-button>
```

## 5. Design Tokens
(Will map to Tailwind config.)

### Color Families
Neutral (50–900) · Primary (institutional blue/indigo) · Success · Warning · Danger · Info.
Semantic tokens: `--ds-color-bg-surface`, `--ds-color-text-primary`, `--ds-color-border-default`, etc.

### Typography Scale
| Token | Size | Line | Use |
|-------|------|------|-----|
| display-lg | 40 | 48 | Hero / Empty State |
| heading-xl | 24 | 32 | Page Titles |
| heading-lg | 20 | 28 | Section Titles |
| body-lg | 16 | 24 | Base text |
| body-sm | 14 | 20 | Secondary text |
| mono-sm | 13 | 18 | Codes / IDs |
| caption-xs | 12 | 16 | Meta annotations |

Font families: `Inter` (sans), `JetBrains Mono` (monospace fallback).

### Spacing (4px scale)
`2 4 6 8 12 16 20 24 32 40 48 56 64`

### Radius
`xs=2` `sm=4` `md=6` `lg=8` `xl=12` `pill=9999`

### Elevation (Shadows)
`sm: 0 1px 2px rgba(0,0,0,.06)` · `md: 0 2px 4px rgba(0,0,0,.08)` · `lg: 0 4px 12px rgba(0,0,0,.12)` · `modal: 0 8px 24px rgba(0,0,0,.18)`

### Focus
2px ring outside border, offset 2px, primary-500. Always visible when keyboard navigating.

## 6. Accessibility
- AA contrast baseline.
- Non-color cues for status (icons / text).
- `focus-visible` utilities applied across interactive components.

## 7. Figma Structure
Pages: Foundations • Components • Patterns • Layouts • Prototypes • Playground • Archive.
Naming: `[A] Button / Primary`, `[M] Table / Row / Selectable`, `[O] App Shell / Sidebar`.
Variant Props: Buttons (variant,size,state), Badges (tone,outlined), Inputs (state,withIcon).

## 8. Token → Tailwind Mapping (Excerpt)
```js
// tailwind.config.js (extend)
colors: {
  neutral: {50:'#F8FAFC',100:'#F1F5F9',500:'#64748B',900:'#0F172A'},
  primary: {50:'#EEF3FF',100:'#D9E6FF',500:'#1D4ED8',600:'#1E40AF',700:'#1E3A8A'},
  success: {50:'#F0FDF4',500:'#16A34A',600:'#15803D'},
  warning: {50:'#FFFBEB',500:'#F59E0B',600:'#D97706'},
  danger: {50:'#FEF2F2',500:'#EF4444',600:'#DC2626'},
  info: {50:'#EFF6FF',500:'#0EA5E9',600:'#0284C7'}
},
```

## 9. Blade Component Mapping
| Figma | Blade | Notes |
|-------|-------|-------|
| Button | `resources/views/components/button.blade.php` | variant + size props |
| Badge | `components/status-badge.blade.php` | semantic classes |
| Modal | `components/modal.blade.php` | alpine show/hide |
| Alert | `components/alert.blade.php` | role="alert" |
| Tabs | (new) `components/tabs.blade.php` | manages active via Alpine |
| KPI Card | reuse `<x-card>` with modifier class | number & delta slots |

## 10. Localization Integration
- Language switcher in shell top bar.
- Reserve spacing for 2–3 letter code.
- Blade uses translation keys; Figma wire labels annotated with `{t:key.path}`.

## 11. Motion Guidelines
- Button + state transitions: 150ms ease-in-out.
- Menus / dropdown scale fade: 120ms cubic-bezier(0.16,1,0.3,1).
- Skeleton shimmer: 1.2s linear infinite.

## 12. Dark Mode
Dark mode is opt-in via either adding the `dark` class to `<html>` or `data-theme="dark"` (Tailwind `darkMode: ['class','[data-theme=dark]']`).

### Toggle API
Global helpers are injected in `app.js`:
```
window.__toggleTheme(); // switches between light/dark
window.__setTheme('dark'); // force
```
Preference is persisted to `localStorage` key `ui.theme` and defaults to system preference.

### Theming Strategy
- Light theme uses existing tailwind utilities.
- Dark theme relies on CSS variable overrides defined in `resources/css/theme-dark.css`.
- Components use semantic surface utilities where possible (`card-pd`, alerts) minimizing per-component overrides.

### Adding New Components (Dark Ready)
Use variables or Tailwind `dark:` modifiers:
```
<div class="bg-white dark:bg-accent-800 text-accent-900 dark:text-accent-100">...</div>
```

### Accessibility Checklist
- Maintain contrast ratio >= 4.5:1 for body text, 3:1 for large text.
- Preserve focus ring visibility (uses `--pd-focus-ring`).
- Avoid pure black (#000) to reduce eye strain; use deep slate (#0f172a) background.

## 13. Delivery Workflow
1. Define / update tokens in Figma styles.
2. Export via Tokens Studio → JSON → sync to `resources/design-tokens.json`.
3. Run build script to regenerate Tailwind extension (future automation).
4. Document deltas in CHANGELOG (Design System section).

## 14. Initial Build Order
Foundations → Buttons & Inputs → Status Badge → Card/Table → Modal/Tabs → KPI → Filters → Timeline.

## 15. Open Questions
- Confirm institutional palette hex values.
- Decide charting library (affects tokenizing gradients / categorical colors).
- Determine audit log exposure level in first release.

## 16. Token Sync Command
Run the artisan command to transform JSON tokens into CSS custom properties consumed by Tailwind layer extensions or plain CSS.

### Source File
Primary: `resources/design-tokens.json` (commit this). Fallback (example): `resources/design-tokens.example.json`.

### Command Usage
```
php artisan design:sync-tokens                 # uses default source & out
php artisan design:sync-tokens --dry           # preview output
php artisan design:sync-tokens --source=resources/design-tokens.example.json
php artisan design:sync-tokens --out=public/build/theme-tokens.css
```

### Output
Writes CSS to `public/build/design-tokens.css` (or custom `--out`) with variables like:
```
:root {
  --pd-color-primary-500: #1D4ED8;
  --pd-typography-heading-xl-font-size: 24px;
  --pd-radius-md: 6px;
  --pd-spacing-4: 8px;
  --pd-shadow-md: 0 2px 4px rgba(0,0,0,.08);
  --pd-motion-fast: 150ms ease-in-out;
}
```
Include early in `<head>`:
```html
<link rel="stylesheet" href="/build/design-tokens.css">
```

### Update Flow
1. Export / edit tokens JSON.
2. Run sync command.
3. (Optional) Run build pipeline (`npm run build`) so PurgeCSS / Vite picks up utilities referencing variables.
4. Commit updated JSON + generated CSS (or regenerate during deploy).

### Future Enhancements
- Generate a SCSS/Tailwind partial for direct config ingestion.
- Hash & compare existing file to skip unnecessary writes.
- Support alias + semantic token mapping layer.

---

### Update: Theming Bundling & No-Flash Strategy (2025-10-09)
Dark theme overrides (`theme-dark.css`) and semantic alias tokens (`theme-semantic.css`) are now imported inside `resources/css/app.css` so they ship via the Vite pipeline (hashed, minified). Remove any legacy `<link>` tags referencing those files directly.

Early theme application script (inline, before CSS) is injected in `app.blade.php` and `landing.blade.php` to add `dark` / `data-theme="dark"` classes pre-paint based on `localStorage.ui.theme` or system preference, eliminating color flash.

Semantic adoption helpers added:
- `.surface-sem`, `.card-sem`, `.border-sem`
- Body now uses `var(--pd-sem-color-bg)` and `var(--pd-sem-color-text)` so global switching honors aliases.

Guidance for new components:
1. Prefer semantic vars: `background-color: var(--pd-sem-color-surface);` etc.
2. Reserve raw palette utilities (e.g., `bg-primary-600`) for brand accent elements (CTAs, emphasis) not structural surfaces.
3. When adding elevated surfaces, pair `.card-sem` + `.elevated` for shadow scale.
4. For future status badge refactor, map subtle backgrounds to semantic state tokens once state alias layer is formalized.

Deployment Checklist Delta:
- Ensure build step runs (Vite) after any semantic token edits.
- If adding new semantic tokens, update `theme-semantic.css` and consider documenting in this section.

---
Update this file as the source of truth for Figma ↔ Code alignment.
