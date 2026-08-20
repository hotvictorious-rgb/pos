<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display all products with stock breakdown across all shops.
     */
    public function index()
    {
        $products = Product::where('archived', false)->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->get();

        // Attach per-branch physical stocks
        $products->map(function ($p) use ($warehouses) {
            $p->branch_stocks = StockLevel::where('product_id', $p->id)->pluck('physical_stock', 'warehouse_id')->toArray();
            return $p;
        });

        $categories = $products->pluck('category')->filter()->unique()->values();

        return view('products.index', compact('products', 'warehouses', 'categories'));
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|unique:products,code',
            'category' => 'required|string',
            'unitPrice' => 'required|numeric|min:0',
            'initial_stock' => 'nullable|numeric|min:0',
            'warehouse_id' => 'nullable|numeric',
        ]);

        $productId = (string) Str::uuid();
        $initialStock = (int) ($request->initial_stock ?? 0);
        $warehouseId = (int) ($request->warehouse_id ?? (Warehouse::first()->id ?? 1));

        $product = Product::create([
            'id' => $productId,
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'category' => $request->category,
            'size' => $request->size,
            'brand' => $request->brand,
            'description' => $request->description,
            'unitPrice' => (float) $request->unitPrice,
            'currentStock' => $initialStock,
            'minStockLevel' => (int) ($request->minStockLevel ?? 5),
            'archived' => false,
            'updatedAt' => now()->toIso8601String(),
        ]);

        // Initialize stock level for this warehouse
        StockLevel::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'physical_stock' => $initialStock,
            'allocated_stock' => 0,
            'min_stock_alert' => 5,
        ]);

        $userName = Auth::user()->name ?? 'Auditor / Admin';

        Activity::create([
            'id' => (string) Str::uuid(),
            'type' => 'PRODUCT_CREATED',
            'description' => "{$userName} created product '{$product->name}' ({$product->code}) with initial stock: {$initialStock} units",
            'userId' => Auth::id() ?? 'ADMIN',
            'userName' => $userName,
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('products.index')->with('success', "✓ Product '{$product->name}' created successfully!");
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'category' => 'required|string',
            'unitPrice' => 'required|numeric|min:0',
        ]);

        $product = Product::findOrFail($id);
        $product->update([
            'name' => $request->name,
            'category' => $request->category,
            'size' => $request->size,
            'brand' => $request->brand,
            'description' => $request->description,
            'unitPrice' => (float) $request->unitPrice,
            'minStockLevel' => (int) ($request->minStockLevel ?? 5),
            'updatedAt' => now()->toIso8601String(),
        ]);

        return redirect()->route('products.index')->with('success', "✓ Product '{$product->name}' updated successfully.");
    }

    /**
     * Archive/Delete a product safely.
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->archived = true;
        $product->save();

        return redirect()->route('products.index')->with('success', "✓ Product '{$product->name}' archived.");
    }
}
