<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InventoryLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionVoidService
{
    /**
     * Asserts that the actor is authorized to void transactions (Tenant Admin or Super Admin).
     */
    public function assertAdminPrivilege(?User $user): void
    {
        if (!$user || (!$user->isAdmin() && !$user->isTenantAdmin() && !$user->isPlatformAdmin())) {
            throw new \Illuminate\Auth\Access\AuthorizationException("🔒 Access Denied: Only Business Administrators can void/delete transactions.");
        }
    }

    /**
     * Atomically void a Sale transaction and rollback stock, debts, and reservations.
     */
    public function voidSale(string $saleId, string $reason, User $actor): array
    {
        $this->assertAdminPrivilege($actor);

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException("A valid audit reason is mandatory for voiding a transaction.");
        }

        return DB::transaction(function () use ($saleId, $reason, $actor) {
            // Guard: Check if this sale already has processed Returns & Refunds
            $existingReturns = \App\Models\SalesReturn::where('saleId', $saleId)->count();
            if ($existingReturns > 0) {
                throw new \InvalidArgumentException(
                    "Cannot void Sale #{$saleId}: This sale already has {$existingReturns} processed Return & Refund record(s). " .
                    "Voiding is strictly blocked to prevent double-restocking and ledger corruption. " .
                    "Please process any remaining balance through the Returns & Refunds screen."
                );
            }

            $sale = Sale::with(['items', 'payments'])->where('id', $saleId)->lockForUpdate()->firstOrFail();

            $warehouseId = (int) $sale->warehouse_id;
            $restoredItems = [];

            // 1. Rollback inventory and reservations
            foreach ($sale->items as $item) {
                $qty = (int) $item->quantity;
                $productId = $item->productId;
                $product = Product::find($productId);

                $stock = StockLevel::where('warehouse_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                $reservation = StockReservation::where('sale_id', $saleId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                $isDelivered = in_array(strtoupper($sale->deliveryStatus ?? ''), ['DELIVERED', 'SUPPLIED']);

                // Exact physical vs allocated breakdown:
                if ($reservation) {
                    $physicalRestored = (int) $reservation->fulfilled_qty;
                    $allocatedCancelled = (int) $reservation->outstanding_qty;
                } else {
                    $physicalRestored = $isDelivered ? $qty : 0;
                    $allocatedCancelled = $isDelivered ? 0 : $qty;
                }

                if ($stock) {
                    if ($physicalRestored > 0) {
                        $stock->physical_stock += $physicalRestored;
                    }
                    if ($allocatedCancelled > 0) {
                        $stock->allocated_stock = max(0, $stock->allocated_stock - $allocatedCancelled);
                    }
                    $stock->save();

                    if ($product) {
                        $product->currentStock = StockLevel::where('product_id', $productId)->sum('physical_stock');
                        $product->save();
                    }
                }

                // Clean up stock reservations
                if ($reservation) {
                    $reservation->update([
                        'status' => 'CANCELLED',
                        'cancelled_qty' => $reservation->cancelled_qty + $allocatedCancelled,
                        'notes' => trim(($reservation->notes ?? '') . " [Sale #{$saleId} voided by Admin {$actor->name}: {$reason}]"),
                    ]);
                } else {
                    StockReservation::where('sale_id', $saleId)
                        ->where('product_id', $productId)
                        ->update([
                            'status' => 'CANCELLED',
                            'notes' => "Sale #{$saleId} voided by Admin: {$reason}",
                        ]);
                }

                $restoredItems[] = [
                    'sku' => $product?->code ?? $item->code ?? $item->productCode ?? 'N/A',
                    'name' => $product?->name ?? $item->productName,
                    'product' => $product?->name ?? $item->productName,
                    'quantity' => $physicalRestored > 0 ? $physicalRestored : $allocatedCancelled,
                    'is_physical' => ($physicalRestored > 0),
                ];
            }

            // Option A: Clean Stock Out Tab & Inventory Reconciliation
            // Delete original SALE and DISPATCH outflow logs so the voided sale completely disappears from the Stock Out tab
            InventoryLog::where(function ($q) use ($saleId) {
                $q->where('description', 'like', "Sale #{$saleId}%")
                  ->orWhere('description', 'like', "Dispatch fulfilled for Sale #{$saleId}%")
                  ->orWhere('description', 'like', "%Sale #{$saleId}%");
            })->whereIn('type', ['SALE', 'SALE_RESERVED', 'DISPATCH_FULFILLED'])->delete();

            // 2. Clean Customer Debt Ledger: Reverse customer debt & eliminate ghost ledger entries
            $customer = null;
            if ($sale->customerId) {
                $customer = Customer::where('id', $sale->customerId)->lockForUpdate()->first();
            }

            $unpaidAmount = max(0.0, (float) $sale->totalAmount - (float) $sale->paidAmount);
            if ($customer && $unpaidAmount > 0) {
                $customer->total_debt = max(0.0, (float) $customer->total_debt - $unpaidAmount);
                $customer->save();

                // Option A: Clean Customer Statement - remove the INVOICE debt entry for this sale so customer ledger stays clean
                CustomerLedger::where('sale_id', $saleId)->delete();
            }

            // 3. Record Security & Anti-Theft Activity Log
            $metadata = [
                'sale_id' => $saleId,
                'total_amount' => $sale->totalAmount,
                'paid_amount' => $sale->paidAmount,
                'cash_amount' => $sale->cashAmount,
                'pos_amount' => $sale->posAmount,
                'transfer_amount' => $sale->transferAmount,
                'customer_name' => $sale->customerName,
                'items' => $restoredItems,
                'void_reason' => $reason,
                'voided_by_user_id' => $actor->id,
                'voided_by_name' => $actor->name,
                'voided_by_role' => $actor->role,
            ];

            Activity::recordSecurityEvent(
                'TRANSACTION_VOIDED',
                "Admin {$actor->name} ({$actor->role}) voided Sale #{$saleId} (₦" . number_format($sale->totalAmount, 2) . "). Reason: {$reason}",
                $metadata,
                $actor
            );

            // 4. Delete payments, items, and the sale record
            Payment::where('saleId', $saleId)->delete();
            \App\Models\SaleItem::where('saleId', $saleId)->delete();
            $sale->delete();

            return [
                'success' => true,
                'message' => "Sale #{$saleId} successfully voided. Restocked " . count($restoredItems) . " items.",
            ];
        });
    }

    /**
     * Atomically void a Stock In log and reverse shelf count.
     */
    public function voidStockIn(string $logId, string $reason, User $actor): array
    {
        $this->assertAdminPrivilege($actor);

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException("A valid audit reason is mandatory for voiding Stock In.");
        }

        return DB::transaction(function () use ($logId, $reason, $actor) {
            $log = InventoryLog::where('id', $logId)->lockForUpdate()->firstOrFail();

            if ($log->type !== 'STOCK_IN' && $log->type !== 'PURCHASE') {
                throw new \InvalidArgumentException("Entry #{$logId} is not a Stock In entry.");
            }

            $warehouseId = (int) $log->warehouse_id;
            $productId = $log->productId;
            $qty = abs((int) $log->quantity);

            $stock = StockLevel::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->physical_stock < $qty) {
                $available = $stock ? $stock->physical_stock : 0;
                throw new \InvalidArgumentException("Cannot void Stock In: Physical stock on ground ({$available}) is lower than the void amount ({$qty}). Products may already have been sold.");
            }

            $stock->physical_stock -= $qty;
            $stock->save();

            $product = Product::find($productId);
            if ($product) {
                $product->currentStock = StockLevel::where('product_id', $productId)->sum('physical_stock');
                $product->save();
            }

            Activity::recordSecurityEvent(
                'STOCK_IN_VOIDED',
                "Admin {$actor->name} voided Stock In entry #{$logId} (-{$qty} units of {$log->productName}). Reason: {$reason}",
                [
                    'log_id'            => $logId,
                    'product_id'        => $productId,
                    'sku'               => $product?->code ?? $log->productCode ?? 'N/A',
                    'product_code'      => $product?->code ?? $log->productCode ?? 'N/A',
                    'product_name'      => $log->productName,
                    'quantity_reversed' => $qty,
                    'warehouse_id'      => $warehouseId,
                    'reason'            => $reason,
                ],
                $actor
            );

            $log->delete();

            return [
                'success' => true,
                'message' => "Stock In entry successfully voided. Deducted {$qty} units from inventory.",
            ];
        });
    }

    /**
     * Atomically void a Stock Out log / Stock Adjustment.
     */
    public function voidStockOutOrAdjustment(string $adjustmentId, string $reason, User $actor): array
    {
        $this->assertAdminPrivilege($actor);

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException("A valid audit reason is mandatory.");
        }

        return DB::transaction(function () use ($adjustmentId, $reason, $actor) {
            $adj = StockAdjustment::where('id', $adjustmentId)->lockForUpdate()->first();

            if ($adj) {
                $qty = (int) $adj->quantity;
                $warehouseId = (int) $adj->warehouse_id;
                $productId = $adj->product_id;

                $stock = StockLevel::where('warehouse_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->physical_stock += $qty;
                    $stock->save();
                }

                $product = Product::find($productId);
                if ($product) {
                    $product->currentStock = StockLevel::where('product_id', $productId)->sum('physical_stock');
                    $product->save();
                }

                Activity::recordSecurityEvent(
                    'STOCK_ADJUSTMENT_VOIDED',
                    "Admin {$actor->name} voided Stock Adjustment #{$adj->id} (+{$qty} units restored). Reason: {$reason}",
                    [
                        'adjustment_id' => $adj->id,
                        'product_id'    => $productId,
                        'sku'           => $product?->code ?? 'N/A',
                        'product_code'  => $product?->code ?? 'N/A',
                        'product_name'  => $product?->name ?? 'Adjusted Product',
                        'qty'           => $qty,
                        'reason'        => $reason,
                    ],
                    $actor
                );

                // Clean up corresponding write-off inventory log so damage/loss analytics reset accurately
                InventoryLog::where('productId', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('type', 'like', 'STOCK_ADJUSTMENT%')
                    ->where('quantity', -$qty)
                    ->take(1)
                    ->delete();

                $adj->delete();

                return [
                    'success' => true,
                    'message' => "Stock adjustment voided and {$qty} units restored to inventory.",
                ];
            }

            // Otherwise check InventoryLog for Stock Out
            $log = InventoryLog::where('id', $adjustmentId)->lockForUpdate()->firstOrFail();
            $qty = abs((int) $log->quantity);
            $warehouseId = (int) $log->warehouse_id;
            $productId = $log->productId;

            $stock = StockLevel::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $stock->physical_stock += $qty;
                $stock->save();
            }

            $product = Product::find($productId);
            if ($product) {
                $product->currentStock = StockLevel::where('product_id', $productId)->sum('physical_stock');
                $product->save();
            }

            Activity::recordSecurityEvent(
                'STOCK_OUT_VOIDED',
                "Admin {$actor->name} voided Stock Out log #{$log->id} (+{$qty} units restored). Reason: {$reason}",
                [
                    'log_id'       => $log->id,
                    'product_id'   => $productId,
                    'sku'          => $product?->code ?? $log->productCode ?? 'N/A',
                    'product_code' => $product?->code ?? $log->productCode ?? 'N/A',
                    'product_name' => $product?->name ?? $log->productName ?? 'Dispatched Item',
                    'qty'          => $qty,
                    'reason'       => $reason,
                ],
                $actor
            );

            $log->delete();

            return [
                'success' => true,
                'message' => "Stock out entry voided and {$qty} units restored to inventory.",
            ];
        });
    }
}
