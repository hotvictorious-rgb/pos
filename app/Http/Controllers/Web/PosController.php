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

        $user = Auth::user();
        if ($user && !$user->isExecutive() && empty($user->warehouse_id)) {
            abort(403, '🔒 Access Restricted: You are not assigned to any branch location. Please contact an administrator.');
        }

        if ($user && $user->isBranchScoped()) {
            $activeWarehouseId = $user->warehouse_id;
            $warehouses = Warehouse::where('id', $user->warehouse_id)->get();
        } else {
            $candidateId = $request->get('warehouse_id') ?: session('active_warehouse_id');
            if (!$candidateId || !$warehouses->contains('id', $candidateId)) {
                $candidateId = $warehouses->first()->id;
            }
            if ($user && !$user->canAccessWarehouse($candidateId)) {
                abort(403, '🔒 Access Restricted: You do not have permission to access this branch.');
            }
            $activeWarehouseId = $candidateId;
        }
        session(['active_warehouse_id' => $activeWarehouseId]);

        $activeWarehouse = Warehouse::find($activeWarehouseId) ?? $warehouses->first();


        // Batch load products with their stock levels at this warehouse (Zero N+1 Queries)
        $productsList = Product::where('archived', false)->get();
        $productIds = $productsList->pluck('id');

        $stockLevelsMap = StockLevel::whereIn('product_id', $productIds)
            ->where('warehouse_id', $activeWarehouse->id)
            ->get()
            ->keyBy('product_id');

        $products = $productsList->map(function ($product) use ($stockLevelsMap) {
            $stock = $stockLevelsMap->get($product->id);

            $product->physical_stock = $stock ? $stock->physical_stock : 0;
            $product->allocated_stock = $stock ? $stock->allocated_stock : 0;
            $product->reservation_shortfall = max(0, $product->allocated_stock - $product->physical_stock);
            $product->net_position = $product->physical_stock - $product->allocated_stock;
            // Physical stock is the authoritative capacity available for immediate walk-in / supplied sale
            $product->available_stock = $product->physical_stock;
            return $product;
        });

        $categories = $products->pluck('category')->filter()->unique()->values();
        $customers = Customer::orderBy('name')->get();

        return view('pos.index', compact('products', 'categories', 'warehouses', 'activeWarehouse', 'customers'));
    }

    /**
     * Quick-Register or Update Customer directly from POS checkout.
     */
    public function quickRegisterCustomer(Request $request)
    {
        $rawPhone = preg_replace('/[\s\-\(\)\+]/', '', trim($request->phone ?? ''));
        if (str_starts_with($rawPhone, '234') && strlen($rawPhone) === 13) {
            $rawPhone = '0' . substr($rawPhone, 3);
        }
        $request->merge(['phone' => $rawPhone]);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'regex:/^0\d{10}$/'],
            'address' => 'nullable|string|max:500',
        ], [
            'phone.regex' => 'Customer phone number must be exactly 11 digits (e.g. 08031234567).',
        ]);

        $phone = $rawPhone;
        $name = trim($request->name);

        $customer = Customer::where('phone', $phone)->first();
        if ($customer) {
            $customer->name = $name;
            if ($request->filled('address')) $customer->address = $request->address;
            $customer->save();
        } else {
            $customer = Customer::create([
                'name' => $name,
                'phone' => $phone,
                'address' => $request->address,
                'total_debt' => 0,
            ]);
        }

        // Security & Operations Audit Log (Fail-safe isolated)
        try {
            $isNew = $customer->wasRecentlyCreated ?? false;
            $actionType = $isNew ? 'CUSTOMER_REGISTERED' : 'CUSTOMER_UPDATED';
            \App\Models\Activity::recordSecurityEvent($actionType, "Customer '{$customer->name}' ({$customer->phone}, Code: {$customer->customer_code}) " . ($isNew ? 'registered' : 'updated') . " via POS quick registration.", [
                'customer_id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'was_new' => $isNew,
            ]);
        } catch (\Throwable $auditEx) {
            \Illuminate\Support\Facades\Log::warning("CUSTOMER_REGISTERED audit log isolated failure: {$auditEx->getMessage()}", [
                'customer_id' => $customer->id ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Customer {$customer->name} ({$customer->customer_code}) registered successfully!",
            'customer' => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'total_debt' => (float) $customer->total_debt,
                'address' => $customer->address,
            ],
        ]);
    }

    /**
     * Search and lookup past sales by Receipt Reference / Invoice ID or Customer Phone.
     * Returns authentic sold items and prices for accurate exchange returns.
     */
    public function lookupSale(Request $request)
    {
        $term = trim($request->get('term', $request->get('query', '')));
        if (empty($term)) {
            return response()->json(['success' => false, 'error' => 'Please enter an Invoice, Receipt Slip Number, or Phone Number.'], 422);
        }

        $user = Auth::user();
        $warehouseId = $user && $user->isBranchScoped() ? $user->warehouse_id : ($request->warehouse_id ?: session('active_warehouse_id'));

        // Query sale by ID prefix / exact ID, customerPhone, customerName, or receipt ref in note
        $cleanTerm = ltrim($term, '#');
        $query = Sale::with(['items.product'])->orderBy('createdAt', 'desc');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $sale = (clone $query)->where(function ($q) use ($cleanTerm) {
            $q->where('id', 'like', "{$cleanTerm}%")
              ->orWhere('customerPhone', 'like', "%{$cleanTerm}%")
              ->orWhere('customerName', 'like', "%{$cleanTerm}%")
              ->orWhere('note', 'like', "%{$cleanTerm}%");
        })->first();

        if (!$sale) {
            return response()->json(['success' => false, 'error' => "No sale found matching '{$term}' at this branch."], 404);
        }

        $isSupplied = in_array(strtoupper($sale->deliveryStatus ?? ''), ['DELIVERED', 'SUPPLIED']);

        // Fetch up to 15 matching sales to support quick selection if multiple sales match
        $allMatches = (clone $query)->where(function ($q) use ($cleanTerm) {
            $q->where('id', 'like', "{$cleanTerm}%")
              ->orWhere('customerPhone', 'like', "%{$cleanTerm}%")
              ->orWhere('customerName', 'like', "%{$cleanTerm}%")
              ->orWhere('note', 'like', "%{$cleanTerm}%");
        })->take(15)->get();

        $matchesSummary = $allMatches->map(function ($mSale) {
            $mSupplied = in_array(strtoupper($mSale->deliveryStatus ?? ''), ['DELIVERED', 'SUPPLIED']);
            $mReceiptRef = null;
            if (preg_match('/\[RECEIPT REF:\s*#?([^\]]+)\]/i', $mSale->note ?? '', $mMatches)) {
                $mReceiptRef = trim($mMatches[1]);
            }
            return [
                'id' => $mSale->id,
                'ref' => '#' . substr($mSale->id, 0, 8),
                'paperReceiptRef' => $mReceiptRef,
                'customerName' => $mSale->customerName ?: 'Walk-in Customer',
                'customerPhone' => $mSale->customerPhone,
                'totalAmount' => (float) $mSale->totalAmount,
                'paidAmount' => (float) $mSale->paidAmount,
                'date' => date('d M Y, h:i A', strtotime($mSale->createdAt)),
                'deliveryStatus' => $mSale->deliveryStatus,
                'isSupplied' => $mSupplied,
                'note' => $mSale->note,
            ];
        });

        $paperReceiptRef = null;
        if (preg_match('/\[RECEIPT REF:\s*#?([^\]]+)\]/i', $sale->note ?? '', $pMatches)) {
            $paperReceiptRef = trim($pMatches[1]);
        }

        // Map items with already returned count
        $items = $sale->items->map(function ($item) use ($sale, $isSupplied) {
            $alreadyReturned = (int) \App\Models\SalesReturn::where('saleId', $sale->id)
                ->where('productId', $item->productId)
                ->sum('quantity');
            $eligibleQty = max(0, (int)$item->quantity - $alreadyReturned);

            return [
                'productId' => $item->productId,
                'productName' => $item->product?->name ?? $item->productName,
                'productCode' => $item->product?->code ?? $item->productCode ?? 'SKU',
                'soldQty' => (int) $item->quantity,
                'alreadyReturnedQty' => $alreadyReturned,
                'eligibleQty' => $eligibleQty,
                'unitPrice' => (float) $item->unitPrice,
                'totalPrice' => (float) $item->totalPrice,
                'isSupplied' => $isSupplied,
            ];
        });

        return response()->json([
            'success' => true,
            'sale' => [
                'id' => $sale->id,
                'ref' => '#' . substr($sale->id, 0, 8),
                'paperReceiptRef' => $paperReceiptRef,
                'customerName' => $sale->customerName ?: 'Walk-in Customer',
                'customerPhone' => $sale->customerPhone,
                'totalAmount' => (float) $sale->totalAmount,
                'paidAmount' => (float) $sale->paidAmount,
                'outstandingBalance' => (float) app(\App\Services\Accounting\AccountingReportService::class)->calculateInvoiceBalance($sale),
                'date' => date('d M Y, h:i A', strtotime($sale->createdAt)),
                'deliveryStatus' => $sale->deliveryStatus,
                'isSupplied' => $isSupplied,
                'note' => $sale->note,
                'items' => $items,
            ],
            'matches' => $matchesSummary,
        ]);
    }

    /**
     * Real-time duplicate check for physical receipt slip references.
     * Prevents cashiers or users from reusing an already recorded slip number.
     */
    public function checkReceiptRef(Request $request)
    {
        $rawRef = trim($request->get('ref', $request->get('receipt_ref', '')));
        if (empty($rawRef)) {
            return response()->json(['exists' => false]);
        }

        $user = Auth::user();
        $warehouseId = $user && $user->isBranchScoped() ? $user->warehouse_id : ($request->warehouse_id ?: session('active_warehouse_id'));

        $cleanRef = ltrim($rawRef, '#');
        $query = Sale::query();
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $existing = $query->whereNotIn('status', ['CANCELLED', 'VOIDED'])
            ->where(function ($q) use ($cleanRef, $rawRef) {
                $q->where('receipt_ref', $cleanRef)
                  ->orWhere('receipt_ref', $rawRef)
                  ->orWhere('note', 'like', "%[RECEIPT REF: #{$cleanRef}]%")
                  ->orWhere('note', 'like', "%[RECEIPT REF: {$cleanRef}]%")
                  ->orWhere('note', 'like', "%[RECEIPT REF: #{$rawRef}]%");
            })
            ->first();

        if ($existing) {
            $cashier = $existing->userName ?: 'Cashier';
            $dateStr = date('d M Y, h:i A', strtotime($existing->createdAt));
            $saleRef = substr($existing->id, 0, 8);
            return response()->json([
                'exists' => true,
                'saleId' => $saleRef,
                'fullSaleId' => $existing->id,
                'cashier' => $cashier,
                'date' => $dateStr,
                'customer' => $existing->customerName ?: 'Walk-in Customer',
                'amount' => (float) $existing->totalAmount,
                'message' => "Receipt slip #{$rawRef} has already been issued on Sale #{$saleRef} by {$cashier} on {$dateStr} at this branch. Users cannot enter an already existing receipt number again."
            ]);
        }

        return response()->json(['exists' => false]);
    }

    /**
     * Process POS Checkout (Full or Part-Payment, Supplied vs. Unsupplied).
     * Strictly enforces Zero-Bypass: accepts Customer Phone OR Physical Receipt Ref for Walk-in identification.
     */
    public function checkout(Request $request)
    {
        $tenantId = Auth::user()?->tenant_id ?? session('tenant_id') ?? 'default-tenant';
        $userId = Auth::id() ?? 'POS-USER-1';
        $warehouseId = null;

        try {
            $request->validate([
                'warehouse_id' => 'required',
                'items' => 'required|array|min:1',
                'items.*.productId' => 'required',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unitPrice' => 'nullable|numeric|min:0',
                'cashAmount' => 'nullable|numeric|min:0',
                'posAmount' => 'nullable|numeric|min:0',
                'paidAmount' => 'nullable|numeric|min:0',
                'is_supplied' => 'required', // 'yes' or 'no'
                'receipt_ref' => 'nullable|string|max:100',
            ]);

            $cashAmount = max(0.0, (float) ($request->cashAmount ?? 0));
            $posAmount = max(0.0, (float) ($request->posAmount ?? 0));
            $transferAmount = 0.0; // Strictly retired

            // 🔒 Process & Validate Multi-SKU Exchange Returns (Server-Authoritative Price & Qty Checks)
            $rawExchangeReturns = $request->exchange_returns;
            if (is_string($rawExchangeReturns)) {
                $rawExchangeReturns = json_decode($rawExchangeReturns, true);
            }
            if (!is_array($rawExchangeReturns)) {
                $rawExchangeReturns = [];
            }

            $validatedExchangeReturns = [];
            $totalExchangeCredit = 0.0;
            $exchangeRefList = [];

            foreach ($rawExchangeReturns as $ex) {
                $origSaleId = $ex['saleId'] ?? null;
                $productId = $ex['productId'] ?? null;
                $retQty = (int) ($ex['quantity'] ?? 0);
                if (!$origSaleId || !$productId || $retQty <= 0) continue;

                $origSale = Sale::with('items')->find($origSaleId);
                if (!$origSale) {
                    $errorMsg = "Original sale #{$origSaleId} for exchange could not be found.";
                    if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                    return back()->withErrors(['error' => $errorMsg])->withInput();
                }

                $origItem = $origSale->items->firstWhere('productId', $productId);
                if (!$origItem) {
                    $errorMsg = "Product was not found on original sale #{$origSaleId}.";
                    if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                    return back()->withErrors(['error' => $errorMsg])->withInput();
                }

                $alreadyReturned = (int) \App\Models\SalesReturn::where('saleId', $origSale->id)
                    ->where('productId', $productId)
                    ->sum('quantity');
                $eligibleQty = max(0, (int)$origItem->quantity - $alreadyReturned);

                if ($retQty > $eligibleQty) {
                    $errorMsg = "Cannot return {$retQty} units of '{$origItem->productName}'. Only {$eligibleQty} units eligible for return.";
                    if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                    return back()->withErrors(['error' => $errorMsg])->withInput();
                }

                $unitPrice = (float) $origItem->unitPrice;
                $lineCredit = round($retQty * $unitPrice, 2);
                $totalExchangeCredit += $lineCredit;
                $refStr = '#' . substr($origSale->id, 0, 8);
                if (!in_array($refStr, $exchangeRefList)) {
                    $exchangeRefList[] = $refStr;
                }

                $validatedExchangeReturns[] = [
                    'saleId' => $origSale->id,
                    'origSaleRef' => $refStr,
                    'productId' => $productId,
                    'productCode' => $origItem->productCode ?? $origItem->product?->code ?? 'SKU',
                    'productName' => $origItem->productName ?? $origItem->product?->name ?? 'Returned Item',
                    'quantity' => $retQty,
                    'unitPrice' => $unitPrice,
                    'creditAmount' => $lineCredit,
                ];
            }

            // 🔒 Server-Authoritative Financial Evaluation: Calculate catalog pricing & tender FIRST
            $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
            $calc = $accountingService->calculateCheckout(
                $request->items,
                [
                    'cashAmount' => $cashAmount,
                    'posAmount' => $posAmount,
                    'exchange_credit' => $totalExchangeCredit,
                ],
                'RETAIL'
            );

            $grossTotal = $calc['grossTotal'];
            $paidAmount = $calc['paidAmount'];
            $outstandingDebt = $calc['outstandingDebt'];
            $hasDebt = ($outstandingDebt > 0.01);

            $declaredPaid = (float) ($request->paidAmount ?? 0);
            $netRequiredTender = max(0.0, round($grossTotal - $totalExchangeCredit, 2));
            if ($declaredPaid > 0 && ($cashAmount + $posAmount) < $declaredPaid) {
                $errorMsg = "Payment mismatch: Total tender (Cash ₦{$cashAmount} + POS ₦{$posAmount}) must be equal to or greater than the recorded paid amount (₦{$declaredPaid}).";
                if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                return back()->withErrors(['error' => $errorMsg])->withInput();
            }

            $authUser = Auth::user();
            if ($authUser && !$authUser->isExecutive() && empty($authUser->warehouse_id)) {
                $errorMsg = '🔒 Unauthorized: You are not assigned to any branch location and cannot process sales.';
                if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 403);
                return back()->withErrors(['error' => $errorMsg])->withInput();
            }

            if ($authUser && $authUser->isBranchScoped()) {
                $warehouseId = (int) $authUser->warehouse_id;
            } else {
                $warehouseId = (int) $request->warehouse_id;
                if ($authUser && !$authUser->canAccessWarehouse($warehouseId)) {
                    $errorMsg = '🔒 Unauthorized: You cannot process checkout for an unassigned branch!';
                    if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 403);
                    return back()->withErrors(['error' => $errorMsg])->withInput();
                }
            }
            $isSuppliedNow = in_array(strtolower($request->is_supplied), ['1', 'yes', 'true', 'on']);
            $userId = Auth::id() ?? 'POS-USER-1';
            $userName = Auth::user()->name ?? 'Sales Officer';

            $totalAmount = $grossTotal; // Authoritative catalog pricing replaces any client input
            $isNotSupplied = !$isSuppliedNow;

            $customerId = $request->customerId ? (int) $request->customerId : null;
            $rawReceiptRef = trim($request->receipt_ref ?? $request->receiptRef ?? '');
            $receiptRef = $rawReceiptRef;
            if (empty($receiptRef) && !empty($exchangeRefList)) {
                $receiptRef = implode(', ', $exchangeRefList);
            }

            // 🔒 Strict Uniqueness: Physical receipt slip numbers cannot be duplicated across active sales at this branch
            if (!empty($rawReceiptRef)) {
                $cleanRef = ltrim($rawReceiptRef, '#');
                $existingSaleWithRef = Sale::where('warehouse_id', $warehouseId)
                    ->whereNotIn('status', ['CANCELLED', 'VOIDED'])
                    ->where(function ($q) use ($cleanRef, $rawReceiptRef) {
                        $q->where('receipt_ref', $cleanRef)
                          ->orWhere('receipt_ref', $rawReceiptRef)
                          ->orWhere('note', 'like', "%[RECEIPT REF: #{$cleanRef}]%")
                          ->orWhere('note', 'like', "%[RECEIPT REF: {$cleanRef}]%")
                          ->orWhere('note', 'like', "%[RECEIPT REF: #{$rawReceiptRef}]%");
                    })
                    ->first();

                if ($existingSaleWithRef) {
                    $cName = $existingSaleWithRef->userName ?: 'Cashier';
                    $sDate = date('d M Y, h:i A', strtotime($existingSaleWithRef->createdAt));
                    $sRef = substr($existingSaleWithRef->id, 0, 8);
                    $errorMsg = "⚠️ Duplicate Receipt Number: Receipt slip #{$rawReceiptRef} was already used on Sale #{$sRef} by {$cName} on {$sDate} at this branch. Users cannot enter an already existing receipt number again. Please verify the physical booklet slip.";
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json(['success' => false, 'error' => $errorMsg], 422);
                    }
                    return back()->withErrors(['error' => $errorMsg])->withInput();
                }
            }

            $customerPhone = preg_replace('/[\s\-\(\)\+]/', '', trim($request->customerPhone ?? ''));
            if (str_starts_with($customerPhone, '234') && strlen($customerPhone) === 13) {
                $customerPhone = '0' . substr($customerPhone, 3);
            }
            $request->merge(['customerPhone' => $customerPhone]);

            if (!empty($customerPhone) && !preg_match('/^0\d{10}$/', $customerPhone)) {
                $errorMsg = "Customer phone number must be exactly 11 digits (e.g. 08031234567).";
                if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                return back()->withErrors(['error' => $errorMsg])->withInput();
            }

            $customerName = trim($request->customerName ?? '');

            // 🔒 ZERO BYPASS RULE FOR DEBT & PICKUP ORDERS: Accepts Phone Number OR Physical Receipt Ref
            if ($hasDebt || $isNotSupplied) {
                $reason = $hasDebt ? 'Credit / Part-Payment' : 'Delayed Pickup (Not Supplied)';
                $hasValidIdentifier = (!empty($customerPhone) && preg_match('/^0\d{10}$/', $customerPhone)) || !empty($receiptRef) || !empty($customerId);

                if (!$hasValidIdentifier) {
                    $errorMsg = "🔒 11-digit Phone Number (e.g. 08031234567) OR Physical Receipt Number required for {$reason}! Walk-in Customer cannot take credit or delayed pickup without identification.";
                    if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                    return back()->withErrors(['error' => $errorMsg])->withInput();
                }

                if (empty($customerName) || strtolower($customerName) === 'walk-in customer') {
                    if (!empty($customerPhone)) {
                        $existing = Customer::where('phone', $customerPhone)->first();
                        if ($existing) {
                            $customerName = $existing->name;
                            $customerId = $existing->id;
                        } elseif (!empty($receiptRef)) {
                            $customerName = "Customer (Ref #{$receiptRef})";
                        } else {
                            $errorMsg = "🔒 Customer Name and Phone Number (or Receipt Ref) are required for {$reason}.";
                            if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                            return back()->withErrors(['error' => $errorMsg])->withInput();
                        }
                    } elseif (!empty($receiptRef)) {
                        $customerName = "Customer (Receipt #{$receiptRef})";
                    } else {
                        $errorMsg = "🔒 Customer Name and Phone Number or Receipt Number are required for {$reason}.";
                        if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                        return back()->withErrors(['error' => $errorMsg])->withInput();
                    }
                }
            }

            $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key') ?? $request->input('sale_id');

            if (empty($idempotencyKey)) {
                if ($request->header('X-Require-Idempotency') || $request->header('X-Strict-Idempotency') || $request->is('api/*') || ($request->expectsJson() && !$request->hasSession())) {
                    return response()->json(['success' => false, 'error' => 'Idempotency key is required for POS checkout.'], 422);
                }
                $idempotencyKey = (string) \Illuminate\Support\Str::uuid();
            }

            $idempotencyPayload = [
                'warehouse_id' => $warehouseId,
                'items' => $request->items,
                'declaredPaidAmount' => (float) ($request->paidAmount ?? $paidAmount),
                'paidAmount' => $calc['paidAmount'],
                'cashAmount' => $calc['retainedCash'],
                'posAmount' => $calc['retainedPos'],
                'exchangeCredit' => $calc['retainedExchange'],
                'transferAmount' => 0.0,
                'customerId' => $customerId,
                'customerPhone' => $customerPhone,
                'receiptRef' => $receiptRef,
                'is_supplied' => $isSuppliedNow,
            ];

            $idempotencyService = app(\App\Services\IdempotencyService::class);
            $sale = $idempotencyService->execute(
                'pos_checkout',
                (string) $idempotencyKey,
                (string) $tenantId,
                (string) $userId,
                $idempotencyPayload,
                function () use ($customerId, $customerPhone, $customerName, $grossTotal, $paidAmount, $calc, $request, $warehouseId, $isSuppliedNow, $userId, $userName, $validatedExchangeReturns, $totalExchangeCredit, $receiptRef, $rawReceiptRef, $cashAmount, $posAmount) {
                    // Resolve or create customer record strictly INSIDE transactional idempotency boundary
                    $resolvedCustomerId = $customerId;
                    $resolvedCustomerName = $customerName;
                    $resolvedCustomerPhone = $customerPhone;

                    $customer = null;
                    if ($resolvedCustomerId) {
                        $customer = Customer::find($resolvedCustomerId);
                    }
                    if (!$customer && !empty($resolvedCustomerPhone)) {
                        $customer = Customer::where('phone', $resolvedCustomerPhone)->first();
                    }
                    if (!$customer && !empty($resolvedCustomerName) && strtolower($resolvedCustomerName) !== 'walk-in customer' && !empty($resolvedCustomerPhone)) {
                        $customer = Customer::create([
                            'name' => $resolvedCustomerName,
                            'phone' => $resolvedCustomerPhone,
                            'address' => $request->customerAddress ?? null,
                            'total_debt' => 0,
                        ]);
                    }

                    if ($customer) {
                        $resolvedCustomerId = $customer->id;
                        $resolvedCustomerName = $customer->name;
                        $resolvedCustomerPhone = $customer->phone;
                    }

                    // Build audit note incorporating exchange details and physical receipt reference
                    $finalNote = trim($request->note ?? '');
                    if (!empty($receiptRef)) {
                        $refAudit = "[RECEIPT REF: #{$receiptRef}]";
                        if (!str_contains($finalNote, $refAudit)) {
                            $finalNote = $finalNote ? ($refAudit . " " . $finalNote) : $refAudit;
                        }
                    }
                    if (!empty($validatedExchangeReturns)) {
                        $exNotes = [];
                        foreach ($validatedExchangeReturns as $vEx) {
                            $exNotes[] = "{$vEx['productName']} x{$vEx['quantity']} (@₦" . number_format($vEx['unitPrice'], 0) . " from Ref {$vEx['origSaleRef']})";
                        }
                        $exchangeAudit = "[EXCHANGE RETURN: " . implode('; ', $exNotes) . " | Total Credit: ₦" . number_format($totalExchangeCredit, 2) . "]";
                        $finalNote = $finalNote ? ($finalNote . " " . $exchangeAudit) : $exchangeAudit;
                    }

                    $saleData = [
                        'totalAmount' => $grossTotal,
                        'paidAmount' => $paidAmount,
                        'cashAmount' => $cashAmount,
                        'posAmount' => $posAmount,
                        'tenderedAmount' => ($cashAmount + $posAmount),
                        'transferAmount' => 0.0,
                        'customerName' => $resolvedCustomerName ?: 'Walk-in Customer',
                        'customerPhone' => $resolvedCustomerPhone ?: null,
                        'customerId' => $resolvedCustomerId,
                        'sale_type' => 'RETAIL', // Strictly forced: Client cannot select privileged wholesale mode at retail checkout
                        'receipt_ref' => !empty($rawReceiptRef) ? ltrim($rawReceiptRef, '#') : null,
                        'note' => $finalNote,
                        'exchange_returns' => $validatedExchangeReturns,
                        'exchange_credit' => $totalExchangeCredit,
                    ];

                    return $this->stockService->recordSale($saleData, $request->items, $warehouseId, $isSuppliedNow, $userId, $userName);
                }
            );

            // Security & Operations Audit Log (Fail-safe isolated)
            try {
                $sale->loadMissing('items');
                $lineItems = $sale->items->map(function ($item) {
                    return [
                        'sku' => $item->code ?? $item->productCode ?? 'N/A',
                        'name' => $item->productName ?? 'Unknown Item',
                        'quantity' => (int) $item->quantity,
                        'unit_price' => (float) $item->unitPrice,
                        'subtotal' => (float) (($item->quantity ?? 1) * ($item->unitPrice ?? 0)),
                    ];
                })->values()->all();

                $exchanges = [];
                if (!empty($validatedExchangeReturns)) {
                    foreach ($validatedExchangeReturns as $vEx) {
                        $exchanges[] = [
                            'sku' => $vEx['productCode'] ?? $vEx['code'] ?? 'N/A',
                            'name' => $vEx['productName'] ?? 'Item',
                            'quantity' => (int) ($vEx['quantity'] ?? 1),
                            'credit_value' => (float) ($vEx['creditAmount'] ?? (($vEx['quantity'] ?? 1) * ($vEx['unitPrice'] ?? 0))),
                            'orig_sale_ref' => $vEx['origSaleRef'] ?? null,
                        ];
                    }
                }

                $grossVal = (float) ($sale->totalAmount ?? $grossTotal);
                $paidVal = (float) ($sale->paidAmount ?? $paidAmount);
                $debtIncurred = max(0.0, round($grossVal - $paidVal, 2));

                \App\Models\Activity::recordSecurityEvent('POS_SALE_COMPLETED', "POS Retail Sale #{$sale->id} completed for ₦" . number_format($grossVal, 2) . " by {$userName}." . ($debtIncurred > 0 ? " (₦" . number_format($debtIncurred, 2) . " Debt Incurred)" : ""), [
                    'sale_id' => $sale->id,
                    'total_amount' => $grossVal,
                    'paid_amount' => $paidVal,
                    'debt_incurred' => $debtIncurred,
                    'cash_amount' => (float) ($sale->cashAmount ?? $cashAmount),
                    'pos_amount' => (float) ($sale->posAmount ?? $posAmount),
                    'exchange_credit' => (float) $totalExchangeCredit,
                    'customer_id' => $sale->customerId ?? $customerId,
                    'customer_name' => $sale->customerName ?: ($customerName ?: 'Walk-in Customer'),
                    'customer_phone' => $sale->customerPhone ?: ($customerPhone ?: null),
                    'is_supplied' => (bool) $isSuppliedNow,
                    'delivery_status' => $sale->deliveryStatus ?? ($isSuppliedNow ? 'DELIVERED' : 'UNSUPPLIED'),
                    'sale_type' => $sale->sale_type ?? 'RETAIL',
                    'items_count' => count($lineItems),
                    'items' => $lineItems,
                    'returned_exchange_items' => $exchanges,
                    'warehouse_id' => $warehouseId,
                ]);
            } catch (\Throwable $auditEx) {
                \Illuminate\Support\Facades\Log::warning("POS_SALE_COMPLETED audit log isolated failure: {$auditEx->getMessage()}", [
                    'sale_id' => $sale->id ?? null,
                ]);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sale completed successfully!',
                    'saleId' => $sale->id,
                    'receiptUrl' => route('pos.receipt', $sale->id),
                ]);
            }

            return redirect()->route('pos.receipt', $sale->id)->with('success', 'Sale recorded successfully!');
        } catch (\App\Exceptions\InsufficientStockException | \InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("POS Checkout failed: " . $e->getMessage(), [
                'exception' => $e,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'warehouse_id' => $warehouseId,
            ]);
            $msg = $e->getMessage() ?: 'Unable to complete sale transaction. Please check product stock or contact support.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $msg], 422);
            }
            return back()->withErrors(['error' => $msg])->withInput();
        }
    }

    /**
     * Printable Visual Receipt / Invoice.
     */
    public function receipt($id)
    {
        $sale = Sale::with(['items', 'returns', 'payments', 'warehouse'])->findOrFail($id);

        // Branch Isolation: Branch-scoped users are strictly restricted to receipts within their own assigned branch
        $authUser = Auth::user();
        if ($authUser && $authUser->isBranchScoped()) {
            if (!empty($sale->warehouse_id) && (int) $sale->warehouse_id !== (int) $authUser->warehouse_id) {
                abort(403, '🔒 Access Denied: You are strictly restricted to viewing receipts from your assigned branch.');
            }
        }

        $warehouse = $sale->warehouse ?? Warehouse::find($sale->warehouse_id) ?? Warehouse::first();

        return view('pos.receipt', compact('sale', 'warehouse'));
    }

    /**
     * Display Sales Returns & Customer Refunds screen with full filters.
     */
    public function returns(Request $request)
    {
        $datePreset = $request->get('date_preset', 'ALL');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $reason = $request->get('return_reason');
        $search = trim($request->get('search', ''));

        $authUser = Auth::user();
        $query = \App\Models\SalesReturn::query();

        if ($authUser && $authUser->isBranchScoped()) {
            $query->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $authUser->warehouse_id));
        }

        $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
        $accountingService->applyDateFilterToQuery($query, 'createdAt', [
            'date_preset' => $datePreset,
            'from_date'   => $fromDate,
            'to_date'     => $toDate,
        ]);

        if ($reason) {
            $query->where('reason', 'like', "%{$reason}%");
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('saleId', 'like', "%{$search}%")
                  ->orWhere('customerName', 'like', "%{$search}%")
                  ->orWhere('productName', 'like', "%{$search}%")
                  ->orWhere('userName', 'like', "%{$search}%");
            });
        }

        $recentReturns = $query->orderBy('createdAt', 'desc')->paginate(25)->withQueryString();
        $totalReturnsCount = (clone $query)->count();
        $totalUnitsRestocked = (clone $query)->sum('quantity');
        $totalRefundValue = (clone $query)->sum('refundAmount');

        $salesQuery = Sale::with('items')->orderBy('createdAt', 'desc');
        if ($authUser && $authUser->isBranchScoped()) {
            $salesQuery->where('warehouse_id', $authUser->warehouse_id);
        }
        $sales = $salesQuery->take(30)->get();

        if ($authUser && $authUser->isBranchScoped()) {
            $warehouses = Warehouse::where('id', $authUser->warehouse_id)->get();
        } else {
            $warehouses = Warehouse::where('is_active', true)->get();
        }

        return view('pos.returns', compact(
            'sales',
            'recentReturns',
            'warehouses',
            'totalReturnsCount',
            'totalUnitsRestocked',
            'totalRefundValue',
            'datePreset',
            'fromDate',
            'toDate',
            'reason',
            'search'
        ));
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
            'refund_method' => 'required|string|in:REFUND,CASH_REFUND,POS_TRANSFER_REFUND,DEBT_REDUCTION,STORE_CREDIT',
            'reason' => 'required|string|min:3|max:255',
        ]);

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Sales Officer';

        $authUser = Auth::user();
        if ($authUser && !$authUser->isExecutive() && empty($authUser->warehouse_id)) {
            $errorMsg = '🔒 Unauthorized: You are not assigned to any branch location!';
            if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 403);
            return back()->withErrors(['error' => $errorMsg]);
        }

        if ($authUser && $authUser->isBranchScoped()) {
            $warehouseId = (int) $authUser->warehouse_id;
        } else {
            $warehouseId = (int) $request->warehouse_id;
            if ($authUser && !$authUser->canAccessWarehouse($warehouseId)) {
                $errorMsg = '🔒 Unauthorized: You cannot process returns for an unassigned branch!';
                if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 403);
                return back()->withErrors(['error' => $errorMsg]);
            }
        }

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        $tenantId = Auth::user()?->tenant_id ?? session('tenant_id') ?? 'default-tenant';

        // 🔒 Invariant VM-032: Enforce Mandatory Idempotency Closure
        if (empty($idempotencyKey)) {
            if ($request->header('X-Strict-Idempotency') || $request->header('X-Require-Idempotency') || $request->is('api/*') || ($request->expectsJson() && !$request->hasSession())) {
                $errorMsg = 'Idempotency key is required for processing returns.';
                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json(['success' => false, 'error' => $errorMsg], 422);
                }
                return back()->withErrors(['error' => $errorMsg]);
            }
            $idempotencyKey = (string) \Illuminate\Support\Str::uuid();
        }

        $idempotencyPayload = [
            'sale_id' => $request->sale_id,
            'warehouse_id' => $warehouseId,
            'items' => $request->items,
            'refund_method' => $request->refund_method,
        ];

        try {
            $idempotencyService = app(\App\Services\IdempotencyService::class);
            $salesReturn = $idempotencyService->execute(
                'pos_return',
                (string) $idempotencyKey,
                (string) $tenantId,
                (string) $userId,
                $idempotencyPayload,
                function () use ($request, $warehouseId, $userId, $userName) {
                    return $this->stockService->recordSaleReturn(
                        $request->sale_id,
                        $request->items,
                        $warehouseId,
                        $request->refund_method,
                        $request->reason,
                        $userId,
                        $userName
                    );
                }
            );

            // Security & Operations Audit Log (Fail-safe isolated)
            try {
                $actualRefund = (float) ($salesReturn->refundAmount ?? ($salesReturn->refund_amount ?? 0));
                $origSale = \App\Models\Sale::find($request->sale_id);

                $resolvedReturnItems = [];
                if (is_array($request->items)) {
                    $pIds = collect($request->items)->pluck('productId')->filter()->all();
                    $products = \App\Models\Product::whereIn('id', $pIds)->get()->keyBy('id');
                    foreach ($request->items as $rItem) {
                        $p = $products->get($rItem['productId'] ?? null);
                        $qty = (int) ($rItem['quantity'] ?? 1);
                        $lineRef = (float) ($rItem['refundAmount'] ?? ($rItem['unit_price'] ?? 0) * $qty);
                        $resolvedReturnItems[] = [
                            'sku' => $p ? $p->code : ($rItem['code'] ?? $rItem['productCode'] ?? 'N/A'),
                            'name' => $p ? $p->name : ($rItem['productName'] ?? 'Returned Product'),
                            'quantity' => $qty,
                            'refund_amount' => $lineRef,
                            'unit_price' => $qty > 0 ? round($lineRef / $qty, 2) : $lineRef,
                        ];
                    }
                }

                \App\Models\Activity::recordSecurityEvent('SALES_RETURN_REFUNDED', "Sales Return #{$salesReturn->code} processed for Sale #{$request->sale_id} (Refund: ₦" . number_format($actualRefund, 2) . " via {$request->refund_method}) by {$userName}.", [
                    'return_id' => $salesReturn->id,
                    'return_code' => $salesReturn->code,
                    'sale_id' => $request->sale_id,
                    'customer_name' => $origSale?->customerName ?? 'Walk-in Customer',
                    'customer_phone' => $origSale?->customerPhone ?? null,
                    'refund_amount' => $actualRefund,
                    'refund_method' => $request->refund_method,
                    'reason' => $request->reason,
                    'warehouse_id' => $warehouseId,
                    'items_count' => count($resolvedReturnItems),
                    'items' => $resolvedReturnItems,
                ]);
            } catch (\Throwable $auditEx) {
                \Illuminate\Support\Facades\Log::warning("SALES_RETURN_REFUNDED audit log isolated failure: {$auditEx->getMessage()}", [
                    'return_id' => $salesReturn->id ?? null,
                ]);
            }

            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Return #{$salesReturn->code} processed successfully!",
                    'returnId' => $salesReturn->id,
                    'code' => $salesReturn->code,
                    'return' => $salesReturn,
                ]);
            }

            return redirect()->route('pos.returns')->with('success', "✓ Return #{$salesReturn->code} processed! Items restored to physical closing stock.");
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("POS Return failed for sale {$request->sale_id}: " . $e->getMessage(), [
                'exception' => $e,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'warehouse_id' => $warehouseId,
            ]);
            $msg = 'Unable to process sales return. Please verify item quantities or contact support.';
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['success' => false, 'error' => $msg], 422);
            }
            return back()->withErrors(['error' => $msg]);
        }
    }
}

