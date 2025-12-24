# Quick Reference: Template Editor API Wiring

## 🎯 Implementation Summary

**Files Modified:** 2  
**Build Status:** ✅ Success (9.30 kB editor.js)  
**Routes Added:** 1 (GET preview)

---

## 📋 Changes Overview

### 1. resources/js/templates/editor.js

**Major Improvements:**

```javascript
// ✅ On-load: Fetch from API
async function loadTemplateFromAPI() {
    const response = await fetch(`/api/templates/${id}`, ...);
    const template = response.data;
    
    // Priority: GrapesJS project data
    if (template.gjs_components?.length > 0) {
        editor.loadProjectData({
            styles: template.gjs_styles,
            pages: [{ component: template.gjs_components }],
        });
    } else {
        // Fallback: HTML + CSS
        editor.setComponents(template.content_html);
        editor.setStyle(template.content_css);
    }
}

// ✅ Save: All editor state
async function saveDraft() {
    const projectData = editor.getProjectData();
    await fetch(`/api/templates/${id}`, {
        method: 'PUT',
        body: JSON.stringify({
            content_html: editor.getHtml(),
            content_css: editor.getCss(),
            gjs_components: projectData.pages[0]?.component,
            gjs_styles: projectData.styles,
        }),
    });
}

// ✅ Preview: Auto-save → Open tab
btnPreview.addEventListener('click', async () => {
    if (isDirty) await saveDraft();
    window.open(`/api/templates/${id}/preview`, '_blank');
});

// ✅ Issue & Activate: Confirm → Reload
btnIssue.addEventListener('click', async () => {
    if (!confirm('...')) return;
    await fetch(`/api/templates/${id}/issue`, { method: 'PUT' });
    setTimeout(() => location.reload(), 1500);
});
```

**UX Enhancements:**
- Button disable/enable during requests
- Error messages use `textContent` (XSS-safe)
- Unsaved changes warning
- Loading states for all actions

---

### 2. routes/api.php

**Added:**
```php
Route::get('/{template}/preview', [TemplateEditorController::class, 'preview'])
    ->name('preview-get');
```

---

## 🔗 API Routes

```
GET    /api/templates              → List all
GET    /api/templates/{id}         → Get one (on-load)
POST   /api/templates              → Create
PUT    /api/templates/{id}         → Update (save draft)
PUT    /api/templates/{id}/issue   → Mark as issued
PUT    /api/templates/{id}/activate → Activate
GET    /api/templates/{id}/preview → Preview PDF (new)
POST   /api/templates/{id}/preview → Preview PDF (legacy)
DELETE /api/templates/{id}         → Delete
```

---

## 🧪 Quick Test

### Minimal Test (5 minutes)

```bash
# 1. Start server
php artisan serve

# 2. Login as admin
open http://localhost:8000/login

# 3. Create template
open http://localhost:8000/templates
# Click "Buat Template Baru"
# Name: "Test", Type: "BA"

# 4. Edit
# Drag "Section" block
# Add text: "Test {{request_number}}"
# Click "Save Draft" → Success alert

# 5. Preview
# Click "Preview PDF" → PDF opens in new tab
# Verify token replaced: "REQ-2025-0001"

# 6. Issue
# Click "Issue" → Confirm → Page reloads
# Verify button disabled

# 7. Activate
# Click "Activate" → Confirm → Page reloads
# Verify badge "Aktif"
```

**Expected:** All steps complete without errors

---

## 📊 Build Output

```bash
npm run build

✓ built in 4.96s

Assets:
public/build/assets/editor-CDV2mFgo.js  9.30 kB │ gzip: 3.34 kB
public/build/assets/grapes-8xpIby7C.js  986 kB  │ gzip: 271.85 kB
```

---

## 🐛 Common Issues

### Issue: Editor doesn't load data

**Check:**
```javascript
// Browser console
const response = await fetch('/api/templates/2');
const data = await response.json();
console.log(data.data.gjs_components);
```

**Fix:** Ensure template has saved data

---

### Issue: Preview shows {{tokens}}

**Check:** Token format must be `{{token}}` (2 braces)

**Valid:**
- `{{request_number}}` ✅
- `{request_number}` ❌
- `{{ request_number }}` ❌ (spaces)

---

### Issue: Save fails with 419

**Fix:** Refresh page (CSRF expired)

---

## 📚 Documentation

**Full Guides:**
- [TEMPLATE_EDITOR_MANUAL_TEST.md](TEMPLATE_EDITOR_MANUAL_TEST.md) - Complete testing
- [TEMPLATE_EDITOR_API_WIRING.md](TEMPLATE_EDITOR_API_WIRING.md) - Implementation details
- [GRAPESJS_QUICK_REF.md](GRAPESJS_QUICK_REF.md) - User guide

---

## ✅ Completion Checklist

- [x] On-load fetch from API
- [x] loadProjectData for GrapesJS
- [x] Save all editor state
- [x] Preview auto-saves
- [x] Issue & Activate with confirm
- [x] Button disable/enable
- [x] textContent for errors
- [x] Unsaved changes warning
- [x] Build successful
- [x] Routes registered
- [x] Documentation created

**Status:** 🎉 COMPLETE

---

**Next:** Run manual tests from [TEMPLATE_EDITOR_MANUAL_TEST.md](TEMPLATE_EDITOR_MANUAL_TEST.md)
