# GrapesJS Container Fix - Patch Summary

## Problem
❌ **Error**: "Container editor tidak ditemukan."  
❌ GrapesJS editor not initializing in template modal

## Root Cause
Alpine.js `x-show` makes container visible **asynchronously**, but GrapesJS was trying to initialize on hidden container (0px dimensions).

## Files Changed

### 1. `resources/views/settings/partials/templates.blade.php`
**Change**: Add explicit dimensions to container

```diff
- <div x-ignore class="h-full">
-     <div x-ref="templateEditorModalCanvas" id="gjs-modal-editor" class="h-full w-full"></div>
+ <div x-ignore class="h-full" style="min-height: 75vh;">
+     <div x-ref="templateEditorModalCanvas" id="gjs-modal-editor" class="h-full w-full" style="min-height: 75vh; width: 100%;"></div>
```

### 2. `resources/js/pages/settings/alpine-component.js`
**Changes**: 
- Wait for container visibility with retry loop (up to 1 second)
- Add fallback `getElementById` selector
- Add console error logging
- Refresh editor after initialization

#### In `openTemplateEditorModal()`:
```javascript
// Wait for container to be truly visible (not display:none)
let attempts = 0;
while (container.offsetParent === null && attempts < 20) {
    await new Promise(resolve => setTimeout(resolve, 50));
    attempts++;
}

if (container.offsetParent === null) {
    this.templateEditorModal.error = 'Container editor belum visible setelah ' + attempts + ' attempts.';
    console.error('GrapesJS container still not visible after waiting');
    return;
}
```

#### After editor load:
```javascript
// Refresh editor to compute correct dimensions after container is visible
await this.$nextTick();
await new Promise(resolve => requestAnimationFrame(resolve));
refreshTemplateEditor('modal');
```

#### In `ensureGrapesEditorInModal()`:
```javascript
// Wait for visibility with retry loop
let attempts = 0;
while (container.offsetParent === null && attempts < 10) {
    await new Promise(resolve => setTimeout(resolve, 50));
    attempts++;
}
```

## Build
```bash
npm run build
```

## Verification
```bash
./verify-grapesjs-container-fix.sh
```

✅ All checks passed!

## Manual Testing
1. `php artisan serve`
2. Navigate to `http://localhost:8000/settings`
3. Click "Edit" on any template
4. **Expected**: Modal opens, GrapesJS editor renders with canvas
5. **Can do**: Drag blocks, edit text, close/reopen modal

## Result
✅ Container has real dimensions (75vh)  
✅ Init waits for visibility (retry loop)  
✅ Editor refreshes after load  
✅ No "Container tidak ditemukan" error  
✅ Drag/drop and editing works  
✅ Clean modal reopening (no duplicates)
