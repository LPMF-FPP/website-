<?php

namespace App\Services\Quality;

use App\Models\QmhDocument;
use App\Models\QmhDocumentDownloadLog;
use App\Models\QmhDocumentRevision;
use Carbon\Carbon;

class QmhDashboardSummaryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function summarize(array $filters): array
    {
        $clause = isset($filters['clause']) ? (int) $filters['clause'] : null;
        $docType = isset($filters['doc_type']) ? (string) $filters['doc_type'] : null;
        $from = isset($filters['from']) ? Carbon::parse((string) $filters['from'])->startOfDay() : null;
        $to = isset($filters['to']) ? Carbon::parse((string) $filters['to'])->endOfDay() : null;

        $documentsQuery = QmhDocument::query()
            ->when($clause !== null, fn ($query) => $query->where('clause', $clause))
            ->when($docType !== null && $docType !== '', fn ($query) => $query->where('doc_type', $docType))
            ->when($from !== null, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->where('created_at', '<=', $to));

        $totalDocuments = (clone $documentsQuery)->count();

        $publishedDocuments = (clone $documentsQuery)
            ->whereHas('currentRevision', fn ($query) => $query->where('status', 'published'))
            ->count();

        $inReviewDocuments = (clone $documentsQuery)
            ->whereHas('currentRevision', fn ($query) => $query->where('status', 'in_review'))
            ->count();

        $obsoleteRevisionsQuery = QmhDocumentRevision::query()
            ->where('status', 'obsolete')
            ->whereHas('document', function ($query) use ($clause, $docType): void {
                if ($clause !== null) {
                    $query->where('clause', $clause);
                }

                if ($docType !== null && $docType !== '') {
                    $query->where('doc_type', $docType);
                }
            })
            ->when($from !== null, fn ($query) => $query->whereNotNull('obsolete_at')->where('obsolete_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereNotNull('obsolete_at')->where('obsolete_at', '<=', $to));

        $obsoleteRevisions = (clone $obsoleteRevisionsQuery)->count();

        $downloadLogsQuery = QmhDocumentDownloadLog::query()
            ->whereHas('document', function ($query) use ($clause, $docType): void {
                if ($clause !== null) {
                    $query->where('clause', $clause);
                }

                if ($docType !== null && $docType !== '') {
                    $query->where('doc_type', $docType);
                }
            })
            ->when($from !== null, fn ($query) => $query->where('downloaded_at', '>=', $from))
            ->when($to !== null, fn ($query) => $query->where('downloaded_at', '<=', $to));

        $controlledDownloads = (clone $downloadLogsQuery)
            ->where('copy_type', 'controlled')
            ->count();

        $uncontrolledDownloads = (clone $downloadLogsQuery)
            ->where('copy_type', 'uncontrolled')
            ->count();

        return [
            'total_documents' => $totalDocuments,
            'published_documents' => $publishedDocuments,
            'in_review_documents' => $inReviewDocuments,
            'obsolete_revisions' => $obsoleteRevisions,
            'controlled_downloads' => $controlledDownloads,
            'uncontrolled_downloads' => $uncontrolledDownloads,
        ];
    }
}
