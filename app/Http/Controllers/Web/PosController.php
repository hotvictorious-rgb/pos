<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\Customer;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display the visual, child-friendly POS interface.
     */
    public function index(Request $request)
    {
        $warehouses = Warehouse::where('is_active', true)->get();
        if ($warehouses->isEmpty()) {
            // Seed a default shop if none exists
            $default = Warehouse::create([
                'name' => 'Main Store / Shop 1',
                'code' => 'SHOP-01',
                'address' => 'Hysam Ventures HQ',
                'manager_name' => 'Store Manager',
            ]);
            $warehouses = collect([$default]);
        }

        $activeWarehouseId = $request->get('warehouse_id', session('active_warehouse_id', $warehouses->first()->id));
        session(['active_warehouse_id' => $activeWarehouseId]);

        $activeWarehouse = Warehouse::find($activeWarehouseId) ?? $warehouses->first();

        // Get products with their stock levels at this warehouse
        $products = Product::where('archived', false)->get()->map(function ($product) use ($activeWarehouse) {
            $stock = StockLevel::where('product_id', $product->id)
                ->where('warehouse_id', $activeWarehouse->id)
                ->first();

            $product->physical_stock = $stock ? $stock->physical_stock : 0;
            $product->allocated_stock = $stock ? $stock->allocated_stock : 0;
            $product->available_stock = max(0, $product->physical_stock - $product->allocated_stock);
            return $product;
        });

        $categories = $products->pluck('category')->filter()->unique()->values();
        $customers = Customer::orderBy('name')->get();

        return view('pos.index', compact('products', 'categories', 'warehouses', 'activeWarehouse', 'customers'));
    }

    /**
     * Process POS Checkout (Full or Part-Payment, Supplied vs. Unsupplied).
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required',
            'items' => 'required|array|min:1',
            'totalAmount' => 'required|numeric|min:0',
            'paidAmount' => 'required|numeric|min:0',
            'is_supplied' => 'required', // 'yes' or 'no'
        ]);

        $warehouseId = (int) $request->warehouse_id;
        $isSuppliedNow = in_array(strtolower($request->is_supplied), ['1', 'yes', 'true', 'on']);
        $userId = Auth::id() ?? 'POS-USER-1';
        $userName = Auth::user()->name ?? 'Sales Officer';

        $saleData = [
            'totalAmount' => (float) $request->totalAmount,
            'paidAmount' => (float) $request->paidAmount,
            'cashAmount' => (float) ($request->cashAmount ?? 0),
            'posAmount' => (float) ($request->posAmount ?? 0),
            'transferAmount' => (float) ($request->transferAmount ?? 0),
            'customerName' => $request->customerName ?: 'Walk-in Customer',
            'customerPhone' => $request->customerPhone,
            'customerId' => $request->customerId,
            'note' => $request->note,
        ];

        try {
            $sale = $this->stockService->recordSale($saleData, $request->items, $warehouseId, $isSuppliedNow, $userId, $userName);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sale completed successfully!',
                    'saleId' => $sale->id,
                    'receiptUrl' => route('pos.receipt', $sale->id),
                ]);
            }

            return redirect()->route('pos.receipt', $sale->id)->with('success', 'Sale recorded successfully!');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Printable Visual Receipt / Invoice.
     */
    public function receipt($id)
    {
        $sale = Sale::with('items')->findOrFail($id);
        $warehouse = Warehouse::find(session('active_warehouse_id', 1)) ?? Warehouse::first();

        return view('pos.receipt', compact('sale', 'warehouse'));
    }

    /**
     * Display Sales Returns & Customer Refunds screen.
     */
    public function returns(Request $request)
    {
        $sales = Sale::with('items')->orderBy('created_at', 'desc')->take(20)->get();
        $recentReturns = \App\Models\SalesReturn::orderBy('created_at', 'desc')->take(15)->get();
        $warehouses = Warehouse::where('is_active', true)->get();

        return view('pos.returns', compact('sales', 'recentReturns', 'warehouses'));
    }

    /**
     * Process Sales Return (Restores stock to physical shelves).
     */
    public function processReturn(Request $request)
    {
        $request->validate([
            'sale_id' => 'required',
            'warehouse_id' => 'required',
            'items' => 'required|array|min:1',
            'refund_method' => 'required|string', // CASH_REFUND, DEBT_REDUCTION
            'reason' => 'required|string',
        ]);

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Sales Officer';

        try {
            $salesReturn = $this->stockService->recordSaleReturn(
                $request->sale_id,
                $request->items,
                (int) $request->warehouse_id,
                $request->refund_method,
                $request->reason,
                $userId,
                $userName
            );

            return redirect()->route('pos.returns')->with('success', "✓ Return #{$salesReturn->code} processed! Items restored to physical closing stock.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

