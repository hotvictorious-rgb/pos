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

        // 5. Immutable Activity Audit Log
        $recentActivities = Activity::orderBy('timestamp', 'desc')->take(25)->get();

        return view('auditor.index', compact(
            'warehouses',
            'discrepancyTransfers',
            'stockOverview',
            'totalCustomerDebt',
            'debtors',
            'unsuppliedSales',
            'unsuppliedValue',
            'recentActivities'
        ));
    }
}
