# Pre-Push Checklist for Laravel Project

Use this checklist before pushing commits to GitHub. Run each step and verify the criteria.

---

## 1. Git Hygiene

```bash
git status
git diff --stat
git diff --cached --stat
```

**Criteria:**
- [ ] No temporary/log/cache files staged
- [ ] Staged changes match intended push

---

## 2. Test & Smoke Checks

```bash
php artisan test             # If Pest/PHPUnit configured
php artisan optimize:clear
php artisan route:list --name=settings
php artisan route:list --name=changelogs
```

**Criteria:**
- [ ] Test suite passes (if available)
- [ ] Critical routes exist and not broken

---

## 3. Endpoint Sanity

```bash
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/settings
curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/changelogs
curl -i -H "Accept: application/json" http://127.0.0.1:8000/api/settings
```

**Criteria:**
- [ ] No 500 errors (302/401 OK for auth-guarded routes)
- [ ] API responds correctly

---

## 4. Frontend Build Gate

```bash
npm run build
```

**Criteria:**
- [ ] Build succeeds
- [ ] `node_modules` not in git
- [ ] `public/build` only if required by repo policy

---

## 5. Migration Safety

```bash
php artisan migrate:status
php artisan migrate --pretend  # Preview only
```

**Criteria:**
- [ ] No destructive migrations without justification
- [ ] Migrations run without error

---

## 6. Secrets & Env Leakage Scan

```bash
git ls-files | grep "\.env"
git diff | grep -E "APP_KEY=|PASSWORD=|SECRET|TOKEN|AWS_ACCESS"
```

**Criteria:**
- [ ] `.env` NOT tracked (only `.env.example`)
- [ ] No hardcoded secrets in diff

---

## 7. AuthZ Regression Check

Verify in `routes/web.php`:
- [ ] Settings routes have `middleware('can:manage-settings')`
- [ ] Delete endpoints require policy/authorization

---

## 8. Refactor Regression Check

```bash
grep -r "blade-templates" routes/web.php
grep -r "document-templates" routes/web.php
```

**Criteria:**
- [ ] `/settings/blade-templates` route exists
- [ ] `/settings/document-templates` redirects (if applicable)
- [ ] No stale references in production code

---

## 9. Commit & Push

```bash
git add -A
git commit -m "feat(changelogs): add changelogs page to referensi menu"
git push origin main
```

**Criteria:**
- [ ] Push succeeds
- [ ] CI pipeline passes (if applicable)

---

## Quick Reference

| Check | Command | Expected |
|-------|---------|----------|
| Git status | `git status --porcelain` | Clean or expected changes |
| Routes | `php artisan route:list` | No missing routes |
| Build | `npm run build` | Success, <10s |
| Secrets | `git ls-files \| grep .env` | Only `.env.example` |
| Endpoints | `curl -w "%{http_code}" <url>` | 200/302, no 500 |
