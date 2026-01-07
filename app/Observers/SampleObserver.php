<?php

namespace App\Observers;

use App\Models\Sample;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;

class SampleObserver
{
    public function created(Sample $sample): void
    {
        ActivityLogger::log(
            'SAMPLE_CREATED',
            null,
            $sample,
            null,
            $this->snapshot($sample),
            [
                'sample_code' => $sample->sample_code,
                'test_request_id' => $sample->test_request_id,
            ]
        );
    }

    public function updated(Sample $sample): void
    {
        $changes = $sample->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $before = Arr::only($sample->getOriginal(), array_keys($changes));

        ActivityLogger::log(
            'SAMPLE_UPDATED',
            null,
            $sample,
            $before,
            $changes,
            [
                'sample_code' => $sample->sample_code,
                'test_request_id' => $sample->test_request_id,
            ]
        );
    }

    public function deleted(Sample $sample): void
    {
        ActivityLogger::log(
            'SAMPLE_DELETED',
            null,
            $sample,
            $this->snapshot($sample),
            null,
            [
                'sample_code' => $sample->sample_code,
                'test_request_id' => $sample->test_request_id,
            ]
        );
    }

    private function snapshot(Sample $sample): array
    {
        return [
            'id' => $sample->id,
            'sample_code' => $sample->sample_code,
            'test_request_id' => $sample->test_request_id,
            'status' => $sample->status,
            'sample_status' => $sample->sample_status,
            'assigned_analyst_id' => $sample->assigned_analyst_id,
        ];
    }
}
