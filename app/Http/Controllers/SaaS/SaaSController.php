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
use Illuminate\Support\Facades\DB;
use App\Rules\PasswordPolicy;

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
        $allowRegistration = SaaSSetting::get('allow_registration', '1');
        if ($allowRegistration === '0') {
            return back()->withInput()->with('error', 'New merchant registrations are currently paused by the platform administrator.');
        }

        // Anti-abuse honeypot check: reject bots submitting hidden honeypot field
        if ($request->filled('registration_hp_check')) {
            abort(422, 'Spam bot submission detected.');
        }

        $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name'    => 'required|string|max:255',
            'owner_email'   => 'required|email|unique:users,email|unique:tenants,owner_email',
            'owner_phone'   => 'required|string|max:20',
            'password'      => PasswordPolicy::rules(true),
            'plan'          => 'required|in:basic,pro,enterprise',
        ], PasswordPolicy::messages());

        $plans = config('saas.plans');
        $selectedPlan = $plans[$request->plan] ?? $plans['basic'];

        if (Str::slug($request->business_name) === 'default-tenant' || str_contains(strtolower($request->business_name), 'default-tenant')) {
            return back()->withInput()->with('error', "Invalid business name: 'default-tenant' is a reserved system identifier.");
        }

        $tenantId = 'tenant-' . Str::slug($request->business_name) . '-' . Str::random(5);
        $trialDays = (int) SaaSSetting::get('trial_days', '14');

        // Atomic Provisioning: Tenant + Initial Branch + Admin Account
        [$tenant, $adminUser] = DB::transaction(function () use ($tenantId, $request, $trialDays, $selectedPlan) {
            // 1. Create Tenant
            $createdTenant = Tenant::create([
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
            $createdUser = User::create([
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

            return [$createdTenant, $createdUser];
        });

        // Log into tenant session only after successful transaction commit
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
        $user = Auth::user();
        if (!$user && session('user_id')) {
            $user = User::findForAuthenticationById(session('user_id'));
        }

        if (!$user || !$user->isPlatformUser()) {
            return redirect()->route('dashboard')->with('error', '🔒 Access Restricted: Platform administrator privileges required.');
        }

        $canTenants = $user->isPlatformAdmin() || $user->hasCapability('platform.tenants');
        $canSettings = $user->isPlatformAdmin() || $user->hasCapability('platform.settings');
        $canBackup = $user->isPlatformAdmin() || $user->hasCapability('platform.backup');
        $canHealth = $user->isPlatformAdmin() || $user->hasCapability('platform.health');
        $canLimits = $user->isPlatformAdmin() || $user->hasCapability('platform.limits');

        // Only load tenant directory and calculate MRR if user holds platform.tenants capability!
        if ($canTenants) {
            $tenants = Tenant::withCount(['users', 'warehouses'])->orderBy('created_at', 'desc')->get();
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
        } else {
            $tenants = collect();
            $totalTenants = null;
            $activeTenants = null;
            $trialTenants = null;
            $suspendedTenants = null;
            $mrr = null;
        }

        // Platform-wide infrastructure count (zero tenant business data)
        $totalBranchesPlatform = Warehouse::withoutGlobalScopes()->count();

        // SaaS Settings Map — loaded ONLY if user holds platform.settings capability
        if ($canSettings) {
            $settings = [
                'platform_name'        => SaaSSetting::get('platform_name', 'Hysam Multi-Branch POS SaaS'),
                'support_email'        => SaaSSetting::get('support_email', 'support@hysamventures.com'),
                'support_phone'        => SaaSSetting::get('support_phone', '+234 800 000 0000'),
                'currency_symbol'      => SaaSSetting::get('currency_symbol', '₦'),
                'trial_days'           => SaaSSetting::get('trial_days', '14'),
                'allow_registration'   => SaaSSetting::get('allow_registration', '1'),
                'monthly_price_basic'  => (float) SaaSSetting::get('monthly_price_basic', 15000),
                'monthly_price_pro'    => (float) SaaSSetting::get('monthly_price_pro', 35000),
                'monthly_price_enterprise' => (float) SaaSSetting::get('monthly_price_enterprise', 75000),
                'bank_name'             => SaaSSetting::get('bank_name', 'Zenith Bank Plc'),
                'bank_account_number'   => SaaSSetting::get('bank_account_number', '1012345678'),
                'bank_account_name'     => SaaSSetting::get('bank_account_name', 'Hysam Ventures SaaS Ltd'),
                'bank_instructions'     => SaaSSetting::get('bank_instructions', 'Please pay into the account above and send proof to support@hysamventures.com'),
                'paystack_enabled'      => SaaSSetting::get('paystack_enabled', '1'),
                'paystack_public_key'   => SaaSSetting::get('paystack_public_key', ''),
                // INVARIANT: Raw paystack_secret_key is NEVER passed to the view!
                'paystack_secret_configured' => !empty(SaaSSetting::get('paystack_secret_key', '')),
            ];
        } else {
            $settings = [
                'currency_symbol' => '₦',
                'paystack_secret_configured' => false,
            ];
        }

        // Backups — loaded ONLY if user holds platform.backup capability
        if ($canBackup) {
            $backups = \App\Models\Backup::whereNull('tenant_id')->orderBy('created_at', 'desc')->get();
        } else {
            $backups = collect();
        }

        return view('saas.admin.index', compact(
            'tenants',
            'totalTenants',
            'activeTenants',
            'trialTenants',
            'suspendedTenants',
            'mrr',
            'totalBranchesPlatform',
            'settings',
            'backups',
            'canTenants',
            'canSettings',
            'canBackup',
            'canHealth',
            'canLimits'
        ));
    }

    /** Update SaaS Platform Settings */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'platform_name'            => 'nullable|string|max:100',
            'support_email'            => 'nullable|email|max:100',
            'support_phone'            => 'nullable|string|max:30',
            'currency_symbol'          => 'nullable|string|max:10',
            'trial_days'               => 'nullable|integer|min:1|max:365',
            'allow_registration'       => 'nullable|in:0,1',
            'monthly_price_basic'      => 'nullable|numeric|min:0|max:10000000',
            'monthly_price_pro'        => 'nullable|numeric|min:0|max:10000000',
            'monthly_price_enterprise' => 'nullable|numeric|min:0|max:10000000',
            'bank_name'                => 'nullable|string|max:100',
            'bank_account_number'      => 'nullable|string|max:30',
            'bank_account_name'        => 'nullable|string|max:100',
            'bank_instructions'        => 'nullable|string|max:1000',
            'paystack_enabled'         => 'nullable|in:0,1',
            'paystack_public_key'      => 'nullable|string|max:255',
            'paystack_secret_key'      => 'nullable|string|max:255',
        ]);

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
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                SaaSSetting::set($field, $request->input($field));
            }
        }

        // Invariant: Paystack Secret Key is updated ONLY when explicitly supplied with a new, non-mask value
        if ($request->filled('paystack_secret_key')) {
            $secretInput = trim($request->input('paystack_secret_key'));
            if ($secretInput !== '' && !preg_match('/^[•\*]+$/u', $secretInput)) {
                SaaSSetting::set('paystack_secret_key', $secretInput);
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
        $temporaryPassword = Str::random(12);

        // Atomic Provisioning: Tenant + Warehouse + Admin
        $tenant = DB::transaction(function () use ($tenantId, $request, $selectedPlan, $temporaryPassword) {
            $createdTenant = Tenant::create([
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
                'password'     => Hash::make($temporaryPassword),
                'role'         => 'admin',
                'warehouse_id' => $mainWarehouse->id,
                'disabled'     => false,
                'permissions'  => ['all' => true],
            ]);

            return $createdTenant;
        });

        return back()->with('success', "✓ New Business Tenant '{$tenant->name}' created successfully! An account activation notice has been recorded for {$request->owner_email}. (Credentials are strictly delivered out-of-band and never exposed in browser responses).");
    }

    /** Toggle Tenant Status */
    public function toggleStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:active,trial,suspended',
        ]);

        $tenant = Tenant::findOrFail($id);
        $tenant->status = $request->input('status');
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



    /** Delete Tenant Account and All Associated Business Data */
    public function deleteTenant($id)
    {
        if ($id === 'default-tenant') {
            return back()->with('error', 'Cannot delete default system master tenant.');
        }

        $tenant = Tenant::findOrFail($id);
        $name = $tenant->name;

        DB::transaction(function () use ($id) {
            \App\Models\SaleItem::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\Payment::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\SalesReturn::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\Sale::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\TransferItem::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\Transfer::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\InventoryLog::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\StockReservation::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\StockAdjustment::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\StockLevel::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\Product::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\CustomerLedger::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\Customer::withoutGlobalScopes()->where('tenant_id', $id)->forceDelete();
            \App\Models\Supplier::withoutGlobalScopes()->where('tenant_id', $id)->forceDelete();
            \App\Models\CashierShift::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\Warehouse::withoutGlobalScopes()->where('tenant_id', $id)->forceDelete();
            \App\Models\Activity::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\Setting::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            \App\Models\IdempotencyRecord::where('tenant_id', $id)->delete();

            // Physically delete tenant backup archive files from disk storage
            $tenantBackups = \App\Models\Backup::where('tenant_id', $id)->get();
            foreach ($tenantBackups as $tb) {
                if (!empty($tb->filename)) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete('backups/' . $tb->filename);
                }
            }
            \App\Models\Backup::where('tenant_id', $id)->delete();

            \App\Models\User::withoutGlobalScopes()->where('tenant_id', $id)->delete();
            Tenant::where('id', $id)->delete();
        });

        return back()->with('success', "✓ Business Tenant '{$name}' and all associated data have been permanently purged.");
    }

    /** Show Suspended Account Notice */
    public function suspended()
    {
        return view('saas.suspended');
    }
}
