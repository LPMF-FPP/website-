<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\TestRequest;
use App\Services\WhatsApp\TemplateService;
use Carbon\Carbon;

class ResiCommand
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    public function execute(string $fromJid, array $params): string
    {
        if (empty($params[0])) {
            return $this->templateService->get('command', 'RESI_FORMAT_ERROR');
        }

        $receiptNumber = $params[0];

        // Find test request
        $testRequest = TestRequest::with(['investigator', 'samples.testProcesses'])
            ->where('receipt_number', $receiptNumber)
            ->first();

        if (! $testRequest) {
            return $this->templateService->render('command', 'RESI_NOT_FOUND', [
                'resi' => $receiptNumber,
            ]);
        }

        // Build tracking response
        return $this->buildTrackingResponse($testRequest);
    }

    private function buildTrackingResponse(TestRequest $testRequest): string
    {
        $milestones = $this->getMilestones($testRequest);
        $milestonesText = $this->formatMilestones($milestones);
        $currentStatus = $this->getCurrentStatusText($testRequest->status);
        $sampleCount = $testRequest->samples->count();

        return $this->templateService->render('command', 'RESI_TRACKING', [
            'resi' => $testRequest->receipt_number,
            'request_number' => $testRequest->request_number,
            'investigator' => $testRequest->investigator?->name ?? '-',
            'milestones' => $milestonesText,
            'current_status' => $currentStatus,
            'sample_count' => (string) $sampleCount,
        ]);
    }

    private function formatMilestones(array $milestones): string
    {
        $lines = [];

        foreach ($milestones as $milestone) {
            if ($milestone['completed']) {
                $icon = '✅';
            } elseif ($milestone['current'] ?? false) {
                $icon = '🟡';
            } else {
                $icon = '⚪️';
            }

            $line = "{$icon} {$milestone['label']}";

            if (! empty($milestone['detail_lines']) && is_array($milestone['detail_lines'])) {
                foreach ($milestone['detail_lines'] as $detailLine) {
                    if (! is_string($detailLine) || trim($detailLine) === '') {
                        continue;
                    }

                    $line .= "\n   ▪️ {$detailLine}";
                }
            }

            if (! empty($milestone['timestamp'])) {
                $line .= "\n   🕒 {$milestone['timestamp']}";
            }

            $lines[] = $line;
        }

        return implode("\n\n", $lines);
    }

    private function getMilestones(TestRequest $testRequest): array
    {
        $tz = settings('locale.timezone', 'Asia/Jakarta');
        $statusLevel = $this->getStatusLevel($testRequest->status);
        $stageThreeDetails = $this->buildStageThreeDetails($testRequest, $tz);

        // 1. Permintaan
        $milestones = [
            [
                'label' => '1. Permintaan',
                'completed' => $statusLevel >= 1 || $testRequest->submitted_at !== null,
                'timestamp' => $testRequest->submitted_at ?
                    Carbon::parse($testRequest->submitted_at)->timezone($tz)->format('d M Y, H:i') : null,
            ],
        ];

        // 2. Kaji Ulang Permintaan
        $milestones[] = [
            'label' => '2. Kaji Ulang Permintaan',
            'completed' => $statusLevel >= 2 || $testRequest->verified_at !== null,
            'timestamp' => $testRequest->verified_at ?
                Carbon::parse($testRequest->verified_at)->timezone($tz)->format('d M Y, H:i') : null,
        ];

        // 3. Pengujian
        $isTestingStarted = $statusLevel >= 3 || $testRequest->received_at !== null;
        $isTestingDone = $statusLevel >= 4 || $testRequest->completed_at !== null || $testRequest->status === 'completed';

        $milestones[] = [
            'label' => '3. Pengujian',
            'detail_lines' => $stageThreeDetails['lines'],
            'completed' => $isTestingDone,
            'current' => $isTestingStarted && ! $isTestingDone,
            'timestamp' => $stageThreeDetails['started_at'],
        ];

        // 4. Siap Diserahkan
        $milestones[] = [
            'label' => '4. Siap Diserahkan',
            'completed' => $statusLevel >= 4,
            'timestamp' => $testRequest->completed_at ?
                Carbon::parse($testRequest->completed_at)->timezone($tz)->format('d M Y, H:i') : null,
        ];

        // 5. Selesai
        $milestones[] = [
            'label' => '5. Selesai',
            'completed' => $statusLevel >= 5,
            'timestamp' => null,
        ];

        return $milestones;
    }

    private function getStatusLevel(string $status): int
    {
        return match ($status) {
            'draft' => 0,
            'submitted', 'pending_verification' => 1,
            'verified', 'pending_review' => 2,
            'received', 'ready_for_test', 'in_testing', 'processing', 'analysis', 'quality_check' => 3,
            'ready_for_delivery', 'completed' => 4,
            'delivered' => 5,
            default => 0,
        };
    }

    private function getCurrentStatusText(string $status): string
    {
        return match ($status) {
            'draft' => '⚪ Tahap 1 - Permintaan (Draft)',
            'submitted' => '🟡 Tahap 1 - Permintaan (Disubmit)',
            'pending_verification' => '🟡 Tahap 1 - Menunggu Verifikasi',
            'verified' => '✅ Tahap 2 - Kaji Ulang Permintaan selesai',
            'pending_review' => '🟡 Tahap 2 - Kaji Ulang sedang review',
            'ready_for_test' => '🟡 Tahap 3 dari 5 - Pengujian siap dimulai',
            'in_testing' => '🟡 Tahap 3 dari 5 - Pengujian sedang berjalan',
            'processing' => '🟡 Tahap 3 dari 5 - Pengujian dalam proses',
            'ready_for_delivery' => '🟡 Tahap 4 - Siap Diserahkan',
            'completed' => '✅ Tahap 4 - Siap Diserahkan',
            'delivered' => '✅ Tahap 5 - Selesai',
            default => 'ℹ️ Status: '.ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * @return array{lines: array<int, string>, started_at: string|null}
     */
    private function buildStageThreeDetails(TestRequest $testRequest, string $tz): array
    {
        $sampleCount = max(1, $testRequest->samples->count());
        $stageLabels = [
            'preparation' => '3.1 Preparasi sampel',
            'instrumentation' => '3.2 Pengujian pada instrumen',
            'interpretation' => '3.3 Interpretasi hasil',
        ];

        $allProcesses = $testRequest->samples
            ->flatMap(fn ($sample) => $sample->testProcesses)
            ->filter(fn ($process) => in_array((string) $process->stage, array_keys($stageLabels), true))
            ->values();

        if ($allProcesses->isEmpty()) {
            return $this->buildStageThreeDetailsFromStatus((string) $testRequest->status, $testRequest, $tz);
        }

        $lines = [];

        foreach ($stageLabels as $stageKey => $label) {
            $stageProcesses = $allProcesses->filter(fn ($process) => (string) $process->stage === $stageKey)->values();
            $completed = $stageProcesses->filter(fn ($process) => $process->completed_at !== null);
            $inProgress = $stageProcesses->filter(fn ($process) => $process->started_at !== null && $process->completed_at === null);

            if ($inProgress->isNotEmpty()) {
                $startedAt = $inProgress
                    ->sortBy(fn ($process) => $process->started_at?->getTimestamp() ?? PHP_INT_MAX)
                    ->first()?->started_at;

                $line = sprintf('%s: *🟡 Sedang berjalan* (%d/%d sampel)', $label, $inProgress->count(), $sampleCount);
                if ($startedAt !== null) {
                    $line .= ' - mulai '.$this->formatProcessTimestamp($startedAt, $tz);
                }

                $lines[] = $line;

                continue;
            }

            if ($completed->count() >= $sampleCount) {
                $completedAt = $completed
                    ->sortByDesc(fn ($process) => $process->completed_at?->getTimestamp() ?? 0)
                    ->first()?->completed_at;

                $line = sprintf('%s: ✅ Selesai (%d/%d sampel)', $label, $completed->count(), $sampleCount);
                if ($completedAt !== null) {
                    $line .= ' - selesai '.$this->formatProcessTimestamp($completedAt, $tz);
                }

                $lines[] = $line;

                continue;
            }

            if ($completed->isNotEmpty()) {
                $completedAt = $completed
                    ->sortByDesc(fn ($process) => $process->completed_at?->getTimestamp() ?? 0)
                    ->first()?->completed_at;

                $line = sprintf('%s: 🟡 Sebagian selesai (%d/%d sampel)', $label, $completed->count(), $sampleCount);
                if ($completedAt !== null) {
                    $line .= ' - update '.$this->formatProcessTimestamp($completedAt, $tz);
                }

                $lines[] = $line;

                continue;
            }

            $lines[] = $label.': ⚪️ Menunggu';
        }

        $stageThreeStartAt = $allProcesses
            ->filter(fn ($process) => $process->started_at !== null)
            ->sortBy(fn ($process) => $process->started_at?->getTimestamp() ?? PHP_INT_MAX)
            ->first()?->started_at;

        return [
            'lines' => $lines,
            'started_at' => $stageThreeStartAt
                ? 'Waktu mulai tahap 3: '.$this->formatProcessTimestamp($stageThreeStartAt, $tz)
                : null,
        ];
    }

    /**
     * @return array{lines: array<int, string>, started_at: string|null}
     */
    private function buildStageThreeDetailsFromStatus(string $status, TestRequest $testRequest, string $tz): array
    {
        $mapping = match ($status) {
            'ready_for_test' => [
                '3.1 Preparasi sampel: *🟡 Sedang berjalan*',
                '3.2 Pengujian pada instrumen: ⚪️ Menunggu',
                '3.3 Interpretasi hasil: ⚪️ Menunggu',
            ],
            'in_testing', 'processing' => [
                '3.1 Preparasi sampel: ✅ Selesai',
                '3.2 Pengujian pada instrumen: *🟡 Sedang berjalan*',
                '3.3 Interpretasi hasil: ⚪️ Menunggu',
            ],
            'analysis', 'quality_check' => [
                '3.1 Preparasi sampel: ✅ Selesai',
                '3.2 Pengujian pada instrumen: ✅ Selesai',
                '3.3 Interpretasi hasil: *🟡 Sedang berjalan*',
            ],
            'ready_for_delivery', 'completed', 'delivered' => [
                '3.1 Preparasi sampel: ✅ Selesai',
                '3.2 Pengujian pada instrumen: ✅ Selesai',
                '3.3 Interpretasi hasil: ✅ Selesai',
            ],
            default => [
                '3.1 Preparasi sampel: ⚪️ Menunggu',
                '3.2 Pengujian pada instrumen: ⚪️ Menunggu',
                '3.3 Interpretasi hasil: ⚪️ Menunggu',
            ],
        };

        return [
            'lines' => $mapping,
            'started_at' => $testRequest->received_at
                ? 'Waktu mulai tahap 3: '.$this->formatProcessTimestamp($testRequest->received_at, $tz)
                : null,
        ];
    }

    private function formatProcessTimestamp(
        \DateTimeInterface $timestamp,
        string $tz
    ): string {
        return Carbon::parse($timestamp)->timezone($tz)->format('d M Y, H:i');
    }
}
