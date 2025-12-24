# Inventaris Document Generation

**Tanggal:** 22 Desember 2025  
**Status:** Dokumentasi Internal  
**Tujuan:** Identifikasi seluruh jalur generate dokumen untuk rencana refactor ke DocumentTemplate system

---

## 1. Executive Summary

Repository ini memiliki **2 sistem document generation** yang berjalan paralel:

1. **Legacy System** (Blade + DomPDF langsung)
2. **New DocumentTemplate System** (Template-based dengan versioning)

Dokumen ini menginventarisasi semua jalur legacy yang perlu di-refactor ke sistem baru.

### Quick Stats

| Metrik | Count |
|--------|-------|
| Controllers dengan PDF generation | 5 |
| View templates PDF | 5 |
| Route endpoints download/stream | 12+ |
| Tipe dokumen yang di-generate | 8 |
| Sudah pakai DocumentTemplate system | 1 (BA Penerimaan) |

---

## 2. Daftar File & Lokasi Kode

### 2.1 Controllers dengan PDF Generation

| File | Penggunaan PDF | Line |
|------|---------------|------|
| [app/Http/Controllers/RequestController.php](../app/Http/Controllers/RequestController.php) | `Pdf::loadView('pdf.sample-receipt', ...)` | L779 |
| [app/Http/Controllers/RequestController.php](../app/Http/Controllers/RequestController.php) | `Pdf::loadView('pdf.request-letter-receipt', ...)` | L823 |
| [app/Http/Controllers/RequestController.php](../app/Http/Controllers/RequestController.php) | `Pdf::loadView('pdf.handover-report', ...)` | L867 |
| [app/Http/Controllers/RequestController.php](../app/Http/Controllers/RequestController.php) | ✅ DocumentRenderService (BA Penerimaan) | L949 |
| [app/Http/Controllers/DeliveryController.php](../app/Http/Controllers/DeliveryController.php) | `view('pdf.ba-penyerahan', ...)` → `Pdf::loadHTML()` | L497, L526 |
| [app/Http/Controllers/SampleTestProcessController.php](../app/Http/Controllers/SampleTestProcessController.php) | `view('pdf.form-preparation', ...)` → `Pdf::loadHTML()` | L350 |
| [app/Http/Controllers/SampleTestProcessController.php](../app/Http/Controllers/SampleTestProcessController.php) | `view('pdf.laporan-hasil-uji', ...)` → `Pdf::loadHTML()` | L600 |
| [app/Http/Controllers/Api/Settings/BrandingController.php](../app/Http/Controllers/Api/Settings/BrandingController.php) | `Pdf::loadView('pdf.settings-preview', ...)` | L52 |

### 2.2 Blade View Templates

| View Path | Controller Usage | Tujuan |
|-----------|-----------------|--------|
| [resources/views/pdf/sample-receipt.blade.php](../resources/views/pdf/sample-receipt.blade.php) | ❌ RequestController L779 (legacy) | Tanda terima sampel |
| [resources/views/pdf/request-letter-receipt.blade.php](../resources/views/pdf/request-letter-receipt.blade.php) | ❌ RequestController L823 (legacy) | Tanda terima surat |
| [resources/views/pdf/handover-report.blade.php](../resources/views/pdf/handover-report.blade.php) | ❌ RequestController L867 (legacy) | Berita acara serah terima (deprecated?) |
| [resources/views/pdf/berita-acara-penerimaan.blade.php](../resources/views/pdf/berita-acara-penerimaan.blade.php) | ✅ DocumentTemplate (BA Penerimaan) | BA penerimaan (DocumentTemplate) |
| [resources/views/pdf/ba-penyerahan.blade.php](../resources/views/pdf/ba-penyerahan.blade.php) | ❌ DeliveryController L497, L526 | BA penyerahan hasil uji |
| [resources/views/pdf/form-preparation.blade.php](../resources/views/pdf/form-preparation.blade.php) | ❌ SampleTestProcessController L350 | Form preparasi sampel |
| [resources/views/pdf/laporan-hasil-uji.blade.php](../resources/views/pdf/laporan-hasil-uji.blade.php) | ❌ SampleTestProcessController L600 | Laporan Hasil Uji (LHU) |
| [resources/views/pdf/settings-preview.blade.php](../resources/views/pdf/settings-preview.blade.php) | ❌ BrandingController L52 | Preview settings branding |

**Catatan:** ✅ = sudah pakai DocumentTemplate system, ❌ = masih legacy

