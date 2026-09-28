<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SampleStatus;
use App\Enums\TestProcessStage;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use Illuminate\Validation\ValidationException;

class SampleReviewService
{
    /**
     * @param  array<string, mixed>  $sampleData
     */
    public function review(Sample $sample, array $sampleData, string $testDate, string $errorPrefix = 'sample'): void
    {
        $requestedMethods = $this->normalizeMethods($sample->requested_test_methods ?? $sample->test_methods);
        $submittedMethods = array_values(array_unique($sampleData['test_methods']));
        $missingRequested = array_diff($requestedMethods, $submittedMethods);

        if ($missingRequested !== []) {
            throw ValidationException::withMessages([
                $errorPrefix.'.test_methods' => 'Metode pengujian pada permintaan tidak dapat dihapus.',
            ]);
        }

        $otherCategory = $sampleData['other_sample_category'] ?? null;
        $isOtherSample = $sample->sample_form === 'other' || $sample->sample_type === 'other';
        if ($isOtherSample && ! $otherCategory) {
            throw ValidationException::withMessages([
                $errorPrefix.'.other_sample_category' => 'Pilih kategori sampel untuk jenis lainnya.',
            ]);
        }

        if (! $isOtherSample) {
            $otherCategory = null;
        }

        $encodedSubmittedMethods = json_encode($submittedMethods, JSON_THROW_ON_ERROR);

        $sample->update([
            'assigned_analyst_id' => $sampleData['assigned_analyst_id'],
            'test_methods' => $encodedSubmittedMethods,
            'requested_test_methods' => $sample->requested_test_methods ?: $encodedSubmittedMethods,
            'active_substance' => $sampleData['active_substance'],
            'test_type' => $sampleData['test_type'] ?? null,
            'physical_identification' => $sampleData['physical_identification'],
            'quantity' => $sampleData['quantity'],
            'quantity_unit' => $sample->unit ?? $sampleData['quantity_unit'] ?? null,
            'batch_number' => $sampleData['batch_number'] ?? null,
            'expiry_date' => $sampleData['expiry_date'] ?? null,
            'test_date' => $testDate,
            'notes' => $sampleData['notes'] ?? null,
            'other_sample_category' => $otherCategory,
            'status' => SampleStatus::PREPARATION_PENDING,
        ]);

        $stages = [
            TestProcessStage::PREPARATION,
            TestProcessStage::INSTRUMENTATION,
            TestProcessStage::INTERPRETATION,
        ];

        foreach ($stages as $stage) {
            $createAttrs = [
                'performed_by' => $stage === TestProcessStage::INSTRUMENTATION
                    ? $sampleData['assigned_analyst_id']
                    : null,
            ];

            if ($stage === TestProcessStage::PREPARATION || $stage === TestProcessStage::INSTRUMENTATION) {
                $createAttrs['started_at'] = now();
            }

            SampleTestProcess::firstOrCreate(
                [
                    'sample_id' => $sample->id,
                    'stage' => $stage->value,
                ],
                $createAttrs
            );
        }
    }

    private function normalizeMethods(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }
}
