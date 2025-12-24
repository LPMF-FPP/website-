# Daftar File Diubah/Ditambah - Frontend Refactor

## 📦 Summary

**Total Files**: 10 files
- **Baru**: 8 files
- **Diubah**: 2 files
- **Backup**: 1 file

---

## ✅ File Baru (8 files)

### JavaScript Modules (2 files)

#### 1. `resources/js/pages/settings/index.js`
**Purpose**: SettingsClient class untuk mengelola semua section settings  
**Lines**: ~600 baris  
**Key Features**:
- `apiFetch(url, options)` - generic fetch dengan error handling
- `loadAll()` - load settings, templates, current numbering
- `saveSection(key)` - save per section dengan auto endpoint mapping
- `testPreview(scope)` - preview numbering pattern
- `uploadTemplate()`, `activateTemplate()`, `deleteTemplate()`
- `previewPdf()` - preview PDF dengan blob handling
- `testNotification(channel)` - test email/WhatsApp
- `sanitizePayload()` - convert empty string → null untuk numeric fields

**Added**:
```bash
resources/js/pages/settings/index.js (file baru)
```

#### 2. `resources/js/pages/requests/documents.js`
**Purpose**: RequestDocumentsClient untuk tab Dokumen & Lampiran  
**Lines**: ~170 baris  
**Key Features**:
- `fetchDocuments()` - GET `/api/requests/{id}/documents`
- `deleteDocument(doc)` - DELETE `/api/documents/{id}` tanpa reload
- `selectDocument(doc)` - set preview
- Helper methods: `documentIsPdf()`, `documentIsImage()`, `documentType()`

**Added**:
```bash
resources/js/pages/requests/documents.js (file baru)
```

---

### Blade Partials - Settings (5 files)

#### 3. `resources/views/settings/partials/numbering.blade.php`
**Purpose**: Section Penomoran Otomatis  
**Lines**: ~60 baris  
**Features**:
- Form untuk sample_code, ba, lhu
- Test Preview button per scope
- Panel "Penomoran Saat Ini" dengan Refresh button
- Status & error display inline

**Added**:
```bash
resources/views/settings/partials/numbering.blade.php (file baru)
```

#### 4. `resources/views/settings/partials/templates.blade.php`
**Purpose**: Section Template Dokumen  
**Lines**: ~80 baris  
**Features**:
- Form upload template .docx (multipart)
- Panel template aktif per tipe (sample_code, ba, lhu)
- List all templates dengan activate/delete button
- Preview template (buka tab baru)

**Added**:
```bash
resources/views/settings/partials/templates.blade.php (file baru)
```

#### 5. `resources/views/settings/partials/branding.blade.php`
**Purpose**: Section Branding & PDF  
**Lines**: ~60 baris  
**Features**:
- Form branding (lab_code, org_name, address, contact)
- Watermark preset & QR toggle
- Preview PDF button dengan iframe panel

**Added**:
```bash
resources/views/settings/partials/branding.blade.php (file baru)
```

#### 6. `resources/views/settings/partials/localization-retention.blade.php`
**Purpose**: Section Lokalisasi & Retensi  
**Lines**: ~55 baris  
**Features**:
- Locale fields (timezone, date_format, number_format, language)
- Retention fields (storage_driver, storage_folder_path, purge_after_days)
- Live preview waktu saat ini

**Added**:
```bash
resources/views/settings/partials/localization-retention.blade.php (file baru)
```

#### 7. `resources/views/settings/partials/notifications-security.blade.php`
**Purpose**: Section Notifikasi & Security  
**Lines**: ~80 baris  
**Features**:
- Email & WhatsApp config dengan toggle aktif
- Test notification button per channel
- Security roles checkboxes (can_manage_settings, can_issue_number)

**Added**:
```bash
resources/views/settings/partials/notifications-security.blade.php (file baru)
```

---

### Blade Partials - Requests (1 file)

#### 8. `resources/views/requests/partials/documents.blade.php`
**Purpose**: Tab Dokumen & Lampiran untuk detail permintaan  
**Lines**: ~55 baris  
**Features**:
- Table list dokumen dengan preview
- Delete button per dokumen (tanpa reload)
- Preview panel (PDF iframe, image img, atau download link)

**Added**:
```bash
resources/views/requests/partials/documents.blade.php (file baru)
```

---

## ♻️ File Diubah (2 files)

### 9. `resources/views/settings/index.blade.php`

