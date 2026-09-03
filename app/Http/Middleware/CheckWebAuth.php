<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckWebAuth
{
    /**
     * Ensure the user is logged into the web session before accessing protected pages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publicPaths = [
            'login',
            'logout',
            'tenant/login',
            'tenant/logout',
            'tenant-employee/login',
            'tenant-employee/logout',
            'super-admin/login',
            'super-admin/logout',
            'super-admin-employee/login',
            'super-admin-employee/logout',
            'install',
            'install/*',
            'api/login',
            'landing',
            'welcome',
            'up',
        ];

        foreach ($publicPaths as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        $userId = session('user_id');
        $isApi = ($request->ajax() || $request->wantsJson() || $request->is('api/*'));

        if (!$userId && !Auth::check()) {
            if ($isApi) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            session()->put('url.intended', $request->fullUrl());
            return redirect()->route('login')->with('warning', 'Please log in to access the system.');
        }

        $effectiveId = Auth::id() ?: $userId;
        $user = $effectiveId ? User::findForAuthenticationById($effectiveId) : null;

        if (!$user || $user->disabled) {
            session()->forget(['user_id', 'user_name', 'user_role', 'tenant_id', 'is_impersonating', 'impersonator_id']);
            Auth::logout();
            if ($isApi) {
                return response()->json(['error' => 'Your session has expired or your account is disabled.'], 401);
            }
            return redirect()->route('login')->with('error', 'Your session has expired or your account is disabled.');
        }

        if (!Auth::check() || Auth::id() !== $user->id) {
            Auth::login($user);
        }

        // Validate and synchronize tenant_id context securely
        if (config('saas.enabled')) {
            if (!session('is_impersonating')) {
                // If a normal tenant user has no valid tenant, deny access rather than assigning them to the platform tenant
                if (empty($user->tenant_id)) {
                    session()->forget(['user_id', 'user_name', 'user_role', 'tenant_id', 'is_impersonating', 'impersonator_id']);
                    Auth::logout();
                    if ($isApi) {
                        return response()->json(['error' => 'Forbidden: Account is not associated with an active tenant.'], 403);
                    }
                    return redirect()->route('login')->with('error', 'Your account is not assigned to an active tenant.');
                }

                if (session('tenant_id') !== $user->tenant_id) {
                    session(['tenant_id' => $user->tenant_id]);
                }
            }
        } else {
            // Standalone mode: set default tenant context if not set
            if (!session()->has('tenant_id')) {
                session(['tenant_id' => 'default-tenant']);
            }
        }

        view()->share('authUser', $user);

        return $next($request);
    }
}
