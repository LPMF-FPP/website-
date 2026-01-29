# Plan: Fix "Late" Status on Environment Monitoring

> **Status:** Proposal (Read-Only)
> **Goal:** Fix the issue where environment monitoring labels show "Terlambat" (Late/Overdue) even after data has been input. This happens because the system strictly checks for readings _within_ the configured time windows (e.g., 07:00-09:00), ignoring valid inputs made outside these hours (e.g., at 10:00).

## 1. Analysis

- **Current Logic:** `getLocationStatus` looks for records strictly between `window_morning_start` and `window_morning_end`.
- **The Problem:** If a user inputs data at 10:00 (late), the system doesn't find it in the 07:00-09:00 window. Variable `$morningFilled` becomes `false`. The system then compares current time vs window end, sees it's past time, and flags it "Overdue".
- **Desired Behavior:** If data exists for the "Morning Session" (even if recorded late), it should be marked as "Filled/Complete", not "Overdue".

## 2. Solution: Widen Detection Windows

We will relax the _search_ window for records, while keeping the _schedule_ window for "Due" status.

- **Morning Detection:** Search from `00:00:00` to `window_afternoon_start` (exclusive).
    - _Example:_ Any reading before 12:00 counts as Morning.
- **Afternoon Detection:** Search from `window_afternoon_start` (inclusive) to `23:59:59`.
    - _Example:_ Any reading from 12:00 onwards counts as Afternoon.

## 3. Implementation Details

**File:** `app/Services/EnvironmentMonitoringService.php`

**Method:** `getLocationStatus`

**Logic Change:**

```php
// BEFORE
$morningReading = ...whereBetween('measured_at', [$morningStart, $morningEnd])...
$afternoonReading = ...whereBetween('measured_at', [$afternoonStart, $afternoonEnd])...

// AFTER
// Define wider detection windows
$morningSearchEnd = $afternoonStart; // e.g., 12:00
$afternoonSearchStart = $afternoonStart;

$morningReading = ...whereBetween('measured_at', [$startOfDay, $morningSearchEnd])...
$afternoonReading = ...whereBetween('measured_at', [$afternoonSearchStart, $endOfDay])...
```

_Note: We need to handle exact boundary carefully (e.g. subtract 1 second for morning end)._

## 4. Execution Steps

1.  **Edit Service:** Modify `getLocationStatus` in `EnvironmentMonitoringService.php`.
2.  **Deploy:** Update production.

---

**Ready to execute?** Type "Execute" to apply the fix.