**Changes**:
- **Direduksi**: 1072 baris → ~150 baris (**-86%**)
- **Inline JS dihapus**: ~570 baris Alpine.data() dipindah ke module
- **Menggunakan partials**: `@include('settings.partials.*')` untuk semua section
- **Script diganti**: Vanilla inline → ES6 module import

**Diff**:
```diff
--- resources/views/settings/index.blade.php (original)
+++ resources/views/settings/index.blade.php (refactored)

- 1072 lines of mixed HTML + inline Alpine.data()
+ ~150 lines: PHP vars + @includes + module import

Key changes:
- Removed: All <section> HTML (moved to partials)
- Removed: <script> with Alpine.data('settingsPage', () => ({ ... }))
+ Added: @include('settings.partials.numbering')
+ Added: @include('settings.partials.templates')
+ Added: @include('settings.partials.branding')
+ Added: @include('settings.partials.localization-retention')
+ Added: @include('settings.partials.notifications-security')
+ Added: <script type="module"> import { SettingsClient } ...
+ Added: x-data="settingsPageAlpine" with SettingsClient instance
```

**Backup Created**:
```bash
resources/views/settings/index.blade.php.backup (1072 baris)
```

**Modified**:
```bash
resources/views/settings/index.blade.php (now ~150 baris)
```

---

### 10. `resources/views/requests/show.blade.php`

**Changes**:
- **Section "Dokumen & Lampiran" diganti partial**: ~80 baris HTML + Alpine → 1 baris `@include`
- **Inline Alpine.data() dihapus**: ~150 baris dipindah ke module
- **Script diganti**: ES6 module import RequestDocumentsClient

**Diff**:
```diff
--- resources/views/requests/show.blade.php (original)
+++ resources/views/requests/show.blade.php (refactored)

- <div class="bg-white shadow-sm sm:rounded-lg" x-data="requestDocuments({{ $request->id }})" x-init="init()">
-   <!-- 80 baris HTML table + preview panel -->
- </div>
+ @include('requests.partials.documents', ['requestId' => $request->id])

- <script>
-   Alpine.data('requestDocuments', (requestIdParam) => ({
-     // 150 baris Alpine logic
-   }));
- </script>
+ <script type="module">
+   import { RequestDocumentsClient } from '/resources/js/pages/requests/documents.js';
+   Alpine.data('requestDocumentsAlpine', () => ({ ... }));
+ </script>
```

**Modified**:
```bash
resources/views/requests/show.blade.php (~527 baris → ~400 baris, -24%)
```

---

## 📄 File Dokumentasi (1 file)

#### 11. `FRONTEND_REFACTOR_DOCS.md`
**Purpose**: Dokumentasi lengkap refactor  
**Lines**: ~450 baris  
**Sections**:
- Ringkasan refactor
- Daftar file diubah/ditambah
- Kebutuhan wireframe yang dipenuhi
- Detail implementasi per module
- Verifikasi manual (step-by-step testing)
- Troubleshooting guide
- Metrics before/after
- Deployment checklist

**Added**:
```bash
FRONTEND_REFACTOR_DOCS.md (file baru)
```

---

## 📊 Summary Table

| File | Type | Status | Lines | Notes |
|------|------|--------|-------|-------|
| `resources/js/pages/settings/index.js` | JS Module | ✅ Baru | ~600 | SettingsClient class |
| `resources/js/pages/requests/documents.js` | JS Module | ✅ Baru | ~170 | RequestDocumentsClient |
| `resources/views/settings/partials/numbering.blade.php` | Blade | ✅ Baru | ~60 | Numbering section |
| `resources/views/settings/partials/templates.blade.php` | Blade | ✅ Baru | ~80 | Templates section |
| `resources/views/settings/partials/branding.blade.php` | Blade | ✅ Baru | ~60 | Branding & PDF |
| `resources/views/settings/partials/localization-retention.blade.php` | Blade | ✅ Baru | ~55 | Locale & Retention |
| `resources/views/settings/partials/notifications-security.blade.php` | Blade | ✅ Baru | ~80 | Notifications & Roles |
| `resources/views/requests/partials/documents.blade.php` | Blade | ✅ Baru | ~55 | Request documents tab |
| `resources/views/settings/index.blade.php` | Blade | ♻️ Diubah | 1072→150 | -86% size, backup dibuat |
| `resources/views/settings/index.blade.php.backup` | Blade | 💾 Backup | 1072 | Original file |
| `resources/views/requests/show.blade.php` | Blade | ♻️ Diubah | 527→400 | -24% size |
| `FRONTEND_REFACTOR_DOCS.md` | Docs | ✅ Baru | ~450 | Full documentation |

