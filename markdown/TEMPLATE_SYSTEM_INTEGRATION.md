# Template System Integration for BA/LHU Generation

## Summary
Integrated document template system (`document_templates`) with existing BA/LHU generators while maintaining backward compatibility.

**Changes:** 3 files modified  
**Status:** ✅ Complete - Routes unchanged, fallback implemented

---

## What Was Done

### 1. ✅ Created DocumentTemplateService

**File:** `app/Services/DocumentTemplateService.php`

**Purpose:** Centralized service for template operations

**Key Methods:**
```php
// Get active template by doc_type
getActiveTemplateByDocType(string $docType): ?DocumentTemplate

// Render HTML from template with token replacement
renderHtmlFromTemplate(DocumentTemplate $template, array $data): string

// Calculate template hash for versioning
calculateTemplateHash(DocumentTemplate $template): string
```

**Features:**
- ✅ Fetches active template (is_active=true, status='issued')
- ✅ Extracts HTML from GrapesJS components or content_html
- ✅ Token replacement with whitelist (security)
- ✅ CSS wrapping support
- ✅ Template hash calculation for tracking

**Token Whitelist (Security):**
```php
$allowedTokens = [
    'request_number', 'case_number', 'to_office', 'generated_at',
    'investigator_name', 'investigator_nrp', 'investigator_rank',
    'suspect_name', 'sample_name', 'sample_code',
    'test_date', 'analyst_name', 'active_substance',
    'lhu_number', 'report_number', 'instrument', 'conclusion',
    'lab_name', 'lab_address', ...
];
```

---

### 2. ✅ Updated BA Generation (RequestController)

**File:** `app/Http/Controllers/RequestController.php`

**Method:** `generateBeritaAcara(TestRequest $request)`

**Flow:**
```
1. Try to get active template (doc_type='BA')
   ├─ If template found:
   │  ├─ Prepare data array with request info
   │  ├─ Render HTML via DocumentTemplateService
   │  ├─ Generate PDF via PdfRenderService
   │  ├─ Save PDF with template metadata
   │  └─ Log template usage
   └─ If NO template:
      ├─ Log warning
      ├─ Fallback to DocumentRenderService (legacy)
      └─ Return immediately (maintain old behavior)
```

**Data Prepared for BA Template:**
```php
$data = [
    'request_number' => $request->request_number,
    'case_number' => $request->case_number,
    'to_office' => $request->to_office,
    'generated_at' => now()->format('d F Y'),
    'investigator_name' => $inv->name,
    'investigator_nrp' => $inv->nrp,
    'investigator_rank' => $inv->rank,
    'investigator_jurisdiction' => $inv->jurisdiction,
    'investigator_phone' => $inv->phone,
    'suspect_name' => $request->suspect_name,
    'suspect_gender' => $request->suspect_gender,
    'suspect_age' => $request->suspect_age,
    'suspect_address' => $request->suspect_address,
    'sample_count' => $request->samples->count(),
    'lab_name' => 'Pusdokkes Polri',
    'lab_address' => 'Jakarta',
];
```

**Template Metadata Saved:**
```php
$doc->extra = [
    'template_id' => $template->id,
    'template_version' => $template->version,
    'template_hash' => hash('sha256', template_content),
    ...
];
```

---

### 3. ✅ Updated LHU Generation (SampleTestProcessController)

**File:** `app/Http/Controllers/SampleTestProcessController.php`

**Method:** `generateReport(SampleTestProcess $sampleProcess, ...)`

**Flow:**
```
1. Issue/reuse LHU number (unchanged logic)
2. Try to get active template (doc_type='LHU')
   ├─ If template found:
   │  ├─ Extract test results from metadata
   │  ├─ Prepare comprehensive data array
   │  ├─ Render HTML via DocumentTemplateService
   │  ├─ Generate PDF via PdfRenderService
   │  ├─ Save PDF with template metadata
   │  └─ Log template usage
   └─ If NO template:
      ├─ Log warning
      ├─ Fallback to legacy view (pdf.laporan-hasil-uji)
      └─ Continue with old DomPDF rendering
```

**Data Prepared for LHU Template:**
```php
$data = [
    'lhu_number' => $lhuNumber,
    'report_number' => $lhuNumber,
    'request_number' => $sampleProcess->sample->testRequest->request_number,
    'case_number' => $sampleProcess->sample->testRequest->case_number,
    'generated_at' => now()->format('d F Y'),
    'investigator_name' => $investigator->name,
    'investigator_nrp' => $investigator->nrp,
    'investigator_rank' => $investigator->rank,
    'sample_name' => $sample->sample_name,
    'sample_code' => $sample->sample_code,
    'sample_type' => $sample->sample_form,
    'sample_weight' => $sample->sample_weight,
    'test_date' => $completed_at->format('d F Y'),
    'analyst_name' => $analyst->name,
    'active_substance' => $forcedActive,
    'detected_substance' => $detectedSubstance,
    'instrument' => $instrument,
    'test_result' => $testResultText, // 'Positif', 'Negatif', etc
    'test_result_text' => $testResultText,
    'conclusion' => "Barang bukti mengandung {$detectedSubstance}",
    'lab_name' => 'Pusdokkes Polri',
    'lab_address' => 'Jakarta',
];
```

