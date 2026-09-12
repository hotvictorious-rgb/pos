<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Transfer;
use App\Models\Customer;
use App\Models\StockAdjustment;
use App\Models\Activity;
use App\Models\User;
use App\Models\SalesReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Display the Central Reports & Business Intelligence Hub with Advanced Filters.
     */
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'day_book');
        $tabMap = [
            'overview' => 'repDayBook',
            'day_book' => 'repDayBook',
            'daybook' => 'repDayBook',
            'daily' => 'repDayBook',
            'daily_summary' => 'repDayBook',
            'repdaybook' => 'repDayBook',
            'sales' => 'repSales',
            'invoices' => 'repSales',
            'repsales' => 'repSales',
            'pending' => 'repPending',
            'pending_orders' => 'repPending',
            'unsupplied' => 'repPending',
            'pickups' => 'repPending',
            'reppending' => 'repPending',
            'stock' => 'repStock',
            'inventory' => 'repStock',
            'products' => 'repStock',
            'repstock' => 'repStock',
            'transfers' => 'repTransfers',
            'waybills' => 'repTransfers',
            'reptransfers' => 'repTransfers',
            'debts' => 'repDebts',
            'debtors' => 'repDebts',
            'repdebts' => 'repDebts',
            'damages' => 'repDamages',
            'stock_out' => 'repDamages',
            'adjustments' => 'repDamages',
            'deductions' => 'repDamages',
            'repdamages' => 'repDamages',
            'returns' => 'repReturns',
            'refunds' => 'repReturns',
            'repreturns' => 'repReturns',
            'ai' => 'repAi',
            'export' => 'repAi',
            'exports' => 'repAi',
            'ai_export' => 'repAi',
            'repai' => 'repAi',
        ];
        $currentTab = $tabMap[strtolower($activeTab)] ?? 'repDayBook';
        $authUser = Auth::user();

        if ($authUser && !$authUser->isExecutive() && empty($authUser->warehouse_id)) {
            abort(403, '🔒 Access Restricted: You are not assigned to any branch location. Please contact an administrator.');
        }

        $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
        $isBranchScoped = ($authUser && $authUser->isBranchScoped());
        $shopStaffIds = collect();

        if ($isBranchScoped) {
            $warehouses = Warehouse::where('id', $authUser->warehouse_id)->get();
            $staffList = User::where('warehouse_id', $authUser->warehouse_id)->get();
            $shopStaffIds = $staffList->pluck('id');
        } else {
            $warehouses = Warehouse::where('is_active', true)->get();
            $staffList = User::all();
        }

        $categories = Product::distinct()->pluck('category')->filter()->values();

        $datePreset = $request->get('date_preset', 'ALL');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $effectiveWh = $request->has('warehouse_id') ? $request->warehouse_id : session('active_warehouse_id');
        if ($effectiveWh === 'ALL' || $effectiveWh === '' || is_null($effectiveWh)) {
            $effectiveWh = null;
        }

        $filters = array_merge(['date_preset' => $datePreset], $request->all());
        if ($effectiveWh) {
            $filters['warehouse_id'] = $effectiveWh;
        } else {
            unset($filters['warehouse_id']);
        }

        // 1. Unified Authoritative Sales Query via AccountingReportService

        $salesQuery = $accountingService->buildSalesQuery($filters);
        $sales = (clone $salesQuery)->get();
        $saleIds = $sales->pluck('id');

        $salesBalances = $accountingService->calculateInvoiceBalancesForSales($sales);
        $returnsMap = \App\Models\SalesReturn::whereIn('saleId', $saleIds)
            ->groupBy('saleId')
            ->selectRaw('saleId, SUM(refundAmount) as total_refund')
            ->pluck('total_refund', 'saleId');

        foreach ($sales as $s) {
            $debt = $salesBalances[$s->id] ?? 0.0;
            $retCredit = (float) ($returnsMap[$s->id] ?? 0.0);
            $s->debt_balance = $debt;
            $s->event_paid_amount = max(0.0, round((float) $s->totalAmount - $retCredit - $debt, 2));
        }

        // 2. High-Level Aggregates (Event-Authoritative via AccountingReportService)
        $periodSummary = $accountingService->getPeriodSummary($filters);
        $dailyReport = $accountingService->getDailyComprehensiveReport($filters);
        $totalRevenue = (float) $sales->sum('totalAmount');
        $inflows = (float) \App\Models\Payment::whereIn('saleId', $saleIds)
            ->where('amount', '>', 0)
            ->where('method', '!=', 'REFUND_CASH')
            ->sum('amount');
        $cashRefunds = abs((float) \App\Models\Payment::whereIn('saleId', $saleIds)
            ->where('method', 'REFUND_CASH')
            ->sum('amount'));
        $totalCollected = max(0.0, round($inflows - $cashRefunds, 2));
        $returnCredits = (float) \App\Models\SalesReturn::whereIn('saleId', $saleIds)->sum('refundAmount');
        $netPayable = max(0.0, round($totalRevenue - $returnCredits, 2));
        $totalDebtCreated = max(0.0, round($netPayable - $totalCollected, 2));
        $totalInvoices = $sales->count();

        // 3. Debt Aging Analysis (Batch Calculated, Zero N+1 Queries)
        $scopedWh = $isBranchScoped ? (int) $authUser->warehouse_id : ($effectiveWh ? (int) $effectiveWh : null);
        if ($scopedWh) {
            $branchSales = Sale::where('warehouse_id', $scopedWh)
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->orderBy('createdAt', 'asc')
                ->get();

            $saleBalances = $accountingService->calculateInvoiceBalancesForSales($branchSales);

            $branchTotalDebt = 0.0;
            $customerBranchDebts = [];
            $oldestDebtDate = [];

            foreach ($branchSales as $bs) {
                $bal = $saleBalances[$bs->id] ?? 0.0;
                if ($bal > 0.01) {
                    $branchTotalDebt += $bal;
                    if (!empty($bs->customerId)) {
                        $cId = $bs->customerId;
                        $customerBranchDebts[$cId] = ($customerBranchDebts[$cId] ?? 0.0) + $bal;
                        if (!isset($oldestDebtDate[$cId])) {
                            $oldestDebtDate[$cId] = $bs->createdAt ?: $bs->created_at;
                        }
                    }
                }
            }

            $totalDebtOwedAllTime = round($branchTotalDebt, 2);

            $debtors = Customer::whereIn('id', array_keys($customerBranchDebts))
                ->get()
                ->map(function ($c) use ($customerBranchDebts, $oldestDebtDate) {
                    $debtDate = $oldestDebtDate[$c->id] ?? ($c->created_at ?: $c->updated_at);
                    $daysOld = $debtDate ? Carbon::parse($debtDate)->diffInDays(now()) : 0;
                    $c->aging_category = $daysOld > 30 ? 'CRITICAL (30+ Days)' : ($daysOld > 7 ? 'DUE (8-30 Days)' : 'CURRENT (0-7 Days)');
                    $bDebt = round($customerBranchDebts[$c->id] ?? 0.0, 2);
                    $c->branch_debt = $bDebt;
                    $c->total_debt = $bDebt; // Never expose tenant-wide total_debt to branch personnel or filtered branch views
                    return $c;
                })
                ->sortByDesc('branch_debt')
                ->values();
        } else {
            $totalDebtOwedAllTime = (float) Customer::sum('total_debt');

            $debtorCustomerIds = Customer::where('total_debt', '>', 0)->pluck('id');
            $oldestSales = Sale::whereIn('customerId', $debtorCustomerIds)
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->orderBy('createdAt', 'asc')
                ->get();
            $saleBalances = $accountingService->calculateInvoiceBalancesForSales($oldestSales);
            $oldestDebtDate = [];
            foreach ($oldestSales as $sale) {
                if (($saleBalances[$sale->id] ?? 0.0) > 0.01) {
                    if (!isset($oldestDebtDate[$sale->customerId])) {
                        $oldestDebtDate[$sale->customerId] = $sale->createdAt ?: $sale->created_at;
                    }
                }
            }

            $debtors = Customer::where('total_debt', '>', 0)
                ->orderBy('total_debt', 'desc')
                ->get()
                ->map(function ($c) use ($oldestDebtDate) {
                    $debtDate = $oldestDebtDate[$c->id] ?? ($c->created_at ?: $c->updated_at);
                    $daysOld = $debtDate ? Carbon::parse($debtDate)->diffInDays(now()) : 0;
                    $c->aging_category = $daysOld > 30 ? 'CRITICAL (30+ Days)' : ($daysOld > 7 ? 'DUE (8-30 Days)' : 'CURRENT (0-7 Days)');
                    $c->branch_debt = $c->total_debt;
                    return $c;
                });
        }

        // 4. Top Selling Products (by revenue) - Strictly scoped to filtered sales within tenant & branch, netting out returns
        $topProductsQuery = SaleItem::selectRaw('productId, productName, code, sum(quantity) as total_qty, sum(totalPrice) as total_revenue');
        if ($sales->isNotEmpty()) {
            $topProductsQuery->whereIn('saleId', $sales->pluck('id'));
        } else {
            $topProductsQuery->whereRaw('1 = 0');
        }
        $topProductsRaw = $topProductsQuery
            ->groupBy('productId', 'productName', 'code')
            ->orderBy('total_revenue', 'desc')
            ->get();

        $returnsByProduct = \App\Models\SalesReturn::whereIn('saleId', $saleIds)
            ->groupBy('productId')
            ->selectRaw('productId, SUM(quantity) as ret_qty, SUM(refundAmount) as ret_amount')
            ->get()
            ->keyBy('productId');

        $topProducts = $topProductsRaw->map(function ($item) use ($returnsByProduct) {
            $ret = $returnsByProduct->get($item->productId);
            $retQty = $ret ? (int) $ret->ret_qty : 0;
            $retAmount = $ret ? (float) $ret->ret_amount : 0.0;
            $item->total_qty = max(0, (int) $item->total_qty - $retQty);
            $item->total_revenue = max(0.0, round((float) $item->total_revenue - $retAmount, 2));
            return $item;
        })->sortByDesc('total_revenue')->take(5)->values();

        // 5. Top Staff by Sales Volume (Batch In-Memory, Zero N+1 Queries)
        $topStaff = $sales->groupBy('userName')->map(function ($group, $name) {
            return [
                'name' => $name ?: 'System',
                'count' => $group->count(),
                'total' => (float) $group->sum('totalAmount'),
                'collected' => max(0.0, round((float) $group->sum('event_paid_amount'), 2)),
            ];
        })->sortByDesc('total')->take(5);

        // 6. Physical Stock & Valuation Matrix
        $prodQuery = Product::where('archived', false);
        if ($request->filled('category')) {
            $prodQuery->where('category', $request->category);
        }

        $productsList = $prodQuery->get();
        $productIds = $productsList->pluck('id');

        $stockLevelsQuery = StockLevel::whereIn('product_id', $productIds);
        if ($isBranchScoped) {
            $stockLevelsQuery->where('warehouse_id', $authUser->warehouse_id);
        } elseif (!empty($effectiveWh)) {
            $stockLevelsQuery->where('warehouse_id', (int) $effectiveWh);
        }
        $stockLevelsGrouped = $stockLevelsQuery->get()->groupBy('product_id');

        $products = $productsList->map(function ($p) use ($stockLevelsGrouped) {
            $levels = $stockLevelsGrouped->get($p->id, collect());
            $p->branch_stocks = $levels->pluck('physical_stock', 'warehouse_id')->toArray();
            $p->total_physical_stock = array_sum($p->branch_stocks);
            $p->total_valuation = max(0, $p->total_physical_stock) * (float) $p->unitPrice;
            $threshold = (int) ($p->minStockLevel ?? 5);
            $p->stock_status = $p->total_physical_stock <= 0 ? 'OUT_OF_STOCK' : ($p->total_physical_stock <= $threshold ? 'LOW_STOCK' : 'IN_STOCK');
            return $p;
        });
        $totalStockValuation = $products->sum('total_valuation');
        $totalPhysicalUnits = $products->sum('total_physical_stock');

        // 7. Transfers & Logistics via AccountingReportService (Untruncated Total)
        $transfersQuery = $accountingService->buildTransfersQuery($filters);
        $transfers = $transfersQuery->take(50)->get();
        $totalDiscrepancyUnits = $accountingService->getTotalDiscrepancyUnits($filters);

        // 8. Stock Out & Deductions via AccountingReportService
        $adjustmentsQuery = $accountingService->buildAdjustmentsQuery($filters);
        $adjustments = $adjustmentsQuery->take(50)->get();
        $totalDamagedUnits = $accountingService->getTotalDamagedUnits($filters);

        // 9. Immutable Activity Logs
        $activitiesQuery = Activity::query();
        if ($isBranchScoped && $shopStaffIds->isNotEmpty()) {
            $activitiesQuery->whereIn('userId', $shopStaffIds);
        }
        $activities = $activitiesQuery->orderBy('timestamp', 'desc')->take(50)->get();

        // 10. Returns & Refunds Query via AccountingReportService
        $returnsQuery = $accountingService->buildReturnsQuery($filters);
        $returns = $returnsQuery->get();
        $totalRefunded = (float) $returns->sum('refundAmount');

        // 11. Pending Orders Backlog & Aging Analytics
        $pendingOrders = $accountingService->getPendingOrdersAnalytics($scopedWh, $filters);

        return view('reports.index', compact(
            'activeTab',
            'currentTab',
            'warehouses',
            'staffList',
            'categories',
            'sales',
            'totalRevenue',
            'totalCollected',
            'netPayable',
            'totalDebtCreated',
            'totalInvoices',
            'totalDebtOwedAllTime',
            'topProducts',
            'topStaff',
            'products',
            'totalStockValuation',
            'totalPhysicalUnits',
            'transfers',
            'totalDiscrepancyUnits',
            'debtors',
            'adjustments',
            'totalDamagedUnits',
            'activities',
            'returns',
            'totalRefunded',
            'periodSummary',
            'dailyReport',
            'pendingOrders',
            'datePreset',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * Export Filtered Report to CSV for Excel, Google Sheets, or AI prompt ingestion.
     */
    public function exportCsv(Request $request, $type)
    {
        $authUser = Auth::user();
        if ($authUser && !$authUser->isExecutive() && empty($authUser->warehouse_id)) {
            abort(403, '🔒 Access Restricted: You are not assigned to any branch location.');
        }

        $isBranchScoped = ($authUser && $authUser->isBranchScoped());
        $rawWh = $request->get('warehouse_id');
        $effectiveWh = ($rawWh === 'ALL' || $rawWh === '' || is_null($rawWh)) ? null : (int) $rawWh;
        $branchWarehouseId = $isBranchScoped ? (int) $authUser->warehouse_id : $effectiveWh;
        $fileName = "hysam_{$type}_report_" . date('Y_m_d_His') . ".csv";

        $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
        $filters = array_merge(['date_preset' => $request->get('date_preset', 'ALL')], $request->all());
        if ($branchWarehouseId) {
            $filters['warehouse_id'] = $branchWarehouseId;
        } else {
            unset($filters['warehouse_id']);
        }

        return new StreamedResponse(function () use ($type, $branchWarehouseId, $accountingService, $filters) {
            $handle = fopen('php://output', 'w');

            if (in_array($type, ['daily_summary', 'day_book', 'daily'])) {
                $daily = $accountingService->getDailyComprehensiveReport($filters);
                $dt = $daily['dateInfo'];

                fputcsv($handle, ['========================================================================================']);
                fputcsv($handle, ['VICTORIOUS MARKET - DAILY OPERATIONS & RECONCILIATION DAY-BOOK']);
                fputcsv($handle, ["Branch: " . ($branchWarehouseId ? ("Warehouse #" . $branchWarehouseId) : "All Branches (Consolidated)") . " | Filter: " . ($dt['label'] ?? 'Selected Period') . " | Timezone: Africa/Lagos (UTC+1)"]);
                fputcsv($handle, ["Generated At: " . now('Africa/Lagos')->format('Y-m-d H:i:s')]);
                fputcsv($handle, ['========================================================================================']);
                fputcsv($handle, []);

                // SECTION 1: EXECUTIVE KPI SUMMARY
                fputcsv($handle, ['--- 1. EXECUTIVE KPI SUMMARY ---']);
                fputcsv($handle, ['Metric', 'Amount / Value (NGN)', 'Quantity / Count', 'Operational Context']);
                fputcsv($handle, ['Total Amount Sold (Gross Invoiced)', $daily['total_amount_sold'], $daily['invoice_count'] . ' Invoices', 'Average Ticket: NGN ' . number_format($daily['average_invoice'], 2)]);
                fputcsv($handle, ['Total Net Realized Collections', $daily['total_net_collections'], '', 'Net Cash and POS Collections']);
                fputcsv($handle, ['Cash Collected (Net)', $daily['net_cash_inflow'], '', 'Cash Sales + Cash Debt Repaid - Cash Refunds']);
                fputcsv($handle, ['POS Collected', $daily['net_pos_inflow'], '', 'Card / Terminal Collections (Sales + Debt)']);
                fputcsv($handle, ['Expected Drawer Physical Cash', $daily['drawer_physical_cash'], '', 'Closing Physical Currency Reconciliation']);
                fputcsv($handle, ['New Credit Issued', $daily['new_credit_issued'], $daily['new_credit_sales_count'] . ' Invoices', 'Unpaid Balances Created on Period Sales']);
                fputcsv($handle, ['Debts Recovered', $daily['debt_recovered'], $daily['debt_recoveries_count'] . ' Payments', 'Cash: NGN ' . $daily['debt_recovered_cash'] . ' | POS: NGN ' . $daily['debt_recovered_pos']]);
                fputcsv($handle, ['Net Debt Portfolio Change', $daily['net_debt_change'], '', 'New Credit Issued - Debts Recovered']);
                fputcsv($handle, ['Total Debt Outstanding (All Time)', $daily['total_debt_outstanding'], '', 'Market Debt Liability']);
                fputcsv($handle, ['Total Stock Out Units', '', $daily['stock_out_total_units'] . ' Units', 'Dispatched (' . $daily['stock_out_sales_dispatch_units'] . ') + Transfers Out (' . $daily['stock_out_transfer_units'] . ') + Deductions/Adjustments (' . $daily['stock_out_damages_units'] . ')']);
                fputcsv($handle, ['Total Stock In Units', '', $daily['stock_in_total_units'] . ' Units', 'Supplier Restocks (' . $daily['stock_in_supplier_restock_units'] . ') + Transfers In (' . $daily['stock_in_transfer_units'] . ') + Returns (' . $daily['stock_in_returns_units'] . ')']);
                fputcsv($handle, ['Net Inventory Movement', '', $daily['net_inventory_movement_units'] . ' Units', 'Stock In Units - Stock Out Units']);
                fputcsv($handle, ['New Pending Orders (in Period)', $daily['pending_orders_new_value'], $daily['pending_orders_new_count'] . ' Orders (' . $daily['pending_orders_new_units'] . ' Units)', 'Awaiting Handover / Delivery']);
                fputcsv($handle, ['Carried Pending Backlog', $daily['pending_orders_carried_value'], $daily['pending_orders_carried_count'] . ' Orders (' . $daily['pending_orders_carried_units'] . ' Units)', '<24h: ' . $daily['carried_aging_under_24h'] . ' | 24-48h: ' . $daily['carried_aging_24h_to_48h'] . ' | 3-7d: ' . $daily['carried_aging_3d_to_7d'] . ' | >7d: ' . $daily['carried_aging_over_7d']]);
                fputcsv($handle, ['Customer Returns & Refunds', $daily['refunds_amount'], $daily['returns_count'] . ' Events (' . $daily['returned_units'] . ' Units)', 'Cash Refunded for Returns']);
                fputcsv($handle, ['Closing Physical Stock on Ground', $daily['physical_stock_remaining_value'], $daily['physical_stock_remaining_units'] . ' Units', 'Asset Valuation at Selling Price (Retail Price, Clamped >= 0)']);
                fputcsv($handle, []);

                // SECTION 2: TENDER & CASH FLOW BREAKDOWN
                fputcsv($handle, ['--- 2. TENDER & CASH FLOW BREAKDOWN ---']);
                fputcsv($handle, ['Inflow Category', 'Cash Amount (NGN)', 'POS Amount (NGN)', 'Total Amount (NGN)']);
                fputcsv($handle, ['Collections from Sales', $daily['summary']['cashFromSales'], $daily['summary']['posFromSales'], round($daily['summary']['cashFromSales'] + $daily['summary']['posFromSales'], 2)]);
                fputcsv($handle, ['Collections from Debt Recovery', $daily['debt_recovered_cash'], $daily['debt_recovered_pos'], $daily['debt_recovered']]);
                fputcsv($handle, ['Gross Realized Inflows', $daily['summary']['totalCashInflow'], $daily['summary']['totalPosInflow'], round($daily['summary']['totalCashInflow'] + $daily['summary']['totalPosInflow'], 2)]);
                fputcsv($handle, ['Less: Customer Refunds Disbursed', '-' . $daily['cash_refunded'], 0.00, '-' . $daily['cash_refunded']]);
                fputcsv($handle, ['Net Inflow Settlement', $daily['net_cash_inflow'], $daily['net_pos_inflow'], $daily['total_net_collections']]);
                fputcsv($handle, []);

                // SECTION 3: NEW CREDIT ISSUED IN PERIOD
                fputcsv($handle, ['--- 3. NEW CREDIT ISSUED IN PERIOD ---']);
                fputcsv($handle, ['Invoice ID', 'Date & Time', 'Customer Name', 'Customer Phone', 'Total Amount (NGN)', 'Deposit Paid (NGN)', 'Credit Balance (NGN)', 'Cashier']);
                foreach ($daily['new_credit_sales'] as $cs) {
                    fputcsv($handle, [$cs['id'], $cs['created_at'], $cs['customer_name'], $cs['customer_phone'], $cs['total_amount'], $cs['paid_amount'], $cs['credit_balance'], $cs['user_name']]);
                }
                if (empty($daily['new_credit_sales'])) {
                    fputcsv($handle, ['None', '—', 'No credit sales recorded in this period', '—', 0.00, 0.00, 0.00, '—']);
                }
                fputcsv($handle, []);

                // SECTION 4: DEBTS RECOVERED LEDGER
                fputcsv($handle, ['--- 4. DEBTS RECOVERED LEDGER ---']);
                fputcsv($handle, ['Receipt / Ref #', 'Date & Time', 'Customer Name', 'Phone', 'Payment Method', 'Amount Recovered (NGN)', 'Sale Ref', 'Notes']);
                foreach ($daily['debt_recoveries'] as $dr) {
                    fputcsv($handle, [
                        $dr->id,
                        $dr->created_at,
                        $dr->customer->name ?? 'Debtor Customer',
                        $dr->customer->phone ?? '—',
                        $dr->payment_method ?? 'CASH',
                        $dr->amount,
                        $dr->saleId ?? '—',
                        $dr->notes ?? 'Part-payment debt recovery'
                    ]);
                }
                if ($daily['debt_recoveries']->isEmpty()) {
                    fputcsv($handle, ['None', '—', 'No debt payments recovered in this period', '—', '—', 0.00, '—', '—']);
                }
                fputcsv($handle, []);

                // SECTION 5: PENDING ORDERS (NEW & CARRIED BACKLOG)
                fputcsv($handle, ['--- 5. PENDING ORDERS (NEW IN PERIOD & CARRIED BACKLOG) ---']);
                fputcsv($handle, ['Classification', 'Sale ID', 'Date Created', 'Customer Name', 'Phone', 'Total Units', 'Total Value (NGN)', 'Paid Amount (NGN)', 'Debt Balance (NGN)', 'Age (Days)', 'Aging Status']);
                foreach ($daily['pending_orders_new_list'] as $nord) {
                    $uCount = (int) $nord->items->sum('quantity');
                    fputcsv($handle, [
                        'NEW_IN_PERIOD',
                        $nord->id,
                        $nord->createdAt,
                        $nord->customerName ?: ($nord->customer->name ?? 'Walk-in'),
                        $nord->customerPhone ?: ($nord->customer->phone ?? '—'),
                        $uCount,
                        $nord->totalAmount,
                        $nord->paidAmount,
                        max(0, (float)$nord->totalAmount - (float)$nord->paidAmount),
                        0,
                        'NEW (< 24h)'
                    ]);
                }
                foreach ($daily['pending_orders_carried_list'] as $cord) {
                    fputcsv($handle, [
                        'CARRIED_BACKLOG',
                        $cord['sale_id'],
                        $cord['created_at'],
                        $cord['customer_name'],
                        $cord['customer_phone'],
                        $cord['total_units'],
                        $cord['total_amount'],
                        $cord['paid_amount'],
                        $cord['debt_balance'],
                        $cord['days_old'],
                        $cord['aging_badge']
                    ]);
                }
                fputcsv($handle, []);

                // SECTION 6: RETURNS & REFUNDS
                fputcsv($handle, ['--- 6. CUSTOMER RETURNS & REFUNDS ---']);
                fputcsv($handle, ['Return Date', 'Sale ID', 'Customer Name', 'SKU', 'Product Name', 'Returned Qty', 'Refund Amount (NGN)', 'Reason', 'Staff']);
                foreach ($daily['returns_list'] as $ret) {
                    fputcsv($handle, [
                        $ret->createdAt,
                        $ret->saleId,
                        $ret->customerName,
                        $ret->productCode,
                        $ret->productName,
                        $ret->quantity,
                        $ret->refundAmount,
                        $ret->reason ?? 'Customer Return',
                        $ret->userName
                    ]);
                }
                if ($daily['returns_list']->isEmpty()) {
                    fputcsv($handle, ['None', '—', 'No customer returns recorded in this period', '—', '—', 0, 0.00, '—', '—']);
                }
            } elseif ($type === 'sales') {
                fputcsv($handle, ['SALE ID', 'DATE', 'CUSTOMER', 'BRANCH', 'TOTAL AMOUNT', 'PAID AMOUNT', 'DEBT BALANCE', 'DELIVERY STATUS', 'CASHIER']);
                $salesQuery = $accountingService->buildSalesQuery($filters);
                $salesQuery->chunk(250, function ($salesChunk) use ($handle, $accountingService) {
                    $balances = $accountingService->calculateInvoiceBalancesForSales($salesChunk);
                    $chunkSaleIds = $salesChunk->pluck('id');
                    $returnsMap = \App\Models\SalesReturn::whereIn('saleId', $chunkSaleIds)
                        ->groupBy('saleId')
                        ->selectRaw('saleId, SUM(refundAmount) as total_refund')
                        ->pluck('total_refund', 'saleId');

                    foreach ($salesChunk as $s) {
                        $debt = $balances[$s->id] ?? 0.0;
                        $returnCredits = (float) ($returnsMap[$s->id] ?? 0.0);
                        $paid = max(0.0, round((float) $s->totalAmount - $returnCredits - $debt, 2));
                        fputcsv($handle, [
                            $s->id,
                            $s->createdAt,
                            $s->customerName,
                            $s->warehouse->name ?? 'Main Branch',
                            $s->totalAmount,
                            $paid,
                            $debt,
                            $s->deliveryStatus,
                            $s->userName
                        ]);
                    }
                });
            } elseif ($type === 'inventory') {
                fputcsv($handle, ['Product ID', 'SKU', 'Product Name', 'Category', 'Brand', 'Size', 'Selling Price (NGN)', 'Total Physical Shelf Units', 'Stock Status', 'Total Asset Valuation (NGN)']);
                $productsQuery = Product::with(['stockLevels' => function ($sq) use ($branchWarehouseId) {
                    if ($branchWarehouseId) {
                        $sq->where('warehouse_id', $branchWarehouseId);
                    }
                }])->where('archived', false);

                foreach ($productsQuery->lazy(100) as $p) {
                    $stock = (float) $p->stockLevels->sum('physical_stock');
                    $threshold = (int) ($p->minStockLevel ?? 5);
                    $status = $stock <= 0 ? 'OUT_OF_STOCK' : ($stock <= $threshold ? 'LOW_STOCK' : 'IN_STOCK');
                    fputcsv($handle, [$p->id, $p->code, $p->name, $p->category, $p->brand, $p->size, $p->unitPrice, $stock, $status, max(0, $stock) * (float)$p->unitPrice]);
                }
            } elseif ($type === 'transfers') {
                fputcsv($handle, ['Transfer No', 'Dispatched Date', 'Origin Branch', 'Destination Branch', 'Carrier Driver', 'Status', 'Dispatched By', 'Received By', 'Notes']);
                $transferQuery = $accountingService->buildTransfersQuery($filters);
                foreach ($transferQuery->cursor() as $t) {
                    fputcsv($handle, [
                        $t->transfer_no,
                        $t->created_at,
                        $t->source->name ?? 'Origin',
                        $t->destination->name ?? 'Destination',
                        $t->carrier_name,
                        $t->status,
                        $t->dispatched_by,
                        $t->received_by ?? 'Pending',
                        $t->notes ?? ''
                    ]);
                }
            } elseif ($type === 'debtors') {
                fputcsv($handle, ['Customer Name', 'Phone Number', 'Address / Market Location', 'Total Debt Owed (NGN)', 'Last Updated']);
                if ($branchWarehouseId) {
                    $branchSales = Sale::where('warehouse_id', $branchWarehouseId)
                        ->whereNotNull('customerId')
                        ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                        ->get();
                    $saleBalances = $accountingService->calculateInvoiceBalancesForSales($branchSales);
                    $customerBranchDebts = [];
                    foreach ($branchSales as $bs) {
                        $bal = $saleBalances[$bs->id] ?? 0.0;
                        if ($bal > 0.01) {
                            $customerBranchDebts[$bs->customerId] = ($customerBranchDebts[$bs->customerId] ?? 0.0) + $bal;
                        }
                    }
                    $debtors = Customer::whereIn('id', array_keys($customerBranchDebts))->get();
                    foreach ($debtors as $c) {
                        $bDebt = round($customerBranchDebts[$c->id] ?? 0.0, 2);
                        fputcsv($handle, [$c->name, $c->phone, $c->address, $bDebt, $c->updated_at]);
                    }
                } else {
                    $debtorsQuery = Customer::where('total_debt', '>', 0)->orderBy('total_debt', 'desc');
                    foreach ($debtorsQuery->cursor() as $c) {
                        fputcsv($handle, [$c->name, $c->phone, $c->address, $c->total_debt, $c->updated_at]);
                    }
                }
            } elseif (in_array($type, ['damages', 'stock', 'stock_out', 'adjustments'])) {
                fputcsv($handle, ['Date & Time', 'Shop Location', 'SKU', 'Product Name', 'Stock Out Category', 'Quantity Deducted', 'Reason / Notes', 'Staff Responsible']);
                $adjustmentsQuery = $accountingService->buildAdjustmentsQuery($filters);
                foreach ($adjustmentsQuery->cursor() as $a) {
                    fputcsv($handle, [
                        $a->created_at,
                        $a->warehouse->name ?? 'Shop',
                        $a->product_code,
                        $a->product_name,
                        $a->type,
                        $a->quantity,
                        $a->reason,
                        $a->recorded_by
                    ]);
                }
            } elseif ($type === 'activities') {
                fputcsv($handle, ['Date & Time', 'User ID', 'User Name', 'Activity Type', 'Description']);
                $activitiesQuery = Activity::orderBy('timestamp', 'desc');
                if ($branchWarehouseId) {
                    $branchUserIds = User::where('warehouse_id', $branchWarehouseId)->pluck('id');
                    $activitiesQuery->whereIn('userId', $branchUserIds);
                }
                foreach ($activitiesQuery->cursor() as $act) {
                    fputcsv($handle, [
                        $act->timestamp ?? $act->created_at,
                        $act->userId,
                        $act->userName ?? 'System',
                        $act->type ?? 'LOG',
                        $act->description,
                    ]);
                }
            } elseif ($type === 'returns') {
                fputcsv($handle, ['Date & Time', 'Original Invoice ID', 'Customer Name', 'SKU', 'Product Name', 'Returned Qty', 'Refunded Amount (NGN)', 'Reason', 'Handled By']);
                $returnsQuery = $accountingService->buildReturnsQuery($filters);
                foreach ($returnsQuery->cursor() as $r) {
                    fputcsv($handle, [
                        $r->createdAt,
                        $r->saleId,
                        $r->customerName,
                        $r->productCode,
                        $r->productName,
                        $r->quantity,
                        $r->refundAmount,
                        $r->reason ?? 'Customer Return',
                        $r->userName
                    ]);
                }
            } elseif ($type === 'pending_orders') {
                fputcsv($handle, ['SALE ID', 'DATE', 'CUSTOMER', 'PHONE', 'BRANCH', 'TOTAL UNITS', 'TOTAL VALUE', 'PAID AMOUNT', 'DEBT BALANCE', 'AGE (DAYS)', 'STATUS']);
                $pending = $accountingService->getPendingOrdersAnalytics($branchWarehouseId, $filters);
                foreach ($pending['backlog'] as $ord) {
                    fputcsv($handle, [
                        $ord['sale_id'],
                        $ord['created_at'],
                        $ord['customer_name'],
                        $ord['customer_phone'],
                        $ord['warehouse_name'],
                        $ord['total_units'],
                        $ord['total_value'],
                        $ord['paid_amount'],
                        $ord['debt_balance'],
                        $ord['age_days'],
                        $ord['delivery_status'],
                    ]);
                }
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Export Report to Structured JSON format for AI analysis.
     */
    public function exportJson(Request $request, $type)
    {
        $authUser = Auth::user();
        if ($authUser && !$authUser->isExecutive() && empty($authUser->warehouse_id)) {
            abort(403, '🔒 Access Restricted: You are not assigned to any branch location.');
        }

        $isBranchScoped = ($authUser && $authUser->isBranchScoped());
        $rawWh = $request->get('warehouse_id');
        $effectiveWh = ($rawWh === 'ALL' || $rawWh === '' || is_null($rawWh)) ? null : (int) $rawWh;
        $branchWarehouseId = $isBranchScoped ? (int) $authUser->warehouse_id : $effectiveWh;
        $fileName = "hysam_{$type}_business_data_" . date('Y_m_d_His') . ".json";

        $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
        $filters = array_merge(['date_preset' => $request->get('date_preset', 'ALL')], $request->all());
        if ($branchWarehouseId) {
            $filters['warehouse_id'] = $branchWarehouseId;
        } else {
            unset($filters['warehouse_id']);
        }

        $salesQuery = $accountingService->buildSalesQuery($filters);
        $transfersQuery = $accountingService->buildTransfersQuery($filters);
        $returnsQuery = $accountingService->buildReturnsQuery($filters);
        $damagesQuery = $accountingService->buildStockMovementsQuery($filters);

        if ($branchWarehouseId) {
            $branchSales = Sale::where('warehouse_id', $branchWarehouseId)
                ->whereNotNull('customerId')
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->get();
            $saleBalances = $accountingService->calculateInvoiceBalancesForSales($branchSales);
            $customerBranchDebts = [];
            foreach ($branchSales as $bs) {
                $bal = $saleBalances[$bs->id] ?? 0.0;
                if ($bal > 0.01) {
                    $customerBranchDebts[$bs->customerId] = ($customerBranchDebts[$bs->customerId] ?? 0.0) + $bal;
                }
            }
            $debtorsData = Customer::whereIn('id', array_keys($customerBranchDebts))
                ->get()
                ->map(function ($c) use ($customerBranchDebts) {
                    $arr = $c->toArray();
                    $bDebt = round($customerBranchDebts[$c->id] ?? 0.0, 2);
                    $arr['total_debt'] = $bDebt;
                    $arr['branch_debt'] = $bDebt;
                    return $arr;
                });
        } else {
            $debtorsData = Customer::where('total_debt', '>', 0)->orderBy('total_debt', 'desc')->get();
        }

        $activitiesQuery = Activity::orderBy('timestamp', 'desc');
        if ($branchWarehouseId) {
            $branchUserIds = User::where('warehouse_id', $branchWarehouseId)->pluck('id');
            $activitiesQuery->whereIn('userId', $branchUserIds);
        }

        $data = match($type) {
            'daily_summary', 'day_book', 'daily' => [
                'meta' => ['report' => 'Daily Operations & Reconciliation Day-Book', 'generated_at' => now('Africa/Lagos')->toIso8601String(), 'currency' => 'NGN', 'timezone' => 'Africa/Lagos'],
                'metadata' => ['report' => 'Daily Operations & Reconciliation Day-Book', 'generated_at' => now('Africa/Lagos')->toIso8601String(), 'currency' => 'NGN', 'timezone' => 'Africa/Lagos'],
                'data' => $accountingService->getDailyComprehensiveReport($filters),
            ],
            'sales' => [
                'meta' => ['report' => 'Sales & Revenue Analysis', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'metadata' => ['report' => 'Sales & Revenue Analysis', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'data' => (function () use ($salesQuery, $accountingService) {
                    $salesList = $salesQuery->get();
                    $balances = $accountingService->calculateInvoiceBalancesForSales($salesList);
                    $saleIds = $salesList->pluck('id');
                    $returnsMap = \App\Models\SalesReturn::whereIn('saleId', $saleIds)
                        ->groupBy('saleId')
                        ->selectRaw('saleId, SUM(refundAmount) as total_refund')
                        ->pluck('total_refund', 'saleId');

                    return $salesList->map(function ($s) use ($balances, $returnsMap) {
                        $arr = $s->toArray();
                        $debt = $balances[$s->id] ?? 0.0;
                        $returnCredits = (float) ($returnsMap[$s->id] ?? 0.0);
                        $paid = max(0.0, round((float) $s->totalAmount - $returnCredits - $debt, 2));
                        $arr['paidAmount'] = $paid;
                        $arr['event_paid_amount'] = $paid;
                        $arr['invoice_balance'] = $debt;
                        return $arr;
                    });
                })()
            ],
            'inventory' => [
                'meta' => ['report' => 'Multi-Branch Inventory Valuation', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'metadata' => ['report' => 'Multi-Branch Inventory Valuation', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'data' => $branchWarehouseId
                    ? Product::with(['stockLevels' => fn($q) => $q->where('warehouse_id', $branchWarehouseId)])
                        ->where('archived', false)
                        ->whereHas('stockLevels', fn($q) => $q->where('warehouse_id', $branchWarehouseId))
                        ->get()
                    : Product::with('stockLevels')->where('archived', false)->get()
            ],
            'transfers' => [
                'meta' => ['report' => 'Inter-Branch Transfer Movements & Discrepancies', 'generated_at' => now()->toIso8601String()],
                'metadata' => ['report' => 'Inter-Branch Transfer Movements & Discrepancies', 'generated_at' => now()->toIso8601String()],
                'data' => $transfersQuery->get()
            ],
            'debtors' => [
                'meta' => ['report' => 'Debtors Ledger & Credit Exposure', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'metadata' => ['report' => 'Debtors Ledger & Credit Exposure', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'data' => $debtorsData
            ],
            'damages', 'stock', 'stock_out', 'adjustments' => [
                'meta' => ['report' => 'Stock Out & Deductions Audit Trail', 'generated_at' => now()->toIso8601String()],
                'metadata' => ['report' => 'Stock Out & Deductions Audit Trail', 'generated_at' => now()->toIso8601String()],
                'data' => $accountingService->buildAdjustmentsQuery($filters)->get()
            ],
            'activities' => [
                'meta' => ['report' => 'Immutable System Audit Activity Log', 'generated_at' => now()->toIso8601String()],
                'metadata' => ['report' => 'Immutable System Audit Activity Log', 'generated_at' => now()->toIso8601String()],
                'data' => $activitiesQuery->get()
            ],
            'returns' => [
                'meta' => ['report' => 'Customer Returns & Refunds Ledger', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'metadata' => ['report' => 'Customer Returns & Refunds Ledger', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'data' => $returnsQuery->get()
            ],
            'pending_orders' => [
                'meta' => ['report' => 'Pending Orders Carryover & Aging Backlog', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'metadata' => ['report' => 'Pending Orders Carryover & Aging Backlog', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'data' => $accountingService->getPendingOrdersAnalytics($branchWarehouseId, $filters),
            ],
            default => ['error' => 'Invalid report type'],
        };

        return response()->json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ], JSON_PRETTY_PRINT);
    }
}
