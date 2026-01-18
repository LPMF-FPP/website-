name: rams
description: Use when reviewing UI code for accessibility (WCAG 2.1) and visual polish, or when users ask to run /rams or design reviews.

# RAMS Design Engineer

## Overview

Acts as a design engineer to review code for accessibility issues, visual inconsistencies, and UI polish. Enforces WCAG 2.1 standards and visual best practices.

## When to Use

- When reviewing frontend code (React, Vue, Blade, HTML)
- When asked to "check accessibility" or "review design"
- When invoked via `/rams`

## Accessibility (WCAG 2.1)

### Critical

- Images without alt text
- Icon-only buttons missing aria-labels
- Form inputs without labels
- Non-semantic click handlers (div onClick)
- Links without href

### Serious

- Focus outline removed without replacement
- Missing keyboard handlers
- Color-only information
- Touch targets under 44×44px

### Moderate

- Skipped heading levels
- Positive tabIndex values
- Role without required attributes

## Visual Design

### Layout & Spacing

- Inconsistent spacing values
- Overflow and alignment issues
- Z-index conflicts

### Typography

- Mixed font families and weights
- Line height issues
- Missing font fallbacks

### Color & Contrast

- Contrast ratio below 4.5:1
- Missing hover/focus states
- Dark mode inconsistencies

### Components

- Missing button states
- Missing form field states
- Inconsistent borders and shadows

## Output

Each issue includes the line number, code snippet, fix suggestion, and WCAG reference.

```text
═══════════════════════════════════════════════════ RAMS DESIGN REVIEW: {filename} ═══════════════════════════════════════════════════
CRITICAL ({count} issues) ───────────────────
[A11Y] Line {line}: {Description}
{code_snippet}
Fix: {suggestion}
WCAG: {reference}

SERIOUS ({count} issues) ─────────────────
[A11Y] Line {line}: {Description}
{code_snippet}
Fix: {suggestion}
WCAG: {reference}

═══════════════════════════════════════════════════
SUMMARY: {c} critical, {s} serious, {m} moderate
Score: {score}/100
═══════════════════════════════════════════════════
```

## Example Output

```text
═══════════════════════════════════════════════════ RAMS DESIGN REVIEW: app/page.tsx ═══════════════════════════════════════════════════
CRITICAL (2 issues) ───────────────────
[A11Y] Line 24: Button missing accessible name
<button><CloseIcon /></button>
Fix: Add aria-label="Close"
WCAG: 4.1.2

SERIOUS (1 issue) ─────────────────
[A11Y] Line 48: Focus outline removed
className="outline-none"
Fix: Add focus-visible:ring-2
WCAG: 2.4.7
═══════════════════════════════════════════════════
SUMMARY: 2 critical, 1 serious, 0 moderate
Score: 68/100
═══════════════════════════════════════════════════
```
