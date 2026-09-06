<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class RequirePlatformUser
{
    /**
     * Handle an incoming request.
     * Restricts SaaS platform control panel and platform operations exclusively
     * to platform-level users (Platform Super Admin & Platform Employees).
     *
     * Fine-grained capability checks (e.g. `capability:platform.tenants`) are then
     * evaluated per-route and at the controller level to restrict platform employees.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user && session('user_id')) {
            $user = User::findForAuthenticationById(session('user_id'));
        }

        // Hard Boundary: Only platform users (Platform Admin & Platform Employee) can enter SaaS platform area
        if (!$user || !$user->isPlatformUser()) {
            if ($request->ajax() || $request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Forbidden. Platform administrator privileges required.'
                ], 403);
            }

            return redirect()->route('dashboard')->with(
                'error',
                '🔒 Access Restricted: Platform administrator privileges required.'
            );
        }

        // If caller is Platform Employee, ensure they hold assigned platform capabilities
        if ($user->isPlatformEmployee()) {
            $platformCaps = $user->getCapabilities();
            if (empty($platformCaps)) {
                if ($request->ajax() || $request->wantsJson() || $request->is('api/*')) {
                    return response()->json([
                        'error' => 'Forbidden. You do not have any assigned platform capabilities.'
                    ], 403);
                }
                abort(403, 'Forbidden. You do not have any assigned platform capabilities.');
            }
        }

        return $next($request);
    }
}
