# Pre-Pull Gate Checklist

> **Tujuan**: Memastikan `git pull` berjalan aman tanpa konflik atau kehilangan perubahan lokal.  
> **Last Updated**: 2026-01-05  
> **Last Verified**: ✅ Production-Ready (v1.0.5)

---

## 🔍 Current Environment Status

| Item | Status | Version/Value |
|------|--------|---------------|
| Laravel | ✅ | 12.31.1 |
| PHP | ✅ | 8.3.29 |
| Node.js/NPM | ✅ | 11.7.0 |
| Git Branch | ✅ | `main` (up to date) |
| Latest Commit | ✅ | v1.0.5 - improvements and bug fixes |
| Tests | ✅ | 289 passed, 9 skipped |
| Working Tree | ✅ | Clean |

---

## 📋 Checklist Eksekusi

### 1️⃣ Validasi Kondisi Kerja Lokal

**Command:**
```bash
git status
```

**Kriteria PASS:**
- Working tree bersih (`nothing to commit, working tree clean`)
- ATAU perubahan sudah siap di-commit/stash

**Jika working tree kotor:**
```bash
# Opsi A: Commit perubahan lokal
git add -A
git commit -m "wip: local changes before pull"

# Opsi B: Stash perubahan
git stash push -u -m "wip before pull $(date +%Y%m%d-%H%M)"
```

**File yang sering kotor tapi aman di-restore:**
- `storage/framework/views/*.php` (compiled views)
- `storage/logs/*.log`

---

### 2️⃣ Pastikan Branch Benar

**Command:**
```bash
git rev-parse --abbrev-ref HEAD
git branch -a
```

**Kriteria PASS:**
- Berada di branch yang memang ingin di-update
- Biasanya: `main` atau feature branch yang aktif

**⚠️ Jika di branch salah:**
```bash
git checkout <branch-yang-benar>
```

---

### 3️⃣ Fetch & Cek Diverge

**Command:**
```bash
git fetch --all --prune
git status -sb
git log --oneline --decorate --graph --max-count=10
```

**Kriteria PASS:**
- Memahami status: `[behind X]`, `[ahead Y]`, atau `[ahead X, behind Y]`
- Jika `behind` saja → aman untuk fast-forward
- Jika `ahead` saja → perlu push dulu atau rebase
- Jika `diverged` → perlu strategi merge/rebase dengan hati-hati

**Status yang mungkin:**
| Status | Arti | Aksi |
|--------|------|------|
| `[behind 5]` | Remote punya 5 commit baru | Pull aman |
| `[ahead 3]` | Local punya 3 commit belum push | Push dulu atau rebase |
| `[ahead 2, behind 3]` | Diverged | Perlu rebase/merge manual |

---

### 4️⃣ Pilih Strategi Pull

**Default (rebase untuk history rapi):**
```bash
git config pull.rebase true  # Set permanen (sekali saja)
git pull --rebase
```

**Alternatif (jika repo butuh merge commit):**
```bash
git pull
```

**Kriteria PASS:**
- Pull selesai tanpa konflik
- Muncul pesan `Fast-forward` atau `Rebasing...done`

---

### 5️⃣ Jika Terjadi Konflik

**Untuk Rebase:**
```bash
# 1. Selesaikan konflik di editor
# 2. Stage file yang sudah diperbaiki
git add <conflicted-files>
# 3. Lanjutkan rebase
git rebase --continue

# Untuk membatalkan:
git rebase --abort
```

**Untuk Merge:**
```bash
# 1. Selesaikan konflik di editor
# 2. Stage dan commit
git add <conflicted-files>
git commit -m "merge: resolve conflicts from origin/main"
```

**⚠️ File Rawan Konflik:**
| File | Alasan |
|------|--------|
| `composer.lock` | Dependency versions |
| `package-lock.json` / `pnpm-lock.yaml` | JS dependencies |
| `database/migrations/*` | Urutan migrasi |
| `resources/views/layouts/*.blade.php` | Shared layouts |
| `config/*.php` | Configuration |
| `routes/web.php`, `routes/api.php` | Route definitions |

**Resolve konflik lockfile:**
```bash
# Untuk composer.lock - accept remote lalu regenerate
git checkout --theirs composer.lock
composer install

# Untuk pnpm-lock.yaml
git checkout --theirs pnpm-lock.yaml
pnpm install
```

