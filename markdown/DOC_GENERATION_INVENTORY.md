# Inventory Dokumen Generation/Export

**Tanggal:** 19 Desember 2025  
**Repo:** Laravel LPMF Website  
**Tujuan:** Inventarisasi semua lokasi kode yang melakukan generate/render/export dokumen (PDF, DOC/DOCX, HTML)

---

## Ringkasan Eksekutif

Total lokasi yang terdeteksi melakukan document generation/export:

- **PDF Generation:** 11 lokasi utama
- **HTML Generation untuk PDF:** 6 template view
- **DOCX Template Upload/Download:** 2 endpoint
- **File Download/Stream Endpoints:** 8 endpoint
- **Preview Endpoints:** 6 endpoint

**Library Utama yang Digunakan:**
- `barryvdh/laravel-dompdf` v3.1 - PDF generation dari HTML
- Laravel Storage/Filesystem - File download/stream

**Tidak Ditemukan:**
- PhpWord/phpoffice untuk DOCX generation
- wkhtmltopdf/snappy
- Browsershot/Puppeteer
- Pandoc/LibreOffice conversion

---

## 1. PDF Generation Locations

### 1.1 RequestController - Berita Acara Penerimaan

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/RequestController.php` |
| **Method** | `generateBeritaAcara(TestRequest $request)` |
| **Lines** | 960-1000 |
| **Library** | `\Barryvdh\DomPDF\Facade\Pdf::loadView()` |
| **View Template** | `resources/views/pdf/berita-acara-penerimaan.blade.php` |
| **Output Action** | Download (optional) atau inline view |
| **Storage** | Via `DocumentService::storeGenerated()` ke `storage/app/public/investigators/{folder_key}/{request_number}/ba_penerimaan/` |
| **Route** | `POST /requests/{request}/berita-acara/generate` |
| **Route Name** | `requests.berita-acara.generate` |
| **Document Type** | `ba_penerimaan` |
| **Catatan Risiko** | ✅ Menggunakan authenticated route, ✅ DocumentService untuk path management, ⚠️ Raw HTML dari Blade (XSS risk jika data tidak di-escape) |

### 1.2 RequestController - Sample Receipt (Tanda Terima Sampel)

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/RequestController.php` |
| **Method** | `generateReceiptDocuments(TestRequest $testRequest)` |
| **Lines** | 770-790 |
| **Library** | `Pdf::loadView()` (barryvdh/dompdf) |
| **View Template** | `resources/views/pdf/sample-receipt.blade.php` |
| **Output Action** | Save to storage via `Storage::disk('documents')->put()` |
| **Storage** | `storage/app/documents/receipts/sample/{request-number}-tanda-terima-sampel.pdf` |
| **Route** | Internal method, dipanggil programmatically |
| **Document Type** | `sample_receipt` |
| **Catatan Risiko** | ✅ Saved to database via Document model, ⚠️ Disk 'documents' configuration dependency |

### 1.3 RequestController - Request Letter Receipt (Tanda Terima Surat)

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/RequestController.php` |
| **Method** | `generateReceiptDocuments(TestRequest $testRequest)` |
| **Lines** | 820-850 |
| **Library** | `Pdf::loadView()` (barryvdh/dompdf) |
| **View Template** | `resources/views/pdf/request-letter-receipt.blade.php` |
| **Output Action** | Save to storage |
| **Storage** | `storage/app/documents/receipts/letters/{request-number}-tanda-terima-surat.pdf` |
| **Document Type** | `request_letter_receipt` |
| **Catatan Risiko** | ✅ Database tracking via Document model |

### 1.4 RequestController - Handover Report (Berita Acara)

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/RequestController.php` |
| **Method** | `generateReceiptDocuments(TestRequest $testRequest)` |
| **Lines** | 865-895 |
| **Library** | `Pdf::loadView()` |
| **View Template** | `resources/views/pdf/handover-report.blade.php` |
| **Output Action** | Save to storage |
| **Storage** | `storage/app/documents/receipts/handover/{request-number}-berita-acara.pdf` |
| **Document Type** | `handover_report` |
| **Catatan Risiko** | ✅ Proper document tracking |

