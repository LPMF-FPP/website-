# Template Editor - Manual Testing Guide

**Last Updated:** December 22, 2025  
**Version:** 1.0  
**Prerequisites:** Admin/Superadmin account, Laravel server running, assets built

---

## 🎯 Testing Objectives

Verify complete workflow:
1. Create new template
2. Edit template in GrapesJS
3. Save draft
4. Preview PDF
5. Issue template
6. Activate template

---

## 📋 Pre-Test Checklist

### ✅ Environment Setup
```bash
# 1. Start Laravel server
cd /home/lpmf-dev/website-
php artisan serve

# 2. Verify assets built
ls -lh public/build/assets/editor-*.js
# Should show: editor-CDV2mFgo.js (9.30 kB)

# 3. Check routes
php artisan route:list --path=templates
# Should show web routes + API routes
```

### ✅ Login Requirements
- **URL:** http://localhost:8000/login
- **User:** Admin or Superadmin
- **Permission:** `manage-settings` gate required

---

## 🧪 Test Scenarios

### Scenario 1: Create New Template

**Steps:**

1. **Navigate to Template Index**
   ```
   URL: http://localhost:8000/templates
   Expected: Template list page loads
   ```

2. **Click "Buat Template Baru"**
   - Modal should appear
   - Form has 2 fields: Nama Template, Tipe Dokumen

3. **Fill Form**
   - **Nama Template:** "Template BA Penerimaan Sampel - Test"
   - **Tipe Dokumen:** Select "BA - Berita Acara"

4. **Submit Form**
   - Click "Buat & Edit"
   - Should redirect to: `/templates/editor/{id}`
   - Alert: "Template berhasil dibuat!" (green)

**Expected Results:**
- ✅ Redirect to editor page
- ✅ GrapesJS editor loads
- ✅ Template ID in URL matches created template
- ✅ Page title shows template name
- ✅ All action buttons visible

**Validation:**
```bash
# Check database
php artisan tinker --execute="
\$template = \App\Models\DocumentTemplate::latest()->first();
echo 'ID: ' . \$template->id . PHP_EOL;
echo 'Name: ' . \$template->name . PHP_EOL;
echo 'Doc Type: ' . \$template->doc_type . PHP_EOL;
echo 'Status: ' . \$template->status . PHP_EOL;
"
# Expected: status = 'draft'
```

---

### Scenario 2: Edit Template in GrapesJS

**Steps:**

1. **Add Section Block**
   - From left sidebar, drag "Section" block
   - Drop into canvas
   - Section should appear with "Section Title" and "Section content"

2. **Edit Content**
   - Click on "Section Title"
   - Change text to: "BERITA ACARA PENERIMAAN SAMPEL"
   - Click on "Section content"
   - Change text to: "Nomor: {{request_number}}"

3. **Add Table**
   - Drag "Table" block from sidebar
   - Drop below section
   - Edit cell content to:
     ```
     Cell 1: No
     Cell 2: Jenis Sampel
     Cell 3: 1
     Cell 4: {{sample_types}}
     ```

4. **Style Section**
   - Click section element
   - Right sidebar → Style Manager
   - Typography → Font Size: 16px
   - Decorations → Background Color: #f9fafb

**Expected Results:**
- ✅ Drag-and-drop works smoothly
- ✅ Text editing is immediate
- ✅ Token {{request_number}} visible in editor
- ✅ Styles apply correctly
- ✅ No console errors

**Screenshot Checkpoint:**
- Canvas should show custom content with tokens

---

### Scenario 3: Save Draft

**Steps:**

1. **Click "💾 Save Draft" Button**
   - Button text changes to "💾 Saving..."
   - All buttons disable during save

2. **Wait for Response**
   - Alert appears: "✓ Draft tersimpan!" (green)
   - Buttons re-enable
   - Button text back to "💾 Save Draft"

3. **Verify Save**
   - Refresh page (F5)
   - Content should persist (not lost)
   - All blocks, text, and styles should reload

**Expected Results:**
- ✅ Save completes in < 2 seconds
- ✅ Success alert shown
- ✅ No errors in browser console
- ✅ Data persists after refresh

**API Validation:**
```bash
# Check network tab (F12 → Network)
# Request: PUT /api/templates/{id}
# Status: 200 OK
# Response: { "success": true, "data": {...} }
```

**Database Validation:**
```bash
php artisan tinker --execute="
\$template = \App\Models\DocumentTemplate::find({ID});
echo 'HTML Length: ' . strlen(\$template->content_html) . ' bytes' . PHP_EOL;
echo 'CSS Length: ' . strlen(\$template->content_css) . ' bytes' . PHP_EOL;
echo 'Components: ' . count(\$template->gjs_components) . ' items' . PHP_EOL;
echo 'Styles: ' . count(\$template->gjs_styles) . ' items' . PHP_EOL;
"
# Expected: All fields populated
```

