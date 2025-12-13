Purpose
- Web application for Pusdokkes sub-unit operations: manage test requests, sample intake and processing, delivery/handover, and reporting (e.g., LHU and Berita Acara). Includes public tracking, authenticated dashboard, statistics, and survey feedback.

Tech stack
- Laravel 12 on PHP 8.2+
- Blade templates + Tailwind CSS
- Vite asset pipeline (Node.js)
- PostgreSQL as default database (`DB_CONNECTION=pgsql`)
- PHPUnit for testing; Laravel Pint for PHP style
- ESLint, Stylelint, Prettier for frontend quality

Key entrypoints
- HTTP routes in `routes/web.php`
- CLI via `artisan`
- Asset dev/build via `npm run dev|build`

Code structure (rough)
- `app/Models` — Eloquent models: `Request`, `Sample`, `SampleTestProcess`, `Delivery`, `TestResult`, etc.
- `app/Http/Controllers` — Feature controllers: `DashboardController`, `RequestController`, `SampleTestController`, `DeliveryController`, `StatisticsController`, etc.
- `app/Http/Requests` — FormRequest validators: `LoginRequest`, `ProfileUpdateRequest`, etc.
- `resources/views` — Blade views grouped by domain: `requests`, `delivery`, `samples`, `statistics`, plus shared `layouts` and `components`.
- `routes/web.php` — Public landing and tracking; authenticated dashboard; requests CRUD; samples and processes; delivery and handover; statistics.
- `config/database.php` — Default to PostgreSQL; other drivers available.
- Frontend config: `vite.config.js`, `tailwind.config.js`, `.eslintrc.cjs`, `.stylelintrc.cjs`, `postcss.config.js`.

Environment and setup
- Copy `.env.example` to `.env`, set DB credentials, then run `composer install`, `npm ci`, `php artisan key:generate`, `php artisan migrate`, and `npm run dev`.

Notable docs
- See root docs for design and workflows: `DESIGN-SYSTEM-README.md`, `README.ui.md`, `IMPLEMENTATION-SUMMARY.md`, `DOCUMENT-GENERATOR-README.md`, deployment guides, and audit summaries.

Notes
- Some Windows helpers are present (e.g., `sync-public-assets.bat`).
- Tests are scaffolded via `phpunit.xml`; no PHPStan/Psalm configured by default.
- Many domain-specific flows are implemented (handover summaries, LHU generation, sample process forms).