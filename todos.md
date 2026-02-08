# Task Tracker - v1.10.3 Changelog Sync + Deploy

## Implementation

- [x] Update `WALKTHROUGH.md` ke v1.10.3 (Inventory Alerts History + Global Search)
- [x] Update halaman `/changelogs` agar menampilkan v1.10.3 dan v1.10.2
- [x] Jalankan verifikasi minimal (targeted Pest + build/audit bila perlu)
- [ ] Commit + push perubahan dokumentasi/UI changelog
- [ ] Deploy ke production (git pull + migrate + cache)

## Verification

- [x] `php vendor/bin/pest tests/Feature/Inventory/GlobalSearchTest.php tests/Feature/WhatsApp/InventoryAlertsTabTest.php`
- [x] `npm run build`
- [x] `npm run audit:critical`

## Deployment (Production)

- [ ] `ssh <host> "cd <path> && git pull && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache"`
