# Pre-Push Checklist for Laravel Project

Use this checklist before pushing commits to GitHub. Run each step and verify the criteria.

**Last Verified:** December 30, 2025 (branch: `chore/update-dependencies`)

---

## 1. Git Hygiene

```bash
git status
git diff --stat
git diff --cached --stat
```

**Criteria:**
- [x] No temporary/log/cache files staged
- [x] Staged changes match intended push (145 files: settings consolidation, storage, SMTP config, dummy data)

---

## 2. Test & Smoke Checks

```bash
php artisan test             # If Pest/PHPUnit configured
php artisan optimize:clear
php artisan route:list --name=settings
php artisan route:list --name=changelogs
```

**Criteria:**
- [x] Test suite passes: **240 passed, 9 skipped** ✅
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
- [x] Build succeeds ✅ (2.27s)
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
git commit -m "feat: storage consolidation, SMTP config, dummy data seeder"
git push origin chore/update-dependencies
```

**Criteria:**
- [ ] Push succeeds
- [ ] CI pipeline passes (if applicable)

---

## Changes Summary (December 30, 2025)

### Storage Consolidation
- Simplified `filesystems.php` from 8 disks to 4
- Storage driver fixed to `public` only
- Path: `storage/app/public/investigators/{investigator}/{request}/`

### SMTP Configuration
- Added SMTP settings to Notifikasi & Security section
- Presets: Mailpit (dev), Gmail, Custom
- Password stored encrypted in database

### Dummy Data
- Created `DummyDataSeeder.php`
- 3 investigators, 12 test requests, 19 samples
- 8 inventory items, 12 lots, 12 balances

### Tests
- All 240 tests passing
- 9 tests skipped (deprecated features)

---

## Quick Reference

| Check | Command | Expected |
|-------|---------|----------|
| Git status | `git status --porcelain` | Clean or expected changes |
| Routes | `php artisan route:list` | No missing routes |
| Build | `npm run build` | Success, <10s |
| Secrets | `git ls-files \| grep .env` | Only `.env.example` |
| Endpoints | `curl -w "%{http_code}" <url>` | 200/302, no 500 |
