# GrapesJS Editor Layout Fix

## Summary
Fixed GrapesJS editor layout issues: narrow canvas, initialization problems, and missing panels.

**Changes:** 2 files modified  
**Build:** ✅ Success (editor-D4HIwH1R.js: 11.81 kB → 3.98 kB gzip)

---

## Problems Fixed

### 1. ❌ Editor "Locked"/Narrow Layout
- **Before:** Single container with `overflow:hidden`, canvas cramped
- **After:** Proper flex layout with `flex-1 min-w-0` for canvas area

### 2. ❌ Missing Panel Containers  
- **Before:** All managers trying to append to `#gjs-editor`
- **After:** Dedicated containers for blocks, layers, styles, traits

### 3. ❌ No Visibility-Aware Init
- **Before:** Editor initialized immediately (may be hidden in tabs)
- **After:** Checks visibility, delays init until visible, calls `refresh()` on tab switch

### 4. ❌ No A4 Canvas Feel
- **Before:** Full-width canvas, no print-like experience
- **After:** 794px max-width (A4 width), centered, with shadow

---

## File Changes

### 📄 `resources/views/templates/editor.blade.php` (MAJOR REFACTOR)

#### Changed: GrapesJS Container Layout

**Before:**
```blade
<div class="bg-white ... overflow-hidden">
    <div id="gjs-editor" style="height: calc(100vh - 300px); min-height: 600px;"></div>
</div>
```

**After:**
```blade
<div class="bg-white ...">
    <!-- Flex wrapper for editor + panels -->
    <div class="flex gap-0" style="min-height: 75vh;">
        <!-- Left Panel: Blocks + Layers -->
        <div class="w-64 border-r ... flex flex-col overflow-y-auto">
            <div id="blocks-container" class="flex-1 overflow-y-auto p-2"></div>
            <div id="layers-container" class="flex-1 overflow-y-auto p-2"></div>
        </div>

        <!-- Center: Canvas Area (flex-1 min-w-0) -->
        <div class="flex-1 min-w-0 flex flex-col bg-gray-100">
            <div id="toolbar-container" class="..."></div>
            
            <!-- Canvas with A4 feel -->
            <div class="flex-1 overflow-auto p-4">
                <div id="gjs-editor" class="mx-auto" 
                     style="max-width: 794px; min-height: 600px; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1);"></div>
            </div>
        </div>

        <!-- Right Panel: Style Manager + Traits -->
        <div class="w-72 border-l ... flex flex-col overflow-y-auto">
            <div id="styles-container" class="flex-1 overflow-y-auto p-2"></div>
            <div id="traits-container" class="flex-1 overflow-y-auto p-2"></div>
        </div>
    </div>
</div>
```

**Key Improvements:**
- ✅ `flex-1 min-w-0` prevents canvas from overflowing
- ✅ `min-height: 75vh` ensures tall editor (not cramped)
- ✅ Dedicated containers: `#blocks-container`, `#layers-container`, `#styles-container`, `#traits-container`
- ✅ A4 canvas: `max-width: 794px`, centered, with shadow

#### Added: Custom CSS Styles

```blade
@push('styles')
@vite(['resources/css/app.css'])
<link rel="stylesheet" href="https://unpkg.com/grapesjs/dist/css/grapes.min.css">
<style>
    /* GrapesJS Panel Customization */
    .gjs-block { min-height: auto !important; padding: 0 !important; }
    
    /* A4 Canvas Styling */
    #gjs-editor .gjs-frame {
        border: 1px solid #ddd !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
    }
    
    /* Style Manager tweaks */
    .gjs-sm-sector { font-size: 12px !important; }
    
    /* Dark mode adjustments */
    .dark #blocks-container .gjs-block { color: #e5e7eb !important; }
</style>
@endpush
```

---

### 📄 `resources/js/templates/editor.js` (MAJOR REFACTOR)

#### Added: Visibility-Aware Initialization

**Before:**
```javascript
const editor = grapesjs.init({
    container: '#gjs-editor',
    height: '100%',
    // ... config
});
```

**After:**
```javascript
let editor = null;
let editorInitialized = false;

function isEditorVisible() {
    const container = document.getElementById('gjs-editor');
    if (!container) return false;
    
    const rect = container.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
}

function initializeEditor() {
    if (editorInitialized) return;
    
    if (!isEditorVisible()) {
        console.log('Editor container not visible, delaying initialization...');
        setTimeout(initializeEditor, 100);
        return;
    }

    console.log('Initializing GrapesJS editor...');
    
    editor = grapesjs.init({
        container: '#gjs-editor',
        height: '600px',
        width: 'auto',
        // ... config with proper appendTo targets
    });

    editorInitialized = true;
    loadTemplateFromAPI();
}

// IntersectionObserver for tab/section visibility
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && editorInitialized && editor) {
            console.log('Editor became visible, refreshing...');
            editor.refresh();
        }
    });
}, { threshold: 0.1 });

observer.observe(editorContainer);
initializeEditor();
```

