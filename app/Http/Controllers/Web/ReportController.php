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

        // 1. Build Filter Query for Sales
        $salesQuery = Sale::with('items');
        $isBranchScoped = ($authUser && $authUser->isBranchScoped());
        $shopStaffIds = collect();

        if ($isBranchScoped) {
            $warehouses = Warehouse::where('id', $authUser->warehouse_id)->get();
            $staffList = User::where('warehouse_id', $authUser->warehouse_id)->get();
            $salesQuery->where('warehouse_id', $authUser->warehouse_id);
        } else {
            $warehouses = Warehouse::where('is_active', true)->get();
            $staffList = User::all();
        }

        $categories = Product::distinct()->pluck('category')->filter()->values();

        $datePreset = $request->get('date_preset', 'ALL');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        if ($fromDate && $toDate) {
            $salesQuery->whereBetween('createdAt', [
                Carbon::parse($fromDate)->startOfDay()->toIso8601String(),
                Carbon::parse($toDate)->endOfDay()->toIso8601String()
            ]);
        } elseif ($datePreset === 'TODAY') {
            $salesQuery->whereDate('createdAt', Carbon::today());
        } elseif ($datePreset === 'YESTERDAY') {
            $salesQuery->whereDate('createdAt', Carbon::yesterday());
        } elseif ($datePreset === 'THIS_WEEK') {
            $salesQuery->whereBetween('createdAt', [
                Carbon::now()->startOfWeek()->toIso8601String(),
                Carbon::now()->endOfWeek()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_MONTH') {
            $salesQuery->whereBetween('createdAt', [
                Carbon::now()->startOfMonth()->toIso8601String(),
                Carbon::now()->endOfMonth()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_YEAR') {
            $salesQuery->whereBetween('createdAt', [
                Carbon::now()->startOfYear()->toIso8601String(),
                Carbon::now()->endOfYear()->toIso8601String()
            ]);
        }

        if ($request->filled('user_name')) {
            $salesQuery->where('userName', 'like', "%{$request->user_name}%");
        }

        // Authoritative event-based financial calculation subqueries:
        $hasPaymentsSql = "(SELECT COUNT(*) FROM payments WHERE payments.saleId = sales.id)";
        $eventNetPaidSql = "COALESCE((SELECT SUM(amount) FROM payments WHERE payments.saleId = sales.id AND payments.amount > 0 AND payments.method != 'REFUND_CASH'), 0) - COALESCE((SELECT ABS(SUM(amount)) FROM payments WHERE payments.saleId = sales.id AND payments.method = 'REFUND_CASH'), 0)";
        $netPaidSql = "CASE WHEN {$hasPaymentsSql} > 0 THEN ({$eventNetPaidSql}) ELSE sales.paidAmount END";
        $returnCreditsSql = "COALESCE((SELECT SUM(refundAmount) FROM sales_returns WHERE sales_returns.saleId = sales.id), 0)";
        $netBalanceSql = "(sales.totalAmount - ({$returnCreditsSql}) - ({$netPaidSql}))";

        if ($request->filled('payment_status')) {
            $pStatus = strtoupper($request->payment_status);
            if ($pStatus === 'PAID') {
                $salesQuery->whereRaw("{$netBalanceSql} <= 0.01");
            } elseif (in_array($pStatus, ['PART_PAID', 'PARTIAL'])) {
                $salesQuery->whereRaw("{$netBalanceSql} > 0.01 AND ({$netPaidSql}) > 0.01");
            } elseif (in_array($pStatus, ['NOT_PAID', 'UNPAID'])) {
                $salesQuery->whereRaw("{$netBalanceSql} > 0.01 AND ({$netPaidSql}) <= 0.01");
            } elseif ($pStatus === 'DEBT') {
                $salesQuery->whereRaw("{$netBalanceSql} > 0.01");
            }
        }

        if ($request->filled('delivery_status')) {
            $dStatus = strtoupper($request->delivery_status);
            if (in_array($dStatus, ['DELIVERED', 'SUPPLIED'])) {
                $salesQuery->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
            } elseif (in_array($dStatus, ['UNSUPPLIED', 'NOT_SUPPLIED', 'PENDING'])) {
                $salesQuery->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
            } elseif ($dStatus === 'PAID_SUPPLIED') {
                $salesQuery->whereRaw("{$netBalanceSql} <= 0.01")->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
            } elseif ($dStatus === 'PAID_NOT_SUPPLIED') {
                $salesQuery->whereRaw("{$netBalanceSql} <= 0.01")->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
            } elseif ($dStatus === 'PART_PAID_SUPPLIED') {
                $salesQuery->whereRaw("{$netBalanceSql} > 0.01 AND ({$netPaidSql}) > 0.01")->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
            } elseif ($dStatus === 'PART_PAID_NOT_SUPPLIED') {
                $salesQuery->whereRaw("{$netBalanceSql} > 0.01 AND ({$netPaidSql}) > 0.01")->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
            } else {
                $salesQuery->where('deliveryStatus', $dStatus);
            }
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $salesQuery->where(function ($q) use ($s) {
                $q->where('id', 'like', "%{$s}%")
                  ->orWhere('customerName', 'like', "%{$s}%")
                  ->orWhere('customerPhone', 'like', "%{$s}%");
            });
        }

        // Executed Sales Collection
        $sales = (clone $salesQuery)->orderBy('createdAt', 'desc')->get();

        // 2. High-Level Aggregates (Event-Authoritative)
        $totalRevenue = (float) $sales->sum('totalAmount');
        $saleIds = $sales->pluck('id');
        $hasPayments = \App\Models\Payment::whereIn('saleId', $saleIds)->exists();
        if ($hasPayments) {
            $inflows = (float) \App\Models\Payment::whereIn('saleId', $saleIds)
                ->where('amount', '>', 0)
                ->where('method', '!=', 'REFUND_CASH')
                ->sum('amount');
            $cashRefunds = abs((float) \App\Models\Payment::whereIn('saleId', $saleIds)
                ->where('method', 'REFUND_CASH')
                ->sum('amount'));
            $totalCollected = max(0.0, round($inflows - $cashRefunds, 2));
        } else {
            $totalCollected = (float) $sales->sum('paidAmount');
        }
        $returnCredits = (float) \App\Models\SalesReturn::whereIn('saleId', $saleIds)->sum('refundAmount');
        $netPayable = max(0.0, round($totalRevenue - $returnCredits, 2));
        $totalDebtCreated = max(0.0, round($netPayable - $totalCollected, 2));
        $totalInvoices = $sales->count();

        if ($isBranchScoped) {
            $branchSales = Sale::where('warehouse_id', $authUser->warehouse_id)
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->get();
            $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
            $branchTotalDebt = 0.0;
            foreach ($branchSales as $bs) {
                $branchTotalDebt += $accountingService->calculateInvoiceBalance($bs);
            }
            $totalDebtOwedAllTime = round($branchTotalDebt, 2);
        } else {
            $totalDebtOwedAllTime = (float) Customer::sum('total_debt');
        }

        // Top Selling Products (by revenue) - Strictly scoped to filtered sales within tenant & branch
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

        // Top Staff by Sales Volume
        $topStaff = $sales->groupBy('userName')->map(function ($group, $name) {
            return [
                'name' => $name,
                'count' => $group->count(),
                'total' => $group->sum('totalAmount'),
                'collected' => $group->sum('paidAmount'),
            ];
        })->sortByDesc('total')->take(5);

        // 3. Physical Stock & Valuation Matrix
        $prodQuery = Product::where('archived', false);
        if ($request->filled('category')) {
            $prodQuery->where('category', $request->category);
        }

        $products = $prodQuery->get()->map(function ($p) use ($isBranchScoped, $authUser) {
            $stockLevelsQuery = StockLevel::where('product_id', $p->id);
            if ($isBranchScoped) {
                $stockLevelsQuery->where('warehouse_id', $authUser->warehouse_id);
            }
            $p->branch_stocks = $stockLevelsQuery->pluck('physical_stock', 'warehouse_id')->toArray();
            $p->total_physical_stock = array_sum($p->branch_stocks);
            $p->total_valuation = $p->total_physical_stock * (float) $p->unitPrice;
            $p->stock_status = $p->total_physical_stock <= 0 ? 'OUT_OF_STOCK' : ($p->total_physical_stock <= 5 ? 'LOW_STOCK' : 'IN_STOCK');
            return $p;
        });
        $totalStockValuation = $products->sum('total_valuation');
        $totalPhysicalUnits = $products->sum('total_physical_stock');

        // 4. Transfers & Logistics
        $transfersQuery = Transfer::with(['source', 'destination', 'items']);
        if ($isBranchScoped) {
            $transfersQuery->where(function ($q) use ($authUser) {
                $q->where('source_warehouse_id', $authUser->warehouse_id)
                  ->orWhere('destination_warehouse_id', $authUser->warehouse_id);
            });
        }
        if ($request->filled('transfer_status')) {
            $transfersQuery->where('status', $request->transfer_status);
        }
        $transfers = $transfersQuery->orderBy('created_at', 'desc')->take(50)->get();
        $totalDiscrepancyUnits = 0;
        foreach ($transfers as $trf) {
            if ($trf->status === 'DISCREPANCY') {
                foreach ($trf->items as $item) {
                    $totalDiscrepancyUnits += max(0, $item->discrepancy_qty);
                }
            }
        }

        // 5. Debt Aging Analysis
        $debtorsQuery = Customer::where('total_debt', '>', 0);
        if ($isBranchScoped && $sales->isNotEmpty()) {
            $debtorsQuery->whereIn('id', $sales->pluck('customerId')->filter());
        }
        $debtors = $debtorsQuery->orderBy('total_debt', 'desc')->get()->map(function ($c) {
            $daysOld = $c->updated_at ? Carbon::parse($c->updated_at)->diffInDays(now()) : 0;
            $c->aging_category = $daysOld > 30 ? 'CRITICAL (30+ Days)' : ($daysOld > 7 ? 'DUE (8-30 Days)' : 'CURRENT (0-7 Days)');
            return $c;
        });

        // 6. Damaged Goods Write-offs
        $adjustmentsQuery = StockAdjustment::with('warehouse');
        if ($isBranchScoped) {
            $adjustmentsQuery->where('warehouse_id', $authUser->warehouse_id);
        }
        $adjustments = $adjustmentsQuery->orderBy('created_at', 'desc')->take(50)->get();
        $totalDamagedUnits = $adjustments->sum('quantity');

        // 7. Immutable Activity Logs
        $activitiesQuery = Activity::query();
        if ($isBranchScoped && $shopStaffIds->isNotEmpty()) {
            $activitiesQuery->whereIn('userId', $shopStaffIds);
        }
        $activities = $activitiesQuery->orderBy('timestamp', 'desc')->take(50)->get();

        // 8. Returns & Refunds Query
        $returnsQuery = SalesReturn::query();
        if ($isBranchScoped && $sales->isNotEmpty()) {
            $returnsQuery->whereIn('saleId', $sales->pluck('id'));
        }
        if ($fromDate && $toDate) {
            $returnsQuery->whereBetween('createdAt', [
                Carbon::parse($fromDate)->startOfDay()->toIso8601String(),
                Carbon::parse($toDate)->endOfDay()->toIso8601String()
            ]);
        } elseif ($datePreset === 'TODAY') {
            $returnsQuery->whereDate('createdAt', Carbon::today());
        } elseif ($datePreset === 'YESTERDAY') {
            $returnsQuery->whereDate('createdAt', Carbon::yesterday());
        } elseif ($datePreset === 'THIS_WEEK') {
            $returnsQuery->whereBetween('createdAt', [
                Carbon::now()->startOfWeek()->toIso8601String(),
                Carbon::now()->endOfWeek()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_MONTH') {
            $returnsQuery->whereBetween('createdAt', [
                Carbon::now()->startOfMonth()->toIso8601String(),
                Carbon::now()->endOfMonth()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_YEAR') {
            $returnsQuery->whereBetween('createdAt', [
                Carbon::now()->startOfYear()->toIso8601String(),
                Carbon::now()->endOfYear()->toIso8601String()
            ]);
        }

        if ($request->filled('user_name')) {
            $returnsQuery->where('userName', 'like', "%{$request->user_name}%");
        }

        if ($request->filled('search')) {
            $sText = trim($request->search);
            $returnsQuery->where(function ($q) use ($sText) {
                $q->where('saleId', 'like', "%{$sText}%")
                  ->orWhere('customerName', 'like', "%{$sText}%")
                  ->orWhere('productName', 'like', "%{$sText}%");
            });
        }

        $returns = $returnsQuery->orderBy('createdAt', 'desc')->get();
        $totalRefunded = $returns->sum('refundAmount');

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
        $filters = $request->all();

        return new StreamedResponse(function () use ($type, $isBranchScoped, $branchWarehouseId, $accountingService, $filters) {
            $handle = fopen('php://output', 'w');

            if ($type === 'sales') {
                fputcsv($handle, ['SALE ID', 'DATE', 'CUSTOMER', 'BRANCH', 'TOTAL AMOUNT', 'PAID AMOUNT', 'DEBT BALANCE', 'DELIVERY STATUS', 'CASHIER']);
                $salesQuery = $accountingService->buildSalesQuery($filters);
                foreach ($salesQuery->cursor() as $s) {
                    $debt = max(0, $s->totalAmount - $s->paidAmount);
                    fputcsv($handle, [
                        $s->id,
                        $s->createdAt,
                        $s->customerName,
                        $s->warehouse->name ?? 'Main Branch',
                        $s->totalAmount,
                        $s->paidAmount,
                        $debt,
                        $s->deliveryStatus,
                        $s->userName
                    ]);
                }
            } elseif ($type === 'inventory') {
                fputcsv($handle, ['Product ID', 'SKU', 'Product Name', 'Category', 'Brand', 'Size', 'Selling Price (NGN)', 'Total Physical Shelf Units', 'Stock Status', 'Total Asset Valuation (NGN)']);
                foreach (Product::where('archived', false)->cursor() as $p) {
                    $stockQuery = StockLevel::where('product_id', $p->id);
                    if ($isBranchScoped) {
                        $stockQuery->where('warehouse_id', $branchWarehouseId);
                    }
                    $stock = $stockQuery->sum('physical_stock');
                    $status = $stock <= 0 ? 'OUT_OF_STOCK' : ($stock <= 5 ? 'LOW_STOCK' : 'IN_STOCK');
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
                $debtorsQuery = Customer::where('total_debt', '>', 0);
                if ($isBranchScoped) {
                    $branchSaleCustomerIds = Sale::where('warehouse_id', $branchWarehouseId)->pluck('customerId')->filter();
                    $debtorsQuery->whereIn('id', $branchSaleCustomerIds);
                }
                foreach ($debtorsQuery->cursor() as $c) {
                    fputcsv($handle, [$c->name, $c->phone, $c->address, $c->total_debt, $c->updated_at]);
                }
            } elseif ($type === 'damages') {
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
        $filters = $request->all();

        $salesQuery = $accountingService->buildSalesQuery($filters);
        $transfersQuery = $accountingService->buildTransfersQuery($filters);
        $returnsQuery = $accountingService->buildReturnsQuery($filters);
        $damagesQuery = $accountingService->buildStockMovementsQuery($filters);

        $debtorsQuery = Customer::where('total_debt', '>', 0);
        if ($isBranchScoped) {
            $branchSaleCustomerIds = Sale::where('warehouse_id', $branchWarehouseId)
                ->whereNotNull('customerId')
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->pluck('customerId')
                ->filter()
                ->unique();
            $debtorsQuery->whereIn('id', $branchSaleCustomerIds);
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
                'data' => $salesQuery->get()
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
                'data' => $debtorsQuery->get()
            ],
            'damages' => [
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
