<?php

namespace App\Services\Search;

use App\Models\Investigator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SearchService
{
    // Document type mappings
    private const DOC_TYPE_LABELS = [
        'sample_photo' => 'Foto Sampel',
        'ba_penerimaan' => 'Berita Acara Penerimaan Sampel',
        'ba_penyerahan' => 'Berita Acara Penyerahan Sampel',
        'request_letter' => 'Surat Permintaan Pengujian',
    ];

    public function search(array $params): array
    {
        $qRaw = $params['q'];
        $qEsc = $params['q_escaped'] ?? $qRaw;
        $docType = $params['doc_type'] ?? 'all';
        $sort = $params['sort'] ?? 'relevance';

        $pagePeople = (int) ($params['page_people'] ?? 1);
        $ppPeople = (int) ($params['per_page_people'] ?? 10);

        $pageDocs = (int) ($params['page_docs'] ?? 1);
        $ppDocs = (int) ($params['per_page_docs'] ?? 10);

        $contains = '%' . $qEsc . '%';
        $startsWith = $qEsc . '%';

        // Search investigators and test requests (people-like results)
        $peoplePaginator = $this->searchInvestigatorsAndRequests(
            $qRaw,
            $contains,
            $startsWith,
            $sort,
            $ppPeople,
            $pagePeople
        );

        // Search documents
        $docPaginator = $this->searchDocumentsWithDeliveries(
            $qRaw,
            $contains,
            $startsWith,
            $docType,
            $sort,
            $ppDocs,
            $pageDocs
        );

        $peopleItems = collect($peoplePaginator->items());
        $docItems = collect($docPaginator->items());

        return [
            'query' => $qRaw,
            'doc_type' => $docType,
            'sort' => $sort,
            'summary' => [
                'people_total' => $peoplePaginator->total(),
                'documents_total' => $docPaginator->total(),
            ],
            'people' => [
                'pagination' => [
                    'page' => $peoplePaginator->currentPage(),
                    'per_page' => $peoplePaginator->perPage(),
                    'total' => $peoplePaginator->total(),
                    'last_page' => $peoplePaginator->lastPage(),
                ],
                'data' => $peopleItems,
            ],
            'documents' => [
                'pagination' => [
                    'page' => $docPaginator->currentPage(),
                    'per_page' => $docPaginator->perPage(),
                    'total' => $docPaginator->total(),
                    'last_page' => $docPaginator->lastPage(),
                ],
                'data' => $docItems,
            ],
        ];
    }

    /**
     * Search investigators by name and test requests by suspect name
     */
    private function searchInvestigatorsAndRequests(
        string $qRaw,
        string $contains,
        string $startsWith,
        string $sort,
        int $perPage,
        int $page
    ): LengthAwarePaginator {
        $casesLimit = (int) config('search.people_cases_limit', 5);

        $investigators = Investigator::query()
            ->select('id', 'name', 'rank', 'created_at')
            ->selectRaw(
                "CASE
                    WHEN lower(name) = lower(?) THEN 100
                    WHEN name ILIKE ? THEN 80
                    ELSE 50
                 END AS relevance",
                [$qRaw, $startsWith]
            )
            ->whereRaw("name ILIKE ?", [$contains])
            ->with(['testRequests' => function ($query) use ($casesLimit) {
                $query->select('id', 'investigator_id', 'request_number', 'suspect_name', 'submitted_at')
                    ->latest('submitted_at')
                    ->limit($casesLimit);
            }])
            ->orderByDesc('relevance')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Investigator $inv) {
                $requests = $inv->testRequests->map(fn($tr) => [
                    'id' => $tr->id,
                    'request_number' => $tr->request_number,
                    'suspect_name' => $tr->suspect_name,
                ])->toArray();

                return (object) [
                    'id' => $inv->id,
                    'type' => 'investigator',
                    'name' => $inv->name,
                    'rank' => $inv->rank ?? '',
                    'role_label' => 'Penyidik',
                    'subtitle' => $inv->rank ? "Penyidik • {$inv->rank}" : 'Penyidik',
                    'detail_url' => null, // Can be added if investigator detail page exists
                    'created_at' => $inv->created_at?->toIso8601String() ?? '',
                    'test_requests' => $requests,
                    'relevance' => (int) ($inv->relevance ?? 0),
                ];
            })
            ->values()
            ->all(); // Convert to array

        $testRequests = DB::table('test_requests')
            ->leftJoin('investigators', 'investigators.id', '=', 'test_requests.investigator_id')
            ->select(
                'test_requests.id',
                'test_requests.request_number',
                'test_requests.suspect_name',
                'test_requests.submitted_at',
                'test_requests.created_at',
                'investigators.name as investigator_name'
            )
            ->selectRaw(
                "CASE
                    WHEN lower(test_requests.suspect_name) = lower(?) THEN 90
                    WHEN test_requests.suspect_name ILIKE ? THEN 80
                    WHEN test_requests.request_number ILIKE ? THEN 75
                    WHEN investigators.name ILIKE ? THEN 65
                    ELSE 40
                 END AS relevance",
                [$qRaw, $contains, $contains, $contains]
            )
            ->where(function (Builder $q) use ($contains) {
                $q->whereRaw("test_requests.suspect_name ILIKE ?", [$contains])
                    ->orWhereRaw("test_requests.request_number ILIKE ?", [$contains])
                    ->orWhereRaw("investigators.name ILIKE ?", [$contains]);
            })
            ->orderByDesc('relevance')
            ->orderByDesc('test_requests.submitted_at')
            ->get()
            ->map(function ($tr) {
                $timestamp = $tr->submitted_at ?? $tr->created_at;
                $createdAt = $timestamp ? Carbon::parse($timestamp) : null;

                return (object) [
                    'id' => $tr->id,
                    'type' => 'test_request',
                    'name' => $tr->suspect_name ?? 'Tersangka (Tanpa Nama)',
                    'role_label' => 'Tersangka',
                    'request_number' => $tr->request_number ?? '',
                    'subtitle' => $tr->request_number
                        ? "Permintaan Pengujian #{$tr->request_number}"
                        : 'Permintaan Pengujian',
                    'investigator' => $tr->investigator_name ?? 'Tanpa Penyidik',
                    'detail_url' => null, // Can be added if request detail page exists
                    'created_at' => $createdAt?->toIso8601String() ?? '',
                    'relevance' => (int) ($tr->relevance ?? 0),
                ];
            })
            ->values()
            ->all(); // Convert to array

        // Merge both arrays
        $combined = array_merge($investigators, $testRequests);
        $total = count($combined);
        $startIndex = ($page - 1) * $perPage;
        $items = array_slice($combined, $startIndex, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    /**
     * Search documents including database documents and delivery handovers
     */
    private function searchDocumentsWithDeliveries(
        string $qRaw,
        string $contains,
        string $startsWith,
        string $docType,
        string $sort,
        int $perPage,
        int $page
    ): LengthAwarePaginator {
        $documents = [];
        $normalizedQuery = mb_strtolower($qRaw);
        $documentTypeMatches = [];

        if ($normalizedQuery !== '') {
            $documentTypeMatches = collect(self::DOC_TYPE_LABELS)
                ->filter(fn($label, $key) => str_contains(mb_strtolower($label), $normalizedQuery))
                ->keys()
                ->values()
                ->all();
        }

        $dbDocs = DB::table('documents')
            ->leftJoin('test_requests', 'test_requests.id', '=', 'documents.test_request_id')
            ->leftJoin('investigators', 'investigators.id', '=', 'documents.investigator_id')
            ->whereNull('documents.deleted_at')
            ->select(
                'documents.id',
                'documents.document_type',
                'documents.original_filename',
                'documents.created_at',
                'test_requests.request_number',
                'test_requests.suspect_name',
                'investigators.name as investigator_name'
            )
            ->selectRaw(
                "CASE
                    WHEN lower(test_requests.suspect_name) = lower(?) THEN 100
                    WHEN test_requests.suspect_name ILIKE ? THEN 90
                    WHEN test_requests.request_number ILIKE ? THEN 85
                    WHEN documents.original_filename ILIKE ? THEN 70
                    WHEN investigators.name ILIKE ? THEN 65
                    WHEN documents.document_type = ? THEN 60
                    ELSE 40
                 END AS relevance",
                [$qRaw, $contains, $contains, $startsWith, $contains, $docType]
            )
            ->where(function (Builder $q) use ($contains, $documentTypeMatches) {
                $q->whereRaw("documents.original_filename ILIKE ?", [$contains])
                    ->orWhereRaw("documents.document_type ILIKE ?", [$contains])
                    ->orWhereRaw("test_requests.suspect_name ILIKE ?", [$contains])
                    ->orWhereRaw("test_requests.request_number ILIKE ?", [$contains])
                    ->orWhereRaw("investigators.name ILIKE ?", [$contains]);

                if (!empty($documentTypeMatches)) {
                    $q->orWhereIn('documents.document_type', $documentTypeMatches);
                }
            })
            ->when($docType !== 'all', fn($q) => $q->where('documents.document_type', $docType))
            ->orderByDesc('relevance')
            ->orderByDesc('documents.created_at')
            ->get();

        foreach ($dbDocs as $doc) {
            $createdAt = $doc->created_at ? Carbon::parse($doc->created_at) : null;

            // Generate signed URLs for document access
            $downloadUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'investigator.documents.download',
                now()->addMinutes(10),
                ['document' => $doc->id]
            );

            $previewUrl = route('investigator.documents.show', ['document' => $doc->id]);

            $documents[] = (object) [
                'id' => 'doc_' . $doc->id,
                'type' => 'document',
                'document_type' => $doc->document_type,
                'document_type_label' => self::DOC_TYPE_LABELS[$doc->document_type] ?? ucfirst(str_replace('_', ' ', $doc->document_type)),
                'name' => $doc->original_filename ?? 'Dokumen',
                'request_number' => $doc->request_number ?? '',
                'suspect_name' => $doc->suspect_name ?? '',
                'investigator_name' => $doc->investigator_name ?? '',
                'download_url' => $downloadUrl,
                'preview_url' => $previewUrl,
                'created_at' => $createdAt?->toIso8601String() ?? '',
                'sort_timestamp' => $createdAt?->getTimestamp() ?? 0,
                'source' => 'database',
                'relevance' => (int) ($doc->relevance ?? 0),
            ];
        }

        // Note: BA penyerahan is stored in the documents table as document_type='ba_penyerahan'
        // So we don't need separate delivery search

        $this->applySortToDocuments($documents, $sort);

        $total = count($documents);
        $startIndex = ($page - 1) * $perPage;
        $items = array_slice($documents, $startIndex, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    /**
     * Apply sorting to documents array
     */
    private function applySortToDocuments(array &$documents, string $sort): void
    {
        usort($documents, function ($a, $b) use ($sort) {
            $aTime = $a->sort_timestamp ?? 0;
            $bTime = $b->sort_timestamp ?? 0;
            $aRelevance = $a->relevance ?? 0;
            $bRelevance = $b->relevance ?? 0;

            if ($sort === 'oldest') {
                return $aTime <=> $bTime;
            }

            if ($sort === 'latest') {
                return $bTime <=> $aTime;
            }

            $compare = $bRelevance <=> $aRelevance;
            if ($compare !== 0) {
                return $compare;
            }

            return $bTime <=> $aTime;
        });
    }
}
