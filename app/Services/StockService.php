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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockService
{
    /**
     * Get or create stock level record for a product at a warehouse.
     */
    public function getStockLevel(string $productId, int $warehouseId): StockLevel
    {
        return StockLevel::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['physical_stock' => 0, 'allocated_stock' => 0, 'min_stock_alert' => 5]
        );
    }

    /**
     * Record Stock In (e.g. from Supplier or Purchase).
     */
    public function recordStockIn(string $productId, int $warehouseId, int $quantity, ?string $supplierName, string $userId, string $userName, ?string $notes = null): StockLevel
    {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $supplierName, $userId, $userName, $notes) {
            $product = Product::findOrFail($productId);
            $stock = $this->getStockLevel($productId, $warehouseId);
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
            $totalAmount = (float) $saleData['totalAmount'];
            $paidAmount = (float) ($saleData['paidAmount'] ?? $totalAmount);
            $cashAmount = (float) ($saleData['cashAmount'] ?? 0);
            $posAmount = (float) ($saleData['posAmount'] ?? 0);
            $transferAmount = (float) ($saleData['transferAmount'] ?? 0);
            $customerId = $saleData['customerId'] ?? null;
            $customerName = $saleData['customerName'] ?? 'Walk-in Customer';

            $deliveryStatus = $isSuppliedNow ? 'DELIVERED' : 'UNSUPPLIED';
            $saleStatus = ($paidAmount >= $totalAmount) ? 'COMPLETED' : 'PARTIAL';

            $sale = Sale::create([
                'id' => $saleId,
                'customerName' => $customerName,
                'totalAmount' => $totalAmount,
                'paidAmount' => $paidAmount,
                'cashAmount' => $cashAmount,
                'posAmount' => $posAmount + $transferAmount,
                'note' => $saleData['note'] ?? null,
                'status' => $saleStatus,
                'deliveryStatus' => $deliveryStatus,
                'deliveredAt' => $isSuppliedNow ? now()->toIso8601String() : null,
                'deliveredBy' => $isSuppliedNow ? $userName : null,
                'userId' => $userId,
                'userName' => $userName,
                'createdAt' => now()->toIso8601String(),
            ]);

            // Process line items & stock impact
            foreach ($items as $item) {
                $product = Product::findOrFail($item['productId']);
                $qty = (int) $item['quantity'];
                $unitPrice = (float) $item['unitPrice'];

                SaleItem::create([
                    'saleId' => $saleId,
                    'productId' => $product->id,
                    'productName' => $product->name,
                    'quantity' => $qty,
                    'unitPrice' => $unitPrice,
                    'totalPrice' => $qty * $unitPrice,
                    'code' => $product->code,
                    'productCode' => $product->code,
                ]);

                $stock = $this->getStockLevel($product->id, $warehouseId);

                if ($isSuppliedNow) {
                    // Item leaves the physical shop immediately
                    $stock->physical_stock = max(0, $stock->physical_stock - $qty);
                    $stock->save();

                    $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                    $product->save();

                    InventoryLog::create([
                        'id' => (string) Str::uuid(),
                        'productId' => $product->id,
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
                    // Physical stock stays on ground; allocated stock is reserved
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

            // Record initial payment entry if paidAmount > 0
            if ($paidAmount > 0) {
                Payment::create([
                    'id' => (string) Str::uuid(),
                    'saleId' => $saleId,
                    'amount' => $paidAmount,
                    'method' => $cashAmount > 0 ? 'CASH' : ($posAmount > 0 ? 'POS' : 'TRANSFER'),
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
            $sale = Sale::with('items')->findOrFail($saleId);

            if ($sale->deliveryStatus === 'DELIVERED') {
                throw new \Exception('Sale has already been fully delivered/dispatched.');
            }

            foreach ($sale->items as $item) {
                $stock = $this->getStockLevel($item->productId, $warehouseId);
                $qty = (int) $item->quantity;

                // Deduct from physical stock and release allocated stock
                $stock->physical_stock = max(0, $stock->physical_stock - $qty);
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
            $transferNo = 'TRF-' . strtoupper(Str::random(6)) . '-' . date('ymd');

            $transfer = Transfer::create([
                'transfer_no' => $transferNo,
                'source_warehouse_id' => $sourceWarehouseId,
                'destination_warehouse_id' => $destWarehouseId,
                'status' => 'DISPATCHED',
                'carrier_name' => $carrierName,
                'dispatched_by' => $userName,
                'dispatched_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $product = Product::findOrFail($item['productId']);
                $qty = (int) $item['quantity'];

                TransferItem::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_code' => $product->code,
                    'dispatched_qty' => $qty,
                    'received_qty' => 0,
                    'discrepancy_qty' => 0,
                ]);

                // Deduct from source physical stock
                $stock = $this->getStockLevel($product->id, $sourceWarehouseId);
                $stock->physical_stock = max(0, $stock->physical_stock - $qty);
                $stock->save();

                $product->currentStock = StockLevel::where('product_id', $product->id)->sum('physical_stock');
                $product->save();

                InventoryLog::create([
                    'id' => (string) Str::uuid(),
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
            $transfer = Transfer::with('items')->findOrFail($transferId);

            if ($transfer->status === 'RECEIVED') {
                throw new \Exception('Transfer has already been received.');
            }

            $hasDiscrepancy = false;
            $totalDiscrepancy = 0;

            foreach ($transfer->items as $transferItem) {
                // Find counted quantity sent from form
                $countedQty = (int) ($countedItems[$transferItem->product_id] ?? $transferItem->dispatched_qty);
                $discrepancy = $transferItem->dispatched_qty - $countedQty;

                $transferItem->received_qty = $countedQty;
                $transferItem->discrepancy_qty = $discrepancy;
                $transferItem->save();

                if ($discrepancy != 0) {
                    $hasDiscrepancy = true;
                    $totalDiscrepancy += abs($discrepancy);
                }

                // Add counted physical stock to destination warehouse
                $destStock = $this->getStockLevel($transferItem->product_id, $transfer->destination_warehouse_id);
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
     * Record Customer Debt Payment (Part payment recovery).
     */
    public function recordCustomerPayment(int $customerId, double $amount, string $paymentMethod, ?string $refNo, string $userId, string $userName, ?string $notes = null): CustomerLedger
    {
        return DB::transaction(function () use ($customerId, $amount, $paymentMethod, $refNo, $userId, $userName, $notes) {
            $customer = Customer::findOrFail($customerId);
            $customer->total_debt = max(0, $customer->total_debt - $amount);
            $customer->save();

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
}
