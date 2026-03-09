<?php

namespace App\Services\WhatsApp\Commands;

use App\Models\Sample;
use App\Models\TestRequest;
use App\Services\WhatsApp\TemplateService;
use Illuminate\Support\Facades\DB;

class StatusRequestCommand
{
    public function __construct(
        private TemplateService $templateService
    ) {}

    public function execute(string $fromJid, array $params): string
    {
        $reviewStatuses = ['submitted', 'pending_verification', 'verified', 'pending_review'];
        $testingStatuses = ['ready_for_test', 'in_testing', 'processing'];
        $readyStatuses = ['ready_for_delivery'];
        $completedStatuses = ['completed', 'delivered'];

        $trackedStatuses = array_merge($reviewStatuses, $testingStatuses, $readyStatuses, $completedStatuses);

        $requests = TestRequest::query()
            ->select('status', DB::raw('count(*) as total'))
            ->whereIn('status', $trackedStatuses)
            ->groupBy('status')
            ->pluck('total', 'status');

        $reviewTotal = $this->sumStatuses($requests, $reviewStatuses);
        $testingTotal = $this->sumStatuses($requests, $testingStatuses);
        $readyTotal = $this->sumStatuses($requests, $readyStatuses);
        $completedTotal = $this->sumStatuses($requests, $completedStatuses);
        $activeTotal = $reviewTotal + $testingTotal + $readyTotal;
        $requestTotal = TestRequest::query()->count();

        $sampleTotal = Sample::query()
            ->whereHas('testRequest', function ($query) use ($reviewStatuses, $testingStatuses, $readyStatuses) {
                $query->whereIn('status', array_merge($reviewStatuses, $testingStatuses, $readyStatuses));
            })
            ->count();

        $sampleGrandTotal = Sample::query()->count();

        return $this->templateService->render('command', 'STATUS_REPORT', [
            'request_total' => (string) $requestTotal,
            'sample_grand_total' => (string) $sampleGrandTotal,
            'active_total' => (string) $activeTotal,
            'review_total' => (string) $reviewTotal,
            'testing_total' => (string) $testingTotal,
            'ready_total' => (string) $readyTotal,
            'sample_total' => (string) $sampleTotal,
            'completed_total' => (string) $completedTotal,
            'timestamp' => now()->format('d M Y H:i'),
            'nomor_resi' => 'LPMF/001/2026',
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int|string, int|string>  $requests
     * @param  array<int, string>  $statuses
     */
    private function sumStatuses($requests, array $statuses): int
    {
        return collect($statuses)
            ->sum(fn (string $status): int => (int) ($requests[$status] ?? 0));
    }
}
