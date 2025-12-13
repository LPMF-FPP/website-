Core setup (Windows)
- Install PHP deps: `composer install`
- Install Node deps: `npm ci` (or `npm install`)
- Copy env: `copy .env.example .env`
- Generate app key: `php artisan key:generate`
- Configure DB in `.env` (default `DB_CONNECTION=pgsql`)
- Run migrations: `php artisan migrate`

Local development
- Run Laravel server: `php artisan serve`
- Run Vite dev server: `npm run dev`
- Build assets for prod: `npm run build`
- Sync public assets (Windows helper): `sync-public-assets.bat`

Quality and formatting
- PHP code style (Pint): `vendor\bin\pint` (or `.\vendorin	ools` resolved by Composer)
- Run PHPUnit tests: `vendor\bin\phpunit`
- JS/TS lint: `npm run audit:eslint`
- CSS lint: `npm run audit:stylelint`
- Full UI/style audits: `npm run audit:all`

Database utilities
- Fresh migrate + seed (if seeds exist): `php artisan migrate:fresh --seed`
- Tinker REPL: `php artisan tinker`

Cache/optimize (when debugging prod issues)
- Clear caches: `php artisan optimize:clear`
- Optimize: `php artisan optimize`

Routing and queues
- Route list: `php artisan route:list`
- If queues used later: `php artisan queue:work`

Testing
- Run all tests: `vendor\bin\phpunit`
- Filter by test name: `vendor\bin\phpunit --filter NameOrClass`

Notes
- Default DB driver is PostgreSQL (see `config/database.php`).
- Node toolchain: Vite + Tailwind; front-end audits via ESLint/Stylelint and custom scripts under `scripts/audit/*`.