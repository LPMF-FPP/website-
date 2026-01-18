---
description: "Rams & UI Skills Guidelines - Strict rules for Design, Accessibility, and Implementation"
globs: "**/*.{ts,tsx,js,jsx,css,scss}"
---

# Rams & UI Skills Guidelines

These guidelines combine the accessibility standards from [Rams.ai](https://rams.ai) and the implementation constraints from [UI-Skills.com](https://ui-skills.com).

## 1. Accessibility (Rams.ai - WCAG 2.1)

### Critical Rules (Must Fix)

- **Images**: Must have `alt` text.
- **Buttons**: Icon-only buttons MUST have `aria-label`.
- **Forms**: All inputs MUST have associated labels.
- **Semantics**: Use `<button>` for actions, never `div` with `onClick`.
- **Links**: MUST have valid `href` attributes.

### Serious Rules

- **Focus**: NEVER remove outlines (`outline: none`) without replacement (e.g., `focus-visible:ring-2`).
- **Keyboard**: All interactive elements MUST have keyboard handlers.
- **Information**: NEVER rely on color alone to convey meaning.
- **Touch**: Targets MUST be at least **44x44px**.

### Moderate Rules

- **Headings**: Do NOT skip levels (e.g., `h1` -> `h3`).
- **Navigation**: Avoid positive `tabIndex` (use `0` or `-1`).
- **ARIA**: Elements with `role` MUST have required attributes.

## 2. Implementation Rules (UI Skills)

### Stack & Components

- **Tailwind**: MUST use defaults unless custom values exist.
- **Animation Lib**: MUST use `motion/react` (JS) or `tw-animate-css` (CSS).
- **Classes**: MUST use `cn` (`clsx` + `tailwind-merge`) for logic.
- **Primitives**: MUST use accessible primitives (Base UI, React Aria, Radix) for complex behavior.
- **Consistency**: NEVER mix primitive systems.
- **Keyboard**: NEVER rebuild keyboard logic by hand.

### Interaction

- **Destructive**: MUST use `AlertDialog` for irreversible actions.
- **Loading**: SHOULD use structural skeletons.
- **Height**: NEVER use `h-screen`; use `h-dvh`.
- **Insets**: MUST respect `safe-area-inset` for fixed elements.
- **Errors**: MUST show errors adjacent to the action.
- **Paste**: NEVER block paste in inputs.

### Animation

- **Explicit**: NEVER animate unless requested.
- **Performance**: MUST animate only `transform` and `opacity`. NEVER animate layout (`width`, `height`, `margin`).
- **Duration**: NEVER exceed `200ms` for feedback.
- **Reduced Motion**: SHOULD respect `prefers-reduced-motion`.
- **Easing**: SHOULD use `ease-out` on entrance.

### Typography & Layout

- **Headings**: MUST use `text-balance`.
- **Body**: MUST use `text-pretty`.
- **Data**: MUST use `tabular-nums`.
- **Z-Index**: MUST use a fixed scale.
- **Sizing**: SHOULD use `size-*` for squares.

### Performance & Design

- **Blur**: NEVER animate large `blur()` or `backdrop-filter`.
- **Will-Change**: NEVER apply unless actively animating.
- **React**: NEVER use `useEffect` for render logic.
- **Gradients**: NEVER use gradients/glows unless requested.
- **Shadows**: SHOULD use Tailwind defaults.
- **Empty States**: MUST have one clear next action.
- **Accents**: SHOULD limit accent color to one per view.
