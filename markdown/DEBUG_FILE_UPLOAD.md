# Debug Routes

## Overview

These debug routes help troubleshoot various features including file uploads and document generation.

## Available Routes

### 0. **Berita Acara Generator (QA Only)** 🚨 TEMPORARY
- **URL**: `GET /debug/ba/{id}`
- **Purpose**: Test Berita Acara PDF generation for a specific TestRequest
- **Middleware**: `auth` (requires login)
- **Usage**: Pass TestRequest ID to generate and view PDF inline

**Example**:
```
http://localhost:8000/debug/ba/1
```

**⚠️ TODO: REMOVE THIS ROUTE AFTER QA**

### 0b. **DocumentService Process Test (QA Only)** 🚨 TEMPORARY
- **URL**: `GET /debug/process/{id}`
- **Purpose**: Test DocumentService.storeForSampleProcess() integration
- **Middleware**: `auth` (requires login)
- **Usage**: Pass SampleTestProcess ID to test document storage

**Example**:
```
http://localhost:8000/debug/process/1
```

**Returns JSON with**:
- Document storage path
- File existence verification
- Investigator folder structure
- Sample and request details

**⚠️ TODO: REMOVE THIS ROUTE AFTER QA**

---

### 1. **Debug File Upload Test Page**
- **URL**: `GET /debug/file-upload`
- **Purpose**: Interactive HTML page to test file uploads
- **Usage**: Open in browser and upload files to see the debug output

**Example**:
```
http://localhost:8000/debug/file-upload
```

### 2. **File Keys Inspector**
- **URL**: `GET|POST /debug/file-keys`
- **Purpose**: Returns JSON showing all file input field names
- **Usage**: Can be called directly or via form submission

**Example Response**:
```json
{
  "message": "File input field names detected",
  "file_keys": [
    "samples.0.photos.0",
    "samples.0.photos.1",
    "samples.1.photos.0"
  ],
  "file_count": 3,
  "all_input_keys": [
    "samples.0.name",
    "samples.0.photos.0",
    "samples.0.photos.1"
  ],
  "method": "POST",
  "content_type": "multipart/form-data",
  "has_files": true,
  "raw_files": {...}
}
```

## How to Use

### Method 1: Interactive Test Page

1. Navigate to: `http://localhost:8000/debug/file-upload`
2. Select files in any of the file inputs
3. Click "Submit & Show File Keys"
4. Check the JSON output to see exact field names

### Method 2: Browser DevTools

1. Open the request create form: `/requests/create`
2. Open browser DevTools (F12) > Network tab
3. Select sample photos and submit the form
4. Look for the POST request to `/requests`
5. Check the "Payload" tab to see file field names

### Method 3: Direct API Call

```bash
# Using cURL to test
curl -X POST http://localhost:8000/debug/file-keys \
  -F "samples[0][photos][]=@/path/to/photo1.jpg" \
  -F "samples[0][photos][]=@/path/to/photo2.jpg" \
  -F "samples[1][photos][]=@/path/to/photo3.jpg"
```

### Method 4: Check Laravel Logs

The RequestController already logs file keys on submission:

```php
\Log::info('FILES KEYS', ['keys' => array_keys(Arr::dot($request->allFiles()))]);
```

Check `storage/logs/laravel.log` after form submission.

## Common File Input Patterns

The system supports these field name patterns:

| Pattern | Type | Example |
|---------|------|---------|
| `samples[0][photos][]` | Array | Multiple photos for sample 0 |
| `samples[0][photo]` | Single | Single photo for sample 0 |
| `samples[0][images][]` | Array | Alternative name for photos |
| `samples[0][image]` | Single | Alternative name for single photo |

## Troubleshooting

### Files Not Being Saved?

1. Visit `/debug/file-upload` and test if files are detected
2. Check if `file_keys` array shows your field names
3. Verify field names match one of the supported patterns:
   - `samples.*.photos.*`
   - `samples.*.photo`
   - `samples.*.images.*`
   - `samples.*.image`

### Validation Errors?

Check validation rules in `RequestController@store`:
```php
'samples.*.photos' => 'nullable|array',
'samples.*.photos.*' => 'image|mimes:jpg,jpeg,png|max:5120',
'samples.*.photo' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
'samples.*.images' => 'nullable|array',
'samples.*.images.*' => 'image|mimes:jpg,jpeg,png|max:5120',
```

### Form Not Submitting Files?

Ensure the form has:
```html
<form enctype="multipart/form-data" method="POST">
```

## Security Note

These debug routes should be disabled or restricted in production.

### Temporary Routes to Remove:
1. **`/debug/ba/{id}`** - Remove after QA testing is complete
2. **`/debug/process/{id}`** - Remove after DocumentService testing is complete

### For Production:
Consider adding stricter middleware:

```php
Route::prefix('debug')->middleware(['auth', 'can:view-debug'])->group(function () {
    // ... debug routes
});
```

Or remove entirely from production by wrapping in:

```php
if (app()->environment('local', 'testing')) {
    Route::prefix('debug')->group(function () {
        // ... debug routes
    });
}
```

### To Remove Debug Routes After QA:
Open `routes/web.php` and delete these blocks:

**1. BA Generation Route:**
```php
// TODO: REMOVE AFTER QA - Debug route to test BA generation
Route::get('/ba/{id}', function ($id) { ... })->middleware('auth')->name('debug.ba');
```

**2. DocumentService Process Route:**
```php
// TODO: REMOVE AFTER QA - Debug route to test DocumentService for SampleTestProcess
Route::get('/process/{id}', function ($id) { ... })->middleware('auth')->name('debug.process');
```

## Related Files

- Route Definition: `routes/web.php`
- Controller Logic: `app/Http/Controllers/RequestController.php`
- Test HTML: `public/debug-file-upload.html`
- Feature Tests: `tests/Feature/SamplePhotoUploadTest.php`
