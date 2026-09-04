<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Auth\CapabilityService;

class RequireCapability
{
    /**
     * Handle an incoming request.
     * Enforces that the authenticated user holds at least one of the specified capabilities.
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$capabilities One or more required capabilities (comma-separated or varargs)
     * @return Response
     */
    public function handle(Request $request, Closure $next, ...$capabilities): Response
    {
        $user = Auth::user();

        // 1. Authentication Check
        if (!$user || $user->disabled) {
            if ($request->ajax() || $request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        // 2. Normalize capability list (support 'cap1,cap2' or multiple parameters)
        $required = [];
        foreach ($capabilities as $cap) {
            foreach (explode(',', $cap) as $subCap) {
                $trimmed = trim($subCap);
                if (!empty($trimmed)) {
                    $required[] = $trimmed;
                }
            }
        }

        // 3. Strict Authority Boundary Enforcement
        // Platform users can NEVER access tenant business capabilities
        if ($user->isPlatformUser()) {
            foreach ($required as $cap) {
                if (!CapabilityService::isPlatformCapability($cap)) {
                    $msg = 'Forbidden. Platform administrators cannot access tenant business records.';
                    if ($request->ajax() || $request->wantsJson() || $request->is('api/*')) {
                        return response()->json(['error' => $msg], 403);
                    }
                    abort(403, $msg);
                }
            }
        }

        // Tenant users can NEVER access platform capabilities
        if ($user->isTenantUser()) {
            foreach ($required as $cap) {
                if (CapabilityService::isPlatformCapability($cap)) {
                    $msg = 'Forbidden. Tenant users cannot access platform management.';
                    if ($request->ajax() || $request->wantsJson() || $request->is('api/*')) {
                        return response()->json(['error' => $msg], 403);
                    }
                    abort(403, $msg);
                }
            }
        }

        // 4. Check if user holds at least one required capability
        $userCapabilities = CapabilityService::getCapabilitiesForUser($user);
        $hasPermission = false;

        foreach ($required as $cap) {
            if (in_array($cap, $userCapabilities, true)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            $capList = implode(', ', $required);
            $msg = "Forbidden. You lack the required capability [{$capList}] for this operation.";

            if ($request->ajax() || $request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => $msg], 403);
            }

            if ($request->isMethod('GET')) {
                if ($user->isPlatformUser()) {
                    abort(403, $msg);
                }
                return redirect()->route('dashboard')->with('warning', "🔒 Access Restricted: You do not have permission to access that area.");
            }

            return response()->json(['error' => $msg], 403);
        }

        return $next($request);
    }
}
