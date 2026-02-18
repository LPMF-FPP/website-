<?php

namespace App\Console\Commands;

use App\Models\QmhDocument;
use App\Models\QmhTemplate;
use App\Models\QmhWorkflowEvent;
use App\Services\Quality\QmhDashboardSummaryService;
use Illuminate\Console\Command;

class QmhSmokeReadonlyCommand extends Command
{
    protected $signature = 'qmh:smoke:readonly {--json : Output result as JSON}';

    protected $description = 'Run read-only QMH smoke checks without writing data';

    public function handle(QmhDashboardSummaryService $summaryService): int
    {
        $startedAt = microtime(true);

        try {
            $summary = $summaryService->summarize([]);
            $activeTemplates = QmhTemplate::query()->where('is_active', true)->count();
            $orphanCurrentRevisionLinks = QmhDocument::query()
                ->whereNotNull('current_revision_id')
                ->whereDoesntHave('currentRevision')
                ->count();

            $latestDocument = QmhDocument::query()
                ->select(['id', 'doc_code', 'title', 'doc_type', 'clause', 'current_revision_id', 'updated_at'])
                ->with([
                    'currentRevision:id,document_id,status,version_label,updated_at',
                ])
                ->latest('updated_at')
                ->first();

            $latestEvent = QmhWorkflowEvent::query()
                ->select(['id', 'revision_id', 'event_type', 'created_at'])
                ->latest('id')
                ->first();

            $result = [
                'ok' => $orphanCurrentRevisionLinks === 0,
                'executed_at' => now()->toIso8601String(),
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'summary' => $summary,
                'active_templates' => $activeTemplates,
                'orphan_current_revision_links' => $orphanCurrentRevisionLinks,
                'latest_document' => $latestDocument === null ? null : [
                    'id' => $latestDocument->id,
                    'doc_code' => $latestDocument->doc_code,
                    'title' => $latestDocument->title,
                    'doc_type' => $latestDocument->doc_type,
                    'clause' => $latestDocument->clause,
                    'revision_status' => $latestDocument->currentRevision?->status,
                    'revision_label' => $latestDocument->currentRevision?->version_label,
                    'updated_at' => optional($latestDocument->updated_at)->toIso8601String(),
                ],
                'latest_workflow_event' => $latestEvent === null ? null : [
                    'id' => $latestEvent->id,
                    'revision_id' => $latestEvent->revision_id,
                    'event_type' => $latestEvent->event_type,
                    'created_at' => optional($latestEvent->created_at)->toIso8601String(),
                ],
            ];

            if ((bool) $this->option('json')) {
                $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->info('QMH read-only smoke selesai.');
                $this->line('Total dokumen: '.(string) ($summary['total_documents'] ?? 0));
                $this->line('Dokumen published: '.(string) ($summary['published_documents'] ?? 0));
                $this->line('Dokumen in-review: '.(string) ($summary['in_review_documents'] ?? 0));
                $this->line('Template aktif: '.$activeTemplates);
                $this->line('Orphan current_revision link: '.$orphanCurrentRevisionLinks);

                if ($latestDocument !== null) {
                    $this->line('Dokumen terbaru: '.$latestDocument->doc_code.' - '.$latestDocument->title);
                }

                if ($latestEvent !== null) {
                    $this->line('Event workflow terbaru: '.$latestEvent->event_type.' (revision '.$latestEvent->revision_id.')');
                }
            }

            if ($orphanCurrentRevisionLinks > 0) {
                $this->warn('Terdeteksi current_revision_id yang tidak valid.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('QMH read-only smoke gagal: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
