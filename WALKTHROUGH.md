# WALKTHROUGH - LPMF LIMS v1.0.5

> **Laboratory Information Management System untuk Laboratorium Pengujian Mutu Farmasi**
> **Dokumen ini menggabungkan PRD (Product Requirements) dan ERD (Entity Relationship)**

---

## 📝 Changelog

### v1.0.5 (5 Januari 2026)

#### 🆕 Improvements

- **Manajemen Staff (Rename dari Manajemen Analis)**: Menu "Analis" di navigasi diganti menjadi "Staff" dan "Manajemen analis" menjadi "Manajemen staff". Halaman index, create, dan edit diperbarui.
  - **Perubahan Peran**: Opsi peran di form create/edit sekarang adalah: `Analis`, `Penyelia`, `Manajer Teknis` (sebelumnya: analyst, lab_analyst, petugas_lab).
  - **File terpengaruh**:
    - `app/Http/Controllers/AnalystController.php` - Update `$analystRoles` array dan success messages
    - `resources/views/layouts/navigation.blade.php` - Menu "Staff" dan "Manajemen staff"
    - `resources/views/analysts/index.blade.php` - Title "Manajemen Staff", "Daftar Staff"
    - `resources/views/analysts/create.blade.php` - Title "Tambah Staff"
    - `resources/views/analysts/edit.blade.php` - Title "Ubah Data Staff"

- **Label Barang Bukti - Ganti Kolom Penyidik dengan Deskripsi Singkat**: Pada template label barang bukti (sheet dan single), kolom "Penyidik" diganti menjadi "Deskripsi Singkat" untuk menampilkan deskripsi sampel yang lebih informatif.
  - **Fitur**: Label sekarang menampilkan `short_description` dari sampel, bukan nama penyidik.
  - **File terpengaruh**:
    - `app/Http/Controllers/LabelController.php` - `buildLabelPayload()` sekarang return `deskripsi_singkat`
    - `resources/views/labels/evidence-sheet.blade.php` - Field "Deskripsi Singkat"
    - `resources/views/labels/evidence-single.blade.php` - Field "Deskripsi Singkat"
    - `app/Http/Controllers/Api/Settings/BladeTemplateEditorController.php` - Preview data untuk template editor

- **Identifikasi Sampel - Toggle Dropdown dan Input Baru**: Di halaman Pengujian Sampel (`/samples/test`), field "Identifikasi Sampel / Barang Bukti" sekarang memiliki dua pilihan input:
  - **Fitur**: Radio button toggle antara "Pilih yang sudah ada" (dropdown dari database) dan "Input baru" (textarea manual).
  - **Behavior**: Jika sudah ada identifikasi di database, user dapat memilih dari dropdown. Jika baru, user dapat input manual via textarea.
  - **File terpengaruh**:
    - `app/Http/Controllers/SampleTestController.php` - Query existing `physical_identification` dari samples table
    - `resources/views/samples/test.blade.php` - UI toggle dengan JavaScript untuk sync nilai

- **Auto-fill Data Penyidik/Pemohon**: Di halaman Buat Permintaan (`/permintaan/buat`), jika penyidik atau pemohon non-Polri sudah pernah mengajukan permintaan sebelumnya, mereka dapat memilih nama dari dropdown untuk auto-fill semua data (NRP, pangkat, satuan, telepon, alamat, dll).
  - **Fitur**: Dropdown "Pilih Data Penyidik yang Sudah Terdaftar" untuk Polri, dan "Pilih Pemohon yang Sudah Terdaftar" untuk non-Polri.
  - **Behavior**: Pilih dari dropdown untuk auto-fill, atau pilih "-- Input Data Baru --" untuk input manual.
  - **File terpengaruh**:
    - `app/Http/Controllers/RequestController.php` - Menambahkan query untuk existing investigators dan externals
    - `resources/views/requests/create.blade.php` - UI dropdown dengan auto-fill JavaScript

- **Autocomplete Zat Aktif**: Field "Zat Aktif" di form sampel sekarang mendukung autocomplete dari zat aktif yang sudah pernah diinput sebelumnya.
  - **Fitur**: Menggunakan HTML5 `<datalist>` untuk menampilkan suggestions dari zat aktif yang sudah ada di database.
  - **Behavior**: User dapat memilih dari suggestions atau mengetik zat aktif baru.
  - **File terpengaruh**:
    - `app/Http/Controllers/RequestController.php` - Query unique active substances
    - `resources/views/requests/create.blade.php` - Datalist dan input dengan list attribute

#### 🐛 Bug Fixes

- **Stepper Tidak Advance ke Interpretasi**: Fix bug di halaman Detail Proses (`/proses/{id}`) dimana stepper tidak menampilkan tahap "Interpretasi" ketika semua proses "Preparasi Sampel" dan "Pengujian Instrumen" telah selesai. 
  - **Root cause**: Logika `resolveStepperStage()` di `ProcessController.php` mengecek apakah ada proses di suatu stage (started atau completed), tapi tidak mempertimbangkan bahwa jika semua proses completed maka harus advance ke stage berikutnya.
  - **Fix**: Memisahkan pengecekan proses in-progress dan completed. Jika semua proses instrumentation completed, stepper sekarang akan menampilkan "Interpretasi" sebagai tahap berikutnya.
  - **File terpengaruh**: `app/Http/Controllers/ProcessController.php`

- **Lokasi Penyimpanan Tidak Bisa Input Baru**: Fix bug di halaman Penerimaan Stok (`/referensi/inventori/transaksi/receipt`) dimana field "Lokasi Penyimpanan" hanya berupa dropdown dan tidak bisa menginput lokasi baru.
  - **Root cause**: Field lokasi hanya menggunakan `<select>` dengan opsi dari database, tidak ada opsi untuk menambah lokasi baru secara inline.
  - **Fix**: Mengubah field lokasi menjadi combobox dengan radio button toggle antara "Lokasi yang ada" (dropdown) dan "Lokasi baru" (text input + tipe lokasi), mirip dengan pola yang sudah ada untuk field Lot.
  - **File terpengaruh**: 
    - `resources/views/inventory/transactions/receipt.blade.php` - UI dengan toggle mode
    - `app/Http/Controllers/Inventory/TransactionController.php` - Backend logic untuk create lokasi baru

