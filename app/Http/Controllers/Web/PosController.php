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
            $activeWarehouseId = $request->get('warehouse_id', session('active_warehouse_id', $warehouses->first()->id));
            if ($user && !$user->canAccessWarehouse($activeWarehouseId)) {
                abort(403, '🔒 Access Restricted: You do not have permission to access this branch.');
            }
        }
        session(['active_warehouse_id' => $activeWarehouseId]);

        $activeWarehouse = Warehouse::find($activeWarehouseId) ?? $warehouses->first();

        // Get products with their stock levels at this warehouse
        $products = Product::where('archived', false)->get()->map(function ($product) use ($activeWarehouse) {
            $stock = StockLevel::where('product_id', $product->id)
                ->where('warehouse_id', $activeWarehouse->id)
                ->first();

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
     * Process POS Checkout (Full or Part-Payment, Supplied vs. Unsupplied).
     * Strictly enforces Zero-Bypass of Customer Phone & Name for Credit / Part-Payment and Delayed Pickup.
     */
    public function checkout(Request $request)
    {
        $tenantId = session('tenant_id') ?? Auth::user()->tenant_id ?? 'default-tenant';
        $userId = Auth::id() ?? 'POS-USER-1';
        $warehouseId = null;

        try {
            $request->validate([
                'warehouse_id' => 'required',
                'items' => 'required|array|min:1',
                'items.*.productId' => 'required',
                'items.*.quantity' => 'required|integer|min:1',
                'cashAmount' => 'nullable|numeric|min:0',
                'posAmount' => 'nullable|numeric|min:0',
                'paidAmount' => 'nullable|numeric|min:0',
                'is_supplied' => 'required', // 'yes' or 'no'
            ]);

            $cashAmount = max(0.0, (float) ($request->cashAmount ?? 0));
            $posAmount = max(0.0, (float) ($request->posAmount ?? 0));
            $transferAmount = 0.0; // Strictly retired

            $declaredPaid = (float) ($request->paidAmount ?? 0);
            if ($declaredPaid > 0 && ($cashAmount + $posAmount) < $declaredPaid) {
                $errorMsg = "Payment mismatch: Total tender (Cash ₦{$cashAmount} + POS ₦{$posAmount}) must be equal to or greater than the recorded paid amount (₦{$declaredPaid}).";
                if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                return back()->withErrors(['error' => $errorMsg])->withInput();
            }

            // 🔒 Server-Authoritative Financial Evaluation: Calculate catalog pricing & tender FIRST
            $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
            $calc = $accountingService->calculateCheckout(
                $request->items,
                [
                    'cashAmount' => $cashAmount,
                    'posAmount' => $posAmount,
                ],
                'RETAIL'
            );

            $grossTotal = $calc['grossTotal'];
            $paidAmount = $calc['paidAmount'];
            $outstandingDebt = $calc['outstandingDebt'];
            $hasDebt = ($outstandingDebt > 0.01);

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

            // 🔒 ZERO BYPASS RULE FOR DEBT & PICKUP ORDERS (Evaluated using authoritative server debt)
            if ($hasDebt || $isNotSupplied) {
                $reason = $hasDebt ? 'Credit / Part-Payment' : 'Delayed Pickup (Not Supplied)';

                if ((empty($customerPhone) || !preg_match('/^0\d{10}$/', $customerPhone)) && empty($customerId)) {
                    $errorMsg = "🔒 Exactly 11-digit Phone Number (e.g. 08031234567) & Registered Customer required for {$reason}! Walk-in Customer cannot take credit or delayed pickup.";
                    if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                    return back()->withErrors(['error' => $errorMsg])->withInput();
                }

                if (empty($customerName) || strtolower($customerName) === 'walk-in customer') {
                    if (!empty($customerPhone)) {
                        $existing = Customer::where('phone', $customerPhone)->first();
                        if ($existing) {
                            $customerName = $existing->name;
                            $customerId = $existing->id;
                        } else {
                            $errorMsg = "🔒 Customer Name and Phone Number are required for {$reason}.";
                            if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
                            return back()->withErrors(['error' => $errorMsg])->withInput();
                        }
                    } else {
                        $errorMsg = "🔒 Customer Name and Phone Number are required for {$reason}.";
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
                'paidAmount' => (float) ($request->paidAmount ?? $paidAmount),
                'cashAmount' => (float) ($request->cashAmount ?? 0),
                'posAmount' => (float) ($request->posAmount ?? 0),
                'transferAmount' => (float) ($request->transferAmount ?? 0),
                'customerId' => $customerId,
                'customerPhone' => $customerPhone,
                'is_supplied' => $isSuppliedNow,
            ];

            $idempotencyService = app(\App\Services\IdempotencyService::class);
            $sale = $idempotencyService->execute(
                'pos_checkout',
                (string) $idempotencyKey,
                (string) $tenantId,
                (string) $userId,
                $idempotencyPayload,
                function () use ($customerId, $customerPhone, $customerName, $grossTotal, $paidAmount, $cashAmount, $posAmount, $request, $warehouseId, $isSuppliedNow, $userId, $userName) {
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

                    $saleData = [
                        'totalAmount' => $grossTotal,
                        'paidAmount' => $paidAmount,
                        'cashAmount' => $cashAmount,
                        'posAmount' => $posAmount,
                        'transferAmount' => 0.0,
                        'customerName' => $resolvedCustomerName ?: 'Walk-in Customer',
                        'customerPhone' => $resolvedCustomerPhone ?: null,
                        'customerId' => $resolvedCustomerId,
                        'sale_type' => 'RETAIL', // Strictly forced: Client cannot select privileged wholesale mode at retail checkout
                        'note' => $request->note,
                    ];

                    return $this->stockService->recordSale($saleData, $request->items, $warehouseId, $isSuppliedNow, $userId, $userName);
                }
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sale completed successfully!',
                    'saleId' => $sale->id,
                    'receiptUrl' => route('pos.receipt', $sale->id),
                ]);
            }

            return redirect()->route('pos.receipt', $sale->id)->with('success', 'Sale recorded successfully!');
        } catch (\InvalidArgumentException $e) {
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
        $sale = Sale::with(['items', 'warehouse'])->findOrFail($id);

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

        if ($fromDate && $toDate) {
            $query->whereBetween('createdAt', [
                \Carbon\Carbon::parse($fromDate)->startOfDay()->toIso8601String(),
                \Carbon\Carbon::parse($toDate)->endOfDay()->toIso8601String()
            ]);
        } elseif ($datePreset === 'TODAY') {
            $query->whereDate('createdAt', \Carbon\Carbon::today());
        } elseif ($datePreset === 'YESTERDAY') {
            $query->whereDate('createdAt', \Carbon\Carbon::yesterday());
        } elseif ($datePreset === 'THIS_WEEK') {
            $query->whereBetween('createdAt', [
                \Carbon\Carbon::now()->startOfWeek()->toIso8601String(),
                \Carbon\Carbon::now()->endOfWeek()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_MONTH') {
            $query->whereBetween('createdAt', [
                \Carbon\Carbon::now()->startOfMonth()->toIso8601String(),
                \Carbon\Carbon::now()->endOfMonth()->toIso8601String()
            ]);
        }

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
            'refund_method' => 'required|string|in:CASH_REFUND,DEBT_REDUCTION',
            'reason' => 'required|string',
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
        $tenantId = session('tenant_id') ?? Auth::user()->tenant_id ?? 'default-tenant';

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

