<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\Activity;
use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    /**
     * Display the comprehensive System Settings Hub.
     */
    public function index()
    {
        $tenantId = session('tenant_id') ?? 'default-tenant';
        $tenantObj = \App\Models\Tenant::find($tenantId);
        $defaultName = $tenantObj ? $tenantObj->name : 'VMARKET POS Store';

        $settings = Setting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'businessName' => $defaultName,
                'businessAddress' => 'Headquarters Address',
                'businessPhone' => '+234 800 000 0000',
                'businessEmail' => 'admin@vmarketpos.com',
                'currency' => '₦',
                'categories' => ['Groceries', 'Beverages', 'Electronics', 'Hardware', 'Household'],
                'reportFooter' => 'Thank you for your patronage! Goods sold in good condition cannot be returned after 3 days.',
                'lowStockThreshold' => 5,
                'transactionEditLimitDays' => 0,
                'fontFamily' => 'Plus Jakarta Sans',
            ]
        );

        $warehouses = Warehouse::orderBy('id')->get();
        $user = Auth::user();
        if ($user && $user->isTenantUser()) {
            $backups = Backup::where('tenant_id', $tenantId)->orderBy('created_at', 'desc')->get();
        } else {
            $backups = collect();
        }

        return view('settings.index', compact('settings', 'warehouses', 'backups'));
    }

    /**
     * Update General Business & Receipt Settings.
     */
    public function update(Request $request)
    {
        $authUser = Auth::user();
        // 🔒 Invariant VM-023: Universal Scope Boundary
        // Company-wide business and receipt settings are strictly tenant-wide configuration
        if ($authUser && $authUser->isBranchScoped()) {
            abort(403, 'Forbidden: Branch employees cannot modify company-wide business settings.');
        }

        $request->validate([
            'businessName' => 'required|string|max:150',
            'businessPhone' => 'nullable|string',
            'businessAddress' => 'nullable|string',
            'currency' => 'required|string|max:10',
            'lowStockThreshold' => 'required|numeric|min:1',
            'reportFooter' => 'nullable|string',
        ]);

        $tenantId = session('tenant_id') ?? 'default-tenant';
        $tenantObj = \App\Models\Tenant::find($tenantId);
        $defaultName = $tenantObj ? $tenantObj->name : 'VMARKET POS Store';

        $settings = Setting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'businessName' => $defaultName,
                'businessAddress' => 'Headquarters Address',
                'businessPhone' => '+234 800 000 0000',
                'businessEmail' => 'admin@vmarketpos.com',
                'currency' => '₦',
                'categories' => ['Groceries', 'Beverages', 'Electronics', 'Hardware', 'Household'],
                'reportFooter' => 'Thank you for your patronage! Goods sold in good condition cannot be returned after 3 days.',
                'lowStockThreshold' => 5,
                'transactionEditLimitDays' => 0,
                'fontFamily' => 'Plus Jakarta Sans',
            ]
        );

        $categories = $request->categories ? array_filter(array_map('trim', explode(',', $request->categories))) : $settings->categories;

        $settings->update([
            'businessName' => $request->businessName,
            'businessPhone' => $request->businessPhone,
            'businessEmail' => $request->businessEmail,
            'businessAddress' => $request->businessAddress,
            'currency' => $request->currency,
            'categories' => $categories,
            'reportFooter' => $request->reportFooter,
            'lowStockThreshold' => (int) $request->lowStockThreshold,
        ]);

        $userName = Auth::user()->name ?? 'Auditor / Admin';

        Activity::create([
            'id' => (string) Str::uuid(),
            'type' => 'SETTINGS_UPDATED',
            'description' => "{$userName} updated business and receipt settings",
            'userId' => Auth::id() ?? 'ADMIN',
            'userName' => $userName,
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('settings.index')->with('success', '✓ Business and receipt settings saved successfully!');
    }

    /**
     * Add a New Branch Shop or Warehouse Location.
     */
    public function storeWarehouse(Request $request)
    {
        $authUser = Auth::user();
        // 🔒 Invariant VM-023: Universal Scope Boundary
        if ($authUser && $authUser->isBranchScoped()) {
            abort(403, 'Forbidden: Branch employees cannot create branch locations.');
        }

        $tenantId = session('tenant_id') ?? 'default-tenant';

        // Enforce SaaS Subscription Branch Limit
        if (config('saas.enabled')) {
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant && $tenant->max_branches !== null) {
                $currentBranches = Warehouse::count();
                if ($currentBranches >= $tenant->max_branches) {
                    $errorMsg = "🔒 Subscription Limit Reached: Your current plan allows a maximum of {$tenant->max_branches} branch location(s). Please upgrade your subscription to add more branches.";
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'error' => $errorMsg], 422);
                    }
                    return back()->withErrors(['error' => $errorMsg])->withInput();
                }
            }
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'code' => ['required', 'string', \Illuminate\Validation\Rule::unique('warehouses', 'code')->where('tenant_id', $tenantId)],
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'manager_name' => 'nullable|string',
        ]);

        Warehouse::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'address' => $request->address,
            'phone' => $request->phone,
            'manager_name' => $request->manager_name,
            'is_active' => true,
        ]);

        return redirect()->route('settings.index')->with('success', "✓ Branch shop '{$request->name}' added successfully!");
    }

    /**
     * Update an Existing Branch Shop or Warehouse Location.
     */
    public function updateWarehouse(Request $request, $id)
    {
        $authUser = Auth::user();
        // 🔒 Invariant VM-023: Universal Scope Boundary
        if ($authUser && $authUser->isBranchScoped()) {
            abort(403, 'Forbidden: Branch employees cannot modify branch infrastructure.');
        }

        $wh = Warehouse::findOrFail($id);
        $tenantId = session('tenant_id') ?? 'default-tenant';

        $request->validate([
            'name' => 'required|string|max:100',
            'code' => ['required', 'string', \Illuminate\Validation\Rule::unique('warehouses', 'code')->where('tenant_id', $tenantId)->ignore($wh->id)],
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'manager_name' => 'nullable|string',
        ]);

        $wh->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'address' => $request->address,
            'phone' => $request->phone,
            'manager_name' => $request->manager_name,
        ]);

        $userName = Auth::user()->name ?? 'Admin';
        Activity::create([
            'id' => (string) Str::uuid(),
            'type' => 'SETTINGS_UPDATED',
            'description' => "{$userName} updated branch details for '{$wh->name}' ({$wh->code})",
            'userId' => Auth::id() ?? 'ADMIN',
            'userName' => $userName,
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('settings.index')->with('success', "✓ Branch '{$wh->name}' updated successfully!");
    }

    /**
     * Toggle Branch Location Active Status.
     */
    public function toggleWarehouse($id)
    {
        $authUser = Auth::user();
        // 🔒 Invariant VM-023: Universal Scope Boundary
        if ($authUser && $authUser->isBranchScoped()) {
            abort(403, 'Forbidden: Branch employees cannot enable or disable branch locations.');
        }

        $wh = Warehouse::findOrFail($id);
        $wh->is_active = !$wh->is_active;
        $wh->save();

        return redirect()->route('settings.index')->with('success', "✓ Branch '{$wh->name}' status updated.");
    }
}
