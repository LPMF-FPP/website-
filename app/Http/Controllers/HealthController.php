<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Read-only health endpoint. No DB access.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'time' => now()->toISOString(),
            'app' => config('app.name'),
            'commit' => env('APP_COMMIT', null),
        ]);
    }
}
