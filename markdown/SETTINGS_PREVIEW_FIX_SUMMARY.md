# Settings Preview & Test Preview Fix - Summary

## 🎯 Tujuan
Memperbaiki fitur tombol **Preview** dan **Test Preview** di halaman `/settings` agar benar-benar memicu aksi dan menampilkan hasil ke UI.

## ❌ Masalah Sebelumnya
- Tombol Preview dan Test Preview tidak memanggil apa-apa saat diklik
- Tidak ada request di Network tab
- Tidak ada perubahan UI
- Error Alpine: `client is not defined`, `labels is not defined`, `client.state.previewState is undefined`

## ✅ Solusi yang Diterapkan

### 1. **Alpine Component Method Exposure**
**File:** `resources/js/pages/settings/alpine-component.js`

Ditambahkan wrapper methods yang expose client methods dengan debugging console.log:

```javascript
// Wrapper methods to expose client methods with debugging
testPreview(scope) {
    console.log('🔍 testPreview called', { scope, state: this.client.state });
    return this.client.testPreview(scope);
},

previewPdf() {
    console.log('📄 previewPdf called', { branding: this.client.state.form.branding, pdf: this.client.state.form.pdf });
    return this.client.previewPdf();
},

previewTemplate(type) {
    console.log('📝 previewTemplate called', { type, activeTemplate: this.client.state.activeTemplates?.[type] });
    return this.previewActiveTemplate(type);
},
```

### 2. **SettingsClient Method Debugging**
**File:** `resources/js/pages/settings/index.js`

Ditambahkan console.log di setiap method untuk tracking:

#### testPreview Method:
```javascript
async testPreview(scope) {
    console.log('SettingsClient.testPreview called', { scope, currentForm: this.state.form.numbering?.[scope] });
    // ... existing code
    console.log('→ POST /api/settings/numbering/preview', { scope, config: scopeConfig });
    // ... after response
    console.log('✓ Preview response:', data);
}
```

#### previewPdf Method:
```javascript
async previewPdf() {
    console.log('SettingsClient.previewPdf called', { branding: this.state.form.branding, pdf: this.state.form.pdf });
    // ... existing code
    console.log('→ POST /api/settings/pdf/preview', { branding, pdf });
    // ... after response
    console.log('✓ PDF preview URL:', data.url);
}
```

### 3. **Template Binding Fix**
Updated template binding dari `client.method()` ke `method()` langsung:

#### Numbering Partial:
**File:** `resources/views/settings/partials/numbering.blade.php`
```blade
<!-- BEFORE -->
@click.prevent="client.testPreview(scope)"

<!-- AFTER -->
@click.prevent="testPreview(scope)"
```

#### Branding Partial:
**File:** `resources/views/settings/partials/branding.blade.php`
```blade
<!-- BEFORE -->
@click="client.previewPdf()"

<!-- AFTER -->
@click="previewPdf()"
```

#### Templates Partial:
**File:** `resources/views/settings/partials/templates.blade.php`
```blade
<!-- BEFORE -->
@click="client.previewTemplate(type)"

<!-- AFTER -->
@click="previewTemplate(type)"
```

### 4. **State Initialization**
Sudah dipastikan di commit sebelumnya bahwa `previewState` dan `numberingPreview` diinisialisasi dengan default values:

```javascript
initializeState(config) {
    return {
        // ... other state
        numberingPreview: { sample_code: '', ba: '', lhu: '', ba_penyerahan: '', tracking: '' },
        previewLoading: { sample_code: false, ba: false, lhu: false, ba_penyerahan: false, tracking: false },
        previewState: { numbering: false, sample_code: false, ba: false, lhu: false, ba_penyerahan: false, tracking: false },
        // ... other state
    };
}
```

### 5. **Helper Methods**
Ditambahkan helper methods untuk scope status management:

```javascript
setScopeStatus(scope, message, intentClass = 'text-green-600') { ... }
setScopeError(scope, message) { ... }
clearScopeError(scope) { ... }
```

