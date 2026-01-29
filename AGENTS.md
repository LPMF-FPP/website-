# Agent Guide & Project Workflow

This file defines the strict workflow, standards, and commands for all agents operating in this repository.

## 1. Project Snapshot

- **Stack**: Laravel 12 (PHP 8.2+), Blade, Alpine.js, Tailwind CSS, Node.js 20+.
- **Core Structure**: `app/` (Backend), `resources/` (Frontend), `tests/` (Pest + Dusk), `scripts/audit/` (Guardrails).
- **Audit System**: Automated CSS/JS/A11y audits via `npm run audit:*`. Reports in `report/`.

## 2. Agent Roles (BMAD + Superpowers)

- **Roles**: `bmad-master` (Orchestrator), `bmm-dev` (Impl), `bmm-tea` (Quality), `bmm-ux-designer` (UI).
- **Superpowers**: `using-superpowers` (Gate), `writing-plans` (Plan), `systematic-debugging` (Fix), `writing-skills` (Meta).
- **Mandate**: **Invoke relevant skills BEFORE any response.** Check `~/.config/opencode/skills/superpowers/`.

## 3. Development Commands

### Setup & Build

```bash
composer install && npm install  # Initial setup
npm run build                    # Build frontend assets
php artisan serve                # Start dev server (Required for audits)
```

### Testing (Crucial)

**Always run tests before pushing.**

```bash
# Run ALL tests
npm run test                     # Parallel PHP + E2E

# PHP Tests (Pest)
npm run test:php                 # Run all PHP tests
php vendor/bin/pest              # Direct execution
php vendor/bin/pest --filter UserTest  # Run SINGLE test method/class
php vendor/bin/pest tests/Unit/ExampleTest.php # Run SPECIFIC FILE

# E2E Tests (Dusk)
npm run test:e2e                 # Run all Browser tests
php artisan dusk tests/Browser/ExampleTest.php # Run SPECIFIC E2E file
```

### Auditing & Linting

**"Safe Mode" enforces strict CSS rules.**

```bash
npm run audit:critical  # MUST PASS (Guard + Cascade + Contrast)
npm run audit:all       # Full suite (A11y, Lighthouse, etc.)
npm run audit:guard     # Check for layout property violations in pd-*.css

# Fix Code Style
./vendor/bin/pint       # Fix PHP code style (PSR-12)
npx eslint "resources/js/**/*.js" --fix
npx stylelint "resources/**/*.css" --fix
```

## 4. Code Style & Standards

### PHP (Laravel)

- **Formatting**: PSR-12 via Pint.
- **Naming**: `PascalCase` classes, `camelCase` methods, `snake_case` DB columns.
- **Strictness**: `declare(strict_types=1);` preferred. Return types mandatory.
- **Safety**: Use `try-catch` + `Log::error()` for external services. No raw exceptions in UI.

### JavaScript (Alpine/Vue)

- **Linting**: ESLint standard. No `eval()`, `var`, or layout thrashing.
- **Imports**: Sorted alphabetically (builtin > external > internal).
- **Cleanup**: Remove event listeners to prevent memory leaks.

### CSS (Tailwind)

- **Linting**: Stylelint standard. Avoid ID selectors, `!important` (except utilities), and high specificity.
- **Performance**: Animate `transform`/`opacity` only.
- **Safe Mode**: Overlay files (`styles/pd-*.css`) **CANNOT** contain layout properties (margin, padding, width).

## 5. Documentation Protocol

**⚠️ DO NOT CREATE NEW .md FILES** (Exceptions: `README.md`, `report/README.md`, `todos.md`).

1. **WALKTHROUGH.md**: The single source of truth. Append new features with `## [Category]/[Feature]`.
2. **todos.md**: Active task tracker. Overwrite for current task.
3. **Docs**: Update `/changelogs` route content to match `WALKTHROUGH.md`.

## 6. Workflow & Git

1. **Plan**: Create/update `todos.md` using `writing-plans` skill.
2. **Implement**: Write code using `writing-code` patterns.
3. **Verify**: Run `npm run test` and `npm run audit:critical`.
4. **Document**: Update `WALKTHROUGH.md`.
5. **Commit**: `git commit -m "feat: description"`.

## 7. Design Guidelines (Vercel + Rams/UI Skills)

- **Core**: Mobile-first, Keyboard accessible, Optimistic UI.
- **Commands**:
    - `/rams`: Review UI for accessibility (Alt text, Labels, Contrast).
    - `/ui-skills`: Enforce constraints (No `h-screen`, `text-balance`, `tabular-nums`).
- **Checklist**:
    - [ ] Inputs have labels?
    - [ ] Loading states (skeletons)?
    - [ ] Touch targets >= 44px?

## 8. Definition of Done

- [ ] All tests passed (`npm run test`).
- [ ] Critical audits passed (`npm run audit:critical`).
- [ ] Code formatted (`pint`, `eslint --fix`).
- [ ] `WALKTHROUGH.md` updated.
- [ ] `todos.md` tasks marked `[x]`.
