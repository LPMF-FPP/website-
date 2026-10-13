# Technology Reality Review — Inventory Spine

**Review date:** 2026-09-25
**Scope:** Repository architecture, declared stack, test configuration, and inventory database readiness claims.

## Spine availability

The exact spine referenced by the earlier report is present and readable at:

`/home/lpmf-dev/website-/_bmad-output/planning-artifacts/architecture/architecture-inventory-desktop-2026-09-24/ARCHITECTURE-SPINE.md`

Its front matter identifies it as a feature-level `architecture-spine`, paradigm `Laravel layered monolith`, status `draft`, created and updated 2026-09-24. The design describes a desktop-first inventory scope using Blade/Alpine, controllers, Form Requests, policies, services, Eloquent, and PostgreSQL (lines 1–8, 29–41). It is a design/requirements artifact, not evidence that the described feature architecture or deployment configuration is fully implemented.

## Repository facts

| Concern | Repository evidence | Finding |
|---|---|---|
| PHP and Laravel | `composer.json` requires PHP `^8.2` and `laravel/framework` `^12.0`; `composer.lock` resolves Laravel to `v12.44.0`. | PHP is a Composer platform constraint, not an observed runtime version. Laravel `12.44.0` is the locked dependency resolution, not proof of the deployed version. |
| Frontend/runtime libraries | `package.json` declares Alpine.js `^3.4.2`, Tailwind CSS `^3.4.18`, Vite `^7.0.4`, React/React DOM `^18.3.1`, TipTap `^3.19.0`, and Axios `^1.11.0`. `package-lock.json` resolves Alpine.js `3.15.0`, Tailwind `3.4.18`, Vite `7.3.5`, and ESLint `10.8.0`. | These are declared dependencies and lock resolutions. React and TipTap are available dependencies; their presence alone does not make React the default UI architecture. |
| UI/build architecture | `vite.config.js` loads both `@vitejs/plugin-react` and `laravel-vite-plugin`; Laravel Vite inputs include CSS and JavaScript entry points. `tailwind.config.js` scans Blade and JS/Vue/JSX/TSX paths and uses Tailwind CSS 3 configuration. The spine specifies Blade/Alpine for this feature. | The repository supports Blade-driven pages and React-capable assets. The spine's Blade/Alpine choice is feature-specific architecture intent, consistent with its design, not evidence that all UI is exclusively Blade/Alpine. |
| Lint/tooling accuracy | `package.json` declares ESLint `^10.8.0` and Stylelint `^16.0.0`. `docs/project-context.md:29` says ESLint `^9`. | The project-context ESLint version is stale/inconsistent with the current manifest and lock. |
| Database configuration | `config/database.php:19` defaults to `pgsql` only when `DB_CONNECTION` is unset. It also configures SQLite, MySQL/MariaDB, and SQL Server. | PostgreSQL is Laravel's configuration fallback, not proof of the database selected by a running or deployed environment. |
| Environment templates | `.env.example:73` selects SQLite; lines 80–86 show commented PostgreSQL sample settings. `.env.testing.example:14–20` documents PostgreSQL for tests using placeholder values. | General local sample selects SQLite while test sample recommends a separate PostgreSQL test database. Neither template proves actual runtime/test connectivity. |
| Local environment files | `.env`, `.env.testing`, and additional Dusk/backup environment files are present in the checkout. Their contents were not used as evidence or reproduced in this report. | Local environment files are checkout-specific and may contain credentials; their presence does not establish deployment or successful test execution. |
| PHPUnit | `phpunit.xml:7–14` defines Unit and Feature suites under `tests/Unit` and `tests/Feature`; `:20–33` sets test environment variables and `DB_CONNECTION=pgsql` using ordinary `<env>` entries. | PHPUnit is configured to use PostgreSQL by default for tests, but an externally supplied environment value may take precedence. Configuration is not evidence a test was run or connected to PostgreSQL. |
| Test/quality commands | `composer.json:77–80` runs `php artisan test`; `package.json:20–29` defines `audit:all`, `audit:critical`, Pest, search, and Dusk commands. | These scripts define available commands only. No passing execution, CI requirement, or branch-protection status is established by manifests. |
| Feature database target | Spine AD-12 (lines 131–139) requires deployed PostgreSQL and `pdo_pgsql` for inventory mutation readiness, while allowing SQLite only for tests/workflows that do not claim those guarantees. | This is a feature readiness requirement. The repository config and manifests cannot establish the actual deployed database engine/version or installed PHP extension. |

## Inventory database guard review

