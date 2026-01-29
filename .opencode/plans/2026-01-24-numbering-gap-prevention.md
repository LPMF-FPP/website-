# Numbering Gap Prevention & Reclaim Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Mencegah gap penomoran saat entity dihapus dan menyediakan fitur UI untuk memperbaiki gap yang sudah ada dengan aman.

**Architecture:**

- P1: Tambahkan `deleting` hook di model untuk memanggil `NumberingService::rollback()` secara otomatis
- P2: Tambahkan fitur Reclaim Gap dengan preview/dry-run di UI Numbering Repair

**Tech Stack:** Laravel 12, PHP 8.2+, Alpine.js, Tailwind CSS, PostgreSQL

---

## Task Overview

| Task   | Deskripsi                                       | Estimasi |
| ------ | ----------------------------------------------- | -------- |
| Task 1 | P1: Rollback on Delete - TestRequest            | 30 menit |
| Task 2 | P1: Rollback on Delete - Sample                 | 30 menit |
| Task 3 | P1: Test Rollback on Delete                     | 30 menit |
| Task 4 | P2: Service Methods (canReclaimGap, reclaimGap) | 45 menit |
| Task 5 | P2: API Endpoints                               | 30 menit |
| Task 6 | P2: UI Components                               | 45 menit |
| Task 7 | P2: Test Reclaim Gap                            | 30 menit |
| Task 8 | Deploy & Verify                                 | 30 menit |

**Total Estimasi:** ~4-5 jam

---

## Task 1: P1 - Rollback on Delete (TestRequest)

**Files:**

- Modify: `app/Models/TestRequest.php:47-87` (boot method)

**Step 1: Tambahkan deleting hook di TestRequest**

Tambahkan sebelum `$clear` closure (sekitar line 73):

```php
static::deleting(function (self $model) {
    // Attempt to rollback numbering sequences
    // Only succeeds if this is the LAST number issued (prevents gaps on delete)
    $numbering = app(\App\Services\NumberingService::class);

    // Rollback BA number
    if ($model->request_number) {
        $numbering->rollback('ba', $model->request_number, [
            'now' => $model->created_at ? \Carbon\CarbonImmutable::parse($model->created_at) : null,
            'investigator_id' => $model->investigator_id,
        ]);
    }

    // Rollback tracking/receipt number
    if ($model->receipt_number) {
        $numbering->rollback('tracking', $model->receipt_number, [
            'now' => $model->created_at ? \Carbon\CarbonImmutable::parse($model->created_at) : null,
            'investigator_id' => $model->investigator_id,
        ]);
    }
});
```

**Step 2: Verify syntax**

Run: `php artisan tinker --execute="new App\Models\TestRequest();"`
Expected: No errors

---

## Task 2: P1 - Rollback on Delete (Sample)

**Files:**

- Modify: `app/Models/Sample.php:99-114` (boot method)

**Step 1: Tambahkan deleting hook di Sample**

Tambahkan setelah `static::creating` closure (sekitar line 113):

```php
static::deleting(function (self $model) {
    // Attempt to rollback sample_code sequence
    // Only succeeds if this is the LAST number issued
    if ($model->sample_code) {
        $numbering = app(\App\Services\NumberingService::class);
        $numbering->rollback('sample_code', $model->sample_code, [
            'now' => $model->created_at ? \Carbon\CarbonImmutable::parse($model->created_at) : null,
            'investigator_id' => $model->testRequest?->investigator_id,
        ]);
    }
});
```

**Step 2: Verify syntax**

Run: `php artisan tinker --execute="new App\Models\Sample();"`
Expected: No errors

---

## Task 3: P1 - Test Rollback on Delete

**Files:**

- Create: `tests/Feature/NumberingRollbackOnDeleteTest.php`

**Step 1: Create test file**

