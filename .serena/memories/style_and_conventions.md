Languages and frameworks
- Backend: PHP 8.2+, Laravel 12 (see `composer.json`)
- Frontend: Vite, Tailwind CSS, JS/TS with ESLint + Prettier
- Views: Blade templates in `resources/views/*`

PHP style
- Follow PSR-12 via Laravel Pint (`laravel/pint`). Run with `vendor\bin\pint`.
- Controllers in `app/Http/Controllers`, Eloquent models in `app/Models`.
- Validation via `FormRequest` classes in `app/Http/Requests` (e.g., `LoginRequest`, `ProfileUpdateRequest`).
- Routes are organized in `routes/web.php` with groups and named routes. Prefer named route helpers.
- Use dependency injection for controllers/services; keep business logic in dedicated classes where feasible.
- Use Eloquent relationships and query scopes for model logic.

Blade and UI
- Blade views grouped by domain: `requests`, `delivery`, `samples`, `statistics`, etc.; shared layouts in `resources/views/layouts`.
- Prefer Blade components in `resources/views/components` for reusable UI.
- Tailwind utility-first styling; see `tailwind.config.js` and design system docs.
- UI/design system is namespaced for Pusdokkes; see `DESIGN-SYSTEM-README.md` and `README.ui.md`.

JavaScript/TypeScript
- Lint with `npm run audit:eslint` (ESLint 9, TypeScript plugin).
- Format with Prettier (Tailwind plugin enabled). No opinionated prettier script defined; use editor integration.

CSS
- Lint with Stylelint (`npm run audit:stylelint`), Tailwind and performance plugins configured.

Database
- Default connection is PostgreSQL (`DB_CONNECTION=pgsql`). Configure via `.env`.

Naming and structure
- Resourceful controllers for CRUD (`Route::resource`). Named routes follow `domain.action` pattern (e.g., `delivery.complete`).
- Keep Blade files kebab-cased; controllers and models StudlyCase.

Testing
- PHPUnit 11 configured (`phpunit.xml` with Unit and Feature suites). No PHPStan/Psalm present by default.