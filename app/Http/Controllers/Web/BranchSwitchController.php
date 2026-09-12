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
            return back()->with('success', '🌐 Switched to All Branches (Consolidated View).');
        }

        // 3. Multi-Branch Users selecting a specific branch
        $warehouse = Warehouse::find($targetId);
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

        return back()->with('success', "🏬 Active branch switched to {$warehouse->name}.");
    }
}