---

### 6️⃣ Setelah Pull: Dependency & Migrasi

**Cek apa yang berubah:**
```bash
# Lihat file yang berubah dari pull
git diff HEAD~<n> HEAD --name-only | grep -E "(composer|package|pnpm)"
git diff HEAD~<n> HEAD --name-only | grep migrations/
```

**Jika `composer.lock` berubah:**
```bash
composer install
```

**Jika lockfile JS berubah:**
```bash
npm ci
# atau
pnpm install
```

**Jika ada migration baru:**
```bash
php artisan migrate
# Production:
php artisan migrate --force
```

---

### 7️⃣ Bersihkan Cache Laravel

**Command:**
```bash
php artisan optimize:clear
# atau minimal:
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

**Jika view:clear error (folder hilang):**
```bash
mkdir -p storage/framework/views
php artisan view:clear
```

---

### 8️⃣ Smoke Test Cepat

**Command:**
```bash
# Run test suite
php artisan test

# Cek route kritikal
php artisan route:list | grep -E "settings|documents|api"
```

**Kriteria PASS:**
- Test hijau (atau hanya fail karena konfigurasi environment)
- Route kritikal tersedia

---

### 9️⃣ Jika Stash Dipakai

**Restore stash:**
```bash
git stash list
git stash pop
```

**Jika konflik saat pop:**
```bash
# Selesaikan konflik seperti biasa
git add <files>
git stash drop  # Hapus stash yang sudah di-pop
```

---

## 📊 Template Laporan Pre-Pull

```
=== PRE-PULL GATE REPORT ===
Date: YYYY-MM-DD HH:MM
Branch: <branch-name>

[✅/❌] 1. Working tree clean
[✅/❌] 2. Correct branch
[✅/❌] 3. Fetch & diverge understood (behind: X, ahead: Y)
[✅/❌] 4. Pull completed
[✅/❌] 5. No conflicts (or resolved)
[✅/❌] 6. Dependencies installed
[✅/❌] 7. Migrations run
[✅/❌] 8. Cache cleared
[✅/❌] 9. Smoke test passed
[✅/❌] 10. Stash restored (if applicable)

Files changed: <count>
New migrations: <count>
Issues to address:
- <issue 1>
- <issue 2>
```

---

## 🚀 Quick Command Sequence (Happy Path)

```bash
# Full pre-pull gate sequence
git status
git stash push -u -m "wip before pull $(date +%Y%m%d-%H%M)"  # if dirty
git fetch --all --prune
git status -sb
git pull --rebase
php artisan migrate
php artisan optimize:clear
php artisan test --stop-on-failure
git stash pop  # if stashed
```

---

## 📝 Notes

- Selalu review output `git status -sb` sebelum pull
- Jika diverged, diskusikan strategi dengan tim
- Untuk production, selalu backup database sebelum migrate
- File di `storage/framework/` boleh di-restore karena auto-generated

---

## 🚨 Production Deployment Notes

### Recent Changes (December 31, 2025)
1. **Test Isolation Fixes**: Fixed 6 failing tests with proper database cleanup
   - `SettingsWriterNullTest`: Clean seeded settings in beforeEach
   - `LhuNumberingGenerationTest`: Use dot-notated settings keys
   - `RegistrationTest` / `ProfileTest`: Use unique emails
   - `SamplePhotoUploadTest`: Re-enable middleware for validation tests
2. **App Branding**: Updated app title to "LIMS" with Pusdokkes Polri logo
3. **Timezone Settings**: Added WIB timezone configuration

### Previous Changes (December 30, 2025)
1. **Storage Consolidation**: Single `public` disk untuk semua dokumen
2. **SMTP Configuration**: Dapat dikonfigurasi via Settings UI
3. **Dummy Data Seeder**: `php artisan db:seed --class=DummyDataSeeder` (DEV only)

### Pre-Production Checklist
- [ ] Backup database sebelum migrate
- [ ] Set `APP_ENV=production` dan `APP_DEBUG=false`
- [ ] Run `php artisan config:cache` dan `php artisan route:cache`
- [ ] Verify SMTP credentials di Settings → Notifikasi & Security
- [ ] Test email delivery dengan tombol "Test Email"

### Environment Variables Required
```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:... (generated)
DB_CONNECTION=pgsql
MAIL_MAILER=smtp
```
