<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\SearchDocumentResource;
use App\Http\Resources\SearchPeopleResource;
use App\Models\Person;
use App\Models\Search\Document;
use App\Services\Search\SearchService;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request, SearchService $service)
    {
        $this->authorize('viewAny', Person::class);
        $this->authorize('viewAny', Document::class);

        $payload = $service->search($request->validated());

        return response()->json([
            'query' => $payload['query'],
            'doc_type' => $payload['doc_type'],
            'sort' => $payload['sort'],
            'summary' => $payload['summary'],
            'people' => [
                'pagination' => $payload['people']['pagination'],
                'data' => SearchPeopleResource::collection($payload['people']['data'] ?? [])->resolve(),
            ],
            'documents' => [
                'pagination' => $payload['documents']['pagination'],
                'data' => SearchDocumentResource::collection($payload['documents']['data'] ?? [])->resolve(),
            ],
        ]);
    }
}
