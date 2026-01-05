# Pre-Push Checklist for Laravel Project

Use this checklist before pushing commits to GitHub. Run each step and verify the criteria.

**Last Verified:** January 5, 2026 (branch: `main`)

---

## 1. Git Hygiene

```bash
git status
git diff --stat
git diff --cached --stat
```

**Criteria:**
- [x] No temporary/log/cache files staged
- [x] Staged changes match intended push (v1.0.5 improvements and bug fixes)

---

## 2. Test & Smoke Checks

```bash
php artisan test             # If Pest/PHPUnit configured
php artisan optimize:clear
php artisan route:list --name=settings
php artisan route:list --name=changelogs
```

**Criteria:**
- [x] Test suite passes: **289 passed, 9 skipped** ✅
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

## Changes Summary (January 5, 2026) - v1.0.5

### 🆕 Improvements

#### Manajemen Staff (Rename dari Manajemen Analis)
- Menu "Analis" → "Staff", "Manajemen analis" → "Manajemen staff"
- Opsi peran baru: `Analis`, `Penyelia`, `Manajer Teknis`
- Files: `AnalystController.php`, `navigation.blade.php`, `analysts/*.blade.php`

#### Label Barang Bukti - Deskripsi Singkat
- Kolom "Penyidik" diganti menjadi "Deskripsi Singkat"
- Files: `LabelController.php`, `evidence-sheet.blade.php`, `evidence-single.blade.php`

#### Identifikasi Sampel Toggle
- Toggle dropdown/textarea untuk field identifikasi sampel di `/samples/test`
- Files: `SampleTestController.php`, `test.blade.php`

#### Auto-fill Data Penyidik/Pemohon
- Dropdown untuk memilih penyidik/pemohon yang sudah terdaftar
- Files: `RequestController.php`, `create.blade.php`

#### Autocomplete Zat Aktif
- Datalist suggestions untuk field zat aktif
- Files: `RequestController.php`, `create.blade.php`

### 🐛 Bug Fixes

- **Stepper Tidak Advance ke Interpretasi**: Fix logika stepper di ProcessController
- **Lokasi Penyimpanan Input Baru**: Combobox toggle di inventory receipt
- **dummy:clear FK Violation**: Gunakan TRUNCATE CASCADE untuk PostgreSQL
- **Penomoran Saat Ini [object Object]**: Fix fetchCurrentNumbering di settings JS

### Files Changed (21 files, +781/-110 lines)
- Controllers: 8 files
- Views: 10 files  
- JS: 1 file
- Config: 2 files (WALKTHROUGH.md, changelogs)

---

## Quick Reference

| Check | Command | Expected |
|-------|---------|----------|
| Git status | `git status --porcelain` | Clean or expected changes |
| Routes | `php artisan route:list` | No missing routes |
| Build | `npm run build` | Success, <5s |
| Secrets | `git ls-files \| grep .env` | Only `.env.example` |
| Endpoints | `curl -w "%{http_code}" <url>` | 200/302, no 500 |
