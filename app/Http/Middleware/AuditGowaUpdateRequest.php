<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuditGowaUpdateRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $safeMeta = [
            'release_id' => is_string($request->input('release_id')) ? substr($request->input('release_id'), 0, 128) : null,
            'action' => $request->route()?->getName(),
        ];

        try {
            $response = $next($request);
            ActivityLogger::log($response->isSuccessful() || $response->isRedirection() ? 'GOWA_UPDATE_REQUEST_ACCEPTED' : 'GOWA_UPDATE_REQUEST_REJECTED', null, null, null, null, $safeMeta, $request->user()?->id, $request);

            return $response;
        } catch (\Throwable $exception) {
            ActivityLogger::log('GOWA_UPDATE_REQUEST_EXCEPTION', null, null, null, null, ['action' => $safeMeta['action']], $request->user()?->id, $request);
            throw $exception;
        }
    }
}