### 2.3 Endpoint Download/Stream

| Route Name | HTTP | Path | Controller@Method | Line |
|-----------|------|------|------------------|------|
| `api.documents.download` | GET | `/api/documents/{document}/download` | DocumentDownloadController::__invoke | L29 |
| `requests.documents.download` | GET | `/requests/{request}/documents/{type}` | RequestController::downloadDocument | L576 (disabled) |
| `requests.berita-acara.generate` | POST | `/requests/{request}/berita-acara/generate` | RequestController::generateBeritaAcara | L949 ✅ |
| `requests.berita-acara.download` | GET | `/requests/{request}/berita-acara/download` | RequestController::downloadBeritaAcara | L1001 |
| `requests.berita-acara.view` | GET | `/requests/{request}/berita-acara/view` | RequestController::viewBeritaAcara | L1021 |
| `sample-processes.generate-form` | GET | `/sample-processes/{process}/form/{stage}` | SampleTestProcessController::generateForm | L350 |
| `sample-processes.lab-report` | GET | `/sample-processes/{process}/lab-report` | SampleTestProcessController::generateReport | L600 |
| `delivery.handover.generate` | POST | `/delivery/{delivery}/handover/generate` | DeliveryController::handoverGenerate | L489 |
| `delivery.handover.view` | GET | `/delivery/{delivery}/handover/view` | DeliveryController::handoverView | L514 |
| `delivery.handover.download` | GET | `/delivery/{delivery}/handover/download` | DeliveryController::handoverDownload | L563 |
| `api.settings.document-templates.preview-html` | GET | `/api/settings/document-templates/{template}/preview/html` | DocumentTemplateController::previewTemplateHtml | L227 ✅ |
| `api.settings.document-templates.preview-pdf` | GET | `/api/settings/document-templates/{template}/preview/pdf` | DocumentTemplateController::previewTemplatePdf | L241 ✅ |
| `api.settings.pdf.preview` | POST | `/api/settings/pdf/preview` | BrandingController::previewPdf | L42 |

### 2.4 Services

| Service | Tujuan | Status |
|---------|--------|--------|
| [app/Services/DocumentGeneration/DocumentRenderService.php](../app/Services/DocumentGeneration/DocumentRenderService.php) | ✅ Main service untuk render documents via DocumentTemplate | New System |
| [app/Services/DocumentTemplates/DocumentTemplateRenderService.php](../app/Services/DocumentTemplates/DocumentTemplateRenderService.php) | ✅ Low-level rendering (HTML/PDF) dengan Blade | New System |
| [app/Services/DocumentService.php](../app/Services/DocumentService.php) | Storage helper untuk simpan document binary ke disk | Shared |

---

## 3. Tabel Ringkas: Route → Tipe Dokumen → Renderer

| Route/Controller/Method | Tipe Dokumen | Renderer Sekarang | Target DocumentTemplate | Priority |
|------------------------|--------------|-------------------|------------------------|----------|
| RequestController::downloadDocument (L576) | `sample_receipt` | ❌ Legacy `Pdf::loadView` | DocumentType::SAMPLE_RECEIPT | High |
| RequestController::downloadDocument (L576) | `request_letter_receipt` | ❌ Legacy `Pdf::loadView` | DocumentType::REQUEST_LETTER_RECEIPT | High |
| RequestController::downloadDocument (L576) | `handover_report` | ❌ Legacy `Pdf::loadView` (deprecated?) | *Skip/Remove* | Low |
| RequestController::generateBeritaAcara (L949) | `ba_penerimaan` | ✅ DocumentRenderService | ✅ Already using new system | ✅ Done |
| DeliveryController::handoverGenerate (L489) | `ba_penyerahan` | ❌ Blade → `Pdf::loadHTML` | DocumentType::BA_PENYERAHAN | High |
| DeliveryController::handoverView (L514) | `ba_penyerahan` | ❌ Blade → `Pdf::loadHTML` | DocumentType::BA_PENYERAHAN | High |
| SampleTestProcessController::generateForm (L350) | `form_preparation` | ❌ Blade → `Pdf::loadHTML` | DocumentType::FORM_PREPARATION | Medium |
| SampleTestProcessController::generateReport (L600) | `laporan_hasil_uji` | ❌ Blade → `Pdf::loadHTML` | DocumentType::LAPORAN_HASIL_UJI | Critical |
| BrandingController::previewPdf (L42) | `settings_preview` | ❌ `Pdf::loadView` | *Keep as utility* | Low |

