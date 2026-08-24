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
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the Date-Filterable Executive Dashboard.
     */
    public function index(Request $request)
    {
        $datePreset = strtoupper($request->get('date_preset', 'TODAY'));
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        // Determine active date range for UI display
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

        // Helper filter function
        $applyFilter = function ($query, string $column) use ($startDate, $endDate, $datePreset) {
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

        // 1. Sales & Revenue Aggregates (Filtered)
        $salesQuery = Sale::query();
        $applyFilter($salesQuery, 'createdAt');

        $salesCount = (clone $salesQuery)->count();
        $totalSalesAmount = (float) (clone $salesQuery)->sum('totalAmount');
        $totalPaidAmount = (float) (clone $salesQuery)->sum('paidAmount');
        $totalCashAmount = (float) (clone $salesQuery)->sum('cashAmount');
        $totalPosAmount = (float) (clone $salesQuery)->sum('posAmount');
        $newDebtIncurred = max(0, $totalSalesAmount - $totalPaidAmount);

        // 2. Returns & Refunds (Filtered)
        $returnsQuery = SalesReturn::query();
        $applyFilter($returnsQuery, 'createdAt');

        $returnsCount = (clone $returnsQuery)->count();
        $returnedUnits = (int) (clone $returnsQuery)->sum('quantity');
        $totalRefundAmount = (float) (clone $returnsQuery)->sum('refundAmount');

        // 3. Stock Movements: In & Out (Filtered)
        $stockInQuery = InventoryLog::whereIn('type', ['STOCK_IN', 'TRANSFER_IN', 'RETURN']);
        $applyFilter($stockInQuery, 'timestamp');
        $totalStockInUnits = (int) (clone $stockInQuery)->sum('quantity');

        $stockOutQuery = InventoryLog::whereIn('type', ['STOCK_OUT', 'TRANSFER_OUT', 'ADJUSTMENT', 'DAMAGE']);
        $applyFilter($stockOutQuery, 'timestamp');
        $totalStockOutUnits = (int) (clone $stockOutQuery)->sum('quantity');

        // 4. Debts & Recovery (Filtered & All-Time)
        $debtPaymentQuery = CustomerLedger::where('type', 'PAYMENT');
        $applyFilter($debtPaymentQuery, 'created_at');
        $debtRecoveredInPeriod = (float) (clone $debtPaymentQuery)->sum('amount');
        $debtRecoveryCount = (clone $debtPaymentQuery)->count();

        $totalOutstandingDebt = (float) Customer::sum('total_debt');
        $activeDebtorsCount = Customer::where('total_debt', '>', 0)->count();

        // 5. Pending Deliveries & Unsupplied Goods Liability
        // Filtered within period:
        $unsuppliedInPeriodCount = (clone $salesQuery)->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending'])->count();
        $unsuppliedInPeriodValue = (float) (clone $salesQuery)->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending'])->sum('totalAmount');

        // All-Time Active Unsupplied Backlog:
        $allUnsuppliedSales = Sale::whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending'])->get();
        $unsuppliedCount = $allUnsuppliedSales->count();
        $unsuppliedValue = (float) $allUnsuppliedSales->sum('totalAmount');

        // 6. Anti-Theft, Discrepancies & Losses (Filtered)
        $transferQuery = Transfer::query();
        $applyFilter($transferQuery, 'created_at');
        $discrepancyCount = (clone $transferQuery)->where('status', 'DISCREPANCY')->count();
        $inTransitCount = Transfer::whereIn('status', ['DISPATCHED', 'IN_TRANSIT', 'PENDING'])->count();

        $damageQuery = StockAdjustment::query();
        $applyFilter($damageQuery, 'created_at');
        $damagedUnits = (int) (clone $damageQuery)->sum('quantity');

        // 7. General Inventory Overview (All-Time Snapshot)
        $totalProducts = Product::where('archived', false)->count();
        $totalWarehouses = Warehouse::where('is_active', true)->count();
        $totalPhysicalUnits = (int) StockLevel::sum('physical_stock');

        $stockLevels = StockLevel::with('product')->get();
        $totalStockValuation = (float) $stockLevels->sum(function ($sl) {
            return $sl->physical_stock * ($sl->product->unitPrice ?? 0);
        });

        $lowStockCount = StockLevel::where('physical_stock', '>', 0)->where('physical_stock', '<=', 5)->count();
        $outOfStockCount = StockLevel::where('physical_stock', '<=', 0)->count();

        return view('dashboard', compact(
            'datePreset',
            'fromDate',
            'toDate',
            'rangeLabel',
            'salesCount',
            'totalSalesAmount',
            'totalPaidAmount',
            'totalCashAmount',
            'totalPosAmount',
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
            'unsuppliedInPeriodCount',
            'unsuppliedInPeriodValue',
            'unsuppliedCount',
            'unsuppliedValue',
            'discrepancyCount',
            'inTransitCount',
            'damagedUnits',
            'totalProducts',
            'totalWarehouses',
            'totalPhysicalUnits',
            'totalStockValuation',
            'lowStockCount',
            'outOfStockCount'
        ));
    }
}
