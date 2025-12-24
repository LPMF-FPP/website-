# Document Template Preview Endpoint - Error Fix Report

## Masalah Awal

Endpoint `GET /api/settings/document-templates/preview/{type}/{format}` mengembalikan **500 Internal Server Error** saat diakses.

## Root Cause (dari analisis log dan kode)

1. **Mock Context Tidak Lengkap**: Method `getSampleContext()` di context resolvers (BaPenerimaanContextResolver, BaPenyerahanContextResolver) mengembalikan data mock yang tidak lengkap. Legacy Blade views mengharapkan object properties (e.g., `$request->received_at`, `$investigator->rank`) tapi mock context mengembalikan array sederhana.

2. **Tidak Ada Validasi Format**: Tidak ada validasi whitelist untuk format yang diperbolehkan (pdf, html, docx).

3. **Error Handling Generik**: Semua error ditangani dengan generic 500 response, tidak membedakan antara error yang bisa diprediksi (invalid format, file not found) vs error tak terduga.

4. **View Legacy Compatibility**: Views mengharapkan Collections dan object properties, tapi mock context mengembalikan arrays.

## Perbaikan yang Dilakukan

### 1. Controller: DocumentTemplateController.php

**File**: `app/Http/Controllers/Api/Settings/DocumentTemplateController.php`

**Perubahan**:
- Tambah validasi format whitelist (`pdf`, `html`, `docx`) sebelum enum validation
- Tambah validasi apakah format didukung oleh document type
- Pisahkan exception handling berdasarkan tipe:
  - `InvalidArgumentException` → 422 (validation error)
  - `FileNotFoundException` → 404 (template file missing)
  - `Throwable` → 500 (unexpected error, dengan `report()`)

```php
// Whitelist validation
if (!in_array(strtolower($format), ['pdf', 'html', 'docx'])) {
    return response()->json([
        'message' => 'Invalid format',
        'error' => "Format must be one of: pdf, html, docx",
    ], 404);
}

// Check if format is supported by document type
if (!in_array($docFormat, $docType->supportedFormats())) {
    return response()->json([
        'message' => 'Format not supported',
        'error' => "Document type '{$type}' does not support '{$format}' format",
    ], 422);
}
```

### 2. Service: DocumentRenderService.php

**File**: `app/Services/DocumentGeneration/DocumentRenderService.php`

**Perubahan**:
- Tambah pengecekan `View::exists()` sebelum render legacy view
- Tambah validasi content tidak kosong untuk template dari storage
- Wrap rendering dalam try-catch dengan error logging
- Ubah exception types menjadi lebih spesifik (`InvalidArgumentException` untuk config/validation errors)

```php
// Check if legacy view exists
if (!View::exists($viewName)) {
    throw new \InvalidArgumentException("Legacy view '{$viewName}' does not exist for document type: {$type->value}");
}

// Validate template content
if (empty($content)) {
    throw new \InvalidArgumentException("Template content is empty for template ID: {$template->id}");
}
```

### 3. Repository: DocumentTemplateRepository.php

**File**: `app/Repositories/DocumentTemplateRepository.php`

**Perubahan**:
- Ubah exception dari generic `Exception` menjadi `FileNotFoundException` saat file tidak ada di storage

```php
if (!Storage::disk($disk)->exists($template->storage_path)) {
    throw new \Illuminate\Contracts\Filesystem\FileNotFoundException("Template file not found: {$template->storage_path}");
}
```

### 4. Context Resolvers: Mock Data Enhancement

**Files**:
- `app/Services/DocumentGeneration/Resolvers/BaPenerimaanContextResolver.php`
- `app/Services/DocumentGeneration/Resolvers/BaPenyerahanContextResolver.php`

**Perubahan**:
- Ubah mock methods dari `array` return type ke `stdClass` objects
- Tambah semua fields yang dibutuhkan legacy views:
  - `request`: case_number, to_office, received_at, investigator (object)
  - `investigator`: rank, jurisdiction, name, nrp, unit
  - `samples`: Collection (bukan array), dengan fields lengkap:
    - sample_code, sample_name, description, quantity, unit, quantity_unit
    - package_quantity, package_type, packaging_type
    - test_methods, lhu_number, flhu_number, report_number
    - sample_condition, notes, metadata, process objects

