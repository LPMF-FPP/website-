<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NumberingController extends Controller
{
    public function __construct(private readonly NumberingService $service)
    {
    }

    public function preview(Request $request, string $scope)
    {
        Gate::authorize('issue-number');

        $context = $request->input('context', []);

        $number = $this->service->preview($scope, $context);

        return response()->json(['number' => $number]);
    }

    public function issue(Request $request, string $scope)
    {
        Gate::authorize('issue-number');

        $context = $request->input('context', []);

        $number = $this->service->issue($scope, $context);

        return response()->json(['number' => $number]);
    }
}