**Key Improvements:**
- ✅ `isEditorVisible()` checks if container has width/height
- ✅ Delays init with `setTimeout()` if not visible
- ✅ `IntersectionObserver` detects tab switches
- ✅ Calls `editor.refresh()` when becoming visible

#### Changed: Panel appendTo Targets

**Before:**
```javascript
blockManager: {
    appendTo: '#gjs-editor',  // ❌ Wrong target
    blocks: [...]
},
layerManager: {
    appendTo: '#gjs-editor',  // ❌ Wrong target
},
styleManager: {
    appendTo: '#gjs-editor',  // ❌ Wrong target
},
```

**After:**
```javascript
blockManager: {
    appendTo: '#blocks-container',  // ✅ Correct dedicated container
    blocks: [
        { id: 'section', label: '...', category: 'Layout', ... },
        { id: 'text', label: '...', category: 'Basic', ... },
        { id: 'heading', ... },
        { id: 'paragraph', ... },
        { id: 'image', category: 'Media', ... },
        { id: 'table', category: 'Layout', ... },
        { id: 'divider', ... },
        { id: 'list', ... },
    ]
},
layerManager: {
    appendTo: '#layers-container',  // ✅ Correct
},
traitManager: {
    appendTo: '#traits-container',  // ✅ Correct
},
styleManager: {
    appendTo: '#styles-container',  // ✅ Correct
    sectors: [
        { name: 'Typography', open: true, buildProps: [...] },
        { name: 'Dimension', buildProps: [...] },
        { name: 'Background', buildProps: [...] },
        { name: 'Border', buildProps: [...] },
        { name: 'Extra', buildProps: [...] },
    ]
},
```

**Key Improvements:**
- ✅ Each manager has dedicated container
- ✅ 8 blocks total (section, text, heading, paragraph, image, table, divider, list)
- ✅ 5 style sectors with comprehensive properties
- ✅ Better categorization (Layout, Basic, Media)

#### Changed: Canvas Configuration

**Before:**
```javascript
grapesjs.init({
    container: '#gjs-editor',
    height: '100%',  // ❌ Fills parent, may be too tall or too short
    width: 'auto',
});
```

**After:**
```javascript
grapesjs.init({
    container: '#gjs-editor',
    height: '600px',  // ✅ Fixed height for consistency
    width: 'auto',
    
    canvas: {
        styles: [],  // Add custom styles if needed
        scripts: [],
    },
    
    panels: {
        defaults: [
            {
                id: 'panel-devices',
                el: '#toolbar-container',  // ✅ Proper toolbar placement
                buttons: [
                    { id: 'device-desktop', label: '🖥️ Desktop', ... },
                    { id: 'device-tablet', label: '📱 Tablet', ... },
                    { id: 'device-mobile', label: '📱 Mobile', ... },
                ],
            },
        ],
    },
});
```

---

## Checklist ✅

### ✅ Layout Requirements
- [x] **Wrapper flex:** Main container uses `display: flex`
- [x] **Canvas area `flex-1 min-w-0`:** Center panel has both classes
- [x] **Tinggi jelas:** `min-height: 75vh` on wrapper, `min-height: 600px` on canvas
- [x] **No `overflow:hidden` on parent:** Removed from main container

### ✅ Visibility-Aware Initialization
- [x] **Init only when visible:** `isEditorVisible()` checks before init
- [x] **Refresh after tab switch:** `IntersectionObserver` + `editor.refresh()`

### ✅ GrapesJS Plugins & Panels
- [x] **Block Manager:** 8 blocks in dedicated `#blocks-container`
- [x] **Style Manager:** 5 sectors in `#styles-container`
- [x] **Trait Manager:** appendTo `#traits-container`
- [x] **Layer Manager:** appendTo `#layers-container`

### ✅ A4 Canvas Feel
- [x] **Frame width ~794px:** `max-width: 794px` on canvas container
- [x] **Centered:** `mx-auto` class
- [x] **Padding:** `p-4` on canvas wrapper
- [x] **Visual frame:** `box-shadow: 0 0 10px rgba(0,0,0,0.1)`

---

## Build Output

```bash
$ npm run build

vite v7.1.7 building for production...
✓ 65 modules transformed.

public/build/assets/template-editor-CaepLTe7.css   56.74 kB │ gzip:  11.93 kB
public/build/assets/editor-D4HIwH1R.js             11.81 kB │ gzip:   3.98 kB
public/build/assets/grapes-8xpIby7C.js            986.01 kB │ gzip: 271.85 kB

✓ built in 4.91s
```

