<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Transfer;
use App\Models\StockAdjustment;
use App\Models\InventoryLog;
use App\Models\User;
use App\Models\Payment;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the Location & Date Filterable Sleek Executive Dashboard.
     */
    public function index(Request $request)
    {
        $datePreset = strtoupper($request->get('date_preset', 'TODAY'));
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $authUser = \Illuminate\Support\Facades\Auth::user();
        $userRole = $authUser->role ?? 'admin';

        // 🔒 Branch Scoping & Session Synchronization
        if ($authUser && $authUser->isBranchScoped() && !empty($authUser->warehouse_id)) {
            $warehouseId = (int) $authUser->warehouse_id;
            $warehouses = Warehouse::where('id', $warehouseId)->get();
            $selectedWarehouse = Warehouse::find($warehouseId);
            $locationLabel = $selectedWarehouse ? $selectedWarehouse->name : 'My Branch';
        } else {
            if ($request->has('warehouse_id')) {
                $rawWh = $request->get('warehouse_id');
                if ($rawWh === 'ALL' || $rawWh === '' || is_null($rawWh)) {
                    $warehouseId = null;
                    session(['active_warehouse_id' => null]);
                } else {
                    $warehouseId = (int) $rawWh;
                    session(['active_warehouse_id' => $rawWh]);
                }
            } else {
                $warehouseId = session('active_warehouse_id') ? (int) session('active_warehouse_id') : null;
            }
            $warehouses = Warehouse::where('is_active', true)->get();
            $selectedWarehouse = $warehouseId ? Warehouse::find($warehouseId) : null;
            $locationLabel = $selectedWarehouse ? $selectedWarehouse->name : 'All Branches (Consolidated)';
        }

        // 1. Determine active date range for UI display
        $accountingService = app(AccountingReportService::class);
        $dateInfo = $accountingService->resolveDateRange($datePreset, $fromDate, $toDate);
        $rangeLabel = $dateInfo['label'];
        $startDate = $dateInfo['start'];
        $endDate = $dateInfo['end'];

        // Helper filter function for timestamps
        $applyDateFilter = function ($query, string $column) use ($startDate, $endDate, $datePreset) {
            if ($datePreset === 'ALL' || !$startDate || !$endDate) {
                return;
            }

            if ($column === 'createdAt' || $column === 'timestamp') {
                $query->whereBetween($column, [
                    $startDate->toIso8601String(),
                    $endDate->toIso8601String()
                ]);
            } else {
                $query->whereBetween($column, [
                    $startDate->toDateTimeString(),
                    $endDate->toDateTimeString()
                ]);
            }
        };

        // Filters for AccountingReportService
        $filters = [
            'date_preset' => $datePreset,
            'from_date'   => $fromDate,
            'to_date'     => $toDate,
        ];
        if ($warehouseId) {
            $filters['warehouse_id'] = $warehouseId;
        }

        // Authoritative Accounting Summary
        $periodSummary = $accountingService->getPeriodSummary($filters);
        $pendingOrders = $accountingService->getPendingOrdersAnalytics($warehouseId, $filters);

        // Cashier Personal Shift Metrics
        $mySalesQuery = Sale::with('items')->where('userId', $authUser->id ?? '');
        $applyDateFilter($mySalesQuery, 'createdAt');
        $mySales = (clone $mySalesQuery)->get();
        $mySalesCount = $mySales->count();
        $mySalesAmount = (float) $mySales->sum('totalAmount');
        $mySaleIds = $mySales->pluck('id');

        $myPaymentsQuery = Payment::whereIn('saleId', $mySaleIds);
        $applyDateFilter($myPaymentsQuery, 'timestamp');
        $myPayments = (clone $myPaymentsQuery)->get();

        $myCashAmount = (float) $myPayments->where('method', 'CASH')->where('amount', '>', 0)->sum('amount');
        $myPosAmount  = (float) $myPayments->where('method', 'POS')->where('amount', '>', 0)->sum('amount');
        $myTransferAmount = 0.0;

        // Cashier debt recoveries collected in cash during shift
        $myDebtCashQuery = CustomerLedger::where('type', 'PAYMENT')
            ->where('payment_method', 'CASH')
            ->where(function($q) use ($authUser) {
                $q->where('recorded_by', $authUser->name ?? '')
                  ->orWhere('recorded_by', 'like', "%{$authUser->id}%");
            });
        $applyDateFilter($myDebtCashQuery, 'created_at');
        $myDebtCashRecovered = (float) (clone $myDebtCashQuery)->sum('amount');

        // Cashier cash refunds paid out during shift
        $myCashRefunds = (float) abs($myPayments->where('method', 'REFUND_CASH')->sum('amount'));

        $myPaidAmount = round($myCashAmount + $myPosAmount, 2);
        $myDebtAmount = max(0.0, round($mySalesAmount - $myPaidAmount, 2));
        $myExpectedCashInDrawer = max(0.0, round($myCashAmount + $myDebtCashRecovered - $myCashRefunds, 2));
        $myRecentSales = (clone $mySalesQuery)->orderBy('createdAt', 'desc')->take(15)->get();

        // 2. Sales & Inflow Aggregates
        $salesCount = $periodSummary['invoiceCount'];
        $totalSalesAmount = $periodSummary['grossSales'];
        $netSales = $periodSummary['netSales'];
        $totalCashAmount = $periodSummary['cashFromSales'];
        $totalPosAmount = $periodSummary['posFromSales'];
        $cashDebtRecovered = $periodSummary['cashDebtRecovered'];
        $posDebtRecovered = $periodSummary['posDebtRecovered'];
        $totalCashInflow = $periodSummary['totalCashInflow'];
        $totalPosInflow = $periodSummary['totalPosInflow'];
        $totalCollections = $periodSummary['totalNetMoneyRealized'];
        $newDebtIncurred = $periodSummary['newDebtCreated'];
        $totalRefundAmount = $periodSummary['cashRefunded'];
        $returnsCount = $periodSummary['returnCount'];
        $totalReturnCredits = $periodSummary['totalReturnCredits'];

        // Returned units
        $returnsQuery = $accountingService->buildReturnsQuery($filters);
        $returnedUnits = (int) $returnsQuery->sum('quantity');

        // 3. Stock Movements (In & Out) - Strictly Scoped by Warehouse
        $stockInQuery = InventoryLog::whereIn('type', ['STOCK_IN', 'TRANSFER_IN', 'RETURN', 'SALES_RETURN']);
        $applyDateFilter($stockInQuery, 'timestamp');
        if ($warehouseId) {
            $stockInQuery->where('warehouse_id', $warehouseId);
        }
        $totalStockInUnits = (int) (clone $stockInQuery)->sum('quantity');

        $stockOutQuery = InventoryLog::where(function($q) {
            $q->whereIn('type', [
                'SALE',
                'DISPATCH_FULFILLED',
                'TRANSFER_OUT',
                'STOCK_OUT',
                'STOCK_ADJUSTMENT_DAMAGE',
                'STOCK_ADJUSTMENT_EXPIRED',
                'STOCK_ADJUSTMENT_LOST'
            ])->orWhere('type', 'like', 'STOCK_ADJUSTMENT%')
              ->orWhere(function ($sub) {
                  $sub->where('quantity', '<', 0)->whereNotIn('type', ['STOCK_IN', 'TRANSFER_IN', 'RETURN', 'SALES_RETURN']);
              });
        });
        $applyDateFilter($stockOutQuery, 'timestamp');
        if ($warehouseId) {
            $stockOutQuery->where('warehouse_id', $warehouseId);
        }
        $totalStockOutUnits = (int) abs((clone $stockOutQuery)->sum('quantity'));

        // 4. Debt Portfolio & Recovery
        $debtPaymentQuery = CustomerLedger::where('type', 'PAYMENT');
        $applyDateFilter($debtPaymentQuery, 'created_at');
        if ($warehouseId) {
            $debtPaymentQuery->where('warehouse_id', $warehouseId);
        }
        $debtRecoveredInPeriod = $periodSummary['debtRecovered'];
        $debtRecoveryCount = (clone $debtPaymentQuery)->count();

        $totalOutstandingDebt = $periodSummary['currentOutstanding'];
        if ($warehouseId) {
            $activeDebtorsCount = Sale::where('warehouse_id', $warehouseId)
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->whereNotNull('customerId')
                ->get()
                ->filter(fn($s) => $accountingService->calculateInvoiceBalance($s) > 0.01)
                ->pluck('customerId')
                ->unique()
                ->count();
        } else {
            $activeDebtorsCount = Customer::where('total_debt', '>', 0)->count();
        }

        // 5. Fulfillment & Unsupplied Backlog
        $unsuppliedCount = $pendingOrders['total_orders'];
        $unsuppliedValue = $pendingOrders['total_value'];
        $unsuppliedUnits = $pendingOrders['total_units'];

        // 6. Transfers & Discrepancies
        $transferQuery = Transfer::query();
        $applyDateFilter($transferQuery, 'created_at');
        if ($warehouseId) {
            $transferQuery->where(function ($q) use ($warehouseId) {
                $q->where('source_warehouse_id', $warehouseId)
                  ->orWhere('destination_warehouse_id', $warehouseId);
            });
        }
        $discrepancyCount = $accountingService->getTotalDiscrepancyUnits($filters);
        $inTransitCount = (clone $transferQuery)->whereIn('status', ['DISPATCHED', 'IN_TRANSIT', 'PENDING'])->count();

        // 7. Damaged Goods Adjustments
        $damagedUnits = $accountingService->getTotalDamagedUnits($filters);

        // 8. Physical Inventory & Valuation (Selling Price Valuation)
        $totalPhysicalUnits = $periodSummary['totalPhysicalUnits'];
        $totalStockValuation = $periodSummary['retailInventoryValue'];

        $lowStockQuery = StockLevel::where('physical_stock', '>', 0)->where('physical_stock', '<=', 5);
        $outOfStockQuery = StockLevel::where('physical_stock', '<=', 0);
        if ($warehouseId) {
            $lowStockQuery->where('warehouse_id', $warehouseId);
            $outOfStockQuery->where('warehouse_id', $warehouseId);
        }
        $lowStockCount = $lowStockQuery->count();
        $outOfStockCount = $outOfStockQuery->count();
        $totalProducts = Product::where('archived', false)->count();

        // 9. Multi-Branch Summary Breakdown (Batch loaded, Zero N+1 Queries)
        $whIds = $warehouses->pluck('id');
        $allBranchLevels = StockLevel::with('product')->whereIn('warehouse_id', $whIds)->get()->groupBy('warehouse_id');

        $branchBreakdown = $warehouses->map(function ($wh) use ($allBranchLevels) {
            $levels = $allBranchLevels->get($wh->id, collect());
            $units = (int) $levels->sum(fn($sl) => max(0, (int)$sl->physical_stock));
            $val = (float) $levels->sum(fn($sl) => max(0, (int)$sl->physical_stock) * ($sl->product->unitPrice ?? 0));
            $lowCount = $levels->where('physical_stock', '<=', 5)->count();
            return [
                'id' => $wh->id,
                'name' => $wh->name,
                'code' => $wh->code,
                'units' => $units,
                'valuation' => $val,
                'low_stock_alerts' => $lowCount,
            ];
        });

        return view('dashboard', compact(
            'warehouses',
            'warehouseId',
            'selectedWarehouse',
            'locationLabel',
            'datePreset',
            'fromDate',
            'toDate',
            'rangeLabel',
            'salesCount',
            'totalSalesAmount',
            'netSales',
            'totalCashAmount',
            'totalPosAmount',
            'cashDebtRecovered',
            'posDebtRecovered',
            'totalCashInflow',
            'totalPosInflow',
            'totalCollections',
            'newDebtIncurred',
            'returnsCount',
            'returnedUnits',
            'totalRefundAmount',
            'totalStockInUnits',
            'totalStockOutUnits',
            'debtRecoveredInPeriod',
            'debtRecoveryCount',
            'totalOutstandingDebt',
            'activeDebtorsCount',
            'unsuppliedCount',
            'unsuppliedValue',
            'unsuppliedUnits',
            'pendingOrders',
            'discrepancyCount',
            'inTransitCount',
            'damagedUnits',
            'totalProducts',
            'totalPhysicalUnits',
            'totalStockValuation',
            'lowStockCount',
            'outOfStockCount',
            'branchBreakdown',
            'userRole',
            'mySalesCount',
            'mySalesAmount',
            'myCashAmount',
            'myPosAmount',
            'myTransferAmount',
            'myPaidAmount',
            'myDebtAmount',
            'myExpectedCashInDrawer',
            'myRecentSales'
        ));
    }
}