---

### Scenario 4: Preview PDF

**Steps:**

1. **Make Minor Edit**
   - Change some text in editor
   - Don't save manually

2. **Click "👁️ Preview PDF" Button**
   - Button text: "👁️ Saving..." → "👁️ Opening..."
   - Alert: "Saving draft before preview..." (blue)
   - Then: "✓ Preview opened in new tab" (green)

3. **Check New Tab**
   - PDF opens in new browser tab
   - PDF shows rendered content with styles
   - Tokens replaced with sample data:
     * {{request_number}} → "REQ-2025-0001"
     * {{sample_types}} → Example value
   - Layout matches editor preview

**Expected Results:**
- ✅ Auto-save before preview
- ✅ New tab opens with PDF
- ✅ PDF renders correctly (A4 size)
- ✅ Tokens replaced, not visible as {{token}}
- ✅ Styles applied (colors, fonts, spacing)

**Browser DevTools Check:**
```javascript
// Network tab should show:
// 1. PUT /api/templates/{id} (save)
// 2. GET /api/templates/{id}/preview (PDF)
```

**PDF Content Validation:**
- Check header/footer rendered
- Check table borders visible
- Check font sizes match design
- No "{{unknown_token}}" visible (should be kept as-is if not in whitelist)

---

### Scenario 5: Issue Template

**Steps:**

1. **Click "✓ Issue" Button**
   - Confirmation dialog appears:
     > "Issue template ini? Template akan menjadi final dan tidak bisa diubah statusnya kembali."
   - Click "OK"

2. **Wait for Processing**
   - Button text: "✓ Issuing..."
   - Alert: "✓ Template berhasil di-issue!" (green)
   - Page reloads after 1.5 seconds

3. **After Reload**
   - Page title shows: "Status: issued"
   - "✓ Issue" button is **disabled**
   - Template status badge shows "issued" (green)

