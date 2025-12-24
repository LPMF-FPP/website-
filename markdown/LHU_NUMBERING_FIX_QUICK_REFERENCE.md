# LHU Numbering Fix - Quick Reference

## What Was Fixed
❌ **Before**: LHU generation used hardcoded pattern from sample code (W012V2025 → FLHU012)
✅ **After**: LHU generation uses configured settings from `/settings` via `NumberingService`

## Key Changes

### 1. Controller Update
**File**: [`app/Http/Controllers/SampleTestProcessController.php`](app/Http/Controllers/SampleTestProcessController.php)

- Added `NumberingService` injection
- Replaced `computeFLHUFromSampleCode()` with `$numberingService->issue('lhu')`
- Added logging for all LHU issuance and reuse
- Stores number in `metadata['lhu_number']`

### 2. Business Logic
```
First Generation:  NumberingService::issue('lhu') → NEW number → Save to metadata
Regeneration:      Read metadata['lhu_number'] → REUSE same number
```

### 3. Scope Consistency
All systems now use scope `'lhu'`:
- Settings API: `GET/PUT /api/settings/numbering` with scope `lhu`
- Number issuance: `NumberingService::issue('lhu')`
- Database: `sequences` table with `scope = 'lhu'`

## Testing

### Run Automated Tests
```bash
php artisan test --filter=LhuNumberingGenerationTest
```

### Manual Browser Test
1. Go to `/settings` → Configure LHU pattern: `TEST-{YYYY}-{NNNN}`
2. Go to `/sample-processes/3` → Click "Generate LHU"
3. Verify PDF shows: `TEST-2025-0001` (or similar)
4. Regenerate → Verify same number is used

### Verify in Database
```bash
php artisan tinker --execute='
$p = App\Models\SampleTestProcess::find(3);
dump($p->metadata["lhu_number"] ?? "Not set");
'
```

## Troubleshooting

### Issue: LHU number not matching settings
**Check**: Is the pattern configured in settings?
```bash
php artisan tinker --execute='dump(settings("numbering.lhu"));'
```

**Fix**: Configure via UI at `/settings` or manually:
```bash
php artisan tinker --execute='
App\Models\SystemSetting::updateOrCreate(
    ["key" => "numbering.lhu.pattern"],
    ["value" => "LHU-{YYYY}-{NNNN}"]
);
settings_forget_cache();
'
```

### Issue: Number changes on regeneration
**Root Cause**: Process doesn't have stored `lhu_number` in metadata

**Check**:
```bash
php artisan tinker --execute='
$p = App\Models\SampleTestProcess::find(3);
dump($p->metadata);
'
```

**Fix**: Regenerate once - it will issue and store the number

### Issue: Cache showing old pattern
**Fix**: Clear cache manually
```bash
php artisan tinker --execute='settings_forget_cache();'
# OR
php artisan cache:clear
```

## Files Modified

| File | Changes |
|------|---------|
| [`SampleTestProcessController.php`](app/Http/Controllers/SampleTestProcessController.php) | Added `NumberingService`, rewrote `generateReport()`, added logging |
| [`SampleTestProcessFactory.php`](database/factories/SampleTestProcessFactory.php) | NEW: Factory for testing |
| [`LhuNumberingGenerationTest.php`](tests/Feature/LhuNumberingGenerationTest.php) | NEW: 6 comprehensive tests |

## Cache & Performance

- ✅ Cache invalidation already working (`SettingsWriter` calls `settings_forget_cache()`)
- ✅ Concurrency safe (NumberingService uses transaction locks)
- ✅ Minimal performance impact (one DB query for sequence)

## Backward Compatibility

- ✅ Legacy `metadata['report_number']` still recognized
- ✅ No migration needed
- ✅ Existing PDFs unchanged
- ✅ New generations use new system

## Key Logs to Monitor
```bash
tail -f storage/logs/laravel.log | grep "LHU number"
```

Look for:
- `[INFO] LHU number issued` - New number generated
- `[DEBUG] Reusing existing LHU number` - Regeneration
- `[ERROR] Failed to issue LHU number` - Configuration or DB issue

## Complete Documentation
See [LHU_NUMBERING_FIX_SUMMARY.md](LHU_NUMBERING_FIX_SUMMARY.md) for full details.
