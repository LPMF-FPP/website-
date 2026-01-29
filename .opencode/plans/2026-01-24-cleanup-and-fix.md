# Code Cleanup & Bug Fix Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Clean up deprecated code ("old strings") and fix a critical race condition potential in the delete rollback logic.

**Analysis:**

1. **Critical Bug:** The `deleting` event fires _before_ the record is deleted. If the delete fails (e.g., FK constraint), the counter is rolled back but the record remains, leading to **duplicate numbers** on the next insert.
2. **Garbage Code:** `TestRequest` and `Sample` models contain deprecated methods `generateRequestNumber`, `generateSampleCode`, and unused helper `toRoman`.

---

## Task Overview

| Task   | Deskripsi                                | Estimasi |
| ------ | ---------------------------------------- | -------- |
| Task 1 | Fix Rollback Logic (deleting -> deleted) | 15 menit |
| Task 2 | Remove Deprecated Code (TestRequest)     | 10 menit |
| Task 3 | Remove Deprecated Code (Sample)          | 10 menit |
| Task 4 | Verify Tests                             | 10 menit |

**Total Estimasi:** ~45 menit

---

## Task 1: Fix Rollback Logic (TestRequest & Sample)

**Files:**

- Modify: `app/Models/TestRequest.php`
- Modify: `app/Models/Sample.php`

**Step 1: Move logic to `deleted` in TestRequest**

Change `static::deleting(...)` to `static::deleted(...)`.
Ensure cache clearing logic is preserved or merged.

```php
// app/Models/TestRequest.php

// REMOVE static::deleting(...) block completely

// MERGE into static::deleted(...)
static::deleted(function (self $model) {
    // 1. Rollback sequences (SAFE now: only runs if delete succeeded)
    $numbering = app(\App\Services\NumberingService::class);

    if ($model->request_number) {
        $numbering->rollback('ba', $model->request_number, [
            'now' => $model->created_at ? \Carbon\CarbonImmutable::parse($model->created_at) : null,
            'investigator_id' => $model->investigator_id,
        ]);
        // Clear cache
        Cache::forget('track:condensed:'.$model->request_number);
    }

    if ($model->receipt_number) {
        $numbering->rollback('tracking', $model->receipt_number, [
            'now' => $model->created_at ? \Carbon\CarbonImmutable::parse($model->created_at) : null,
            'investigator_id' => $model->investigator_id,
        ]);
        // Clear cache
        Cache::forget('track:condensed:'.$model->receipt_number);
    }
});
```

**Step 2: Move logic to `deleted` in Sample**

Change `static::deleting(...)` to `static::deleted(...)`.

```php
// app/Models/Sample.php

static::deleted(function (self $model) {
    if ($model->sample_code) {
        $numbering = app(\App\Services\NumberingService::class);
        $numbering->rollback('sample_code', $model->sample_code, [
            'now' => $model->created_at ? \Carbon\CarbonImmutable::parse($model->created_at) : null,
            'investigator_id' => $model->testRequest?->investigator_id,
        ]);
    }
});
```

---

## Task 2: Remove Deprecated Code (TestRequest)

**Files:**

- Modify: `app/Models/TestRequest.php`

**Step 1: Remove `generateRequestNumber` method**

Remove the entire method block:

```php
    /**
     * @deprecated Use NumberingService instead
     * Legacy method - kept for reference only
     */
    protected static function generateRequestNumber(): string
    {
        // ...
    }
```

---

## Task 3: Remove Deprecated Code (Sample)

**Files:**

- Modify: `app/Models/Sample.php`

**Step 1: Remove `generateSampleCode` and `toRoman` methods**

Remove:

1. `generateSampleCode()`
2. `toRoman()` (confirmed unused)

---

## Task 4: Verify Tests

**Files:**

- Run: `tests/Feature/NumberingRollbackOnDeleteTest.php`

**Step 1: Run tests**

`php artisan test tests/Feature/NumberingRollbackOnDeleteTest.php`

Expected: PASS (Tests check for rollback _success_. Since we delete successfully in tests, `deleted` event will fire and rollback will happen.)
