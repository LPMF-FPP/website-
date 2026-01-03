# Pre-Push Checklist for Laravel Project

Use this checklist before pushing commits to GitHub. Run each step and verify the criteria.

**Last Verified:** January 3, 2026 (branch: `main`)

---

## 1. Git Hygiene

```bash
git status
git diff --stat
git diff --cached --stat
```

**Criteria:**
- [x] No temporary/log/cache files staged
- [x] Staged changes match intended push (IKU feature, Customer Survey, code linting fixes)

---

## 2. Test & Smoke Checks

```bash
php artisan test             # If Pest/PHPUnit configured
php artisan optimize:clear
php artisan route:list --name=settings
php artisan route:list --name=changelogs
```

**Criteria:**
- [x] Test suite passes: **284 passed, 9 skipped** ✅
- [x] Critical routes exist and not broken

---

## 3. Endpoint Sanity

```bash
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/settings
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/changelogs
curl -i -H "Accept: application/json" http://127.0.0.1:8000/api/settings
```

**Criteria:**
- [x] No 500 errors (302/401 OK for auth-guarded routes)
- [x] API responds correctly

---

## 4. Frontend Build Gate

```bash
npm run build
```

**Criteria:**
- [x] Build succeeds ✅ (3.34s)
- [x] `node_modules` not in git
- [x] `public/build` only if required by repo policy

---

## 5. Migration Safety

```bash
php artisan migrate:status
php artisan migrate --pretend  # Preview only
```

**Criteria:**
- [x] No destructive migrations without justification
- [x] All migrations ran successfully

---

## 6. Secrets & Env Leakage Scan

```bash
git ls-files | grep "\.env"
git diff | grep -E "APP_KEY=|PASSWORD=|SECRET|TOKEN|AWS_ACCESS"
```

**Criteria:**
- [x] `.env` NOT tracked (only `.env.example`, `.env.testing`)
- [x] No hardcoded production secrets in diff
- [x] `.env.testing` uses placeholder password

---

## 7. AuthZ Regression Check

Verify in `routes/web.php`:
- [x] Settings routes have `middleware('can:manage-settings')`
- [x] Delete endpoints require policy/authorization

---

## 8. Refactor Regression Check

```bash
grep -r "blade-templates" routes/web.php
grep -r "document-templates" routes/web.php
```

**Criteria:**
- [x] `/settings/blade-templates` route exists
- [x] `/settings/document-templates` redirects to blade-templates
- [x] No stale references in production code

---

## 9. Commit & Push

```bash
git add -A
git commit -m "feat: IKU feature, Customer Survey model, Survey Export, code linting"
git push origin main
```

**Criteria:**
- [ ] Push succeeds
- [ ] CI pipeline passes (if applicable)

---

## Changes Summary (January 3, 2026)

### IKU (Indeks Kinerja Utama) Feature
- New `IkuService.php` for calculating IKU (performance index)
- New `IkuSettingsController.php` API controller
- New `IkuSettingsRequest.php` form request validation
- Settings partial: `iku.blade.php` with preview and configuration UI
- IKU Settings test suite: `IkuSettingsTest.php`

### Customer Survey Model & Export
- New `CustomerSurvey.php` model with validation methods
- Migration: `2025_10_02_000000_create_customer_surveys_table`
- New `CustomerSurveyExport.php` for Excel export
- New `SurveyExportController.php` with date filtering
- Route: `GET /reports/surveys/export`

### Code Quality & Linting Fixes
- Import statement sorting (alphabetical order)
- Whitespace cleanup in routes/web.php
- Arrow function formatting: `fn() =>` to `fn () =>`
- String concatenation standardization: `. ` spacing
- Removed deprecated files from `siap-dihapus-2025-12-23/`
- PHP-CS-Fixer compliant test files

### Tests
- All 284 tests passing (increased from 245)
- 9 tests skipped (deprecated features)
- New test coverage for IKU feature

---

## Quick Reference

| Check | Command | Expected |
|-------|---------|----------|
| Git status | `git status --porcelain` | Clean or expected changes |
| Routes | `php artisan route:list` | No missing routes |
| Build | `npm run build` | Success, <10s |
| Secrets | `git ls-files \| grep .env` | Only `.env.example` |
| Endpoints | `curl -w "%{http_code}" <url>` | 200/302, no 500 |