- **Artisan dummy:clear Gagal dengan Foreign Key Violation**: Fix bug pada command `php artisan dummy:clear` yang gagal dengan error `SQLSTATE[23503]: Foreign key violation` karena PostgreSQL FK constraints.
  - **Root cause**: Menggunakan Eloquent `Model::query()->delete()` tidak bisa menangani FK constraints yang kompleks di PostgreSQL. Bahkan dengan `SET CONSTRAINTS ALL DEFERRED`, masih ada masalah timing dengan child records.
  - **Fix**: Menggunakan raw SQL `TRUNCATE TABLE ... CASCADE` yang secara native PostgreSQL menangani FK constraints dengan cascade delete semua child records.
  - **File terpengaruh**: `app/Console/Commands/ClearDummyData.php`

- **Penomoran Saat Ini Menampilkan [object Object]**: Fix bug pada halaman `/settings` bagian "Penomoran Saat Ini" yang menampilkan `[object Object]` dan tombol Refresh tidak berfungsi dengan benar.
  - **Root cause**: Backend API `/api/settings/numbering/current` mengembalikan objek `{ current, next, pattern }` untuk setiap scope, tapi frontend JavaScript mengasumsikan response berupa string langsung.
  - **Fix**: Update `fetchCurrentNumbering()` di `resources/js/pages/settings/index.js` untuk mengekstrak nilai `next` atau `current` dari objek response.
  - **File terpengaruh**: `resources/js/pages/settings/index.js`

- **Penamaan Dokumen Sesuai Penomoran Otomatis**: Dokumen yang digenerate (BA Penerimaan, BA Penyerahan, LHU) sekarang menggunakan nomor dari sistem Penomoran Otomatis di `/settings` untuk nama file.
  - **Sebelumnya**: Nama file menggunakan `request_number` (contoh: `BA-Penerimaan-2026-01-05-0001.pdf`)
  - **Sekarang**: Nama file menggunakan nomor dokumen dari scope yang sesuai (contoh: `BA-2026-01-0001-ba-penerimaan.pdf` sesuai pattern `BA/{YYYY}/{MM}/{SEQ:4}`)
  - **Mapping scope**:
    - `ba_penerimaan` → scope `ba`
    - `ba_penyerahan` → scope `ba_penyerahan`
    - `lhu` / `laporan_hasil_uji` → scope `lhu`
  - **File terpengaruh**:
    - `app/Services/DocumentService.php` - Tambah method `issueDocumentNumber()`, `previewDocumentNumber()`, `generateDocumentBaseName()`
    - `app/Http/Controllers/RequestController.php` - Generate BA Penerimaan menggunakan numbering
    - `app/Http/Controllers/DeliveryController.php` - Generate BA Penyerahan menggunakan numbering
    - `app/Http/Controllers/SampleTestProcessController.php` - LHU sudah menggunakan numbering, update baseName

---

### v1.0.4 (3 Januari 2026)

#### 🔍 Audit Besar Codebase

**Tanggal Audit:** 3 Januari 2026

##### 1. Audit Kata "Pelanggan" → "User"

| File | Line | Teks Ditemukan | Status |
|------|------|----------------|--------|
| `resources/views/settings/partials/iku.blade.php` | 149 | "Pelanggan harus mengisi survey..." | ⚠️ Perlu diganti |
| `resources/views/settings/partials/iku.blade.php` | 177 | "survey kepuasan pelanggan" | ⚠️ Perlu diganti |
| `resources/views/changelogs/index.blade.php` | 53 | "survey kepuasan pelanggan" | ⚠️ Perlu diganti |
| `resources/views/sample-processes/report-lhu.blade.php` | 95,97,98 | "Informasi Pelanggan", "Nama Pelanggan", "Alamat Pelanggan" | ⚠️ Perlu diganti |
| `resources/views/delivery/survey.blade.php` | 5,59 | "Survei Kepuasan Pelanggan" | ⚠️ Perlu diganti |
| `resources/views/pdf/ba-penyerahan.blade.php` | 343 | "Pelanggan" | ⚠️ Perlu diganti |
| `scripts/generate_ba_penyerahan_summary.py` | 73,133 | "nama_pelanggan", "nomor_pelanggan" | ⚠️ Perlu diganti |
| `app/Http/Controllers/RequestController.php` | 759 | "Hapus survey pelanggan" | ⚠️ Perlu diganti |
| `templates/ba_penyerahan_ringkasan.html.j2` | 49 | "Pelanggan" | ⚠️ Perlu diganti |
| `templates/laporan_hasil_uji.html.j2` | 91,94,95 | "Informasi Pelanggan", "Nama Pelanggan", "Alamat Pelanggan" | ⚠️ Perlu diganti |
| `output/laporan-hasil-uji/*.html` | 87,90,91 | Generated output (akan regenerate) | ℹ️ Auto-fix |

**Total:** 19 kemunculan kata "pelanggan" di 11 file

##### 2. Audit Kata "Narokita" & "Psikotropika"

| Kata | Hasil | Status |
|------|-------|--------|
| `narokita` | ❌ Tidak ditemukan | ✅ Aman |
| `psikotropika` | ✅ 8 kemunculan | ⚠️ Perlu review |

**Detail kemunculan "psikotropika":**
| File | Konteks | Status |
|------|---------|--------|
| `WALKTHROUGH.md` | Referensi tabel enum | ℹ️ Dokumentasi |
| `database/seeders/LabelTestSeeder.php` | sample_category enum | ⚠️ Review |
| `database/factories/SampleFactory.php` | sample_category enum | ⚠️ Review |
| `database/seeders/DummyDataSeeder.php` | 2 occurrences | ⚠️ Review |
| `database/migrations/2025_09_29_044652_create_samples_table.php` | enum definition | ⚠️ Review |
| `app/Support/TemplatePreviewData.php` | jenis sample | ⚠️ Review |
| `app/Http/Controllers/Api/Settings/BladeTemplateEditorController.php` | preview data | ⚠️ Review |

