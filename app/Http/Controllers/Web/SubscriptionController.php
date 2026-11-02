<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\SaaSSetting;
use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * Display the Tenant Business Owner's Subscription & Billing Hub.
     */
    public function index()
    {
        $authUser = Auth::user();
        $tenantId = session('tenant_id') ?? $authUser->tenant_id ?? 'default-tenant';
        $tenant = Tenant::find($tenantId);

        // Fallback for standalone/root instances
        if (!$tenant) {
            $tenant = new Tenant([
                'id' => $tenantId,
                'name' => config('saas.platform_name', 'Hysam POS'),
                'plan' => 'enterprise',
                'status' => 'active',
                'max_branches' => 999,
                'max_users' => 999,
                'trial_ends_at' => now()->addYears(10),
            ]);
        }

        // 1. Quota calculations
        $branchesUsed = Warehouse::where('tenant_id', $tenantId)->count();
        $usersUsed = User::where('tenant_id', $tenantId)->count();
        $maxBranches = (int) ($tenant->max_branches ?: 1);
        $maxUsers = (int) ($tenant->max_users ?: 3);

        $branchPercentage = $maxBranches > 0 ? min(100, round(($branchesUsed / $maxBranches) * 100)) : 100;
        $userPercentage = $maxUsers > 0 ? min(100, round(($usersUsed / $maxUsers) * 100)) : 100;

        // 2. Subscription Status & Expiry Days
        $status = $tenant->status ?: 'active';
        $trialEndsAt = $tenant->trial_ends_at;
        $daysRemaining = null;
        $isExpired = false;

        if ($trialEndsAt) {
            if ($trialEndsAt->isFuture()) {
                $daysRemaining = (int) now()->diffInDays($trialEndsAt);
            } else {
                $daysRemaining = 0;
                $isExpired = true;
            }
        }

        // 3. Dynamic Plan Pricing & Specs
        $currency = SaaSSetting::get('currency_symbol', '₦');
        $plansConfig = config('saas.plans', []);

        $plans = [
            'basic' => [
                'key' => 'basic',
                'name' => $plansConfig['basic']['name'] ?? 'Starter Plan',
                'price' => (float) SaaSSetting::get('monthly_price_basic', $plansConfig['basic']['price_monthly'] ?? 15000),
                'max_branches' => (int) ($plansConfig['basic']['max_branches'] ?? 1),
                'max_users' => (int) ($plansConfig['basic']['max_users'] ?? 3),
                'tagline' => 'Ideal for single retail shops & rising supermarkets',
                'features' => [
                    '1 Branch Retail Location',
                    'Up to 3 Cashier / Staff Accounts',
                    'Visual Point of Sale (POS)',
                    'Products Catalog & Barcode Printing',
                    'Receipt Printing & Customer Ledgers',
                    'Executive PDF & CSV Reports',
                ],
            ],
            'pro' => [
                'key' => 'pro',
                'name' => $plansConfig['pro']['name'] ?? 'Professional Growth',
                'price' => (float) SaaSSetting::get('monthly_price_pro', $plansConfig['pro']['price_monthly'] ?? 35000),
                'max_branches' => (int) ($plansConfig['pro']['max_branches'] ?? 5),
                'max_users' => (int) ($plansConfig['pro']['max_users'] ?? 15),
                'tagline' => 'Perfect for multi-branch stores & wholesale depots',
                'is_popular' => true,
                'features' => [
                    'Up to 5 Branch Locations',
                    'Up to 15 Cashier / Staff Accounts',
                    'Multi-Branch Stock Matrix & Inter-Shop Transfers',
                    'Waybills with Dispatch & Receive Tracking',
                    'Debtors Aging & Customer Credit Recovery',
                    'Dual-SKU Product Exchanges',
                    'Auditor Anti-Theft Hub & Activity Logs',
                ],
            ],
            'enterprise' => [
                'key' => 'enterprise',
                'name' => $plansConfig['enterprise']['name'] ?? 'Enterprise Multi-Branch',
                'price' => (float) SaaSSetting::get('monthly_price_enterprise', $plansConfig['enterprise']['price_monthly'] ?? 75000),
                'max_branches' => 999,
                'max_users' => 999,
                'tagline' => 'Unlimited power for high-volume retail chains & distributors',
                'features' => [
                    'Unlimited Retail Branches & Central Warehouses',
                    'Unlimited Staff, Cashier & Manager Accounts',
                    'Full Consolidated Multi-Branch Ledgers',
                    'Automated Database Backups & Cloud Storage',
                    'Executive Boardroom-Ready PDF Statements',
                    'Priority 24/7 Dedicated Account Manager',
                ],
            ],
        ];

        // 4. Platform Payment Details
        $paymentDetails = [
            'currency' => $currency,
            'bank_name' => SaaSSetting::get('bank_name', 'Zenith Bank Plc'),
            'bank_account_number' => SaaSSetting::get('bank_account_number', '1012345678'),
            'bank_account_name' => SaaSSetting::get('bank_account_name', 'Hysam Ventures SaaS Ltd'),
            'bank_instructions' => SaaSSetting::get('bank_instructions', 'Please pay into the account above and enter your payment reference below.'),
            'paystack_enabled' => (bool) SaaSSetting::get('paystack_enabled', '1'),
            'paystack_public_key' => SaaSSetting::get('paystack_public_key', ''),
            'support_email' => SaaSSetting::get('support_email', 'support@hysamventures.com'),
            'support_phone' => SaaSSetting::get('support_phone', '+234 800 000 0000'),
        ];

        return view('subscription.index', compact(
            'tenant',
            'branchesUsed',
            'usersUsed',
            'maxBranches',
            'maxUsers',
            'branchPercentage',
            'userPercentage',
            'status',
            'trialEndsAt',
            'daysRemaining',
            'isExpired',
            'plans',
            'paymentDetails'
        ));
    }

    /**
     * Switch or Upgrade Subscription Plan.
     */
    public function changePlan(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:basic,pro,enterprise',
        ]);

        $authUser = Auth::user();
        $tenantId = session('tenant_id') ?? $authUser->tenant_id;
        $tenant = Tenant::findOrFail($tenantId);

        $plansConfig = config('saas.plans', []);
        $targetPlan = $plansConfig[$request->plan] ?? null;

        if (!$targetPlan) {
            return back()->with('error', 'Invalid subscription plan selected.');
        }

        $tenant->update([
            'plan' => $request->plan,
            'max_branches' => $targetPlan['max_branches'] ?? 1,
            'max_users' => $targetPlan['max_users'] ?? 3,
        ]);

        // Audit log
        Activity::recordSecurityEvent(
            'SUBSCRIPTION_PLAN_CHANGED',
            "Upgraded subscription plan to {$targetPlan['name']}",
            ['plan' => $request->plan, 'max_branches' => $targetPlan['max_branches'] ?? 1],
            $authUser
        );

        return back()->with('success', "🎉 Your business plan has been successfully upgraded to {$targetPlan['name']}!");
    }

    /**
     * Initialize Paystack Payment for Plan Renewal.
     */
    public function initializePaystack(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:basic,pro,enterprise',
            'months' => 'nullable|integer|min:1|max:12',
        ]);

        $authUser = Auth::user();
        $tenantId = session('tenant_id') ?? $authUser->tenant_id;
        $tenant = Tenant::findOrFail($tenantId);

        $months = (int) ($request->months ?: 1);
        $planKey = $request->plan;

        $priceKey = 'monthly_price_' . $planKey;
        $monthlyPrice = (float) SaaSSetting::get($priceKey, config("saas.plans.{$planKey}.price_monthly", 15000));
        $totalAmount = $monthlyPrice * $months;

        $reference = 'VMPOS-' . strtoupper(Str::random(10));
        $email = $tenant->owner_email ?: $authUser->email;

        $secretKey = SaaSSetting::get('paystack_secret_key', '');
        if (empty($secretKey)) {
            return back()->with('error', 'Online payment gateway is not yet configured by the platform. Please use direct bank transfer.');
        }

        try {
            $response = Http::withToken($secretKey)->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => (int) ($totalAmount * 100), // in kobo
                'reference' => $reference,
                'callback_url' => route('subscription.paystack.callback'),
                'metadata' => [
                    'tenant_id' => $tenant->id,
                    'user_id' => $authUser->id,
                    'plan' => $planKey,
                    'months' => $months,
                ],
            ]);

            if ($response->successful() && isset($response->json()['data']['authorization_url'])) {
                return redirect($response->json()['data']['authorization_url']);
            }

            return back()->with('error', 'Unable to start payment session: ' . ($response->json()['message'] ?? 'Please try again.'));
        } catch (\Exception $e) {
            return back()->with('error', 'Gateway error: ' . $e->getMessage());
        }
    }

    /**
     * Handle Paystack Webhook / Return Callback.
     */
    public function handlePaystackCallback(Request $request)
    {
        $reference = $request->get('reference');
        if (!$reference) {
            return redirect()->route('subscription.index')->with('error', 'Missing transaction reference.');
        }

        $secretKey = SaaSSetting::get('paystack_secret_key', '');
        $isVerified = false;
        $meta = [];
        $amountPaidKobo = 0;

        if (empty($secretKey)) {
            return redirect()->route('subscription.index')->with('error', '⚠️ Automated payment processing is temporarily offline (Gateway key unconfigured). Please submit your bank transfer notice below.');
        }

        try {
            $response = Http::withToken($secretKey)->get("https://api.paystack.co/transaction/verify/{$reference}");
            if ($response->successful()) {
                $payData = $response->json()['data'] ?? [];
                if (($payData['status'] ?? '') === 'success') {
                    $isVerified = true;
                    $meta = $payData['metadata'] ?? [];
                    $amountPaidKobo = (int) ($payData['amount'] ?? 0);
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Paystack verification error for ref {$reference}: " . $e->getMessage());
        }

        if (!$isVerified) {
            Activity::recordSecurityEvent(
                'PAYSTACK_SUBSCRIPTION_VERIFICATION_FAILED',
                "Failed or unconfirmed Paystack callback with reference '{$reference}'.",
                ['reference' => $reference]
            );
            return redirect()->route('subscription.index')->with('error', '⚠️ Payment verification failed or transaction is incomplete. Please contact support if you were debited.');
        }

        // 🔒 Anti-Replay Protection: Ensure reference has not been fulfilled already
        $alreadyFulfilled = Activity::withoutGlobalScopes()
            ->where('action', 'PAYSTACK_SUBSCRIPTION_ACTIVATED')
            ->where('metadata->reference', $reference)
            ->exists();

        if ($alreadyFulfilled) {
            return redirect()->route('subscription.index')->with('error', '⚠️ Transaction reference already processed. Duplicate activation rejected.');
        }

        $authUser = Auth::user();
        $tenantId = $meta['tenant_id'] ?? session('tenant_id') ?? $authUser?->tenant_id;
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return redirect()->route('subscription.index')->with('error', 'Tenant account not found.');
        }

        $months = max(1, (int) ($meta['months'] ?? 1));
        $planKey = $meta['plan'] ?? $tenant->plan;

        $plansConfig = config('saas.plans', []);
        $targetPlan = $plansConfig[$planKey] ?? null;

        // Extend expiration
        $baseDate = ($tenant->trial_ends_at && $tenant->trial_ends_at->isFuture()) ? $tenant->trial_ends_at : now();
        $newExpiry = $baseDate->copy()->addDays($months * 30);

        $tenant->update([
            'status' => 'active',
            'plan' => $planKey,
            'trial_ends_at' => $newExpiry,
            'max_branches' => $targetPlan['max_branches'] ?? $tenant->max_branches,
            'max_users' => $targetPlan['max_users'] ?? $tenant->max_users,
        ]);

        Activity::recordSecurityEvent(
            'PAYSTACK_SUBSCRIPTION_ACTIVATED',
            "Automated subscription payment verified via Paystack. Plan: {$planKey}, Duration: {$months} month(s), Amount: ₦" . number_format($amountPaidKobo / 100, 2) . " (Ref: {$reference}).",
            [
                'reference' => $reference,
                'tenant_id' => $tenant->id,
                'plan' => $planKey,
                'months' => $months,
                'amount_kobo' => $amountPaidKobo,
                'new_expiry' => $newExpiry->toIso8601String(),
            ]
        );

        return redirect()->route('subscription.index')->with('success', "💳 Payment confirmed! Your {$tenant->name} subscription is active until " . $newExpiry->format('M d, Y') . ".");
    }

    /**
     * Record Direct Bank Transfer Payment Notice.
     */
    public function recordManualPaymentNotice(Request $request)
    {
        $request->validate([
            'bank_paid_from' => 'required|string|max:100',
            'reference' => 'required|string|max:100',
            'amount_paid' => 'required|numeric|min:1000',
            'plan' => 'required|in:basic,pro,enterprise',
        ]);

        $authUser = Auth::user();
        $tenantId = session('tenant_id') ?? $authUser->tenant_id;
        $tenant = Tenant::findOrFail($tenantId);

        Activity::recordSecurityEvent(
            'MANUAL_SUBSCRIPTION_PAYMENT_SUBMITTED',
            "Submitted bank transfer of ₦" . number_format($request->amount_paid, 2) . " from {$request->bank_paid_from} (Ref: {$request->reference}) for {$request->plan} plan.",
            [
                'status' => 'pending',
                'amount' => (float) $request->amount_paid,
                'bank' => $request->bank_paid_from,
                'reference' => $request->reference,
                'plan' => $request->plan,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'owner_email' => $tenant->owner_email ?: $authUser->email,
                'owner_phone' => $tenant->owner_phone ?: '',
                'submitted_at' => now()->toIso8601String(),
            ],
            $authUser
        );

        return back()->with('success', '✅ Payment notice submitted successfully! The platform accounts department will confirm your transfer shortly.');
    }
}