### Legend

- ✅ = Sudah pakai DocumentTemplate system
- ❌ = Masih legacy (perlu refactor)
- **Priority:**
  - **Critical**: LHU (Laporan Hasil Uji) - dokumen utama lab
  - **High**: BA Penyerahan, Sample/Request Receipt - dokumen sering dipakai
  - **Medium**: Form Preparation - internal workflow
  - **Low**: Utility/deprecated endpoints

---

## 4. Analisis Pola Legacy

### 4.1 Pattern: RequestController (3 dokumen deprecated)

**File:** [app/Http/Controllers/RequestController.php](../app/Http/Controllers/RequestController.php) L770-900

```php
// LEGACY PATTERN - 3 dokumen di-generate sekaligus
$samplePdf = Pdf::loadView('pdf.sample-receipt', [...]);
$sampleContent = $samplePdf->output();
Storage::disk('documents')->put($samplePath, $sampleContent);
Document::create([...]);

$letterPdf = Pdf::loadView('pdf.request-letter-receipt', [...]);
// ... repeat pattern

$handoverPdf = Pdf::loadView('pdf.handover-report', [...]);
// ... repeat pattern
```

**Issues:**
- ❌ Tidak pakai DocumentTemplate (hard-coded Blade views)
- ❌ No versioning untuk template
- ❌ Trigger ada di method yang sudah tidak dipanggil (route disabled)
- ❌ Duplikasi kode (3x pattern serupa)

**Recommended Action:**
- [ ] Verifikasi apakah 3 dokumen ini masih dipakai (route disabled di L60 web.php)
- [ ] Jika masih perlu, migrate ke DocumentTemplate
- [ ] Jika deprecated, remove code

### 4.2 Pattern: DeliveryController (BA Penyerahan)

**File:** [app/Http/Controllers/DeliveryController.php](../app/Http/Controllers/DeliveryController.php) L489-571

```php
// LEGACY PATTERN - Blade render manual lalu convert PDF
$html = view('pdf.ba-penyerahan', [...])->render();
$pdf = Pdf::loadHTML($html)->setPaper('a4')->output();
$docs->storeGenerated($pdf, 'pdf', $inv, $req, 'ba_penyerahan', $base);
```

**Issues:**
- ❌ Manual Blade rendering
- ❌ Duplikasi kode di 3 methods: `handoverGenerate`, `handoverView`, `handoverDownload`
- ❌ Tidak pakai DocumentTemplate versioning

**Recommended Action:**
- [ ] Buat DocumentType::BA_PENYERAHAN enum
- [ ] Migrate view `pdf.ba-penyerahan.blade.php` ke DocumentTemplate
- [ ] Replace manual render dengan `DocumentRenderService::render()`

### 4.3 Pattern: SampleTestProcessController (LHU)

**File:** [app/Http/Controllers/SampleTestProcessController.php](../app/Http/Controllers/SampleTestProcessController.php) L600-630

```php
// LEGACY PATTERN - Critical document (Laporan Hasil Uji)
$html = view('pdf.laporan-hasil-uji', [
    'process' => $sampleProcess,
    'noLHU' => $lhuNumber,
    'forcedActiveSubstance' => $forcedActive,
])->render();

$pdf = Pdf::loadHTML($html)->setPaper('a4')->output();
$docs->storeForSampleProcess($sampleProcess, 'pdf', 'laporan_hasil_uji', $base, $pdf);
```

**Issues:**
- ❌ **CRITICAL**: Laporan Hasil Uji adalah dokumen legal utama
- ❌ Tidak ada versioning untuk template
- ❌ Manual numbering logic (`$lhuNumber` via NumberingService)
- ❌ Duplikasi simpan HTML + PDF manual

**Recommended Action (PRIORITY):**
- [ ] Buat DocumentType::LAPORAN_HASIL_UJI
- [ ] Pastikan numbering system terintegrasi ke DocumentRenderService
- [ ] Migrate dengan testing ketat (dokumen legal)

### 4.4 Pattern: Settings Preview (Utility)

**File:** [app/Http/Controllers/Api/Settings/BrandingController.php](../app/Http/Controllers/Api/Settings/BrandingController.php) L42-60

```php
// UTILITY PATTERN - Preview untuk settings UI
$binary = Pdf::loadView('pdf.settings-preview', [
    'branding' => $branding,
    'pdf' => $pdfConfig,
])->output();

return response($binary, 200, ['Content-Type' => 'application/pdf', ...]);
```

