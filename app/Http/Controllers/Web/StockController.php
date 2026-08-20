<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Transfer;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Stock Management Hub with 3 big visual action cards.
     */
    public function index(Request $request)
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        if ($warehouses->isEmpty()) {
            $default = Warehouse::create(['name' => 'Main Store / Shop 1', 'code' => 'SHOP-01']);
            $warehouses = collect([$default]);
        }

        $activeWarehouseId = $request->get('warehouse_id', session('active_warehouse_id', $warehouses->first()->id));
        $activeWarehouse = Warehouse::find($activeWarehouseId) ?? $warehouses->first();

        // Get stocks for this warehouse
        $stockLevels = StockLevel::with('product')
            ->where('warehouse_id', $activeWarehouse->id)
            ->get();

        $allProducts = Product::where('archived', false)->get();
        $suppliers = Supplier::all();

        // Pending incoming transfers for this shop
        $incomingTransfers = Transfer::with(['source', 'items'])
            ->where('destination_warehouse_id', $activeWarehouse->id)
            ->where('status', 'DISPATCHED')
            ->get();

        // Count of unsupplied sales waiting in this shop
        $unsuppliedCount = Sale::where('deliveryStatus', 'UNSUPPLIED')->count();

        return view('stock.index', compact(
            'warehouses',
            'activeWarehouse',
            'stockLevels',
            'allProducts',
            'suppliers',
            'incomingTransfers',
            'unsuppliedCount'
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

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Storekeeper';

        $this->stockService->recordStockIn(
            $request->product_id,
            (int) $request->warehouse_id,
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
     */
    public function transferOut(Request $request)
    {
        $request->validate([
            'source_warehouse_id' => 'required',
            'destination_warehouse_id' => 'required|different:source_warehouse_id',
            'items' => 'required|array|min:1',
            'carrier_name' => 'required|string',
        ]);

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Dispatch Officer';

        $transfer = $this->stockService->initiateTransfer(
            (int) $request->source_warehouse_id,
            (int) $request->destination_warehouse_id,
            $request->items,
            $request->carrier_name,
            $userId,
            $userName,
            $request->notes
        );

        return redirect()->route('stock.index')->with('success', "✓ Transfer #{$transfer->transfer_no} dispatched! Goods in transit to destination.");
    }

    /**
     * Action 3: Receive & Count Goods from Transfer.
     */
    public function transferIn(Request $request, $id)
    {
        $request->validate([
            'counted_items' => 'required|array',
        ]);

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
            return redirect()->route('stock.index')->with('warning', "⚠️ Transfer Received with DISCREPANCY! Missing items flagged to Auditor.");
        }

        return redirect()->route('stock.index')->with('success', "✓ Transfer #{$transfer->transfer_no} successfully verified and added to shop physical count!");
    }

    /**
     * View list of Unsupplied Goods awaiting customer pickup.
     */
    public function unsuppliedList()
    {
        $unsuppliedSales = Sale::with('items')
            ->where('deliveryStatus', 'UNSUPPLIED')
            ->orderBy('createdAt', 'desc')
            ->get();

        $activeWarehouse = Warehouse::find(session('active_warehouse_id', 1)) ?? Warehouse::first();

        return view('stock.unsupplied', compact('unsuppliedSales', 'activeWarehouse'));
    }

    /**
     * Handover Unsupplied Goods to Customer (Deducts physical closing stock now).
     */
    public function dispatchConfirm(Request $request, $saleId)
    {
        $warehouseId = (int) session('active_warehouse_id', 1);
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
     * Stock Adjustments Hub (Damages, Expired, Lost write-offs).
     */
    public function adjustments()
    {
        $adjustments = \App\Models\StockAdjustment::with('warehouse')->orderBy('created_at', 'desc')->take(30)->get();
        $warehouses = Warehouse::where('is_active', true)->get();
        $products = Product::where('archived', false)->orderBy('name')->get();

        return view('stock.adjustments', compact('adjustments', 'warehouses', 'products'));
    }

    /**
     * Record Stock Adjustment (Damages/Loss).
     */
    public function recordAdjustment(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required',
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
                (int) $request->warehouse_id,
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

