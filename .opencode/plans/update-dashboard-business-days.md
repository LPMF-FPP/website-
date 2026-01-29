# Plan: Calculate Business Days for Dashboard Statistics

> **Status:** Proposal (Read-Only)
> **Goal:** Update the "average processing time" calculation on the dashboard to count only **5 business days (Mon-Fri)**, excluding weekends.

## 1. Analysis

- **Current Logic:** Uses `$start->floatDiffInDays($end)`, which counts 24/7 (calendar days).
- **Target Logic:** Count only Monday through Friday.
- **Context:** `DashboardController::calculateMonthlyAverageProcessingDays` (Lines 285-320).
- **Dependency:** Carbon (Laravel's date library) has built-in methods for business days (`diffInWeekdays` or `diffInDaysFiltered`), but they return integers. For fractional days (e.g. 1.5 days), we need a slightly more precise approach or stick to integer business days.
- **Decision:** Since standard business day calculations usually deal in full days, using `diffInWeekdays()` is the standard approach. If precision (hours) is needed, we calculate diff in hours and exclude weekend hours, but that's complex. Standard "SLA" calculation is usually integer or rounded days. I'll propose using `diffInDaysFiltered` to strictly exclude weekends.

## 2. Implementation Steps

### Phase 1: Controller Update (`app/Http/Controllers/DashboardController.php`)

Modify `calculateMonthlyAverageProcessingDays` method.

- **Change:** Replace `floatDiffInDays()` with a custom business day calculation logic.
- **Logic:**
    ```php
    // Calculate business days (Mon-Fri)
    // Carbon's diffInWeekdays() counts start but not end, or vice versa depending on inclusive/exclusive.
    // A safe approach for partial days is tricky.
    // Simpler approach:
    return $start->diffInWeekdays($end);
    ```

    - _Self-Correction:_ `floatDiffInDays` was giving 1 decimal precision (e.g., 2.5 days). `diffInWeekdays` gives integers.
    - _Refined Logic:_ If we want to keep the precision (e.g., submitted Friday 4 PM, done Monday 10 AM = ~0.5 work days?), that's complex.
    - _Standard:_ Most dashboard stats use integer "Work Days".
    - _User Intent:_ "dihitung 7 hari (senin- minggu) kerja atau 5 hari kerja (senin-Jumat)". They want the rule changed to Mon-Fri.
    - **Proposal:** Use `diffInWeekdays()` for simplicity and standard SLA tracking.

### Phase 2: Verification

- **Test Case:**
    - Start: Friday 10:00 AM
    - End: Monday 10:00 AM
    - Calendar Days: 3.0
    - Business Days: 1.0 (Friday to Monday is 1 weekday transition).

## 3. Plan Details

**File:** `app/Http/Controllers/DashboardController.php`

**Snippet to Update:**

```php
// BEFORE
return \Carbon\Carbon::parse($r->submitted_at)->floatDiffInDays(\Carbon\Carbon::parse($end));

// AFTER
$start = \Carbon\Carbon::parse($r->submitted_at);
$finish = \Carbon\Carbon::parse($end);
// Use diffInWeekdays() which excludes Sat/Sun
return $start->diffInWeekdays($finish);
```

---

**Ready to execute?** Type "Execute" to apply the change.
