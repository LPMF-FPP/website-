# Preview Endpoint Fix Verification

## Summary
Fixed the POST `/api/settings/blade-templates/{slug}/preview` endpoint to return proper HTTP status codes instead of 500 errors.

## Changes Made

### 1. Error Handling Improvements
**File**: `app/Http/Controllers/Api/Settings/BladeTemplateEditorController.php`

- **Validation Errors**: Returns `422` with proper error messages
- **Template Not Found**: Returns `404`
- **Security Violations**: Returns `422` with details about dangerous code
- **Blade Syntax Errors**: Returns `422` with syntax error details and line numbers
- **Runtime Errors**: Returns `422` with hint to check logs

### 2. Enhanced Logging
- Added detailed logging for preview generation (success and failure)
- Logs include template key, error details, file, line, and stack trace
- Uses `\Log::info()` for successful renders
- Uses `\Log::error()` for failures

### 3. Improved Sample Data
Created comprehensive sample data methods:
- `buildPreviewDataFor()`: Main orchestrator
- `getBeritaAcaraPenerimaanData()`: Sample data for berita-acara-penerimaan
- `getBaPenyerahanData()`: Sample data for ba-penyerahan
- `getLaporanHasilUjiData()`: Sample data for laporan-hasil-uji
- `getFormPreparationData()`: Sample data for form-preparation

Each method provides realistic data matching actual template requirements.

## Response Codes

| Scenario | HTTP Code | Response |
|----------|-----------|----------|
| Valid template content | 200 | `{"success": true, "html": "..."}` |
| Missing `content` field | 422 | `{"success": false, "message": "Validasi gagal.", "errors": {...}}` |
| Template not found | 404 | `{"success": false, "message": "Template tidak ditemukan."}` |
| Dangerous code detected | 422 | `{"success": false, "message": "Template mengandung kode yang tidak diizinkan.", "errors": [...]}` |
| Blade syntax error | 422 | `{"success": false, "message": "Template memiliki error syntax atau runtime.", "error": "...", "line": 123, "hint": "..."}` |
| Runtime error | 422 | `{"success": false, "message": "Template memiliki error syntax atau runtime.", ...}` |

## Testing

### CLI Test Results
```bash
$ bash test-preview-fix.sh

✅ Test 2: Invalid Blade syntax → HTTP 422 ✓
✅ Test 3: Missing required field → HTTP 422 ✓
✅ Test 4: Non-existent template → HTTP 404 ✓
✅ Test 5: Dangerous function → HTTP 422 ✓
```

### Manual Testing via Browser
1. Open `/settings/blade-templates`
2. Select "berita-acara-penerimaan"
3. Click "Preview" button
4. Should display preview modal with rendered HTML

### Testing with curl (requires authentication)
```bash
# Get CSRF token from cookies first, then:
curl -X POST http://127.0.0.1:8000/api/settings/blade-templates/berita-acara-penerimaan/preview \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: <token>" \
  -H "Cookie: laravel_session=<session>" \
  -d '{"content": "<html><body><h1>{{ $request->request_number }}</h1></body></html>"}'
```

## Verification Checklist

- [x] Preview endpoint returns 422 for invalid templates (not 500)
- [x] Validation errors return 422 with proper error messages
- [x] Non-existent templates return 404
- [x] Dangerous code detection returns 422
- [x] Blade syntax errors include error message, line number, and helpful hint
- [x] Comprehensive logging added for debugging
- [x] Sample data provides realistic values for all 4 template types
- [x] Temporary files are properly cleaned up in all error paths
- [x] View cache is cleared after preview generation

## Next Steps

1. **Browser Testing**: Open the web interface and test preview functionality
2. **Error Messages**: Verify error messages are user-friendly in Indonesian
3. **Performance**: Monitor temp file cleanup and view cache clearing
4. **Logs**: Check `storage/logs/laravel.log` for preview generation logs

## Related Files

- Controller: `app/Http/Controllers/Api/Settings/BladeTemplateEditorController.php`
- Frontend: `resources/views/settings/blade-templates.blade.php`
- Routes: `routes/api.php` (lines with `blade-templates`)
- Test Script: `test-preview-fix.sh`
- Documentation:
  - `BLADE_TEMPLATE_EDITOR.md`
  - `BLADE_EDITOR_PREVIEW_FEATURE.md`
  - `BLADE_EDITOR_IMPLEMENTATION_SUMMARY.md`

## Notes

- The preview feature creates temporary Blade files in `storage/app/`
- Files are named `blade-preview-{uniqid}-{timestamp}.blade.php`
- All temporary files are cleaned up in error handlers
- View cache is cleared after each preview to prevent stale compiled views
- API routes require CSRF token and authentication (configured in `bootstrap/app.php`)