```php
<?php

namespace Tests\Feature;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\Sequence;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingRollbackOnDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup default numbering settings
        settings()->set('numbering.ba.pattern', 'BA-{SEQ:3}/{RM}/{YYYY}');
        settings()->set('numbering.ba.reset', 'yearly');
        settings()->set('numbering.tracking.pattern', 'TR-{SEQ:3}I{YYYY}');
        settings()->set('numbering.tracking.reset', 'yearly');
        settings()->set('numbering.sample_code.pattern', 'LS{SEQ:3}I{YYYY}');
        settings()->set('numbering.sample_code.reset', 'yearly');
    }

    /** @test */
    public function it_rollbacks_ba_and_tracking_when_last_test_request_is_deleted()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        // Create test request (will auto-generate BA and tracking numbers)
        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);

        // Verify sequences were created
        $baSequence = Sequence::where('scope', 'ba')->first();
        $trackingSequence = Sequence::where('scope', 'tracking')->first();

        $this->assertEquals(1, $baSequence->current_value);
        $this->assertEquals(1, $trackingSequence->current_value);

        // Delete the request
        $request->delete();

        // Verify sequences were rolled back
        $baSequence->refresh();
        $trackingSequence->refresh();

        $this->assertEquals(0, $baSequence->current_value);
        $this->assertEquals(0, $trackingSequence->current_value);
    }

    /** @test */
    public function it_does_not_rollback_when_deleting_non_last_test_request()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        // Create two test requests
        $request1 = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);

        $request2 = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);

        $baSequence = Sequence::where('scope', 'ba')->first();
        $this->assertEquals(2, $baSequence->current_value);

        // Delete the FIRST request (not the last)
        $request1->delete();

        // Sequence should NOT be rolled back (would cause duplicate)
        $baSequence->refresh();
        $this->assertEquals(2, $baSequence->current_value);
    }

    /** @test */
    public function it_rollbacks_sample_code_when_last_sample_is_deleted()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);

        // Create sample (will auto-generate sample_code)
        $sample = Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Test Sample',
            'sample_form' => 'tablet',
            'sample_category' => 'narkotika',
        ]);

        $sampleSequence = Sequence::where('scope', 'sample_code')->first();
        $this->assertEquals(1, $sampleSequence->current_value);

        // Delete the sample
        $sample->delete();

        // Verify sequence was rolled back
        $sampleSequence->refresh();
        $this->assertEquals(0, $sampleSequence->current_value);
    }

    /** @test */
    public function it_does_not_rollback_when_deleting_non_last_sample()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
        ]);

        $sample1 = Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 1',
            'sample_form' => 'tablet',
            'sample_category' => 'narkotika',
        ]);

        $sample2 = Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 2',
            'sample_form' => 'tablet',
            'sample_category' => 'narkotika',
        ]);

        $sampleSequence = Sequence::where('scope', 'sample_code')->first();
        $this->assertEquals(2, $sampleSequence->current_value);

        // Delete the FIRST sample
        $sample1->delete();

        // Sequence should NOT be rolled back
        $sampleSequence->refresh();
        $this->assertEquals(2, $sampleSequence->current_value);
    }
}
```

**Step 2: Run tests**

Run: `php artisan test tests/Feature/NumberingRollbackOnDeleteTest.php`
Expected: All 4 tests pass

---

## Task 4: P2 - Service Methods (canReclaimGap, reclaimGap)

**Files:**

- Modify: `app/Services/NumberingRepairService.php`
- Modify: `app/Models/NumberingChangeLog.php`

**Step 1: Add ACTION_RECLAIM constant to NumberingChangeLog**

Di `app/Models/NumberingChangeLog.php`, tambahkan setelah line 33:

```php
public const ACTION_RECLAIM = 'reclaim';
```

Update `getActionLabelAttribute()` di line 58-66:

```php
public function getActionLabelAttribute(): string
{
    return match ($this->action_type) {
        self::ACTION_RESET => 'Reset Manual',
        self::ACTION_SYNC_MAX => 'Sinkronisasi (Tertinggi)',
        self::ACTION_SYNC_COUNT => 'Sinkronisasi (Jumlah)',
        self::ACTION_EDIT => 'Edit Nomor',
        self::ACTION_RECLAIM => 'Reclaim Gap',
        default => $this->action_type,
    };
}
```

**Step 2: Add canReclaimGap method to NumberingRepairService**

Tambahkan di akhir class `NumberingRepairService` (sebelum closing brace):

```php
/**
 * Check if a gap can be reclaimed for a scope.
 *
 * A gap can be reclaimed when:
 * 1. There is at least one gap
 * 2. There is a document at the current counter position
 * 3. The gap is at position (counter - 1) - i.e., we can rename the last doc to fill the gap
 *
 * Returns null if no reclaimable gap, or array with reclaim info.
 */
public function canReclaimGap(string $scope): ?array
{
    $config = $this->getScopeConfig($scope);
    if (! $config) {
        return null;
    }

    $bucket = $this->getCurrentBucket($scope);
    $reset = settings("numbering.$scope.reset") ?? 'never';
    $documents = $this->getDocumentsInBucket($scope, $bucket, $reset);

    // Get all sequence numbers
    $sequenceNumbers = $documents
        ->map(fn ($doc) => $this->extractSequenceNumber($scope, $doc))
        ->filter(fn ($num) => $num !== null && $num > 0)
        ->unique()
        ->sort()
        ->values();

    if ($sequenceNumbers->isEmpty()) {
        return null;
    }

    $maxNumber = $sequenceNumbers->max();
    $totalDocs = $sequenceNumbers->count();

    // Find all gaps
    $gaps = [];
    for ($i = 1; $i <= $maxNumber; $i++) {
        if (! $sequenceNumbers->contains($i)) {
            $gaps[] = $i;
        }
    }

    if (empty($gaps)) {
        return null; // No gaps
    }

    // Get current counter value
    $sequence = Sequence::where('scope', $scope)
        ->where('bucket', $bucket)
        ->first();

    $currentCounter = $sequence?->current_value ?? 0;

    // For reclaim to work, we need:
    // 1. A document at position = currentCounter (the "last" document)
    // 2. A gap that we can fill by renaming that document

    // Find the document at the highest sequence number
    $lastDoc = $documents->first(fn ($doc) =>
        $this->extractSequenceNumber($scope, $doc) === $maxNumber
    );

    if (! $lastDoc) {
        return null;
    }

    // The simplest reclaim: if counter matches max and there's a gap just before
    // Example: counter=73, docs=1..71,73 (gap at 72) → rename 73→72, counter→72
    $lastGap = end($gaps);

    // Can only reclaim if the gap is exactly (maxNumber - 1)
    // This ensures we're just "shifting" the last document back by 1
    if ($lastGap !== $maxNumber - 1) {
        return [
            'can_reclaim' => false,
            'reason' => 'Gap tidak berada di posisi yang bisa di-reclaim. Gap terakhir di posisi ' . $lastGap . ', dokumen terakhir di posisi ' . $maxNumber . '.',
            'gaps' => $gaps,
            'current_counter' => $currentCounter,
            'max_number' => $maxNumber,
            'suggestion' => 'Gunakan fitur "Edit Nomor" untuk memperbaiki secara manual, atau biarkan gap tersebut.',
        ];
    }

    // We can reclaim!
    $lastDocNumber = $this->getDocumentNumber($scope, $lastDoc);
    $newNumber = $this->numberingService->preview($scope, [], $lastGap);

    return [
        'can_reclaim' => true,
        'gap_position' => $lastGap,
        'current_counter' => $currentCounter,
        'max_number' => $maxNumber,
        'document_to_rename' => [
            'entity_id' => $lastDoc->id,
            'entity_type' => get_class($lastDoc),
            'current_number' => $lastDocNumber,
            'new_number' => $newNumber,
            'entity_name' => $this->getEntityName($scope, $lastDoc),
        ],
        'counter_change' => [
            'from' => $currentCounter,
            'to' => $lastGap,
        ],
        'total_gaps' => count($gaps),
        'all_gaps' => $gaps,
        'preview_message' => sprintf(
            'Rename %s → %s, Counter %d → %d',
            $lastDocNumber,
            $newNumber,
            $currentCounter,
            $lastGap
        ),
    ];
}

/**
 * Execute gap reclaim for a scope.
 *
 * This will:
 * 1. Rename the last document to fill the gap
 * 2. Update related records (cascade)
 * 3. Rollback the counter
 * 4. Log the change
 */
public function reclaimGap(string $scope, string $reason): array
{
    $reclaimInfo = $this->canReclaimGap($scope);

    if (! $reclaimInfo || ! $reclaimInfo['can_reclaim']) {
        throw new \InvalidArgumentException(
            $reclaimInfo['reason'] ?? 'Tidak ada gap yang bisa di-reclaim untuk scope ini'
        );
    }

    $config = $this->getScopeConfig($scope);
    $model = $config['model'];
    $column = $config['column'];
    $bucket = $this->getCurrentBucket($scope);

    $docInfo = $reclaimInfo['document_to_rename'];
    $counterChange = $reclaimInfo['counter_change'];

    return DB::transaction(function () use (
        $scope, $model, $column, $bucket, $docInfo, $counterChange, $reason
    ) {
        // 1. Find and update the document
        $entity = $model::findOrFail($docInfo['entity_id']);
        $oldNumber = $docInfo['current_number'];
        $newNumber = $docInfo['new_number'];

        if ($scope === 'lhu') {
            $metadata = $entity->metadata ?? [];
            $metadata['lhu_number'] = $newNumber;
            $entity->metadata = $metadata;
        } else {
            $entity->{$column} = $newNumber;
        }
        $entity->save();

        // 2. Cascade update related records
        $cascadeCount = 0;

        if ($scope === 'sample_code' && $oldNumber) {
            $cascadeCount = \App\Models\EvidenceUnit::where('sample_id', $entity->id)
                ->where('sample_code', $oldNumber)
                ->update(['sample_code' => $newNumber]);
        }

        if ($scope === 'tracking' && $oldNumber) {
            $cascadeCount = \App\Models\EvidenceUnit::where('request_id', $entity->id)
                ->where('receipt_code', $oldNumber)
                ->update(['receipt_code' => $newNumber]);
        }

        // 3. Rollback the counter
        $sequence = Sequence::where('scope', $scope)
            ->where('bucket', $bucket)
            ->lockForUpdate()
            ->first();

        $oldCounter = $sequence->current_value;
        $sequence->current_value = $counterChange['to'];
        $sequence->save();

        // 4. Log the change
        NumberingChangeLog::log(
            $scope,
            NumberingChangeLog::ACTION_RECLAIM,
            sprintf('%s (counter: %d)', $oldNumber, $oldCounter),
            sprintf('%s (counter: %d)', $newNumber, $counterChange['to']),
            $reason . ($cascadeCount > 0 ? " (cascade: {$cascadeCount} related records)" : ''),
            get_class($entity),
            $entity->id
        );

        return [
            'success' => true,
            'renamed' => [
                'from' => $oldNumber,
                'to' => $newNumber,
            ],
            'counter' => [
                'from' => $oldCounter,
                'to' => $counterChange['to'],
            ],
            'cascade_count' => $cascadeCount,
            'entity_id' => $entity->id,
        ];
    });
}
```