**Issues:**
- ⚠️ Bukan dokumen utama, hanya preview UI
- ⚠️ Mungkin tidak perlu masuk DocumentTemplate (overhead)

**Recommended Action:**
- [ ] **Keep as-is** (utility endpoint, low priority)
- [ ] Optional: convert ke DocumentTemplate jika mau unify semua PDF generation

---

## 5. PhpWord / TemplateProcessor

### Status

**Hasil:** ❌ **TIDAK DITEMUKAN**

Tidak ada penggunaan PhpWord atau TemplateProcessor di codebase saat ini. Semua dokumen menggunakan Blade + DomPDF (HTML → PDF).

**Catatan:** Jika nanti perlu DOCX generation, bisa extend DocumentTemplate system untuk support `DocumentFormat::DOCX`.

---

## 6. Rencana Refactor (Checklist)

### Phase 1: Critical Documents (Week 1-2)

- [ ] **Laporan Hasil Uji (LHU)**
  - [ ] Buat `DocumentType::LAPORAN_HASIL_UJI` enum
  - [ ] Migrate view `pdf.laporan-hasil-uji.blade.php` ke DocumentTemplate
  - [ ] Test numbering integration dengan `NumberingService`
  - [ ] Replace `SampleTestProcessController::generateReport()` logic
  - [ ] E2E testing (dokumen legal)

### Phase 2: High Priority Documents (Week 3-4)

- [ ] **BA Penyerahan**
  - [ ] Buat `DocumentType::BA_PENYERAHAN`
  - [ ] Migrate view `pdf.ba-penyerahan.blade.php`
  - [ ] Refactor `DeliveryController` (3 methods)

- [ ] **Sample Receipt & Request Letter Receipt** (jika masih dipakai)
  - [ ] Verifikasi apakah endpoint masih perlu (currently disabled)
  - [ ] Jika ya: migrate ke DocumentTemplate
  - [ ] Jika tidak: remove legacy code

### Phase 3: Medium Priority (Week 5)

- [ ] **Form Preparation**
  - [ ] Buat `DocumentType::FORM_PREPARATION`
  - [ ] Migrate view `pdf.form-preparation.blade.php`
  - [ ] Refactor `SampleTestProcessController::generateForm()`

### Phase 4: Cleanup (Week 6)

- [ ] Remove deprecated code:
  - [ ] `RequestController::downloadDocument()` (L576) jika tidak dipakai
  - [ ] `pdf.handover-report.blade.php` (deprecated duplicate?)
- [ ] Update documentation
- [ ] Performance audit

---

## 7. Mapping ke DocumentType Enum

### Existing DocumentType (perlu dicek)

```php
// File: app/Enums/DocumentType.php (assumption)
enum DocumentType: string
{
    case BA_PENERIMAAN = 'ba_penerimaan'; // ✅ Already implemented
    
    // TO ADD:
    case BA_PENYERAHAN = 'ba_penyerahan';
    case LAPORAN_HASIL_UJI = 'laporan_hasil_uji';
    case FORM_PREPARATION = 'form_preparation';
    case SAMPLE_RECEIPT = 'sample_receipt';
    case REQUEST_LETTER_RECEIPT = 'request_letter_receipt';
    
    // Optional (jika utility tetap pakai DocumentTemplate):
    case SETTINGS_PREVIEW = 'settings_preview';
}
```

### Context Resolvers (perlu dibuat)

Setiap DocumentType perlu punya `DocumentContextResolver`:

| DocumentType | Context Model | Resolver Class |
|--------------|---------------|----------------|
| BA_PENERIMAAN | TestRequest | ✅ `BAPenerimaanContextResolver` (exists) |
| BA_PENYERAHAN | Delivery + TestRequest | `BAPenyerahanContextResolver` |
| LAPORAN_HASIL_UJI | SampleTestProcess | `LaporanHasilUjiContextResolver` |
| FORM_PREPARATION | SampleTestProcess | `FormPreparationContextResolver` |
| SAMPLE_RECEIPT | TestRequest | `SampleReceiptContextResolver` |
| REQUEST_LETTER_RECEIPT | TestRequest | `RequestLetterReceiptContextResolver` |

---

## 8. Migration Strategy

### Step-by-Step per Dokumen

1. **Buat DocumentType enum entry**
2. **Buat ContextResolver**:
   ```php
   class LaporanHasilUjiContextResolver implements DocumentContextResolver
   {
       public function getDocumentType(): DocumentType { return DocumentType::LAPORAN_HASIL_UJI; }
       public function resolveContext($contextId): array { /* fetch SampleTestProcess data */ }
       public function resolveSampleContext(): array { /* sample data untuk preview */ }
   }
   ```
