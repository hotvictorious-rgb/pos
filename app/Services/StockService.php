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
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockService
{
    /**
     * Get or create stock level record for a product at a warehouse with optional row-level locking.
     */
    public function getStockLevel(string $productId, int $warehouseId, bool $lockForUpdate = false): StockLevel
    {
        $query = StockLevel::where('product_id', $productId)->where('warehouse_id', $warehouseId);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        $stock = $query->first();

        if (!$stock) {
            $stock = StockLevel::create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'physical_stock' => 0,
                'allocated_stock' => 0,
                'min_stock_alert' => 5,
            ]);
            if ($lockForUpdate) {
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
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $supplierName, $userId, $userName, $notes) {
            if ($quantity <= 0) {
                throw new \InvalidArgumentException("Stock in quantity must be at least 1 unit.");
            }

            $product = Product::findOrFail($productId);
            $stock = $this->getStockLevel($productId, $warehouseId, true);
            $stock->physical_stock += $quantity;
            $stock->save();

            // Also update total currentStock on product
            $product->currentStock = StockLevel::where('product_id', $productId)->sum('physical_stock');
            $product->save();

            InventoryLog::create([
                'id' => (string) Str::uuid(),
                'productId' => $productId,
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

            // Server-authoritative line items and pricing calculation
            $serverTotal = 0;
            $validatedItems = [];
            foreach ($items as $item) {
                $productId = $item['productId'] ?? $item['product_id'] ?? null;
                if (!$productId) {
                    throw new \InvalidArgumentException("Invalid line item: Product ID missing.");
                }
                $product = Product::findOrFail($productId);
                $qty = (int) ($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    throw new \InvalidArgumentException("Quantity for product '{$product->name}' must be at least 1 unit.");
                }

                // Server is strictly authoritative for retail catalog pricing; client prices are ignored
                $saleType = $saleData['sale_type'] ?? 'RETAIL';
                if ($saleType === 'RETAIL' || !isset($item['unitPrice'])) {
                    $unitPrice = (float) $product->unitPrice;
                } else {
                    $unitPrice = (float) $item['unitPrice'];
                }
                if ($unitPrice < 0) {
                    throw new \InvalidArgumentException("Unit price for product '{$product->name}' cannot be negative.");
                }

                $lineTotal = $qty * $unitPrice;
                $serverTotal += $lineTotal;

                $validatedItems[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unitPrice' => $unitPrice,
                    'totalPrice' => $lineTotal,
                ];
            }

            // Server is strictly authoritative for totalAmount
            $totalAmount = (float) $serverTotal;

            // Tender accounting & reconciliation
            $cashAmount = (float) ($saleData['cashAmount'] ?? 0);
            $posAmount = (float) ($saleData['posAmount'] ?? 0);
            $transferAmount = (float) ($saleData['transferAmount'] ?? 0);

            if ($cashAmount < 0 || $posAmount < 0 || $transferAmount < 0) {
                throw new \InvalidArgumentException("Payment tender amounts cannot be negative.");
            }

            // Total tender presented
            $tenderedTotal = $cashAmount + $posAmount + $transferAmount;

            // If no explicit split tender, fallback to paidAmount as cash tender
            if ($tenderedTotal == 0 && isset($saleData['paidAmount'])) {
                $tenderedTotal = (float) $saleData['paidAmount'];
                $cashAmount = $tenderedTotal;
            }

            // Electronic payment validation: Cards & Transfers cannot exceed total bill
            if (($posAmount + $transferAmount) > $totalAmount) {
                throw new \InvalidArgumentException(
                    "Electronic payments (POS & Transfer: ₦" . number_format($posAmount + $transferAmount, 2) . ") cannot exceed sale total amount of ₦" . number_format($totalAmount, 2) . ". Cash change cannot be disbursed from card/transfer overpayment."
                );
            }

            // Change calculation: Cash tendered beyond what is required to pay the bill
            $changeAmount = 0.0;
            if ($tenderedTotal > $totalAmount) {
                $changeAmount = round($tenderedTotal - $totalAmount, 2);
                if ($changeAmount > $cashAmount) {
                    throw new \InvalidArgumentException("Change amount cannot exceed cash tendered.");
                }
            }

            // Net payment applied to the sale: CANNOT exceed totalAmount!
            $paidAmount = min($totalAmount, max(0.0, round($tenderedTotal - $changeAmount, 2)));

            // If a partial payment was explicitly declared by client (credit sale where customer tendered less than total):
            if (isset($saleData['paidAmount']) && (float)$saleData['paidAmount'] < $totalAmount && (float)$saleData['paidAmount'] <= $tenderedTotal) {
                $declaredPaid = (float) $saleData['paidAmount'];
                $paidAmount = min($totalAmount, max(0.0, round($declaredPaid, 2)));
                $changeAmount = max(0.0, round($tenderedTotal - $paidAmount, 2));
            }

            // Net cash kept in drawer
            $netCashKept = max(0.0, round($cashAmount - $changeAmount, 2));

            $customerId = $saleData['customerId'] ?? null;
            $customerName = $saleData['customerName'] ?? 'Walk-in Customer';

            $deliveryStatus = $isSuppliedNow ? 'DELIVERED' : 'UNSUPPLIED';
            $saleStatus = ($paidAmount >= $totalAmount) ? 'COMPLETED' : 'PARTIAL';
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
                'transferAmount' => $transferAmount,
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

            // Process line items & stock impact
            foreach ($validatedItems as $vItem) {
                $product = $vItem['product'];
                $qty = $vItem['quantity'];
                $unitPrice = $vItem['unitPrice'];
                $lineTotal = $vItem['totalPrice'];

                SaleItem::create([
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

                $stock = $this->getStockLevel($product->id, $warehouseId, true);
                $availableStock = (int) ($stock->physical_stock - $stock->allocated_stock);

                if ($isSuppliedNow) {
                    if ($availableStock < $qty) {
                        throw new InsufficientStockException(
                            "Cannot complete sale: Insufficient available physical stock for '{$product->name}' ({$product->code}) at branch #{$warehouseId}. Physical: {$stock->physical_stock}, Allocated: {$stock->allocated_stock}, Available: {$availableStock}, Requested: {$qty}",
                            $product->code,
                            $product->name,
                            $warehouseId,
                            $availableStock,
                            $qty
                        );
                    }
                    // Item leaves the physical shop immediately
                    $stock->physical_stock = $stock->physical_stock - $qty;
                    $stock->save();

                    $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                    $product->save();

                    $logPrefix = ($saleType === 'WHOLESALE_DISPATCH') ? 'Wholesale Dispatch' : 'Sale';
                    InventoryLog::create([
                        'id' => (string) Str::uuid(),
                        'productId' => $product->id,
                        'type' => 'SALE',
                        'quantity' => -$qty,
                        'userId' => $userId,
                        'userName' => $userName,
                        'productCode' => $product->code,
                        'productName' => $product->name,
                        'description' => "{$logPrefix} #{$saleId} (Supplied to {$customerName})",
                        'timestamp' => now()->toIso8601String(),
                    ]);
                } else {
                    // Unsupplied sale: Physical stock stays on ground; allocated stock is reserved
                    // Invariant: allocated_stock <= physical_stock (cannot reserve more than available unallocated stock)
                    if ($availableStock < $qty) {
                        throw new InsufficientStockException(
                            "Cannot reserve unsupplied sale: Insufficient available physical stock for '{$product->name}' ({$product->code}) at branch #{$warehouseId}. Physical: {$stock->physical_stock}, Already Allocated: {$stock->allocated_stock}, Available to reserve: {$availableStock}, Requested: {$qty}",
                            $product->code,
                            $product->name,
                            $warehouseId,
                            $availableStock,
                            $qty
                        );
                    }

                    $stock->allocated_stock += $qty;
                    $stock->save();

                    InventoryLog::create([
                        'id' => (string) Str::uuid(),
                        'productId' => $product->id,
                        'type' => 'SALE_RESERVED',
                        'quantity' => 0, // Physical stock hasn't changed yet
                        'userId' => $userId,
                        'userName' => $userName,
                        'productCode' => $product->code,
                        'productName' => $product->name,
                        'description' => "Sale #{$saleId} (Unsupplied - Goods on ground reserved for {$customerName})",
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

            // 3. Bank Transfer Payment
            if ($transferAmount > 0) {
                Payment::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'saleId' => $saleId,
                    'amount' => $transferAmount,
                    'method' => 'TRANSFER',
                    'timestamp' => now()->toIso8601String(),
                    'recordedBy' => $userName,
                    'createdAt' => now()->toIso8601String(),
                ]);
            }

            // Fallback for legacy calls or tests where only paidAmount is provided without breakdown
            if ($paidAmount > 0 && $netCashKept == 0 && $posAmount == 0 && $transferAmount == 0) {
                Payment::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'saleId' => $saleId,
                    'amount' => $paidAmount,
                    'method' => 'CASH',
                    'timestamp' => now()->toIso8601String(),
                    'recordedBy' => $userName,
                    'createdAt' => now()->toIso8601String(),
                ]);
            }

            // Handle Customer Debt Ledger for Part Payments
            $remainingDebt = max(0, $totalAmount - $paidAmount);
            if ($remainingDebt > 0 || $customerId) {
                $customer = null;
                if ($customerId) {
                    $customer = Customer::find($customerId);
                }
                if (!$customer && !empty($customerName) && $customerName !== 'Walk-in Customer') {
                    $customer = Customer::firstOrCreate(['name' => $customerName], ['phone' => $saleData['customerPhone'] ?? null]);
                }

                if ($customer) {
                    $customer->total_debt += $remainingDebt;
                    $customer->save();

                    CustomerLedger::create([
                        'customer_id' => $customer->id,
                        'sale_id' => $saleId,
                        'type' => 'INVOICE',
                        'amount' => $totalAmount,
                        'balance_after' => $customer->total_debt,
                        'payment_method' => 'DEBT_ISSUED',
                        'reference_no' => $saleId,
                        'recorded_by' => $userName,
                        'notes' => "Invoice created. Paid: ₦{$paidAmount}, Debt balance: ₦{$remainingDebt}",
                    ]);
                }
            }

            return $sale;
        });
    }

    /**
     * Dispatch Unsupplied Goods (Customer picks up previously purchased goods).
     */
    public function dispatchUnsuppliedSale(string $saleId, int $warehouseId, string $userId, string $userName): Sale
    {
        return DB::transaction(function () use ($saleId, $warehouseId, $userId, $userName) {
            $sale = Sale::with('items')->where('id', $saleId)->lockForUpdate()->firstOrFail();

            // Branch isolation: Goods must be dispatched from the branch where they were originally sold/reserved
            if (!empty($sale->warehouse_id) && (int) $sale->warehouse_id !== (int) $warehouseId) {
                throw new \InvalidArgumentException("Cross-branch dispatch rejected: Sale #{$saleId} was reserved at Branch #{$sale->warehouse_id} and cannot be dispatched from Branch #{$warehouseId}.");
            }

            if ($sale->deliveryStatus === 'DELIVERED') {
                throw new \Exception('Sale has already been fully delivered/dispatched.');
            }

            foreach ($sale->items as $item) {
                $stock = $this->getStockLevel($item->productId, $warehouseId, true);
                $qty = (int) $item->quantity;

                if ($stock->physical_stock < $qty) {
                    $product = Product::find($item->productId);
                    $pName = $product ? $product->name : $item->productName;
                    $pCode = $product ? $product->code : $item->code;
                    throw new InsufficientStockException(
                        "Cannot fulfill dispatch for Sale #{$saleId}: Insufficient physical stock for '{$pName}' ({$pCode}) at branch #{$warehouseId}. Available: {$stock->physical_stock}, Requested: {$qty}",
                        $pCode,
                        $pName,
                        $warehouseId,
                        $stock->physical_stock,
                        $qty
                    );
                }

                // Deduct from physical stock and release allocated stock
                $stock->physical_stock = $stock->physical_stock - $qty;
                $stock->allocated_stock = max(0, $stock->allocated_stock - $qty);
                $stock->save();

                $product = Product::find($item->productId);
                if ($product) {
                    $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                    $product->save();
                }

                InventoryLog::create([
                    'id' => (string) Str::uuid(),
                    'productId' => $item->productId,
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
     * Anti-Theft Step 1: Initiate Inter-Location Transfer (Goods Dispatched from Source).
     */
    public function initiateTransfer(int $sourceWarehouseId, int $destWarehouseId, array $items, ?string $carrierName, string $userId, string $userName, ?string $notes = null): Transfer
    {
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

                // Row-level lock on source physical stock to prevent race-condition overdraft
                $stock = $this->getStockLevel($product->id, $sourceWarehouseId, true);
                if ($stock->physical_stock < $qty) {
                    throw new InsufficientStockException(
                        "Cannot dispatch transfer: Insufficient physical stock for '{$product->name}' ({$product->code}) at origin branch #{$sourceWarehouseId}. Available: {$stock->physical_stock}, Requested: {$qty}",
                        $product->code,
                        $product->name,
                        $sourceWarehouseId,
                        $stock->physical_stock,
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
        return DB::transaction(function () use ($transferId, $countedItems, $userId, $userName, $discrepancyNotes) {
            $transfer = Transfer::with('items')->where('id', $transferId)->lockForUpdate()->firstOrFail();

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
                $destStock = $this->getStockLevel($transferItem->product_id, $transfer->destination_warehouse_id, true);
                $destStock->physical_stock += $countedQty;
                $destStock->save();

                $product = Product::find($transferItem->product_id);
                if ($product) {
                    $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                    $product->save();
                }

                InventoryLog::create([
                    'id' => (string) Str::uuid(),
                    'productId' => $transferItem->product_id,
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
        return DB::transaction(function () use ($transferId, $userId, $userName, $reason) {
            $transfer = Transfer::with(['items', 'source', 'destination'])->where('id', $transferId)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'DISPATCHED') {
                throw new \Exception("Transfer #{$transfer->transfer_no} cannot be recalled: Current status is '{$transfer->status}' (only in-transit 'DISPATCHED' transfers can be recalled).");
            }

            foreach ($transfer->items as $item) {
                $qty = (int) $item->dispatched_qty;

                // Restore stock back to source warehouse with row locking
                $stock = $this->getStockLevel($item->product_id, $transfer->source_warehouse_id, true);
                $stock->physical_stock += $qty;
                $stock->save();

                $product = Product::find($item->product_id);
                if ($product) {
                    $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                    $product->save();
                }

                InventoryLog::create([
                    'id' => (string) Str::uuid(),
                    'productId' => $item->product_id,
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
     */
    public function recordCustomerPayment(int $customerId, float $amount, string $paymentMethod, ?string $refNo, string $userId, string $userName, ?string $notes = null): CustomerLedger
    {
        return DB::transaction(function () use ($customerId, $amount, $paymentMethod, $refNo, $userId, $userName, $notes) {
            if ($amount <= 0) {
                throw new \InvalidArgumentException("Payment amount must be greater than zero.");
            }

            $customer = Customer::where('id', $customerId)->lockForUpdate()->firstOrFail();

            if ($amount > (float) $customer->total_debt) {
                throw new \InvalidArgumentException(
                    "Payment amount (₦" . number_format($amount, 2) . ") exceeds customer's total outstanding debt (₦" . number_format($customer->total_debt, 2) . ")."
                );
            }

            $customer->total_debt = max(0, $customer->total_debt - $amount);
            $customer->save();

            // Reconcile customer's oldest partial sales
            $remainingPayment = $amount;
            $partialSales = Sale::where('customerId', $customerId)
                ->where('status', 'PARTIAL')
                ->orderBy('createdAt', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($partialSales as $pSale) {
                if ($remainingPayment <= 0) break;
                $unpaid = max(0, (float) $pSale->totalAmount - (float) $pSale->paidAmount);
                $alloc = min($remainingPayment, $unpaid);
                $pSale->paidAmount += $alloc;
                if ($pSale->paidAmount >= $pSale->totalAmount) {
                    $pSale->status = 'COMPLETED';
                }
                $pSale->save();
                $remainingPayment -= $alloc;
            }

            $ledger = CustomerLedger::create([
                'customer_id' => $customer->id,
                'type' => 'PAYMENT',
                'amount' => $amount,
                'balance_after' => $customer->total_debt,
                'payment_method' => $paymentMethod,
                'reference_no' => $refNo,
                'recorded_by' => $userName,
                'notes' => $notes ?? "Part-payment of ₦" . number_format($amount, 2) . " received. New balance: ₦" . number_format($customer->total_debt, 2),
            ]);

            Activity::create([
                'id' => (string) Str::uuid(),
                'type' => 'DEBT_PAYMENT',
                'description' => "{$userName} recorded debt payment of ₦" . number_format($amount, 2) . " for {$customer->name}",
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
        return DB::transaction(function () use ($productId, $warehouseId, $type, $quantity, $reason, $userId, $userName) {
            if ($quantity <= 0) {
                throw new \InvalidArgumentException("Write-off quantity must be at least 1 unit.");
            }

            $product = Product::findOrFail($productId);
            $stock = $this->getStockLevel($productId, $warehouseId, true);

            if ($stock->physical_stock < $quantity) {
                throw new InsufficientStockException(
                    "Cannot record stock write-off: Insufficient physical stock for '{$product->name}' ({$product->code}) at branch #{$warehouseId}. Available: {$stock->physical_stock}, Requested write-off: {$quantity}",
                    $product->code,
                    $product->name,
                    $warehouseId,
                    $stock->physical_stock,
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
                'productId' => $productId,
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
        return DB::transaction(function () use ($saleId, $returnItems, $warehouseId, $refundMethod, $reason, $userId, $userName) {
            if (empty($returnItems)) {
                throw new \InvalidArgumentException("No items specified for return.");
            }

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

                // Check already returned quantity for this specific product in this sale
                $previouslyReturnedQty = (int) \App\Models\InventoryLog::where('type', 'SALES_RETURN')
                    ->where('productId', $productId)
                    ->where('description', 'like', "%Sale #{$saleId}%")
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
                $stock = $this->getStockLevel($product->id, $warehouseId, true);
                if ($sale->deliveryStatus === 'UNSUPPLIED') {
                    // Goods were reserved on ground but never physically taken; release allocation
                    $stock->allocated_stock = max(0, $stock->allocated_stock - $qty);
                    $stock->save();
                } else {
                    // Goods were physically delivered; restore physical stock to shelves
                    $stock->physical_stock += $qty;
                    $stock->save();
                }

                $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                $product->save();

                InventoryLog::create([
                    'id' => (string) Str::uuid(),
                    'productId' => $product->id,
                    'type' => 'SALES_RETURN',
                    'quantity' => $qty,
                    'userId' => $userId,
                    'userName' => $userName,
                    'productCode' => $product->code,
                    'productName' => $product->name,
                    'description' => "Customer Return for Sale #{$saleId} ({$qty} units restored to shelf count). Reason: {$reason}",
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            // Financial integrity: Cash refund cannot exceed actual money customer paid!
            if ($refundMethod === 'CASH_REFUND') {
                $maxCashRefundable = max(0, (float) $sale->paidAmount - $priorRefundedTotal);
                if ($totalRefundAmount > $maxCashRefundable) {
                    throw new \InvalidArgumentException(
                        "Cannot issue cash refund of ₦" . number_format($totalRefundAmount, 2) . ". Maximum refundable cash for Sale #{$saleId} based on actual payments made is ₦" . number_format($maxCashRefundable, 2) . ". Use DEBT_REDUCTION for unpaid/credit balance."
                    );
                }
            }

            $salesReturn = \App\Models\SalesReturn::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => session('tenant_id') ?? $sale->tenant_id ?? null,
                'saleId' => $saleId,
                'customerName' => $sale->customerName,
                'code' => 'RET-' . strtoupper(Str::random(6)),
                'productId' => $firstProduct ? $firstProduct->id : 'MULTI',
                'productName' => $firstProduct ? $firstProduct->name : 'Returned Items',
                'quantity' => array_sum(array_column($returnItems, 'quantity')),
                'refundAmount' => $totalRefundAmount,
                'reason' => $reason,
                'createdAt' => now()->toIso8601String(),
                'userId' => $userId,
                'userName' => $userName,
                'wasDelivered' => true,
            ]);

            // Adjust Customer Debt Ledger if refund method is DEBT_REDUCTION
            if ($refundMethod === 'DEBT_REDUCTION' && $sale->customerName) {
                $customer = Customer::where('name', $sale->customerName)->lockForUpdate()->first();
                if ($customer) {
                    $customer->total_debt = max(0, $customer->total_debt - $totalRefundAmount);
                    $customer->save();

                    CustomerLedger::create([
                        'customer_id' => $customer->id,
                        'sale_id' => $saleId,
                        'type' => 'RETURN_CREDIT',
                        'amount' => $totalRefundAmount,
                        'balance_after' => $customer->total_debt,
                        'payment_method' => 'RETURN_CREDIT',
                        'reference_no' => $salesReturn->code,
                        'recorded_by' => $userName,
                        'notes' => "Debt reduced by ₦" . number_format($totalRefundAmount, 2) . " due to Sales Return #{$salesReturn->code}",
                    ]);
                }
            }

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

