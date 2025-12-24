# Frontend Refactor: Settings & Request Documents

## 📋 Ringkasan Refactor

Refactor frontend `/settings` dan tab "Dokumen & Lampiran" di detail permintaan menjadi modular, maintainable, dan sesuai dengan wireframe API yang ada. Menghapus semua inline JavaScript dan memindahkannya ke module terpisah dengan reusable `apiFetch` method.

---

## 📁 File yang Diubah/Ditambah

### ✅ File Baru (Ditambahkan)

#### JavaScript Modules
1. **`resources/js/pages/settings/index.js`**
   - SettingsClient class dengan apiFetch method
   - State management untuk semua section (numbering, templates, branding, localization, notifications)
   - Error handling dan 422 validation errors mapping
   - Sanitize payload untuk numeric fields (empty string → null)

2. **`resources/js/pages/requests/documents.js`**
   - RequestDocumentsClient class
   - GET `/api/requests/{id}/documents`
   - DELETE `/api/documents/{id}` tanpa reload
   - Clear preview jika dokumen terpilih dihapus

#### Blade Partials (Settings)
3. **`resources/views/settings/partials/numbering.blade.php`**
   - Section Penomoran Otomatis
   - Test Preview button per scope
   - Refresh current numbering

4. **`resources/views/settings/partials/templates.blade.php`**
   - Upload template .docx (multipart)
   - List templates dengan activate/delete
   - Preview template (buka tab baru)

5. **`resources/views/settings/partials/branding.blade.php`**
   - Branding fields (lab_code, org_name, dll)
   - PDF preview (POST `/api/settings/pdf/preview`)
   - Preview panel dengan iframe

6. **`resources/views/settings/partials/localization-retention.blade.php`**
   - Localization fields (timezone, date_format, dll)
   - Retention fields (storage_folder_path, purge_after_days)
   - Live preview waktu saat ini

7. **`resources/views/settings/partials/notifications-security.blade.php`**
   - Email & WhatsApp test notification
   - Security roles (can_manage_settings, can_issue_number)
   - Inline result dari test

#### Blade Partials (Requests)
8. **`resources/views/requests/partials/documents.blade.php`**
   - Tab Dokumen & Lampiran
   - List documents dengan preview
   - Delete button per dokumen

### ♻️ File yang Diubah (Refactored)

9. **`resources/views/settings/index.blade.php`**
   - **Backup dibuat**: `resources/views/settings/index.blade.php.backup`
   - Direduksi dari 1072 baris → ~150 baris
   - Menggunakan `@include` untuk semua partials
   - Script Alpine diganti dengan import module ES6
   - x-data="settingsPageAlpine" menggunakan SettingsClient instance

10. **`resources/views/requests/show.blade.php`**
    - Section "Dokumen & Lampiran" diganti dengan `@include('requests.partials.documents')`
    - Inline Alpine.data('requestDocuments') diganti dengan RequestDocumentsClient
    - Script direduksi ~150 baris

---

## 🎯 Kebutuhan Wireframe yang Dipenuhi

### On-load (Settings)
- ✅ GET `/api/settings`
- ✅ GET `/api/settings/numbering/current`

### Per Section (Settings)
- ✅ Save (PUT) → disable tombol saat loading
- ✅ Status "Saved" / "Error" inline
- ✅ Error inline dari 422 validation (field: message)

### Numbering
- ✅ Test Preview (POST `/api/settings/numbering/preview`)
- ✅ "Penomoran Saat Ini" panel
- ✅ Tombol Refresh

### Templates
- ✅ Upload .docx (multipart)
- ✅ List templates
- ✅ Activate (PUT)
- ✅ Delete (DELETE)
- ✅ Preview buka tab baru

### Branding & PDF
- ✅ Preview PDF (POST `/api/settings/pdf/preview`)
- ✅ Tampil di panel iframe atau download

### Localization & Retention
- ✅ Field `storage_folder_path` disimpan via PUT `/api/settings/localization-retention`
- ✅ Semua retention fields terkirim

### Notifications & Security
- ✅ Test Email (POST `/api/settings/notifications/test`)
- ✅ Test WhatsApp (POST `/api/settings/notifications/test`)
- ✅ Result inline

### Request Documents Tab
- ✅ GET `/api/requests/{id}/documents`
- ✅ DELETE `/api/documents/{id}` menghapus row tanpa reload
- ✅ Clear preview jika dokumen terhapus

---

## 🔍 Detail Implementasi

### SettingsClient (`resources/js/pages/settings/index.js`)

#### `apiFetch(url, options)`
- Generic fetch wrapper
- Auto handle `Accept: application/json`
- Auto inject CSRF token untuk POST/PUT/PATCH/DELETE
- Support FormData untuk multipart upload
- Parse 422 validation errors → user-friendly message

#### State Management
```javascript
state = {
  pageLoading: true,
  form: { numbering, branding, pdf, locale, retention, notifications },
  currentNumbering: { sample_code, ba, lhu },
  sectionStatus: { numbering: {message, intentClass}, ... },
  sectionErrors: { numbering: '', ... },
  loadingSections: { numbering: false, ... },
  templates: [],
  activeTemplates: {},
  ...
}
```