**Catatan:** "Psikotropika" adalah istilah teknis legal/farmasi. Jika perlu dihilangkan, perlu migration database.

##### 3. Audit File Inactive/Orphaned

**🔴 CRITICAL - Hapus Segera:**
| File | Alasan | Prioritas |
|------|--------|-----------|
| `siap-dihapus-2025-12-23/er->role = 'admin';` | Filename corrupted | 🔴 CRITICAL |
| `siap-dihapus-2025-12-23/mcp-server.log` | Runtime log | 🔴 CRITICAL |
| `siap-dihapus-2025-12-23/mcp-server.prev.log` | Runtime log | 🔴 CRITICAL |
| `siap-dihapus-2025-12-23/test.php` | Debug script | 🔴 CRITICAL |

**🟡 HIGH - Aman Dihapus:**
| File | Alasan | Prioritas |
|------|--------|-----------|
| `siap-dihapus-2025-12-23/test-null-removal.php` | Debug script | 🟡 HIGH |
| `siap-dihapus-2025-12-23/REFACTORED_METHODS.php` | Old reference | 🟡 HIGH |
| `siap-dihapus-2025-12-23/test-preview-debug.php` | Debug script | 🟡 HIGH |
| `resources/views/_unused/dashboard.dynamic.backup.blade.php` | Backup file | 🟡 HIGH |
| `resources/views/_unused/welcome.blade.php` | Unused view | 🟡 HIGH |

**🟢 KEEP - Masih Digunakan:**
| File | Alasan |
|------|--------|
| `test-safe-overlay.html` | Referenced in DESIGN-SYSTEM-README |
| `design-system-demo.html` | Design reference |
| `theme-demo.html` | Theme reference |

##### 4. Audit Folder Inactive/Orphaned

**🔴 HAPUS - Folder Tidak Aktif:**
| Folder | Size | Status | Alasan |
|--------|------|--------|--------|
| `siap-dihapus-2025-12-23/` | ~1 MB | 🔴 HAPUS | Staging folder for deletion |
| `script sh/` | 76 KB | 🔴 HAPUS/RENAME | Space in name, unused scripts |
| `resources/views/_unused/` | 8 KB | 🔴 HAPUS | Explicitly marked unused |

**🟡 REORGANIZE - Perlu Cleanup:**
| Folder | Size | Status | Rekomendasi |
|--------|------|--------|-------------|
| `markdown-backup-20251230/` | 1.2 MB | 🟡 ARCHIVE | Move to archive atau hapus |
| `md-backup-20251230/` | ~500 KB | 🟡 ARCHIVE | Duplicate backup, hapus |
| `temp/` | 8 KB | 🟡 KEEP | Theme build workflow |
| `output/` | 1.6 MB | 🟡 KEEP | Generated docs, add to .gitignore |

**✅ AKTIF - Jangan Disentuh:**
| Folder | Purpose |
|--------|---------|
| `app/` | PHP application code |
| `resources/` | Views, CSS, JS |
| `routes/` | Route definitions |
| `database/` | Migrations, seeders |
| `config/` | Configuration |
| `public/` | Web assets |
| `storage/` | Laravel storage |
| `scripts/` | Build utilities |
| `templates/` | Document templates |
| `tests/` | Test files |
| `docs/` | Documentation |
| `dokpol-style/` | Design system |

##### 📋 Deprecated Code Found

| File | Line | Keterangan |
|------|------|------------|
| `app/Models/TestRequest.php` | 85-95 | `generateRequestNumber()` deprecated, gunakan NumberingService |
| `resources/views/components/stage-tabs.blade.php` | 1 | Deprecated, gunakan `<x-tabs>` |
| `scripts/generate_laporan_hasil_uji.py` | 121,154 | `--api` flag deprecated |

##### 🎯 Action Items - COMPLETED ✅

| Action | Status | Tanggal |
|--------|--------|---------|
| Hapus folder `siap-dihapus-2025-12-23/` | ✅ Done | 3 Jan 2026 |
| Hapus folder `script sh/` | ✅ Done | 3 Jan 2026 |
| Hapus folder `resources/views/_unused/` | ✅ Done | 3 Jan 2026 |
| Hapus folder `markdown-backup-20251230/` | ✅ Done | 3 Jan 2026 |
| Hapus folder `md-backup-20251230/` | ✅ Done | 3 Jan 2026 |
| Hapus kata "psikotropika" dari codebase | ✅ Done | 3 Jan 2026 |

**Files Updated untuk hapus 'psikotropika':**
- `database/migrations/2025_09_29_044652_create_samples_table.php` - Hapus dari enum
- `database/migrations/2026_01_03_*_remove_psikotropika_*.php` - Migration baru
- `database/seeders/LabelTestSeeder.php` - Ganti dengan 'narkotika'
- `database/seeders/DummyDataSeeder.php` - Ganti dengan 'narkotika'
- `database/factories/SampleFactory.php` - Hapus dari enum list
- `app/Support/TemplatePreviewData.php` - Ganti dengan 'Narkotika'
- `app/Http/Controllers/Api/Settings/BladeTemplateEditorController.php` - Ganti dengan 'Narkotika'

**Pending Action (Optional):**
- Update kata "pelanggan" → "user" (belum dilakukan, perlu konfirmasi)

---

### v1.0.3 (3 Januari 2026)

#### 🆕 Fitur Baru

**1. Sistem IKU (Indeks Kinerja Utama) - Full Implementation**
- Dashboard card IKU menggantikan card "SLA Performance"
- Halaman Settings dengan konfigurasi bobot, target sampel per tahun, dan periode
- Preview IKU real-time dengan data mentah, formula, dan skala kategori
- Penjelasan komprehensif variabel A-F di panel preview