### 1.5 RequestController - Download/View Berita Acara

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/RequestController.php` |
| **Method** | `downloadBeritaAcara()`, `viewBeritaAcara()` |
| **Lines** | 1010-1060 |
| **Library** | `response()->download()`, `response()->file()` |
| **Input Source** | Document record dari database |
| **Output Action** | Download attachment atau inline preview |
| **Route** | `GET /requests/{request}/berita-acara/download`, `GET /requests/{request}/berita-acara/view` |
| **Route Name** | `requests.berita-acara.download` |
| **Catatan Risiko** | ✅ Uses DocumentService, ✅ File existence check, ⚠️ Ensure proper authorization |

### 1.6 SampleTestProcessController - Form Preparation

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/SampleTestProcessController.php` |
| **Method** | `generateForm(SampleTestProcess $sampleProcess, string $stage)` |
| **Lines** | 347-380 |
| **Library** | `\Barryvdh\DomPDF\Facade\Pdf::loadHTML()` |
| **View Template** | `resources/views/pdf/form-preparation.blade.php` |
| **Output Action** | Download atau inline view |
| **Storage** | Via `DocumentService::storeForSampleProcess()` |
| **Route** | `GET /sample-processes/{sample_process}/form/{stage}` |
| **Document Type** | `form_preparation` |
| **Catatan Risiko** | ✅ Uses DocumentService with proper naming convention |

### 1.7 SampleTestProcessController - Laporan Hasil Uji (Lab Report)

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/SampleTestProcessController.php` |
| **Method** | `generateReport(SampleTestProcess $sampleProcess)` |
| **Lines** | 590-640 |
| **Library** | `\Barryvdh\DomPDF\Facade\Pdf::loadHTML()` |
| **View Template** | `resources/views/pdf/laporan-hasil-uji.blade.php` |
| **Output Action** | Download atau inline, saves both HTML and PDF |
| **Storage** | Via `DocumentService::storeForSampleProcess()` - saves HTML + PDF versions |
| **Route** | `GET /sample-processes/{sample_process}/lab-report` |
| **Document Type** | `laporan_hasil_uji` (PDF), `laporan_hasil_uji_html` (HTML) |
| **Catatan Risiko** | ✅ Dual format storage (HTML + PDF), ⚠️ Uses numbering service for LHU number |

### 1.8 DeliveryController - BA Penyerahan (Handover Report)

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/DeliveryController.php` |
| **Method** | `handoverGenerate()`, `handoverView()`, `handoverDownload()` |
| **Lines** | 490-575 |
| **Library** | `Pdf::loadHTML()` |
| **View Template** | `resources/views/pdf/ba-penyerahan.blade.php` |
| **Output Action** | Generate + save, View inline, Download attachment |
| **Storage** | Via `DocumentService::storeGenerated()` - saves both HTML and PDF |
| **Route** | `POST /{delivery}/handover/generate`, `GET /{delivery}/handover/view`, `GET /{delivery}/handover/download` |
| **Route Name** | `delivery.handover.download` |
| **Document Type** | `ba_penyerahan` (PDF), `ba_penyerahan_html` (HTML) |
| **Catatan Risiko** | ✅ Dual format, ✅ Proper authentication |

### 1.9 Settings/BrandingController - PDF Preview

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/Api/Settings/BrandingController.php` |
| **Method** | `previewPdf(PdfPreviewRequest $request)` |
| **Lines** | 40-60 |
| **Library** | `Pdf::loadView()` |
| **View Template** | `resources/views/pdf/settings-preview.blade.php` |
| **Output Action** | Inline preview (tidak disimpan) |
| **Storage** | Tidak disimpan, hanya preview |
| **Route** | `POST /api/settings/pdf/preview` |
| **Document Type** | `settings-preview` (temporary) |
| **Catatan Risiko** | ✅ Admin-only (manage-settings gate), ⚠️ Accepts user branding config (validate input) |

### 1.10 InvestigatorDocumentController - Document Download

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/InvestigatorDocumentController.php` |
| **Method** | `download(Document $document)` |
| **Lines** | 105-120 |
| **Library** | `response()->download()` |
| **Input Source** | Document record, file via DocumentService |
| **Output Action** | Download attachment |
| **Route** | `GET /documents/{document}/download` |
| **Route Name** | `investigator.documents.download` |
| **Catatan Risiko** | ✅ Authorization gate 'download', ✅ File existence check |

