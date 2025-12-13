<?php

namespace App\Services;

use App\Models\Sample;
use App\Models\Delivery;
use App\Models\SampleTestProcess;
use App\Enums\SampleStatus;
use App\Enums\DeliveryStatus;
use App\Enums\TestProcessStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function startTestProcess(Sample $sample, TestProcessStage $stage): SampleTestProcess
    {
        if ($sample->status !== $stage->getRequiredStatus()) {
            throw ValidationException::withMessages([
                'status' => ['Sample belum siap untuk memulai tahap ' . $stage->label()]
            ]);
        }

        return DB::transaction(function () use ($sample, $stage) {
            $process = new SampleTestProcess([
                'sample_id' => $sample->id,
                'stage' => $stage->value,
                'started_at' => now(),
                'performed_by' => optional(auth())->id()
            ]);
            $process->save();

            $sample->status = $stage->getInProgressStatus();
            $sample->save();

            return $process;
        });
    }

    public function completeTestProcess(SampleTestProcess $process): void
    {
        $sample = $process->sample;
        $stage = TestProcessStage::from($process->stage);

        if (!$process->started_at) {
            throw ValidationException::withMessages([
                'process' => ['Tidak dapat menyelesaikan proses yang belum dimulai']
            ]);
        }

        DB::transaction(function () use ($process, $sample, $stage) {
            $process->completed_at = now();
            $process->save();

            $sample->status = $stage->getCompletedStatus();
            $sample->save();

            // If this was the last stage, create a delivery record
            if ($stage === TestProcessStage::INTERPRETATION) {
                $this->createDeliveryRecord($sample);
            } else {
                // Set the status for the next stage
                $nextStatus = $sample->status->getNextStatus();
                if ($nextStatus) {
                    $sample->status = $nextStatus;
                    $sample->save();
                }
            }
        });
    }

    protected function createDeliveryRecord(Sample $sample): void
    {
        if (!$sample->testRequest()->exists()) {
            return;
        }

        $delivery = Delivery::firstOrCreate(
            ['request_id' => $sample->test_request_id],
            [
                'status' => DeliveryStatus::PENDING,
                'delivery_date' => now()
            ]
        );
    }

    public function updateDeliveryStatus(Delivery $delivery, DeliveryStatus $newStatus): void
    {
        if (!$delivery->status->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => ['Tidak dapat mengubah status dari ' . $delivery->status->label() . ' ke ' . $newStatus->label()]
            ]);
        }

        DB::transaction(function () use ($delivery, $newStatus) {
            $delivery->status = $newStatus;
            $delivery->save();
        });
    }
}