## 🧪 Testing Checklist

### ✅ Console Logging
Saat tombol diklik, console akan menampilkan:
1. `🔍 testPreview called` - Wrapper method terpanggil
2. `SettingsClient.testPreview called` - Client method terpanggil
3. `→ POST /api/settings/numbering/preview` - Request dimulai
4. `✓ Preview response:` - Response diterima
5. `✗ Preview error:` - Jika ada error

### ✅ Network Tab
Request yang terlihat:
- `POST /api/settings/numbering/preview` (Test Preview)
- `POST /api/settings/pdf/preview` (Preview PDF)
- `GET /api/settings/templates/{id}/preview` (Preview Template)

### ✅ UI Updates
- Preview text muncul di box preview
- Loading state: tombol disabled dan menampilkan "Testing..." / "Loading..."
- Success message: "Preview berhasil!" dengan warna hijau
- Error message: ditampilkan dengan warna merah

### ✅ Error Handling
- Validation errors ditampilkan per field
- Network errors ditampilkan sebagai pesan error
- State preview di-reset ke "Error" jika gagal

## 📋 Endpoint Backend (Verified)

```bash
POST   /api/settings/numbering/preview
POST   /api/settings/pdf/preview  
GET    /api/settings/templates/{template}/preview
POST   /settings/preview
```

## 🔄 Flow Eksekusi

### Test Preview (Numbering):
1. User klik tombol "Test Preview" pada scope tertentu (sample_code, ba, lhu, dll)
2. Alpine component wrapper `testPreview(scope)` terpanggil → console.log
3. Client method `client.testPreview(scope)` dipanggil → console.log
4. Loading state diset: `previewLoading[scope] = true`
5. Request POST ke `/api/settings/numbering/preview` → console.log
6. Response diterima → console.log
7. Preview text di-update: `numberingPreview[scope] = response.preview`
8. Success message ditampilkan
9. Loading state direset: `previewLoading[scope] = false`

### Preview PDF:
1. User klik tombol "Preview PDF"
2. Alpine wrapper `previewPdf()` → console.log
3. Client method `client.previewPdf()` → console.log
4. Loading state: `pdfPreviewLoading = true`
5. Request POST ke `/api/settings/pdf/preview` → console.log
6. Response blob/URL diterima → console.log
7. `pdfPreviewUrl` di-update untuk iframe
8. Loading state direset

### Preview Template:
1. User klik tombol "Preview" pada card template
2. Alpine wrapper `previewTemplate(type)` → console.log
3. Internal method `previewActiveTemplate(type)`
4. Validasi template aktif
5. Open new tab: `/api/settings/templates/{id}/preview`

## 📁 Files Modified

1. ✅ `resources/js/pages/settings/alpine-component.js` - Added wrapper methods
2. ✅ `resources/js/pages/settings/index.js` - Added console.log debugging
3. ✅ `resources/views/settings/partials/numbering.blade.php` - Fixed binding
4. ✅ `resources/views/settings/partials/branding.blade.php` - Fixed binding
5. ✅ `resources/views/settings/partials/templates.blade.php` - Fixed binding

## 🚀 Deploy Instructions

```bash
# Rebuild assets
npm run build

# Clear Laravel cache (optional)
php artisan cache:clear
php artisan view:clear

# Test di browser
# 1. Buka /settings
# 2. Buka Console (F12)
# 3. Klik "Test Preview" pada numbering
# 4. Verifikasi console logs muncul
# 5. Verifikasi Network request muncul
# 6. Verifikasi preview text ter-update
```

## 🎉 Expected Result

✅ Console menampilkan log lengkap  
✅ Network tab menampilkan request preview  
✅ UI menampilkan hasil preview atau error yang informatif  
✅ Tidak ada error Alpine "is not defined" atau "undefined property"  
✅ Tombol loading state bekerja dengan benar  
✅ Error handling menampilkan pesan ke user  

---

**Status:** ✅ FIXED - Ready for testing  
**Date:** December 19, 2025  
**Build:** Completed successfully
