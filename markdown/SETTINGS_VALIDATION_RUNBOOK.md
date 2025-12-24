# Settings Page - Post-Deployment Validation Runbook

**Purpose**: Verify Settings page functionality after deploying null handling fix  
**Target Environment**: Production / Staging  
**Estimated Time**: 15 minutes  

---

## Pre-Requisites

✅ Laravel application deployed and running  
✅ Database migrations up to date  
✅ User with `manage-settings` permission exists  
✅ Browser with developer console access  

---

## Validation Steps

### 1. Page Load & Initial State

**Action**: Navigate to `/settings`

**Expected**:
- ✅ Page loads without HTTP errors
- ✅ No JavaScript console errors
- ✅ All tabs visible: Penomoran, Template, Branding, Lokalisasi, Notifikasi
- ✅ Loading spinner appears briefly, then content loads

**Terminal Check**:
```bash
curl -I http://127.0.0.1:8000/settings -H "Cookie: laravel_session=YOUR_SESSION"
# Expected: HTTP/1.1 200 OK
```

**Browser Console Check**:
- Open Developer Tools (F12)
- Console tab should show NO errors
- Look for: "Alpine.js", "settingsPage" component initialized

---

### 2. Numbering Section (Sample, BA, LHU)

**Action**: Click "Penomoran Otomatis" tab (should be default)

**Expected**:
- ✅ Current numbers display for Sample/BA/LHU (or "-" if not configured)
- ✅ Scope labels show translated text: "Kode Sampel", "Berita Acara", "Laporan Hasil Uji"
- ✅ Pattern input fields are editable
- ✅ Reset dropdown works (Never/Yearly/Monthly/Daily)
- ✅ "Test Preview" generates preview number WITHOUT saving

**Test "Test Preview"**:
1. Set pattern: `{YYYY}-{MM}-{SEQ:4}`
2. Click "Test Preview" for any scope
3. Expected: Preview shows format like "2025-12-0001"
4. Expected: No error message, preview displays below pattern field

**Test "Simpan"**:
1. Modify any numbering pattern
2. Click "Simpan" button
3. Expected: Success message "Pengaturan tersimpan."
4. Expected: No HTTP 500 or console errors

**API Call Check** (Browser Network Tab):
```
PUT /api/settings/numbering
Status: 200 OK
Response: {"numbering": {...}}
```

---

### 3. Localization & Retention (CRITICAL TEST - Null Handling)

**Action**: Click "Lokalisasi & Retensi" tab

**Expected Fields**:
- Timezone (dropdown)
- Date Format (dropdown)
- Storage Driver (dropdown)
- Storage Folder Path (text input)
- **Purge After Days (number input, optional)** ⭐ KEY FIELD
- Export Filename Pattern (text input, optional)

**🔴 CRITICAL TEST - Empty Optional Fields**:

This is the primary fix validation. The null constraint error occurred when optional fields were left empty.

**Steps**:
1. Clear the "Purge After Days" field (leave it empty)
2. Set Storage Driver: "local"
3. Set Storage Folder Path: "test-folder"
4. Leave "Export Filename Pattern" empty
5. Click "Simpan"

**Expected** ✅:
- Success message: "Pengaturan tersimpan."
- NO error: "NOT NULL constraint violation"
- NO HTTP 500 error
- Settings save successfully

**API Call Check** (Browser Network Tab):
```
PUT /api/settings/localization-retention
Status: 200 OK (NOT 500!)
Response: {"locale": {...}, "retention": {...}}
```

**Database Check** (Terminal):
```bash
PGPASSWORD='LPMFjaya1' psql -h 127.0.0.1 -U lis_user -d lis_db -c \
  "SELECT key FROM settings WHERE key = 'retention.purge_after_days';"
  
# Expected: 0 rows (key should not exist if value was null)
# This confirms null values are NOT being inserted
```

**🔴 CRITICAL TEST - Update Null to Value**:

**Steps**:
1. Enter "90" in "Purge After Days" field
2. Click "Simpan"
3. Expected: Success, value saved

