<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\InventoryLog;
use App\Models\Activity;
use App\Models\Warehouse;
use App\Models\User;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class StockService
{
    /**
     * Authoritatively assert that a warehouse exists and belongs to the active tenant.
     *
     * @throws \InvalidArgumentException
     */
    public function assertTenantWarehouse(int $warehouseId): Warehouse
    {
        $wh = Warehouse::withoutGlobalScopes()->find($warehouseId);
        if (!$wh) {
            throw new \InvalidArgumentException("Warehouse #{$warehouseId} does not exist.");
        }

        if (config('saas.enabled')) {
            $currentTenantId = session('tenant_id') ?? (Auth::check() ? Auth::user()->tenant_id : null);
            if (empty($currentTenantId)) {
                throw new \InvalidArgumentException("Security Violation: No authoritative tenant context established. Operation rejected.");
            }
            if ($wh->tenant_id !== $currentTenantId) {
                throw new \InvalidArgumentException("Security Violation: Warehouse #{$warehouseId} does not belong to active tenant '{$currentTenantId}'. Cross-tenant stock transfers are strictly forbidden.");
            }
        }

        return $wh;
    }

    /**
     * Authoritatively assert that the acting user possesses authority to operate on the specified warehouse.
     * Enforces the complete WHAT + WHERE security contract at the service layer:
     * - Tenant Admin: Authorized on any warehouse belonging to their active tenant.
     * - Branch-Scoped Employee: Strictly restricted to their assigned warehouse_id.
     * - Platform User: Strictly forbidden from performing tenant business warehouse operations.
     *
     * @throws AuthorizationException
     */
    public function assertUserWarehouseAuthority(int $warehouseId, ?User $actor = null): Warehouse
    {
        $wh = $this->assertTenantWarehouse($warehouseId);

        // Authoritative actor resolution:
        // In HTTP route contexts, authorization derives strictly from the authenticated session (Auth::user()).
        // Caller-supplied $actor is ignored during HTTP requests to prevent privilege spoofing.
        $isHttp = (request()->route() !== null || (!app()->runningInConsole() && !app()->runningUnitTests()));
        $user = $isHttp ? Auth::user() : (Auth::user() ?? $actor);

        if ($user) {
            if ($user->isPlatformUser()) {
                throw new AuthorizationException(
                    "Security Violation: Platform user '{$user->name}' cannot perform tenant business operations on Branch #{$warehouseId}."
                );
            }

            if ($user->isBranchScoped()) {
                if ((int) $user->warehouse_id !== (int) $warehouseId) {
                    throw new AuthorizationException(
                        "Security Violation: User '{$user->name}' assigned to Branch #{$user->warehouse_id} cannot operate on Branch #{$warehouseId}."
                    );
                }
            }

            if ($user->isTenantAdmin()) {
                if (config('saas.enabled') && $wh->tenant_id !== $user->tenant_id) {
                    throw new AuthorizationException(
                        "Security Violation: Tenant admin cannot operate on foreign tenant warehouse #{$warehouseId}."
                    );
                }
            }

            return $wh;
        }

        // Fail-closed authorization: Reject execution when no authenticated actor exists in an HTTP context
        if ($isHttp) {
            throw new AuthorizationException(
                "Unauthorized: No authenticated actor provided for warehouse #{$warehouseId} operation."
            );
        }

        return $wh;
    }

    /**
     * Authoritatively assert that the acting user holds the required capability.
     * Fails closed: rejects execution if no authenticated actor or resolved user exists.
     * Derives actor strictly from authenticated session in HTTP route contexts.
     *
     * @throws AuthorizationException
     */
    public function assertUserCapability(string $capability, ?string $userId = null): void
    {
        // Authoritative actor separation: Derive strictly from authenticated session
        $user = Auth::user();

        if ($user) {
            if (!$user->hasCapability($capability)) {
                throw new AuthorizationException(
                    "Unauthorized: User {$user->name} lacks the required '{$capability}' capability."
                );
            }
            return;
        }

        // Fail-closed authorization: Reject execution when no authenticated actor exists in an HTTP route context
        if (request()->route() !== null || (!app()->runningInConsole() && !app()->runningUnitTests())) {
            throw new AuthorizationException(
                "Unauthorized: No authenticated actor provided for '{$capability}' operation."
            );
        }
    }

    /**
     * Read-only stock level query for a product at a warehouse.
     * Guaranteed non-mutating: does NOT create database records if stock level is missing.
     */
    public function getStockLevel(string $productId, int $warehouseId, bool $lockForUpdate = false): ?StockLevel
    {
        $this->assertTenantWarehouse($warehouseId);

        $query = StockLevel::where('product_id', $productId)->where('warehouse_id', $warehouseId);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        return $query->first();
    }

    /**
     * Authoritatively ensure a stock level record exists with row-level locking for authorized mutations.
     * Strictly enforces WHAT + WHERE warehouse authorization before creating or locking stock records.
     *
     * @throws AuthorizationException
     */
    public function ensureStockLevelForAuthorizedMutation(string $productId, int $warehouseId, bool $lockForUpdate = true): StockLevel
    {
        $this->assertUserWarehouseAuthority($warehouseId);

        $query = StockLevel::where('product_id', $productId)->where('warehouse_id', $warehouseId);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        $stock = $query->first();

        if (!$stock) {
            try {
                $stock = StockLevel::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'physical_stock' => 0,
                    'allocated_stock' => 0,
                    'min_stock_alert' => 5,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Gracefully resolve race conditions on concurrent first-creation
                $query = StockLevel::where('product_id', $productId)->where('warehouse_id', $warehouseId);
                if ($lockForUpdate) {
                    $query->lockForUpdate();
                }
                $stock = $query->firstOrFail();
            }

            if ($lockForUpdate && $stock) {
                $stock = StockLevel::where('id', $stock->id)->lockForUpdate()->first();
            }
        }

        return $stock;
    }

    /**
     * Record Stock In (e.g. from Supplier or Purchase).
     */
    public function recordStockIn(string $productId, int $warehouseId, int $quantity, ?string $supplierName, string $userId, string $userName, ?string $notes = null): StockLevel
    {
        $this->assertUserCapability('stock.in', $userId);
        $this->assertUserWarehouseAuthority($warehouseId);

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $supplierName, $userId, $userName, $notes) {
            if ($quantity <= 0) {
                throw new \InvalidArgumentException("Stock in quantity must be at least 1 unit.");
            }

            $product = Product::findOrFail($productId);
            $stock = $this->ensureStockLevelForAuthorizedMutation($productId, $warehouseId, true);
            $stock->physical_stock += $quantity;
            $stock->save();

            // Also update total currentStock on product
            $product->currentStock = StockLevel::where('product_id', $productId)->sum('physical_stock');
            $product->save();

            InventoryLog::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => session('tenant_id') ?? $product->tenant_id ?? null,
                'productId' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => 'STOCK_IN',
                'quantity' => $quantity,
                'userId' => $userId,
                'userName' => $userName,
                'productCode' => $product->code,
                'productName' => $product->name,
                'description' => "Stock In from {$supplierName}. " . ($notes ?? ''),
                'timestamp' => now()->toIso8601String(),
            ]);

            Activity::create([
                'id' => (string) Str::uuid(),
                'type' => 'STOCK_IN',
                'description' => "{$userName} added {$quantity} units of {$product->name} at warehouse #{$warehouseId}",
                'userId' => $userId,
                'userName' => $userName,
                'timestamp' => now()->toIso8601String(),
                'metadata' => json_encode([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'supplier' => $supplierName,
                ]),
            ]);

            return $stock;
        });
    }

    /**
     * Record a Sale (with Part-Payment and Supplied vs. Unsupplied physical closing stock rule).
     */
    public function recordSale(array $saleData, array $items, int $warehouseId, bool $isSuppliedNow, string $userId, string $userName): Sale
    {
        $this->assertUserCapability('pos.checkout', $userId);
        $this->assertUserWarehouseAuthority($warehouseId);

        return DB::transaction(function () use ($saleData, $items, $warehouseId, $isSuppliedNow, $userId, $userName) {
            $saleId = $saleData['id'] ?? (string) Str::uuid();

            // Safe Idempotency Check: if a sale with this ID already exists, return it cleanly
            if (isset($saleData['id'])) {
                $existingSale = Sale::with('items')->find($saleId);
                if ($existingSale) {
                    return $existingSale;
                }
            }

            if (empty($items)) {
                throw new \InvalidArgumentException("Checkout requires at least one sale line item.");
            }

            // Server-authoritative checkout calculation via AccountingReportService
            $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
            $tenderInput = (isset($saleData['tender']) && is_array($saleData['tender']))
                ? array_merge($saleData, $saleData['tender'])
                : $saleData;
            $calc = $accountingService->calculateCheckout($items, $tenderInput, $saleData['sale_type'] ?? 'RETAIL');

            $validatedItems = $calc['validatedItems'];
            $totalAmount    = $calc['totalAmount'];
            $netCashKept    = $calc['retainedCash'];
            $posAmount      = $calc['retainedPos'];
            $transferAmount = 0.0; // Strictly retired across the system
            $tenderedTotal  = $calc['totalTendered'];
            $changeAmount   = $calc['changeAmount'];
            $paidAmount     = $calc['paidAmount'];
            $saleStatus     = $calc['status'];

            $customerId = $saleData['customerId'] ?? null;
            $customerName = $saleData['customerName'] ?? 'Walk-in Customer';
            $remainingDebt = max(0.0, round($totalAmount - $paidAmount, 2));

            // Level 2 in Global Lock-Order Hierarchy: Customer Row Lock
            // When a sale creates or alters customer debt or associates an existing customer account,
            // acquire Customer with lockForUpdate() BEFORE Sale (Level 3) or StockLevel (Level 4).
            // Anonymous walk-in transactions with zero debt do NOT acquire customer locks.
            $customer = null;
            if (!empty($customerId)) {
                $activeTenantId = session('tenant_id') ?? (Auth::check() ? Auth::user()->tenant_id : null);
                $customer = Customer::withoutGlobalScopes()->where('id', $customerId)->lockForUpdate()->first();
                if (!$customer) {
                    throw new \InvalidArgumentException("Customer #{$customerId} does not exist.");
                }
                $activeTenantClean = !empty($activeTenantId) ? (string) $activeTenantId : null;
                $custTenantClean   = !empty($customer->tenant_id) ? (string) $customer->tenant_id : null;

                if ($custTenantClean !== $activeTenantClean) {
                    throw new \InvalidArgumentException("Security Violation: Customer #{$customerId} belongs to tenant '{$customer->tenant_id}', not active tenant '{$activeTenantId}'.");
                }
                $customerName = $customer->name;
            } elseif ($remainingDebt > 0 && !empty($customerName) && $customerName !== 'Walk-in Customer') {
                $customer = Customer::firstOrCreate(['name' => $customerName], ['phone' => $saleData['customerPhone'] ?? null]);
                $customer = Customer::where('id', $customer->id)->lockForUpdate()->first();
                $customerId = $customer->id;
            }

            $deliveryStatus = $isSuppliedNow ? 'DELIVERED' : 'UNSUPPLIED';
            $saleType = $saleData['sale_type'] ?? 'RETAIL';

            $sale = Sale::create([
                'id' => $saleId,
                'tenant_id' => session('tenant_id') ?? null,
                'warehouse_id' => $warehouseId,
                'customerName' => $customerName,
                'customerId' => $customerId,
                'totalAmount' => $totalAmount,
                'paidAmount' => $paidAmount,
                'tenderedAmount' => $tenderedTotal,
                'changeAmount' => $changeAmount,
                'cashAmount' => $netCashKept,
                'posAmount' => $posAmount,
                'transferAmount' => 0.0,
                'note' => $saleData['note'] ?? null,
                'status' => $saleStatus,
                'sale_type' => $saleType,
                'deliveryStatus' => $deliveryStatus,
                'deliveredAt' => $isSuppliedNow ? now()->toIso8601String() : null,
                'deliveredBy' => $isSuppliedNow ? $userName : null,
                'userId' => $userId,
                'userName' => $userName,
                'createdAt' => now()->toIso8601String(),
            ]);

            // Sort line items deterministically by product ID to guarantee monotonic lock acquisition and eliminate database deadlocks
            usort($validatedItems, function ($a, $b) {
                return strcmp((string)$a['product']->id, (string)$b['product']->id);
            });

            // Process line items & stock impact
            foreach ($validatedItems as $vItem) {
                $product = $vItem['product'];
                $qty = $vItem['quantity'];
                $unitPrice = $vItem['unitPrice'];
                $lineTotal = $vItem['totalPrice'];

                $saleItem = SaleItem::create([
                    'tenant_id' => session('tenant_id') ?? $sale->tenant_id ?? null,
                    'saleId' => $saleId,
                    'productId' => $product->id,
                    'productName' => $product->name,
                    'quantity' => $qty,
                    'unitPrice' => $unitPrice,
                    'totalPrice' => $lineTotal,
                    'code' => $product->code,
                    'productCode' => $product->code,
                ]);

                $stock = $this->ensureStockLevelForAuthorizedMutation($product->id, $warehouseId, true);

                if ($isSuppliedNow) {
                    // Core Invariant: Physical sale requires physical_stock >= Q ONLY.
                    // Allocated stock is decoupled and represents customer reservations.
                    if ($stock->physical_stock < $qty) {
                        throw new InsufficientStockException(
                            "Cannot complete sale: Insufficient physical stock for '{$product->name}' ({$product->code}) at branch #{$warehouseId}. Physical: {$stock->physical_stock}, Allocated: {$stock->allocated_stock}, Requested: {$qty}",
                            $product->code,
                            $product->name,
                            $warehouseId,
                            $stock->physical_stock,
                            $qty
                        );
                    }
                    // Item leaves the physical shop immediately; allocated_stock is unchanged
                    $stock->physical_stock = $stock->physical_stock - $qty;
                    $stock->save();

                    $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                    $product->save();

                    InventoryLog::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => session('tenant_id') ?? $sale->tenant_id ?? null,
                        'productId' => $product->id,
                        'warehouse_id' => $warehouseId,
                        'type' => 'SALE',
                        'quantity' => -$qty,
                        'userId' => $userId,
                        'userName' => $userName,
                        'productCode' => $product->code,
                        'productName' => $product->name,
                        'description' => "Sale #{$saleId} (Supplied to {$customerName})",
                        'timestamp' => now()->toIso8601String(),
                    ]);
                } else {
                    // Unsupplied sale: Customer purchased buffer goods for future collection.
                    // Increments allocated_stock (can validly exceed physical_stock if shortfall exists).
                    // Physical stock on ground does not leave the warehouse yet.
                    $stock->allocated_stock += $qty;
                    $stock->save();

                    // Create authoritative line-item reservation
                    \App\Models\StockReservation::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => session('tenant_id') ?? $sale->tenant_id ?? null,
                        'sale_id' => $saleId,
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $product->id,
                        'warehouse_id' => $warehouseId,
                        'reserved_qty' => $qty,
                        'fulfilled_qty' => 0,
                        'cancelled_qty' => 0,
                        'status' => 'ACTIVE',
                        'customer_id' => $customerId ? (string) $customerId : null,
                        'customer_name' => $customerName,
                        'notes' => "Sale #{$saleId} unsupplied buffer reservation",
                    ]);

                    InventoryLog::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => session('tenant_id') ?? $sale->tenant_id ?? null,
                        'productId' => $product->id,
                        'warehouse_id' => $warehouseId,
                        'type' => 'SALE_RESERVED',
                        'quantity' => 0, // Physical stock unchanged; customer reservation recorded
                        'userId' => $userId,
                        'userName' => $userName,
                        'productCode' => $product->code,
                        'productName' => $product->name,
                        'description' => "Sale #{$saleId} (Unsupplied - Goods reserved for {$customerName})",
                        'timestamp' => now()->toIso8601String(),
                    ]);
                }
            }

            // Record granular Payment records for each tender method used in mixed/split payments
            $tenantId = session('tenant_id') ?? $sale->tenant_id ?? null;

            // 1. Cash Tender (Net cash retained in cashier drawer after change)
            if ($netCashKept > 0) {
                Payment::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'saleId' => $saleId,
                    'amount' => $netCashKept,
                    'method' => 'CASH',
                    'timestamp' => now()->toIso8601String(),
                    'recordedBy' => $userName,
                    'createdAt' => now()->toIso8601String(),
                ]);
            }

            // 2. POS Card Payment
            if ($posAmount > 0) {
                Payment::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'saleId' => $saleId,
                    'amount' => $posAmount,
                    'method' => 'POS',
                    'timestamp' => now()->toIso8601String(),
                    'recordedBy' => $userName,
                    'createdAt' => now()->toIso8601String(),
                ]);
            }



            // Handle Customer Debt Ledger for Part Payments
            if ($customer && $remainingDebt > 0) {
                // Customer is already held under exclusive lockForUpdate() (Level 2)
                $currentDebtKobo = \App\Services\Accounting\AccountingReportService::toKobo($customer->total_debt);
                $remainingDebtKobo = \App\Services\Accounting\AccountingReportService::toKobo($remainingDebt);
                $customer->total_debt = \App\Services\Accounting\AccountingReportService::toNaira($currentDebtKobo + $remainingDebtKobo);
                $customer->save();

                CustomerLedger::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'sale_id' => $saleId,
                    'warehouse_id' => $warehouseId,
                    'type' => 'INVOICE',
                    'amount' => $remainingDebt,
                    'balance_after' => $customer->total_debt,
                    'payment_method' => 'DEBT_ISSUED',
                    'reference_no' => $saleId,
                    'recorded_by' => $userName,
                    'notes' => "Invoice created. Paid: ₦{$paidAmount}, Debt balance: ₦{$remainingDebt}",
                ]);
            }

            return $sale;
        });
    }

    /**
     * Dispatch Unsupplied Goods (Customer picks up previously purchased goods).
     */
    public function dispatchUnsuppliedSale(string $saleId, int $warehouseId, string $userId, string $userName): Sale
    {
        $this->assertUserCapability('stock.transfer', $userId);
        $this->assertUserWarehouseAuthority($warehouseId);

        return DB::transaction(function () use ($saleId, $warehouseId, $userId, $userName) {
            $sale = Sale::with('items')->where('id', $saleId)->lockForUpdate()->firstOrFail();

            // Branch isolation: Goods must be dispatched from the branch where they were originally sold/reserved
            if (!empty($sale->warehouse_id) && (int) $sale->warehouse_id !== (int) $warehouseId) {
                throw new \InvalidArgumentException("Cross-branch dispatch rejected: Sale #{$saleId} was reserved at Branch #{$sale->warehouse_id} and cannot be dispatched from Branch #{$warehouseId}.");
            }

            if ($sale->deliveryStatus === 'DELIVERED') {
                throw new \Exception('Sale has already been fully delivered/dispatched.');
            }

            $sortedItems = $sale->items->sortBy('productId');
            foreach ($sortedItems as $item) {
                $stock = $this->ensureStockLevelForAuthorizedMutation($item->productId, $warehouseId, true);
                $qty = (int) $item->quantity;

                if ($stock->allocated_stock < $qty || $stock->physical_stock < $qty) {
                    $product = Product::find($item->productId);
                    $pName = $product ? $product->name : $item->productName;
                    $pCode = $product ? $product->code : $item->code;
                    throw new InsufficientStockException(
                        "Cannot fulfill dispatch for Sale #{$saleId}: Insufficient allocated reservation ({$stock->allocated_stock}) or physical stock ({$stock->physical_stock}) for '{$pName}' ({$pCode}) at branch #{$warehouseId}. Requested: {$qty}",
                        $pCode,
                        $pName,
                        $warehouseId,
                        $stock->allocated_stock,
                        $qty
                    );
                }

                // Deduct from physical stock and release allocated stock
                $stock->physical_stock = $stock->physical_stock - $qty;
                $stock->allocated_stock = $stock->allocated_stock - $qty;
                $stock->save();

                $product = Product::find($item->productId);
                if ($product) {
                    $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                    $product->save();
                }

                // Update authoritative line-item reservation record
                $reservation = \App\Models\StockReservation::where('sale_id', $saleId)
                    ->where('product_id', $item->productId)
                    ->where('warehouse_id', $warehouseId)
                    ->whereIn('status', ['ACTIVE', 'PARTIALLY_FULFILLED'])
                    ->first();

                if ($reservation) {
                    $reservation->fulfilled_qty += $qty;
                    if ($reservation->fulfilled_qty >= $reservation->reserved_qty) {
                        $reservation->status = 'FULFILLED';
                    } else {
                        $reservation->status = 'PARTIALLY_FULFILLED';
                    }
                    $reservation->save();
                }

                InventoryLog::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => session('tenant_id') ?? $sale->tenant_id ?? null,
                    'productId' => $item->productId,
                    'warehouse_id' => $warehouseId,
                    'type' => 'DISPATCH_FULFILLED',
                    'quantity' => -$qty,
                    'userId' => $userId,
                    'userName' => $userName,
                    'productCode' => $item->code,
                    'productName' => $item->productName,
                    'description' => "Dispatch fulfilled for Sale #{$saleId} to {$sale->customerName}",
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            $sale->deliveryStatus = 'DELIVERED';
            $sale->deliveredAt = now()->toIso8601String();
            $sale->deliveredBy = $userName;
            $sale->save();

            Activity::create([
                'id' => (string) Str::uuid(),
                'type' => 'DISPATCH_FULFILLED',
                'description' => "{$userName} dispatched previously unsupplied goods for Sale #{$saleId} ({$sale->customerName})",
                'userId' => $userId,
                'userName' => $userName,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $sale;
        });
    }

    /**
     * Authoritative unit/partial fulfillment of an unsupplied stock reservation.
     */
    public function fulfillStockReservation(string $saleId, string $productId, int $warehouseId, int $qty, string $userId, string $userName): \App\Models\StockReservation
    {
        $this->assertUserCapability('stock.transfer', $userId);
        $this->assertUserWarehouseAuthority($warehouseId);

        if ($qty <= 0) {
            throw new \InvalidArgumentException('Fulfillment quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($saleId, $productId, $warehouseId, $qty, $userId, $userName) {
            $sale = Sale::with('items')->where('id', $saleId)->lockForUpdate()->firstOrFail();

            if (!empty($sale->warehouse_id) && (int) $sale->warehouse_id !== (int) $warehouseId) {
                throw new \InvalidArgumentException("Cross-branch reservation fulfillment rejected: Sale #{$saleId} was reserved at Branch #{$sale->warehouse_id} and cannot be fulfilled from Branch #{$warehouseId}.");
            }

            $stock = $this->ensureStockLevelForAuthorizedMutation($productId, $warehouseId, true);
            if ($stock->allocated_stock < $qty || $stock->physical_stock < $qty) {
                $product = Product::find($productId);
                $pName = $product ? $product->name : $productId;
                $pCode = $product ? $product->code : $productId;
                throw new InsufficientStockException(
                    "Cannot fulfill reservation for Sale #{$saleId}: Insufficient allocated ({$stock->allocated_stock}) or physical stock ({$stock->physical_stock}) for '{$pName}' ({$pCode}) at branch #{$warehouseId}. Requested: {$qty}",
                    $pCode,
                    $pName,
                    $warehouseId,
                    $stock->allocated_stock,
                    $qty
                );
            }

            $stock->physical_stock -= $qty;
            $stock->allocated_stock -= $qty;
            $stock->save();

            $product = Product::find($productId);
            if ($product) {
                $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                $product->save();
            }

            $reservation = \App\Models\StockReservation::where('sale_id', $saleId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->whereIn('status', ['ACTIVE', 'PARTIALLY_FULFILLED'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->outstanding_qty < $qty) {
                throw new \InvalidArgumentException("Requested fulfillment quantity ({$qty}) exceeds outstanding reservation ({$reservation->outstanding_qty}).");
            }

            $reservation->fulfilled_qty += $qty;
            if ($reservation->fulfilled_qty >= $reservation->reserved_qty) {
                $reservation->status = 'FULFILLED';
            } else {
                $reservation->status = 'PARTIALLY_FULFILLED';
            }
            $reservation->save();

            $remainingActive = \App\Models\StockReservation::where('sale_id', $saleId)
                ->whereIn('status', ['ACTIVE', 'PARTIALLY_FULFILLED'])
                ->count();
            if ($remainingActive === 0) {
                $sale->deliveryStatus = 'DELIVERED';
                $sale->deliveredAt = now()->toIso8601String();
                $sale->deliveredBy = $userName;
                $sale->save();
            }

            InventoryLog::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => session('tenant_id') ?? $sale->tenant_id ?? null,
                'productId' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => 'DISPATCH_FULFILLED',
                'quantity' => -$qty,
                'userId' => $userId,
                'userName' => $userName,
                'productCode' => $product ? $product->code : '',
                'productName' => $product ? $product->name : '',
                'description' => "Reservation fulfilled ({$qty} units) for Sale #{$saleId} to {$sale->customerName}",
                'timestamp' => now()->toIso8601String(),
            ]);

            return $reservation;
        });
    }

    /**
     * Anti-Theft Step 1: Initiate Inter-Location Transfer (Goods Dispatched from Source).
     */
    public function initiateTransfer(int $sourceWarehouseId, int $destWarehouseId, array $items, ?string $carrierName, string $userId, string $userName, ?string $notes = null): Transfer
    {
        $this->assertUserCapability('stock.transfer', $userId);
        $this->assertUserWarehouseAuthority($sourceWarehouseId);
        $this->assertTenantWarehouse($destWarehouseId);

        return DB::transaction(function () use ($sourceWarehouseId, $destWarehouseId, $items, $carrierName, $userId, $userName, $notes) {
            // 1. Validate branch identity and distinctness
            if ($sourceWarehouseId === $destWarehouseId) {
                throw new \InvalidArgumentException("Origin and destination branch locations cannot be identical (#{$sourceWarehouseId}).");
            }

            // 2. Authoritative Tenant Boundary Verification for Both Source & Destination
            $sourceWh = Warehouse::withoutGlobalScopes()->find($sourceWarehouseId);
            $destWh   = Warehouse::withoutGlobalScopes()->find($destWarehouseId);

            if (!$sourceWh) {
                throw new \InvalidArgumentException("Source branch #{$sourceWarehouseId} does not exist.");
            }
            if (!$destWh) {
                throw new \InvalidArgumentException("Destination branch #{$destWarehouseId} does not exist.");
            }

            if (config('saas.enabled')) {
                $currentTenantId = session('tenant_id') ?? (Auth::check() ? Auth::user()->tenant_id : null) ?? $sourceWh->tenant_id;

                if ($sourceWh->tenant_id !== $currentTenantId) {
                    throw new \InvalidArgumentException("Security Violation: Source branch #{$sourceWarehouseId} does not belong to active tenant '{$currentTenantId}'.");
                }
                if ($destWh->tenant_id !== $currentTenantId) {
                    throw new \InvalidArgumentException("Security Violation: Destination branch #{$destWarehouseId} belongs to a different tenant ('{$destWh->tenant_id}'). Cross-tenant stock transfers are strictly forbidden.");
                }
            }

            // 3. Pre-validate ALL items and lock stock before creating the Transfer record
            if (empty($items)) {
                throw new \InvalidArgumentException("Cannot dispatch transfer: Must specify at least one product item.");
            }

            // Sort transfer items deterministically by product ID to guarantee monotonic lock acquisition
            usort($items, function ($a, $b) {
                $idA = (string)($a['productId'] ?? $a['product_id'] ?? '');
                $idB = (string)($b['productId'] ?? $b['product_id'] ?? '');
                return strcmp($idA, $idB);
            });

            $validatedItems = [];
            foreach ($items as $index => $item) {
                $productId = $item['productId'] ?? $item['product_id'] ?? null;
                if (!$productId) {
                    throw new \InvalidArgumentException("Transfer item at index {$index} is missing a valid product ID.");
                }

                $product = Product::find($productId);
                if (!$product) {
                    throw new \InvalidArgumentException("Product #{$productId} does not exist in catalog.");
                }

                $qty = (int) ($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    throw new \InvalidArgumentException("Transfer quantity for product '{$product->name}' ({$product->code}) must be an integer greater than zero (received: {$qty}). Negative quantities are strictly prohibited.");
                }

                // Row-level lock on source stock to prevent overdraft of available unallocated stock
                $stock = $this->ensureStockLevelForAuthorizedMutation($product->id, $sourceWarehouseId, true);
                $availableStock = (int) ($stock->physical_stock - $stock->allocated_stock);
                if ($availableStock < $qty) {
                    throw new InsufficientStockException(
                        "Cannot dispatch transfer: Insufficient available unallocated stock for '{$product->name}' ({$product->code}) at origin branch #{$sourceWarehouseId}. Physical: {$stock->physical_stock}, Allocated: {$stock->allocated_stock}, Available: {$availableStock}, Requested: {$qty}",
                        $product->code,
                        $product->name,
                        $sourceWarehouseId,
                        $availableStock,
                        $qty
                    );
                }

                $validatedItems[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'stock' => $stock,
                ];
            }

            // 4. Invariants satisfied: Safely create Transfer record
            $transferNo = 'TRF-' . strtoupper(Str::random(6)) . '-' . date('ymd');
            $tenantId = session('tenant_id') ?? $sourceWh->tenant_id ?? 'default-tenant';

            $transfer = Transfer::create([
                'tenant_id' => $tenantId,
                'transfer_no' => $transferNo,
                'source_warehouse_id' => $sourceWarehouseId,
                'destination_warehouse_id' => $destWarehouseId,
                'status' => 'DISPATCHED',
                'carrier_name' => $carrierName,
                'dispatched_by' => $userName,
                'dispatched_at' => now(),
                'notes' => $notes,
            ]);

            // 5. Create Transfer Items and deduct inventory
            foreach ($validatedItems as $vItem) {
                $product = $vItem['product'];
                $qty = $vItem['quantity'];
                $stock = $vItem['stock'];

                TransferItem::create([
                    'tenant_id' => $tenantId,
                    'transfer_id' => $transfer->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_code' => $product->code,
                    'dispatched_qty' => $qty,
                    'received_qty' => 0,
                    'discrepancy_qty' => 0,
                ]);

                // Deduct from source physical stock
                $stock->physical_stock = $stock->physical_stock - $qty;
                $stock->save();

                $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                $product->save();

                InventoryLog::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'productId' => $product->id,
                    'warehouse_id' => $sourceWarehouseId,
                    'type' => 'TRANSFER_OUT',
                    'quantity' => -$qty,
                    'userId' => $userId,
                    'userName' => $userName,
                    'productCode' => $product->code,
                    'productName' => $product->name,
                    'description' => "Transfer #{$transferNo} Dispatched to Warehouse #{$destWarehouseId} via {$carrierName}",
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            Activity::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'type' => 'TRANSFER_DISPATCHED',
                'description' => "Transfer #{$transferNo} dispatched from Shop #{$sourceWarehouseId} to Shop #{$destWarehouseId} by {$userName}",
                'userId' => $userId,
                'userName' => $userName,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $transfer;
        });
    }

    /**
     * Anti-Theft Step 2: Receive & Count Inter-Location Transfer (Verify Count at Destination).
     */
    public function receiveTransfer(int $transferId, array $countedItems, string $userId, string $userName, ?string $discrepancyNotes = null): Transfer
    {
        $this->assertUserCapability('stock.receive', $userId);

        return DB::transaction(function () use ($transferId, $countedItems, $userId, $userName, $discrepancyNotes) {
            $transfer = Transfer::with('items')->where('id', $transferId)->lockForUpdate()->firstOrFail();

            $this->assertUserWarehouseAuthority($transfer->destination_warehouse_id);

            if ($transfer->status !== 'DISPATCHED') {
                throw new \Exception("Transfer #{$transfer->transfer_no} cannot be received: Current status is '{$transfer->status}' (only in-transit 'DISPATCHED' transfers can be received).");
            }

            $hasDiscrepancy = false;
            $totalDiscrepancy = 0;

            foreach ($transfer->items as $transferItem) {
                // Find counted quantity sent from form
                $rawCounted = $countedItems[$transferItem->product_id] ?? $transferItem->dispatched_qty;
                $countedQty = (int) $rawCounted;

                if ($countedQty < 0) {
                    throw new \InvalidArgumentException("Counted quantity for '{$transferItem->product_name}' cannot be negative.");
                }
                if ($countedQty > $transferItem->dispatched_qty) {
                    throw new \InvalidArgumentException("Counted quantity ({$countedQty}) cannot exceed dispatched quantity ({$transferItem->dispatched_qty}) for '{$transferItem->product_name}'.");
                }

                $discrepancy = $transferItem->dispatched_qty - $countedQty;

                $transferItem->received_qty = $countedQty;
                $transferItem->discrepancy_qty = $discrepancy;
                $transferItem->save();

                if ($discrepancy != 0) {
                    $hasDiscrepancy = true;
                    $totalDiscrepancy += abs($discrepancy);
                }

                // Add counted physical stock to destination warehouse with row locking
                $destStock = $this->ensureStockLevelForAuthorizedMutation($transferItem->product_id, $transfer->destination_warehouse_id, true);
                $destStock->physical_stock += $countedQty;
                $destStock->save();

                $product = Product::find($transferItem->product_id);
                if ($product) {
                    $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                    $product->save();
                }

                InventoryLog::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => session('tenant_id') ?? $transfer->tenant_id ?? null,
                    'productId' => $transferItem->product_id,
                    'warehouse_id' => $transfer->destination_warehouse_id,
                    'type' => 'TRANSFER_IN',
                    'quantity' => $countedQty,
                    'userId' => $userId,
                    'userName' => $userName,
                    'productCode' => $transferItem->product_code,
                    'productName' => $transferItem->product_name,
                    'description' => "Transfer #{$transfer->transfer_no} Received at Destination. Count: {$countedQty} (Dispatched: {$transferItem->dispatched_qty})",
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            $transfer->status = $hasDiscrepancy ? 'DISCREPANCY' : 'RECEIVED';
            $transfer->received_by = $userName;
            $transfer->received_at = now();
            $transfer->discrepancy_notes = $discrepancyNotes;
            $transfer->save();

            // Log activity & trigger Auditor alert if discrepancy exists
            Activity::create([
                'id' => (string) Str::uuid(),
                'type' => $hasDiscrepancy ? 'THEFT_ALERT_DISCREPANCY' : 'TRANSFER_RECEIVED',
                'description' => $hasDiscrepancy
                    ? "🚨 THEFT/VARIANCE ALERT: Transfer #{$transfer->transfer_no} received with {$totalDiscrepancy} missing units! Carrier: {$transfer->carrier_name}, Counted by: {$userName}"
                    : "Transfer #{$transfer->transfer_no} successfully received and verified with 0 discrepancies by {$userName}",
                'userId' => $userId,
                'userName' => $userName,
                'timestamp' => now()->toIso8601String(),
                'metadata' => json_encode([
                    'transfer_no' => $transfer->transfer_no,
                    'has_discrepancy' => $hasDiscrepancy,
                    'missing_units' => $totalDiscrepancy,
                ]),
            ]);

            return $transfer;
        });
    }

    /**
     * Recall / Cancel Dispatched Transfer (Restores physical stock back to source shop).
     */
    public function recallTransfer(int $transferId, string $userId, string $userName, ?string $reason = null): Transfer
    {
        $this->assertUserCapability('stock.recall', $userId);

        return DB::transaction(function () use ($transferId, $userId, $userName, $reason) {
            $transfer = Transfer::with(['items', 'source', 'destination'])->where('id', $transferId)->lockForUpdate()->firstOrFail();

            $this->assertUserWarehouseAuthority($transfer->source_warehouse_id);

            if ($transfer->status !== 'DISPATCHED') {
                throw new \Exception("Transfer #{$transfer->transfer_no} cannot be recalled: Current status is '{$transfer->status}' (only in-transit 'DISPATCHED' transfers can be recalled).");
            }

            foreach ($transfer->items as $item) {
                $qty = (int) $item->dispatched_qty;

                // Restore stock back to source warehouse with row locking
                $stock = $this->ensureStockLevelForAuthorizedMutation($item->product_id, $transfer->source_warehouse_id, true);
                $stock->physical_stock += $qty;
                $stock->save();

                $product = Product::find($item->product_id);
                if ($product) {
                    $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                    $product->save();
                }

                InventoryLog::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => session('tenant_id') ?? $transfer->tenant_id ?? null,
                    'productId' => $item->product_id,
                    'warehouse_id' => $transfer->source_warehouse_id,
                    'type' => 'TRANSFER_CANCELLED',
                    'quantity' => $qty,
                    'userId' => $userId,
                    'userName' => $userName,
                    'productCode' => $item->product_code,
                    'productName' => $item->product_name,
                    'description' => "Transfer #{$transfer->transfer_no} Recalled/Cancelled back to " . ($transfer->source->name ?? 'Source') . ". Reason: " . ($reason ?? 'Trip cancelled'),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            $transfer->status = 'CANCELLED';
            $transfer->notes = trim(($transfer->notes ?? '') . " [Recalled by {$userName}: " . ($reason ?? 'Delivery cancelled') . "]");
            $transfer->save();

            Activity::create([
                'id' => (string) Str::uuid(),
                'type' => 'TRANSFER_CANCELLED',
                'description' => "Transfer #{$transfer->transfer_no} cancelled by {$userName}. Stock restored to " . ($transfer->source->name ?? 'Source') . ".",
                'userId' => $userId,
                'userName' => $userName,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $transfer;
        });
    }

    /**
     * Record Customer Debt Payment (Part payment recovery).
     * Strictly scopes invoice reconciliation to branch warehouse if acting user is branch-scoped.
     * Enforces that 100% of the payment amount is allocated across open invoice balances.
     */
    public function recordCustomerPayment(
        int $customerId,
        float $amount,
        string $paymentMethod,
        ?string $refNo,
        string $userId,
        string $userName,
        ?string $notes = null,
        ?int $warehouseId = null
    ): CustomerLedger {
        $this->assertUserCapability('debt.pay', $userId);

        $cleanMethod = strtoupper(trim($paymentMethod));
        if (!in_array($cleanMethod, ['CASH', 'POS'], true)) {
            throw new \InvalidArgumentException("Debt payment method must be either 'CASH' or 'POS'.");
        }

        // Automatically bind branch context for branch-scoped actors if not explicitly specified
        if ($warehouseId === null) {
            $actingUser = Auth::user() ?? \App\Models\User::withoutGlobalScopes()->find($userId);
            if ($actingUser && $actingUser->isBranchScoped()) {
                $warehouseId = (int) $actingUser->warehouse_id;
            }
        }

        if ($warehouseId !== null) {
            $this->assertUserWarehouseAuthority($warehouseId);
        }

        return DB::transaction(function () use ($customerId, $amount, $cleanMethod, $refNo, $userId, $userName, $notes, $warehouseId) {
            if ($amount <= 0) {
                throw new \InvalidArgumentException("Payment amount must be greater than zero.");
            }

            $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

            if ($amount > (float) $customer->total_debt) {
                throw new \InvalidArgumentException(
                    "Payment amount (₦" . number_format($amount, 2) . ") exceeds customer's total outstanding debt (₦" . number_format($customer->total_debt, 2) . ")."
                );
            }

            // Tenant boundary assertion
            if (config('saas.enabled')) {
                $activeTenantId = session('tenant_id') ?? (Auth::check() ? Auth::user()->tenant_id : null);
                if ($activeTenantId && $customer->tenant_id !== $activeTenantId) {
                    throw new \InvalidArgumentException("Security Violation: Customer #{$customerId} does not belong to active tenant '{$activeTenantId}'.");
                }
            }

            $hasSales = Sale::where('customerId', $customerId)->exists();
            $allocatedSaleIds = [];
            $tenantId = session('tenant_id') ?? $customer->tenant_id ?? null;

            if ($hasSales) {
                // Retrieve open sales strictly within branch context if warehouseId is provided
                $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
                $openSalesQuery = Sale::where('customerId', $customerId)
                    ->whereNotIn('status', ['CANCELLED', 'RETURNED']);

                if ($warehouseId !== null) {
                    $openSalesQuery->where('warehouse_id', $warehouseId);
                }

                $openSales = $openSalesQuery->orderBy('createdAt', 'asc')->lockForUpdate()->get();

                // Calculate authoritative available unpaid invoice balance within scope
                $totalAvailableUnpaid = 0.0;
                foreach ($openSales as $pSale) {
                    $totalAvailableUnpaid += $accountingService->calculateInvoiceBalance($pSale);
                }
                $totalAvailableUnpaid = round($totalAvailableUnpaid, 2);

                if ($totalAvailableUnpaid <= 0.001) {
                    $scopeDesc = $warehouseId ? "at branch warehouse #{$warehouseId}" : "across open invoices";
                    throw new \InvalidArgumentException(
                        "Customer {$customer->name} has no outstanding invoice debt {$scopeDesc}."
                    );
                }

                if ($amount > $totalAvailableUnpaid) {
                    $scopeDesc = $warehouseId ? "at this branch" : "across open invoices";
                    throw new \InvalidArgumentException(
                        "Payment amount (₦" . number_format($amount, 2) . ") exceeds customer's outstanding balance {$scopeDesc} (₦" . number_format($totalAvailableUnpaid, 2) . ")."
                    );
                }

                $currentCustDebtKobo = \App\Services\Accounting\AccountingReportService::toKobo($customer->total_debt);
                $paymentTotalKobo = \App\Services\Accounting\AccountingReportService::toKobo($amount);
                $customer->total_debt = \App\Services\Accounting\AccountingReportService::toNaira(max(0, $currentCustDebtKobo - $paymentTotalKobo));
                $customer->save();

                $remainingPaymentKobo = $paymentTotalKobo;

                foreach ($openSales as $pSale) {
                    if ($remainingPaymentKobo <= 0) break;

                    $unpaidKobo = \App\Services\Accounting\AccountingReportService::toKobo($accountingService->calculateInvoiceBalance($pSale));
                    if ($unpaidKobo <= 0) {
                        continue;
                    }

                    $allocKobo = min($remainingPaymentKobo, $unpaidKobo);
                    $alloc = \App\Services\Accounting\AccountingReportService::toNaira($allocKobo);
                    $pSale->paidAmount = \App\Services\Accounting\AccountingReportService::toNaira(
                        \App\Services\Accounting\AccountingReportService::toKobo($pSale->paidAmount) + $allocKobo
                    );
                    if (($pSale->totalAmount - $pSale->paidAmount) <= 0.001 || ($unpaidKobo - $allocKobo) <= 0) {
                        $pSale->status = 'COMPLETED';
                    }
                    $pSale->save();

                    // Financial ledger entry linked to the specific sale invoice
                    Payment::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId ?? $pSale->tenant_id,
                        'saleId' => $pSale->id,
                        'amount' => $alloc,
                        'method' => $cleanMethod,
                        'timestamp' => now()->toIso8601String(),
                        'recordedBy' => $userName . ' [DEBT_RECOVERY]',
                        'createdAt' => now()->toIso8601String(),
                    ]);

                    $allocatedSaleIds[] = $pSale->id;
                    $remainingPaymentKobo -= $allocKobo;
                }

                // Strict Invariant: 100% of the payment must be allocated
                if ($remainingPaymentKobo > 0) {
                    $unallocNaira = \App\Services\Accounting\AccountingReportService::toNaira($remainingPaymentKobo);
                    throw new \RuntimeException(
                        "Accounting Integrity Error: Payment of ₦" . number_format($amount, 2) . " could not be fully allocated across open invoices. Remaining unallocated: ₦" . number_format($unallocNaira, 2) . "."
                    );
                }

                $targetWarehouseId = $warehouseId ?? ($openSales->first()?->warehouse_id ?? null);
            } else {
                // Customer has opening debt or ledger balance without sales records
                $currentCustDebtKobo = \App\Services\Accounting\AccountingReportService::toKobo($customer->total_debt);
                $paymentTotalKobo = \App\Services\Accounting\AccountingReportService::toKobo($amount);
                $customer->total_debt = \App\Services\Accounting\AccountingReportService::toNaira(max(0, $currentCustDebtKobo - $paymentTotalKobo));
                $customer->save();
                $targetWarehouseId = $warehouseId;
            }

            $ledger = CustomerLedger::create([
                'tenant_id' => $tenantId ?? $customer->tenant_id,
                'customer_id' => $customer->id,
                'sale_id' => count($allocatedSaleIds) === 1 ? $allocatedSaleIds[0] : null,
                'warehouse_id' => $targetWarehouseId,
                'type' => 'PAYMENT',
                'amount' => $amount,
                'balance_after' => $customer->total_debt,
                'payment_method' => $cleanMethod,
                'reference_no' => $refNo,
                'recorded_by' => $userName,
                'notes' => $notes ?? "Part-payment of ₦" . number_format($amount, 2) . " received via {$cleanMethod}. New balance: ₦" . number_format($customer->total_debt, 2),
            ]);

            Activity::create([
                'id' => (string) Str::uuid(),
                'type' => 'DEBT_PAYMENT',
                'description' => "{$userName} recorded debt payment of ₦" . number_format($amount, 2) . " via {$cleanMethod} for {$customer->name}",
                'userId' => $userId,
                'userName' => $userName,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $ledger;
        });
    }

    /**
     * Record Stock Adjustment (Damaged, Expired, or Lost items on ground).
     */
    public function recordStockAdjustment(string $productId, int $warehouseId, string $type, int $quantity, string $reason, string $userId, string $userName): \App\Models\StockAdjustment
    {
        $this->assertUserCapability('stock.adjust', $userId);
        $this->assertUserWarehouseAuthority($warehouseId);

        return DB::transaction(function () use ($productId, $warehouseId, $type, $quantity, $reason, $userId, $userName) {
            if ($quantity <= 0) {
                throw new \InvalidArgumentException("Write-off quantity must be at least 1 unit.");
            }

            $product = Product::findOrFail($productId);
            $stock = $this->ensureStockLevelForAuthorizedMutation($productId, $warehouseId, true);

            $available = (int) ($stock->physical_stock - $stock->allocated_stock);
            if ($available < $quantity) {
                throw new InsufficientStockException(
                    "Cannot record stock write-off: Insufficient available stock for '{$product->name}' ({$product->code}) at branch #{$warehouseId}. Physical: {$stock->physical_stock}, Allocated: {$stock->allocated_stock}, Available: {$available}, Requested write-off: {$quantity}",
                    $product->code,
                    $product->name,
                    $warehouseId,
                    $available,
                    $quantity
                );
            }

            // Deduct physical closing stock
            $stock->physical_stock = $stock->physical_stock - $quantity;
            $stock->save();

            $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
            $product->save();

            $adjustment = \App\Models\StockAdjustment::create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'type' => strtoupper($type),
                'quantity' => $quantity,
                'reason' => $reason,
                'recorded_by' => $userName,
                'status' => 'APPROVED',
            ]);

            InventoryLog::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => session('tenant_id') ?? $product->tenant_id ?? null,
                'productId' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => 'STOCK_ADJUSTMENT_' . strtoupper($type),
                'quantity' => -$quantity,
                'userId' => $userId,
                'userName' => $userName,
                'productCode' => $product->code,
                'productName' => $product->name,
                'description' => "Stock Adjustment ({$type}): {$quantity} units written off. Reason: {$reason}",
                'timestamp' => now()->toIso8601String(),
            ]);

            Activity::create([
                'id' => (string) Str::uuid(),
                'type' => 'STOCK_ADJUSTMENT',
                'description' => "{$userName} wrote off {$quantity} units of {$product->name} ({$type}) at Shop #{$warehouseId}. Reason: {$reason}",
                'userId' => $userId,
                'userName' => $userName,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $adjustment;
        });
    }

    /**
     * Record Sales Return & Customer Refund (Restores units to physical closing stock).
     */
    public function recordSaleReturn(string $saleId, array $returnItems, int $warehouseId, string $refundMethod, string $reason, string $userId, string $userName): \App\Models\SalesReturn
    {
        $this->assertUserCapability('returns.process', $userId);
        $this->assertUserWarehouseAuthority($warehouseId);

        if (!in_array($refundMethod, ['CASH_REFUND', 'DEBT_REDUCTION'], true)) {
            throw new \InvalidArgumentException("Invalid refund method '{$refundMethod}'. Allowed methods are: CASH_REFUND, DEBT_REDUCTION.");
        }

        return DB::transaction(function () use ($saleId, $returnItems, $warehouseId, $refundMethod, $reason, $userId, $userName) {
            if (empty($returnItems)) {
                throw new \InvalidArgumentException("No items specified for return.");
            }

            // Canonical Lock Hierarchy Enforcement (Level 2: Customer):
            // If debt reduction is requested, lock Customer *before* Sale (Level 3) to prevent AB-BA deadlock with recordCustomerPayment
            $customer = null;
            if ($refundMethod === 'DEBT_REDUCTION') {
                $saleMeta = Sale::select('id', 'customerId', 'customerName')->where('id', $saleId)->first();
                if ($saleMeta) {
                    $cId = $saleMeta->customerId;
                    if ($cId) {
                        $customer = Customer::where('id', $cId)->lockForUpdate()->first();
                    } elseif ($saleMeta->customerName && $saleMeta->customerName !== 'Walk-in Customer') {
                        $customer = Customer::where('name', $saleMeta->customerName)->lockForUpdate()->first();
                    }
                }
            }

            // Level 3: Transaction Document Root (Sale)
            $sale = Sale::with('items')->where('id', $saleId)->lockForUpdate()->firstOrFail();

            // Branch isolation: Returns must be processed at the originating branch
            if (!empty($sale->warehouse_id) && (int) $sale->warehouse_id !== (int) $warehouseId) {
                throw new \InvalidArgumentException("Cross-branch return rejected: Sale #{$saleId} originated at Branch #{$sale->warehouse_id} and cannot be returned at Branch #{$warehouseId}.");
            }

            // Fetch prior returns for this sale to enforce refund & item quantity ceilings
            $priorReturns = \App\Models\SalesReturn::where('saleId', $saleId)->get();
            $priorRefundedTotal = (float) $priorReturns->sum('refundAmount');

            // Map sold items by product ID
            $soldItemsByProduct = $sale->items->keyBy('productId');

            $totalRefundAmount = 0;
            $firstProduct = null;
            $itemWasDelivered = [];

            // Sort return items deterministically by product ID to guarantee monotonic lock acquisition
            usort($returnItems, function ($a, $b) {
                $idA = (string)($a['productId'] ?? $a['product_id'] ?? '');
                $idB = (string)($b['productId'] ?? $b['product_id'] ?? '');
                return strcmp($idA, $idB);
            });

            foreach ($returnItems as $item) {
                $productId = $item['productId'] ?? $item['product_id'] ?? null;
                if (!$productId || !isset($soldItemsByProduct[$productId])) {
                    throw new \InvalidArgumentException("Cannot return item: Product was not part of original Sale #{$saleId}.");
                }

                $saleItem = $soldItemsByProduct[$productId];
                $product = Product::findOrFail($productId);
                $qty = (int) ($item['quantity'] ?? 0);

                if ($qty <= 0) {
                    throw new \InvalidArgumentException("Return quantity for '{$product->name}' must be at least 1 unit.");
                }

                // Check already returned quantity for this specific product in this sale authoritatively
                $previouslyReturnedQty = (int) \App\Models\SalesReturn::where('saleId', $saleId)
                    ->where('productId', $productId)
                    ->sum('quantity');

                if (($previouslyReturnedQty + $qty) > $saleItem->quantity) {
                    $remainingAllowed = max(0, $saleItem->quantity - $previouslyReturnedQty);
                    throw new \InvalidArgumentException(
                        "Cannot return {$qty} units of '{$product->name}'. Sold: {$saleItem->quantity}, Already returned: {$previouslyReturnedQty}, Remaining eligible: {$remainingAllowed}."
                    );
                }

                // Authoritative historical unit price from the actual sale record
                $unitPrice = (float) $saleItem->unitPrice;
                $totalRefundAmount += ($qty * $unitPrice);

                if (!$firstProduct) {
                    $firstProduct = $product;
                }

                // Restore physical closing stock or release allocation with row locking
                $stock = $this->ensureStockLevelForAuthorizedMutation($product->id, $warehouseId, true);

                $reservation = \App\Models\StockReservation::where('sale_id', $saleId)
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->first();

                $physicalUnitsRestored = 0;
                $allocatedUnitsReleased = 0;

                if ($reservation) {
                    $heldUnits = $reservation->held_by_customer_qty;
                    $outstandingUnits = $reservation->outstanding_qty;

                    $explicitDelivered = isset($item['was_delivered']) ? (bool)$item['was_delivered'] : null;
                    if ($explicitDelivered === null && isset($item['is_unsupplied'])) {
                        $explicitDelivered = !$item['is_unsupplied'];
                    }

                    $isExplicitBufferCancel = (is_string($reason) && preg_match('/(cancel|buffer|unsupplied|uncollected|not\s+collected|pending\s+delivery)/i', $reason));
                    $isExplicitPhysicalReturn = (is_string($reason) && preg_match('/(physical|in[- ]hand|collected|already\s+delivered|shelf)/i', $reason));

                    if ($explicitDelivered === true || ($explicitDelivered === null && $isExplicitPhysicalReturn && !$isExplicitBufferCancel)) {
                        $physicalUnitsRestored = min($qty, $heldUnits);
                        $allocatedUnitsReleased = $qty - $physicalUnitsRestored;
                    } elseif ($explicitDelivered === false || ($explicitDelivered === null && $isExplicitBufferCancel && !$isExplicitPhysicalReturn)) {
                        $allocatedUnitsReleased = min($qty, $outstandingUnits);
                        $physicalUnitsRestored = $qty - $allocatedUnitsReleased;
                    } else {
                        // Intelligent partitioning: customer returns physical items in hand first
                        $physicalUnitsRestored = min($qty, $heldUnits);
                        $allocatedUnitsReleased = $qty - $physicalUnitsRestored;
                    }

                    if ($physicalUnitsRestored > 0) {
                        $stock->physical_stock += $physicalUnitsRestored;
                        $reservation->returned_fulfilled_qty += $physicalUnitsRestored;
                    }

                    if ($allocatedUnitsReleased > 0) {
                        $stock->allocated_stock = max(0, $stock->allocated_stock - $allocatedUnitsReleased);
                        $reservation->cancelled_qty += $allocatedUnitsReleased;
                    }

                    $stock->save();

                    if ($reservation->outstanding_qty <= 0) {
                        if ($reservation->held_by_customer_qty > 0) {
                            $reservation->status = 'FULFILLED';
                        } else {
                            $reservation->status = ($reservation->returned_fulfilled_qty > 0) ? 'RETURNED' : 'CANCELLED';
                        }
                    } else {
                        $reservation->status = ($reservation->fulfilled_qty > 0) ? 'PARTIALLY_FULFILLED' : 'ACTIVE';
                    }
                    $reservation->save();
                } else {
                    $physicalUnitsRestored = $qty;
                    $stock->physical_stock += $qty;
                    $stock->save();
                }

                $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                $product->save();

                $itemWasDelivered[$productId] = ($physicalUnitsRestored > 0);

                $descParts = [];
                if ($physicalUnitsRestored > 0) {
                    $descParts[] = "{$physicalUnitsRestored} units restored to shelf count";
                }
                if ($allocatedUnitsReleased > 0) {
                    $descParts[] = "{$allocatedUnitsReleased} units reservation allocation released";
                }
                $movementDesc = implode(' & ', $descParts);

                InventoryLog::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => session('tenant_id') ?? $sale->tenant_id ?? null,
                    'productId' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'type' => 'SALES_RETURN',
                    'quantity' => $physicalUnitsRestored,
                    'userId' => $userId,
                    'userName' => $userName,
                    'productCode' => $product->code,
                    'productName' => $product->name,
                    'description' => "Customer Return for Sale #{$saleId} ({$movementDesc}). Reason: {$reason}",
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            // Update sale delivery status if all reservations are resolved
            $remainingActiveReservations = \App\Models\StockReservation::where('sale_id', $saleId)
                ->whereIn('status', ['ACTIVE', 'PARTIALLY_FULFILLED'])
                ->count();
            if ($remainingActiveReservations === 0 && $sale->deliveryStatus === 'UNSUPPLIED') {
                $hasHeld = (int) \App\Models\StockReservation::where('sale_id', $saleId)
                    ->get()
                    ->sum('held_by_customer_qty');
                if ($hasHeld > 0) {
                    $sale->deliveryStatus = 'DELIVERED';
                    $sale->deliveredAt = now()->toIso8601String();
                    $sale->deliveredBy = $userName;
                } else {
                    $sale->deliveryStatus = 'RETURNED';
                }
                $sale->save();
            }

            $wasDelivered = ($sale->deliveryStatus === 'DELIVERED');

            // Financial integrity: Cash refund cannot exceed actual money customer paid!
            if ($refundMethod === 'CASH_REFUND') {
                $cashPaid = (float) Payment::where('saleId', $saleId)->where('method', 'CASH')->where('amount', '>', 0)->sum('amount');
                if ($cashPaid <= 0 && (float) ($sale->cashAmount ?? 0) > 0) {
                    $cashPaid = (float) $sale->cashAmount;
                }
                $priorCashRefunds = abs((float) Payment::where('saleId', $saleId)->where('method', 'REFUND_CASH')->sum('amount'));
                $maxCashRefundable = max(0.0, round($cashPaid - $priorCashRefunds, 2));

                if ($totalRefundAmount > $maxCashRefundable) {
                    throw new \InvalidArgumentException(
                        "Cannot issue cash refund of ₦" . number_format($totalRefundAmount, 2) . ". Maximum refundable cash for Sale #{$saleId} based on actual payments made is ₦" . number_format($maxCashRefundable, 2) . ". Use DEBT_REDUCTION for unpaid/credit balance."
                    );
                }

                // Balance financial ledger: reduce sale paidAmount and create negative payment record
                $sale->paidAmount = max(0, (float) $sale->paidAmount - $totalRefundAmount);
                if ($sale->paidAmount < $sale->totalAmount) {
                    $sale->status = ($sale->paidAmount <= 0) ? 'RETURNED' : 'PARTIAL';
                }
                $sale->save();

                Payment::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => session('tenant_id') ?? $sale->tenant_id ?? null,
                    'saleId' => $saleId,
                    'amount' => -$totalRefundAmount,
                    'method' => 'REFUND_CASH',
                    'timestamp' => now()->toIso8601String(),
                    'recordedBy' => $userName,
                    'createdAt' => now()->toIso8601String(),
                ]);
            }

            // Financial integrity: Debt reduction applies against outstanding invoice debt and customer debt balance.
            // Invariant: The historical gross invoice ($sale->totalAmount) is NEVER mutated!
            // The return credit is authoritatively recorded in SalesReturn ($lineRefund), which calculateInvoiceBalance() deducts from gross invoice.
            if ($refundMethod === 'DEBT_REDUCTION') {
                $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
                $invoiceOutstanding = $accountingService->calculateInvoiceBalance($sale);
                if ($totalRefundAmount > $invoiceOutstanding) {
                    throw new \InvalidArgumentException(
                        "Cannot apply debt reduction of ₦" . number_format($totalRefundAmount, 2) . ". Outstanding balance on Sale #{$saleId} is only ₦" . number_format($invoiceOutstanding, 2) . "."
                    );
                }

                $balanceAfterReturn = max(0.0, round($invoiceOutstanding - $totalRefundAmount, 2));
                if ($balanceAfterReturn <= 0.01) {
                    $sale->status = 'COMPLETED';
                }
                $sale->save();

                if (!$customer) {
                    $cId = $sale->customerId ?? $sale->customer_id ?? null;
                    $customer = $cId ? Customer::find($cId) : null;
                    if (!$customer && $sale->customerName) {
                        $customer = Customer::where('name', $sale->customerName)->first();
                    }
                }

                if ($customer) {
                    $currentDebtKobo = \App\Services\Accounting\AccountingReportService::toKobo($customer->total_debt);
                    $refundKobo = \App\Services\Accounting\AccountingReportService::toKobo($totalRefundAmount);
                    $customer->total_debt = \App\Services\Accounting\AccountingReportService::toNaira(max(0, $currentDebtKobo - $refundKobo));
                    $customer->save();

                    CustomerLedger::create([
                        'tenant_id' => $sale->tenant_id,
                        'customer_id' => $customer->id,
                        'sale_id' => $saleId,
                        'warehouse_id' => $sale->warehouse_id,
                        'type' => 'RETURN_CREDIT',
                        'amount' => $totalRefundAmount,
                        'balance_after' => $customer->total_debt,
                        'payment_method' => 'RETURN_CREDIT',
                        'reference_no' => 'RET-' . strtoupper(Str::random(6)),
                        'recorded_by' => $userName,
                        'notes' => "Debt reduced by ₦" . number_format($totalRefundAmount, 2) . " due to Sales Return on Sale #{$saleId}",
                    ]);
                }
            }

            // Create individual SalesReturn record per returned SKU
            $primaryReturn = null;
            foreach ($returnItems as $rItem) {
                $pId = $rItem['productId'] ?? $rItem['product_id'];
                $pQty = (int) $rItem['quantity'];
                $prod = Product::find($pId);
                $uPrice = (float) $soldItemsByProduct[$pId]->unitPrice;
                $lineRefund = round($pQty * $uPrice, 2);

                $singleReturn = \App\Models\SalesReturn::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => session('tenant_id') ?? $sale->tenant_id ?? null,
                    'saleId' => $saleId,
                    'customerName' => $sale->customerName,
                    'code' => 'RET-' . strtoupper(Str::random(6)),
                    'productId' => $pId,
                    'productName' => $prod ? $prod->name : 'Returned Item',
                    'quantity' => $pQty,
                    'refundAmount' => $lineRefund,
                    'reason' => $reason,
                    'createdAt' => now()->toIso8601String(),
                    'userId' => $userId,
                    'userName' => $userName,
                    'wasDelivered' => $itemWasDelivered[$pId] ?? $wasDelivered,
                    'deliveryStatus' => $sale->deliveryStatus,
                ]);

                if (!$primaryReturn) {
                    $primaryReturn = $singleReturn;
                }
            }

            $salesReturn = $primaryReturn;

            Activity::create([
                'id' => (string) Str::uuid(),
                'type' => 'SALES_RETURN',
                'description' => "{$userName} processed Sales Return for Sale #{$saleId} (₦" . number_format($totalRefundAmount, 2) . " refund). Reason: {$reason}",
                'userId' => $userId,
                'userName' => $userName,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $salesReturn;
        });
    }
}

