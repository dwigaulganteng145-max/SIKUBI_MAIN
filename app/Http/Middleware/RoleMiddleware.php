<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Usage: Route::middleware('role:ADMIN_KEUANGAN') or Route::middleware('role:DIREKTUR')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles)) {
            if ($request->expectsJson() || $request->header('X-Inertia')) {
                abort(403, 'Anda tidak memiliki akses ke halaman ini.');
            }

            return redirect('/dashboard')->with('importResult', [
                'status' => 'ERROR',
                'message' => 'Anda tidak memiliki akses ke halaman tersebut.',
            ]);
        }

        return $next($request);
    }
}
