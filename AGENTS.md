# Project Workflow Rules & Agent Guide

This file defines the strict workflow, standards, and commands for all agents operating in this repository.

## 1. Project Snapshot

- **Stack**: Laravel 12 (PHP 8.2+), Blade, Alpine.js, Tailwind CSS, Node.js 20+.
- **Core Structure**: `app/` (Backend), `resources/` (Frontend), `tests/` (Pest + Dusk).
- **Audit System**: `scripts/audit/` provides strict CSS/JS/A11y guardrails.

## 2. Agent Roles (BMAD + Superpowers)

### BMAD Agents

- **bmad-agent-core-bmad-master**: Orchestrates BMAD workflows and agent coordination.
- **bmad-agent-bmm-pm**: Product/project planning and scope management.
- **bmad-agent-bmm-analyst**: Requirements analysis and specifications.
- **bmad-agent-bmm-architect**: System design and architecture decisions.
- **bmad-agent-bmm-dev**: Implementation, refactoring, and debugging.
- **bmad-agent-bmm-tea**: Technical excellence, code quality, best practices.
- **bmad-agent-bmm-ux-designer**: UX/UI design and interaction.
- **bmad-agent-bmm-tech-writer**: Documentation and changelogs.
- **bmad-agent-bmm-sm**: Scrum facilitation and blocker removal.
- **bmad-agent-bmm-quick-flow-solo-dev**: Solo rapid execution (use only when explicitly requested).

### Superpowers Skills (Process Guides)

- **superpowers:using-superpowers**: Skill discovery and invocation gate.
- **superpowers:brainstorming**: Requirements clarification and design exploration.
- **superpowers:writing-plans**: Plan before multi-step work.
- **superpowers:executing-plans**: Execute plan with checkpoints.
- **superpowers:dispatching-parallel-agents**: Parallelize independent work.
- **superpowers:subagent-driven-development**: Subagent orchestration.
- **superpowers:test-driven-development**: TDD workflow.
- **superpowers:systematic-debugging**: Bug investigation rigor.
- **superpowers:verification-before-completion**: Evidence before completion claims.
- **superpowers:requesting-code-review**: Ask for review before merge.
- **superpowers:receiving-code-review**: Evaluate and apply feedback.
- **superpowers:using-git-worktrees**: Isolated workspaces.
- **superpowers:finishing-a-development-branch**: Wrap up and integrate.
- **superpowers:writing-skills**: Create or update skills.

### Role Requirements (UI Compliance)

- **bmad-agent-bmm-ux-designer**: MUST apply `/rams` for UI reviews and `/ui-skills` for UI implementation; include violations + fixes in review notes.
- **bmad-agent-bmm-dev**: MUST follow `/ui-skills` constraints for any UI change; run `/rams` to check accessibility on UI edits.
- **bmad-agent-bmm-tea**: MUST enforce compliance and resolve `/rams`/`/ui-skills` violations before sign-off.
- **bmad-agent-core-bmad-master**: Ensure `/rams` and `/ui-skills` are used for any UI deliverable.

## 3. Development Commands (Crucial)

### Setup & Build

```bash
composer install && npm install  # Initial setup
npm run build                    # Build frontend assets
php artisan serve                # Start dev server (Required for audits)
```

### Testing (Detailed)

**Always run tests before pushing.**

```bash
# Run ALL tests (PHP + E2E)
npm run test

# Run PHP tests (Pest)
npm run test:php                    # All PHP tests
php vendor/bin/pest                 # Direct Pest command
php vendor/bin/pest --filter Name   # Run SINGLE test method/class (e.g., --filter UserTest)
php vendor/bin/pest path/to/file.php # Run tests in a SPECIFIC FILE

# Run E2E tests (Dusk)
npm run test:e2e                    # All Browser tests
php artisan dusk tests/Browser/ExampleTest.php # Run SPECIFIC E2E file
```

### Auditing & Linting

**"Safe Mode v2" enforces strict CSS rules (no layout in overlays).**