**Status:** ✅ All assets compiled successfully

---

## Testing Steps

### 1. Visual Verification
```bash
php artisan serve
# Navigate to: http://localhost:8000/templates/{id}/edit
```

**Expected:**
- ✅ Left panel (256px): Blocks + Layers with scrollbars
- ✅ Center area (flex-1): Toolbar + A4 canvas (794px, centered, shadowed)
- ✅ Right panel (288px): Styles + Settings with scrollbars
- ✅ Canvas not cramped, minimum 600px tall

### 2. Responsiveness Test
```bash
# Resize browser window to narrow width (< 1280px)
```

**Expected:**
- ✅ Canvas area shrinks gracefully (`min-w-0` prevents overflow)
- ✅ Side panels maintain fixed widths (w-64, w-72)
- ✅ No horizontal scroll unless unavoidable

### 3. Visibility Test (If in Tab/Section)
```html
<!-- Wrap editor in hidden section -->
<div x-data="{ tab: 'info' }">
    <button @click="tab = 'info'">Info</button>
    <button @click="tab = 'editor'">Editor</button>
    
    <div x-show="tab === 'editor'">
        <!-- GrapesJS editor here -->
    </div>
</div>
```

**Expected:**
- ✅ Console: "Editor container not visible, delaying initialization..."
- ✅ After clicking "Editor" tab → "Initializing GrapesJS editor..."
- ✅ After switching back to editor tab → "Editor became visible, refreshing..."

### 4. Block/Style Manager Test
```bash
# Click block in left panel → drag to canvas
# Select element → check right panel for styles
```

**Expected:**
- ✅ 8 blocks available (Section, Text, Heading, Paragraph, Image, Table, Divider, List)
- ✅ Blocks render in `#blocks-container` (not overlaying canvas)
- ✅ Style Manager shows 5 sectors (Typography, Dimension, Background, Border, Extra)
- ✅ Layer Manager shows document tree
- ✅ Trait Manager shows element settings

### 5. Device Switcher Test
```bash
# Click "📱 Tablet" button in toolbar
# Click "📱 Mobile" button
# Click "🖥️ Desktop" button
```

**Expected:**
- ✅ Canvas width changes (Desktop → full, Tablet → 768px, Mobile → 375px)
- ✅ A4 frame remains centered
- ✅ Active button highlighted

---

## Common Issues & Solutions

### Issue: Panels still overlaying canvas
**Cause:** Container IDs don't match `appendTo` targets  
**Fix:** Verify blade has `#blocks-container`, `#layers-container`, `#styles-container`, `#traits-container`

### Issue: Editor not initializing
**Cause:** Container hidden or zero dimensions  
**Fix:** Check console for "not visible" message, verify `min-height` set on parent

### Issue: Canvas too narrow
**Cause:** Missing `flex-1 min-w-0` on center panel  
**Fix:** Verify blade has `<div class="flex-1 min-w-0 flex flex-col">`

### Issue: No A4 frame visible
**Cause:** Custom CSS not loading  
**Fix:** Clear browser cache, verify `@push('styles')` includes custom `<style>` block

---

## API Integration Notes

**No changes to API wiring.** All existing functionality preserved:
- ✅ `loadTemplateFromAPI()` still fetches on load
- ✅ `saveDraft()` still saves html/css/components/styles
- ✅ Preview, Issue, Activate still work
- ✅ Error handling unchanged

**Only visual layout improved.**

---

## Next Steps

1. **Manual Test:** Follow testing steps above
2. **User Feedback:** Test with actual templates
3. **Optional Enhancements:**
   - Install `grapesjs-preset-webpage` for richer blocks (if needed)
   - Add custom blocks for {{tokens}}
   - Add print-specific CSS for A4 rendering

---

## Files Modified

```
resources/views/templates/editor.blade.php  (Layout refactor + custom CSS)
resources/js/templates/editor.js             (Visibility-aware init + panel config)
```

**Build artifact:**
```
public/build/assets/editor-D4HIwH1R.js       (11.81 kB → 3.98 kB gzip)
```

---

## Summary Checklist

✅ **Flex Layout:** `flex` wrapper + `flex-1 min-w-0` canvas  
✅ **Height:** `min-height: 75vh` wrapper + `600px` canvas  
✅ **No Overflow:** Removed `overflow:hidden` from parent  
✅ **Visibility Init:** Checks visibility before init  
✅ **Refresh on Tab:** `IntersectionObserver` + `editor.refresh()`  
✅ **Proper Panels:** 4 dedicated containers for managers  
✅ **A4 Canvas:** 794px max-width, centered, shadowed  
✅ **Build Success:** Assets compiled without errors  

**Status:** ✅ All requirements met
