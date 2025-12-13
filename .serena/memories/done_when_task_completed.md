Before marking a task done, do the following:

Code quality
- Run PHP formatter: `vendor\bin\pint`
- Lint JS/TS: `npm run audit:eslint`
- Lint CSS: `npm run audit:stylelint`

Tests
- Run unit/feature tests: `vendor\bin\phpunit`

Build and runtime sanity
- Build assets (if UI changed): `npm run build`
- Start app locally: `php artisan serve` and smoke test changed routes/UI

Migrations
- If schema changed: run `php artisan migrate` (or `migrate:fresh --seed` if appropriate)

Docs
- Update any relevant docs in the repo (design/system/readme) when changing flows or UI components

Housekeeping
- Clear caches when debugging config/route/view issues: `php artisan optimize:clear`
- Ensure `.env` is not checked in; respect `.editorconfig` and existing lint rules