---

## Fallback Logic

### BA Fallback
```php
if ($template) {
    // Use template system + PdfRenderService
} else {
    // Use DocumentRenderService (legacy)
    Log::warning('No active template for BA, using legacy view');
    return $renderService->render(DocumentType::BA_PENERIMAAN, $request->id);
}
```

### LHU Fallback
```php
if ($template) {
    // Use template system + PdfRenderService
} else {
    // Use legacy Blade view
    Log::warning('No active template for LHU, using legacy view');
    $html = view('pdf.laporan-hasil-uji', [...]);
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output();
}
```

**Fallback Trigger:** No active template where:
- `doc_type` = 'BA' or 'LHU'
- `is_active` = true
- `status` = 'issued'

---

## File Changes

### Created Files
1. **app/Services/DocumentTemplateService.php** (NEW - 350 lines)
   - Service for template retrieval and rendering
   - Token replacement with security whitelist
   - GrapesJS component extraction
   - Template hash calculation

### Modified Files
1. **app/Http/Controllers/RequestController.php**
   - Method: `generateBeritaAcara()`
   - Added template lookup and rendering
   - Added fallback to legacy system
   - Added template metadata tracking

2. **app/Http/Controllers/SampleTestProcessController.php**
   - Method: `generateReport()`
   - Added template lookup and rendering
   - Added fallback to legacy view
   - Added template metadata tracking

---

## Route Verification

### BA Routes (Unchanged)
```
POST   /requests/{request}/berita-acara/generate  →  generateBeritaAcara
GET    /requests/{request}/berita-acara/check     →  checkBeritaAcara
GET    /requests/{request}/berita-acara/download  →  downloadBeritaAcara
GET    /requests/{request}/berita-acara/view      →  viewBeritaAcara
```

### LHU Routes (Unchanged)
```
POST   /sample-processes/{sampleProcess}/generate-report  →  generateReport
```

**✅ No route changes - all existing URLs work unchanged**

---

## Database Schema

### Template Metadata in Documents Table

**Existing `documents` table has `extra` JSON column.**

**New metadata saved:**
```json
{
  "template_id": 123,
  "template_version": 2,
  "template_hash": "a1b2c3d4e5f6...",
  ...existing extra data...
}
```

**Query to check template usage:**
```sql
SELECT 
    d.id,
    d.filename,
    d.document_type,
    d.extra->>'template_id' as template_id,
    d.extra->>'template_version' as version,
    d.created_at
FROM documents d
WHERE d.extra->>'template_id' IS NOT NULL
ORDER BY d.created_at DESC;
```

---

## Testing Guide

### Test 1: BA Generation with Template

**Prerequisites:**
1. Create BA template in GrapesJS editor
2. Add tokens like `{{request_number}}`, `{{investigator_name}}`
3. Set status = 'issued', is_active = true, doc_type = 'BA'

**Steps:**
```bash
# 1. Login to system
# 2. Go to request detail page
# 3. Click "Generate Berita Acara"
# 4. Check logs for:
```

**Expected Logs:**
```
[INFO] Active template found
  doc_type: BA
  template_id: 123
  template_name: "BA Penerimaan Template v2"

[INFO] Using active template for BA generation
  template_id: 123
  request_id: 456

[INFO] BA generated with template metadata
  document_id: 789
  template_id: 123
  template_version: 2
```

**Verify PDF:**
- Open generated PDF
- Check that tokens are replaced with actual data
- Verify layout matches template design

### Test 2: BA Generation WITHOUT Template (Fallback)

**Prerequisites:**
- No active BA template exists
- Or deactivate existing template

**Steps:**
```bash
# 1. Deactivate all BA templates
UPDATE document_templates SET is_active = false WHERE doc_type = 'BA';

# 2. Generate BA as usual
```

**Expected Logs:**
```
[WARNING] No active template found for doc_type
  doc_type: BA

[WARNING] No active template for BA, using legacy view
  request_id: 456
```

**Verify:**
- PDF still generates successfully
- Uses legacy blade view (pdf.ba-penerimaan or DocumentRenderService)
- No template metadata in document.extra

### Test 3: LHU Generation with Template

**Prerequisites:**
1. Create LHU template with tokens:
   - `{{lhu_number}}`, `{{sample_name}}`, `{{detected_substance}}`
