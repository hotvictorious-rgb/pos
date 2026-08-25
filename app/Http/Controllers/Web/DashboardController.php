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
        $warehouseId = $request->get('warehouse_id');

        $warehouses = Warehouse::where('is_active', true)->get();
        $selectedWarehouse = $warehouseId ? Warehouse::find($warehouseId) : null;
        $locationLabel = $selectedWarehouse ? $selectedWarehouse->name : 'All Branches (Consolidated)';

        // 1. Determine active date range for UI display
        $rangeLabel = 'Today (' . Carbon::today()->format('d M Y') . ')';
        $startDate = null;
        $endDate = null;

        if ($fromDate && $toDate) {
            $datePreset = 'CUSTOM';
            $startDate = Carbon::parse($fromDate)->startOfDay();
            $endDate = Carbon::parse($toDate)->endOfDay();
            $rangeLabel = $startDate->format('d M Y') . ' — ' . $endDate->format('d M Y');
        } elseif ($datePreset === 'TODAY') {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
            $rangeLabel = 'Today (' . Carbon::today()->format('d M Y') . ')';
        } elseif ($datePreset === 'YESTERDAY') {
            $startDate = Carbon::yesterday()->startOfDay();
            $endDate = Carbon::yesterday()->endOfDay();
            $rangeLabel = 'Yesterday (' . Carbon::yesterday()->format('d M Y') . ')';
        } elseif ($datePreset === 'THIS_WEEK') {
            $startDate = Carbon::now()->startOfWeek()->startOfDay();
            $endDate = Carbon::now()->endOfWeek()->endOfDay();
            $rangeLabel = 'This Week (' . $startDate->format('d M') . ' — ' . $endDate->format('d M Y') . ')';
        } elseif ($datePreset === 'THIS_MONTH') {
            $startDate = Carbon::now()->startOfMonth()->startOfDay();
            $endDate = Carbon::now()->endOfMonth()->endOfDay();
            $rangeLabel = 'This Month (' . Carbon::now()->format('F Y') . ')';
        } elseif ($datePreset === 'THIS_YEAR') {
            $startDate = Carbon::now()->startOfYear()->startOfDay();
            $endDate = Carbon::now()->endOfYear()->endOfDay();
            $rangeLabel = 'This Year (' . Carbon::now()->format('Y') . ')';
        } elseif ($datePreset === 'ALL') {
            $rangeLabel = 'All-Time';
        }

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

        $authUser = \Illuminate\Support\Facades\Auth::user();
        $userRole = $authUser->role ?? 'admin';

        // Auto-scope branch if user is a manager/storekeeper/cashier assigned to a specific branch
        if ($userRole !== 'admin' && !empty($authUser->warehouse_id) && empty($warehouseId)) {
            $warehouseId = $authUser->warehouse_id;
            $selectedWarehouse = Warehouse::find($warehouseId);
            $locationLabel = $selectedWarehouse ? $selectedWarehouse->name : 'My Branch';
        }

        // Staff assigned to selected location
        $branchUserIds = $warehouseId ? User::where('warehouse_id', $warehouseId)->pluck('id') : collect([]);

        // Cashier Personal Shift Metrics
        $mySalesQuery = Sale::with('items')->where('userId', $authUser->id ?? '');
        $applyDateFilter($mySalesQuery, 'createdAt');
        $mySalesCount = (clone $mySalesQuery)->count();
        $mySalesAmount = (float) (clone $mySalesQuery)->sum('totalAmount');
        $myCashAmount = (float) (clone $mySalesQuery)->sum('cashAmount');
        $myPosAmount = (float) (clone $mySalesQuery)->sum('posAmount');
        $myTransferAmount = (float) (clone $mySalesQuery)->sum('transferAmount');
        $myPaidAmount = (float) (clone $mySalesQuery)->sum('paidAmount');
        $myDebtAmount = max(0, $mySalesAmount - $myPaidAmount);
        $myRecentSales = (clone $mySalesQuery)->orderBy('createdAt', 'desc')->take(15)->get();

        // 2. Sales & Revenue Aggregates
        $salesQuery = Sale::query();
        $applyDateFilter($salesQuery, 'createdAt');
        if ($warehouseId && $branchUserIds->isNotEmpty()) {
            $salesQuery->whereIn('userId', $branchUserIds);
        }

        $salesCount = (clone $salesQuery)->count();
        $totalSalesAmount = (float) (clone $salesQuery)->sum('totalAmount');
        $totalPaidAmount = (float) (clone $salesQuery)->sum('paidAmount');
        $totalCashAmount = (float) (clone $salesQuery)->sum('cashAmount');
        $totalPosAmount = (float) (clone $salesQuery)->sum('posAmount');
        $totalCollections = $totalCashAmount + $totalPosAmount;
        $newDebtIncurred = max(0, $totalSalesAmount - $totalPaidAmount);

        // 3. Returns & Refunds
        $returnsQuery = SalesReturn::query();
        $applyDateFilter($returnsQuery, 'createdAt');
        if ($warehouseId && $branchUserIds->isNotEmpty()) {
            $returnsQuery->whereIn('userId', $branchUserIds);
        }

        $returnsCount = (clone $returnsQuery)->count();
        $returnedUnits = (int) (clone $returnsQuery)->sum('quantity');
        $totalRefundAmount = (float) (clone $returnsQuery)->sum('refundAmount');

        // 4. Stock Movements (In & Out)
        $stockInQuery = InventoryLog::whereIn('type', ['STOCK_IN', 'TRANSFER_IN', 'RETURN']);
        $applyDateFilter($stockInQuery, 'timestamp');
        if ($warehouseId && $branchUserIds->isNotEmpty()) {
            $stockInQuery->whereIn('userId', $branchUserIds);
        }
        $totalStockInUnits = (int) (clone $stockInQuery)->sum('quantity');

        $stockOutQuery = InventoryLog::whereIn('type', ['STOCK_OUT', 'TRANSFER_OUT', 'ADJUSTMENT', 'DAMAGE']);
        $applyDateFilter($stockOutQuery, 'timestamp');
        if ($warehouseId && $branchUserIds->isNotEmpty()) {
            $stockOutQuery->whereIn('userId', $branchUserIds);
        }
        $totalStockOutUnits = (int) (clone $stockOutQuery)->sum('quantity');

        // 5. Debt Recovery
        $debtPaymentQuery = CustomerLedger::where('type', 'PAYMENT');
        $applyDateFilter($debtPaymentQuery, 'created_at');
        $debtRecoveredInPeriod = (float) (clone $debtPaymentQuery)->sum('amount');
        $debtRecoveryCount = (clone $debtPaymentQuery)->count();

        $totalOutstandingDebt = (float) Customer::sum('total_debt');
        $activeDebtorsCount = Customer::where('total_debt', '>', 0)->count();

        // 6. Fulfillment & Unsupplied Backlog
        $unsuppliedSalesQuery = Sale::whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
        if ($warehouseId && $branchUserIds->isNotEmpty()) {
            $unsuppliedSalesQuery->whereIn('userId', $branchUserIds);
        }
        $unsuppliedCount = (clone $unsuppliedSalesQuery)->count();
        $unsuppliedValue = (float) (clone $unsuppliedSalesQuery)->sum('totalAmount');

        // 7. Transfers & Anti-Theft Discrepancies
        $transferQuery = Transfer::query();
        $applyDateFilter($transferQuery, 'created_at');
        if ($warehouseId) {
            $transferQuery->where(function ($q) use ($warehouseId) {
                $q->where('source_warehouse_id', $warehouseId)
                  ->orWhere('destination_warehouse_id', $warehouseId);
            });
        }
        $discrepancyCount = (clone $transferQuery)->where('status', 'DISCREPANCY')->count();
        $inTransitCount = (clone $transferQuery)->whereIn('status', ['DISPATCHED', 'IN_TRANSIT', 'PENDING'])->count();

        // 8. Damaged Goods Adjustments
        $damageQuery = StockAdjustment::query();
        $applyDateFilter($damageQuery, 'created_at');
        if ($warehouseId) {
            $damageQuery->where('warehouse_id', $warehouseId);
        }
        $damagedUnits = (int) (clone $damageQuery)->sum('quantity');

        // 9. Physical Inventory & Valuation
        $stockLevelQuery = StockLevel::with('product');
        if ($warehouseId) {
            $stockLevelQuery->where('warehouse_id', $warehouseId);
        }
        $stockLevels = $stockLevelQuery->get();

        $totalPhysicalUnits = (int) $stockLevels->sum('physical_stock');
        $totalStockValuation = (float) $stockLevels->sum(function ($sl) {
            return $sl->physical_stock * ($sl->product->unitPrice ?? 0);
        });

        $lowStockQuery = StockLevel::where('physical_stock', '>', 0)->where('physical_stock', '<=', 5);
        $outOfStockQuery = StockLevel::where('physical_stock', '<=', 0);
        if ($warehouseId) {
            $lowStockQuery->where('warehouse_id', $warehouseId);
            $outOfStockQuery->where('warehouse_id', $warehouseId);
        }
        $lowStockCount = $lowStockQuery->count();
        $outOfStockCount = $outOfStockQuery->count();
        $totalProducts = Product::where('archived', false)->count();

        // 10. Multi-Branch Summary Breakdown (for executive view)
        $branchBreakdown = $warehouses->map(function ($wh) {
            $levels = StockLevel::with('product')->where('warehouse_id', $wh->id)->get();
            $units = (int) $levels->sum('physical_stock');
            $val = (float) $levels->sum(fn($sl) => $sl->physical_stock * ($sl->product->unitPrice ?? 0));
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
            'totalPaidAmount',
            'totalCashAmount',
            'totalPosAmount',
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
            'myRecentSales'
        ));
    }
}