**Step 3: Add required import at top of file**

Pastikan `use App\Models\Sequence;` sudah ada di imports (sudah ada).

---

## Task 5: P2 - API Endpoints

**Files:**

- Modify: `app/Http/Controllers/Api/Settings/NumberingRepairController.php`
- Modify: `routes/api.php`

**Step 1: Add canReclaim endpoint to controller**

Tambahkan method baru di `NumberingRepairController`:

```php
/**
 * Check if gap can be reclaimed for a scope
 */
public function canReclaim(string $scope): JsonResponse
{
    try {
        $result = $this->repairService->canReclaimGap($scope);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    } catch (\InvalidArgumentException $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
}

/**
 * Execute gap reclaim for a scope
 */
public function reclaim(Request $request, string $scope): JsonResponse
{
    $validated = $request->validate([
        'reason' => 'required|string|max:500',
    ], [
        'reason.required' => 'Alasan reclaim wajib diisi',
    ]);

    try {
        $result = $this->repairService->reclaimGap(
            $scope,
            $validated['reason']
        );

        return response()->json([
            'success' => true,
            'message' => 'Gap berhasil di-reclaim',
            'data' => $result,
        ]);
    } catch (\InvalidArgumentException $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
}
```

**Step 2: Add routes**

Di `routes/api.php`, tambahkan di dalam group `numbering/repair` (sekitar line 71, sebelum closing bracket):

