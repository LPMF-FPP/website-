# Manual Testing Guide - Settings Numbering Per-Scope Save

## Prerequisites
1. Laravel server running: `php artisan serve`
2. Frontend assets built: `npm run build` or `npm run dev`
3. Admin user logged in

## Test Scenarios

### 1. Basic Per-Scope Save
**Steps:**
1. Navigate to `/settings`
2. Go to "Penomoran Otomatis" section
3. Find the "Kode Sampel" (sample_code) card
4. Fill in:
   - Pattern: `SMP-{YYYY}-{MM}-{N:4}`
   - Reset: `Yearly`
   - Start From: `1`
5. Click the "Simpan" button on that card only

**Expected:**
- ✅ Loading spinner shows on the button
- ✅ Success message appears below the buttons (green background)
- ✅ "Penomoran Saat Ini" section refreshes
- ✅ Other scope cards are not affected
- ✅ No page reload

### 2. Validation Error Display
**Steps:**
1. In any scope card, leave Pattern empty
2. Click "Simpan"

**Expected:**
- ✅ 422 error returned
- ✅ Pattern field shows red border
- ✅ Error message displays below Pattern field: "Pattern wajib diisi."
- ✅ Error message in status area (red background)
- ✅ Other fields remain valid (no red borders)

### 3. Invalid Reset Period
**Steps:**
1. Fill Pattern: `TEST-{N:4}`
2. Select invalid reset (use DevTools to modify): set to "invalid"
3. Set Start From: `1`
4. Click "Simpan"

**Expected:**
- ✅ 422 error returned
- ✅ Reset field shows red border
- ✅ Error message: "Reset period harus salah satu dari: daily, weekly, monthly, yearly, never."

### 4. Invalid Start From
**Steps:**
1. Fill Pattern: `TEST-{N:4}`
2. Select Reset: `Yearly`
3. Set Start From: `0` or `-1`
4. Click "Simpan"

**Expected:**
- ✅ 422 error returned
- ✅ Start From field shows red border
- ✅ Error message: "Start from minimal 1."

### 5. Multiple Scopes Independent Save
**Steps:**
1. Save "Kode Sampel" with Pattern: `SMP-{YYYY}-{N:4}`
2. Save "BA Penerimaan" with Pattern: `BA-{MM}-{YYYY}-{N:4}`
3. Save "Nomor Resi" with Pattern: `RESI-{N:5}`

**Expected:**
- ✅ Each save succeeds independently
- ✅ Each shows its own success message
- ✅ "Penomoran Saat Ini" updates after each save
- ✅ Different patterns are preserved independently

### 6. Test Preview Still Works
**Steps:**
1. Fill any scope with valid pattern
2. Click "Test Preview" button

**Expected:**
- ✅ Preview loads without errors
- ✅ Shows generated number in preview section
- ✅ Save button still available

### 7. Network Request Verification
**Steps:**
1. Open DevTools → Network tab
2. Fill "Kode Sampel" and click "Simpan"

**Expected:**
- ✅ Request Method: `PUT`
- ✅ Request URL: `http://127.0.0.1:8000/api/settings/numbering/sample_code`
- ✅ Request Payload:
  ```json
  {
    "pattern": "SMP-{YYYY}-{MM}-{N:4}",
    "reset": "yearly",
    "start_from": 1
  }
  ```
- ✅ Response Status: `200`
- ✅ Response Body:
  ```json
  {
    "scope": "sample_code",
    "config": {...},
    "message": "Pengaturan penomoran berhasil disimpan."
  }
  ```

### 8. Console Errors Check
**Steps:**
1. Open DevTools → Console
2. Navigate to Settings page
3. Perform various saves

**Expected:**
- ✅ No errors about undefined properties
- ✅ No errors about missing state keys
- ✅ No errors about `previewState`
- ✅ No errors about `numbering` or `labels`

### 9. UI State Consistency
**Steps:**
1. Save one scope
2. Navigate to another section (Templates)
3. Return to Penomoran Otomatis

**Expected:**
- ✅ Success messages cleared
- ✅ Form values preserved
- ✅ No lingering error states
- ✅ Loading states reset

### 10. Authorization Test
**Steps:**
1. Log out
2. Try to access: `PUT http://127.0.0.1:8000/api/settings/numbering/sample_code`

**Expected:**
- ✅ 401 Unauthorized response
- ✅ No settings modified

## Automated Test Verification

Run the test suite:
```bash
php artisan test --filter=NumberingScopeSaveTest
```

Expected output:
```
PASS  Tests\Feature\Api\Settings\NumberingScopeSaveTest
✓ can save single numbering scope
✓ validates required fields for scope save
✓ validates reset period enum
✓ validates start from minimum
✓ rejects invalid scope
✓ requires authentication
✓ requires admin role
✓ can save different scopes independently

Tests:  8 passed (24 assertions)
```

## Regression Testing

Ensure existing functionality still works:
1. ✅ Templates section saves correctly
2. ✅ Branding section saves correctly
3. ✅ Localization section saves correctly
4. ✅ Notifications section saves correctly
5. ✅ PDF preview still works
6. ✅ Template upload still works

## Rollback Plan

If issues are found, revert these files:
```bash
git checkout HEAD -- routes/api.php
git checkout HEAD -- app/Http/Controllers/Api/Settings/NumberingController.php
git checkout HEAD -- resources/js/pages/settings/index.js
git checkout HEAD -- resources/views/settings/partials/numbering.blade.php
rm tests/Feature/Api/Settings/NumberingScopeSaveTest.php
npm run build
php artisan view:clear
```

## Success Criteria

All of the following must be true:
- [x] Each scope has its own "Simpan" button
- [x] Clicking "Simpan" sends correct payload (not empty)
- [x] 422 errors display inline per field
- [x] Success messages show per scope
- [x] Multiple scopes can be saved independently
- [x] No console errors
- [x] All automated tests pass
- [x] Frontend builds without errors
- [x] Current numbering refreshes after save
