<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    /**
     * Handle an incoming request.
     * Restricts administrative and settings management to users with the 'admin' role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // 1. Admin has full unrestricted access
        if ($user && $user->role === 'admin') {
            return $next($request);
        }

        // 2. Executive Owner (viewer) has read-only GET access to monitor staff and audit pages
        if ($user && $user->role === 'viewer' && $request->isMethod('GET') && !$request->is('settings*')) {
            return $next($request);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['error' => 'Forbidden. Administrator privileges required.'], 403);
        }

        return redirect()->route('dashboard')->with('warning', '🔒 Access Restricted: Administrator privileges required.');
    }
}