**Database Check**:
```bash
PGPASSWORD='LPMFjaya1' psql -h 127.0.0.1 -U lis_user -d lis_db -c \
  "SELECT key, value FROM settings WHERE key = 'retention.purge_after_days';"
  
# Expected: 1 row with value "90"
```

**🔴 CRITICAL TEST - Update Value Back to Null**:

**Steps**:
1. Clear the "Purge After Days" field again
2. Click "Simpan"
3. Expected: Success, no errors

**Database Check**:
```bash
PGPASSWORD='LPMFjaya1' psql -h 127.0.0.1 -U lis_user -d lis_db -c \
  "SELECT key FROM settings WHERE key = 'retention.purge_after_days';"
  
# Expected: 0 rows (record should be deleted)
```

This confirms the **delete-on-null behavior** is working correctly.

---

### 4. Templates Section

**Action**: Click "Template Dokumen" tab

**Test Upload**:
1. Select DOCX file (any .docx file for testing)
2. Enter Code: "TEST_TPL"
3. Enter Name: "Test Template"
4. Click "Upload"
5. Expected: Template appears in list

**Test Activate**:
1. Click "Aktifkan" for uploaded template
2. Select scope: "sample_code" / "ba" / "lhu"
3. Expected: Template assigned to scope
4. Expected: Active template badge shows on that template

**Test Preview**:
1. Click "Preview" on an active template
2. Expected: New tab opens showing template content OR PDF preview

**Test Delete**:
1. Click "Hapus" on a template
2. Confirm deletion
3. Expected: Template removed from list

**API Calls Check**:
```
POST /api/settings/templates/upload - 200 OK
PUT /api/settings/templates/{id}/activate - 200 OK
GET /api/settings/templates/{id}/preview - 200 OK
DELETE /api/settings/templates/{id} - 200 OK
```

---

### 5. Branding Section

**Action**: Click "Branding & PDF" tab

**Test Fields**:
1. Lab Code: Enter "LF-123"
2. Organization Name: Enter "Test Lab"
3. PDF Header Address: Enter "Jl. Test No. 1"
4. PDF Header Contact: Enter "021-1234567"
5. Watermark: Select "Draft" or "Confidential"
6. PDF Footer Text: Enter "Footer test"

**Test PDF Preview**:
1. Click "Preview PDF"
2. Expected: PDF preview appears in embedded viewer OR new tab
3. Expected: PDF shows branding information entered above

**Test Save**:
1. Click "Simpan"
2. Expected: Success message

**API Calls Check**:
```
POST /api/settings/pdf/preview - 200 OK (returns PDF blob)
PUT /api/settings/branding - 200 OK
```

---

### 6. Notifications & Security Section

**Action**: Click "Notifikasi & Keamanan" tab

**Test Notification Toggles**:
1. Toggle "Email Enabled" on/off
2. Toggle "WhatsApp Enabled" on/off
3. Expected: Toggles change state smoothly

**Test Notification Test (Optional if configured)**:
1. Enter email address in "Test Email" field
2. Click "Kirim Test"
3. Expected: Success message OR informative error if not configured

**Test Security Roles**:
1. Check/uncheck roles for "Can Manage Settings"
2. Check/uncheck roles for "Can Issue Number"
3. Click "Simpan"
4. Expected: Success message

**API Calls Check**:
```
PUT /api/settings/notifications-security - 200 OK
POST /api/settings/notifications/test - 200 OK (or 422 if not configured)
```

---

## Error Scenarios to Test

### ❌ Scenario 1: Missing CSRF Token
**Action**: Remove CSRF meta tag from page, try to save  
**Expected**: HTTP 419 (CSRF Token Mismatch) - graceful error message

### ❌ Scenario 2: Invalid Timezone
**Action**: Use browser console to inject invalid timezone value  
**Expected**: HTTP 422 (Validation Error) - informative error message

### ❌ Scenario 3: Negative Purge Days
**Action**: Enter "-10" in Purge After Days  
**Expected**: HTTP 422 (Validation: min 30 days)