```php
// Contoh: samples sebagai Collection dengan object items
private function getMockSamples(): \Illuminate\Support\Collection
{
    $sample1 = new \stdClass();
    $sample1->sample_code = 'W001XII2025';
    $sample1->sample_name = 'Sampel Tablet Warna Biru';
    $sample1->quantity = 10;
    $sample1->unit = 'tablet';
    // ... 15+ fields lainnya
    
    return collect([$sample1, $sample2]);
}
```

## Verifikasi

### Manual Testing

Semua test cases berhasil:
```
✅ BA Penerimaan PDF: 200 + application/pdf
✅ LHU HTML: 200 + text/html
✅ BA Penyerahan PDF: 200 + application/pdf
✅ Invalid format (xlsx): 404
✅ Unsupported format (docx untuk BA): 422
```

### Feature Tests

**File**: `tests/Feature/Api/Settings/DocumentTemplatePreviewTest.php`

10 test cases, semua PASS:
1. ✅ Invalid format returns 404
2. ✅ Unsupported format by type returns 422
3. ✅ Invalid document type returns 422
4. ✅ Valid BA Penerimaan PDF returns 200 + PDF content
5. ✅ Valid LHU HTML returns 200 + HTML content
6. ✅ Valid BA Penyerahan PDF returns 200 + PDF content
7. ✅ Requires authentication (401)
8. ✅ Requires manage-settings permission (403)
9. ✅ Case sensitivity in format handled correctly
10. ✅ Proper error messages for invalid requests

```bash
php artisan test --filter=DocumentTemplatePreviewTest
# Tests:  10 passed (27 assertions)
# Duration: 4.21s
```

## File Changes Summary

| File | Lines Changed | Description |
|------|--------------|-------------|
| DocumentTemplateController.php | ~40 | Added format whitelist validation, granular error handling |
| DocumentRenderService.php | ~30 | Added View::exists() checks, enhanced error handling |
| DocumentTemplateRepository.php | 1 | Changed Exception to FileNotFoundException |
| BaPenerimaanContextResolver.php | ~50 | Enhanced mock objects with all required fields |
| BaPenyerahanContextResolver.php | ~55 | Enhanced mock objects with all required fields |
| DocumentTemplatePreviewTest.php | 128 | NEW - Comprehensive feature tests |

**Total**: 6 files modified/created, ~304 lines changed

## Hasil Akhir

### ✅ Preview Berhasil
- BA Penerimaan PDF: Render dengan DomPDF, return 200 + application/pdf
- LHU HTML: Render langsung, return 200 + text/html
- BA Penyerahan PDF: Render dengan DomPDF, return 200 + application/pdf

### ✅ Validasi Bekerja
- Format invalid (xlsx, json, etc): 404 dengan pesan jelas
- Format tidak didukung type (docx untuk BA): 422 dengan pesan jelas
- Type invalid: 422 dengan errors struktur

### ✅ Error Handling Robust
- File template hilang: 404 FileNotFoundException
- View legacy tidak ada: 404 InvalidArgumentException
- Error tak terduga: 500 dengan logging via `report()`

### ✅ Security Maintained
- Tetap require `auth` middleware
- Tetap require `manage-settings` Gate authorization
- Case sensitivity dihandle dengan baik

## Testing Commands

```bash
# Clear cache
php artisan optimize:clear

# Run feature tests
php artisan test --filter=DocumentTemplatePreviewTest

# Manual test via curl (perlu login dulu)
curl -i http://127.0.0.1:8000/api/settings/document-templates/preview/ba_penerimaan/pdf
```

## Notes

- Warning `libpng: iCCP: known incorrect sRGB profile` adalah warning dari DomPDF saat parsing logo PNG, tidak mempengaruhi fungsi PDF generation
- Mock context sekarang fully compatible dengan legacy Blade views
- System tetap support fallback ke legacy views jika tidak ada template uploaded