```php
Route::get('/{scope}/can-reclaim', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'canReclaim']);
Route::post('/{scope}/reclaim', [\App\Http\Controllers\Api\Settings\NumberingRepairController::class, 'reclaim']);
```

---

## Task 6: P2 - UI Components

**Files:**

- Modify: `resources/views/settings/partials/numbering-repair.blade.php`

**Step 1: Add reclaim state variables**

Di bagian `return { ... }` dalam function `numberingRepair()`, tambahkan setelah `syncReason`:

```javascript
// Reclaim state
reclaimInfo: null,
showReclaimModal: false,
reclaimReason: '',
reclaiming: false,
checkingReclaim: false,
```

**Step 2: Add checkReclaim method**

Tambahkan method baru setelah `confirmSync()`:

```javascript
async checkReclaim() {
    if (!this.selectedScope) return;

    this.checkingReclaim = true;
    this.reclaimInfo = null;

    try {
        const response = await fetch(`/api/settings/numbering/repair/${this.selectedScope}/can-reclaim`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            credentials: 'same-origin',
        });

        const data = await response.json();

        if (response.ok && data.data) {
            this.reclaimInfo = data.data;
        }
    } catch (error) {
        console.error('Check reclaim error:', error);
    } finally {
        this.checkingReclaim = false;
    }
},

openReclaimModal() {
    this.reclaimReason = '';
    this.showReclaimModal = true;
},

async confirmReclaim() {
    if (!this.reclaimReason) return;

    this.reclaiming = true;

    try {
        const response = await fetch(`/api/settings/numbering/repair/${this.selectedScope}/reclaim`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                reason: this.reclaimReason,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            this.showReclaimModal = false;
            this.reclaimInfo = null;
            this.scanScope();
            this.fetchChangeLogs();
            alert('Gap berhasil di-reclaim: ' + data.data.renamed.from + ' → ' + data.data.renamed.to);
        } else {
            this.handleError(data);
        }
    } catch (error) {
        console.error('Reclaim error:', error);
        alert('Gagal melakukan reclaim');
    } finally {
        this.reclaiming = false;
    }
},
```

**Step 3: Update scanScope to also check reclaim**

Di method `scanScope()`, tambahkan setelah `this.scanned = true;` (sekitar line 714):

```javascript
// Also check if gap can be reclaimed
this.checkReclaim();
```

**Step 4: Add Reclaim UI Section**

Tambahkan setelah Counter Status section (setelah line 179, sebelum Problems Table):