### ❌ Scenario 4: Directory Traversal Attempt
**Action**: Enter "../../../etc/passwd" in Storage Folder Path  
**Expected**: HTTP 422 (Validation: directory traversal not allowed)

---

## Automated Test Verification

**Run API Settings Test Suite**:
```bash
php artisan test --filter="Api\\Settings"
```

**Expected Output**:
```
PASS  Tests\Feature\Api\Settings\LocalizationRetentionTest
 ✓ dapat mengupdate localization dan retention settings
 ✓ menerima purge_after_days kosong sebagai null ⭐ CRITICAL
 ✓ menolak absolute path
 ✓ menolak directory traversal
 ✓ validasi timezone harus dari daftar yang diizinkan
 ✓ validasi purge_after_days minimum 30 hari

PASS  Tests\Feature\Api\Settings\SettingsWriterNullTest
 ✓ it handles null values without database constraint violation ⭐ CRITICAL
 ✓ it handles nested arrays with all null values
 ✓ it handles mixed null and non-null values correctly
 ✓ it deletes existing setting when updated to null

[... more passing tests ...]

Tests:  17 passed (25 assertions)
Duration: ~1 second
```

**If any test fails**: ❌ DO NOT DEPLOY - investigate and fix

---

## Log Monitoring

**During Testing**:
```bash
tail -f storage/logs/laravel.log | grep -i "settings\|numbering\|constraint\|null"
```

**Look For** (GOOD signs):
- `Settings update: updating key` - normal operation
- `Settings update: skipping null` - null handling working (if using web route)
- No ERROR or EXCEPTION messages

**Look For** (BAD signs):
- ❌ `SQLSTATE[23502]` - NULL constraint violation (FIX NOT WORKING)
- ❌ `RuntimeException: Numbering config for` - safe defaults not applied
- ❌ `Uncaught Error` - JavaScript errors

---

## Success Criteria

All of the following must be TRUE:

- ✅ Settings page loads without errors
- ✅ All 5 tabs (Penomoran, Template, Branding, Lokalisasi, Notifikasi) functional
- ✅ **Saving retention settings with empty "Purge After Days" succeeds** ⭐ CRITICAL
- ✅ No HTTP 500 errors during any save operation
- ✅ No JavaScript console errors
- ✅ No database constraint violations in logs
- ✅ All automated tests pass (17/17 API Settings tests)
- ✅ Audit logs created for setting changes
- ✅ Settings persist across page reloads

---

## Rollback Plan (If Issues Found)

If critical issues occur:

1. **Revert SettingsWriter changes**:
```bash
git diff app/Services/Settings/SettingsWriter.php > settings-writer-fix.patch
git checkout HEAD -- app/Services/Settings/SettingsWriter.php
php artisan config:clear
php artisan route:clear
```

2. **Restart Laravel**:
```bash
# If using php artisan serve
Ctrl+C
php artisan serve

# If using systemd/supervisor
sudo systemctl restart laravel-worker
```

3. **Notify team**: Settings may have issues with null values until fix is reapplied

---

## Post-Validation Actions

### If ALL TESTS PASS ✅:
1. Mark deployment as successful
2. Monitor logs for 24 hours
3. No action needed - system is stable

### If ANY TEST FAILS ❌:
1. Document exact failure scenario
2. Capture:
   - HTTP request/response (browser Network tab)
   - JavaScript console errors
   - Laravel logs (last 100 lines)
   - Database state
3. Open bug ticket with evidence
4. Consider rollback if critical

---

## Contact & Support

**If you encounter issues during validation**:

1. Check `SETTINGS_QA_DEFECT_REPORT.md` for known issues
2. Check `storage/logs/laravel.log` for error details
3. Run automated tests: `php artisan test --filter="Api\\Settings"`
4. Create issue with:
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshots of errors
   - Log excerpts

---

**Validation Checklist Complete** ✅  
**Estimated Completion Time**: 15 minutes  
**Critical Test**: Null value handling in retention settings  
**Success Rate Expected**: 100% (all tests should pass)