**2. DummyDataSeeder Enhancements**
- Pembuatan dokumen LHU (Laporan Hasil Uji) otomatis via `createLhuDocuments()`
- Pembuatan CustomerSurvey untuk testing via `createCustomerSurveys()`
- Fix enum constraint untuk `request_type` dan `respondent_job_category`
- Fix unique constraint `sample_code` dengan timestamp suffix

**3. Clear Dummy Data Command**
- Artisan command baru: `php artisan dummy:clear`
- Opsi `--force` untuk skip konfirmasi
- Menghapus semua data dari DummyDataSeeder secara aman

**4. Admin User Persistence**
- `AdminUserSeeder` dipanggil dari `DatabaseSeeder`
- User admin tidak hilang setelah migration/seeding
- Default credentials: `labmutufarmapol@gmail.com` / `LPMFjaya1`

#### 🐛 Bug Fixes

- **Double JSON.stringify**: Menghapus `JSON.stringify()` redundan di `saveIkuSettings()` - penyebab data IKU tidak tersimpan
- **Tambah Tahun Bug**: Fix `addIkuTargetYear()` yang mengubah object menjadi array dengan validasi `Array.isArray()`
- **IKU Samples Count 0**: Fix `getSamplesCompletedCount()` untuk mengenali status 'ready_for_delivery', 'interpretation_done', 'tested', 'completed'
- **IKU LHU Count 0**: Fix `getLhuIssuedCount()` untuk mengenali document type 'laporan_hasil_uji' dan 'lhu'

#### 🎨 UI Improvements

- Preview IKU dengan penjelasan komprehensif variabel (A = Permohonan dikerjakan, dst)
- Tampilan formula perhitungan R, P, L, S dengan nilai aktual
- Skala kategori IKU (A: >4.00, B: >3.00, dst)
- Card dashboard IKU dengan warna sesuai kategori

#### 📦 Database/Seeder Changes

- `DatabaseSeeder.php` memanggil `AdminUserSeeder` untuk memastikan admin user persist
- `DummyDataSeeder.php` support LHU dan Survey creation

#### 📁 Files Changed

| File | Change |
|------|--------|
| `app/Services/IkuService.php` | Fixed getSamplesCompletedCount, getLhuIssuedCount |
| `app/Console/Commands/ClearDummyData.php` | **NEW** - Clear dummy data command |
| `database/seeders/DummyDataSeeder.php` | Added LHU + Survey creation |
| `database/seeders/DatabaseSeeder.php` | Added AdminUserSeeder call |
| `resources/js/pages/settings/alpine-component.js` | Fixed double stringify, addIkuTargetYear |
| `resources/views/settings/partials/iku.blade.php` | Comprehensive preview descriptions |
| `resources/views/dashboard/_iku-card.blade.php` | IKU dashboard card |

#### 📋 Artisan Commands

```bash
# Seed dummy data (requests, samples, LHU, surveys)
php artisan db:seed --class=DummyDataSeeder

# Clear dummy data
php artisan dummy:clear
php artisan dummy:clear --force  # Skip confirmation
```

---

### v1.0.2 (2 Januari 2026)

#### 🆕 Fitur Baru

**1. Process Controller Refactor**
- New dedicated `ProcessController.php` for unified sample process workflows
- Improved route organization in `routes/web.php`
- Better separation of concerns between test and process controllers

**2. Recent Requests Tracking**
- New `RecentRequest` model untuk tracking aktivitas terbaru
- Tabel `recent_requests` baru untuk menyimpan riwayat akses
- Enhanced `TestRequest` model dengan relationships baru

**3. Sample Process UI Improvements**
- Improved create, edit, index, and show views untuk sample-processes
- Enhanced navigation layout

#### 📦 Database Changes

