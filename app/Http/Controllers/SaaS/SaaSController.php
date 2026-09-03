<?php

namespace App\Http\Controllers\SaaS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaaSSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class SaaSController extends Controller
{
    /** Show Tenant Self-Registration Form */
    public function registerForm()
    {
        $allowRegistration = SaaSSetting::get('allow_registration', '1');
        if ($allowRegistration === '0') {
            return view('saas.suspended')->with('error', 'Public self-registration is currently closed by the platform owner.');
        }

        $settings = [
            'platform_name'   => SaaSSetting::get('platform_name', 'Hysam Multi-Branch POS SaaS'),
            'currency_symbol' => SaaSSetting::get('currency_symbol', '₦'),
            'trial_days'      => SaaSSetting::get('trial_days', '14'),
            'price_basic'     => SaaSSetting::get('monthly_price_basic', '15000'),
            'price_pro'       => SaaSSetting::get('monthly_price_pro', '35000'),
            'price_enterprise'=> SaaSSetting::get('monthly_price_enterprise', '75000'),
        ];

        return view('saas.register', compact('settings'));
    }

    /** Process Tenant Self-Registration */
    public function processRegister(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name'    => 'required|string|max:255',
            'owner_email'   => 'required|email|unique:users,email|unique:tenants,owner_email',
            'owner_phone'   => 'required|string|max:20',
            'password'      => 'required|min:6',
            'plan'          => 'required|in:basic,pro,enterprise',
        ]);

        $plans = config('saas.plans');
        $selectedPlan = $plans[$request->plan] ?? $plans['basic'];
        $tenantId = 'tenant-' . Str::slug($request->business_name) . '-' . Str::random(5);
        $trialDays = (int) SaaSSetting::get('trial_days', '14');

        // 1. Create Tenant
        $tenant = Tenant::create([
            'id'           => $tenantId,
            'name'         => $request->business_name,
            'owner_email'  => $request->owner_email,
            'owner_phone'  => $request->owner_phone,
            'plan'         => $request->plan,
            'status'       => 'trial',
            'trial_ends_at'=> now()->addDays($trialDays),
            'max_branches' => $selectedPlan['max_branches'],
            'max_users'    => $selectedPlan['max_users'],
        ]);

        // 2. Create Initial Main Branch for the Tenant
        $mainWarehouse = Warehouse::create([
            'tenant_id'    => $tenantId,
            'name'         => 'Main Headquarters',
            'code'         => 'HQ-' . strtoupper(Str::random(5)),
            'address'      => 'Main Address',
            'phone'        => $request->owner_phone,
            'manager_name' => $request->owner_name,
            'is_active'    => true,
        ]);

        // 3. Create Tenant Super Admin User
        $adminUser = User::create([
            'id'           => (string) Str::uuid(),
            'tenant_id'    => $tenantId,
            'name'         => $request->owner_name,
            'email'        => strtolower(trim($request->owner_email)),
            'password'     => Hash::make($request->password),
            'role'         => 'admin',
            'warehouse_id' => $mainWarehouse->id,
            'disabled'     => false,
            'permissions'  => ['all' => true],
        ]);

        // Log into tenant session
        session([
            'user_id'   => $adminUser->id,
            'user_name' => $adminUser->name,
            'user_role' => $adminUser->role,
            'tenant_id' => $tenantId,
        ]);
        Auth::login($adminUser);

        return redirect('/')->with('success', "Welcome to {$tenant->name}! Your {$trialDays}-day free trial has started.");
    }

    /** Super Admin Master SaaS Dashboard & Control Panel */
    public function adminIndex()
    {
        if (config('saas.enabled') && session('tenant_id') !== 'default-tenant' && !session('is_impersonating')) {
            return redirect()->route('dashboard')->with('error', '🔒 Access Restricted: Only the SaaS Super Admin can access the Master Control Panel.');
        }

        $tenants = Tenant::withCount(['users', 'warehouses', 'sales'])->orderBy('created_at', 'desc')->get();
        $totalTenants = $tenants->count();
        $activeTenants = $tenants->where('status', 'active')->count();
        $trialTenants = $tenants->where('status', 'trial')->count();
        $suspendedTenants = $tenants->where('status', 'suspended')->count();

        // Calculate Monthly Recurring Revenue (MRR)
        $priceBasic = (float) SaaSSetting::get('monthly_price_basic', 15000);
        $pricePro = (float) SaaSSetting::get('monthly_price_pro', 35000);
        $priceEnterprise = (float) SaaSSetting::get('monthly_price_enterprise', 75000);

        $mrr = 0;
        foreach ($tenants->where('status', 'active') as $t) {
            if ($t->plan === 'basic') $mrr += $priceBasic;
            elseif ($t->plan === 'pro') $mrr += $pricePro;
            elseif ($t->plan === 'enterprise') $mrr += $priceEnterprise;
        }

        // Platform-wide counts (bypassing tenant scopes)
        $totalBranchesPlatform = Warehouse::withoutGlobalScopes()->count();
        $totalProductsPlatform = Product::withoutGlobalScopes()->count();
        $totalSalesPlatform = Sale::withoutGlobalScopes()->count();

        // SaaS Settings Map
        $settings = [
            'platform_name'        => SaaSSetting::get('platform_name', 'Hysam Multi-Branch POS SaaS'),
            'support_email'        => SaaSSetting::get('support_email', 'support@hysamventures.com'),
            'support_phone'        => SaaSSetting::get('support_phone', '+234 800 000 0000'),
            'currency_symbol'      => SaaSSetting::get('currency_symbol', '₦'),
            'trial_days'           => SaaSSetting::get('trial_days', '14'),
            'allow_registration'   => SaaSSetting::get('allow_registration', '1'),
            'monthly_price_basic'  => $priceBasic,
            'monthly_price_pro'    => $pricePro,
            'monthly_price_enterprise' => $priceEnterprise,
            'bank_name'             => SaaSSetting::get('bank_name', 'Zenith Bank Plc'),
            'bank_account_number'   => SaaSSetting::get('bank_account_number', '1012345678'),
            'bank_account_name'     => SaaSSetting::get('bank_account_name', 'Hysam Ventures SaaS Ltd'),
            'bank_instructions'     => SaaSSetting::get('bank_instructions', 'Please pay into the account above and send proof to support@hysamventures.com'),
            'paystack_enabled'      => SaaSSetting::get('paystack_enabled', '1'),
            'paystack_public_key'   => SaaSSetting::get('paystack_public_key', ''),
            'paystack_secret_key'   => SaaSSetting::get('paystack_secret_key', ''),
        ];

        $backups = \App\Models\Backup::orderBy('created_at', 'desc')->get();

        return view('saas.admin.index', compact(
            'tenants',
            'totalTenants',
            'activeTenants',
            'trialTenants',
            'suspendedTenants',
            'mrr',
            'totalBranchesPlatform',
            'totalProductsPlatform',
            'totalSalesPlatform',
            'settings',
            'backups'
        ));
    }

    /** Update SaaS Platform Settings */
    public function updateSettings(Request $request)
    {
        $fields = [
            'platform_name',
            'support_email',
            'support_phone',
            'currency_symbol',
            'trial_days',
            'allow_registration',
            'monthly_price_basic',
            'monthly_price_pro',
            'monthly_price_enterprise',
            'bank_name',
            'bank_account_number',
            'bank_account_name',
            'bank_instructions',
            'paystack_enabled',
            'paystack_public_key',
            'paystack_secret_key',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                SaaSSetting::set($field, $request->input($field));
            }
        }

        return back()->with('success', '✓ SaaS Platform settings updated successfully!');
    }

    /** Create Tenant Account Manually from Control Panel */
    public function storeTenant(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name'    => 'required|string|max:255',
            'owner_email'   => 'required|email|unique:users,email|unique:tenants,owner_email',
            'owner_phone'   => 'required|string|max:20',
            'plan'          => 'required|in:basic,pro,enterprise',
            'status'        => 'required|in:active,trial,suspended',
        ]);

        $plans = config('saas.plans');
        $selectedPlan = $plans[$request->plan] ?? $plans['basic'];
        $tenantId = 'tenant-' . Str::slug($request->business_name) . '-' . Str::random(5);

        $tenant = Tenant::create([
            'id'           => $tenantId,
            'name'         => $request->business_name,
            'owner_email'  => $request->owner_email,
            'owner_phone'  => $request->owner_phone,
            'plan'         => $request->plan,
            'status'       => $request->status,
            'trial_ends_at'=> now()->addDays((int) SaaSSetting::get('trial_days', '14')),
            'max_branches' => $selectedPlan['max_branches'],
            'max_users'    => $selectedPlan['max_users'],
        ]);

        $mainWarehouse = Warehouse::create([
            'tenant_id'    => $tenantId,
            'name'         => 'Main Headquarters',
            'code'         => 'HQ-' . strtoupper(Str::random(5)),
            'address'      => 'HQ Address',
            'phone'        => $request->owner_phone,
            'manager_name' => $request->owner_name,
            'is_active'    => true,
        ]);

        User::create([
            'id'           => (string) Str::uuid(),
            'tenant_id'    => $tenantId,
            'name'         => $request->owner_name,
            'email'        => strtolower(trim($request->owner_email)),
            'password'     => Hash::make('password123'),
            'role'         => 'admin',
            'warehouse_id' => $mainWarehouse->id,
            'disabled'     => false,
            'permissions'  => ['all' => true],
        ]);

        return back()->with('success', "✓ New Business Tenant '{$tenant->name}' created successfully with default password 'password123'!");
    }

    /** Toggle Tenant Status */
    public function toggleStatus(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->status = $request->input('status', 'active');
        $tenant->save();

        return back()->with('success', "✓ Tenant status for '{$tenant->name}' updated to '{$tenant->status}'.");
    }

    /** Update Custom Plan & Branch Limits per Tenant */
    public function updateTenantLimits(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);
        
        if ($request->has('plan')) {
            $tenant->plan = $request->input('plan');
        }
        if ($request->has('max_branches')) {
            $tenant->max_branches = (int) $request->input('max_branches');
        }
        if ($request->has('max_users')) {
            $tenant->max_users = (int) $request->input('max_users');
        }
        if ($request->input('extend_trial')) {
            $days = (int) $request->input('extend_trial');
            $baseDate = ($tenant->trial_ends_at && $tenant->trial_ends_at->isFuture()) ? $tenant->trial_ends_at : now();
            $tenant->trial_ends_at = $baseDate->addDays($days);
            $tenant->status = 'trial';
        }

        $tenant->save();

        return back()->with('success', "✓ Subscription plan & custom limits updated for '{$tenant->name}'!");
    }

    /** 1-Click Tenant Impersonation */
    /** 1-Click Tenant Impersonation */
    public function impersonateTenant($id)
    {
        $caller = Auth::user();
        if (!$caller || !$caller->isSuperAdmin()) {
            abort(403, 'Forbidden. Only platform super-administrators can initiate impersonation.');
        }

        $tenant = Tenant::findOrFail($id);
        $adminUser = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'admin')->first();

        if (!$adminUser) {
            return back()->with('error', "No admin user found for tenant '{$tenant->name}'.");
        }

        session([
            'impersonator_id' => $caller->id,
            'user_id'         => $adminUser->id,
            'user_name'       => $adminUser->name,
            'user_role'       => $adminUser->role,
            'tenant_id'       => $tenant->id,
            'is_impersonating' => true,
        ]);
        Auth::login($adminUser);

        return redirect('/')->with('info', "👁️ You are now impersonating '{$tenant->name}'.");
    }

    /** Stop Impersonation & Return to Master Super Admin */
    public function stopImpersonation(Request $request)
    {
        $isImpersonating = session('is_impersonating');
        $impersonatorId = session('impersonator_id');

        // Strictly verify active impersonation session
        if (!$isImpersonating || empty($impersonatorId)) {
            abort(403, 'Forbidden: No active impersonation session to stop.');
        }

        $masterAdmin = User::findForAuthenticationById($impersonatorId);

        // Strictly verify that the recorded impersonator is genuinely a platform super-admin
        if (!$masterAdmin || !$masterAdmin->isSuperAdmin()) {
            Auth::logout();
            session()->flush();
            $request->session()->regenerate();
            return redirect()->route('login')->with('error', 'Invalid impersonation state. Session cleared for security.');
        }

        // Cleanly wipe impersonation context and regenerate session
        session()->forget(['is_impersonating', 'impersonator_id']);
        $request->session()->regenerate();

        session([
            'user_id'   => $masterAdmin->id,
            'user_name' => $masterAdmin->name,
            'user_role' => $masterAdmin->role,
            'tenant_id' => 'default-tenant',
        ]);
        Auth::login($masterAdmin);

        return redirect()->route('saas.admin.index')->with('success', "✓ Stopped impersonation. Returned to SaaS Master Control Panel.");
    }

    /** Delete Tenant Account */
    public function deleteTenant($id)
    {
        if ($id === 'default-tenant') {
            return back()->with('error', 'Cannot delete default system master tenant.');
        }

        $tenant = Tenant::findOrFail($id);
        $name = $tenant->name;
        $tenant->delete();

        return back()->with('success', "✓ Business Tenant '{$name}' has been deleted.");
    }

    /** Show Suspended Account Notice */
    public function suspended()
    {
        return view('saas.suspended');
    }
}
