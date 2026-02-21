<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhAudit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QmhAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyPermission(['qmh.audit.view', 'qmh.audit.view.all', 'qmh.view']), 403);

        $audits = QmhAudit::query()
            ->withCount('temuans')
            ->search($request->string('search')->toString())
            ->when($request->filled('audit_type'), fn ($query) => $query->where('audit_type', $request->string('audit_type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when(! $user->hasAnyPermission(['qmh.audit.view.all', 'qmh.view']), function ($query) use ($user) {
                $query->where(function ($subquery) use ($user): void {
                    $subquery
                        ->where('created_by', $user->id)
                        ->orWhereHas('auditAuditors', fn ($auditorQuery) => $auditorQuery->where('user_id', $user->id));
                });
            })
            ->orderByDesc('scheduled_at')
            ->paginate((int) $request->input('per_page', 15))
            ->appends($request->query());

        return response()->json($audits);
    }
}