```html
{{-- Reclaim Gap Section --}}
<template x-if="reclaimInfo">
    <div
        class="rounded-lg p-4 border mb-6"
        :class="reclaimInfo.can_reclaim ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-200'"
    >
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <svg
                    x-show="reclaimInfo.can_reclaim"
                    class="h-6 w-6 text-amber-500"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
                <svg
                    x-show="!reclaimInfo.can_reclaim"
                    class="h-6 w-6 text-gray-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
            </div>
            <div class="flex-1">
                <h3
                    class="text-sm font-semibold"
                    :class="reclaimInfo.can_reclaim ? 'text-amber-900' : 'text-gray-700'"
                >
                    <span x-show="reclaimInfo.can_reclaim"
                        >Gap Terdeteksi - Bisa Di-Reclaim</span
                    >
                    <span x-show="!reclaimInfo.can_reclaim"
                        >Gap Terdeteksi</span
                    >
                </h3>

                <template x-if="reclaimInfo.can_reclaim">
                    <div class="mt-2">
                        <p class="text-sm text-amber-800">
                            Ditemukan
                            <strong x-text="reclaimInfo.total_gaps"></strong>
                            gap. Gap terakhir di posisi
                            <strong x-text="reclaimInfo.gap_position"></strong>
                            bisa diperbaiki.
                        </p>

                        <div
                            class="mt-3 bg-white rounded-lg p-3 border border-amber-200"
                        >
                            <p class="text-xs text-gray-500 mb-2">
                                Preview Perubahan:
                            </p>
                            <div class="flex items-center gap-2 text-sm">
                                <span
                                    class="font-mono bg-red-100 text-red-700 px-2 py-1 rounded"
                                    x-text="reclaimInfo.document_to_rename.current_number"
                                ></span>
                                <svg
                                    class="h-4 w-4 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M17 8l4 4m0 0l-4 4m4-4H3"
                                    />
                                </svg>
                                <span
                                    class="font-mono bg-green-100 text-green-700 px-2 py-1 rounded"
                                    x-text="reclaimInfo.document_to_rename.new_number"
                                ></span>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                <span
                                    x-text="reclaimInfo.document_to_rename.entity_name"
                                ></span>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Counter:
                                <span
                                    x-text="reclaimInfo.counter_change.from"
                                ></span>
                                →
                                <span
                                    x-text="reclaimInfo.counter_change.to"
                                ></span>
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="openReclaimModal()"
                            class="mt-3 px-4 py-2 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700"
                        >
                            Reclaim Gap
                        </button>
                    </div>
                </template>

                <template x-if="!reclaimInfo.can_reclaim">
                    <div class="mt-2">
                        <p
                            class="text-sm text-gray-600"
                            x-text="reclaimInfo.reason"
                        ></p>
                        <p
                            class="text-xs text-gray-500 mt-2"
                            x-text="reclaimInfo.suggestion"
                        ></p>
                        <p class="text-xs text-gray-400 mt-1">
                            Gap di posisi:
                            <span
                                x-text="reclaimInfo.gaps?.join(', ') || '-'"
                            ></span>
                        </p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
```

**Step 5: Add Reclaim Modal**

Tambahkan setelah Sync Reason Modal (setelah line 540, sebelum closing `</div>` dari component):