3. **Register resolver** di `DocumentRenderService` atau ServiceProvider
4. **Migrate Blade view** ke `templates/` folder (atau keep di `resources/views/pdf/` as template source)
5. **Update Controller**:
   ```php
   // OLD:
   $html = view('pdf.laporan-hasil-uji', [...])->render();
   $pdf = Pdf::loadHTML($html)->output();
   
   // NEW:
   $rendered = $documentRenderService->render(
       type: DocumentType::LAPORAN_HASIL_UJI,
       contextId: $sampleProcess->id,
       format: DocumentFormat::PDF
   );
   return $rendered->toDownloadResponse();
   ```
6. **Test** dengan real data
7. **Deploy** dengan feature flag (optional)

---

## 9. Risks & Mitigasi

| Risk | Impact | Mitigation |
|------|--------|------------|
| Template versi lama break production | High | - Feature flag per DocumentType<br>- Parallel run (legacy + new)<br>- Gradual rollout |
| Numbering tidak konsisten (LHU) | Critical | - Test numbering integration terlebih dahulu<br>- Preserve existing `NumberingService` logic |
| Data context tidak lengkap | Medium | - Sample data generator di ContextResolver<br>- Validation sebelum render |
| Performance degradation | Low | - Benchmark sebelum/sesudah<br>- Cache compiled templates (Blade) |
| Missing template content | Medium | - Seed DocumentTemplate via command<br>- Migration script dari Blade views |

---

## 10. Testing Checklist

### Per Document Type

- [ ] Unit test ContextResolver
- [ ] Integration test DocumentRenderService::render()
- [ ] Visual regression test (PDF screenshot compare)
- [ ] E2E test: generate → store → download
- [ ] Load test (concurrent requests)

### Regression Testing

- [ ] Semua existing dokumen masih bisa di-generate
- [ ] Format PDF tidak berubah (layout, styling)
- [ ] Metadata Document table tetap valid
- [ ] Audit log tetap terekam

---

## 11. Dependencies

### Internal

- [app/Services/DocumentGeneration/DocumentRenderService.php](../app/Services/DocumentGeneration/DocumentRenderService.php)
- [app/Services/DocumentTemplates/DocumentTemplateRenderService.php](../app/Services/DocumentTemplates/DocumentTemplateRenderService.php)
- [app/Repositories/DocumentTemplateRepository.php](../app/Repositories/DocumentTemplateRepository.php)
- [app/Models/DocumentTemplate.php](../app/Models/DocumentTemplate.php)
- [app/Services/DocumentService.php](../app/Services/DocumentService.php) (storage helper)
- [app/Services/NumberingService.php](../app/Services/NumberingService.php) (untuk LHU numbering)

### External Packages

```json
{
  "barryvdh/laravel-dompdf": "^2.0",  // PDF rendering
  "spatie/browsershot": "^4.0"        // Alternative PDF rendering (Browsershot engine)
}
```

---

## 12. Next Steps

### Immediate Actions

1. **Diskusi dengan team**:
   - Verifikasi apakah `sample_receipt`, `request_letter_receipt`, `handover_report` masih dipakai
   - Tentukan priority (apakah LHU atau BA Penyerahan dulu?)
   - Timeline refactor (4-6 minggu?)

2. **Setup development**:
   - Buat feature branch: `feature/migrate-to-document-template-system`
   - Siapkan testing environment
   - Create sample data untuk testing

3. **Start with Critical Path**:
   - **Recommended:** Mulai dari BA Penyerahan (lebih sederhana dari LHU)
   - Atau **LHU** jika tim mau tackle yang paling critical dulu

### Long-term

- [ ] Semua dokumen menggunakan DocumentTemplate system
- [ ] Remove semua legacy `Pdf::loadView()` direct calls
- [ ] Centralized template management via admin UI
- [ ] Version control untuk templates
- [ ] A/B testing untuk template versions

---

## 13. Contact & Questions

**Document Owner:** Engineering Team  
**Last Updated:** 22 Desember 2025  
**Review Cycle:** Monthly (atau sebelum refactor major)

**Questions?**
- Check existing DocumentTemplate implementation di BA Penerimaan (RequestController::generateBeritaAcara)
- Review DocumentRenderService source code
- Ping team untuk diskusi prioritas
