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

        // Allow access to SaaS registration, suspended page, assets, and auth logout
        if ($request->is('saas/*') || $request->is('login') || $request->is('logout')) {
            return $next($request);
        }

        $tenantId = session('tenant_id');
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if ($tenant && !$tenant->isActive()) {
                return redirect()->route('saas.suspended')->with('error', 'Your business subscription has expired or been suspended. Please contact support or renew your subscription.');
            }
        }

        return $next($request);
    }
}
