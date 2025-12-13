<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class Audit
{
    public static function log(string $action, ?string $target = null, $before = null, $after = null, array $context = []): void
    {
        AuditLog::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'target' => $target,
            'before' => $before,
            'after' => $after,
            'context' => $context,
        ]);
    }
}

