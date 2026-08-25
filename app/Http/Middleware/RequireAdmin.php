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

        if (!$user || $user->role !== 'admin') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Forbidden. Administrator privileges required.'], 403);
            }

            return redirect()->route('dashboard')->with('warning', '🔒 Access Restricted: Administrator privileges required.');
        }

        return $next($request);
    }
}
