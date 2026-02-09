# Current Task: WhatsApp Hub Settings Redesign + Whitelist Manager

## Goals

- Redesign WhatsApp Hub Settings tab into focused horizontal sub-tabs.
- Add Web UI to manage WhatsApp admin whitelist (view/add/remove).
- Update documentation: `WALKTHROUGH.md` and `/changelogs` page.

## Plan

### Batch 1 (Backend + TDD)

1. Add failing feature tests for whitelist endpoints (Pest).
2. Add whitelist routes under `/whatsapp/settings`.
3. Add controller endpoints for whitelist JSON CRUD.

### Batch 2 (Frontend)

4. Rewrite `resources/views/whatsapp/partials/tab-settings.blade.php` into 5 sub-tabs:
    - Quick Test (default)
    - Templates (single-template editor)
    - GOWA
    - Whitelist (new)
    - Alerts
5. Update Alpine state/methods in `resources/views/whatsapp/index.blade.php`.
6. Add cross-link from `resources/views/whatsapp/partials/tab-inventory-alerts.blade.php` to Whitelist tab.

### Batch 3 (Docs + Verification)

7. Update `WALKTHROUGH.md` with new version entry.
8. Update `resources/views/changelogs/index.blade.php` with new card at top.
9. Run quality gates: `npm run test` + `npm run audit:critical` + `./vendor/bin/pint`.
