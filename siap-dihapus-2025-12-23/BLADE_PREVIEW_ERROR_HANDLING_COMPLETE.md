# Blade Template Preview Error Handling - Implementation Summary

## Overview
Upgraded the Blade template preview feature with comprehensive error handling, consistent 422 responses, and improved frontend error display.

## Changes Made

### 1. Backend Improvements

#### Controller: `app/Http/Controllers/Api/Settings/BladeTemplateEditorController.php`

**Consistent Response Format**:
- All error responses now include: `success`, `message`, `error`, `slug`, and optional `hint`
- Validation errors (422): Include `errors` array
- Runtime errors (422): Include `line` and `file` information
- Template not found (404): Clear error message

**Enhanced Error Handling**:
```php
// Validation error
return response()->json([
    'success' => false,
    'message' => 'Validasi gagal.',
    'error' => 'Konten template harus diisi.',
    'errors' => $validator->errors(),
    'slug' => $templateKey,
], 422);

// Render error with line number
return response()->json([
    'success' => false,
    'message' => 'Template memiliki error syntax atau runtime.',
    'error' => $renderError->getMessage(),
    'slug' => $templateKey,
    'line' => $renderError->getLine(),
    'file' => basename($renderError->getFile()),
    'hint' => 'Periksa sintaks Blade dan pastikan semua variabel yang digunakan tersedia.',
], 422);
```

**Fixed Preview Rendering**:
- Changed from `storage/app` to `resources/views` for temporary files
- Now uses `resource_path("views/temp-preview-{uniqid}.blade.php")`
- Proper cleanup in all error paths
- Works correctly in both web and test environments

**Comprehensive Logging**:
- Success: Logs template key and HTML length
- Errors: Logs full stack trace, file, line number
- Uses `\Log::info()` and `\Log::error()` appropriately

#### Middleware: `app/Http/Middleware/ValidateBladeTemplateAccess.php`

**Graceful Audit Logging**:
```php
try {
    if (config('logging.channels.audit')) {
        \Log::channel('audit')->info('Blade template edit attempt', $logData);
    } else {
        \Log::info('Blade template edit attempt', $logData);
    }
} catch (\Exception $e) {
    \Log::info('Blade template edit attempt', $logData);
}
```
- Handles missing audit channel in test environments
- Falls back to default log channel

### 2. Frontend Improvements

#### View: `resources/views/settings/blade-templates.blade.php`

**Enhanced Error Display**:
```html
<div x-show="previewError" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400"><!-- error icon --></svg>
        </div>
        <div class="ml-3 flex-1">
            <h3 class="text-sm font-medium text-red-800">Preview Gagal</h3>
            <div class="mt-2 text-sm text-red-700">
                <p x-text="previewError.message || previewError"></p>
                <template x-if="previewError.error">
                    <div class="mt-2 p-2 bg-red-100 rounded text-xs font-mono">
                        <p x-text="previewError.error"></p>
                        <template x-if="previewError.line">
                            <p class="mt-1">Baris: <span x-text="previewError.line"></span></p>
                        </template>
                    </div>
                </template>
                <template x-if="previewError.hint">
                    <p class="mt-2 text-xs italic" x-text="previewError.hint"></p>
                </template>
            </div>
        </div>
    </div>
</div>
```