**Expected Results:**
- ✅ Confirmation required before issue
- ✅ API call succeeds
- ✅ Page reloads automatically
- ✅ Status updated to "issued"
- ✅ Issue button disabled (can't re-issue)

**Database Validation:**
```bash
php artisan tinker --execute="
\$template = \App\Models\DocumentTemplate::find({ID});
echo 'Status: ' . \$template->status . PHP_EOL;
echo 'Issued At: ' . \$template->issued_at . PHP_EOL;
"
# Expected: status = 'issued', issued_at = timestamp
```

**API Validation:**
```
PUT /api/templates/{id}/issue
Response: 200 OK
{
  "success": true,
  "message": "Template marked as issued",
  "data": { "status": "issued", ... }
}
```

---

### Scenario 6: Activate Template

**Prerequisites:**
- Template must be "issued" status
- Can have other templates with same doc_type

**Steps:**

1. **Click "⚡ Activate" Button**
   - Confirmation dialog:
     > "Aktifkan template ini? Template lain dengan doc_type yang sama akan otomatis di-nonaktifkan."
   - Click "OK"

2. **Wait for Processing**
   - Button text: "⚡ Activating..."
   - Alert: "✓ Template berhasil diaktifkan!" (green)
   - Page reloads after 1.5 seconds

3. **After Reload**
   - Active badge shows "Aktif" (blue)
   - "⚡ Activate" button is **disabled**

4. **Verify Other Templates Deactivated**
   - Go back to template index: `/templates`
   - Filter by doc_type: BA
   - Other BA templates should show "Tidak Aktif"

**Expected Results:**
- ✅ Confirmation required
- ✅ API call succeeds
- ✅ Page reloads
- ✅ Current template is_active = true
- ✅ Other templates with same doc_type deactivated
- ✅ Transaction commit successful (atomic operation)

**Database Validation:**
```bash
php artisan tinker --execute="
// Check current template
\$template = \App\Models\DocumentTemplate::find({ID});
echo 'Current Active: ' . (\$template->is_active ? 'YES' : 'NO') . PHP_EOL;

// Check other templates
\$others = \App\Models\DocumentTemplate::where('doc_type', \$template->doc_type)
    ->where('id', '!=', \$template->id)
    ->get(['id', 'name', 'is_active']);

foreach (\$others as \$t) {
    echo sprintf('ID %d: %s - Active: %s', \$t->id, \$t->name, \$t->is_active ? 'YES' : 'NO') . PHP_EOL;
}
"
# Expected: Current = YES, Others = NO
```

---

## 🔍 Error Scenarios (Negative Testing)

### Test 1: Save with Network Offline

**Steps:**
1. Open DevTools → Network tab
2. Select "Offline" from throttling dropdown
3. Edit template content
4. Click "Save Draft"

**Expected:**
- ❌ Alert: "Gagal menyimpan: Failed to fetch" (red)
- ❌ Buttons re-enable after error
- ❌ No page reload

---

### Test 2: Activate Non-Issued Template

**Steps:**
1. Create new template (status: draft)
2. Try to activate without issuing first

**Expected:**
- Button should be enabled for draft
- Click activate → API should return error
- Alert shows error message

---

### Test 3: Leave Page with Unsaved Changes

**Steps:**
1. Edit template content
2. Don't save
3. Try to close tab or navigate away

**Expected:**
- ⚠️ Browser warning: "You have unsaved changes. Are you sure you want to leave?"
- User can choose to stay or leave

---

## 📊 Performance Benchmarks

### Load Time
- **Template Index:** < 1 second
- **GrapesJS Editor Load:** < 3 seconds
- **Save Draft:** < 2 seconds
- **Preview PDF:** < 5 seconds (depends on content complexity)

### Bundle Size
```
editor-CDV2mFgo.js: 9.30 kB (3.34 kB gzip)
grapes-8xpIby7C.js: 986.01 kB (271.85 kB gzip)
Total: ~275 kB gzipped
```

---

## 🐛 Common Issues & Solutions

### Issue: GrapesJS Not Loading

**Symptoms:**
- White canvas area
- No blocks in sidebar

**Solutions:**
1. Check browser console for errors
2. Verify CDN CSS loaded:
   ```html
   <link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
   ```
3. Hard refresh: Ctrl+Shift+R
4. Check `public/build/assets/grapes-*.js` exists

---

### Issue: Save Fails with 419 Error

**Symptoms:**
- Alert: "Gagal menyimpan: HTTP 419"
- Console: CSRF token mismatch

**Solutions:**
1. Refresh page (CSRF token expired)
2. Check `<meta name="csrf-token">` in page source
3. Verify `X-CSRF-TOKEN` header in network request

---

### Issue: Preview Shows {{tokens}} Instead of Values

**Symptoms:**
- PDF preview shows "{{request_number}}" literally

**Expected Behavior:**
- Tokens in whitelist should be replaced
- Unknown tokens kept as-is

**Check:**
1. Token format must be exact: `{{token_name}}` (2 curly braces)
2. Token must be in whitelist (see controller's `getSampleData()`)
3. Check PDF source in browser DevTools

---

### Issue: Activate Doesn't Deactivate Others

**Symptoms:**
- Multiple templates with is_active = true

**Solutions:**
1. Check database transaction committed
2. Verify controller uses `DB::beginTransaction()` and `DB::commit()`
3. Check logs: `storage/logs/laravel.log`

---

## ✅ Success Criteria

All tests pass when:

- [x] Can create new template via modal
- [x] GrapesJS editor loads and is interactive
- [x] Drag-and-drop blocks work
- [x] Content editing works (text, styles, tokens)
- [x] Save draft persists data (verified by refresh)
- [x] Preview PDF opens in new tab with correct rendering
- [x] Tokens replaced with sample data in PDF
- [x] Issue template works and prevents re-issue
- [x] Activate template works and deactivates others
- [x] All buttons disable during requests
- [x] Error messages use textContent (no XSS)
- [x] Unsaved changes warning works
- [x] No console errors during normal operation
- [x] Network requests return 200 OK
- [x] Database state matches UI state

---

## 📝 Test Report Template

```
Test Date: _______________
Tester: _______________
Environment: [ ] Local [ ] Staging [ ] Production

| Scenario | Status | Notes |
|----------|--------|-------|
| Create Template | ☐ Pass ☐ Fail | |
| Edit in GrapesJS | ☐ Pass ☐ Fail | |
| Save Draft | ☐ Pass ☐ Fail | |
| Preview PDF | ☐ Pass ☐ Fail | |
| Issue Template | ☐ Pass ☐ Fail | |
| Activate Template | ☐ Pass ☐ Fail | |
| Error Handling | ☐ Pass ☐ Fail | |

Overall: ☐ PASS ☐ FAIL

Issues Found:
1. _______________
2. _______________
```

---

## 🔗 Related Documentation

- [Implementation Guide](docs/grapesjs-ui-implementation.md)
- [File Changes Summary](GRAPESJS_UI_FILE_CHANGES.md)
- [Quick Reference](GRAPESJS_QUICK_REF.md)
- [API Documentation](docs/template-editor-api.md)

---

**Last Updated:** December 22, 2025  
**Next Review:** When new features added
