# AGENTS.md — LPMF LIMS Codebase Guide

## Stack

Laravel 12 (PHP 8.2+), Blade, Alpine.js 3, Tailwind CSS 3, Vite 7, PostgreSQL.
Domain: Forensic pharmaceutical laboratory management (Indonesian government context).
UI text in Bahasa Indonesia, code identifiers in English.

## Commands

### Setup & Build

```bash
composer install && npm install   # Install all dependencies
npm run build                     # Build frontend (Vite)
php artisan serve                 # Dev server (required for audits/E2E)
composer run dev                  # Concurrent: serve + queue + pail + vite
```

### Testing

```bash
# PHP (Pest)
php vendor/bin/pest                              # All PHP tests
php vendor/bin/pest --filter UserTest             # Single test by name
php vendor/bin/pest tests/Unit/ExampleTest.php    # Single file
php vendor/bin/pest tests/Feature/Settings/       # Single directory
npm run test:php                                  # Pest --parallel --stop-on-failure

# E2E (Dusk) — requires `php artisan serve` running
php artisan dusk                                  # All browser tests
php artisan dusk tests/Browser/ExampleTest.php    # Single E2E file
npm run test:e2e                                  # Alias

# All tests (parallel PHP + E2E)
npm run test
```

### Linting & Formatting

```bash
./vendor/bin/pint                               # PHP code style (PSR-12)
npx eslint "resources/js/**/*.js" --fix         # JS lint + fix
npx stylelint "resources/**/*.css" --fix        # CSS lint + fix
```

### Auditing

```bash
npm run audit:critical   # MUST PASS before commit (guard + cascade + contrast)
npm run audit:all        # Full suite (a11y, lighthouse, coverage, etc.)
npm run audit:guard      # Check pd-*.css for layout property violations
```

### Quality Gate (before every commit)

```bash
npm run test && npm run audit:critical && ./vendor/bin/pint
```

## Project Structure

```
app/
  Console/         Artisan commands
  Enums/           PHP 8.1 backed enums (14)
  Http/Controllers/ 34 controllers (+ Api/, Auth/, Inventory/, Settings/)
  Http/Middleware/  CheckPermission (RBAC)
  Http/Requests/   Form request validation
  Models/          59 Eloquent models
  Observers/       Model lifecycle hooks
  Policies/        Authorization policies
  Repositories/    SettingsRepository, DocumentTemplateRepository
  Services/        28 services (AI/, WhatsApp/, Inventory/, etc.)
  helpers.php      Global helpers: settings(), fmt_date(), fmt_number()
resources/
  css/             Tailwind entry, themes, design tokens
  js/              Alpine.js components, stores, utils
  views/           Blade templates (35 subdirectories)
styles/            pd-*.css overlay files (Safe Mode — NO layout properties)
tests/
  Feature/         50 test files (Controllers, API, WhatsApp, Settings, etc.)
  Unit/            Model + Service + Repository tests
  Browser/         21 Dusk E2E test dirs (A11y, Mobile, Visual, etc.)
scripts/audit/     Automated CSS/JS/A11y audit scripts
.opencode/agent/   BMAD agent definitions (20 agents)
```

## Code Style

### PHP

- **Formatter**: Laravel Pint (PSR-12, default config — no `pint.json`).
- **Strict types**: `declare(strict_types=1);` preferred for new files.
- **Naming**: `PascalCase` classes, `camelCase` methods, `snake_case` DB columns/properties.
- **Return types**: Always declare on methods.
- **Architecture**: Service layer for business logic. Controllers stay thin.
  Constructor injection with `private readonly`: `__construct(private readonly FooService $foo)`.
- **Models**: Explicit `$fillable`, `$casts`. Relationships with return types (`BelongsTo`, `HasMany`).
  Use scopes, accessors, observers for reusable query/model logic.
- **Enums**: PHP 8.1 backed enums with `label()`, state machine methods (`canTransitionTo()`).
- **Error handling**: `try-catch` + `Log::error()` for external services. Never expose raw exceptions to UI.
- **Helpers**: `settings()`, `fmt_date()`, `fmt_number()` — global helpers in `app/helpers.php`.
- **Testing**: Pest syntax. `uses(RefreshDatabase::class)`. `actingAs($user)`. `settings_fake()` for test config.

### JavaScript / Alpine.js

- **Linter**: ESLint (`eslint:recommended` + `import` + `unicorn` plugins).
- **No** `eval()`, `var`, or direct `window.innerWidth`/`document.body` access (DOM thrashing prevention).
- **Prefer** `const` over `let`. Use `===` strictly.
- **Import order**: builtin > external > internal (enforced by ESLint).
- **Alpine patterns**: Register components via `Alpine.data()`, stores via `Alpine.store()`.
  Clean up event listeners to prevent memory leaks.
- **AJAX**: Axios (configured in `bootstrap.js`). CSRF token from meta tag.

### CSS / Tailwind

- **Linter**: Stylelint (`stylelint-config-standard` + SCSS + order + performance plugins).
- **Max specificity**: `0,4,0`. No ID selectors. Max 4 compound selectors.
- **No `!important`** (warning severity, except utility classes).
- **Performance**: Only animate `transform`/`opacity` (composited properties).
- **Custom properties**: Must follow `pd-*`, `theme-*`, or `color-*` naming.
- **Property order**: positioning > display > box model > visual > typography > transitions.
- **Safe Mode**: Files in `styles/pd-*.css` **CANNOT** contain layout properties
  (margin, padding, width, height, position, display, flex, grid, gap, overflow, transform).
  Enforced by `scripts/audit/guard-nonlayout.mjs`.
- **Design tokens**: CSS custom properties (`--pd-sem-color-*`, `--pd-spacing-*`).
  Tailwind extended with medical/health color palette and custom plugin utilities.
- **Dark mode**: `.dark` class + `[data-theme="dark"]` attribute.

## BMAD Agent System

Primary plugin system. Agents in `.opencode/agent/`. Delegate specialized work via Task tool:

- BMAD workspace root for agents/workflows/config: `/home/lpmf-dev/website-/_bmad/`

| Need                  | Agent             | When to use                          |
| --------------------- | ----------------- | ------------------------------------ |
| Architecture decision | `bmm-architect`   | System design, DB schema, API design |
| UI/UX deep work       | `bmm-ux-designer` | Component design, accessibility      |
| Heavy implementation  | `bmm-dev`         | Large features, parallel coding      |
| Requirements analysis | `bmm-analyst`     | Spec refinement, acceptance criteria |
| Quality assurance     | `bmm-qa`          | Test strategy, coverage gaps         |

## Documentation Rules

- **DO NOT** create new `.md` files. Exceptions: `README.md`, `report/README.md`, `todos.md`.
- **WALKTHROUGH.md**: Single source of truth. Append with `## [Category]/[Feature]`.
- **todos.md**: Current task tracker. Overwrite per task.

## Key Domain Concepts

- **TestRequest** → **Sample** (with `sample_code`) → **SampleTestProcess** (4 stages: preparation > instrumentation > interpretation > delivery) → **TestResult**
- **Delivery/DeliveryItem**: Sample delivery tracking.
- **Inventory**: Items, Balances, Lots, Locations, Movements, Alerts, Disposal.
- **WhatsApp**: GOWA API integration — broadcasts, templates, reminders, commands.
- **IKU**: Performance index calculation (government KPI system).
- **Numbering**: Auto-generated document codes with repair tools.
- **Permissions**: 33 permissions with role + user-level overrides (spatie/laravel-permission).

## Deployment

Config in `.deploy.local` (gitignored). Ask user if missing.
