<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhRapat;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QmhRapatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyPermission(['qmh.rapat.view', 'qmh.rapat.view.all', 'qmh.view']), 403);

        $rapats = QmhRapat::query()
            ->with(['creator'])
            ->search($request->string('search')->toString())
            ->when($request->filled('meeting_type'), fn ($query) => $query->where('meeting_type', $request->string('meeting_type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when(! $user->hasAnyPermission(['qmh.rapat.view.all', 'qmh.view']), fn ($query) => $query->where('created_by', $user->id))
            ->orderByDesc('scheduled_at')
            ->paginate((int) $request->input('per_page', 15))
            ->appends($request->query());

        return response()->json($rapats);
    }
}
