<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ActivityLogger;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            if (in_array($response->getStatusCode(), [401, 403, 419, 422, 429], true)) {
                ActivityLogger::log('GOWA_UPDATE_HTTP_REJECTED', null, null, null, null, $safeMeta + ['status' => $response->getStatusCode()], $request->user()?->id, $request);
            } else {
                ActivityLogger::log($response->isSuccessful() || $response->isRedirection() ? 'GOWA_UPDATE_REQUEST_ACCEPTED' : 'GOWA_UPDATE_REQUEST_REJECTED', null, null, null, null, $safeMeta, $request->user()?->id, $request);
            }

            return $response;
        } catch (\Throwable $exception) {
            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof TokenMismatchException => 419,
                $exception instanceof ValidationException => 422,
                $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 429 => 429,
                default => null,
            };
            if ($status !== null) {
                ActivityLogger::log('GOWA_UPDATE_HTTP_REJECTED', null, null, null, null, $safeMeta + ['status' => $status], $request->user()?->id, $request);
            }
            throw $exception;
        }
    }
}
