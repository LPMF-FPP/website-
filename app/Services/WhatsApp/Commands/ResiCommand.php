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
                $statusText = '';
            } elseif ($milestone['current'] ?? false) {
                $icon = '▶️';
                $statusText = ' (PROSES)';
            } else {
                $icon = '⚪';
                $statusText = '';
            }

            $line = "{$icon} *{$milestone['label']}*{$statusText}";

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

        $substeps = $isTestingStarted
            ? "\n      a. Preparasi sampel\n      b. Pengujian pada instrumen\n      c. Interpretasi hasil"
            : '';

        $milestones[] = [
            'label' => '3. Pengujian'.$substeps,
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
            'draft' => '1. Permintaan (Draft)',
            'submitted' => '1. Permintaan (Disubmit)',
            'pending_verification' => '1. Permintaan (Menunggu Verifikasi)',
            'verified' => '2. Kaji Ulang Permintaan (Selesai)',
            'pending_review' => '2. Kaji Ulang Permintaan (Sedang Review)',
            'ready_for_test' => '3. Pengujian (Siap)',
            'in_testing' => '3. Pengujian (Sedang Berjalan)',
            'processing' => '3. Pengujian (Proses)',
            'ready_for_delivery' => '4. Siap Diserahkan',
            'completed' => '4. Siap Diserahkan',
            'delivered' => '5. Selesai',
            default => 'Status: '.ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