**Improved generatePreview() Function**:
```javascript
async generatePreview() {
    this.previewLoading = true;
    this.previewError = null;

    try {
        const response = await fetch(`/api/settings/blade-templates/${this.selectedTemplate}/preview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ content: this.currentContent })
        });

        const data = await response.json();
        
        if (response.ok && data.success) {
            // Render HTML in iframe
            const iframe = this.$refs.previewFrame;
            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            iframeDoc.open();
            iframeDoc.write(data.html);
            iframeDoc.close();
            
            // Auto-resize
            setTimeout(() => {
                const contentHeight = iframeDoc.body.scrollHeight;
                iframe.style.height = Math.max(contentHeight, 600) + 'px';
            }, 100);
        } else if (response.status === 422) {
            // Validation or compilation error
            console.error('Preview validation error:', data);
            this.previewError = {
                message: data.message || 'Template tidak valid',
                error: data.error || '',
                line: data.line,
                file: data.file,
                hint: data.hint || '',
                slug: data.slug
            };
        } else {
            // Other errors
            console.error('Preview failed:', data);
            this.previewError = {
                message: data.message || 'Gagal membuat preview',
                error: data.error || '',
                hint: data.hint || ''
            };
        }
    } catch (error) {
        console.error('Preview request failed:', error);
        this.previewError = {
            message: 'Gagal menghubungi server',
            error: error.message,
            hint: 'Periksa koneksi jaringan Anda'
        };
    } finally {
        this.previewLoading = false;
    }
}
```

**Key Features**:
- Differentiates between 200, 422, and network errors
- Preserves error state until next preview attempt
- Console logging for debugging
- Loading state with spinner
- Proper error object structure

### 3. Testing

#### Test File: `tests/Feature/BladeTemplatePreviewTest.php`

**Comprehensive Test Coverage** (9 tests, 64 assertions):
1. ✅ Preview returns HTML for valid template
2. ✅ Preview returns 422 for missing content
3. ✅ Preview returns 422 for invalid Blade syntax
4. ✅ Preview returns 404 for nonexistent template
5. ✅ Preview returns 422 for dangerous functions
6. ✅ Preview clears view cache after render
7. ✅ Preview works for all 4 template types
8. ✅ Preview includes all required variables for berita-acara-penerimaan
9. ✅ Preview requires authentication

## Response Structure

### Success (200)
```json
{
  "success": true,
  "html": "<html>...</html>"
}
```

### Validation Error (422)
```json
{
  "success": false,
  "message": "Validasi gagal.",
  "error": "Konten template harus diisi.",
  "errors": {
    "content": ["The content field is required."]
  },
  "slug": "berita-acara-penerimaan"
}
```

### Compile/Runtime Error (422)
```json
{
  "success": false,
  "message": "Template memiliki error syntax atau runtime.",
  "error": "Undefined variable $nonexistent",
  "slug": "berita-acara-penerimaan",
  "line": 45,
  "file": "temp-preview-abc123.blade.php",
  "hint": "Periksa sintaks Blade dan pastikan semua variabel yang digunakan tersedia."
}
```

### Security Error (422)
```json
{
  "success": false,
  "message": "Template mengandung kode yang tidak diizinkan.",
  "error": "Fungsi PHP berbahaya terdeteksi: exec()",
  "errors": ["Fungsi PHP berbahaya terdeteksi: exec()"],
  "slug": "berita-acara-penerimaan"
}
```

### Template Not Found (404)
```json
{
  "success": false,
  "message": "Template tidak ditemukan."
}
```

## Sample Data

Complete sample data provided for all 4 templates:
- **berita-acara-penerimaan**: Request with investigator, samples, case number
- **ba-penyerahan**: Request with suspect, samples, BA number
- **laporan-hasil-uji**: Process with method, metadata, test results
- **form-preparation**: Process with analyst, sample information

## Files Changed

1. `app/Http/Controllers/Api/Settings/BladeTemplateEditorController.php` - Enhanced preview() method
2. `app/Http/Middleware/ValidateBladeTemplateAccess.php` - Graceful audit logging
3. `resources/views/settings/blade-templates.blade.php` - Improved error display and handling
4. `tests/Feature/BladeTemplatePreviewTest.php` - Comprehensive test suite

## Verification

Run tests:
```bash
php artisan test --filter=BladeTemplatePreviewTest
```

Expected result: ✅ 9 passed (64 assertions)

## Next Steps

1. **Manual Testing**: Open `/settings/blade-templates` and test preview with:
   - Valid template content
   - Invalid Blade syntax (e.g., `{{ $undefined->property }}`)
   - Dangerous functions (e.g., `<?php exec("ls"); ?>`)
   - Missing variables

2. **Monitor Logs**: Check `storage/logs/laravel.log` for:
   - Preview generation attempts
   - Error details with stack traces
   - Template edit audit logs

3. **User Feedback**: Verify error messages are clear and actionable in Indonesian

## Security Notes

- ✅ CSRF protection enabled
- ✅ Authentication required (middleware: `auth`, `verified`)
- ✅ Permission check: `manage-settings`
- ✅ Dangerous function detection (exec, eval, file_put_contents, etc.)
- ✅ Audit logging for all edit/preview attempts
- ✅ Temporary files cleaned up in all paths
- ✅ View cache cleared after each preview

## Performance Considerations

- Temporary files created in `resources/views/` (fast access)
- View cache cleared after each preview (prevents stale cache)
- Cleanup happens in all error paths (no orphaned files)
- Proper exception handling (no resource leaks)
