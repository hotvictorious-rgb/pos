<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchSwitchController extends Controller
{
    /**
     * Switch the active operational or executive branch context.
     */
    public function switchBranch(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Gracefully handle GET requests or direct navigation by redirecting to appropriate dashboard
        if ($request->isMethod('get') || !$request->has('warehouse_id')) {
            $dest = ($user->role === 'cashier') ? 'pos.index' : (($user->isSuperAdmin() || $user->isPlatformAdmin()) ? 'saas.admin.index' : 'dashboard');
            return redirect()->route($dest);
        }

        $targetId = $request->input('warehouse_id');

        // 1. Branch-scoped personnel (cashiers, storekeepers) are locked to their assigned branch
        if ($user->isBranchScoped()) {
            if ($targetId && (int) $targetId !== (int) $user->warehouse_id) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => '🔒 Access Restricted: You are assigned to your designated branch.'], 403);
                }
                return back()->with('error', '🔒 Access Restricted: You are assigned to your designated branch.');
            }

            session([
                'warehouse_id' => $user->warehouse_id,
                'active_warehouse_id' => $user->warehouse_id,
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'warehouse_id' => $user->warehouse_id]);
            }
            return back();
        }

        // 2. Multi-Branch Users (Admins, Owners, Executives) selecting "ALL" (Consolidated)
        if ($targetId === 'ALL' || $targetId === '' || $targetId === null) {
            session()->forget('active_warehouse_id');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'warehouse_id' => null,
                    'warehouse_name' => 'All Branches (Consolidated)',
                ]);
            }
            return $this->redirectClean($request, '🌐 Switched to All Branches (Consolidated View).');
        }

        // 3. Multi-Branch Users selecting a specific branch
        $warehouse = Warehouse::withoutGlobalScopes()->find($targetId);
        if (!$warehouse || !$warehouse->is_active) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Selected branch is invalid or inactive.'], 404);
            }
            return back()->with('error', 'Selected branch is invalid or inactive.');
        }

        // Validate access permission & tenant boundary
        if (!$user->canAccessWarehouse($warehouse->id)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => '🔒 Access Restricted: You do not have permission to access this branch.'], 403);
            }
            return back()->with('error', '🔒 Access Restricted: You do not have permission to access this branch.');
        }

        session(['active_warehouse_id' => $warehouse->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'warehouse_id' => $warehouse->id,
                'warehouse_name' => $warehouse->name,
            ]);
        }

        return $this->redirectClean($request, "🏬 Active branch switched to {$warehouse->name}.");
    }

    /**
     * Cleanly redirect back while stripping any stale warehouse_id query parameters from the referer URL.
     */
    protected function redirectClean(Request $request, string $message)
    {
        $previousUrl = url()->previous();
        if ($previousUrl) {
            $parsed = parse_url($previousUrl);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryParams);
                if (isset($queryParams['warehouse_id'])) {
                    unset($queryParams['warehouse_id']);
                    $newQuery = http_build_query($queryParams);
                    $scheme = ($request->isSecure() || $request->header('x-forwarded-proto') === 'https' || ($parsed['scheme'] ?? '') === 'https') ? 'https' : ($parsed['scheme'] ?? 'http');
                    $host = $parsed['host'] ?? $request->getHost();
                    $port = (isset($parsed['port']) && !in_array((int)$parsed['port'], [80, 443])) ? ':' . $parsed['port'] : '';
                    $cleanUrl = $scheme . '://' . $host . $port . ($parsed['path'] ?? '/');
                    if (!empty($newQuery)) {
                        $cleanUrl .= '?' . $newQuery;
                    }
                    return redirect($cleanUrl)->with('success', $message);
                }
            }
        }
        return back()->with('success', $message);
    }
}