#### Section Endpoints
- `saveSection(key)` → auto map ke endpoint yang benar
- `numbering` → PUT `/api/settings/numbering`
- `templates` → PUT `/api/settings/templates` (body: `{ active }`)
- `branding` → PUT `/api/settings/branding` + `pdf`
- `localization` → PUT `/api/settings/localization-retention`
- `notifications` → PUT `/api/settings/notifications-security`

#### Sanitize Payload
- Empty string untuk numeric fields (start_from, purge_after_days) → `null`
- Mencegah 422 error "must be integer, string given"

### RequestDocumentsClient (`resources/js/pages/requests/documents.js`)

#### Methods
- `fetchDocuments()` → GET `/api/requests/{id}/documents`
- `deleteDocument(doc)` → DELETE `/api/documents/{id}`
- `selectDocument(doc)` → set preview
- `clearPreview()` → clear selected
- Helper: `documentIsPdf()`, `documentIsImage()`, `documentType()`

---

## 🧪 Verifikasi Manual

### 1. Settings - Numbering

```bash
# 1. Buka halaman settings
# URL: /settings

# 2. Verifikasi on-load:
# - Panel "Penomoran Saat Ini" menampilkan sample_code, ba, lhu
# - Form numbering ter-populate dari DB

# 3. Test Preview:
# - Ubah pattern di salah satu scope (misal sample_code)
# - Klik "Test Preview"
# - Verifikasi contoh hasil muncul di bawah form

# 4. Save:
# - Ubah pattern, reset, start_from
# - Klik "Simpan"
# - Verifikasi status "Pengaturan tersimpan" (hijau)
# - Verifikasi tombol disabled saat loading

# 5. Refresh Current Numbering:
# - Klik tombol "Refresh"
# - Verifikasi panel update tanpa reload

# 6. Error Handling (422):
# - Kosongkan pattern
# - Klik "Simpan"
# - Verifikasi error inline muncul (merah)
```

### 2. Settings - Templates

```bash
# 1. Upload Template:
# - Isi kode & nama template
# - Pilih file .docx
# - Klik "Upload"
# - Verifikasi template muncul di list

# 2. Activate Template:
# - Klik "Aktifkan" pada salah satu template
# - Verifikasi panel "Template Aktif Per Tipe" update

# 3. Preview Template:
# - Pada panel aktif, klik "Preview"
# - Verifikasi tab baru terbuka dengan preview

# 4. Delete Template:
# - Klik "Hapus" pada template
# - Confirm dialog
# - Verifikasi template hilang dari list

# 5. Prompt Activate (Alternative):
# - Pada panel aktif, klik "Ubah"
# - Masukkan kode template
# - Verifikasi template aktif berubah
```

### 3. Settings - Branding & PDF

```bash
# 1. Edit Branding:
# - Isi lab_code, org_name, address, contact
# - Pilih watermark preset
# - Centang "Tampilkan QR pada PDF"

# 2. Preview PDF:
# - Klik "Preview PDF"
# - Verifikasi loading spinner
# - Verifikasi PDF muncul di iframe panel

# 3. Save:
# - Klik "Simpan"
# - Verifikasi status "Pengaturan tersimpan"
```

### 4. Settings - Localization & Retention

```bash
# 1. Ubah Locale:
# - Pilih timezone berbeda
# - Pilih date_format berbeda
# - Verifikasi "Sekarang di..." update live

# 2. Retention Fields:
# - Isi storage_folder_path: "/lims/storage"
# - Isi purge_after_days: 730
# - Isi export_filename_pattern

# 3. Save:
# - Klik "Simpan"
# - Verifikasi status "Pengaturan tersimpan"
# - Verifikasi storage_folder_path tersimpan (cek DB)
```

### 5. Settings - Notifications & Security

```bash
# 1. Email Test:
# - Centang "Aktif" untuk email
# - Isi address default
# - Isi "Target Test Email"
# - Klik "Test Email"
# - Verifikasi result inline (hijau jika sukses)

# 2. WhatsApp Test:
# - Centang "Aktif" untuk WhatsApp
# - Isi nomor default (62...)
# - Isi "Target Test WhatsApp"
# - Klik "Test WhatsApp"
# - Verifikasi result inline

# 3. Security Roles:
# - Centang beberapa role untuk "Boleh Mengelola"
# - Centang beberapa role untuk "Boleh Issue Number"

# 4. Save:
# - Klik "Simpan"
# - Verifikasi status "Pengaturan tersimpan"
```

### 6. Request Documents Tab

