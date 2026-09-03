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
        if ($user && $user->role !== 'admin' && $user->role !== 'viewer' && !empty($user->warehouse_id)) {
            $activeWarehouseId = $user->warehouse_id;
            $warehouses = Warehouse::where('id', $user->warehouse_id)->get();
        } else {
            $activeWarehouseId = $request->get('warehouse_id', session('active_warehouse_id', $warehouses->first()->id));
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
            $product->available_stock = $product->physical_stock; // All physical items on ground are sellable
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
        $request->validate([
            'warehouse_id' => 'required',
            'items' => 'required|array|min:1',
            'items.*.productId' => 'required',
            'items.*.quantity' => 'required|integer|min:1',
            'paidAmount' => 'required|numeric|min:0',
            'is_supplied' => 'required', // 'yes' or 'no'
        ]);

        $cashAmount = (float) ($request->cashAmount ?? 0);
        $posAmount = (float) ($request->posAmount ?? 0);
        $transferAmount = (float) ($request->transferAmount ?? 0);
        $paidAmount = (float) $request->paidAmount;

        if ($paidAmount > 0 && ($cashAmount + $posAmount + $transferAmount) < $paidAmount) {
            $errorMsg = "Payment mismatch: Total tender (Cash ₦{$cashAmount} + POS ₦{$posAmount} + Transfer ₦{$transferAmount}) must be equal to or greater than the recorded paid amount (₦{$paidAmount}).";
            if ($request->wantsJson()) return response()->json(['success' => false, 'error' => $errorMsg], 422);
            return back()->withErrors(['error' => $errorMsg])->withInput();
        }

        $authUser = Auth::user();
        if ($authUser && $authUser->role !== 'admin' && $authUser->role !== 'viewer' && !empty($authUser->warehouse_id)) {
            $warehouseId = (int) $authUser->warehouse_id;
        } else {
            $warehouseId = (int) $request->warehouse_id;
        }
        $isSuppliedNow = in_array(strtolower($request->is_supplied), ['1', 'yes', 'true', 'on']);
        $userId = Auth::id() ?? 'POS-USER-1';
        $userName = Auth::user()->name ?? 'Sales Officer';

        $totalAmount = (float) ($request->totalAmount ?? 0);
        $hasDebt = ($paidAmount < $totalAmount);
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

        // 🔒 ZERO BYPASS RULE FOR DEBT & PICKUP ORDERS
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

        // Resolve or create customer record
        $customer = null;
        if ($customerId) {
            $customer = Customer::find($customerId);
        }
        if (!$customer && !empty($customerPhone)) {
            $customer = Customer::where('phone', $customerPhone)->first();
        }
        if (!$customer && !empty($customerName) && strtolower($customerName) !== 'walk-in customer' && !empty($customerPhone)) {
            $customer = Customer::create([
                'name' => $customerName,
                'phone' => $customerPhone,
                'address' => $request->customerAddress ?? null,
                'total_debt' => 0,
            ]);
        }

        if ($customer) {
            $customerId = $customer->id;
            $customerName = $customer->name;
            $customerPhone = $customer->phone;
        }

        $saleData = [
            'totalAmount' => $totalAmount,
            'paidAmount' => $paidAmount,
            'cashAmount' => (float) ($request->cashAmount ?? 0),
            'posAmount' => (float) ($request->posAmount ?? 0),
            'transferAmount' => (float) ($request->transferAmount ?? 0),
            'customerName' => $customerName ?: 'Walk-in Customer',
            'customerPhone' => $customerPhone ?: null,
            'customerId' => $customerId,
            'sale_type' => $request->get('sale_type', 'RETAIL'),
            'note' => $request->note,
        ];

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key') ?? $request->input('sale_id');
        $tenantId = session('tenant_id') ?? Auth::user()->tenant_id ?? 'default-tenant';

        try {
            if ($idempotencyKey) {
                $idempotencyService = app(\App\Services\IdempotencyService::class);
                $sale = $idempotencyService->execute(
                    'pos_checkout',
                    (string) $idempotencyKey,
                    (string) $tenantId,
                    (string) $userId,
                    [
                        'warehouse_id' => $warehouseId,
                        'items' => $request->items,
                        'paidAmount' => $paidAmount,
                        'cashAmount' => (float) ($request->cashAmount ?? 0),
                        'posAmount' => (float) ($request->posAmount ?? 0),
                        'transferAmount' => (float) ($request->transferAmount ?? 0),
                        'customerId' => $customerId,
                        'is_supplied' => $isSuppliedNow,
                    ],
                    function () use ($saleData, $request, $warehouseId, $isSuppliedNow, $userId, $userName) {
                        return $this->stockService->recordSale($saleData, $request->items, $warehouseId, $isSuppliedNow, $userId, $userName);
                    }
                );
            } else {
                $sale = $this->stockService->recordSale($saleData, $request->items, $warehouseId, $isSuppliedNow, $userId, $userName);
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
        $saleUser = \App\Models\User::find($sale->userId);
        $saleWarehouseId = ($saleUser && !empty($saleUser->warehouse_id)) ? $saleUser->warehouse_id : session('active_warehouse_id');
        $warehouse = Warehouse::find($saleWarehouseId) ?? Warehouse::first();

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

        $query = \App\Models\SalesReturn::query();

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

        $sales = Sale::with('items')->orderBy('createdAt', 'desc')->take(30)->get();
        $warehouses = Warehouse::where('is_active', true)->get();

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
            'refund_method' => 'required|string', // CASH_REFUND, DEBT_REDUCTION
            'reason' => 'required|string',
        ]);

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Sales Officer';

        $authUser = Auth::user();
        if ($authUser && $authUser->isBranchScoped()) {
            $warehouseId = (int) $authUser->warehouse_id;
        } else {
            $warehouseId = (int) $request->warehouse_id;
            if ($authUser && !$authUser->canAccessWarehouse($warehouseId)) {
                return back()->withErrors(['error' => '🔒 Unauthorized: You cannot process returns for an unassigned branch!']);
            }
        }

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        $tenantId = session('tenant_id') ?? Auth::user()->tenant_id ?? 'default-tenant';

        try {
            if ($idempotencyKey) {
                $idempotencyService = app(\App\Services\IdempotencyService::class);
                $salesReturn = $idempotencyService->execute(
                    'pos_return',
                    (string) $idempotencyKey,
                    (string) $tenantId,
                    (string) $userId,
                    [
                        'sale_id' => $request->sale_id,
                        'warehouse_id' => $warehouseId,
                        'items' => $request->items,
                        'refund_method' => $request->refund_method,
                    ],
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
            } else {
                $salesReturn = $this->stockService->recordSaleReturn(
                    $request->sale_id,
                    $request->items,
                    $warehouseId,
                    $request->refund_method,
                    $request->reason,
                    $userId,
                    $userName
                );
            }

            return redirect()->route('pos.returns')->with('success', "✓ Return #{$salesReturn->code} processed! Items restored to physical closing stock.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

