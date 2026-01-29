# Plan: Fix CSV Delimiter for Excel

> **Status:** Ready for Execution
> **Issue:** Excel lumps all CSV data into one column because it expects a semicolon (`;`) separator in regions where comma is a decimal separator, but the current export uses comma (`,`).
> **Fix:** Force `fputcsv` to use `;` as the delimiter.

---

### 1. Implementation

**File:** `app/Http/Controllers/Reports/MonthlyLogReportController.php`

**Change 1: Environment CSV Header**

```php
// Line 127
fputcsv($handle, [ ... ], ';');
```

**Change 2: Environment CSV Rows**

```php
// Line 155 (writeEnvironmentRows)
fputcsv($handle, [ ... ], ';');
```

**Change 3: Instrument CSV Header**

```php
// Line 190
fputcsv($handle, [ ... ], ';');
```

**Change 4: Instrument CSV Rows**

```php
// Line 197
fputcsv($handle, [ ... ], ';');
```

### 2. Deployment

1.  **Apply Patch:** Update local controller.
2.  **Deploy:** SCP file to production.

---

**Ready to execute?** Type "Execute" to apply the fix.
