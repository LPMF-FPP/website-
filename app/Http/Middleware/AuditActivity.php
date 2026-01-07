<?php

namespace App\Http\Middleware;

use App\Support\ActivityLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditActivity
{
    public function handle(Request $request, Closure $next, string $action, ?string $subjectParam = null, ?string $subjectType = null): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $subject = $request->attributes->get('audit_subject');
        $meta = $request->attributes->get('audit_meta', []);
        $targetUserId = $request->attributes->get('audit_target_user_id');
        $before = $request->attributes->get('audit_before');
        $after = $request->attributes->get('audit_after');

        if (! $subject && $subjectParam) {
            $subject = $request->route($subjectParam);
        }

        if ($subject && ! $subject instanceof Model && $subjectType) {
            $subject = [
                'type' => $subjectType,
                'id' => $subject,
            ];
        }

        $meta = array_merge(
            [
                'query' => $request->query(),
                'route_params' => $this->normalizeRouteParams($request),
            ],
            $meta
        );

        ActivityLogger::log(
            $action,
            $targetUserId,
            $subject,
            $before,
            $after,
            $meta,
            null,
            $request
        );

        return $response;
    }

    private function normalizeRouteParams(Request $request): array
    {
        $route = $request->route();
        if (! $route) {
            return [];
        }

        $normalized = [];
        foreach ($route->parameters() as $key => $value) {
            $normalized[$key] = $value instanceof Model ? $value->getKey() : $value;
        }

        return $normalized;
    }
}
