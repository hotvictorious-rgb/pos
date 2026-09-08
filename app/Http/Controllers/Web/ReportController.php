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
        $activeTab = $request->get('tab', 'overview');
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
        $filters = array_merge(['date_preset' => $datePreset], $request->all());

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

        // 2. High-Level Aggregates (Event-Authoritative)
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
        if ($isBranchScoped) {
            $branchSales = Sale::where('warehouse_id', $authUser->warehouse_id)
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
                    $c->total_debt = $bDebt; // Never expose tenant-wide total_debt to branch personnel
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

        // 4. Top Selling Products (by revenue) - Strictly scoped to filtered sales within tenant & branch
        $topProductsQuery = SaleItem::selectRaw('productName, code, sum(quantity) as total_qty, sum(totalPrice) as total_revenue');
        if ($sales->isNotEmpty()) {
            $topProductsQuery->whereIn('saleId', $sales->pluck('id'));
        } else {
            $topProductsQuery->whereRaw('1 = 0');
        }
        $topProducts = $topProductsQuery
            ->groupBy('productName', 'code')
            ->orderBy('total_revenue', 'desc')
            ->take(5)
            ->get();

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
        }
        $stockLevelsGrouped = $stockLevelsQuery->get()->groupBy('product_id');

        $products = $productsList->map(function ($p) use ($stockLevelsGrouped) {
            $levels = $stockLevelsGrouped->get($p->id, collect());
            $p->branch_stocks = $levels->pluck('physical_stock', 'warehouse_id')->toArray();
            $p->total_physical_stock = array_sum($p->branch_stocks);
            $p->total_valuation = $p->total_physical_stock * (float) $p->unitPrice;
            $threshold = (int) ($p->minStockLevel ?? 5);
            $p->stock_status = $p->total_physical_stock <= 0 ? 'OUT_OF_STOCK' : ($p->total_physical_stock <= $threshold ? 'LOW_STOCK' : 'IN_STOCK');
            return $p;
        });
        $totalStockValuation = $products->sum('total_valuation');
        $totalPhysicalUnits = $products->sum('total_physical_stock');

        // 7. Transfers & Logistics via AccountingReportService
        $transfersQuery = $accountingService->buildTransfersQuery($filters);
        $transfers = $transfersQuery->take(50)->get();
        $totalDiscrepancyUnits = 0;
        foreach ($transfers as $trf) {
            if ($trf->status === 'DISCREPANCY') {
                foreach ($trf->items as $item) {
                    $totalDiscrepancyUnits += max(0, $item->discrepancy_qty);
                }
            }
        }

        // 8. Damaged Goods Write-offs
        $adjustmentsQuery = StockAdjustment::with('warehouse');
        if ($isBranchScoped) {
            $adjustmentsQuery->where('warehouse_id', $authUser->warehouse_id);
        }
        $adjustments = $adjustmentsQuery->orderBy('created_at', 'desc')->take(50)->get();
        $totalDamagedUnits = $adjustments->sum('quantity');

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

        return view('reports.index', compact(
            'activeTab',
            'warehouses',
            'staffList',
            'categories',
            'sales',
            'totalRevenue',
            'totalCollected',
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
        $branchWarehouseId = $isBranchScoped ? (int) $authUser->warehouse_id : null;
        $fileName = "hysam_{$type}_report_" . date('Y_m_d_His') . ".csv";

        $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
        $filters = array_merge(['date_preset' => $request->get('date_preset', 'ALL')], $request->all());

        return new StreamedResponse(function () use ($type, $isBranchScoped, $branchWarehouseId, $accountingService, $filters) {
            $handle = fopen('php://output', 'w');

            if ($type === 'sales') {
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
                $productsQuery = Product::with(['stockLevels' => function ($sq) use ($isBranchScoped, $branchWarehouseId) {
                    if ($isBranchScoped) {
                        $sq->where('warehouse_id', $branchWarehouseId);
                    }
                }])->where('archived', false);

                foreach ($productsQuery->lazy(100) as $p) {
                    $stock = (float) $p->stockLevels->sum('physical_stock');
                    $threshold = (int) ($p->minStockLevel ?? 5);
                    $status = $stock <= 0 ? 'OUT_OF_STOCK' : ($stock <= $threshold ? 'LOW_STOCK' : 'IN_STOCK');
                    fputcsv($handle, [$p->id, $p->code, $p->name, $p->category, $p->brand, $p->size, $p->unitPrice, $stock, $status, $stock * (float)$p->unitPrice]);
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
                if ($isBranchScoped) {
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
            } elseif (in_array($type, ['damages', 'stock'])) {
                fputcsv($handle, ['Date & Time', 'Shop Location', 'SKU', 'Product Name', 'Incident Category', 'Quantity Deducted', 'Reason / Notes', 'Staff Responsible']);
                $damagesQuery = StockAdjustment::with('warehouse')->orderBy('created_at', 'desc');
                if ($isBranchScoped) {
                    $damagesQuery->where('warehouse_id', $branchWarehouseId);
                }
                foreach ($damagesQuery->cursor() as $a) {
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
                        $r->reason,
                        $r->userName
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
        $branchWarehouseId = $isBranchScoped ? (int) $authUser->warehouse_id : null;
        $fileName = "hysam_{$type}_business_data_" . date('Y_m_d_His') . ".json";

        $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
        $filters = array_merge(['date_preset' => $request->get('date_preset', 'ALL')], $request->all());

        $salesQuery = $accountingService->buildSalesQuery($filters);
        $transfersQuery = $accountingService->buildTransfersQuery($filters);
        $returnsQuery = $accountingService->buildReturnsQuery($filters);
        $damagesQuery = $accountingService->buildStockMovementsQuery($filters);

        if ($isBranchScoped) {
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
        if ($isBranchScoped) {
            $branchUserIds = User::where('warehouse_id', $branchWarehouseId)->pluck('id');
            $activitiesQuery->whereIn('userId', $branchUserIds);
        }

        $data = match($type) {
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
                'data' => $isBranchScoped
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
            'damages', 'stock' => [
                'meta' => ['report' => 'Damaged Goods & Loss Audit Trail', 'generated_at' => now()->toIso8601String()],
                'metadata' => ['report' => 'Damaged Goods & Loss Audit Trail', 'generated_at' => now()->toIso8601String()],
                'data' => $damagesQuery->get()
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
            default => ['error' => 'Invalid report type'],
        };

        return response()->json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ], JSON_PRETTY_PRINT);
    }
}
