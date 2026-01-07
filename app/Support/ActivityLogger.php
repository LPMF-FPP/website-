<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ActivityLogger
{
    public static function log(
        string $action,
        ?int $targetUserId = null,
        Model|array|null $subject = null,
        $before = null,
        $after = null,
        array $meta = [],
        ?int $actorUserId = null,
        ?Request $request = null
    ): ActivityLog {
        try {
            if (! Schema::hasTable('activity_logs')) {
                return new ActivityLog();
            }
        } catch (\Throwable $e) {
            return new ActivityLog();
        }

        $actorId = $actorUserId ?? Auth::id();
        [$subjectType, $subjectId] = self::resolveSubject($subject);

        if (! $request && app()->bound('request')) {
            $request = app('request');
        }

        return ActivityLog::create([
            'actor_user_id' => $actorId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'route' => $request?->route()?->getName(),
            'method' => $request?->getMethod(),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'before' => $before,
            'after' => $after,
            'meta' => $meta,
        ]);
    }

    private static function resolveSubject(Model|array|null $subject): array
    {
        if (! $subject) {
            return [null, null];
        }

        if ($subject instanceof Model) {
            return [$subject->getMorphClass(), $subject->getKey()];
        }

        $subjectType = Arr::get($subject, 'type');
        $subjectId = Arr::get($subject, 'id');

        return [$subjectType, $subjectId];
    }
}