```html
{{-- Reclaim Modal --}}
<div
    x-show="showReclaimModal"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-modal="true"
    aria-labelledby="reclaim-modal-title"
    @keydown.escape.window="showReclaimModal = false"
>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div
            class="fixed inset-0 bg-black/50"
            @click="showReclaimModal = false"
        ></div>
        <div
            class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6"
            x-trap.noscroll.inert="showReclaimModal"
        >
            <h3
                id="reclaim-modal-title"
                class="text-lg font-semibold text-gray-900 mb-4"
            >
                Konfirmasi Reclaim Gap
            </h3>

            <div
                class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4"
            >
                <p class="text-sm text-amber-800 font-medium mb-2">
                    Perubahan yang akan dilakukan:
                </p>
                <ul class="text-sm text-amber-700 space-y-1">
                    <li>
                        • Rename:
                        <strong
                            x-text="reclaimInfo?.document_to_rename?.current_number"
                        ></strong>
                        →
                        <strong
                            x-text="reclaimInfo?.document_to_rename?.new_number"
                        ></strong>
                    </li>
                    <li>
                        • Counter:
                        <strong
                            x-text="reclaimInfo?.counter_change?.from"
                        ></strong>
                        →
                        <strong
                            x-text="reclaimInfo?.counter_change?.to"
                        ></strong>
                    </li>
                    <li>• Dokumen terkait akan di-update otomatis (cascade)</li>
                </ul>
            </div>

            <div class="mb-4">
                <label
                    for="reclaim-reason-input"
                    class="block text-sm font-medium text-gray-700 mb-1"
                    >Alasan Reclaim *</label
                >
                <textarea
                    id="reclaim-reason-input"
                    x-model="reclaimReason"
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500"
                    placeholder="Jelaskan alasan reclaim gap..."
                ></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    @click="showReclaimModal = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="confirmReclaim()"
                    :disabled="!reclaimReason || reclaiming"
                    class="px-4 py-2 text-sm font-medium text-white bg-amber-600 rounded-lg hover:bg-amber-700 disabled:opacity-50"
                >
                    <span x-show="!reclaiming">Reclaim Gap</span>
                    <span x-show="reclaiming">Processing...</span>
                </button>
            </div>
        </div>
    </div>
</div>
```

---

## Task 7: P2 - Test Reclaim Gap

**Files:**

- Create: `tests/Feature/NumberingRepairReclaimTest.php`

**Step 1: Create test file**

```php
<?php

namespace Tests\Feature;

use App\Models\Investigator;
use App\Models\Sample;
use App\Models\Sequence;
use App\Models\TestRequest;
use App\Models\User;
use App\Services\NumberingRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingRepairReclaimTest extends TestCase
{
    use RefreshDatabase;

    protected NumberingRepairService $repairService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repairService = app(NumberingRepairService::class);

        // Setup default numbering settings
        settings()->set('numbering.sample_code.pattern', 'LS{SEQ:3}I{YYYY}');
        settings()->set('numbering.sample_code.reset', 'yearly');
    }

    /** @test */
    public function it_detects_reclaimable_gap_when_last_document_deleted()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'request_number' => 'BA-001',
            'receipt_number' => 'TR-001',
        ]);

        // Create 3 samples: LS001, LS002, LS003
        $samples = [];
        for ($i = 0; $i < 3; $i++) {
            $samples[] = Sample::create([
                'test_request_id' => $request->id,
                'short_description' => "Sample " . ($i + 1),
                'sample_form' => 'tablet',
                'sample_category' => 'narkotika',
            ]);
        }

        // Delete middle sample (LS002) - this creates a gap
        $samples[1]->delete();

        // Now we have: LS001, LS003 (gap at LS002), counter = 3
        // This should be reclaimable: rename LS003 → LS002, counter 3 → 2

        $result = $this->repairService->canReclaimGap('sample_code');

        $this->assertNotNull($result);
        $this->assertTrue($result['can_reclaim']);
        $this->assertEquals(2, $result['gap_position']);
    }

    /** @test */
    public function it_cannot_reclaim_gap_in_middle_of_sequence()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'request_number' => 'BA-001',
            'receipt_number' => 'TR-001',
        ]);

        // Create samples with a gap in the middle
        // LS001, LS003, LS004 (gap at LS002)
        Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 1',
            'sample_form' => 'tablet',
            'sample_category' => 'narkotika',
        ]);

        // Skip LS002 by manually creating with forced code
        Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 3',
            'sample_form' => 'tablet',
            'sample_category' => 'narkotika',
            'sample_code' => 'LS003I' . now()->year,
        ]);

        Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 4',
            'sample_form' => 'tablet',
            'sample_category' => 'narkotika',
        ]);

        // Gap is at position 2, but last doc is at 4
        // Cannot reclaim because gap is not at (max - 1)
        $result = $this->repairService->canReclaimGap('sample_code');

        $this->assertNotNull($result);
        $this->assertFalse($result['can_reclaim']);
    }

    /** @test */
    public function it_executes_reclaim_and_updates_counter()
    {
        $investigator = Investigator::factory()->create();
        $user = User::factory()->create();

        $request = TestRequest::create([
            'investigator_id' => $investigator->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'request_number' => 'BA-001',
            'receipt_number' => 'TR-001',
        ]);

        // Create 2 samples
        $sample1 = Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 1',
            'sample_form' => 'tablet',
            'sample_category' => 'narkotika',
        ]);

        $sample2 = Sample::create([
            'test_request_id' => $request->id,
            'short_description' => 'Sample 2',
            'sample_form' => 'tablet',
            'sample_category' => 'narkotika',
        ]);

        // Delete sample 1 to create gap at position 1
        $sample1->delete();

        // Now: only LS002 exists, counter = 2, gap at 1
        // Reclaim should: rename LS002 → LS001, counter 2 → 1

        $result = $this->repairService->reclaimGap('sample_code', 'Test reclaim');

        $this->assertTrue($result['success']);

        // Verify sample was renamed
        $sample2->refresh();
        $this->assertStringContainsString('LS001', $sample2->sample_code);

        // Verify counter was updated
        $sequence = Sequence::where('scope', 'sample_code')->first();
        $this->assertEquals(1, $sequence->current_value);
    }
}
```