2. Complete sample testing process
3. Add interpretation with test results

**Steps:**
```bash
# 1. Go to sample process detail page
# 2. Click "Generate Laporan Hasil Uji"
# 3. Check logs and PDF
```

**Expected Logs:**
```
[INFO] LHU number issued
  scope: lhu
  number: FLHU123

[INFO] Using active template for LHU generation
  template_id: 456
  process_id: 789

[INFO] LHU generated with template metadata
  document_id: 101
  template_id: 456
  template_version: 1
```

**Verify PDF:**
- LHU number matches
- Sample data correctly displayed
- Test results appear
- Analyst name, conclusion text present

### Test 4: LHU Generation WITHOUT Template (Fallback)

**Prerequisites:**
- Deactivate all LHU templates

**Steps:**
```bash
UPDATE document_templates SET is_active = false WHERE doc_type = 'LHU';
# Generate LHU as usual
```

**Expected:**
- Legacy view (pdf.laporan-hasil-uji) used
- DomPDF rendering (not PdfRenderService)
- No template metadata saved

### Test 5: Template Change Detection

**Steps:**
```bash
# 1. Generate BA/LHU with Template v1
# 2. Edit template, increase version to v2, issue
# 3. Generate again

# 4. Query database:
SELECT 
    filename,
    extra->>'template_id' as tpl_id,
    extra->>'template_version' as version,
    extra->>'template_hash' as hash
FROM documents
WHERE document_type IN ('ba_penerimaan', 'laporan_hasil_uji')
ORDER BY created_at DESC
LIMIT 5;
```

**Expected:**
- First document: version=1, hash=abc123
- Second document: version=2, hash=def456
- Different hash indicates template change

---

## Verification Checklist

- [ ] **BA Generation with Template**
  - [ ] Template fetched correctly
  - [ ] Tokens replaced with real data
  - [ ] PDF generated via PdfRenderService
  - [ ] Template metadata saved in document.extra
  - [ ] Logs show template usage

- [ ] **BA Generation WITHOUT Template (Fallback)**
  - [ ] Warning logged
  - [ ] Legacy system used (DocumentRenderService)
  - [ ] PDF still generates correctly
  - [ ] No template metadata

- [ ] **LHU Generation with Template**
  - [ ] Template fetched correctly
  - [ ] Sample data mapped to tokens
  - [ ] Test results appear correctly
  - [ ] PDF generated via PdfRenderService
  - [ ] Template metadata saved

- [ ] **LHU Generation WITHOUT Template (Fallback)**
  - [ ] Warning logged
  - [ ] Legacy blade view used
  - [ ] DomPDF rendering works
  - [ ] LHU number issued correctly

- [ ] **Routes Unchanged**
  - [ ] All BA routes work (check, generate, download, view)
  - [ ] All LHU routes work (generate-report)
  - [ ] No breaking changes for existing users

- [ ] **Security**
  - [ ] Only whitelisted tokens replaced
  - [ ] XSS prevention in token values
  - [ ] Template content sanitized

---

## Common Issues & Solutions

### Issue: Template not found despite being active
**Cause:** Status not 'issued' or doc_type mismatch  
**Fix:** 
```sql
UPDATE document_templates 
SET status = 'issued', is_active = true 
WHERE doc_type = 'BA' AND id = 123;
```

### Issue: Tokens not replaced
**Cause:** Token not in whitelist or data key mismatch  
**Fix:** Check DocumentTemplateService::$allowedTokens array

### Issue: PDF generation fails
**Cause:** PdfRenderService not configured  
**Fix:** Check .env for BROWSERSHOT_* variables or use fallback

### Issue: Missing template metadata
**Cause:** Document saved before template lookup  
**Fix:** Metadata added AFTER PDF generation, check $doc->save() called

---

## Next Steps

1. **Create Default Templates:**
   ```bash
   # Via GrapesJS UI or SQL:
   INSERT INTO document_templates (doc_type, name, status, is_active, ...)
   VALUES ('BA', 'Default BA Template', 'issued', true, ...);
   ```

2. **Monitor Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i template
   ```

3. **Test with Real Data:**
   - Generate BA for actual request
   - Generate LHU for completed test
   - Verify PDF output quality

4. **Migrate Existing Documents:**
   - Optionally regenerate old documents with new templates
   - Preserve original PDFs as backups

---

## Files Summary

```
app/Services/DocumentTemplateService.php          (NEW - 350 lines)
app/Http/Controllers/RequestController.php        (MODIFIED - generateBeritaAcara)
app/Http/Controllers/SampleTestProcessController.php  (MODIFIED - generateReport)
```

**Status:** ✅ Integration complete, routes unchanged, backward compatible
