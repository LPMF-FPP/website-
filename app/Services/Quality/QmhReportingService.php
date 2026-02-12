<?php

namespace App\Services\Quality;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class QmhReportingService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function revisionHistory(array $filters): LengthAwarePaginator
    {
        return $this->revisionHistoryQuery($filters)
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function downloadHistory(array $filters, bool $controlledOnly = false): LengthAwarePaginator
    {
        return $this->downloadHistoryQuery($filters, $controlledOnly)
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{filename: string, content: string}
     */
    public function exportRevisionHistoryCsv(array $filters, string $timezone = 'Asia/Jakarta'): array
    {
        $rows = $this->revisionHistoryQuery($filters)->get();

        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, ['occurred_at', 'actor_name', 'document_code', 'document_title', 'version_label', 'status_transition', 'reason']);

        foreach ($rows as $row) {
            fputcsv($stream, [
                Carbon::parse((string) $row->occurred_at)->setTimezone($timezone)->format('Y-m-d H:i:s'),
                $row->actor_name,
                $row->document_code,
                $row->document_title,
                $row->version_label,
                $row->status_transition,
                $row->reason,
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream) ?: '';
        fclose($stream);

        return [
            'filename' => 'qmh-revision-history-'.now()->format('Ymd-His').'.csv',
            'content' => $content,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{filename: string, content: string}
     */
    public function exportDownloadHistoryCsv(array $filters, string $timezone = 'Asia/Jakarta', bool $controlledOnly = false): array
    {
        $rows = $this->downloadHistoryQuery($filters, $controlledOnly)->get();

        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, ['occurred_at', 'actor_name', 'document_code', 'document_title', 'version_label', 'copy_type', 'distribution_target', 'reason']);

        foreach ($rows as $row) {
            fputcsv($stream, [
                Carbon::parse((string) $row->occurred_at)->setTimezone($timezone)->format('Y-m-d H:i:s'),
                $row->actor_name,
                $row->document_code,
                $row->document_title,
                $row->version_label,
                $row->copy_type,
                $row->distribution_target,
                $row->reason,
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream) ?: '';
        fclose($stream);

        return [
            'filename' => ($controlledOnly ? 'qmh-controlled-distribution-' : 'qmh-download-history-').now()->format('Ymd-His').'.csv',
            'content' => $content,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function revisionHistoryQuery(array $filters)
    {
        $query = DB::table('qmh_workflow_events as events')
            ->join('qmh_document_revisions as revisions', 'revisions.id', '=', 'events.revision_id')
            ->join('qmh_documents as documents', 'documents.id', '=', 'revisions.document_id')
            ->leftJoin('users as actors', 'actors.id', '=', 'events.actor_id')
            ->whereIn('events.event_type', [
                'create_draft',
                'submit_review',
                'review_return',
                'review_pass',
                'approve',
                'reject',
                'publish',
            ])
            ->selectRaw("events.actor_id, actors.name as actor_name, documents.doc_code as document_code, documents.title as document_title, revisions.version_label, COALESCE(events.payload_json->>'status_transition', events.event_type) as status_transition, events.payload_json->>'reason' as reason, events.created_at as occurred_at")
            ->orderByDesc('events.created_at');

        $this->applyCommonFilters($query, $filters, 'events.created_at');

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function downloadHistoryQuery(array $filters, bool $controlledOnly = false)
    {
        $query = DB::table('qmh_document_download_logs as logs')
            ->join('qmh_documents as documents', 'documents.id', '=', 'logs.document_id')
            ->leftJoin('users as actors', 'actors.id', '=', 'logs.downloaded_by')
            ->selectRaw("logs.downloaded_by as actor_id, actors.name as actor_name, documents.doc_code as document_code, documents.title as document_title, CONCAT('E', logs.edition_number, '-R', logs.revision_number) as version_label, logs.copy_type, logs.reason, logs.distribution_target, logs.downloaded_at as occurred_at")
            ->orderByDesc('logs.downloaded_at');

        if ($controlledOnly) {
            $query->where('logs.copy_type', 'controlled');
        }

        $this->applyCommonFilters($query, $filters, 'logs.downloaded_at');

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyCommonFilters($query, array $filters, string $timestampColumn): void
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $documentId = isset($filters['document_id']) ? (int) $filters['document_id'] : null;
        $clause = isset($filters['clause']) ? (int) $filters['clause'] : null;
        $docType = isset($filters['doc_type']) ? (string) $filters['doc_type'] : null;
        $actorId = isset($filters['actor_id']) ? (int) $filters['actor_id'] : null;
        $from = isset($filters['from']) ? Carbon::parse((string) $filters['from'])->startOfDay() : null;
        $to = isset($filters['to']) ? Carbon::parse((string) $filters['to'])->endOfDay() : null;

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('documents.doc_code', 'like', '%'.$search.'%')
                    ->orWhere('documents.title', 'like', '%'.$search.'%');
            });
        }

        if ($documentId !== null) {
            $query->where('documents.id', $documentId);
        }

        if ($clause !== null) {
            $query->where('documents.clause', $clause);
        }

        if ($docType !== null && $docType !== '') {
            $query->where('documents.doc_type', $docType);
        }

        if ($actorId !== null) {
            if (str_starts_with($timestampColumn, 'events')) {
                $query->where('events.actor_id', $actorId);
            } else {
                $query->where('logs.downloaded_by', $actorId);
            }
        }

        if ($from !== null) {
            $query->where($timestampColumn, '>=', $from);
        }

        if ($to !== null) {
            $query->where($timestampColumn, '<=', $to);
        }
    }
}
