# Agent Guide & Project Workflow

This file defines the strict workflow, standards, and commands for all agents operating in this repository.

## 1. Project Snapshot

- **Stack**: Laravel 12 (PHP 8.2+), Blade, Alpine.js, Tailwind CSS, Node.js 20+.
- **Core Structure**: `app/` (Backend), `resources/` (Frontend), `tests/` (Pest + Dusk), `scripts/audit/` (Guardrails).
- **Audit System**: Automated CSS/JS/A11y audits via `npm run audit:*`. Reports in `report/`.

## 2. Unified Workflow Architecture

### 2.1 Two-Layer System

| Layer           | System      | Purpose                   | Location                                 |
| --------------- | ----------- | ------------------------- | ---------------------------------------- |
| **Process**     | Superpowers | HOW to work (methodology) | `~/.config/opencode/skills/superpowers/` |
| **Specialists** | BMAD Agents | WHO does specialized work | `.opencode/agent/`                       |

### 2.2 The Golden Rule

> **Invoke Superpowers skills BEFORE any response.**
> Delegate to BMAD agents only for specialized work.

### 2.3 Decision Tree

```
User Request Received
        │
        ▼
┌───────────────────────┐
│ Check Superpowers     │
│ skill applicability   │
└───────────────────────┘
        │
        ├─── Creative work? ──→ brainstorming
        ├─── Implementation? ─→ writing-plans → TDD
        ├─── Bug/Error? ──────→ systematic-debugging
        ├─── UI work? ────────→ using-rams / using-ui-skills
        ├─── Before commit? ──→ verification-before-completion
        └─── Merge/PR? ───────→ finishing-a-development-branch
                │
                ▼
┌───────────────────────┐
│ Need specialized      │
│ expertise?            │
└───────────────────────┘
        │
        ├─── Architecture ────→ @bmm-architect (Task tool)
        ├─── UI/UX deep work ─→ @bmm-ux-designer (Task tool)
        ├─── Parallel impl ───→ @bmm-dev (Task tool)
        └─── Requirements ────→ @bmm-analyst (Task tool)
```

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

### 6.1 Unified Workflow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. GATE: using-superpowers                                   │
│    "Which skill applies to this task?"                       │
└─────────────────────────────────────────────────────────────┘
                           │
┌─────────────────────────────────────────────────────────────┐
│ 2. PROCESS: Invoke appropriate skill                         │
│    brainstorming → writing-plans → TDD → verification       │
└─────────────────────────────────────────────────────────────┘
                           │
┌─────────────────────────────────────────────────────────────┐
│ 3. DELEGATE (if needed): Task tool → BMAD Agent             │
│    @bmm-architect, @bmm-ux-designer, @bmm-dev               │
└─────────────────────────────────────────────────────────────┘
                           │
┌─────────────────────────────────────────────────────────────┐
│ 4. QUALITY GATE: verification-before-completion              │
│    npm run test && npm run audit:critical                    │
└─────────────────────────────────────────────────────────────┘
                           │
┌─────────────────────────────────────────────────────────────┐
│ 5. COMPLETE: finishing-a-development-branch                  │
│    Commit → Merge/PR → Cleanup                               │
└─────────────────────────────────────────────────────────────┘
```

### 6.2 Workflow by Task Type

| Task Type       | Workflow                                                             |
| --------------- | -------------------------------------------------------------------- |
| **New Feature** | brainstorming → writing-plans → TDD → verification → finish          |
| **Bug Fix**     | systematic-debugging → TDD (regression test) → verification → finish |
| **UI Work**     | brainstorming → @bmm-ux-designer → rams + ui-skills → verification   |
| **Refactor**    | writing-plans → TDD → verification → finish                          |
| **Quick Fix**   | systematic-debugging → verification → finish                         |

### 6.3 Quality Gates (Mandatory)

Before ANY commit:

```bash
npm run test              # All tests pass
npm run audit:critical    # A11y + CSS guards
./vendor/bin/pint         # PHP style
```

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

## 9. Quick Reference

### 9.1 Skill Invocation Table

| Trigger                | Skill to Invoke                  | Purpose          |
| ---------------------- | -------------------------------- | ---------------- |
| "Add feature X"        | `brainstorming`                  | Design first     |
| "Implement X"          | `writing-plans` → `TDD`          | Plan then code   |
| "Fix bug" / "Error"    | `systematic-debugging`           | Root cause first |
| "Review UI"            | `using-rams`                     | A11y check       |
| "Check UI constraints" | `using-ui-skills`                | UI rules         |
| "Before commit"        | `verification-before-completion` | Evidence first   |
| "Merge/PR"             | `finishing-a-development-branch` | Clean finish     |
| "Multiple tasks"       | `dispatching-parallel-agents`    | Parallel work    |

### 9.2 BMAD Agent Delegation

| Need                  | Agent             | Invoke via |
| --------------------- | ----------------- | ---------- |
| Architecture decision | `bmm-architect`   | Task tool  |
| UI/UX specialized     | `bmm-ux-designer` | Task tool  |
| Heavy implementation  | `bmm-dev`         | Task tool  |
| Requirements analysis | `bmm-analyst`     | Task tool  |

### 9.3 Iron Laws (Non-Negotiable)

| Skill                            | Iron Law                                      |
| -------------------------------- | --------------------------------------------- |
| `TDD`                            | NO production code WITHOUT failing test first |
| `systematic-debugging`           | NO fixes WITHOUT root cause investigation     |
| `verification-before-completion` | NO claims WITHOUT fresh evidence              |
