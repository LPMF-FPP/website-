# GrapesJS Editor Layout Fix - Quick Reference

## ✅ What Was Fixed

1. **Layout tidak sempit:** Flex layout with `flex-1 min-w-0` on canvas area
2. **Init saat visible:** Editor checks visibility before initialization
3. **Refresh setelah tab switch:** IntersectionObserver + `editor.refresh()`
4. **Panel lengkap:** Blocks, Layers, Styles, Traits in dedicated containers
5. **A4 canvas:** 794px width, centered, with shadow

---

## 📋 File Changes

### 1. `resources/views/templates/editor.blade.php`

**Layout Structure:**
```
┌─────────────────────────────────────────────────────┐
│              Header & Actions                        │
├──────────┬────────────────────────┬─────────────────┤
│ Blocks   │   Toolbar (devices)    │   Styles        │
│ (w-64)   │                        │   (w-72)        │
│          │   Canvas (flex-1)      │                 │
│ Layers   │   A4: 794px centered   │   Settings      │
│          │                        │                 │
└──────────┴────────────────────────┴─────────────────┘
```

**Key Changes:**
- Wrapper: `<div class="flex gap-0" style="min-height: 75vh;">`
- Canvas: `<div class="flex-1 min-w-0 flex flex-col">`
- Canvas inner: `<div id="gjs-editor" class="mx-auto" style="max-width: 794px; min-height: 600px;">`
- Containers: `#blocks-container`, `#layers-container`, `#styles-container`, `#traits-container`

### 2. `resources/js/templates/editor.js`

**Visibility-Aware Init:**
```javascript
function isEditorVisible() {
    const container = document.getElementById('gjs-editor');
    const rect = container.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
}

function initializeEditor() {
    if (!isEditorVisible()) {
        setTimeout(initializeEditor, 100);
        return;
    }
    editor = grapesjs.init({ ... });
}

// IntersectionObserver for tab switches
const observer = new IntersectionObserver((entries) => {
    if (entry.isIntersecting && editor) {
        editor.refresh();
    }
});
```

**Panel Configuration:**
```javascript
blockManager: { appendTo: '#blocks-container', ... },
layerManager: { appendTo: '#layers-container' },
styleManager: { appendTo: '#styles-container', ... },
traitManager: { appendTo: '#traits-container' },
```

---

## 🧪 Quick Test (5 min)

```bash
# 1. Build assets
npm run build

# 2. Start server
php artisan serve

# 3. Open editor
# Navigate to: http://localhost:8000/templates/{id}/edit

# 4. Verify checklist:
# ✅ Left panel shows 8 blocks (Section, Text, Heading, etc.)
# ✅ Canvas is 794px wide, centered, with shadow
# ✅ Right panel shows Styles + Settings
# ✅ Canvas area not cramped (min 600px tall)
# ✅ No horizontal scroll (unless very narrow window)

# 5. Test visibility (if in tabs):
# - Hide editor section
# - Console: "Editor container not visible, delaying..."
# - Show editor section
# - Console: "Initializing GrapesJS editor..."
# - Switch tabs back to editor
# - Console: "Editor became visible, refreshing..."
```

---

## ✅ Checklist

- [x] **flex-1 min-w-0:** Canvas area has both classes
- [x] **init saat visible:** `isEditorVisible()` checks before init
- [x] **refresh setelah tab:** `IntersectionObserver` calls `editor.refresh()`
- [x] **min-height: 75vh:** Wrapper has explicit height
- [x] **A4 canvas:** 794px max-width, centered
- [x] **Panel containers:** 4 dedicated `#*-container` divs
- [x] **Build success:** editor-D4HIwH1R.js (11.81 kB)

---

## 🔍 Common Issues

| Issue | Fix |
|-------|-----|
| Panels overlay canvas | Check container IDs match `appendTo` targets |
| Editor not initializing | Verify `min-height` set, check console for "not visible" |
| Canvas too narrow | Add `flex-1 min-w-0` to center panel |
| No A4 frame | Clear cache, verify custom `<style>` in blade |

---

## 📊 Build Output

```
public/build/assets/editor-D4HIwH1R.js    11.81 kB → 3.98 kB gzip ✅
public/build/assets/grapes-8xpIby7C.js   986 kB → 271.85 kB gzip
```

---

## 🎯 Next Actions

1. Manual test with real template
2. Test in different browser sizes
3. Test tab/section visibility (if applicable)
4. Collect user feedback

**Status:** ✅ Ready for testing
