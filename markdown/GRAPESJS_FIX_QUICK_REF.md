# GrapesJS Drag & Drop Fix - Quick Reference

## 🎯 Problem
Runtime errors saat drag komponen di GrapesJS:
- `TypeError: can't access property "getChildrenContainer", view is undefined`
- `TypeError: can't access property "length", dims is undefined`  
- `TypeError: can't access property "method", pos is undefined`

## ✅ Solution Summary

### 1. Protect DOM dengan x-ignore
**File**: [resources/views/settings/partials/templates.blade.php](resources/views/settings/partials/templates.blade.php#L146)
```blade
<div x-ignore class="h-[520px] rounded-lg overflow-hidden">
    <div x-ref="documentTemplateEditorCanvas" id="gjs"></div>
</div>
```

### 2. Visibility Check + Auto Refresh
**File**: [resources/js/pages/settings/template-editor.js](resources/js/pages/settings/template-editor.js#L18)
```javascript
// Check container visible before init
if (!container || container.offsetParent === null) {
    throw new Error('GrapesJS container is not visible');
}

// Auto refresh after init
setTimeout(() => editor.refresh(), 100);
```

### 3. Lifecycle Management
**File**: [resources/js/pages/settings/alpine-component.js](resources/js/pages/settings/alpine-component.js)

**Section change refresh**:
```javascript
set activeSection(value) {
    if (value === 'templates' && this.templateEditorInstance) {
        this.$nextTick(() => this.refreshTemplateEditor());
    }
}
```

**Enhanced ensureTemplateEditor**:
- ✅ Container existence check
- ✅ Visibility check with retry
- ✅ Auto refresh on return
- ✅ Better error messages

**New functions**:
- `refreshTemplateEditor()` - Call `editor.refresh()`
- `destroyTemplateEditor()` - Cleanup if needed

---

## 📋 Files Changed

| File | Lines Changed | Purpose |
|------|---------------|---------|
| `resources/views/settings/partials/templates.blade.php` | ~5 | x-ignore wrapper |
| `resources/js/pages/settings/template-editor.js` | ~10 | Visibility check + refresh |
| `resources/js/pages/settings/alpine-component.js` | ~50 | Lifecycle management |

---

## 🧪 Quick Test

```bash
# Run automated checks
./verify-grapesjs-fix.sh

# Manual test
1. Open /settings → Template Dokumen
2. Click "New Template"
3. Drag "Section" block to canvas
4. Drag "Table" block to canvas
5. ✅ NO Sorter.ts errors in console
6. ✅ Drag & drop works smoothly
```

---

## 🔍 Root Cause

1. **Init saat hidden** → `offsetParent === null` → GrapesJS can't calculate dims
2. **Alpine reactivity** → Interferes with GrapesJS DOM → Lost references
3. **No refresh** → Section change → Layout outdated → Sorter breaks

---

## 🎉 Result

| Before | After |
|--------|-------|
| ❌ Sorter.ts errors | ✅ No errors |
| ❌ Drag fails | ✅ Smooth drag & drop |
| ❌ Init when hidden | ✅ Only init when visible |
| ❌ No section refresh | ✅ Auto refresh |

---

**See**: [GRAPESJS_DRAG_DROP_FIX.md](GRAPESJS_DRAG_DROP_FIX.md) for detailed documentation
