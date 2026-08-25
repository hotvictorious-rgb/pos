<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BlockReadOnlyMutations
{
    /**
     * Prevent users with 'viewer' (Read-Only Executive) role from performing mutating actions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->role === 'viewer') {
            // Block all data modification HTTP methods (POST, PUT, PATCH, DELETE)
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => '🔒 Access Denied: Executive Owner (View-Only) account cannot create, edit, or delete records.'
                    ], 403);
                }

                return back()->with('error', '🔒 Access Denied: Executive Owner (View-Only) account is strictly read-only and cannot create, edit, or delete records.');
            }
        }

        return $next($request);
    }
}
