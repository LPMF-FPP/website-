# Summary: RequestController@store Refactoring

## ✅ Changes Completed

### 1. Stopped Writing to 'documents' and 'samples' Disks
**Before:**
```php
$letterPath = $request->file('request_letter')->store('official_docs', 'documents');
$evidencePath = $request->file('evidence_photo')->store('evidence', 'samples');
```

**After:**
```php
$letterDoc = $documentService->storeUpload(
    $request->file('request_letter'),
    $investigator,
    $testRequest,
    'request_letter'
);
```

### 2. Now Uses DocumentService->storeUpload()

Files are now stored via `DocumentService` with proper path structure:
- **request_letter** → type: `'request_letter'`
- **evidence_photo** → type: `'evidence_photo'`

**Storage Path:**
```
storage/app/public/investigators/{folder_key}/{request_number}/uploads/{type}/filename
```

Example:
```
investigators/87010123-andri-wibowo/REQ-2025-0001/uploads/request_letter/20251210123456-surat-permintaan.pdf
investigators/87010123-andri-wibowo/REQ-2025-0001/uploads/evidence_photo/20251210123457-foto-barang-bukti.jpg
```

### 3. Updated TestRequest Columns

**Before:**
```php
'official_letter_path' => $letterPath,  // old disk path
'evidence_photo_path' => $evidencePath, // old disk path
```

**After:**
```php
'official_letter_path' => null,  // Set initially
'evidence_photo_path' => null,   // Set initially

// Then updated with Document path after upload:
$testRequest->official_letter_path = $letterDoc->path;
$testRequest->evidence_photo_path = $evidenceDoc->path;
$testRequest->save();
```

### 4. Ensured folder_key is Set

Added automatic folder_key generation if empty:
```php
// Ensure folder_key is set (NRP + slug nama)
if (empty($investigator->folder_key)) {
    $investigator->folder_key = trim(
        ($investigator->nrp ? $investigator->nrp.'-' : '') . 
        Str::slug($investigator->name ?? 'noname')
    );
    $investigator->save();
}
```

### 5. Updated Error Cleanup

**Before:**
```php
if ($letterPath) {
    Storage::disk('documents')->delete($letterPath);
}
if ($evidencePath) {
    Storage::disk('samples')->delete($evidencePath);
}
```

**After:**
```php
if ($letterDoc && $letterDoc->path) {
    Storage::disk('public')->delete($letterDoc->path);
}
if ($evidenceDoc && $evidenceDoc->path) {
    Storage::disk('public')->delete($evidenceDoc->path);
}
```

## Benefits

1. ✅ **Centralized Storage Management** - All document uploads now go through DocumentService
2. ✅ **Consistent Path Structure** - All documents follow investigators/{folder_key}/{request_number}/... pattern
3. ✅ **Database Tracking** - Documents are automatically recorded in `documents` table
4. ✅ **Per-Investigator Organization** - Files are organized by investigator's folder_key
5. ✅ **Single Disk** - Everything on 'public' disk, no more juggling 'documents' and 'samples' disks

## Files Modified

- `app/Http/Controllers/RequestController.php` (store method, lines ~188-345)

## Testing Checklist

- [ ] Create new test request with request_letter file
- [ ] Verify file is stored at `investigators/{folder_key}/{request_number}/uploads/request_letter/`
- [ ] Verify Document record is created in database
- [ ] Verify TestRequest.official_letter_path contains correct path
- [ ] Test with evidence_photo upload
- [ ] Test error rollback (simulate error after upload)
- [ ] Verify files are deleted from 'public' disk on error
- [ ] Check investigator.folder_key is auto-generated

## Migration Note

**Old files** stored in:
- `storage/app/documents/official_docs/` 
- `storage/app/samples/evidence/`

**New files** stored in:
- `storage/app/public/investigators/{folder_key}/{request_number}/uploads/request_letter/`
- `storage/app/public/investigators/{folder_key}/{request_number}/uploads/evidence_photo/`

Existing files in old locations will still work, but new uploads will use the new structure.