Spine AD-3, AD-4, AD-12, and AD-15 prescribe database-enforced ledger immutability, movement shape/quantity checks, balance tuple uniqueness including a null-lot partial index, and canonical lot uniqueness. These are target requirements; the reviewed migrations do not show them implemented.

| Requirement | Migration evidence | Finding |
|---|---|---|
| Canonical lot uniqueness | `database/migrations/2025_12_29_000003_create_inventory_lots_table.php:11–24` creates an ordinary index on `lot_no` and an `(item_id, status)` index. | No functional unique index on `(item_id, UPPER(BTRIM(lot_no)))` is defined in this migration. |
| Movement quantity and endpoint validation | `2025_12_29_000004_create_inventory_movements_table.php:11–32` defines movement type, nullable endpoints, and decimal `qty`, plus lookup indexes. | This migration contains no database CHECK constraint enforcing positive quantity or movement-specific endpoint shape. |
| Append-only movement ledger | The movement migration contains no trigger/function or privilege statements. A search of migration sources found no `CREATE TRIGGER`, `CREATE FUNCTION`, or `CREATE OR REPLACE FUNCTION` definitions. | The reviewed migration set provides no repository evidence for database denial of movement `UPDATE`/`DELETE` by the normal application role. This does not establish the state of any deployed database. |
| Balance tuple uniqueness and quantity bounds | `2025_12_29_000005_create_inventory_balances_table.php:11–21` creates a nullable `lot_id`, decimal quantities, and ordinary unique key `(item_id, lot_id, location_id)`. | PostgreSQL ordinary uniqueness permits multiple null values for `lot_id`; the migration does not define the required null-lot partial unique index, complementary partial index, or quantity CHECK constraints. |

The reviewed inventory migrations are the core lot, movement, and balance table migrations listed above. Migration source inspection cannot prove whether a database has manual schema changes, whether other deploy-time scripts ran, or what privileges exist on a live database.

## Readiness gate evidence

The spine's AD-12 gate is an explicit design rule: mutation mode defaults fail-closed to `disabled`; activation requires an approved complete baseline, named acceptance/readiness evidence, PostgreSQL/`pdo_pgsql`, and verification of database guards (spine lines 131–139 and 223). In the reviewed repository evidence:

- The spine is still marked `draft` in its front matter.
- The reviewed inventory migrations do not implement the required PostgreSQL guard set described above.
- `phpunit.xml` and `.env.testing.example` configure an intended PostgreSQL test target, but no test execution/result or database connection is evidenced here.
- No repository evidence reviewed establishes an applied migration state, PostgreSQL server version, production PHP runtime/extension set, application-role privileges, approved baseline, readiness acceptance, or operational sign-offs.

Accordingly, repository evidence supports the existence of the **specified readiness gate**, but does not support a claim that the gate has been implemented, verified, or passed. Before describing inventory mutations as ready, evidence would need to establish the installed schema and privileges on the target PostgreSQL database, actual `pdo_pgsql` availability for the relevant runtime, PostgreSQL tests of the guards using the normal application role, and the AD-12 baseline/readiness acceptance.

## Deployment and execution claims not established

The reviewed repository files do not establish:

- The deployed release/commit, PHP CLI or web-SAPI version, installed PHP extensions, Node.js runtime, or PostgreSQL server version.
- Which database engine a deployed process currently uses or successful connectivity from the deployed application.
- Whether the migrations have been applied to a target database, whether inventory constraints/triggers exist there, or which privileges the application role has.
- Successful execution of PHPUnit, PostgreSQL guard tests, frontend audits, or any CI workflow; nor whether a check is required by branch protection.
- Baseline reconciliation, readiness acceptance/sign-offs, backup/restore rehearsal, or production deployment status.

## Evidence reviewed

- `/home/lpmf-dev/website-/_bmad-output/planning-artifacts/architecture/architecture-inventory-desktop-2026-09-24/ARCHITECTURE-SPINE.md`
- `composer.json`, `composer.lock`, `package.json`, `package-lock.json`
- `vite.config.js`, `tailwind.config.js`, `postcss.config.js`
- `config/database.php`, `.env.example`, `.env.testing.example`, `phpunit.xml`
- `docs/project-context.md`
- `database/migrations/2025_12_29_000003_create_inventory_lots_table.php`
- `database/migrations/2025_12_29_000004_create_inventory_movements_table.php`
- `database/migrations/2025_12_29_000005_create_inventory_balances_table.php`
- Repository migration search for trigger/function definitions and relevant inventory constraints/indexes