```bash
# 1. Buka detail permintaan:
# URL: /requests/{id}

# 2. Scroll ke section "Dokumen & Lampiran"

# 3. Verifikasi Load:
# - List dokumen ter-populate
# - Preview kosong (pilih dokumen untuk melihat)

# 4. Select Document:
# - Klik "Lihat" pada salah satu dokumen
# - Verifikasi row ter-highlight
# - Verifikasi preview muncul di panel kanan
# - PDF → iframe, Image → img tag

# 5. Delete Document:
# - Klik "Hapus" pada dokumen
# - Confirm dialog
# - Verifikasi row hilang tanpa reload halaman
# - Jika dokumen terpilih dihapus, preview clear

# 6. Refresh:
# - Klik "Refresh"
# - Verifikasi list update tanpa reload

# 7. Open in New Tab:
# - Select dokumen
# - Klik "Buka Tab Baru"
# - Verifikasi tab baru terbuka
```

---

## 🐛 Testing Error Scenarios

### 1. 422 Validation Errors

```bash
# Settings - Numbering:
# - Kosongkan pattern
# - Klik "Simpan"
# Expected: Error inline "pattern: The pattern field is required."

# Settings - Localization:
# - Isi purge_after_days dengan string
# Expected: Error inline atau auto-convert (cek sanitizePayload)
```

### 2. Network Errors

```bash
# Matikan server Laravel
# Coba save section apapun
# Expected: Error inline "Gagal menyimpan pengaturan." atau "Request failed..."
```

### 3. Empty Numeric Fields

```bash
# Settings - Numbering:
# - Kosongkan start_from
# - Klik "Simpan"
# Expected: Field terkirim sebagai null (bukan empty string)
# Verifikasi: Check network tab, payload body
```

---

## 🔧 Troubleshooting

### Issue: Module JS tidak ter-load

**Solusi:**
```bash
# 1. Pastikan path module benar:
# - /resources/js/pages/settings/index.js
# - /resources/js/pages/requests/documents.js

# 2. Jika 404, check Vite config atau serve langsung:
php artisan serve

# 3. Jika CORS error, pastikan credentials: 'same-origin'
```

### Issue: Alpine x-data tidak dikenal

**Solusi:**
```bash
# 1. Pastikan Alpine.js ter-load sebelum script
# 2. Check console error
# 3. Pastikan x-data name match dengan Alpine.data() name:
#    x-data="settingsPageAlpine" → Alpine.data('settingsPageAlpine', ...)
```

### Issue: CSRF token mismatch

**Solusi:**
```bash
# 1. Pastikan meta tag csrf-token ada di layout:
<meta name="csrf-token" content="{{ csrf_token() }}">

# 2. Pastikan client.csrf / documentsClient.csrf ter-set
```

### Issue: Preview PDF tidak muncul

**Solusi:**
```bash
# 1. Check network tab, response content-type
# 2. Jika JSON, cek data.url
# 3. Jika blob, cek URL.createObjectURL()
# 4. Pastikan iframe src ter-bind: :src="client.state.pdfPreviewUrl"
```

---

## 📊 Metrics Before/After

| Metrik | Before | After | Improvement |
|--------|--------|-------|-------------|
| settings/index.blade.php | 1072 lines | ~150 lines | **-86%** |
| requests/show.blade.php | ~527 lines | ~400 lines | **-24%** |
| Inline JS (settings) | ~570 lines | 0 | **-100%** |
| Inline JS (requests docs) | ~150 lines | 0 | **-100%** |
| Reusable modules | 0 | 2 | **+2** |
| Partials (Blade) | 0 | 6 | **+6** |

---

## ✅ Checklist Final

- [x] SettingsClient module dengan apiFetch
- [x] RequestDocumentsClient module
- [x] 6 partials settings (numbering, templates, branding, localization, notifications)
- [x] 1 partial requests (documents)
- [x] Refactor settings/index.blade.php
- [x] Refactor requests/show.blade.php
- [x] Backup file lama (index.blade.php.backup)
- [x] Semua endpoint wireframe terpenuhi
- [x] Error handling 422 validation
- [x] Sanitize numeric fields (empty → null)
- [x] Header `Accept: application/json` di semua request
- [x] Dokumentasi verifikasi manual

---

## 🚀 Deployment Checklist

```bash
# 1. Git status & commit
git status
git add resources/views/settings/
git add resources/views/requests/partials/
git add resources/js/pages/
git commit -m "refactor: modular frontend settings & request documents"

# 2. Clear cache Laravel
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 3. Compile assets (jika pakai Vite/Mix)
npm run build
# atau development:
npm run dev

# 4. Test di staging
# - Buka /settings
# - Test semua section save
# - Test preview PDF, numbering, templates
# - Buka /requests/{id}
# - Test delete dokumen

# 5. Monitor error logs
tail -f storage/logs/laravel.log

# 6. Production deploy
# (sesuai workflow production Anda)
```

---

## 📞 Support

Jika ada issue atau pertanyaan:
1. Check console browser (F12 → Console)
2. Check network tab (F12 → Network → filter XHR)
3. Check Laravel logs (`storage/logs/laravel.log`)
4. Verifikasi routes API exist: `php artisan route:list | grep api/settings`
5. Verifikasi permissions: User harus punya role yang sesuai

---

**Refactor selesai!** ✨

Frontend `/settings` dan tab "Dokumen & Lampiran" sekarang modular, maintainable, dan fully compliant dengan wireframe API.
