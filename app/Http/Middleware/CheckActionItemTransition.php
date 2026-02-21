<?php

namespace App\Http\Middleware;

use App\Models\QmhRapatActionItem;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckActionItemTransition
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $targetStatus = (string) $request->input('status', '');
        $actionItem = $request->route('actionItem');

        if (! $actionItem instanceof QmhRapatActionItem || $targetStatus === '') {
            return $next($request);
        }

        if ($targetStatus === QmhRapatActionItem::STATUS_VERIFIED && ! $user->hasAnyPermission([
            'action-item:verify',
            'qmh.rapat.edit',
            'qmh.rapat.create.all',
            'qmh.create',
        ])) {
            Log::warning('QMH action item transition denied: verify permission missing', [
                'action_item_id' => $actionItem->id,
                'user_id' => $user->id,
                'from' => $actionItem->status,
                'to' => $targetStatus,
            ]);
            abort(403);
        }

        if ($targetStatus === QmhRapatActionItem::STATUS_CLOSED && ! $user->hasAnyPermission([
            'action-item:close',
            'qmh.rapat.edit',
            'qmh.rapat.create.all',
            'qmh.create',
        ])) {
            Log::warning('QMH action item transition denied: close permission missing', [
                'action_item_id' => $actionItem->id,
                'user_id' => $user->id,
                'from' => $actionItem->status,
                'to' => $targetStatus,
            ]);
            abort(403);
        }

        if ((string) $actionItem->status === QmhRapatActionItem::STATUS_OVERDUE
            && in_array($targetStatus, [QmhRapatActionItem::STATUS_IN_PROGRESS, QmhRapatActionItem::STATUS_RESOLVED], true)
            && ! $user->hasAnyPermission([
                'action-item:reopen',
                'qmh.rapat.edit',
                'qmh.rapat.create.all',
                'qmh.create',
            ])
            && (int) $actionItem->assignee_id !== (int) $user->id) {
            Log::warning('QMH action item transition denied: overdue transition not authorized', [
                'action_item_id' => $actionItem->id,
                'user_id' => $user->id,
                'from' => $actionItem->status,
                'to' => $targetStatus,
            ]);
            abort(403);
        }

        return $next($request);
    }
}
