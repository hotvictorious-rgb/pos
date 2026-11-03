<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\Sale;
use App\Models\Payment;
use App\Models\CustomerLedger;
use App\Models\SalesReturn;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Activity;
use App\Models\Tenant;
use App\Services\Notification\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendDailyWhatsAppReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:send-daily-whatsapp 
                            {--date= : Specific date to report (YYYY-MM-DD, defaults to today)} 
                            {--tenant= : Scope to specific tenant ID} 
                            {--force : Force dispatch even if disabled in tenant settings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile end-of-day (9:00 PM) financial reconciliation summary and dispatch to store owner WhatsApp';

    protected WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        parent::__construct();
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $targetDateStr = $this->option('date') ?: Carbon::today()->toDateString();
        $targetDate = Carbon::parse($targetDateStr);
        $forced = (bool) $this->option('force');
        $tenantFilter = $this->option('tenant');

        $this->info("🚀 Starting 9:00 PM Daily WhatsApp Reconciliation Report for {$targetDateStr}...");

        $tenantsQuery = Tenant::query();
        if ($tenantFilter) {
            $tenantsQuery->where('id', $tenantFilter);
        }

        // Check if SaaS is enabled and tenants table is populated
        $tenants = config('saas.enabled') && $tenantsQuery->exists()
            ? $tenantsQuery->where('status', 'active')->get()
            : collect([null]); // Standalone / single-tenant mode

        $totalDispatched = 0;

        foreach ($tenants as $tenant) {
            $tenantId = $tenant ? $tenant->id : 'default-tenant';
            $tenantName = $tenant ? $tenant->name : config('app.name', 'Victorious POS');

            $setting = Setting::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$setting) {
                $setting = Setting::withoutGlobalScopes()->first();
            }

            if (!$setting) {
                $this->warn("⚠️ No Settings found for tenant {$tenantId}. Skipping.");
                continue;
            }

            $ownerPhone = trim($setting->owner_whatsapp_phone ?? '');
            $reportEnabled = (bool) ($setting->whatsapp_report_enabled ?? false);

            if (!$forced && !$reportEnabled) {
                $this->line("ℹ️ WhatsApp daily reporting disabled in settings for {$tenantName} ({$tenantId}). Use --force to override.");
                continue;
            }

            if (empty($ownerPhone)) {
                $this->warn("⚠️ WhatsApp reporting enabled for {$tenantName}, but no owner_whatsapp_phone is configured in Settings.");
                continue;
            }

            $this->info("📊 Compiling daily metrics for: {$tenantName} (Recipient: {$ownerPhone})...");

            $reportData = $this->compileDailyMetrics($tenantId, $targetDate, $setting, $tenantName);
            $message = $this->whatsAppService->buildDailyReportMessage($reportData);

            // 1. Dispatch to Store Owner
            $result = $this->whatsAppService->sendMessage($ownerPhone, $message, $tenantId);

            if ($result['success']) {
                $this->info("✅ WhatsApp report successfully dispatched to owner ({$ownerPhone}) via {$result['gateway']}!");
                $totalDispatched++;

                // Record immutable audit activity
                Activity::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'type' => 'WHATSAPP_DAILY_REPORT_SENT',
                    'description' => "Automated 9:00 PM daily reconciliation report sent to store owner ({$ownerPhone})",
                    'userId' => 'SYSTEM_CRON',
                    'userName' => 'Automated POS Reporter',
                    'timestamp' => now()->toIso8601String(),
                ]);
            } else {
                $this->error("❌ Failed to send WhatsApp report to owner ({$ownerPhone}): " . ($result['error'] ?? 'Unknown error'));
            }

            // 2. Dispatch to Branch Managers (if configured on individual warehouses)
            $warehouses = Warehouse::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereNotNull('manager_whatsapp_phone')
                ->where('manager_whatsapp_phone', '!=', '')
                ->where('whatsapp_report_enabled', true)
                ->get();

            foreach ($warehouses as $wh) {
                $whPhone = trim($wh->manager_whatsapp_phone);
                if (empty($whPhone) || $whPhone === $ownerPhone) {
                    continue; // Skip if empty or same as owner (avoid duplicate)
                }

                $branchData = $this->compileDailyMetrics($tenantId, $targetDate, $setting, $tenantName, $wh->id);
                $branchMsg = $this->whatsAppService->buildDailyReportMessage($branchData);

                $bResult = $this->whatsAppService->sendMessage($whPhone, $branchMsg, $tenantId);
                if ($bResult['success']) {
                    $this->info("   ↳ Dispatched branch report to {$wh->name} manager ({$whPhone})");
                    $totalDispatched++;
                }
            }
        }

        $this->info("🎉 Completed Daily WhatsApp Reconciliation Dispatch. Total messages sent: {$totalDispatched}.");
        return self::SUCCESS;
    }

    /**
     * Compile complete end-of-day financial & logistical metrics.
     */
    protected function compileDailyMetrics(string $tenantId, Carbon $targetDate, Setting $setting, string $businessName, ?int $warehouseId = null): array
    {
        $dateStart = $targetDate->copy()->startOfDay();
        $dateEnd = $targetDate->copy()->endOfDay();

        // 1. Sales query
        $salesQuery = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('createdAt', [$dateStart, $dateEnd])
            ->whereNotIn('status', ['VOIDED', 'CANCELLED']);

        if ($warehouseId !== null) {
            $salesQuery->where('warehouse_id', $warehouseId);
        }

        $sales = $salesQuery->with('payments')->get();
        $totalInvoices = $sales->count();
        $grossSales = (float) $sales->sum('totalAmount');
        $saleIds = $sales->pluck('id')->toArray();

        // 2. Payments on today's sales
        $cashSales = (float) Payment::withoutGlobalScopes()
            ->whereIn('saleId', $saleIds)
            ->where('method', 'CASH')
            ->where('amount', '>', 0)
            ->sum('amount');

        $posSales = (float) Payment::withoutGlobalScopes()
            ->whereIn('saleId', $saleIds)
            ->where('method', 'POS')
            ->where('amount', '>', 0)
            ->sum('amount');

        $transferSales = (float) Payment::withoutGlobalScopes()
            ->whereIn('saleId', $saleIds)
            ->where('method', 'TRANSFER')
            ->where('amount', '>', 0)
            ->sum('amount');

        // 3. Customer Debt Recoveries today
        $debtRecoveryQuery = CustomerLedger::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd])
            ->where('type', 'PAYMENT')
            ->where('notes', 'not like', '[REVERSED%');

        if ($warehouseId !== null) {
            $debtRecoveryQuery->where('warehouse_id', $warehouseId);
        }

        $debtsRecovered = abs((float) $debtRecoveryQuery->sum('amount'));

        // 4. Returns & Refunds Outflows
        $returnsQuery = SalesReturn::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd]);

        if ($warehouseId !== null) {
            $returnsQuery->where('warehouse_id', $warehouseId);
        }

        $returnsRefunded = (float) $returnsQuery->sum('refundAmount');

        // 5. Net Cash in Drawer for Deposit:
        // (Cash from sales) + (Debt recovery cash) - (Cash refunds)
        $expectedDrawerCash = max(0.0, round($cashSales + $debtsRecovered - $returnsRefunded, 2));

        // 6. New Debt Incurred today (Sales total minus payments made on invoice)
        $totalPaidOnTodaySales = (float) Payment::withoutGlobalScopes()
            ->whereIn('saleId', $saleIds)
            ->where('amount', '>', 0)
            ->whereNotIn('method', ['REFUND', 'REFUND_CASH'])
            ->sum('amount');
        $newDebtsIncurred = max(0.0, round($grossSales - $totalPaidOnTodaySales, 2));

        // 7. Reversals & Voids audit logs
        $reversalsQuery = Activity::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd])
            ->whereIn('type', [
                'TRANSACTION_VOIDED',
                'DEBT_PAYMENT_REVERSED',
                'STOCK_IN_VOIDED',
                'STOCK_OUT_VOIDED',
            ]);

        $reversalsCount = $reversalsQuery->count();
        $reversalsDetails = [];
        $reversalsAmount = 0.0;

        foreach ($reversalsQuery->take(5)->get() as $act) {
            $revAmt = 0.0;
            if (preg_match('/₦([0-9,\.]+)/', $act->description, $m)) {
                $revAmt = (float) str_replace(',', '', $m[1]);
            }
            $reversalsAmount += $revAmt;
            $reversalsDetails[] = [
                'ref' => $act->type,
                'amount' => $revAmt,
                'user' => $act->userName ?: 'Supervisor',
                'reason' => Str::limit($act->description, 35),
            ];
        }

        // 8. Branch Breakdown (if aggregate report)
        $branchesBreakdown = [];
        $whName = 'All Branches (Consolidated)';

        if ($warehouseId !== null) {
            $whObj = Warehouse::find($warehouseId);
            $whName = $whObj ? $whObj->name : "Branch #{$warehouseId}";
        } elseif ($setting->whatsapp_branch_breakdown) {
            $branches = Warehouse::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get();

            foreach ($branches as $b) {
                $bSales = (float) Sale::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('warehouse_id', $b->id)
                    ->whereBetween('createdAt', [$dateStart, $dateEnd])
                    ->whereNotIn('status', ['VOIDED', 'CANCELLED'])
                    ->sum('totalAmount');

                $bSaleIds = Sale::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('warehouse_id', $b->id)
                    ->whereBetween('createdAt', [$dateStart, $dateEnd])
                    ->pluck('id');

                $bCash = (float) Payment::withoutGlobalScopes()
                    ->whereIn('saleId', $bSaleIds)
                    ->where('method', 'CASH')
                    ->where('amount', '>', 0)
                    ->sum('amount');

                $branchesBreakdown[] = [
                    'name' => $b->name,
                    'sales' => $bSales,
                    'cash' => $bCash,
                ];
            }
        }

        // 9. Low stock items
        $lowStockItems = [];
        if ($setting->whatsapp_include_low_stock) {
            $threshold = (int) ($setting->lowStockThreshold ?: 5);

            if ($warehouseId !== null) {
                $lowLevels = StockLevel::withoutGlobalScopes()
                    ->where('warehouse_id', $warehouseId)
                    ->where('quantity', '<=', $threshold)
                    ->with('product')
                    ->take(10)
                    ->get();

                foreach ($lowLevels as $sl) {
                    if ($sl->product) {
                        $lowStockItems[] = [
                            'name' => $sl->product->name,
                            'qty' => $sl->quantity,
                            'unit' => $sl->product->unit ?? 'pcs',
                            'min' => $threshold,
                        ];
                    }
                }
            } else {
                $lowProducts = Product::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('stock', '<=', $threshold)
                    ->take(10)
                    ->get();

                foreach ($lowProducts as $p) {
                    $lowStockItems[] = [
                        'name' => $p->name,
                        'qty' => $p->stock,
                        'unit' => $p->unit ?? 'pcs',
                        'min' => $threshold,
                    ];
                }
            }
        }

        return [
            'currency' => $setting->currency ?: '₦',
            'business_name' => $setting->businessName ?: $businessName,
            'branch_name' => $whName,
            'date' => $targetDate->format('D, d M Y'),
            'total_invoices' => $totalInvoices,
            'gross_sales' => $grossSales,
            'cash_collected' => $cashSales,
            'pos_collected' => $posSales,
            'transfer_collected' => $transferSales,
            'debts_recovered' => $debtsRecovered,
            'new_debts' => $newDebtsIncurred,
            'returns_refunded' => $returnsRefunded,
            'expected_drawer_cash' => $expectedDrawerCash,
            'reversals_count' => $reversalsCount,
            'reversals_amount' => $reversalsAmount,
            'reversals_details' => $reversalsDetails,
            'branches_breakdown' => $branchesBreakdown,
            'low_stock_items' => $lowStockItems,
        ];
    }
}

