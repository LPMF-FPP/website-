# AGENTS.md — LPMF LIMS Coding Agent Guide

This guide is for coding agents in this repository. Prioritize correctness, patient-safety domain integrity, and existing conventions over speed.

## Project Snapshot

- Stack: Laravel 12, PHP 8.2+, Blade, Alpine.js 3, Tailwind CSS 3, Vite 7, PostgreSQL
- Domain: forensic pharmaceutical laboratory management (government context)
- Language policy: UI copy in Bahasa Indonesia; code identifiers in English
- Tooling baseline: Node 20+ for audits and frontend tooling

## Execution Profile (Strict Core + Quick Start)

### Strict Core (Non-Negotiable)

- MUST follow instruction priority exactly; do not override higher-priority instructions with lower-priority guidance
- MUST preserve domain integrity and policy/service boundaries for all workflow, inventory, and result-lifecycle changes
- MUST use project-approved tooling flow: semantic discovery first, focused search second, minimal diff implementation third
- MUST invoke skills by task type before implementation; do not apply Next.js/React skills on Laravel-only tasks
- MUST verify each implementation with the smallest relevant command and iterate until passing
- MUST protect secrets at all times; never print raw sensitive values from `.env*`, credentials, or token-like payloads

### Quick Start (8-Step Runbook)

-   1. Read instruction priority and relevant project docs (`AGENTS.md`, optional `.next-docs/`, optional `docs/`)
-   2. Classify task stack first: Laravel/PHP, frontend UI, UI/UX review, or Next.js/React
-   3. Load the right skill set for the classified stack before touching implementation
-   4. Discover context with `morph-mcp_warpgrep_codebase_search`, then narrow with `glob` and `grep`
-   5. Use Context7/Firecrawl when external docs or web evidence are required
-   6. Implement focused changes that preserve existing architecture and naming
-   7. Run the smallest relevant verification command and fix failures immediately
-   8. Report changed paths, verification result, and any remaining risk or follow-up

## Instruction Sources and Priority

1. System/developer/user instructions in the active session
2. This file (`AGENTS.md`)
3. `.next-docs/` (if present, primarily for Next.js projects)
4. `docs/` project guides (if present)
5. `.github/copilot-instructions.md`
6. Cursor rules, if present

- Copilot rules found: `.github/copilot-instructions.md`
- Cursor rules not found: no `.cursor/rules/` and no `.cursorrules`

## Runtime Tooling and Search Rules

- Start broad codebase exploration with `morph-mcp_warpgrep_codebase_search` before narrow file-level lookups
- Use `glob` before opening files; use the `grep` tool for content search (avoid shell `grep` for normal code search)
- Use Context7 for third-party framework/library documentation before proposing unfamiliar APIs
- Use Firecrawl for external web research (`firecrawl_search` first, then `firecrawl_scrape` or `firecrawl_map` as needed)
- After implementation, run the smallest relevant verification command (test/lint/build) and iterate until passing
- Treat `.env*` and secrets as sensitive: never expose raw values in terminal output, logs, or commit content

## MCP Integration Playbook

- Morph MCP: use `morph-mcp_warpgrep_codebase_search` first for semantic discovery, then narrow with `glob`/`grep`
- Context7 MCP: always run `context7_resolve-library-id` before `context7_query-docs`; keep queries specific and avoid generic prompts
- Context7 MCP: do not exceed 3 Context7 calls per question; use the best result if perfect coverage is unavailable
- Firecrawl MCP: default flow is `firecrawl_search` (no scrapeOptions) -> select URL -> `firecrawl_scrape` or `firecrawl_map`
- Firecrawl MCP: add `maxAge` when cached results are acceptable for faster research cycles
- Next.js DevTools MCP: only for Next.js tasks; call `next-devtools_init` first and prefer `nextjs_index`/`nextjs_call` for runtime diagnostics

## Build and Run Commands

```bash
# Safe baseline install (no lifecycle scripts)
composer install --no-plugins --no-scripts && npm ci --ignore-scripts
# Local development
composer run dev
php artisan serve
npm run dev
# Production build
npm run build
```

## Lint and Format Commands

```bash
# PHP format
./vendor/bin/pint
# JS lint (check and fix)
npm run audit:eslint
npx eslint "resources/js/**/*.{js,ts,vue,jsx,tsx}" --fix
# CSS lint (check and fix)
npm run audit:stylelint
npx stylelint "resources/**/*.{css,scss}" "public/**/*.css" "styles/**/*.css" --fix
```

## Test Commands

```bash
# Full suites
npm run test
npm run test:all
npm run test:php
npm run test:e2e
# Useful variants
npm run test:php:watch
npm run test:e2e:headed
```

## Single-Test Quick Reference

```bash
# Pest: by test name
php vendor/bin/pest --filter "InventoryAlertServiceTest"
# Pest: by file
php vendor/bin/pest tests/Feature/Inventory/DashboardTest.php
# Pest: by directory
php vendor/bin/pest tests/Unit/Services/Quality/
# Dusk: single browser file
php artisan dusk tests/Browser/Auth/AuthenticationFlowTest.php
# Dusk: method pattern
php artisan dusk tests/Browser/Auth/AuthenticationFlowTest.php --filter "user_can_login"
# Node test
node --test tests/js/search.test.js
npm run test:search
```

## Audit and Pre-Commit

```bash
npm run audit:guard
npm run audit:critical
npm run audit:all
npm run audit:a11y
```

Recommended pre-commit gate: `npm run test && npm run audit:critical && ./vendor/bin/pint`

## Code Style Guidelines

### General

- Follow existing architecture and naming before introducing new patterns
- Avoid broad refactors in feature PRs; keep diffs focused and reviewable
- Remove dead code and unused imports in touched files
- Preserve module/file structure unless there is a clear maintenance benefit

