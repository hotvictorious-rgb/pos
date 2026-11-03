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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    /**
     * Display the comprehensive System Settings Hub.
     */
    public function index()
    {
        $tenantId = session('tenant_id') ?? 'default-tenant';
        $settings = $this->getOrCreateSettings($tenantId);

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
            'owner_whatsapp_phone' => 'nullable|string|max:30',
            'whatsapp_report_time' => 'nullable|string|max:10',
        ]);

        $tenantId = session('tenant_id') ?? 'default-tenant';
        $settings = $this->getOrCreateSettings($tenantId);

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
            'owner_whatsapp_phone' => $request->owner_whatsapp_phone,
            'whatsapp_report_enabled' => $request->boolean('whatsapp_report_enabled'),
            'whatsapp_report_time' => $request->whatsapp_report_time ?: '21:00',
            'whatsapp_branch_breakdown' => $request->boolean('whatsapp_branch_breakdown'),
            'whatsapp_include_reversals' => $request->boolean('whatsapp_include_reversals'),
            'whatsapp_include_low_stock' => $request->boolean('whatsapp_include_low_stock'),
            'whatsapp_include_inventory' => $request->boolean('whatsapp_include_inventory', true),
            'whatsapp_include_transfers' => $request->boolean('whatsapp_include_transfers', true),
            'whatsapp_include_deliveries' => $request->boolean('whatsapp_include_deliveries', true),
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
     * Resiliently fetch or initialize tenant business settings with guaranteed unique ID.
     */
    private function getOrCreateSettings(string $tenantId): Setting
    {
        $settings = Setting::where('tenant_id', $tenantId)->first();
        if ($settings) {
            return $settings;
        }

        $tenantObj = \App\Models\Tenant::find($tenantId);
        $defaultName = $tenantObj ? $tenantObj->name : 'VMARKET POS Store';
        $nextId = max(1, (int) Setting::withoutGlobalScopes()->max('id') + 1);

        return Setting::create([
            'id' => $nextId,
            'tenant_id' => $tenantId,
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
        ]);
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

        $request->validate([
            'name' => 'required|string|max:100',
            'code' => ['required', 'string', \Illuminate\Validation\Rule::unique('warehouses', 'code')->where('tenant_id', $tenantId)],
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'manager_name' => 'nullable|string',
        ]);

        try {
            $warehouse = DB::transaction(function () use ($tenantId, $request) {
                // Enforce SaaS Subscription Branch Limit with pessimistic row locking
                if (config('saas.enabled')) {
                    $tenant = \App\Models\Tenant::where('id', $tenantId)->lockForUpdate()->first();
                    if ($tenant && $tenant->max_branches !== null) {
                        $currentBranches = Warehouse::count();
                        if ($currentBranches >= $tenant->max_branches) {
                            throw new \DomainException("🔒 Subscription Limit Reached: Your current plan allows a maximum of {$tenant->max_branches} branch location(s). Please upgrade your subscription to add more branches.");
                        }
                    }
                }

                return Warehouse::create([
                    'tenant_id' => $tenantId,
                    'name' => $request->name,
                    'code' => strtoupper($request->code),
                    'address' => $request->address,
                    'phone' => $request->phone,
                    'manager_name' => $request->manager_name,
                    'is_active' => true,
                ]);
            });

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Branch shop '{$warehouse->name}' added successfully!",
                    'warehouseId' => $warehouse->id,
                ]);
            }

            return redirect()->route('settings.index')->with('success', "✓ Branch shop '{$warehouse->name}' added successfully!");
        } catch (\DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
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

    /**
     * Dispatch an immediate test WhatsApp report message to verify configuration.
     */
    public function sendTestWhatsApp(Request $request, \App\Services\Notification\WhatsAppService $whatsAppService)
    {
        $authUser = Auth::user();
        if ($authUser && $authUser->isBranchScoped()) {
            abort(403, 'Forbidden: Branch employees cannot trigger platform WhatsApp test dispatches.');
        }

        $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $tenantId = session('tenant_id') ?? 'default-tenant';
        $settings = $this->getOrCreateSettings($tenantId);

        $testData = [
            'currency' => $settings->currency ?: '₦',
            'business_name' => $settings->businessName ?: config('app.name', 'Victorious Market POS'),
            'branch_name' => 'Main Warehouse (Test Simulation)',
            'date' => now()->format('D, d M Y'),
            'total_invoices' => 12,
            'gross_sales' => 450000.00,
            'cash_collected' => 280000.00,
            'pos_collected' => 120000.00,
            'transfer_collected' => 50000.00,
            'debts_recovered' => 65000.00,
            'new_debts' => 20000.00,
            'returns_refunded' => 0.00,
            'expected_drawer_cash' => 345000.00,
            'reversals_count' => 0,
            'reversals_amount' => 0.00,
            'reversals_details' => [],
            'branches_breakdown' => [
                ['name' => 'Main Branch', 'sales' => 300000, 'cash' => 200000],
                ['name' => 'Outlet Store', 'sales' => 150000, 'cash' => 80000],
            ],
            'low_stock_items' => [
                ['name' => 'Test Item 50kg', 'qty' => 3, 'unit' => 'bags', 'min' => 5],
            ],
        ];

        $message = "🔔 *TEST NOTIFICATION*\n" . $whatsAppService->buildDailyReportMessage($testData);
        $result = $whatsAppService->sendMessage($request->phone, $message, $tenantId);

        return response()->json($result);
    }

    /**
     * Live Site Overlay Helper: Execute database migrations and clear caches on live production overlay.
     */
    public function runMigrations(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser || (!$authUser->isAdmin() && !$authUser->isTenantAdmin() && !$authUser->isPlatformAdmin())) {
            return response()->json(['success' => false, 'error' => 'Unauthorized: Administrator credentials required.'], 403);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $migrateOutput = \Illuminate\Support\Facades\Artisan::output();

            \Illuminate\Support\Facades\Artisan::call('optimize:clear');
            $cacheOutput = \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Database migrations and application caches refreshed successfully!',
                'migrate_output' => trim($migrateOutput),
                'cache_output' => trim($cacheOutput),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
