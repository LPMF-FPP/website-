# Settings Numbering Per-Scope Save Implementation

## Summary

Implemented per-scope save functionality for the Settings page numbering section. Users can now save individual numbering configurations (sample_code, ba, lhu, ba_penyerahan, tracking) without affecting other scopes.

## Changes Made

### Backend

#### 1. Routes (`routes/api.php`)
Added new route for per-scope numbering updates:
```php
Route::put('/numbering/{scope}', [NumberingController::class, 'updateScope']);
```

This route is placed before the general `/numbering` route to ensure proper routing precedence.

#### 2. Controller (`app/Http/Controllers/Api/Settings/NumberingController.php`)
Added `updateScope()` method with:
- Scope validation (sample_code, ba, lhu, ba_penyerahan, tracking)
- Comprehensive field validation:
  - `pattern`: required, string, max 255 characters
  - `reset`: required, enum (daily, weekly, monthly, yearly, never)
  - `start_from`: required, integer, minimum 1
  - `per_test_type`: optional boolean
- Custom validation error messages in Indonesian
- Partial update logic that preserves other scopes
- Proper authorization check (manage-settings gate)

#### 3. Tests (`tests/Feature/Api/Settings/NumberingScopeSaveTest.php`)
Created comprehensive test suite:
- ✅ Can save single scope
- ✅ Validates required fields
- ✅ Validates reset period enum
- ✅ Validates start_from minimum
- ✅ Rejects invalid scopes
- ✅ Requires authentication
- ✅ Requires admin role
- ✅ Can save different scopes independently

All 8 tests passing with 24 assertions.

### Frontend

#### 1. JavaScript Client (`resources/js/pages/settings/index.js`)
Added to state initialization:
- `scopeErrors`: Per-scope validation error tracking
- `scopeStatus`: Per-scope status messages (success/error)
- `scopeLoading`: Per-scope loading states

Added `saveNumberingScope(scope)` method:
- Local validation before API call
- Sends PUT request to `/api/settings/numbering/{scope}`
- Handles 422 validation errors with field-specific parsing
- Updates scope status with success/error messages
- Refreshes current numbering display on success
- Shows inline error messages per field

#### 2. Blade Template (`resources/views/settings/partials/numbering.blade.php`)
Updated each scope card to include:
- **Inline validation error display**: Red borders and error messages for invalid fields
- **Per-scope action buttons**:
  - "Test Preview" button (existing, now side-by-side with save)
  - "Simpan" (Save) button for each scope
- **Status messages**: Success/error messages displayed per scope
- Removed global "Simpan" button
- Added informative tip about per-scope saving

UI improvements:
- Error states show red borders on inputs with validation errors
- Error messages display below each invalid field
- Success/error status shows below action buttons
- Loading states for each scope independently

## API Endpoint

### PUT `/api/settings/numbering/{scope}`

**Scopes**: `sample_code`, `ba`, `lhu`, `ba_penyerahan`, `tracking`

**Request Body**:
```json
{
  "pattern": "SMP/{YYYY}/{MM}/{N:4}",
  "reset": "yearly",
  "start_from": 1
}
```

**Success Response (200)**:
```json
{
  "scope": "sample_code",
  "config": {
    "pattern": "SMP/{YYYY}/{MM}/{N:4}",
    "reset": "yearly",
    "start_from": 1
  },
  "message": "Pengaturan penomoran berhasil disimpan."
}
```

**Validation Error Response (422)**:
```json
{
  "message": "pattern: Pattern wajib diisi.; reset: Reset period wajib dipilih.",
  "errors": {
    "pattern": ["Pattern wajib diisi."],
    "reset": ["Reset period wajib dipilih."],
    "start_from": ["Start from minimal 1."]
  }
}
```

## User Experience

### Before
- Single "Simpan" button for all scopes
- Empty payload `{"numbering":{}}` causing 422 errors
- No inline validation feedback
- Had to fill all scopes before saving

### After
- ✅ Individual "Simpan" button per document type
- ✅ Each scope saves independently with correct payload
- ✅ Inline validation errors per field (pattern, reset, start_from)
- ✅ Success/error messages per scope
- ✅ Can save partial configurations
- ✅ No console errors about undefined state keys
- ✅ Current numbering display refreshes after save

## Scope Mappings

- `sample_code` → Used by `/samples/test` (Kode Sampel)
- `ba` → Used by `/requests` (BA Penerimaan)
- `ba_penyerahan` → Used by `/delivery` (BA Penyerahan)
- `tracking` → Used by `/tracking` (Nomor Resi)
- `lhu` → Laporan Hasil Uji

## Validation Rules

| Field | Rules | Error Message |
|-------|-------|---------------|
| pattern | required, string, max:255 | Pattern wajib diisi. |
| reset | required, in:daily,weekly,monthly,yearly,never | Reset period wajib dipilih. |
| start_from | required, integer, min:1 | Start from minimal 1. |
| per_test_type | optional, boolean | - |

## Technical Implementation Details

### State Management
- Each scope has independent error, status, and loading states
- Errors are parsed from API response and displayed inline
- Status messages show success (green) or error (red) styling
- Loading states prevent multiple simultaneous saves

### Error Handling
- 422 responses parsed for field-specific errors
- Errors displayed inline below each input field
- Invalid fields highlighted with red borders
- Generic error message shown in status area

### Backward Compatibility
- Original bulk save endpoint (`PUT /api/settings/numbering`) still works
- No breaking changes to existing functionality
- Tests for both endpoints passing

## Files Modified

1. `/routes/api.php` - Added per-scope route
2. `/app/Http/Controllers/Api/Settings/NumberingController.php` - Added updateScope method
3. `/resources/js/pages/settings/index.js` - Added saveNumberingScope method and state
4. `/resources/views/settings/partials/numbering.blade.php` - Updated UI with per-scope buttons
5. `/tests/Feature/Api/Settings/NumberingScopeSaveTest.php` - New test file

## Verification Steps

1. ✅ Backend tests pass (8/8 tests, 24 assertions)
2. ✅ Frontend builds without errors
3. ✅ View cache cleared
4. ✅ All scopes have individual save buttons
5. ✅ Validation errors display inline
6. ✅ Success messages show per scope
7. ✅ No console errors about undefined properties

## Next Steps (Optional)

- Add optimistic UI updates (show success state before API response)
- Add undo functionality for scope saves
- Add bulk save option for power users
- Add pattern validation preview during typing
- Add pattern suggestions/templates
