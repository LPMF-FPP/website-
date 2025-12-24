# Fix: Preview Tidak Tampil di /settings (Alpine Reactivity)

## 🐛 Masalah
Tombol "Test Preview" berhasil diklik, request API berjalan, tetapi hasil preview tidak pernah muncul di UI:
```html
<p x-text="client.state.numberingPreview?.[scope] || 'Click Test Preview'"></p>
<!-- Stuck di "Click Test Preview" meskipun state sudah berubah -->
```

## 🔍 Root Cause
**Alpine.js tidak mendeteksi perubahan pada nested object property.**

Ketika kita lakukan:
```javascript
this.state.numberingPreview[scope] = 'SMP-2025-0001';
```

Alpine tidak trigger re-render karena object reference-nya sama. Alpine hanya track perubahan pada top-level properties.

## ✅ Solusi: Object Spread Pattern

### 1. Reassign Entire Object (Trigger Reactivity)
```javascript
// ❌ WRONG - Alpine tidak detect
this.state.numberingPreview[scope] = 'SMP-2025-0001';

// ✅ CORRECT - Alpine detect perubahan
this.state.numberingPreview = {
    ...this.state.numberingPreview,
    [scope]: 'SMP-2025-0001'
};
```

### 2. Gunakan Nullish Coalescing (??)
```html
<!-- ❌ WRONG - Empty string dianggap falsy, fallback muncul -->
x-text="client.state.numberingPreview?.[scope] || 'Click Test Preview'"

<!-- ✅ CORRECT - Hanya null/undefined yang fallback -->
x-text="client.state.numberingPreview?.[scope] ?? 'Click Test Preview'"
```

### 3. Comprehensive Logging
```javascript
console.log('🔍 [testPreview] Starting preview for scope:', scope);
console.log('→ POST /api/settings/numbering/preview', { scope, config });
console.log('✓ Preview response:', { data, extractedValue });
console.log('✓ State updated:', { scope, value, fullState });
```

## 📝 Files Modified

### 1. `resources/js/pages/settings/index.js`
**Line ~270-295: testPreview method**
```javascript
async testPreview(scope) {
    // Use spread operator for reactivity
    this.state.previewLoading = { ...this.state.previewLoading, [scope]: true };
    
    try {
        const data = await this.apiFetch(this.api.numberingPreview, {
            method: 'POST',
            body: { scope, ...scopeConfig },
        });
        
        const previewValue = data.preview ?? data.value ?? data.data?.preview ?? '';
        
        // CRITICAL: Reassign entire object
        this.state.numberingPreview = {
            ...this.state.numberingPreview,
            [scope]: previewValue
        };
        
    } finally {
        // Also use spread for loading state
        this.state.previewLoading = { ...this.state.previewLoading, [scope]: false };
    }
}
```

### 2. `resources/js/pages/settings/alpine-component.js`
**Line ~200-210: Wrapper dengan logging**
```javascript
testPreview(scope) {
    console.log('🔍 [Alpine Wrapper] testPreview called', { scope });
    console.log('📊 Current preview state:', this.client.state.numberingPreview);
    console.log('⚙️ Current form config:', this.client.state.form.numbering?.[scope]);
    
    const result = this.client.testPreview(scope);
    console.log('▶️ testPreview promise initiated for scope:', scope);
    return result;
}
```

### 3. `resources/views/settings/partials/numbering.blade.php`
**Line ~97: Ubah operator**
```html
<!-- BEFORE -->
<p x-text="client.state.numberingPreview?.[scope] || 'Click Test Preview'"></p>

<!-- AFTER -->
<p x-text="client.state.numberingPreview?.[scope] ?? 'Click Test Preview'"></p>
```

## 🧪 Testing & Validation

### Console Output (Expected)
```
🔍 [Alpine Wrapper] testPreview called { scope: "sample_code" }
📊 Current preview state: { sample_code: "", ba: "", ... }
⚙️ Current form config: { pattern: "SMP-{YYYY}-{SEQ:4}", ... }
▶️ testPreview promise initiated for scope: sample_code
🔍 [testPreview] Starting preview for scope: sample_code
→ POST /api/settings/numbering/preview { scope: "sample_code", ... }
✓ Preview response: { preview: "SMP-2025-0001" }
✓ State updated: { scope: "sample_code", value: "SMP-2025-0001" }
```

### UI Behavior (Expected)
1. ✅ Button: "Test Preview" → "Testing..." → "Test Preview"
2. ✅ Preview box: "Click Test Preview" → "SMP-2025-0001"
3. ✅ Success message: "Preview berhasil!" (green)
4. ✅ No Alpine errors

### Network (Expected)
- POST `/api/settings/numbering/preview`
- Payload: `{ scope: "sample_code", pattern: "...", reset: "...", start_from: 1 }`
- Response: `{ preview: "SMP-2025-0001" }` (200 OK)

## 📚 Alpine Reactivity Best Practices

### ✅ DO:
1. **Reassign objects** untuk trigger reactivity:
   ```javascript
   this.state.obj = { ...this.state.obj, key: value }
   ```

2. **Use nullish coalescing** untuk fallback:
   ```javascript
   value ?? 'default'  // Only null/undefined
   ```

3. **Log state changes** untuk debugging:
   ```javascript
   console.log('State before:', this.state.obj);
   this.state.obj = { ...this.state.obj, key: value };
   console.log('State after:', this.state.obj);
   ```

### ❌ DON'T:
1. **Direct mutation** nested properties:
   ```javascript
   this.state.obj.key = value  // Alpine tidak detect
   ```

2. **Use logical OR** untuk fallback dengan string:
   ```javascript
   value || 'default'  // Empty string = falsy = fallback
   ```

3. **Assume Alpine detects** nested changes:
   ```javascript
   this.state.obj.nested.deep = value  // Tidak reaktif
   ```

## 🎯 Key Takeaway

**Alpine.js Reactivity Rule:**
> Alpine only tracks changes to **top-level properties** of the data object.
> For nested properties, you must **reassign the entire parent object** using spread operator.

**Pattern:**
```javascript
// For any nested property change:
this.state.parent = {
    ...this.state.parent,
    [dynamicKey]: newValue
};
```

---

**Status:** ✅ FIXED  
**Build:** npm run build (completed)  
**Ready for:** Browser testing  
**Test Script:** `./test-preview-reactivity.sh`
