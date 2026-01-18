---
description: "Vercel Web Interface Guidelines - Best practices for UI, interactions, and design"
globs: "**/*.{ts,tsx,js,jsx,css,scss}"
---

# Vercel Web Interface Guidelines

These guidelines are based on Vercel's [Design Guidelines](https://vercel.com/design/guidelines) and cover interactions, animations, layout, content, forms, and design.

## Core Principles

- **Keyboard First**: All flows must be keyboard-operable. Follow WAI-ARIA Authoring Patterns.
- **Clear Focus**: Every focusable element must have a visible focus ring (prefer `:focus-visible`).
- **Mobile Friendly**: Touch targets ≥ 44px (or ≥ 24px hit target for smaller visual elements). Font size ≥ 16px to prevent auto-zoom.
- **Fast & Responsive**: Optimistic updates, skeleton loading states, and minimal layout shift.
- **Accessible**: Semantic HTML first, proper ARIA labels, and high contrast.

## 1. Interactions

- **Focus**: Use `:focus-visible` for indicators. Manage focus with traps/restoration per ARIA patterns.
- **Loading**:
    - Show loading indicators but keep original labels.
    - Use optimistic updates for likely success.
    - Min delay: 150-300ms; Min duration: 300-500ms to avoid flicker.
- **URL State**: Persist UI state (filters, tabs, pagination) in URL for shareability.
- **Gestures**: `touch-action: manipulation` to prevent double-tap zoom.
- **Links**: Use `<a>` or `<Link>` for navigation, never `<button>` or `<div>`.
- **Drag**: Disable text selection and apply `inert` during drag operations.

## 2. Animations

- **Preference**: CSS > Web Animations API > JS libraries.
- **Performance**: Animate cheap properties (`opacity`, `transform`). Avoid layout triggers (`width`, `height`, `top`, `left`).
- **Reduced Motion**: Respect `prefers-reduced-motion`.
- **Logic**: Animations must clarify cause & effect or add deliberate delight.
- **Interruptible**: User input should cancel or skip animations.
- **SVG**: Animate `<g>` wrappers with `transform-box: fill-box; transform-origin: center;`.

## 3. Layout

- **Alignment**: Every element aligns deliberately (grid, baseline, or optical center).
- **Responsive**: Test mobile, laptop, and ultra-wide (zoom 50%).
- **Safe Areas**: Respect notches with `env(safe-area-inset-*)`.
- **Scrollbars**: Avoid layout shift; only render useful scrollbars.
- **Sizing**: Prefer flex/grid/intrinsic sizing over JS measurements.

## 4. Content & Typography

- **Inline Help**: Prefer inline explanations over tooltips.
- **Skeletons**: Must match final content layout exactly.
- **Quotes**: Use curly quotes (“ ”).
- **Numbers**: Use `tabular-nums` for data/comparisons.
- **Formatting**: Locale-aware dates, times, and numbers.
- **Copywriting (Vercel Style)**:
    - Active voice ("Install CLI", not "CLI will be installed").
    - Title Case for headings/buttons (except marketing).
    - Use `&` over `and`.
    - Second person ("You").
    - Positive framing ("Something went wrong", not "Failed").
    - Separated units (`10 MB`, use `&nbsp;`).

## 5. Forms

- **Submission**:
    - Enter submits single-input forms.
    - ⌘/Ctrl+Enter submits textareas; Enter adds newline.
    - Disable submit _during_ request (not before).
- **Labels**: Every control needs a label (visible or accessible).
- **Validation**: Don't block typing; allow input and show feedback.
- **Passwords**: Allow pasting; don't trigger managers for non-auth fields.
- **Autofill**: Use correct `name`, `type`, and `autocomplete` attributes.

## 6. Performance

- **Metrics**: Track re-renders and network latency (<500ms for mutations).
- **Images**: Explicit dimensions to prevent CLS. Lazy-load below fold.
- **Lists**: Virtualize large lists.
- **Fonts**: Preload critical fonts; subset usage.

## 7. Design Tokens (Geist System)

- **Shadows**: Layered shadows (ambient + direct).
- **Borders**: Combine with shadows; use semi-transparent borders for clarity.
- **Radius**: Concentric nesting (Child radius ≤ Parent radius).
- **Contrast**: APCA preferred over WCAG 2.
- **Dark Mode**: Use `color-scheme: dark`.

### Colors (Geist)

- **Backgrounds**:
    - `Background 1`: Default (Page/Card)
    - `Background 2`: Secondary/Subtle
- **Component Backgrounds**:
    - `Color 1`: Default
    - `Color 2`: Hover
    - `Color 3`: Active
- **Borders**:
    - `Color 4`: Default
    - `Color 5`: Hover
    - `Color 6`: Active
- **Text/Icons**:
    - `Color 9`: Secondary
    - `Color 10`: Primary (High Contrast)

## 8. Icons (Geist)

- Use Vercel's Geist icons for consistency.
- **Naming**: `kebab-case` (e.g., `arrow-right`, `check-circle`).
- **Optimization**: SVG format, accessible `aria-label` or `aria-hidden`.

## Checklist for Agents

When generating UI code:

1. [ ] Is it keyboard accessible?
2. [ ] Are loading states handled (optimistic + skeleton)?
3. [ ] Is the copy active and concise?
4. [ ] Are layout animations performant (`transform`/`opacity` only)?
5. [ ] Does it work on mobile (touch targets, font size)?
6. [ ] Are form inputs semantic and autofill-ready?