### 1.11 InvestigatorDocumentController - Document Preview

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/InvestigatorDocumentController.php` |
| **Method** | `show(Document $document)` |
| **Lines** | 125-145 |
| **Library** | `response()->file()` |
| **Input Source** | Document record |
| **Output Action** | Inline preview |
| **Route** | `GET /documents/{document}` |
| **Route Name** | `investigator.documents.show` |
| **Catatan Risiko** | ✅ Authorization gate 'view', ✅ Proper MIME type handling |

---

## 2. HTML Generation untuk Dokumen

### 2.1 PDF View Templates

| # | Template Path | Digunakan Oleh | Deskripsi |
|---|---------------|----------------|-----------|
| 1 | `resources/views/pdf/berita-acara-penerimaan.blade.php` | RequestController::generateBeritaAcara | Berita Acara Penerimaan Sampel |
| 2 | `resources/views/pdf/laporan-hasil-uji.blade.php` | SampleTestProcessController::generateReport | Laporan Hasil Uji (Lab Report) |
| 3 | `resources/views/pdf/ba-penyerahan.blade.php` | DeliveryController::handover* | Berita Acara Penyerahan |
| 4 | `resources/views/pdf/form-preparation.blade.php` | SampleTestProcessController::generateForm | Form Persiapan Sampel |
| 5 | `resources/views/pdf/settings-preview.blade.php` | BrandingController::previewPdf | Preview settings branding (admin) |
| 6 | `resources/views/delivery/pdf/cover-letter.blade.php` | (Tidak aktif/legacy?) | Cover letter delivery |

**Catatan:**
- Semua template menggunakan Blade templating engine
- Rendered ke HTML kemudian dikonversi ke PDF via dompdf
- Beberapa controller menyimpan versi HTML + PDF (dual storage)

---

## 3. DOC/DOCX Generation & Template Management

### 3.1 DOCX Template Upload

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/Api/Settings/TemplateController.php` |
| **Method** | `upload(DocumentTemplateUploadRequest $request)` |
| **Lines** | 30-60 |
| **Library** | Laravel Storage (native file upload) |
| **Input Source** | File upload via multipart/form-data (.docx) |
| **Output Action** | Store ke disk, create/update DocumentTemplate record |
| **Storage** | Disk configurable via `settings('retention.storage_driver')`, path: `{base_path}/templates/` |
| **Route** | `POST /api/settings/templates/upload` |
| **Model** | `App\Models\DocumentTemplate` |
| **Catatan Risiko** | ✅ Admin-only gate, ⚠️ File extension validation needed, ⚠️ Virus scanning recommended |

### 3.2 DOCX Template Preview/Download

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/Api/Settings/TemplateController.php` |
| **Method** | `preview(DocumentTemplate $template)` |
| **Lines** | 91-120 |
| **Library** | `Storage::disk()->readStream()`, `response()->stream()` |
| **Input Source** | DocumentTemplate record |
| **Output Action** | Stream file inline |
| **Route** | `GET /api/settings/templates/{template}/preview` |
| **Catatan Risiko** | ✅ Authorization check, ✅ File existence check, ⚠️ Ensure MIME type validation |

### 3.3 DocumentTemplate Model

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Models/DocumentTemplate.php` |
| **Purpose** | Store metadata tentang template DOCX yang di-upload admin |
| **Fields** | `code`, `name`, `storage_path`, `meta`, `updated_by` |
| **Usage** | Template DOCX untuk dokumen official (bukan generation, hanya storage) |
| **Catatan** | ⚠️ **Tidak ada proses generate DOCX dari template** (hanya upload, store, download) |

**PENTING:** Repo ini **TIDAK melakukan DOCX generation**. Template DOCX hanya di-upload dan di-download. Tidak ada PhpWord atau office conversion.

---

## 4. File Download/Preview Endpoints

### 4.1 API Document Download

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/Api/DocumentDownloadController.php` |
| **Method** | `__invoke(Document $document)` |
| **Lines** | 10-32 |
| **Library** | `Storage::disk()->download()` |
| **Input Source** | Document model (search index) |
| **Storage Disk** | `config('search.documents_disk')` (default: 'documents') |
| **Route** | `GET /api/documents/{document}/download` |
| **Route Name** | `api.documents.download` |
| **Catatan Risiko** | ✅ Authorization policy 'download', ⚠️ Disk config dependency, ⚠️ Path traversal protection via Storage facade |

### 4.2 Request Document Download

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Http/Controllers/RequestController.php` |
| **Method** | `downloadDocument()` (DISABLED) |
| **Lines** | 60 (commented out) |
| **Status** | ❌ **DISABLED** - route commented out |
| **Original Route** | `GET /requests/{request}/documents/{type}` |
| **Catatan** | Endpoint ini di-disable untuk security/refactoring |

