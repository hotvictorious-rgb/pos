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
     * Store a newly created product (Auditor Admin Only).
     */
    public function store(Request $request)
    {
        if (Auth::check() && Auth::user()->role !== 'admin') {
            return redirect()->route('products.index')->with('error', '⛔ Permission Denied: Only Auditor / Super Admin can create catalog products. Branch managers and staff can add stock quantities via Stock In.');
        }

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
     * Update an existing product (Auditor Admin Only).
     */
    public function update(Request $request, $id)
    {
        if (Auth::check() && Auth::user()->role !== 'admin') {
            return redirect()->route('products.index')->with('error', '⛔ Permission Denied: Only Auditor / Super Admin can edit product catalog details.');
        }

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
     * Archive/Delete a product safely (Auditor Admin Only).
     */
    public function destroy($id)
    {
        if (Auth::check() && Auth::user()->role !== 'admin') {
            return redirect()->route('products.index')->with('error', '⛔ Permission Denied: Only Auditor / Super Admin can delete or archive catalog products.');
        }

        $product = Product::findOrFail($id);
        $product->archived = true;
        $product->save();

        return redirect()->route('products.index')->with('success', "✓ Product '{$product->name}' archived.");
    }

    /**
     * Download a clean CSV template for bulk product import.
     */
    public function downloadCsvTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="hysam_products_import_template.csv"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['name', 'code', 'category', 'brand', 'size', 'unitPrice', 'minStockLevel', 'initial_stock']);
            // Sample demonstration rows
            fputcsv($handle, ['Mama Gold Rice (50kg)', 'MAMA-RICE-50KG', 'Grains & Rice', 'Mama Gold', '50kg Bag', '78000', '10', '50']);
            fputcsv($handle, ['Kings Vegetable Oil (25L)', 'KINGS-OIL-25L', 'Oils & Fats', 'Devon Kings', '25L Keg', '46500', '5', '30']);
            fputcsv($handle, ['Peak Milk Powder (900g)', 'PEAK-MILK-900G', 'Provisions', 'Friesland', '900g Tin', '7200', '15', '100']);
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Bulk import products from an uploaded CSV file (Auditor Admin Only).
     */
    public function importCsv(Request $request)
    {
        if (Auth::check() && Auth::user()->role !== 'admin') {
            return redirect()->route('products.index')->with('error', '⛔ Permission Denied: Only Auditor / Super Admin can bulk import new catalog products.');
        }

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
            'warehouse_id' => 'nullable|numeric',
        ]);

        $warehouseId = (int) ($request->warehouse_id ?? (Warehouse::first()->id ?? 1));
        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        $header = fgetcsv($handle);
        if (!$header) {
            return redirect()->route('products.index')->with('error', 'Uploaded CSV file is empty or invalid.');
        }

        // Normalize header keys
        $headerMap = [];
        foreach ($header as $index => $col) {
            $cleaned = strtolower(trim(str_replace([' ', '_', '-'], '', $col)));
            $headerMap[$cleaned] = $index;
        }

        $importedCount = 0;
        $updatedCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) continue; // Skip empty rows

            $name = $row[$headerMap['name'] ?? -1] ?? null;
            if (!$name) continue;

            $code = $row[$headerMap['code'] ?? $headerMap['sku'] ?? -1] ?? null;
            if (!$code) {
                $code = 'SKU-' . strtoupper(Str::random(6));
            } else {
                $code = strtoupper(trim($code));
            }

            $category = $row[$headerMap['category'] ?? -1] ?? 'General Provisions';
            $brand = $row[$headerMap['brand'] ?? -1] ?? null;
            $size = $row[$headerMap['size'] ?? -1] ?? null;
            $unitPrice = (float) ($row[$headerMap['unitprice'] ?? $headerMap['price'] ?? -1] ?? 0);
            $minStock = (int) ($row[$headerMap['minstocklevel'] ?? $headerMap['minstock'] ?? -1] ?? 5);
            $initialStock = (int) ($row[$headerMap['initialstock'] ?? $headerMap['stock'] ?? $headerMap['quantity'] ?? -1] ?? 0);

            // Check if product exists by code
            $product = Product::where('code', $code)->first();
            if ($product) {
                $product->update([
                    'name' => $name,
                    'category' => $category,
                    'brand' => $brand,
                    'size' => $size,
                    'unitPrice' => $unitPrice > 0 ? $unitPrice : $product->unitPrice,
                    'minStockLevel' => $minStock,
                    'archived' => false,
                ]);

                if ($initialStock > 0) {
                    $stock = StockLevel::firstOrCreate(
                        ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
                        ['physical_stock' => 0]
                    );
                    $stock->physical_stock += $initialStock;
                    $stock->save();

                    $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                    $product->save();
                }

                $updatedCount++;
            } else {
                $productId = (string) Str::uuid();
                $newProduct = Product::create([
                    'id' => $productId,
                    'name' => $name,
                    'code' => $code,
                    'category' => $category,
                    'brand' => $brand,
                    'size' => $size,
                    'unitPrice' => $unitPrice,
                    'currentStock' => $initialStock,
                    'minStockLevel' => $minStock,
                    'archived' => false,
                    'updatedAt' => now()->toIso8601String(),
                ]);

                StockLevel::create([
                    'product_id' => $newProduct->id,
                    'warehouse_id' => $warehouseId,
                    'physical_stock' => $initialStock,
                ]);

                $importedCount++;
            }
        }

        fclose($handle);

        $userName = Auth::user()->name ?? 'Manager / Admin';
        Activity::create([
            'id' => (string) Str::uuid(),
            'type' => 'CSV_PRODUCTS_IMPORT',
            'description' => "{$userName} imported {$importedCount} new products and updated {$updatedCount} products via CSV bulk upload.",
            'userId' => Auth::id() ?? 'ADMIN',
            'userName' => $userName,
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('products.index')->with('success', "✓ Bulk import complete! Added {$importedCount} new products, updated {$updatedCount} existing items.");
    }
}
