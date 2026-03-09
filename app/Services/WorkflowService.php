<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\TestProcessStage;
use App\Models\Delivery;
use App\Models\Sample;
use App\Models\SampleTestProcess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function __construct(
        protected ?InstrumentLoggingService $instrumentLoggingService = null
    ) {
        $this->instrumentLoggingService = $instrumentLoggingService ?? new InstrumentLoggingService;
    }

    public function startTestProcess(Sample $sample, TestProcessStage $stage): SampleTestProcess
    {
        if ($this->sampleStatusValue($sample->status) !== $stage->getRequiredStatus()->value) {
            throw ValidationException::withMessages([
                'status' => ['Sample belum siap untuk memulai tahap '.$stage->label()],
            ]);
        }

        return DB::transaction(function () use ($sample, $stage) {
            $process = new SampleTestProcess([
                'sample_id' => $sample->id,
                'stage' => $stage->value,
                'started_at' => now(),
                'performed_by' => optional(auth())->id(),
            ]);
            $process->save();

            $sample->status = $stage->getInProgressStatus()->value;
            $sample->save();

            return $process;
        });
    }

    public function completeTestProcess(SampleTestProcess $process): ?SampleTestProcess
    {
        $sample = $process->sample;
        $stage = $process->stage instanceof TestProcessStage
            ? $process->stage
            : TestProcessStage::from((string) $process->stage);

        if (! $process->started_at) {
            throw ValidationException::withMessages([
                'process' => ['Tidak dapat menyelesaikan proses yang belum dimulai'],
            ]);
        }

        $this->validateStageGates($sample, $stage);

        return DB::transaction(function () use ($process, $sample, $stage): ?SampleTestProcess {
            $process->completed_at = now();
            $process->save();

            $sample->status = $stage->getCompletedStatus()->value;
            $sample->save();

            $nextProcess = null;

            // If this was the last stage, create a delivery record
            if ($stage === TestProcessStage::INTERPRETATION) {
                $this->createDeliveryRecord($sample);
            } else {
                // Set the status for the next stage
                $nextStatus = $stage->getCompletedStatus()->getNextStatus();
                if ($nextStatus) {
                    $sample->status = $nextStatus->value;
                    $sample->save();
                }

                $nextStage = match ($stage) {
                    TestProcessStage::PREPARATION => TestProcessStage::INSTRUMENTATION,
                    TestProcessStage::INSTRUMENTATION => TestProcessStage::INTERPRETATION,
                    default => null,
                };

                if ($nextStage) {
                    $nextProcess = SampleTestProcess::firstOrCreate(
                        [
                            'sample_id' => $sample->id,
                            'stage' => $nextStage->value,
                        ],
                        [
                            'performed_by' => $process->performed_by ?? Auth::id(),
                        ]
                    );
                }
            }

            return $nextProcess;
        });
    }

    public function startExistingProcess(SampleTestProcess $process): void
    {
        if ($process->started_at !== null) {
            throw ValidationException::withMessages([
                'process' => ['Proses sudah dimulai sebelumnya.'],
            ]);
        }

        $sample = $process->sample;
        $stage = $process->stage instanceof TestProcessStage ? $process->stage : TestProcessStage::from((string) $process->stage);

        $this->validateGuidedTransition($sample, $stage);

        DB::transaction(function () use ($process, $sample, $stage): void {
            $process->started_at = now();
            if ($process->performed_by === null) {
                $process->performed_by = Auth::id();
            }
            $process->save();

            $sample->status = $stage->getInProgressStatus()->value;
            $sample->save();
        });
    }

    public function unlockCompletedProcess(SampleTestProcess $process, string $reason): void
    {
        if ($process->completed_at === null) {
            throw ValidationException::withMessages([
                'process' => ['Hanya proses yang sudah selesai yang dapat dibuka kembali.'],
            ]);
        }

        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages([
                'reason' => ['Alasan minimal 10 karakter.'],
            ]);
        }

        $sample = $process->sample;
        $stage = $process->stage instanceof TestProcessStage ? $process->stage : TestProcessStage::from((string) $process->stage);

        $hasNextStageProgress = $this->hasNextStageProgress($sample, $stage);
        if ($hasNextStageProgress) {
            throw ValidationException::withMessages([
                'process' => ['Tidak dapat unlock karena tahap setelahnya sudah dimulai atau selesai.'],
            ]);
        }

        DB::transaction(function () use ($process, $sample, $stage): void {
            $process->completed_at = null;
            $process->save();

            $sample->status = ($process->started_at !== null)
                ? $stage->getInProgressStatus()->value
                : $stage->getRequiredStatus()->value;
            $sample->save();

            if ($stage === TestProcessStage::INTERPRETATION && $sample->testRequest) {
                $testRequest = $sample->testRequest;
                if (in_array($testRequest->status, ['ready_for_delivery', 'completed'], true)) {
                    $testRequest->status = 'in_testing';
                    $testRequest->ready_for_delivery_at = null;
                    $testRequest->save();
                }
            }
        });
    }

    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public function canStartProcess(SampleTestProcess $process): array
    {
        if ($process->started_at !== null) {
            return ['allowed' => false, 'reason' => 'Tahap ini sudah dimulai.'];
        }

        $sample = $process->sample;
        $stage = $process->stage instanceof TestProcessStage ? $process->stage : TestProcessStage::from((string) $process->stage);

        try {
            $this->validateGuidedTransition($sample, $stage);

            return ['allowed' => true, 'reason' => null];
        } catch (ValidationException $e) {
            return ['allowed' => false, 'reason' => $this->firstValidationMessage($e)];
        }
    }

    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public function canCompleteProcess(SampleTestProcess $process): array
    {
        if ($process->started_at === null) {
            return ['allowed' => false, 'reason' => 'Tahap belum dimulai.'];
        }

        if ($process->completed_at !== null) {
            return ['allowed' => false, 'reason' => 'Tahap ini sudah selesai.'];
        }

        $sample = $process->sample;
        $stage = $process->stage instanceof TestProcessStage ? $process->stage : TestProcessStage::from((string) $process->stage);

        try {
            $this->validateStageGates($sample, $stage);

            return ['allowed' => true, 'reason' => null];
        } catch (ValidationException $e) {
            return ['allowed' => false, 'reason' => $this->firstValidationMessage($e)];
        }
    }

    /**
     * @return array{allowed: bool, reason: string|null}
     */
    public function canUnlockProcess(SampleTestProcess $process): array
    {
        if ($process->completed_at === null) {
            return ['allowed' => false, 'reason' => 'Hanya tahap selesai yang dapat diperbaiki.'];
        }

        $sample = $process->sample;
        $stage = $process->stage instanceof TestProcessStage ? $process->stage : TestProcessStage::from((string) $process->stage);

        if ($this->hasNextStageProgress($sample, $stage)) {
            return ['allowed' => false, 'reason' => 'Tahap berikutnya sudah berjalan atau selesai.'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    protected function createDeliveryRecord(Sample $sample): void
    {
        if (! $sample->testRequest()->exists()) {
            return;
        }

        $delivery = Delivery::firstOrCreate(
            ['request_id' => $sample->test_request_id],
            [
                'delivered_by' => Auth::id() ?? $sample->testRequest?->user_id,
                'status' => DeliveryStatus::PENDING,
                'delivery_date' => now(),
            ]
        );
    }

    public function updateDeliveryStatus(Delivery $delivery, DeliveryStatus $newStatus): void
    {
        if (! $delivery->status->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => ['Tidak dapat mengubah status dari '.$delivery->status->label().' ke '.$newStatus->label()],
            ]);
        }

        DB::transaction(function () use ($delivery, $newStatus) {
            $delivery->status = $newStatus;
            $delivery->save();
        });
    }

    protected function validateStageGates(Sample $sample, TestProcessStage $stage): void
    {
        $this->validateGuidedTransition($sample, $stage);

        if ($stage === TestProcessStage::PREPARATION) {
            $this->validatePreparationGate($sample);
        }

        if ($stage === TestProcessStage::INSTRUMENTATION) {
            $this->validateInstrumentationGate($sample);
        }
    }

    protected function validatePreparationGate(Sample $sample): void
    {
        if ($this->instrumentLoggingService->requiresWeighing($sample)) {
            if (! $this->instrumentLoggingService->hasCompletedWeighing($sample)) {
                throw ValidationException::withMessages([
                    'weighing' => ['Sample membutuhkan penimbangan (Analytical Balance). Silakan isi data penimbangan sebelum menyelesaikan preparasi.'],
                ]);
            }
        }
    }

    protected function validateInstrumentationGate(Sample $sample): void
    {
        if (! $this->instrumentLoggingService->isEnabled()) {
            return;
        }

        if (! $this->instrumentLoggingService->hasCompletedAllRequirementsForSample($sample)) {
            $missing = $this->instrumentLoggingService->getMissingRequirements($sample);
            $missingNames = collect($missing)->pluck('instrument_name')->join(', ');

            throw ValidationException::withMessages([
                'instrument_usage' => ["Instrumen berikut wajib dicatat sebelum menyelesaikan tahap instrumentasi: {$missingNames}"],
            ]);
        }
    }

    protected function validateGuidedTransition(Sample $sample, TestProcessStage $stage): void
    {
        if ($stage === TestProcessStage::PREPARATION) {
            return;
        }

        $requiredPreviousStage = match ($stage) {
            TestProcessStage::INSTRUMENTATION => TestProcessStage::PREPARATION,
            TestProcessStage::INTERPRETATION => TestProcessStage::INSTRUMENTATION,
            default => null,
        };

        if (! $requiredPreviousStage) {
            return;
        }

        $prevProcess = $sample->testProcesses()
            ->where('stage', $requiredPreviousStage->value)
            ->first();

        if (! $prevProcess || ! $prevProcess->completed_at) {
            throw ValidationException::withMessages([
                'process' => ["Tahap {$stage->label()} belum dapat dimulai. Selesaikan {$requiredPreviousStage->label()} terlebih dahulu."],
            ]);
        }
    }

    protected function hasNextStageProgress(Sample $sample, TestProcessStage $stage): bool
    {
        $nextStages = match ($stage) {
            TestProcessStage::PREPARATION => [TestProcessStage::INSTRUMENTATION->value, TestProcessStage::INTERPRETATION->value],
            TestProcessStage::INSTRUMENTATION => [TestProcessStage::INTERPRETATION->value],
            default => [],
        };

        if (empty($nextStages)) {
            return false;
        }

        return $sample->testProcesses()
            ->whereIn('stage', $nextStages)
            ->where(function ($query): void {
                $query->whereNotNull('started_at')
                    ->orWhereNotNull('completed_at');
            })
            ->exists();
    }

    private function sampleStatusValue(mixed $status): string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        return (string) $status;
    }

    private function firstValidationMessage(ValidationException $e): string
    {
        $errors = $e->errors();
        foreach ($errors as $messages) {
            if (is_array($messages) && isset($messages[0]) && is_string($messages[0])) {
                return $messages[0];
            }
        }

        return $e->getMessage();
    }
}