### PHP / Laravel

- Follow PSR-12 and run `./vendor/bin/pint` on touched files
- Prefer `declare(strict_types=1);` in new PHP files when feasible
- Use explicit return types and typed parameters for non-trivial methods
- Naming: classes `PascalCase`, methods/properties `camelCase`, DB fields `snake_case`
- Keep controllers orchestration-focused; move business logic into services/actions
- Prefer constructor injection and `private readonly` dependencies when practical
- Use Form Requests for complex validation/authorization
- Keep models explicit (`$fillable`, `$casts`, typed relationship methods)
- Prefer policies/permissions over ad-hoc role checks

### JavaScript / Alpine

- Linting source of truth: `eslint.config.cjs` + `.eslintrc.cjs`
- Avoid `eval`, `new Function`, and `var`; prefer `const` then `let`
- Use strict equality (`===`) and explicit branching
- Import order: builtin -> external -> internal -> relative
- Use `Alpine.data()` / `Alpine.store()` registration patterns
- Use Axios via `resources/js/bootstrap.js` for CSRF-safe defaults

### CSS / Tailwind

- Linting source of truth: `.stylelintrc.cjs`
- Keep selector specificity <= `0,4,0`; avoid ID selectors
- Max compound selectors: 4
- Avoid `!important` except controlled utility edge cases
- Animate only `transform` and `opacity`
- Keep custom properties within approved prefixes (`pd-*`, `theme-*`, `color-*`, etc.)
- Respect property ordering and warning-level rules in stylelint
- Safe Mode: `styles/pd-*.css` must not contain layout properties (`margin`, `padding`, `width`, `height`, `position`, `display`, `flex`, `grid`, `gap`, `overflow`, `transform`)

### Imports, Types, Naming, and Formatting

- Keep imports grouped, stable, and duplicate-free
- Prefer explicit typing/signatures in PHP and TS where already used
- Do not introduce new global helpers unless justified and documented
- Keep formatting automated (Pint/ESLint/Stylelint), avoid manual style churn

### Error Handling and Security

- Fail closed on authorization and validation failures
- Keep middleware protections intact (`auth`, `verified`, `permission`, `throttle`)
- Never log secrets, tokens, raw credentials, or sensitive payloads
- Wrap external provider calls with retries/guards and structured error handling
- Use mocks/fakes or sandbox endpoints by default for third-party integrations
- Treat export/download endpoints as sensitive and enforce policy-scoped access

## Documentation Rules

- Do not create ad-hoc documentation markdown files
- Use `WALKTHROUGH.md` for implementation notes and walkthroughs
- Allowed standalone markdown files: `README.md`, `PRE_PULL_CHECKLIST.md`, `PRE_PUSH_CHECKLIST.md`, `report/README.md`, `.github/copilot-instructions.md`

## Skill Invocation Guidance

- For Laravel feature/bugfix work, MUST load and follow `laravel-11-12-app-guidelines`
- For frontend implementation tasks, MUST load `frontend-design` first; keep visual language consistent with existing app patterns
- If acting as `frontend-developer` subagent, first action MUST be loading `frontend-design`
- For UI/UX review or design refinement, use `ui-ux-pro-max` and `web-design-guidelines` when relevant
- For Next.js or React tasks only, MUST use `next-best-practices` and `vercel-react-best-practices` before implementation
- Keep skill usage task-scoped; do not force Next.js skills on Laravel-only tasks

## Installed Skills Inventory

- Core project: `laravel-11-12-app-guidelines`, `frontend-design`, `web-design-guidelines`, `tailwind-design-system`
- UI/UX and content: `ui-ux-pro-max`, `copywriting`
- Discovery and setup: `find-skills`
- Next/React specialized: `next-best-practices`, `vercel-react-best-practices` (use only when task stack is Next.js/React)

## Web Interface Guidelines (Condensed)

- MUST provide full keyboard access and visible focus states; never remove focus outlines without a replacement
- MUST use semantic interactive elements (`button`, `a`, `label`, `input`) before ARIA workarounds
- MUST keep touch targets usable (minimum 24px desktop, 44px mobile)
- MUST keep forms resilient: allow paste, preserve input state, show inline errors, and focus first invalid field on submit
- MUST keep navigation state in URL where relevant (filters, tabs, pagination) and preserve back/forward behavior
- MUST provide confirmation or undo for destructive actions
- MUST respect `prefers-reduced-motion` and animate only `transform`/`opacity`
- MUST avoid layout jank: explicit media dimensions, skeletons that match final layout, and no avoidable CLS
- MUST design for empty, sparse, dense, and error states (no dead-end screens)
- MUST ensure accessible labeling (`aria-label` for icon-only buttons, `aria-hidden` for decorative icons)
- MUST ensure readable contrast and avoid color-only status communication
- MUST handle long/short user-generated content safely (`min-w-0`, wrapping, truncation where appropriate)
- SHOULD optimize perceived performance (lazy-load below fold, preload critical assets, keep mutations responsive)
- SHOULD validate desktop/mobile/ultra-wide layouts and prevent accidental overflow/scrollbars

## Domain and Secrets Guardrails

- Preserve lifecycle integrity: `TestRequest -> Sample -> SampleTestProcess -> TestResult`
- Treat inventory mutations, numbering, and workflow transitions as high risk
- Do not bypass service or policy layers for convenience
- Keep WhatsApp/GOWA flows idempotent, auditable, and retry-safe
- Deployment config lives in `.deploy.local` (gitignored)
- Never print, commit, or request raw secrets in chat/logs/PR text
- Use placeholders for secrets, e.g. `DB_PASSWORD=<provided-by-user>`
- Redact credential-like output as `***REDACTED***`