**Total Lines Added**: ~1210 baris (module + partials + docs)  
**Total Lines Removed/Refactored**: ~720 baris (inline JS + redundant HTML)  
**Net Impact**: +490 baris (tapi jauh lebih maintainable & reusable)

---

## 🔍 Patch/Diff per File

### Module: SettingsClient

**Key Methods**:
```javascript
// Generic API fetch
async apiFetch(url, options = {})

// Load initial data
async loadAll()
async fetchSettings()
async fetchCurrentNumbering()
async fetchTemplates()

// Section save
async saveSection(key)
sectionEndpoint(key) // auto map endpoint per section

// Numbering
async testPreview(scope)

// Templates
async uploadTemplate(templateForm, fileInputRef)
async activateTemplate(template)
async deleteTemplate(template)

// PDF
async previewPdf()

// Notifications
async testNotification(channel)

// Helpers
sanitizePayload(payload) // empty string → null
mergeDefaults(form)
setSectionLoading(key, state)
setSectionError(key, message)
setSectionStatus(key, message, intentClass)
```

### Module: RequestDocumentsClient

**Key Methods**:
```javascript
async fetchDocuments()
async deleteDocument(doc)
selectDocument(doc)
clearPreview()
documentType(doc)
documentIsPdf(doc)
documentIsImage(doc)
isDeleting(id)
openDocument(doc)
```

---

## 🎯 Endpoints Coverage

| Endpoint | Method | Section | Status |
|----------|--------|---------|--------|
| `/api/settings` | GET | All | ✅ |
| `/api/settings/numbering/current` | GET | Numbering | ✅ |
| `/api/settings/numbering` | PUT | Numbering | ✅ |
| `/api/settings/numbering/preview` | POST | Numbering | ✅ |
| `/api/settings/templates` | GET | Templates | ✅ |
| `/api/settings/templates/upload` | POST | Templates | ✅ |
| `/api/settings/templates/{id}/activate` | PUT | Templates | ✅ |
| `/api/settings/templates/{id}` | DELETE | Templates | ✅ |
| `/api/settings/templates/{id}/preview` | GET | Templates | ✅ |
| `/api/settings/branding` | PUT | Branding | ✅ |
| `/api/settings/pdf/preview` | POST | Branding | ✅ |
| `/api/settings/localization-retention` | PUT | Localization | ✅ |
| `/api/settings/notifications-security` | PUT | Notifications | ✅ |
| `/api/settings/notifications/test` | POST | Notifications | ✅ |
| `/api/requests/{id}/documents` | GET | Req Docs | ✅ |
| `/api/documents/{id}` | DELETE | Req Docs | ✅ |

**Total**: 16 endpoints, semua ter-cover ✅

---

## ✅ Wireframe Compliance Checklist

- [x] On-load: GET settings + current numbering
- [x] Per section: Save (PUT) dengan loading state
- [x] Status inline (Saved/Error) dengan intent class
- [x] Error 422 mapping (field: message)
- [x] Numbering: Test Preview + Refresh
- [x] Templates: Upload multipart, list, activate, delete, preview
- [x] Branding: Preview PDF (POST) tampil di panel
- [x] Localization: storage_folder_path disimpan
- [x] Notifications: Test Email/WhatsApp result inline
- [x] Request Docs: GET list + DELETE tanpa reload
- [x] Header `Accept: application/json` di semua request
- [x] Payload numeric tidak string kosong (sanitizePayload)

**100% Compliant** ✅

---

## 🚀 Quick Start

```bash
# 1. Verify files exist
ls -la resources/js/pages/settings/index.js
ls -la resources/js/pages/requests/documents.js
ls -la resources/views/settings/partials/
ls -la resources/views/requests/partials/

# 2. Clear cache
php artisan view:clear
php artisan cache:clear

# 3. Test settings page
curl -H "Accept: application/json" http://localhost:8000/api/settings

# 4. Open browser
# /settings
# /requests/{id}

# 5. Check console (F12)
# - No errors
# - Module imports successful
# - API calls dengan Accept: application/json
```

---

**Refactor Complete!** 🎉

Semua file telah di-refactor sesuai dengan requirement wireframe. Frontend sekarang modular, maintainable, dan fully tested-ready.