```bash
npm run audit:critical  # MUST PASS before commit (Guard + Cascade + Contrast)
npm run audit:all       # Full suite (A11y, Lighthouse, etc.)
npm run audit:guard     # Check for layout property violations in pd-*.css

# Fix Code Style
npx eslint "resources/js/**/*.js" --fix
npx stylelint "resources/**/*.css" --fix
./vendor/bin/pint       # Fix PHP code style (PSR-12)
```

## 4. Code Style & Standards

### PHP (Laravel)

- **Formatting**: PSR-12 enforced via Laravel Pint.
- **Imports**: Sorted alphabetically, unused imports removed.
- **Types**: Use strict types (`declare(strict_types=1);` optional but encouraged). Return types are mandatory for new code.
- **Naming**:
    - Classes: `PascalCase` (e.g., `UserController`).
    - Methods/Variables: `camelCase` (e.g., `updateProfile`).
    - Database Columns: `snake_case` (e.g., `user_id`).
    - Config/Lang keys: `snake_case` (dot notation).
- **Error Handling**: Use `try-catch` blocks for external services. Log errors via `Log::error()` with context. Never expose raw exceptions to UI.

### JavaScript (Alpine.js / Vue)

- **Formatting**: ESLint + Prettier.
- **Naming**: `camelCase` for functions/vars. `PascalCase` for Components.
- **State**: Use Alpine.js `x-data` for local state. Avoid polluting global window.

### CSS (Tailwind)

- **Structure**: Mobile-first (`block md:flex`).
- **Safe Mode**: Overlay files (`styles/pd-*.css`) **CANNOT** contain layout properties (margin, padding, width, height, position).
- **Naming**: `kebab-case` for custom classes.

## 5. Documentation Protocol

**⚠️ DO NOT CREATE NEW .md FILES** (Exceptions: `README.md` and sub-folder docs).

1.  **WALKTHROUGH.md**: The single source of truth for changelogs.
    - Append new features/fixes with `## [Category]/[Feature]` and `Updated on YYYY-MM-DD`.
2.  **todos.md**: Active task tracker. Overwrite for new tasks. Clear when done.
3.  **Changelog Page**: Update the `/changelogs` route in the app after `WALKTHROUGH.md`.

## 6. Workflow & Git

1.  **Plan**: `Planner-Sisyphus` creates/updates `todos.md`.
2.  **Implement**: `Sisyphus` writes code.
3.  **Verify**:
    - `npm run test` (Passes?)
    - `npm run audit:critical` (Passes?)
4.  **Document**: Update `WALKTHROUGH.md` and `/changelogs`.
5.  **Commit**: `git commit -m "feat: description"` (Descriptive messages).

## 7. Design Guidelines (Vercel/Geist + Rams/UI Skills)

- **Source**: Follow `VERCEL_GUIDELINES.md` and `RAMS_UI_GUIDELINES.md`.
- **Core Rules**:
    - **Keyboard**: All interactive elements must be keyboard accessible.
    - **Loading**: Use skeletons and optimistic updates.
    - **Forms**: Enter submits; proper autocomplete/labels.
    - **Copy**: Active voice, Title Case for buttons, specific error messages.
    - **Motion**: `transform`/`opacity` only; respect `prefers-reduced-motion`.
    - **Constraints**: No `h-screen` (use `h-dvh`), no layout animation, `text-balance` for headings.

### Command Usage

- **/rams**: Review UI files for accessibility + visual polish. Output violations, why it matters, and concrete fixes.
- **/ui-skills**: Enforce UI implementation constraints. Output violations, why it matters, and concrete fixes.
- **Suggested flow**: `/ui-skills` during implementation, then `/rams` for final review.

## 8. Definition of Done

- [ ] All tests passed (`npm run test`).
- [ ] Critical audits passed (`npm run audit:critical`).
- [ ] Code formatted (`pint`, `eslint --fix`).
- [ ] `WALKTHROUGH.md` updated.
- [ ] `todos.md` tasks marked `[x]`.