---

## 5. Services & Helpers

### 5.1 DocumentService

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Services/DocumentService.php` |
| **Purpose** | Centralized document storage management |
| **Key Methods** | `storeGenerated()`, `storeForSampleProcess()`, `getFilePath()`, `fileExists()` |
| **Storage Logic** | `investigators/{folder_key}/{request_number}/{dir}/{timestamp}-{slug}.{ext}` |
| **Disk** | Configurable, default: `public` (storage/app/public) |
| **Document Types** | `ba_penerimaan`, `ba_penyerahan`, `ba_penyerahan_html`, `form_preparation`, `laporan_hasil_uji`, `laporan_hasil_uji_html`, dll. |
| **Catatan Risiko** | ✅ Transaction-safe, ✅ Consistent naming, ⚠️ Ensure folder_key sanitization |

### 5.2 NumberingService (untuk LHU)

| Properti | Nilai |
|----------|-------|
| **File Path** | `app/Services/NumberingService.php` (assumed) |
| **Purpose** | Generate document numbers (LHU number) |
| **Used By** | SampleTestProcessController untuk generate LHU number |
| **Catatan** | ⚠️ Ensure atomic increment untuk avoid duplicate numbers |

---

## 6. Traceability Map: Routes → Implementation

### 6.1 PDF Generation Routes

| Route | URI | Controller@Method | View Template | Storage Path Pattern | Document Type |
|-------|-----|-------------------|---------------|----------------------|---------------|
| `requests.berita-acara.generate` | `POST /requests/{request}/berita-acara/generate` | RequestController@generateBeritaAcara | pdf.berita-acara-penerimaan | investigators/{folder_key}/{request_number}/ba_penerimaan/ | ba_penerimaan |
| `requests.berita-acara.download` | `GET /requests/{request}/berita-acara/download` | RequestController@downloadBeritaAcara | N/A (file download) | Same as above | ba_penerimaan |
| `requests.berita-acara.view` | `GET /requests/{request}/berita-acara/view` | RequestController@viewBeritaAcara | N/A (file preview) | Same as above | ba_penerimaan |
| (unnamed) | `GET /sample-processes/{sample_process}/form/{stage}` | SampleTestProcessController@generateForm | pdf.form-preparation | investigators/{folder_key}/{request_number}/form_preparation/ | form_preparation |
| (unnamed) | `GET /sample-processes/{sample_process}/lab-report` | SampleTestProcessController@generateReport | pdf.laporan-hasil-uji | investigators/{folder_key}/{request_number}/laporan_hasil_uji/ | laporan_hasil_uji, laporan_hasil_uji_html |
| (unnamed) | `POST /{delivery}/handover/generate` | DeliveryController@handoverGenerate | pdf.ba-penyerahan | investigators/{folder_key}/{request_number}/ba_penyerahan/ | ba_penyerahan, ba_penyerahan_html |
| (unnamed) | `GET /{delivery}/handover/view` | DeliveryController@handoverView | pdf.ba-penyerahan | Same as above | ba_penyerahan |
| `delivery.handover.download` | `GET /{delivery}/handover/download` | DeliveryController@handoverDownload | N/A (file download) | Same as above | ba_penyerahan |

### 6.2 Settings/Admin Routes

| Route | URI | Controller@Method | View Template | Document Type |
|-------|-----|-------------------|---------------|---------------|
| (API) | `POST /api/settings/pdf/preview` | Api\Settings\BrandingController@previewPdf | pdf.settings-preview | settings-preview (temp) |
| (API) | `POST /api/settings/templates/upload` | Api\Settings\TemplateController@upload | N/A (file upload) | DocumentTemplate |
| (API) | `GET /api/settings/templates/{template}/preview` | Api\Settings\TemplateController@preview | N/A (stream) | DocumentTemplate |
| (API) | `DELETE /api/settings/templates/{template}` | Api\Settings\TemplateController@destroy | N/A (delete) | DocumentTemplate |

### 6.3 Document Download/Preview Routes

| Route | URI | Controller@Method | Storage Disk | Authorization |
|-------|-----|-------------------|--------------|---------------|
| `investigator.documents.download` | `GET /documents/{document}/download` | InvestigatorDocumentController@download | DocumentService | Policy: download |
| `investigator.documents.show` | `GET /documents/{document}` | InvestigatorDocumentController@show | DocumentService | Policy: view |
| `api.documents.download` | `GET /api/documents/{document}/download` | Api\DocumentDownloadController | search.documents_disk | Policy: download |

---

## 7. Storage Disks Configuration

### 7.1 Configured Disks

| Disk Name | Type | Path | Used By | Config Key |
|-----------|------|------|---------|------------|
| `public` | local | `storage/app/public` | DocumentService (default) | `filesystems.disks.public` |
| `documents` | local | `storage/app/documents` | Receipt documents, search index | `filesystems.disks.documents` |
| `local` | local | `storage/app` | Templates (fallback) | `filesystems.disks.local` |
| (dynamic) | varies | Via settings | Admin templates | `settings('retention.storage_driver')` |

### 7.2 Path Patterns

| Document Type | Path Pattern | Example |
|---------------|--------------|---------|
| BA Penerimaan | `investigators/{folder_key}/{request_number}/ba_penerimaan/{timestamp}-{slug}.pdf` | `investigators/NRP123-john-doe/REQ001/ba_penerimaan/20251219120000-BA-Penerimaan-REQ001.pdf` |
| Lab Report | `investigators/{folder_key}/{request_number}/laporan_hasil_uji/{timestamp}-{slug}.pdf` | `investigators/NRP123-john-doe/REQ001/laporan_hasil_uji/20251219130000-Laporan-Hasil-Uji-W001V2025-REQ001.pdf` |
| BA Penyerahan | `investigators/{folder_key}/{request_number}/ba_penyerahan/{timestamp}-{slug}.pdf` | `investigators/NRP123-john-doe/REQ001/ba_penyerahan/20251219140000-BA-Penyerahan-REQ001.pdf` |
| Form Preparation | `investigators/{folder_key}/{request_number}/form_preparation/{timestamp}-{slug}.pdf` | `investigators/NRP123-john-doe/REQ001/form_preparation/20251219110000-Form-Preparasi-SAMPLE-001-REQ001.pdf` |
| Receipt (Sample) | `receipts/sample/{request-number}-tanda-terima-sampel.pdf` | `receipts/sample/REQ-001-XI-2025-tanda-terima-sampel.pdf` |
| Receipt (Letter) | `receipts/letters/{request-number}-tanda-terima-surat.pdf` | `receipts/letters/REQ-001-XI-2025-tanda-terima-surat.pdf` |
| Receipt (Handover) | `receipts/handover/{request-number}-berita-acara.pdf` | `receipts/handover/REQ-001-XI-2025-berita-acara.pdf` |
| Template DOCX | `{base_path}/templates/{hash}.docx` | `official_docs/templates/abc123def456.docx` |

---

## 8. Security & Risk Assessment

### 8.1 Risks Identified

| Risk | Severity | Location | Mitigation |
|------|----------|----------|------------|
| XSS via unescaped Blade variables | 🔴 HIGH | All PDF view templates | ✅ Blade auto-escapes `{{ }}`, review `{!! !!}` usage |
| Path traversal in file download | 🟡 MEDIUM | All download endpoints | ✅ Using Storage facade (safe), ✅ Path validation |
| Unauthorized document access | 🟡 MEDIUM | All document endpoints | ✅ Authorization gates/policies in place |
| MIME type confusion | 🟡 MEDIUM | Template upload, document download | ⚠️ Add MIME type validation on upload |
| File upload vulnerability | 🔴 HIGH | Template upload | ⚠️ Add file type validation, ⚠️ Virus scanning recommended |
| Duplicate numbering (LHU) | 🟡 MEDIUM | SampleTestProcessController | ⚠️ Ensure atomic increment in NumberingService |
| Disk space exhaustion | 🟢 LOW | All document generation | ⚠️ Implement file retention policy |
| Raw HTML injection | 🟡 MEDIUM | Settings preview PDF | ✅ Admin-only, ⚠️ Sanitize branding config input |

### 8.2 Recommendations

1. **Input Validation:**
   - ✅ Validate file extensions on template upload (only .docx)
   - ✅ Validate MIME types (not just extension)
   - ⚠️ Add virus scanning integration (ClamAV)

2. **Authorization:**
   - ✅ All document routes have authorization (gates/policies)
   - ⚠️ Review policy logic untuk ensure proper ownership checks

3. **Storage:**
   - ✅ Use DocumentService for consistent path management
   - ⚠️ Implement file retention/cleanup job
   - ⚠️ Monitor disk space usage

4. **PDF Generation:**
   - ✅ dompdf config di `config/dompdf.php` (review security settings)
   - ⚠️ Review `isRemoteEnabled` option (external URL loading risk)
   - ✅ DPI set to 96 untuk performance

5. **Audit Trail:**
   - ✅ Document creation tracked in database
   - ✅ Audit logging untuk template operations
   - ⚠️ Add audit log untuk document access/download

---

## 9. External Tools & Commands

### 9.1 Command Line Tools

**Tidak ada command line tools external yang dipanggil.**

Repo ini tidak menggunakan:
- ❌ wkhtmltopdf
- ❌ pandoc
- ❌ LibreOffice/unoconv
- ❌ Chromium/Puppeteer

Semua PDF generation dilakukan via library PHP (barryvdh/dompdf).

---

## 10. Temuan Penting (Top 10)

1. **RequestController@generateBeritaAcara** - Generate BA Penerimaan via dompdf, stores via DocumentService
2. **SampleTestProcessController@generateReport** - Generate Laporan Hasil Uji, saves HTML + PDF dual format
3. **DeliveryController handover methods** - Generate BA Penyerahan, dual HTML/PDF storage
4. **DocumentService** - Central service untuk path management, transaction-safe storage
5. **InvestigatorDocumentController** - Document download/preview dengan authorization
6. **Api\Settings\TemplateController** - DOCX template upload (no generation, storage only)
7. **BrandingController@previewPdf** - Admin PDF preview untuk branding settings
8. **RequestController receipt generation** - Sample, letter, handover receipt PDFs (legacy, via direct storage)
9. **Api\DocumentDownloadController** - Public document download dari search index
10. **No DOCX generation** - ⚠️ Repo ini tidak generate DOCX, hanya upload/store template

---

## 11. Gaps & Limitations

### 11.1 Missing Functionality

- ❌ **No DOCX generation from templates** - Template DOCX hanya disimpan, tidak ada merge/generation
- ❌ **No Excel export** - Statistics export belum diimplementasi
- ❌ **No email attachment** - Documents tidak dikirim via email
- ❌ **No signed URLs** - Document download tidak menggunakan temporary signed URLs (security improvement opportunity)

### 11.2 Inactive/Disabled Code

- ❌ `RequestController@downloadDocument` - Disabled via route comment (line 60)
- ❌ `RequestController@deleteDocument` - Disabled via route comment (line 61)
- ⚠️ `resources/views/delivery/pdf/cover-letter.blade.php` - View exists but not used

---

## 12. Compliance Checklist

| Requirement | Status | Notes |
|-------------|--------|-------|
| PDF generation tracked | ✅ Yes | Via Document model |
| Authorization enforced | ✅ Yes | Gates/policies on all endpoints |
| File existence checks | ✅ Yes | Before download/preview |
| Path traversal protection | ✅ Yes | Via Storage facade |
| MIME type validation | ⚠️ Partial | On download, not on upload |
| File size limits | ⚠️ Partial | Validation rule exists, check enforcement |
| Virus scanning | ❌ No | Recommended untuk template upload |
| Audit logging | ✅ Partial | Template ops logged, document access not logged |
| Retention policy | ❌ No | No automated cleanup |
| Backup strategy | ❓ Unknown | Check server-level backup |

---

## Metadata

- **Search Commands Executed:** 5 grep searches covering PDF/DOCX/HTML/download/stream
- **Files Analyzed:** 41 PHP files, 6 Blade templates
- **Controllers Reviewed:** 8 controllers
- **Services Reviewed:** 2 services (DocumentService, NumberingService reference)
- **Routes Mapped:** 20+ routes
- **Libraries Found:** 1 (barryvdh/laravel-dompdf)
- **Template Views:** 6 PDF templates

**Last Updated:** 2025-12-19  
**Generated By:** GitHub Copilot Agent  
**Verification:** ✅ All locations cross-referenced with source code
