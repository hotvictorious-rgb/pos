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
        $dateStartIso = $dateStart->toIso8601String();
        $dateEndIso = $dateEnd->toIso8601String();

        $salesQuery = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('createdAt', [$dateStartIso, $dateEndIso])
            ->whereNotIn('status', ['VOIDED', 'CANCELLED']);

        if ($warehouseId !== null) {
            $salesQuery->where('warehouse_id', $warehouseId);
        }

        $sales = $salesQuery->with('payments')->get();
        $totalInvoices = $sales->count();
        $grossSales = (float) $sales->sum('totalAmount');
        $saleIds = $sales->pluck('id')->toArray();

        // Retail vs Wholesale breakdown
        $wholesaleSalesList = $sales->filter(fn($s) => strtolower($s->sale_type ?? '') === 'wholesale');
        $wholesaleCount = $wholesaleSalesList->count();
        $wholesaleAmount = (float) $wholesaleSalesList->sum('totalAmount');

        $retailSalesList = $sales->filter(fn($s) => strtolower($s->sale_type ?? '') !== 'wholesale');
        $retailCount = $retailSalesList->count();
        $retailAmount = (float) $retailSalesList->sum('totalAmount');
        $avgOrderValue = $totalInvoices > 0 ? round($grossSales / $totalInvoices, 2) : 0.0;

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

        $exchangeCreditSales = (float) Payment::withoutGlobalScopes()
            ->whereIn('saleId', $saleIds)
            ->where('method', 'EXCHANGE_CREDIT')
            ->where('amount', '>', 0)
            ->sum('amount');

        $totalTenderCollected = $cashSales + $posSales + $transferSales + $exchangeCreditSales;

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
        $debtsRecoveredCount = $debtRecoveryQuery->distinct('customer_id')->count('customer_id');

        // 4. Returns & Refunds Outflows
        $returnsQuery = SalesReturn::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd]);

        if ($warehouseId !== null) {
            $returnsQuery->where('warehouse_id', $warehouseId);
        }

        $returnsRefunded = (float) $returnsQuery->sum('refundAmount');
        $returnsCount = $returnsQuery->count();
        $returnsUnits = (float) $returnsQuery->sum('quantity');

        // Customer exchanges
        $exchangesCount = $sales->filter(fn($s) => $s->payments && $s->payments->where('method', 'EXCHANGE_CREDIT')->isNotEmpty())->count();

        // 5. Net Cash in Drawer for Deposit:
        // (Cash from sales) + (Debt recovery cash) - (Cash refunds)
        $expectedDrawerCash = max(0.0, round($cashSales + $debtsRecovered - $returnsRefunded, 2));

        // 6. New Debt Incurred today
        $totalPaidOnTodaySales = (float) Payment::withoutGlobalScopes()
            ->whereIn('saleId', $saleIds)
            ->where('amount', '>', 0)
            ->whereNotIn('method', ['REFUND', 'REFUND_CASH'])
            ->sum('amount');
        $newDebtsIncurred = max(0.0, round($grossSales - $totalPaidOnTodaySales, 2));
        $newDebtsCount = $sales->filter(fn($s) => ($s->totalAmount - $s->paidAmount) > 0.01)->count();

        // Cumulative open customer debt across the shop
        $totalOpenDebtQuery = \App\Models\Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId);
        $totalCumulativeDebt = (float) $totalOpenDebtQuery->sum('total_debt');

        // 7. Physical Inventory Movement (Stock In, Stock Out, Damages)
        $stockInQuery = \App\Models\InventoryLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('timestamp', [$dateStartIso, $dateEndIso])
            ->whereIn('type', ['STOCK_IN', 'PURCHASE']);
        if ($warehouseId !== null) {
            $stockInQuery->where('warehouse_id', $warehouseId);
        }
        $stockInBatches = $stockInQuery->count();
        $stockInUnits = abs((float) $stockInQuery->sum('quantity'));
        $topStockInProducts = (clone $stockInQuery)
            ->selectRaw('productName, SUM(ABS(quantity)) as total_qty')
            ->groupBy('productName')
            ->orderByDesc('total_qty')
            ->take(3)
            ->get()
            ->map(fn($row) => ['name' => $row->productName, 'qty' => (float) $row->total_qty])
            ->toArray();

        $stockOutQuery = \App\Models\InventoryLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('timestamp', [$dateStartIso, $dateEndIso])
            ->whereIn('type', ['SALE_DISPATCH', 'STOCK_OUT']);
        if ($warehouseId !== null) {
            $stockOutQuery->where('warehouse_id', $warehouseId);
        }
        $stockOutUnits = abs((float) $stockOutQuery->sum('quantity'));

        $damageQuery = \App\Models\InventoryLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('timestamp', [$dateStartIso, $dateEndIso])
            ->whereIn('type', [
                'STOCK_ADJUSTMENT',
                'STOCK_ADJUSTMENT_DAMAGE',
                'STOCK_ADJUSTMENT_EXPIRED',
                'STOCK_ADJUSTMENT_LOST',
            ]);
        if ($warehouseId !== null) {
            $damageQuery->where('warehouse_id', $warehouseId);
        }
        $damageUnits = abs((float) $damageQuery->sum('quantity'));
        $damageBatches = $damageQuery->count();
        $netStockDelta = round($stockInUnits - $stockOutUnits - $damageUnits, 2);

        // 8. Inter-Branch Logistics & Transfers
        $outboundTransferQuery = \App\Models\Transfer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('dispatched_at', [$dateStart, $dateEnd]);
        if ($warehouseId !== null) {
            $outboundTransferQuery->where('source_warehouse_id', $warehouseId);
        }
        $outboundTransfersCount = $outboundTransferQuery->count();
        $outboundTransferIds = $outboundTransferQuery->pluck('id')->toArray();
        $outboundUnits = !empty($outboundTransferIds) 
            ? (float) \App\Models\TransferItem::withoutGlobalScopes()->whereIn('transfer_id', $outboundTransferIds)->sum('dispatched_qty') 
            : 0.0;

        $inboundTransferQuery = \App\Models\Transfer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('received_at', [$dateStart, $dateEnd])
            ->where('status', 'RECEIVED');
        if ($warehouseId !== null) {
            $inboundTransferQuery->where('destination_warehouse_id', $warehouseId);
        }
        $inboundTransfersCount = $inboundTransferQuery->count();
        $inboundTransferIds = $inboundTransferQuery->pluck('id')->toArray();
        $inboundUnits = !empty($inboundTransferIds) 
            ? (float) \App\Models\TransferItem::withoutGlobalScopes()->whereIn('transfer_id', $inboundTransferIds)->sum('received_qty') 
            : 0.0;

        $pendingIncomingQuery = \App\Models\Transfer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['DISPATCHED', 'IN_TRANSIT']);
        if ($warehouseId !== null) {
            $pendingIncomingQuery->where('destination_warehouse_id', $warehouseId);
        }
        $pendingIncomingCount = $pendingIncomingQuery->count();

        // 9. Pending Deliveries & Pickup Backlog (Unsupplied Orders)
        $pendingDeliverySales = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('deliveryStatus', ['PENDING', 'PARTIALLY_DELIVERED'])
            ->whereNotIn('status', ['VOIDED', 'CANCELLED']);
        if ($warehouseId !== null) {
            $pendingDeliverySales->where('warehouse_id', $warehouseId);
        }
        $pendingDeliveriesCount = $pendingDeliverySales->count();
        $pendingDeliveriesValue = (float) $pendingDeliverySales->sum('totalAmount');

        $unsuppliedBacklogUnitsQuery = \App\Models\StockReservation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['ACTIVE', 'PARTIALLY_FULFILLED']);
        if ($warehouseId !== null) {
            $unsuppliedBacklogUnitsQuery->where('warehouse_id', $warehouseId);
        }
        $unsuppliedBacklogUnits = (float) $unsuppliedBacklogUnitsQuery->get()
            ->sum(fn($r) => $r->outstanding_qty);

        $fulfilledTodayQuery = Sale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('deliveredAt', [$dateStartIso, $dateEndIso])
            ->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
        if ($warehouseId !== null) {
            $fulfilledTodayQuery->where('warehouse_id', $warehouseId);
        }
        $fulfilledTodayCount = $fulfilledTodayQuery->count();

        // 10. Reversals & Voids audit logs
        $reversalsQuery = Activity::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$dateStart, $dateEnd])
            ->whereIn('type', [
                'TRANSACTION_VOIDED',
                'DEBT_PAYMENT_REVERSED',
                'STOCK_IN_VOIDED',
                'STOCK_OUT_VOIDED',
                'STOCK_ADJUSTMENT_VOIDED',
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

            // 1. Sales & Revenue
            'total_invoices' => $totalInvoices,
            'gross_sales' => $grossSales,
            'retail_sales_count' => $retailCount,
            'retail_sales_amount' => $retailAmount,
            'wholesale_sales_count' => $wholesaleCount,
            'wholesale_sales_amount' => $wholesaleAmount,
            'avg_order_value' => $avgOrderValue,

            // 2. Payments & Cash In Till
            'cash_collected' => $cashSales,
            'pos_collected' => $posSales,
            'transfer_collected' => $transferSales,
            'exchange_credit_collected' => $exchangeCreditSales,
            'total_tender_collected' => $totalTenderCollected,
            'expected_drawer_cash' => $expectedDrawerCash,

            // 3. Customer Debts & Receivables
            'debts_recovered' => $debtsRecovered,
            'debts_recovered_count' => $debtsRecoveredCount,
            'new_debts' => $newDebtsIncurred,
            'new_debts_count' => $newDebtsCount,
            'total_cumulative_debt' => $totalCumulativeDebt,

            // 4. Physical Stock Movement
            'include_inventory' => (bool) ($setting->whatsapp_include_inventory ?? true),
            'stock_in_batches' => $stockInBatches,
            'stock_in_units' => $stockInUnits,
            'top_stock_in_products' => $topStockInProducts,
            'stock_out_units' => $stockOutUnits,
            'damage_units' => $damageUnits,
            'damage_batches' => $damageBatches,
            'net_stock_delta' => $netStockDelta,

            // 5. Inter-Branch Transfers
            'include_transfers' => (bool) ($setting->whatsapp_include_transfers ?? true),
            'outbound_transfers_count' => $outboundTransfersCount,
            'outbound_units' => $outboundUnits,
            'inbound_transfers_count' => $inboundTransfersCount,
            'inbound_units' => $inboundUnits,
            'pending_incoming_transfers' => $pendingIncomingCount,

            // 6. Pending Deliveries & Unsupplied Orders
            'include_deliveries' => (bool) ($setting->whatsapp_include_deliveries ?? true),
            'pending_deliveries_count' => $pendingDeliveriesCount,
            'pending_deliveries_value' => $pendingDeliveriesValue,
            'unsupplied_backlog_units' => $unsuppliedBacklogUnits,
            'fulfilled_today_count' => $fulfilledTodayCount,

            // 7. Returns, Refunds & Exchanges
            'returns_refunded' => $returnsRefunded,
            'returns_count' => $returnsCount,
            'returns_units' => $returnsUnits,
            'exchanges_count' => $exchangesCount,

            // 8. Reversals & Voids
            'reversals_count' => $reversalsCount,
            'reversals_amount' => $reversalsAmount,
            'reversals_details' => $reversalsDetails,

            // 9. Aggregates & Alerts
            'branches_breakdown' => $branchesBreakdown,
            'low_stock_items' => $lowStockItems,
        ];
    }
}

