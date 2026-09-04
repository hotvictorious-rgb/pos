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
     * Display all products with multi-criteria filters & stock breakdown across all shops.
     */
    public function index(Request $request)
    {
        $query = Product::where('archived', false);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('min_price')) {
            $query->where('unitPrice', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('unitPrice', '<=', (float) $request->max_price);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%")
                  ->orWhere('brand', 'like', "%{$s}%");
            });
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role !== 'admin' && $authUser->role !== 'viewer' && !empty($authUser->warehouse_id)) {
            $warehouses = Warehouse::where('id', $authUser->warehouse_id)->get();
        } else {
            $warehouses = Warehouse::where('is_active', true)->get();
        }

        $products = $query->orderBy('name')->get();

        // Attach per-branch physical stocks and calculate total
        $products = $products->map(function ($p) use ($warehouses) {
            $p->branch_stocks = StockLevel::where('product_id', $p->id)->whereIn('warehouse_id', $warehouses->pluck('id'))->pluck('physical_stock', 'warehouse_id')->toArray();
            $p->total_physical_stock = array_sum($p->branch_stocks);
            return $p;
        });

        // Stock status filter
        if ($request->filled('stock_status')) {
            $status = $request->stock_status;
            if ($status === 'OUT_OF_STOCK') {
                $products = $products->filter(fn($p) => $p->total_physical_stock <= 0)->values();
            } elseif ($status === 'LOW_STOCK') {
                $products = $products->filter(fn($p) => $p->total_physical_stock > 0 && $p->total_physical_stock <= ($p->minStockLevel ?? 5))->values();
            } elseif ($status === 'IN_STOCK') {
                $products = $products->filter(fn($p) => $p->total_physical_stock > ($p->minStockLevel ?? 5))->values();
            }
        }

        $categories = Product::distinct()->pluck('category')->filter()->values();

        return view('products.index', compact('products', 'warehouses', 'categories'));
    }

    /**
     * Store a newly created product (Auditor Admin Only).
     */
    public function store(Request $request)
    {
        if (Auth::check() && !Auth::user()->hasCapability('products.write')) {
            return redirect()->route('products.index')->with('error', '⛔ Permission Denied: You do not have permission to create catalog products.');
        }

        $tenantId = session('tenant_id') ?? 'default-tenant';
        $request->validate([
            'name' => 'required|string|max:150',
            'code' => ['required', 'string', \Illuminate\Validation\Rule::unique('products', 'code')->where('tenant_id', $tenantId)],
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
        if (Auth::check() && !Auth::user()->hasCapability('products.write')) {
            return redirect()->route('products.index')->with('error', '⛔ Permission Denied: You do not have permission to edit catalog products.');
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
        if (Auth::check() && !Auth::user()->hasCapability('products.write')) {
            return redirect()->route('products.index')->with('error', '⛔ Permission Denied: You do not have permission to delete or archive catalog products.');
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

        if ($request->filled('warehouse_id')) {
            $wh = Warehouse::find($request->warehouse_id);
            if (!$wh) {
                return redirect()->route('products.index')->with('error', 'Selected branch location does not exist.');
            }
            $warehouseId = $wh->id;
        } else {
            $wh = Warehouse::first();
            $warehouseId = $wh ? $wh->id : 1;
        }
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

    /**
     * Export Products Catalog to CSV for Excel / Google Sheets.
     */
    public function exportCsv(Request $request)
    {
        $fileName = "hysam_products_catalog_" . date('Y_m_d_His') . ".csv";

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU / Code', 'Product Name', 'Category', 'Brand', 'Size', 'Selling Price (NGN)', 'Min Stock Alert', 'Total Physical Stock', 'Asset Value (NGN)']);

            $products = Product::where('archived', false)->orderBy('category')->orderBy('name')->get();
            foreach ($products as $p) {
                $stock = StockLevel::where('product_id', $p->id)->sum('physical_stock');
                fputcsv($handle, [
                    $p->code,
                    $p->name,
                    $p->category,
                    $p->brand ?? 'Standard',
                    $p->size ?? '',
                    $p->unitPrice,
                    $p->minStockLevel,
                    $stock,
                    $stock * (float) $p->unitPrice
                ]);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Export Products Catalog to Structured JSON format for AI analysis.
     */
    public function exportJson(Request $request)
    {
        $fileName = "hysam_products_catalog_" . date('Y_m_d_His') . ".json";

        $products = Product::with('stockLevels')->where('archived', false)->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'category' => $p->category,
                'brand' => $p->brand,
                'size' => $p->size,
                'unitPrice' => (float) $p->unitPrice,
                'minStockLevel' => (int) $p->minStockLevel,
                'total_physical_stock' => $p->stockLevels->sum('physical_stock'),
                'total_asset_value' => $p->stockLevels->sum('physical_stock') * (float) $p->unitPrice,
                'branch_stock_breakdown' => $p->stockLevels->pluck('physical_stock', 'warehouse_id'),
            ];
        });

        return response()->json([
            'metadata' => [
                'business' => 'Hysam Ventures Ltd',
                'report' => 'Master Products & Price Catalog',
                'generated_at' => now()->toIso8601String(),
                'total_skus' => $products->count(),
            ],
            'products' => $products,
        ], 200, [
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ], JSON_PRETTY_PRINT);
    }
}
