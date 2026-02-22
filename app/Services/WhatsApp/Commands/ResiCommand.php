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
        $testRequest = TestRequest::with(['investigator', 'samples'])
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
            'detail_lines' => [
                '3.1 Preparasi sampel',
                '3.2 Pengujian pada instrumen',
                '3.3 Interpretasi hasil',
            ],
            'completed' => $isTestingDone,
            'current' => $isTestingStarted && ! $isTestingDone,
            'timestamp' => $testRequest->received_at ?
                Carbon::parse($testRequest->received_at)->timezone($tz)->format('d M Y, H:i') : null,
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
}
