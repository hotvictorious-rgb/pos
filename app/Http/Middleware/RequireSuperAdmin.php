<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdmin
{
    /**
     * Handle an incoming request.
     * Restricts SaaS platform control panel and cross-tenant mutations
     * exclusively to verified platform super-administrators.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user && session('user_id')) {
            $user = User::findForAuthenticationById(session('user_id'));
        }

        // Allow stop-impersonation route only if caller is currently in an active impersonation session
        // initiated by a verified server-side platform super-administrator.
        if ($request->is('saas/admin/stop-impersonate')) {
            $isImpersonating = session('is_impersonating');
            $impersonatorId = session('impersonator_id');
            if ($isImpersonating && !empty($impersonatorId)) {
                $impersonator = User::findForAuthenticationById($impersonatorId);
                if ($impersonator && $impersonator->isSuperAdmin()) {
                    return $next($request);
                }
            }
        }

        if (!$user || !$user->isSuperAdmin()) {
            if ($request->ajax() || $request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Forbidden. Platform Super-Administrator privileges required.'
                ], 403);
            }

            return redirect()->route('dashboard')->with(
                'error',
                '🔒 Access Restricted: Platform Super-Administrator privileges required.'
            );
        }

        return $next($request);
    }
}
