<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Transfer;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Activity;
use App\Models\CashierShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditorController extends Controller
{
    /**
     * Auditor Anti-Theft & Reconciliation Hub.
     */
    public function index()
    {
        $warehouses = Warehouse::all();
        
        // 1. Theft / Variance Alerts (Transfers with missing units)
        $discrepancyTransfers = Transfer::with(['source', 'destination', 'items'])
            ->where('status', 'DISCREPANCY')
            ->orderBy('updated_at', 'desc')
            ->get();

        // 2. Physical Stock vs. Allocated Unsupplied Goods per Shop
        $stockOverview = Warehouse::with(['stockLevels.product'])->get()->map(function ($warehouse) {
            $totalPhysical = $warehouse->stockLevels->sum('physical_stock');
            $totalAllocated = $warehouse->stockLevels->sum('allocated_stock');
            $totalAvailable = max(0, $totalPhysical - $totalAllocated);
            $stockValue = $warehouse->stockLevels->sum(function ($sl) {
                return $sl->physical_stock * ($sl->product->unitPrice ?? 0);
            });

            return [
                'warehouse' => $warehouse,
                'total_physical' => $totalPhysical,
                'total_allocated' => $totalAllocated,
                'total_available' => $totalAvailable,
                'stock_value' => $stockValue,
            ];
        });

        // 3. Customer Debt Liability
        $totalCustomerDebt = Customer::sum('total_debt');
        $debtors = Customer::where('total_debt', '>', 0)->orderBy('total_debt', 'desc')->get();

        // 4. Undelivered / Unsupplied Sales Liability
        $unsuppliedSales = Sale::with('items')->where('deliveryStatus', 'UNSUPPLIED')->get();
        $unsuppliedValue = $unsuppliedSales->sum('totalAmount');

        // 5. Recent Cashier Shifts / Balancing
        $recentShifts = CashierShift::with('warehouse')->orderBy('created_at', 'desc')->take(10)->get();

        // 6. Immutable Activity Audit Log
        $recentActivities = Activity::orderBy('timestamp', 'desc')->take(25)->get();

        return view('auditor.index', compact(
            'warehouses',
            'discrepancyTransfers',
            'stockOverview',
            'totalCustomerDebt',
            'debtors',
            'unsuppliedSales',
            'unsuppliedValue',
            'recentShifts',
            'recentActivities'
        ));
    }

    /**
     * Cashier End-of-Day Shift Close & Cash Count Balancing.
     */
    public function closeShift(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required',
            'counted_cash' => 'required|numeric|min:0',
        ]);

        $warehouseId = (int) $request->warehouse_id;
        $countedCash = (float) $request->counted_cash;
        $userId = Auth::id() ?? 'CASHIER-1';
        $userName = Auth::user()->name ?? 'Cashier';

        // Calculate expected cash from sales today for this shop
        $todaySales = Sale::where('userId', $userId)
            ->whereDate('created_at', today())
            ->get();

        $cashSales = $todaySales->sum('cashAmount');
        $posSales = $todaySales->sum('posAmount');
        $expectedCash = $cashSales;
        $difference = $countedCash - $expectedCash;

        CashierShift::create([
            'warehouse_id' => $warehouseId,
            'cashier_id' => $userId,
            'cashier_name' => $userName,
            'opening_float' => 0,
            'cash_sales' => $cashSales,
            'pos_sales' => $posSales,
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'difference' => $difference,
            'status' => abs($difference) > 0 ? 'AUDITED' : 'CLOSED',
            'auditor_notes' => $difference < 0 ? "SHORTAGE OF ₦" . abs($difference) : ($difference > 0 ? "OVERAGE OF ₦" . $difference : "BALANCED"),
            'opened_at' => today(),
            'closed_at' => now(),
        ]);

        return redirect()->route('auditor.index')->with('success', '✓ Shift Closed and Cash Balancing submitted to Auditor!');
    }
}
