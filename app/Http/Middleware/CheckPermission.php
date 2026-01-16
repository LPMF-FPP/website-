<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  Permission name to check (e.g., "permintaan.view")
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        // Jika tidak login, redirect ke login
        if (!$user) {
            return redirect()->route('login');
        }

        // Cek permission
        if (!$user->hasPermission($permission)) {
            // Jika request AJAX, return JSON
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses ke halaman ini.',
                    'permission_required' => $permission,
                ], 403);
            }

            // Redirect dengan pesan error
            return redirect()
                ->back()
                ->with('error', 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
