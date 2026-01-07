<?php

namespace App\Observers;

use App\Models\TestRequest;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;

class TestRequestObserver
{
    public function created(TestRequest $testRequest): void
    {
        ActivityLogger::log(
            'REQUEST_CREATED',
            null,
            $testRequest,
            null,
            $this->snapshot($testRequest),
            [
                'request_number' => $testRequest->request_number,
                'receipt_number' => $testRequest->receipt_number,
            ]
        );
    }

    public function updated(TestRequest $testRequest): void
    {
        $changes = $testRequest->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $before = Arr::only($testRequest->getOriginal(), array_keys($changes));
        $action = 'REQUEST_UPDATED';
        $meta = [
            'request_number' => $testRequest->request_number,
            'receipt_number' => $testRequest->receipt_number,
        ];

        if (array_key_exists('status', $changes)) {
            $action = 'REQUEST_STATUS_CHANGED';
            $meta['changes'] = Arr::except($changes, ['status']);
            $before = ['status' => $before['status'] ?? null];
            $changes = ['status' => $changes['status']];
        }

        ActivityLogger::log(
            $action,
            null,
            $testRequest,
            $before,
            $changes,
            $meta
        );
    }

    public function deleted(TestRequest $testRequest): void
    {
        ActivityLogger::log(
            'REQUEST_DELETED',
            null,
            $testRequest,
            $this->snapshot($testRequest),
            null,
            [
                'request_number' => $testRequest->request_number,
                'receipt_number' => $testRequest->receipt_number,
            ]
        );
    }

    private function snapshot(TestRequest $testRequest): array
    {
        return [
            'id' => $testRequest->id,
            'request_number' => $testRequest->request_number,
            'receipt_number' => $testRequest->receipt_number,
            'status' => $testRequest->status,
            'investigator_id' => $testRequest->investigator_id,
            'user_id' => $testRequest->user_id,
            'submitted_at' => optional($testRequest->submitted_at)->toISOString(),
            'verified_at' => optional($testRequest->verified_at)->toISOString(),
            'received_at' => optional($testRequest->received_at)->toISOString(),
            'completed_at' => optional($testRequest->completed_at)->toISOString(),
        ];
    }
}
