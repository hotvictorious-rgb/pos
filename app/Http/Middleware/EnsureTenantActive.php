<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('saas.enabled')) {
            return $next($request);
        }

        // Allow access to SaaS registration, suspended page, assets, public landing and auth/portal login & logout
        if ($request->is('saas/*') || $request->is('landing') || $request->is('welcome') || $request->is('login') || $request->is('logout') || $request->is('*/login') || $request->is('*/logout')) {
            return $next($request);
        }

        $tenantId = session('tenant_id');
        $isApi = ($request->ajax() || $request->wantsJson() || $request->is('api/*'));

        if (!$tenantId) {
            if ($isApi) {
                return response()->json(['error' => 'Unauthenticated: Missing tenant context.'], 401);
            }
            return redirect()->route('login')->with('warning', 'Session tenant context missing. Please log in again.');
        }

        // The default platform tenant is inherently active
        if ($tenantId === 'default-tenant') {
            return $next($request);
        }

        $tenant = Tenant::find($tenantId);

        // Fail-closed: tenant does not exist in database
        if (!$tenant) {
            session()->forget(['user_id', 'user_name', 'user_role', 'tenant_id', 'is_impersonating', 'impersonator_id']);
            if (\Illuminate\Support\Facades\Auth::check()) {
                \Illuminate\Support\Facades\Auth::logout();
            }

            if ($isApi) {
                return response()->json(['error' => 'Forbidden: Tenant business not found or has been deactivated.'], 403);
            }
            return redirect()->route('login')->with('error', 'Your business account was not found. Please contact support.');
        }

        // Fail-closed: tenant is suspended or expired
        if (!$tenant->isActive()) {
            if ($isApi) {
                return response()->json([
                    'error' => 'Forbidden: Your business subscription has expired or been suspended.'
                ], 403);
            }
            return redirect()->route('saas.suspended')->with(
                'error',
                'Your business subscription has expired or been suspended. Please contact support or renew your subscription.'
            );
        }

        return $next($request);
    }
}