**Step 2: Run tests**

Run: `php artisan test tests/Feature/NumberingRepairReclaimTest.php`
Expected: All tests pass

---

## Task 8: Deploy & Verify

**Step 1: Run all tests locally**

```bash
npm run test
```

**Step 2: Run critical audits**

```bash
npm run audit:critical
```

**Step 3: Commit changes**

```bash
git add -A
git commit -m "feat(numbering): add rollback on delete and reclaim gap feature

- P1: Auto-rollback sequence when TestRequest/Sample deleted (if last)
- P2: Add canReclaimGap/reclaimGap service methods
- P2: Add API endpoints for reclaim feature
- P2: Add UI section with preview and confirmation modal
- Add comprehensive tests for both features"
```

**Step 4: Deploy to production**

```bash
sshpass -p 'LPMFjaya1' ssh lpmf-admin@192.168.0.206 "cd /var/www/lis && git pull && php artisan optimize:clear"
```

**Step 5: Verify on production**

1. Buka halaman Settings > Numbering Repair
2. Pilih scope "Kode Sampel"
3. Klik "Scan Masalah"
4. Verifikasi:
    - Counter status muncul dengan benar
    - Jika ada gap, section "Gap Terdeteksi" muncul
    - Jika gap bisa di-reclaim, tombol "Reclaim Gap" aktif
5. (Optional) Test reclaim dengan alasan "Test reclaim feature"

---

## Rollback Plan

Jika ada masalah setelah deploy:

```bash
# Revert commit
git revert HEAD

# Deploy revert
sshpass -p 'LPMFjaya1' ssh lpmf-admin@192.168.0.206 "cd /var/www/lis && git pull && php artisan optimize:clear"
```

---

## Success Criteria

- [ ] P1: Menghapus TestRequest terakhir → counter BA dan tracking rollback
- [ ] P1: Menghapus Sample terakhir → counter sample_code rollback
- [ ] P1: Menghapus entity non-terakhir → counter TIDAK rollback (mencegah duplikasi)
- [ ] P2: Scan menampilkan info gap dengan benar
- [ ] P2: Preview reclaim menampilkan perubahan yang akan dilakukan
- [ ] P2: Reclaim berhasil rename dokumen dan update counter
- [ ] P2: Cascade update ke evidence_units berjalan
- [ ] P2: Log perubahan tercatat di History
