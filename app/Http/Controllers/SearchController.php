<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(
        private SearchService $searchService
    ) {}

    /**
     * Handle search data endpoint for the unified search page.
     * Returns JSON response with people and documents results.
     */
    public function data(SearchRequest $request): JsonResponse
    {
        $payload = $this->searchService->search($request->validated());

        return response()->json([
            'query' => $payload['query'],
            'doc_type' => $payload['doc_type'],
            'sort' => $payload['sort'],
            'summary' => $payload['summary'],
            'people' => $payload['people'],
            'documents' => $payload['documents'],
        ]);
    }

    /**
     * Handle autocomplete suggestions endpoint.
     * Returns JSON response with suggestions for search operators and entities.
     */
    public function suggest(): JsonResponse
    {
        // Provide common search operators and field suggestions
        $suggestions = [
            ['type' => 'operator', 'value' => 'type:', 'label' => 'type: - Filter by request type'],
            ['type' => 'operator', 'value' => 'status:', 'label' => 'status: - Filter by status'],
            ['type' => 'operator', 'value' => 'investigator:', 'label' => 'investigator: - Filter by investigator'],
            ['type' => 'operator', 'value' => 'date:', 'label' => 'date: - Filter by date (YYYY-MM-DD)'],
            ['type' => 'status', 'value' => 'pending', 'label' => 'Pending'],
            ['type' => 'status', 'value' => 'testing', 'label' => 'Testing'],
            ['type' => 'status', 'value' => 'completed', 'label' => 'Completed'],
            ['type' => 'status', 'value' => 'delivered', 'label' => 'Delivered'],
        ];
        
        return response()->json([
            'items' => $suggestions,
        ]);
    }
}
