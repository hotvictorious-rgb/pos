<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Transfer;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class StockController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Helper to apply date filters to queries
     */
    protected function applyDateFilter($query, $dateColumn, $datePreset, $fromDate, $toDate)
    {
        if ($fromDate && $toDate) {
            $query->whereBetween($dateColumn, [
                Carbon::parse($fromDate)->startOfDay()->toIso8601String(),
                Carbon::parse($toDate)->endOfDay()->toIso8601String()
            ]);
        } elseif ($datePreset === 'TODAY') {
            $query->whereDate($dateColumn, Carbon::today());
        } elseif ($datePreset === 'YESTERDAY') {
            $query->whereDate($dateColumn, Carbon::yesterday());
        } elseif ($datePreset === 'THIS_WEEK') {
            $query->whereBetween($dateColumn, [
                Carbon::now()->startOfWeek()->toIso8601String(),
                Carbon::now()->endOfWeek()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_MONTH') {
            $query->whereBetween($dateColumn, [
                Carbon::now()->startOfMonth()->toIso8601String(),
                Carbon::now()->endOfMonth()->toIso8601String()
            ]);
        }
    }

    /**
     * Stock Management Hub with Filter Bar and Live Search.
     */
    /**
     * Stock Management Hub with Filter Bar and Live Search.
     */
    public function index(Request $request)
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        if ($warehouses->isEmpty()) {
            $default = Warehouse::create(['name' => 'Main Store / Shop 1', 'code' => 'SHOP-01']);
            $warehouses = collect([$default]);
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->isBranchScoped()) {
            $activeWarehouseId = $authUser->warehouse_id;
            $warehouses = Warehouse::where('id', $authUser->warehouse_id)->get();
        } else {
            $requestedId = $request->get('warehouse_id', session('active_warehouse_id', $warehouses->first()->id));
            if ($authUser && !$authUser->canAccessWarehouse($requestedId)) {
                $activeWarehouseId = $warehouses->first()->id;
            } else {
                $activeWarehouseId = $requestedId;
            }
        }
        $activeWarehouse = Warehouse::find($activeWarehouseId) ?? $warehouses->first();
        session(['active_warehouse_id' => $activeWarehouse->id]);

        $search = trim($request->get('search', ''));
        $category = $request->get('category');
        $stockStatus = $request->get('stock_status');

        $query = StockLevel::with('product')->where('warehouse_id', $activeWarehouse->id);

        if ($search) {
            $query->whereHas('product', function ($pq) use ($search) {
                $pq->where('name', 'like', "%{$search}%")
                   ->orWhere('code', 'like', "%{$search}%")
                   ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category) {
            $query->whereHas('product', function ($pq) use ($category) {
                $pq->where('category', $category);
            });
        }

        if ($stockStatus === 'OUT') {
            $query->where('physical_stock', '<=', 0);
        } elseif ($stockStatus === 'LOW') {
            $query->where('physical_stock', '>', 0)->where('physical_stock', '<=', 10);
        } elseif ($stockStatus === 'HEALTHY') {
            $query->where('physical_stock', '>', 10);
        }

        $stockLevels = $query->get();
        $allProducts = Product::where('archived', false)->get();
        $categories = Product::distinct()->whereNotNull('category')->pluck('category');
        $suppliers = Supplier::all();

        // Pending incoming transfers for this shop
        $incomingTransfers = Transfer::with(['source', 'items'])
            ->where('destination_warehouse_id', $activeWarehouse->id)
            ->where('status', 'DISPATCHED')
            ->get();

        // Count of unsupplied sales waiting in this shop
        $unsuppliedCount = Sale::where('deliveryStatus', 'UNSUPPLIED')
            ->where('warehouse_id', $activeWarehouse->id)
            ->count();

        // Stock Summary Metrics for this shop
        $totalItemsCount = $stockLevels->count();
        $totalPhysicalUnits = $stockLevels->sum('physical_stock');
        $lowStockCount = $stockLevels->where('physical_stock', '>', 0)->where('physical_stock', '<=', 10)->count();
        $outOfStockCount = $stockLevels->where('physical_stock', '<=', 0)->count();

        return view('stock.index', compact(
            'warehouses',
            'activeWarehouse',
            'stockLevels',
            'allProducts',
            'categories',
            'suppliers',
            'incomingTransfers',
            'unsuppliedCount',
            'totalItemsCount',
            'totalPhysicalUnits',
            'lowStockCount',
            'outOfStockCount',
            'category',
            'stockStatus',
            'search'
        ));
    }

    /**
     * Action 1: Record Goods In (Supplier arrival).
     */
    public function stockIn(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required',
            'product_id' => 'required',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $warehouseId = (int) $user->warehouse_id;
        } else {
            $warehouseId = (int) $request->warehouse_id;
            if ($user && !$user->canAccessWarehouse($warehouseId)) {
                return back()->withErrors(['error' => '🔒 Unauthorized: You cannot record stock in for an unassigned branch!']);
            }
        }

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Storekeeper';

        $this->stockService->recordStockIn(
            $request->product_id,
            $warehouseId,
            (int) $request->quantity,
            $request->supplier_name,
            $userId,
            $userName,
            $request->notes
        );

        return redirect()->route('stock.index')->with('success', '✓ Stock In recorded successfully! Physical count increased.');
    }

    /**
     * Action 2: Send Goods to Another Shop (Dispatch Transfer).
     * Strictly locks Source Shop to the staff member's assigned branch.
     */
    public function transferOut(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $sourceWarehouseId = (int) $user->warehouse_id;
        } else {
            $sourceWarehouseId = (int) $request->source_warehouse_id;
            if ($user && !$user->canDispatchTransfer($sourceWarehouseId)) {
                return back()->withErrors(['error' => '🔒 Unauthorized: You cannot dispatch transfers out of an unassigned branch!']);
            }
        }

        $destWarehouseId = (int) $request->destination_warehouse_id;

        if ($sourceWarehouseId === $destWarehouseId) {
            return back()->withErrors(['error' => 'Destination shop must be different from the source shop!'])->withInput();
        }

        $request->validate([
            'destination_warehouse_id' => 'required',
            'items' => 'required|array|min:1',
            'carrier_name' => 'required|string',
        ]);

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Dispatch Officer';

        $transfer = $this->stockService->initiateTransfer(
            $sourceWarehouseId,
            $destWarehouseId,
            $request->items,
            $request->carrier_name,
            $userId,
            $userName,
            $request->notes
        );

        return redirect()->route('stock.transfers')->with('success', "✓ Transfer #{$transfer->transfer_no} dispatched! Goods in transit to destination.");
    }

    /**
     * Action 3: Receive & Count Goods from Transfer.
     * Strictly verifies destination shop matches staff branch.
     */
    public function transferIn(Request $request, $id)
    {
        $request->validate([
            'counted_items' => 'required|array',
        ]);

        $user = Auth::user();
        $transferRecord = Transfer::findOrFail($id);

        if ($user && !$user->canReceiveTransfer($transferRecord)) {
            return back()->withErrors(['error' => '🔒 Unauthorized: You can only receive and count transfers sent to your assigned branch!']);
        }

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Receiving Storekeeper';

        $transfer = $this->stockService->receiveTransfer(
            (int) $id,
            $request->counted_items,
            $userId,
            $userName,
            $request->discrepancy_notes
        );

        if ($transfer->status === 'DISCREPANCY') {
            return redirect()->route('stock.transfers')->with('warning', "⚠️ Transfer Received with DISCREPANCY! Missing items flagged to Auditor.");
        }

        return redirect()->route('stock.transfers')->with('success', "✓ Transfer #{$transfer->transfer_no} successfully verified and added to shop physical count!");
    }

    /**
     * Action 4: Recall / Cancel Dispatched Transfer.
     * Allows source shop or admin to cancel an in-transit transfer and restore shelf stock.
     */
    public function recallTransfer(Request $request, $id)
    {
        $user = Auth::user();
        $transferRecord = Transfer::findOrFail($id);

        if ($user && !$user->canRecallTransfer($transferRecord)) {
            return back()->withErrors(['error' => '🔒 Unauthorized: You can only recall transfers dispatched out of your assigned branch!']);
        }

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Dispatch Officer';

        try {
            $transfer = $this->stockService->recallTransfer(
                (int) $id,
                $userId,
                $userName,
                $request->reason ?? 'Cancelled by source branch'
            );

            return redirect()->route('stock.transfers')->with('success', "✓ Transfer #{$transfer->transfer_no} has been cancelled! All items have been restored to your shop physical inventory.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Dedicated Transfers Management Hub (Accept & Dispatch) with full filters.
     */
    public function transfersList(Request $request)
    {
        $datePreset = $request->get('date_preset', 'ALL');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $status = $request->get('status');
        $sourceId = $request->get('source_warehouse_id');
        $destId = $request->get('destination_warehouse_id');
        $carrier = $request->get('carrier_name');
        $search = trim($request->get('search', ''));

        $authUser = Auth::user();
        $isBranchStaff = ($authUser && $authUser->isBranchScoped());
        $userWarehouse = $isBranchStaff ? Warehouse::find($authUser->warehouse_id) : null;

        $query = Transfer::with(['source', 'destination', 'items']);
        $this->applyDateFilter($query, 'created_at', $datePreset, $fromDate, $toDate);

        // 🔒 Strict Branch Scoping: Branch staff ONLY see transfers involving their assigned shop (Origin or Destination)
        if ($isBranchStaff) {
            $query->where(function ($q) use ($authUser) {
                $q->where('source_warehouse_id', $authUser->warehouse_id)
                  ->orWhere('destination_warehouse_id', $authUser->warehouse_id);
            });
        }

        if ($status) {
            $query->where('status', strtoupper($status));
        }
        if ($sourceId) {
            $query->where('source_warehouse_id', $sourceId);
        }
        if ($destId) {
            $query->where('destination_warehouse_id', $destId);
        }
        if ($carrier) {
            $query->where('carrier_name', $carrier);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transfer_no', 'like', "%{$search}%")
                  ->orWhere('carrier_name', 'like', "%{$search}%")
                  ->orWhere('dispatched_by', 'like', "%{$search}%")
                  ->orWhere('received_by', 'like', "%{$search}%");
            });
        }

        $allTransfers = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        // Scope KPI metrics strictly to this shop
        $kpiQuery = Transfer::query();
        if ($isBranchStaff) {
            $kpiQuery->where(function ($q) use ($authUser) {
                $q->where('source_warehouse_id', $authUser->warehouse_id)
                  ->orWhere('destination_warehouse_id', $authUser->warehouse_id);
            });
        }

        $pendingCount = (clone $kpiQuery)->where('status', 'DISPATCHED')->count();
        $receivedCount = (clone $kpiQuery)->where('status', 'RECEIVED')->count();
        $discrepancyCount = (clone $kpiQuery)->where('status', 'DISCREPANCY')->count();

        $warehouses = $isBranchStaff ? Warehouse::where('id', $authUser->warehouse_id)->get() : Warehouse::where('is_active', true)->get();
        $allWarehouses = $warehouses;
        $carriers = Transfer::distinct()->whereNotNull('carrier_name')->where('carrier_name', '!=', '')->pluck('carrier_name');
        $allProducts = Product::where('archived', false)->get();

        return view('stock.transfers', compact(
            'allTransfers',
            'pendingCount',
            'receivedCount',
            'discrepancyCount',
            'warehouses',
            'allWarehouses',
            'isBranchStaff',
            'userWarehouse',
            'carriers',
            'allProducts',
            'datePreset',
            'fromDate',
            'toDate',
            'status',
            'sourceId',
            'destId',
            'carrier',
            'search'
        ));
    }

    /**
     * Printable Transfer Waybill / Delivery Note.
     */
    public function waybill($id)
    {
        $transfer = Transfer::with(['source', 'destination', 'items'])->findOrFail($id);
        $user = Auth::user();
        if ($user && !$user->canAccessTransfer($transfer)) {
            abort(403, '🔒 Access Denied: Delivery waybill is restricted to origin or destination branch staff.');
        }
        return view('stock.waybill', compact('transfer'));
    }

    /**
     * View list of Unsupplied Goods awaiting customer pickup with filters.
     */
    public function unsuppliedList(Request $request)
    {
        $datePreset = $request->get('date_preset', 'ALL');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $search = trim($request->get('search', ''));
        $paymentStatus = $request->get('payment_status');

        $query = Sale::with('items')->where('deliveryStatus', 'UNSUPPLIED');
        $this->applyDateFilter($query, 'createdAt', $datePreset, $fromDate, $toDate);

        if ($paymentStatus === 'PAID') {
            $query->whereColumn('paidAmount', '>=', 'totalAmount');
        } elseif ($paymentStatus === 'PARTIAL') {
            $query->whereColumn('paidAmount', '<', 'totalAmount')->where('paidAmount', '>', 0);
        } elseif ($paymentStatus === 'UNPAID') {
            $query->where('paidAmount', '<=', 0);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('customerName', 'like', "%{$search}%")
                  ->orWhere('customerPhone', 'like', "%{$search}%");
            });
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->isBranchScoped()) {
            $activeWarehouse = Warehouse::find($authUser->warehouse_id) ?? Warehouse::first();
            $query->where('warehouse_id', $authUser->warehouse_id);
        } else {
            $activeWarehouseId = session('active_warehouse_id', Warehouse::first()->id ?? 1);
            $activeWarehouse = Warehouse::find($activeWarehouseId) ?? Warehouse::first();
        }

        $unsuppliedSales = $query->orderBy('createdAt', 'desc')->paginate(25)->withQueryString();
        $totalUnsuppliedOrders = (clone $query)->count();
        $totalUnsuppliedValue = (clone $query)->sum('totalAmount');

        return view('stock.unsupplied', compact(
            'unsuppliedSales',
            'activeWarehouse',
            'totalUnsuppliedOrders',
            'totalUnsuppliedValue',
            'datePreset',
            'fromDate',
            'toDate',
            'paymentStatus',
            'search'
        ));
    }

    /**
     * Handover Unsupplied Goods to Customer (Deducts physical closing stock now).
     */
    public function dispatchConfirm(Request $request, $saleId)
    {
        $sale = Sale::findOrFail($saleId);
        $user = Auth::user();
        $warehouseId = $sale->warehouse_id ?? ($user && $user->isBranchScoped() ? $user->warehouse_id : session('active_warehouse_id'));

        if ($user && !$user->canAccessWarehouse($warehouseId)) {
            return back()->withErrors(['error' => '🔒 Unauthorized: You cannot fulfill pickup orders belonging to another branch!']);
        }

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Dispatch Officer';

        try {
            $this->stockService->dispatchUnsuppliedSale($saleId, $warehouseId, $userId, $userName);
            return back()->with('success', '✓ Goods officially handed over to customer! Physical closing stock updated.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Stock Adjustments Hub (Damages, Expired, Lost write-offs) with filters.
     */
    public function adjustments(Request $request)
    {
        $datePreset = $request->get('date_preset', 'ALL');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $type = $request->get('type');
        $warehouseId = $request->get('warehouse_id');
        $search = trim($request->get('search', ''));

        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $warehouseId = $user->warehouse_id;
            $warehouses = Warehouse::where('id', $user->warehouse_id)->get();
        } else {
            $warehouses = Warehouse::where('is_active', true)->get();
            if ($warehouseId && $user && !$user->canAccessWarehouse($warehouseId)) {
                $warehouseId = null;
            }
        }

        $query = StockAdjustment::with('warehouse');
        $this->applyDateFilter($query, 'created_at', $datePreset, $fromDate, $toDate);

        if ($type) {
            $query->where('type', $type);
        }
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_id', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('performed_by', 'like', "%{$search}%");
            });
        }

        $adjustments = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();
        $totalAdjustmentsCount = (clone $query)->count();
        $totalUnitsLost = (clone $query)->sum('quantity');

        $products = Product::where('archived', false)->orderBy('name')->get();

        return view('stock.adjustments', compact(
            'adjustments',
            'warehouses',
            'products',
            'totalAdjustmentsCount',
            'totalUnitsLost',
            'datePreset',
            'fromDate',
            'toDate',
            'type',
            'warehouseId',
            'search'
        ));
    }

    /**
     * Record Stock Adjustment (Damages/Loss).
     */
    public function recordAdjustment(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $warehouseId = (int) $user->warehouse_id;
        } else {
            $warehouseId = (int) $request->warehouse_id;
            if ($user && !$user->canAccessWarehouse($warehouseId)) {
                return back()->withErrors(['error' => '🔒 Unauthorized: You cannot record stock adjustments for an unassigned branch!']);
            }
        }

        $request->validate([
            'product_id' => 'required',
            'type' => 'required|string',
            'quantity' => 'required|numeric|min:1',
            'reason' => 'required|string',
        ]);

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Storekeeper';

        try {
            $this->stockService->recordStockAdjustment(
                $request->product_id,
                $warehouseId,
                $request->type,
                (int) $request->quantity,
                $request->reason,
                $userId,
                $userName
            );

            return redirect()->route('stock.adjustments')->with('success', "✓ Stock adjustment recorded and deducted from physical shelf count.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
