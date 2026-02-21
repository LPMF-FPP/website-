<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAnyPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $permissionList = collect(preg_split('/[|,]/', $permissions) ?: [])
            ->map(fn ($permission) => trim((string) $permission))
            ->filter(fn (string $permission) => $permission !== '')
            ->values()
            ->all();

        if (empty($permissionList) || ! $user->hasAnyPermission($permissionList)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses ke halaman ini.',
                    'permission_required_any' => $permissionList,
                ], 403);
            }

            return redirect()
                ->back()
                ->with('error', 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
