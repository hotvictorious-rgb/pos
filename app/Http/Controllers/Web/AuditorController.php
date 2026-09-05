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
        $authUser = Auth::user();
        $assignedWarehouseId = ($authUser && !empty($authUser->warehouse_id)) ? $authUser->warehouse_id : null;

        $warehouses = $assignedWarehouseId 
            ? Warehouse::where('id', $assignedWarehouseId)->get() 
            : Warehouse::all();
        
        // 1. Theft / Variance Alerts (Transfers with missing units)
        $discrepancyQuery = Transfer::with(['source', 'destination', 'items'])->where('status', 'DISCREPANCY');
        if ($assignedWarehouseId) {
            $discrepancyQuery->where(function($q) use ($assignedWarehouseId) {
                $q->where('source_warehouse_id', $assignedWarehouseId)
                  ->orWhere('destination_warehouse_id', $assignedWarehouseId);
            });
        }
        $discrepancyTransfers = $discrepancyQuery->orderBy('updated_at', 'desc')->get();

        // 2. Physical Stock vs. Allocated Unsupplied Goods per Shop
        $stockOverview = $warehouses->map(function ($warehouse) {
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

        // 3. Customer Debt Liability (Branch scoped if worker is assigned to a specific shop)
        $debtorQuery = Customer::where('total_debt', '>', 0);
        if ($assignedWarehouseId) {
            $debtorQuery->whereHas('sales', function ($q) use ($assignedWarehouseId) {
                $q->where('warehouse_id', $assignedWarehouseId);
            });
        }
        $debtors = $debtorQuery->orderBy('total_debt', 'desc')->get();
        $totalCustomerDebt = (float) $debtors->sum('total_debt');

        // 4. Undelivered / Unsupplied Sales Liability
        $unsuppliedQuery = Sale::with('items')->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
        if ($assignedWarehouseId) {
            $unsuppliedQuery->where('warehouse_id', $assignedWarehouseId);
        }
        $unsuppliedSales = $unsuppliedQuery->get();
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
