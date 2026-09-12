<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Activity;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Build filtered products and attach branch-scoped stock levels.
     * Returns: [$products, $warehouses, $categories, $isBranchScoped]
     */
    protected function buildFilteredProducts(Request $request): array
    {
        $query = Product::where('archived', false);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $minPrice = $request->filled('min_price') ? max(0.0, (float) $request->min_price) : null;
        $maxPrice = $request->filled('max_price') ? max(0.0, (float) $request->max_price) : null;

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            // Swap if min > max so query remains valid
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        if ($minPrice !== null) {
            $query->where('unitPrice', '>=', $minPrice);
        }

        if ($maxPrice !== null) {
            $query->where('unitPrice', '<=', $maxPrice);
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
        $isBranchScoped = ($authUser && $authUser->isBranchScoped());

        if ($isBranchScoped) {
            $warehouses = Warehouse::where('id', $authUser->warehouse_id)->get();
        } else {
            $warehouses = Warehouse::where('is_active', true)->get();
        }

        $warehouseIds = $warehouses->pluck('id');
        $productsList = $query->orderBy('category')->orderBy('name')->get();
        $productIds = $productsList->pluck('id');

        // Batch-load all authorized branch stock levels in exactly 1 query
        $allStockLevels = StockLevel::whereIn('product_id', $productIds)
            ->whereIn('warehouse_id', $warehouseIds)
            ->get()
            ->groupBy('product_id');

        // Attach per-branch physical stocks strictly scoped to authorized warehouses
        $products = $productsList->map(function ($p) use ($allStockLevels) {
            $levels = $allStockLevels->get($p->id, collect());
            $p->branch_stocks = $levels->pluck('physical_stock', 'warehouse_id')->toArray();
            $p->total_physical_stock = array_sum($p->branch_stocks);
            return $p;
        });

        // Stock status filter
        if ($request->filled('stock_status')) {
            $status = strtoupper($request->stock_status);
            if ($status === 'OUT_OF_STOCK') {
                $products = $products->filter(fn($p) => $p->total_physical_stock <= 0)->values();
            } elseif ($status === 'LOW_STOCK') {
                $products = $products->filter(fn($p) => $p->total_physical_stock > 0 && $p->total_physical_stock <= ($p->minStockLevel ?? 5))->values();
            } elseif ($status === 'IN_STOCK') {
                $products = $products->filter(fn($p) => $p->total_physical_stock > ($p->minStockLevel ?? 5))->values();
            }
        }

        $categories = Product::distinct()->pluck('category')->filter()->values();

        return [$products, $warehouses, $categories, $isBranchScoped];
    }

    /**
     * Display all products with multi-criteria filters & stock breakdown across all authorized shops.
     */
    public function index(Request $request)
    {
        [$products, $warehouses, $categories] = $this->buildFilteredProducts($request);

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

        $stockService = app(\App\Services\StockService::class);
        $authUser = Auth::user();

        if ($authUser && $authUser->isBranchScoped() && !empty($authUser->warehouse_id)) {
            $warehouseId = (int) $authUser->warehouse_id;
        } else {
            $defaultWh = Warehouse::where('tenant_id', $tenantId)->first();
            $warehouseId = (int) ($request->warehouse_id ?? ($defaultWh ? $defaultWh->id : 1));
        }

        // Canonical assertion: warehouse must belong strictly to active tenant
        $warehouse = $stockService->assertTenantWarehouse($warehouseId);

        $productId = (string) Str::uuid();
        $initialStock = (int) ($request->initial_stock ?? 0);

        $product = Product::create([
            'id' => $productId,
            'tenant_id' => $tenantId,
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

        $userName = Auth::user()->name ?? 'Auditor / Admin';
        $userId = Auth::id() ?? 'ADMIN';

        // Initialize stock level record with 0 allocated stock and user-defined min_stock_alert
        StockLevel::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            [
                'tenant_id' => $tenantId,
                'physical_stock' => 0,
                'allocated_stock' => 0,
                'min_stock_alert' => (int) ($request->minStockLevel ?? 5),
            ]
        );

        if ($initialStock > 0) {
            // Authoritatively route physical stock addition through canonical StockService
            $this->stockService->recordStockIn(
                $product->id,
                $warehouse->id,
                $initialStock,
                'INITIAL_STOCK',
                $userId,
                $userName,
                'Initial catalog registration stock'
            );
        }

        Activity::create([
            'id' => (string) Str::uuid(),
            'type' => 'PRODUCT_CREATED',
            'description' => "{$userName} created product '{$product->name}' ({$product->code}) with initial stock: {$initialStock} units",
            'userId' => $userId,
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
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => '⛔ Permission Denied: You do not have permission to edit catalog products.'], 403);
            }
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

        $userName = Auth::user()->name ?? 'Auditor / Admin';
        $userId = Auth::id() ?? 'ADMIN';

        Activity::create([
            'id' => (string) Str::uuid(),
            'type' => 'PRODUCT_UPDATED',
            'description' => "{$userName} updated product '{$product->name}' ({$product->code}): Price ₦" . number_format($product->unitPrice, 2) . ", Category '{$product->category}'",
            'userId' => $userId,
            'userName' => $userName,
            'timestamp' => now()->toIso8601String(),
        ]);

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "✓ Product '{$product->name}' updated successfully.",
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'category' => $product->category,
                    'brand' => $product->brand,
                    'size' => $product->size,
                    'unitPrice' => (float) $product->unitPrice,
                    'unitPriceFormatted' => '₦' . number_format($product->unitPrice, 0),
                    'minStockLevel' => $product->minStockLevel,
                ]
            ]);
        }

        $returnUrl = $request->input('return_url');
        if (!empty($returnUrl) && filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            return redirect($returnUrl)->with('success', "✓ Product '{$product->name}' updated successfully.");
        }

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
            fputcsv($handle, ['name', 'code', 'category', 'brand', 'size', 'unitPrice', 'minStockLevel', 'initial_stock', 'description']);
            // Sample demonstration rows
            fputcsv($handle, ['Mama Gold Rice (50kg)', 'MAMA-RICE-50KG', 'Grains & Rice', 'Mama Gold', '50kg Bag', '78000', '10', '50', 'Premium parboiled long grain rice']);
            fputcsv($handle, ['Kings Vegetable Oil (25L)', 'KINGS-OIL-25L', 'Oils & Fats', 'Devon Kings', '25L Keg', '46500', '5', '30', 'Pure cholesterol free refined vegetable oil']);
            fputcsv($handle, ['Peak Milk Powder (900g)', 'PEAK-MILK-900G', 'Provisions', 'Friesland', '900g Tin', '7200', '15', '100', 'Rich creamy whole milk powder']);
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Bulk import products from an uploaded CSV file (Auditor Admin Only).
     */
    public function importCsv(Request $request)
    {
        if (Auth::check() && !Auth::user()->hasCapability('products.write') && Auth::user()->role !== 'admin') {
            return redirect()->route('products.index')->with('error', '⛔ Permission Denied: You lack the products.write capability to bulk import new catalog products.');
        }

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
            'warehouse_id' => 'nullable|numeric',
        ]);

        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $warehouseId = (int) $user->warehouse_id;
        } elseif ($request->filled('warehouse_id')) {
            $wh = Warehouse::find($request->warehouse_id);
            if (!$wh) {
                return redirect()->route('products.index')->with('error', 'Selected branch location does not exist.');
            }
            $warehouseId = (int) $wh->id;
        } else {
            $wh = Warehouse::first();
            $warehouseId = $wh ? (int) $wh->id : 1;
        }

        $this->stockService->assertTenantWarehouse($warehouseId);
        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return redirect()->route('products.index')->with('error', 'Uploaded CSV file is empty or invalid.');
        }

        if (count($header) > 20) {
            fclose($handle);
            return redirect()->route('products.index')->with('error', 'Uploaded CSV has too many columns. Maximum allowed is 20 columns.');
        }

        // Normalize header keys
        $headerMap = [];
        foreach ($header as $index => $col) {
            $cleaned = strtolower(trim(str_replace([' ', '_', '-'], '', $col)));
            $headerMap[$cleaned] = $index;
        }

        // Validate required headers
        $missingHeaders = [];
        if (!isset($headerMap['name'])) {
            $missingHeaders[] = 'name';
        }
        if (!isset($headerMap['code']) && !isset($headerMap['sku'])) {
            $missingHeaders[] = 'code (or sku)';
        }
        if (!isset($headerMap['category'])) {
            $missingHeaders[] = 'category';
        }
        if (!isset($headerMap['unitprice']) && !isset($headerMap['price'])) {
            $missingHeaders[] = 'unitPrice (or price)';
        }

        if (!empty($missingHeaders)) {
            fclose($handle);
            return redirect()->route('products.index')->with('error', 'Uploaded CSV is missing required column headers: ' . implode(', ', $missingHeaders) . '. Required headers: name, code, category, unitPrice.');
        }

        $codeCol = $headerMap['code'] ?? $headerMap['sku'];
        $nameCol = $headerMap['name'];
        $catCol = $headerMap['category'];
        $priceCol = $headerMap['unitprice'] ?? $headerMap['price'];
        $stockCol = $headerMap['initialstock'] ?? $headerMap['stock'] ?? $headerMap['quantity'] ?? null;
        $minStockCol = $headerMap['minstocklevel'] ?? $headerMap['minstock'] ?? null;
        $brandCol = $headerMap['brand'] ?? null;
        $sizeCol = $headerMap['size'] ?? null;
        $descCol = $headerMap['description'] ?? null;

        $rows = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) continue; // Skip empty rows
            $rowCount++;
            if ($rowCount > 1000) {
                fclose($handle);
                return redirect()->route('products.index')->with('error', 'Uploaded CSV exceeds maximum limit of 1000 rows per batch import.');
            }
            $rows[] = $row;
        }
        fclose($handle);

        if (empty($rows)) {
            return redirect()->route('products.index')->with('error', 'Uploaded CSV does not contain any data rows.');
        }

        // Row-by-row pre-validation
        $errors = [];
        $seenCodesInBatch = [];
        $validatedRows = [];

        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 2; // Row 1 is header

            $name = trim((string)($row[$nameCol] ?? ''));
            $rawCode = trim((string)($row[$codeCol] ?? ''));
            $cat = trim((string)($row[$catCol] ?? ''));
            $rawPrice = trim((string)($row[$priceCol] ?? ''));

            if ($name === '') {
                $errors[] = "Row {$rowNum}: 'name' is required.";
            }

            if ($rawCode === '') {
                $errors[] = "Row {$rowNum}: 'code' / 'sku' is required (silent auto-generation is disabled).";
            } else {
                $codeUpper = strtoupper($rawCode);
                if (isset($seenCodesInBatch[$codeUpper])) {
                    $errors[] = "Row {$rowNum}: Duplicate SKU '{$codeUpper}' found in upload (already defined on Row {$seenCodesInBatch[$codeUpper]}).";
                } else {
                    $seenCodesInBatch[$codeUpper] = $rowNum;
                }
            }

            if ($cat === '') {
                $errors[] = "Row {$rowNum}: 'category' is required.";
            }

            if ($rawPrice === '' || !is_numeric($rawPrice) || (float)$rawPrice < 0) {
                $errors[] = "Row {$rowNum}: 'unitPrice' must be a valid non-negative number.";
            }

            $initialStock = 0;
            if ($stockCol !== null && isset($row[$stockCol]) && trim((string)$row[$stockCol]) !== '') {
                $rawStock = trim((string)$row[$stockCol]);
                if (!is_numeric($rawStock) || (int)$rawStock < 0) {
                    $errors[] = "Row {$rowNum}: 'initial_stock' must be a non-negative integer.";
                } else {
                    $initialStock = (int)$rawStock;
                }
            }

            $minStock = 5;
            if ($minStockCol !== null && isset($row[$minStockCol]) && trim((string)$row[$minStockCol]) !== '') {
                $rawMin = trim((string)$row[$minStockCol]);
                if (!is_numeric($rawMin) || (int)$rawMin < 0) {
                    $errors[] = "Row {$rowNum}: 'minStockLevel' must be a non-negative integer.";
                } else {
                    $minStock = (int)$rawMin;
                }
            }

            $brand = ($brandCol !== null && isset($row[$brandCol])) ? trim((string)$row[$brandCol]) : null;
            $size = ($sizeCol !== null && isset($row[$sizeCol])) ? trim((string)$row[$sizeCol]) : null;
            $description = ($descCol !== null && isset($row[$descCol])) ? trim((string)$row[$descCol]) : null;

            $validatedRows[] = [
                'name' => $name,
                'code' => strtoupper($rawCode),
                'category' => $cat,
                'brand' => $brand !== '' ? $brand : null,
                'size' => $size !== '' ? $size : null,
                'description' => $description !== '' ? $description : null,
                'unitPrice' => (float)$rawPrice,
                'minStock' => $minStock,
                'initialStock' => $initialStock,
            ];
        }

        if (!empty($errors)) {
            $sampleErrors = array_slice($errors, 0, 8);
            $summary = "CSV Import Failed (" . count($errors) . " errors found): " . implode(' | ', $sampleErrors);
            if (count($errors) > 8) {
                $summary .= " ...and " . (count($errors) - 8) . " more errors.";
            }
            return redirect()->route('products.index')->with('error', $summary);
        }

        $importedCount = 0;
        $updatedCount = 0;

        DB::transaction(function () use ($validatedRows, $warehouseId, &$importedCount, &$updatedCount) {
            $stockService = app(\App\Services\StockService::class);
            $userId = Auth::id() ?? 'ADMIN';
            $userName = Auth::user()->name ?? 'Manager / Admin';

            foreach ($validatedRows as $v) {
                $product = Product::where('code', $v['code'])->first();
                if ($product) {
                    $product->update([
                        'name' => $v['name'],
                        'category' => $v['category'],
                        'brand' => $v['brand'] ?? $product->brand,
                        'size' => $v['size'] ?? $product->size,
                        'description' => $v['description'] ?? $product->description,
                        'unitPrice' => $v['unitPrice'] > 0 ? $v['unitPrice'] : $product->unitPrice,
                        'minStockLevel' => $v['minStock'],
                        'archived' => false,
                        'updatedAt' => now()->toIso8601String(),
                    ]);

                    if ($v['initialStock'] > 0) {
                        $stockService->recordStockIn(
                            $product->id,
                            $warehouseId,
                            $v['initialStock'],
                            'CSV Stock In',
                            $userId,
                            $userName,
                            "Bulk CSV Import Additional Stock for {$product->name}"
                        );
                    }

                    $updatedCount++;
                } else {
                    $productId = (string) Str::uuid();
                    $newProduct = Product::create([
                        'id' => $productId,
                        'name' => $v['name'],
                        'code' => $v['code'],
                        'category' => $v['category'],
                        'brand' => $v['brand'],
                        'size' => $v['size'],
                        'description' => $v['description'],
                        'unitPrice' => $v['unitPrice'],
                        'currentStock' => 0,
                        'minStockLevel' => $v['minStock'],
                        'archived' => false,
                        'updatedAt' => now()->toIso8601String(),
                    ]);

                    if ($v['initialStock'] > 0) {
                        $stockService->recordStockIn(
                            $newProduct->id,
                            $warehouseId,
                            $v['initialStock'],
                            'CSV Initial Balance',
                            $userId,
                            $userName,
                            "Bulk CSV Import Initial Stock for {$newProduct->name}"
                        );
                    } else {
                        $stockService->ensureStockLevelForAuthorizedMutation($newProduct->id, $warehouseId, false);
                    }

                    $importedCount++;
                }
            }

            Activity::create([
                'id' => (string) Str::uuid(),
                'type' => 'CSV_PRODUCTS_IMPORT',
                'description' => "{$userName} imported {$importedCount} new products and updated {$updatedCount} products via CSV bulk upload.",
                'userId' => $userId,
                'userName' => $userName,
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        return redirect()->route('products.index')->with('success', "✓ Bulk import complete! Added {$importedCount} new products, updated {$updatedCount} existing items.");
    }

    /**
     * Export Products Catalog to CSV for Excel / Google Sheets.
     * Respects active filters and strictly enforces branch-scoped stock isolation.
     */
    public function exportCsv(Request $request)
    {
        $fileName = "hysam_products_catalog_" . date('Y_m_d_His') . ".csv";
        [$products] = $this->buildFilteredProducts($request);

        return response()->stream(function () use ($products) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SKU / Code', 'Product Name', 'Category', 'Brand', 'Size', 'Selling Price (NGN)', 'Min Stock Alert', 'Total Physical Stock', 'Asset Value (NGN)']);

            foreach ($products as $p) {
                $stock = $p->total_physical_stock;
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
     * Respects active filters and strictly enforces branch-scoped stock isolation.
     */
    public function exportJson(Request $request)
    {
        $fileName = "hysam_products_catalog_" . date('Y_m_d_His') . ".json";
        [$products, , , $isBranchScoped] = $this->buildFilteredProducts($request);

        $mappedProducts = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'category' => $p->category,
                'brand' => $p->brand,
                'size' => $p->size,
                'description' => $p->description,
                'unitPrice' => (float) $p->unitPrice,
                'minStockLevel' => (int) $p->minStockLevel,
                'total_physical_stock' => $p->total_physical_stock,
                'total_asset_value' => $p->total_physical_stock * (float) $p->unitPrice,
                'branch_stock_breakdown' => $p->branch_stocks,
            ];
        });

        return response()->json([
            'metadata' => [
                'business' => 'Hysam Ventures Ltd',
                'report' => 'Master Products & Price Catalog',
                'generated_at' => now()->toIso8601String(),
                'total_skus' => $mappedProducts->count(),
                'branch_scoped' => $isBranchScoped,
            ],
            'products' => $mappedProducts,
        ], 200, [
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ], JSON_PRETTY_PRINT);
    }
}