```sql
-- Migration: 2026_01_07_000000_create_recent_requests_table
CREATE TABLE recent_requests (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    test_request_id BIGINT REFERENCES test_requests(id) ON DELETE CASCADE,
    accessed_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 📁 Files Changed

| File | Change |
|------|--------|
| `app/Http/Controllers/ProcessController.php` | **NEW** - Process workflow controller |
| `app/Http/Controllers/SampleTestController.php` | Updated process handling |
| `app/Http/Controllers/SampleTestProcessController.php` | Updated process handling |
| `app/Models/RecentRequest.php` | **NEW** - Recent request tracking model |
| `app/Models/TestRequest.php` | Added recent requests relationship |
| `resources/views/layouts/navigation.blade.php` | Enhanced navigation |
| `resources/views/requests/index.blade.php` | UI improvements |
| `resources/views/requests/show.blade.php` | UI improvements |
| `resources/views/sample-processes/*.blade.php` | Updated all views |
| `resources/views/samples/test.blade.php` | UI improvements |
| `routes/web.php` | New process routes |
| `vite.config.js` | Build configuration updates |

---

### v1.0.1 (31 Desember 2025)

#### 🆕 Fitur Baru

**1. Multi-Suspect Support**
- Mendukung multi tersangka per permohonan (tidak lagi terbatas 1 tersangka)
- Tabel `suspects` baru dengan relasi ke `test_requests`
- Dynamic add/remove tersangka di form create dan edit
- Backward compatibility: tersangka pertama tetap disimpan ke kolom legacy `test_requests.suspect_*`

**2. Non-Polri Investigator Support**
- Pertanyaan "Apakah Anda penyidik?" toggle antara form Polri dan non-Polri
- Kolom baru di `investigators`: `is_polri`, `institution`, `occupation`, `alt_phone`
- Synthetic NRP untuk non-Polri dengan format `EXT-XXXXXXXX`

**3. Improved Suspect Display**
- Halaman index: Menampilkan tersangka pertama + "+N tersangka lainnya"
- Halaman detail: Card-style display untuk semua tersangka dengan badge nomor urut

#### 🐛 Bug Fixes

- Fixed `deleteDocument()` method using undefined `$request->id` instead of `$testRequest->id`

#### 🎨 UI Improvements

- Form Data Tersangka di-redesign dengan styling oranye dan numbered badges
- Section tersangka sekarang full-width (tidak lagi cramped di grid)
- Tombol Hapus dengan icon SVG yang lebih jelas
- Removed: Kolom "Alamat Tersangka" dari form

#### 📦 Database Changes

```sql
-- Migration: add_external_fields_to_investigators
ALTER TABLE investigators ADD COLUMN is_polri BOOLEAN DEFAULT TRUE;
ALTER TABLE investigators ADD COLUMN institution VARCHAR(255);
ALTER TABLE investigators ADD COLUMN occupation VARCHAR(255);
ALTER TABLE investigators ADD COLUMN alt_phone VARCHAR(50);

-- Migration: create_suspects_table
CREATE TABLE suspects (
    id BIGSERIAL PRIMARY KEY,
    test_request_id BIGINT REFERENCES test_requests(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    gender VARCHAR(20),
    age SMALLINT,
    order_no INT DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 📁 Files Changed

| File | Change |
|------|--------|
| `app/Models/Suspect.php` | **NEW** - Multi-suspect model |
| `app/Models/TestRequest.php` | Added `suspects()` relationship |
| `app/Models/Investigator.php` | Added external fields |
| `app/Http/Controllers/RequestController.php` | Updated store/update/edit methods |
| `resources/views/requests/create.blade.php` | New suspect UI, external form |
| `resources/views/requests/edit.blade.php` | Same updates as create |
| `resources/views/requests/index.blade.php` | Multi-suspect display |
| `resources/views/requests/show.blade.php` | Card-style suspect display |
| `resources/js/pages/requests-form.js` | **NEW** - Dynamic suspect handling |
| `vite.config.js` | Added new JS entry |

---


## 📋 Daftar Isi

1. [Ringkasan Produk](#ringkasan-produk)
2. [Arsitektur Sistem](#arsitektur-sistem)
3. [Entity Relationship Diagram (ERD)](#entity-relationship-diagram-erd)
4. [Modul & Fitur](#modul--fitur)
5. [Alur Kerja (Workflow)](#alur-kerja-workflow)
6. [API Endpoints](#api-endpoints)
7. [Konfigurasi & Deployment](#konfigurasi--deployment)
8. [Panduan Pengembangan](#panduan-pengembangan)

---

## Ringkasan Produk

### Tujuan
LPMF LIMS adalah sistem manajemen informasi laboratorium yang dirancang untuk:
- Mengelola **permohonan pengujian** dari penyidik kepolisian
- Melacak **sampel barang bukti** (narkotika dan zat terlarang)
- Menghasilkan **dokumen resmi** (Berita Acara, Laporan Hasil Uji)
- Mengelola **inventaris laboratorium** (reagen, consumables)
- Menyediakan **dashboard analitik** untuk monitoring kinerja

### Tech Stack
| Layer | Teknologi | Versi |
|-------|-----------|-------|
| Backend | Laravel (PHP) | 12.x (PHP 8.3+) |
| Frontend | Blade + Alpine.js + Tailwind CSS | Alpine 3.x, Tailwind 3.x |
| Database | PostgreSQL | 16+ |
| Build Tool | Vite | 7.x |
| PDF Generation | DomPDF | barryvdh/laravel-dompdf ^3.1 |
| Template Editor | Blade Editor | Native inline editor |
| Queue | Laravel Queue | Database driver |
| Audit Tools | Puppeteer + Lighthouse + axe-core | Development only |

---

## Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────┐
│                        LPMF LIMS                            │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         │
│  │  Dashboard  │  │  Requests   │  │  Samples    │         │
│  │  Controller │  │  Controller │  │  Controller │         │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘         │
│         │                │                │                 │
│  ┌──────┴────────────────┴────────────────┴──────┐         │
│  │              Service Layer                     │         │
│  │  ┌──────────────┐  ┌──────────────────┐       │         │
│  │  │ Numbering    │  │ Document         │       │         │
│  │  │ Service      │  │ Generation       │       │         │
│  │  └──────────────┘  └──────────────────┘       │         │
│  └───────────────────────┬───────────────────────┘         │
│                          │                                  │
│  ┌───────────────────────┴───────────────────────┐         │
│  │              Model Layer (Eloquent)            │         │
│  │  TestRequest │ Sample │ Document │ Investigator│        │
│  └───────────────────────┬───────────────────────┘         │
│                          │                                  │
│  ┌───────────────────────┴───────────────────────┐         │
│  │              PostgreSQL Database               │         │
│  └───────────────────────────────────────────────┘         │
└─────────────────────────────────────────────────────────────┘
```

---

## Entity Relationship Diagram (ERD)

### Core Entities

```
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│   investigators  │       │   test_requests  │       │     samples      │
├──────────────────┤       ├──────────────────┤       ├──────────────────┤
│ id               │       │ id               │       │ id               │
│ name             │──┐    │ request_number   │    ┌──│ test_request_id  │
│ rank             │  │    │ receipt_number   │    │  │ sample_code      │
│ nrp              │  └───>│ investigator_id  │<───┘  │ short_description │
│ jurisdiction     │       │ user_id          │       │ sample_category  │
│ phone            │       │ suspect_name     │       │ sample_form      │
│ email            │       │ case_number      │       │ sample_weight    │
│ address          │       │ status           │       │ sample_status    │
│ folder_key       │       │ submitted_at     │       │ received_at      │
└──────────────────┘       │ received_at      │       │ tested_by        │
                           │ completed_at     │       │ test_methods     │
                           └────────┬─────────┘       │ active_substance │
                                    │                 └────────┬─────────┘
                                    │                          │
                           ┌────────┴─────────┐       ┌────────┴─────────┐
                           │    documents     │       │   test_results   │
                           ├──────────────────┤       ├──────────────────┤
                           │ id               │       │ id               │
                           │ investigator_id  │       │ sample_id        │
                           │ test_request_id  │       │ tested_by        │
                           │ document_type    │       │ test_method      │
                           │ filename         │       │ active_substances│
                           │ file_path        │       │ test_conclusion  │
                           │ generated_by     │       │ qc_approved      │
                           └──────────────────┘       └──────────────────┘
```

### Inventory Entities

```
┌──────────────────┐       ┌──────────────────┐       ┌──────────────────┐
│ inventory_items  │       │  inventory_lots  │       │inventory_movements│
├──────────────────┤       ├──────────────────┤       ├──────────────────┤
│ id               │       │ id               │       │ id               │
│ item_type        │──────>│ item_id          │<──────│ item_id          │
│ name             │       │ lot_number       │       │ lot_id           │
│ brand            │       │ expiry_date      │       │ movement_type    │
│ uom              │       │ initial_qty      │       │ quantity         │
│ min_stock        │       │ current_qty      │       │ reference_type   │
│ is_hazardous     │       └──────────────────┘       │ performed_by     │
│ storage_condition│                                   └──────────────────┘
└──────────────────┘
```

### Delivery & Handover

```
┌──────────────────┐       ┌──────────────────┐
│    deliveries    │       │  delivery_items  │
├──────────────────┤       ├──────────────────┤
│ id               │       │ id               │
│ request_id       │──────>│ delivery_id      │
│ delivered_by     │       │ sample_id        │
│ delivery_date    │       │ quantity         │
│ status           │       │ notes            │
│ collected_at     │       └──────────────────┘
└──────────────────┘
```

### Entity Relationships

| Parent | Child | Type | Description |
|--------|-------|------|-------------|
| `investigators` | `test_requests` | 1:N | Satu penyidik bisa punya banyak permohonan |
| `test_requests` | `samples` | 1:N | Satu permohonan bisa punya banyak sampel |
| `test_requests` | `documents` | 1:N | Satu permohonan bisa punya banyak dokumen |
| `test_requests` | `deliveries` | 1:N | Satu permohonan bisa punya banyak pengiriman |
| `samples` | `test_results` | 1:1 | Satu sampel punya satu hasil uji |
| `samples` | `sample_test_processes` | 1:N | Satu sampel melalui banyak tahap proses |
| `deliveries` | `delivery_items` | 1:N | Satu delivery punya banyak item |
| `inventory_items` | `inventory_lots` | 1:N | Satu item punya banyak lot/batch |
| `inventory_lots` | `inventory_movements` | 1:N | Satu lot punya banyak pergerakan stok |

---

## Modul & Fitur

### 1. Modul Permohonan Pengujian (`/requests`)

**Entitas:** `TestRequest`

| Field | Type | Description |
|-------|------|-------------|
| `request_number` | string | Nomor BA otomatis (format: BA/LPMF/XII/2025/001) |
| `receipt_number` | string | Nomor resi tracking |
| `investigator_id` | FK | Referensi ke penyidik |
| `suspect_name` | string | Nama tersangka |
| `case_number` | string | Nomor LP perkara |
| `status` | enum | pending → received → testing → completed |

**Fitur:**
- ✅ CRUD permohonan pengujian
- ✅ Upload surat resmi & foto barang bukti
- ✅ Generate Berita Acara Penerimaan (PDF)
- ✅ Tracking status realtime
- ✅ Penomoran otomatis per scope

---

### 2. Modul Sampel/Barang Bukti (`/samples`)

**Entitas:** `Sample`

| Field | Type | Description |
|-------|------|-------------|
| `sample_code` | string | Kode sampel unik |
| `sample_category` | enum | narkotika, obat, kosmetik, makanan_minuman |
| `sample_form` | enum | crystal, powder, tablet, liquid, plant |
| `sample_weight` | decimal | Berat bruto (gram) |
| `net_weight` | decimal | Berat netto (gram) |
| `sample_status` | enum | received → testing → completed |

**Kategori Sampel:**
| Category | Description |
|----------|-------------|
| `narkotika` | Narkotika |
| `prekursor` | Prekursor |
| `zat_adiktif` | Zat Adiktif |
| `obat_keras` | Obat Keras |
| `other` | Lainnya |

**Status Flow:**
```
received → preparation → instrumentation → reporting → completed → delivered
```

---

### 3. Modul Pengujian (`/sample-processes`)

**Entitas:** `SampleTestProcess`, `TestResult`

**Tahapan Pengujian:**

| Stage | Description |
|-------|-------------|
| `preparation` | Preparasi sampel (penimbangan, pelarutan) |
| `instrumentation` | Analisis instrumen (GCMS, HPLC, UV-Vis) |
| `reporting` | Pembuatan laporan hasil |

**Fitur:**
- ✅ Input hasil identifikasi fisik
- ✅ Input hasil uji GCMS/instrumen
- ✅ Upload kromatogram & spektrum
- ✅ QC approval workflow
- ✅ Generate Laporan Hasil Uji (LHU)

---

### 4. Modul Penyerahan (`/delivery`)

**Entitas:** `Delivery`, `DeliveryItem`

**Status Delivery:**600
| Status | Description |
|--------|-------------|
| `pending` | Menunggu penyerahan |
| `ready` | Siap diserahkan |
| `delivered` | Sudah diserahkan |
| `collected` | Sudah diambil penyidik |

**Fitur:**
- ✅ Daftar sampel siap diserahkan
- ✅ Generate Berita Acara Penyerahan
- ✅ Survey kepuasan pelayanan
- ✅ Mark as collected

---

### 5. Modul Inventaris (`/referensi/inventori`)

**Entitas:** `InventoryItem`, `InventoryLot`, `InventoryMovement`, `InventoryBalance`

**Item Types:**
| Type | Description |
|------|-------------|
| `REAGENT` | Reagen kimia |
| `CONSUMABLE` | Bahan habis pakai (BHP) |
| `STANDARD` | Standar referensi |
| `CONTROL` | Kontrol kualitas |

**Movement Types:**
| Type | Description |
|------|-------------|
| `RECEIPT` | Penerimaan barang |
| `ISSUE` | Pengeluaran/pemakaian |
| `ADJUSTMENT` | Penyesuaian stok |
| `TRANSFER` | Transfer antar lokasi |

**Fitur:**
- ✅ Master data item (reagen, consumable, standar)
- ✅ Lot/batch tracking dengan expiry date
- ✅ Stock in/out movements
- ✅ Low stock alerts (min_stock)
- ✅ Storage condition tracking

---

### 6. Modul Dokumen & Template

**Entitas:** `Document`, `DocumentTemplate`

**Jenis Dokumen:**
| Type | Description |
|------|-------------|
| `berita_acara_penerimaan` | BA saat terima barang bukti |
| `berita_acara_penyerahan` | BA saat serah terima hasil |
| `laporan_hasil_uji` | LHU resmi laboratorium |
| `request_letter_receipt` | Tanda terima surat permohonan |
| `sample_receipt` | Tanda terima sampel |

**Template Engine:**
- **Blade Editor** - Inline code editor untuk template
- **Blade** - Server-side rendering
- **DomPDF** - PDF generation (barryvdh/laravel-dompdf)

**Penomoran Otomatis:**
| Scope | Format Example |
|-------|----------------|
| `ba` | BA/LPMF/XII/2025/001 |
| `lhu` | LHU/LPMF/XII/2025/001 |
| `tracking` | LPMF-20251230-0001 |

---

### 7. Modul Pengaturan (`/settings`)

**Entitas:** `SystemSetting`

**Grup Pengaturan:**
| Group | Settings |
|-------|----------|
| `general` | Nama lab, alamat, kontak |
| `documents` | Format penomoran, template default |
| `branding` | Logo, header, footer |

---

## Alur Kerja (Workflow)

### Alur Utama Pengujian

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  PENYIDIK   │────>│  PENERIMAAN │────>│  PENGUJIAN  │────>│ PENYERAHAN  │
│  Ajukan     │     │  Verifikasi │     │  Analisis   │     │  Serah      │
│  Permohonan │     │  Sampel     │     │  Sampel     │     │  Hasil      │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
      │                   │                   │                   │
      ▼                   ▼                   ▼                   ▼
 TestRequest         BA Penerimaan       TestResult        BA Penyerahan
 + Samples           generated           + LHU             generated
```

### Status Transitions

**TestRequest Status:**
```
pending → received → testing → completed → delivered
```

**Sample Status:**
```
received → preparation → instrumentation → reporting → completed → delivered
```

**Delivery Status:**
```
pending → ready → delivered → collected
```

---

## API Endpoints

### Public Endpoints (Tanpa Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | Landing page |
| GET | `/track` | Form tracking publik |
| POST | `/track` | Submit nomor resi |
| GET | `/track/{number}.json` | API tracking JSON |
| GET | `/health` | Health check |

### Authenticated Endpoints

**Dashboard & Search:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Dashboard utama |
| GET | `/api/dashboard-stats` | Stats JSON |
| GET | `/search` | Halaman pencarian |
| GET | `/search/data` | Search results JSON |

**Requests (Permohonan):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/requests` | List permohonan |
| POST | `/requests` | Create permohonan |
| GET | `/requests/{id}` | Detail permohonan |
| PUT | `/requests/{id}` | Update permohonan |
| DELETE | `/requests/{id}` | Hapus permohonan |
| POST | `/requests/{id}/berita-acara/generate` | Generate BA |
| GET | `/requests/{id}/berita-acara/download` | Download BA PDF |

**Samples & Testing:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/samples/test` | Form input pengujian |
| POST | `/samples/test` | Submit hasil uji |
| GET | `/sample-processes` | List proses |
| GET | `/sample-processes/{id}/lab-report` | Generate LHU |

**Delivery:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/delivery` | Daftar penyerahan |
| GET | `/delivery/{id}` | Detail delivery |
| POST | `/delivery/{id}/complete` | Mark completed |
| POST | `/delivery/{id}/handover/generate` | Generate BA Penyerahan |

**Settings (Admin Only):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/settings` | Halaman pengaturan |
| GET | `/settings/data` | Get all settings |
| POST | `/settings/save` | Save settings |
| GET | `/settings/templates` | Template list |

**Inventory:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/referensi/inventori` | Dashboard inventaris |
| GET | `/referensi/inventori/items` | List items |
| POST | `/referensi/inventori/items` | Create item |
| GET | `/referensi/inventori/lots` | List lots |
| POST | `/referensi/inventori/movements` | Record movement |

---

## Konfigurasi & Deployment

### Environment Variables

```env
# Application
APP_NAME="LPMF LIMS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://lpmf.example.com

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=lpmf_lims
DB_USERNAME=lpmf
DB_PASSWORD=secret

# PDF Generation
PDF_DRIVER=dompdf
DOMPDF_PAPER=a4
DOMPDF_ORIENTATION=portrait

# Queue
QUEUE_CONNECTION=database

# Session & Cache
SESSION_DRIVER=database
CACHE_DRIVER=database
```

### Deployment Checklist

```bash
# 1. Install dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Database
php artisan migrate --force

# 3. Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Storage
php artisan storage:link

# 5. Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Queue Worker (Supervisor)

```ini
[program:lpmf-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/lpmf/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=2
user=www-data
```

---

## Panduan Pengembangan

### Struktur Folder

```
app/
├── Console/Commands/     # Artisan commands
├── Enums/               # Status enums
│   ├── SampleStatus.php
│   ├── DocumentType.php
│   └── DeliveryStatus.php
├── Http/Controllers/    # Request handlers
├── Models/              # Eloquent models
├── Services/            # Business logic
│   ├── NumberingService.php
│   └── DocumentGenerationService.php
└── View/Components/     # Blade components

resources/views/
├── components/          # Reusable UI (buttons, cards, modals)
├── requests/           # Request CRUD views
├── samples/            # Sample views
├── delivery/           # Delivery views
├── settings/           # Settings views
├── inventory/          # Inventory views
└── layouts/            # App layout (navigation, footer)

database/
├── migrations/         # Schema changes
└── seeders/           # Test data
```

### Konvensi Kode

| Type | Convention | Example |
|------|------------|---------|
| Model | Singular PascalCase | `TestRequest`, `Sample` |
| Controller | Resource pattern | `RequestController` |
| View folder | kebab-case | `sample-processes/` |
| Route name | dot notation | `requests.store` |
| Enum | PascalCase | `SampleStatus::RECEIVED` |

### Menambah Fitur Baru

```bash
# 1. Buat migration
php artisan make:migration create_new_feature_table

# 2. Buat model
php artisan make:model NewFeature

# 3. Buat controller
php artisan make:controller NewFeatureController --resource

# 4. Daftarkan route di routes/web.php

# 5. Buat views di resources/views/new-feature/

# 6. Update WALKTHROUGH.md ini (JANGAN buat file .md baru!)
```

---

## 📊 Sistem IKU (Indeks Kinerja Utama)

> Ditambahkan: Januari 2025

### Gambaran Umum

Sistem IKU menghitung indeks kinerja laboratorium dengan 4 komponen berbobot:

| Komponen | Kode | Formula | Default Bobot |
|----------|------|---------|---------------|
| Registrasi Permohonan | R | A / B | 10% |
| Pemeriksaan Laboratorium | P | C / D | 40% |
| Laporan Hasil | L | E / A | 40% |
| Survei Kepuasan | S | F / A | 10% |

**Variabel:**
- A = jumlah permohonan dikerjakan
- B = jumlah permohonan diterima
- C = jumlah sampel dikerjakan
- D = target sampel (konfigurasi per tahun)
- E = jumlah laporan diterbitkan
- F = jumlah survey diterima

**Formula IKU:**
```
IKU = (R × WR + P × WP + L × WL + S × WS) × 5
```
Hasil: Indeks 0-5 dengan kategori: Sangat Baik, Baik, Cukup, Kurang, Sangat Kurang.

### File Terkait

| File | Fungsi |
|------|--------|
| `app/Services/IkuService.php` | Service untuk komputasi dan konfigurasi IKU |
| `app/Http/Controllers/Api/Settings/IkuSettingsController.php` | API endpoint IKU |
| `app/Http/Requests/Settings/IkuSettingsRequest.php` | Request validation |
| `resources/views/settings/partials/iku.blade.php` | Blade partial untuk halaman settings |
| `resources/views/dashboard/_iku-card.blade.php` | Card IKU di dashboard |
| `resources/js/pages/settings/alpine-component.js` | Alpine component (saveIkuSettings, ensureIkuDefaults) |

### API Endpoints

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/settings/iku` | Get konfigurasi IKU |
| PUT | `/api/settings/iku` | Update konfigurasi IKU |
| GET | `/api/settings/iku/preview` | Preview perhitungan IKU bulan ini |

### Konfigurasi di Database

Pengaturan IKU disimpan di tabel `system_settings` dengan key prefix `iku.`:

```
iku.enabled = true/false
iku.period_mode = 'monthly'/'yearly'
iku.weights.registration = 10
iku.weights.lab_exam = 40
iku.weights.report = 40
iku.weights.survey = 10
iku.target_samples_by_year = {"2025": 500, "2026": 600}
iku.sources.A = 'requests_completed_count'
iku.survey_required_for_delivery = true
```

### Troubleshooting

**Settings tidak tersimpan dari UI:**
- Pastikan frontend di-build: `npm run build`
- Cek browser console untuk error JavaScript
- Verifikasi endpoint `/api/settings/iku` menerima request

**Nilai selalu default:**
- Cek database: `SELECT * FROM system_settings WHERE key LIKE 'iku.%';`
- Gunakan tinker untuk test: `app(IkuService::class)->getConfig()`

---

## Storage Cleanup

```
Source: Updated on 2025-01-04
```

### Deskripsi

Fitur pembersihan storage untuk menghapus file-file yang tidak terpakai:
1. **Folder Investigator Orphan**: Folder dari investigator yang sudah dihapus dari database
2. **Dokumen Duplikat**: Dokumen generated yang sama untuk satu request (duplicate timestamps)

### Artisan Commands

```bash
# Hapus folder investigator yang orphan (tidak ada di database)
php artisan storage:cleanup-investigators --dry-run  # Preview
php artisan storage:cleanup-investigators --force    # Execute

# Hapus dokumen duplikat (simpan hanya yang terbaru)
php artisan storage:cleanup-duplicates --dry-run     # Preview  
php artisan storage:cleanup-duplicates --force       # Execute
```

### API Endpoints

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/settings/documents/cleanup-stats` | Statistik folder orphan & dokumen duplikat |
| POST | `/api/settings/documents/cleanup-orphaned` | Hapus folder investigator orphan |
| POST | `/api/settings/documents/cleanup-duplicates` | Hapus dokumen duplikat |

### UI di Settings

Fitur cleanup tersedia di halaman **Settings > Manajemen Dokumen** section "Pembersihan Storage":
1. Klik "Perbarui Statistik" untuk melihat jumlah file yang bisa dihapus
2. Klik "Hapus Folder Orphan" untuk menghapus folder investigator yang tidak terpakai
3. Klik "Hapus Duplikat" untuk menghapus dokumen duplikat (hanya yang terbaru dipertahankan)

### Files Terkait

- `app/Console/Commands/CleanupOrphanedInvestigatorFolders.php`
- `app/Console/Commands/CleanupDuplicateDocuments.php`
- `app/Http/Controllers/Api/Settings/DocumentMaintenanceController.php`
- `resources/views/settings/partials/documents.blade.php`
- `resources/js/pages/settings/index.js`

---

## ⚠️ Aturan Dokumentasi

### JANGAN BUAT FILE .md BARU

Semua dokumentasi project harus ditambahkan ke file `WALKTHROUGH.md` ini.

Untuk menambah dokumentasi baru, tambahkan section di bagian bawah file ini.

### File Exception (boleh terpisah):
- `README.md` - Untuk GitHub
- `PRE_PULL_CHECKLIST.md` - Checklist sebelum pull
- `PRE_PUSH_CHECKLIST.md` - Checklist sebelum push
- `report/README.md` - Audit system docs
- `.github/copilot-instructions.md` - Copilot config

---

*Dokumen ini terakhir diperbarui: 3 Januari 2026*
