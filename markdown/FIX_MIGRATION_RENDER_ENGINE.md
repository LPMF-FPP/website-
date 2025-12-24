# Panduan Perbaikan Migration Error - Production

## ❌ Error yang Terjadi

```
SQLSTATE[42703]: Undefined column: 7 ERROR: column "render_engine" does not exist
Migration: 2025_02_14_000000_update_document_template_render_engine
```

## 🔍 Penyebab

Migration mencoba **update column `render_engine`** tapi column belum ada di table `document_templates`.

Table `document_templates` dibuat di migration `2025_10_09_000000_create_system_settings_tables.php` **tanpa column `render_engine`**.

## ✅ Solusi Perbaikan

### Langkah 1: Rollback Migration yang Gagal

Di server production:

```bash
cd /var/www/lis

# Rollback hanya migration yang gagal
php artisan migrate:rollback --step=1
```

Atau jika sudah failed dan stuck:

```bash
# Cek status migrations
php artisan migrate:status

# Rollback semua dan mulai dari awal
php artisan migrate:reset
```

### Langkah 2: Update Code Lokal

Di local development:

```bash
cd /home/lpmf-dev/website-

# Migration baru sudah dibuat:
# database/migrations/2025_02_13_000000_add_render_engine_to_document_templates.php
# (nama tanggal 13 agar sebelum 14 saat sorting)

# Verify file exists
ls -la database/migrations/2025_02_13_000000_*
```

### Langkah 3: Commit dan Push

```bash
cd /home/lpmf-dev/website-

# Add migration baru
git add database/migrations/2025_02_13_000000_add_render_engine_to_document_templates.php

# Commit
git commit -m "fix(migrations): add render_engine column before update migration"

# Push ke main
git push origin main
```

### Langkah 4: Deploy ke Production

Di server production:

```bash
cd /var/www/lis

# Pull code terbaru (termasuk migration baru)
git pull origin main

# Jalankan migrations dengan urutan benar
php artisan migrate --force
```

**Output yang diharapkan:**

```
INFO  Running migrations.

  2025_02_13_000000_add_render_engine_to_document_templates ........ 123ms DONE
  2025_02_14_000000_update_document_template_render_engine ........ 456ms DONE
```

### Langkah 5: Verifikasi

```bash
# Check database status
php artisan tinker

# Di Tinker:
>>> DB::table('document_templates')->getConnection()->getSchemaBuilder()->getColumnListing('document_templates')
# Harus menunjukkan column 'render_engine'

>>> exit
```

## 📝 Checklist

- [ ] Rollback migration yang gagal (jika perlu)
- [ ] Verifikasi file `2025_02_13_000000_add_render_engine_to_document_templates.php` ada di production
- [ ] Jalankan `php artisan migrate --force`
- [ ] Verifikasi column `render_engine` ada di table
- [ ] Verifikasi aplikasi berjalan normal

## 🚀 Mencegah di Masa Depan

1. **Test migrations sebelum production:**
   ```bash
   php artisan migrate:refresh --seed  # Local
   ```

2. **Gunakan Schema::hasColumn()** untuk defensive checks:
   ```php
   if (!Schema::hasColumn('document_templates', 'render_engine')) {
       // tambah column
   }
   ```

3. **Order migrations dengan tanggal yang benar:**
   - Add column migration: `2025_02_13_...` (ADD)
   - Update column migration: `2025_02_14_...` (UPDATE)

---

## 📞 Support

Jika ada masalah:

```bash
# Check migrations
php artisan migrate:status

# See recent logs
tail -f storage/logs/laravel.log
```
