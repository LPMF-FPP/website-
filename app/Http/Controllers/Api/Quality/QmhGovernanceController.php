<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhAudit;
use App\Models\QmhKum;
use App\Models\QmhRapat;
use App\Models\QmhRapatActionItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QmhGovernanceController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyPermission(['qmh.view', 'qmh.rapat.view', 'qmh.audit.view', 'qmh.kum.view']), 403);

        $canViewAll = $user->hasAnyPermission(['qmh.view', 'qmh.rapat.view.all', 'qmh.audit.view.all', 'qmh.kum.view.all']);

        $rapatQuery = QmhRapat::query();
        $auditQuery = QmhAudit::query();
        $kumQuery = QmhKum::query();
        $actionItemQuery = QmhRapatActionItem::query();

        if (! $canViewAll) {
            $rapatQuery->where('created_by', $user->id);
            $auditQuery->where(function ($query) use ($user): void {
                $query
                    ->where('created_by', $user->id)
                    ->orWhereHas('auditAuditors', fn ($auditorQuery) => $auditorQuery->where('user_id', $user->id));
            });
            $kumQuery->where('created_by', $user->id);
            $actionItemQuery->where(function ($query) use ($user): void {
                $query->where('created_by', $user->id)->orWhere('assignee_id', $user->id);
            });
        }

        $payload = [
            'rapat_count' => $rapatQuery->count(),
            'audit_count' => $auditQuery->count(),
            'kum_count' => $kumQuery->count(),
            'overdue_count' => (clone $actionItemQuery)->where('status', QmhRapatActionItem::STATUS_OVERDUE)->count(),
            'due_soon_count' => (clone $actionItemQuery)
                ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
                ->whereIn('status', [
                    QmhRapatActionItem::STATUS_OPEN,
                    QmhRapatActionItem::STATUS_IN_PROGRESS,
                    QmhRapatActionItem::STATUS_RESOLVED,
                    QmhRapatActionItem::STATUS_OVERDUE,
                ])
                ->count(),
            'updated_at' => now()->toIso8601String(),
        ];

        return response()->json($payload);
    }
}
