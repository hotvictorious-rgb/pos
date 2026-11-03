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
use App\Services\Accounting\AccountingReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TransactionVoidService
{
    /**
     * Asserts that the actor is authorized to void/reverse transactions (Tenant Admin or Super Admin).
     */
    public function assertAdminPrivilege(?User $user): void
    {
        if (!$user || (!$user->isAdmin() && !$user->isTenantAdmin() && !$user->isPlatformAdmin())) {
            throw new \Illuminate\Auth\Access\AuthorizationException("🔒 Access Denied: Only Business Administrators can reverse transactions.");
        }
    }

    /**
     * Asserts that an actor has authority over the target warehouse/shop.
     */
    public function assertBranchAuthority(int $warehouseId, ?User $actor): void
    {
        if (!$actor) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Unauthenticated.");
        }

        // If actor is assigned to a specific branch and is not a global super admin without branch constraint:
        if (!empty($actor->warehouse_id) && (int) $actor->warehouse_id !== $warehouseId && $actor->role !== 'super_admin') {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                "🔒 Cross-branch violation: You are assigned to Branch #{$actor->warehouse_id} and cannot reverse transactions belonging to Branch #{$warehouseId}."
            );
        }
    }

    /**
     * Atomically reverses a Sale transaction without hard-deleting records.
     * Preserves receipt sequence, creates symmetrical contra-entries, and enforces branch isolation.
     */
    public function voidSale(string $saleId, string $reason, User $actor): array
    {
        $this->assertAdminPrivilege($actor);

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException("A valid audit reason is mandatory for reversing a transaction.");
        }

        return DB::transaction(function () use ($saleId, $reason, $actor) {
            $sale = Sale::with(['items', 'payments'])->where('id', $saleId)->lockForUpdate()->firstOrFail();

            // Guard: Check if already voided or cancelled
            if (in_array(strtoupper($sale->status ?? ''), ['VOIDED', 'CANCELLED'], true)) {
                throw new \InvalidArgumentException("Sale #{$saleId} has already been {$sale->status}.");
            }

            $warehouseId = (int) $sale->warehouse_id;
            $this->assertBranchAuthority($warehouseId, $actor);

            // Tenant boundary assertion
            if (config('saas.enabled')) {
                $activeTenantId = $actor->tenant_id ?? session('tenant_id');
                if ($activeTenantId && $sale->tenant_id && $sale->tenant_id !== $activeTenantId) {
                    throw new \Illuminate\Auth\Access\AuthorizationException("Tenant boundary violation: Sale does not belong to active tenant.");
                }
            }

            // Guard 1: Sales with processed Returns & Refunds cannot be voided directly
            $existingReturns = \App\Models\SalesReturn::where('saleId', $saleId)->count();
            if ($existingReturns > 0) {
                throw new \InvalidArgumentException(
                    "Cannot reverse Sale #{$saleId}: This sale already has {$existingReturns} processed Return & Refund record(s). " .
                    "Reversal is strictly blocked to prevent double-restocking and ledger corruption. " .
                    "Please process any remaining balance through the Returns & Refunds screen."
                );
            }

            // Guard 2: Sales that received subsequent debt recovery payments
            $debtRecoveryPayments = Payment::where('saleId', $saleId)
                ->where('amount', '>', 0)
                ->where(function ($q) {
                    $q->where('recordedBy', 'like', '%[DEBT_RECOVERY]%')
                      ->orWhere('method', 'like', '%DEBT%');
                })
                ->count();
            if ($debtRecoveryPayments > 0) {
                throw new \InvalidArgumentException(
                    "Cannot reverse Sale #{$saleId}: Subsequent debt recovery payment(s) have been credited to this invoice. " .
                    "Direct reversal would cause customer ledger and cash drawer corruption. " .
                    "Please process an item return or refund via the Returns screen."
                );
            }

            // Guard 3: Partially-fulfilled unsupplied orders
            $reservations = StockReservation::where('sale_id', $saleId)->lockForUpdate()->get();
            $partiallyFulfilled = $reservations->filter(function ($r) {
                return (int) $r->fulfilled_qty > 0 && (int) $r->outstanding_qty > 0;
            })->count();
            if ($partiallyFulfilled > 0) {
                throw new \InvalidArgumentException(
                    "Cannot reverse Sale #{$saleId}: This unsupplied order is PARTIALLY FULFILLED. Some items have already been collected by the customer while others remain pending. " .
                    "Please cancel remaining uncollected items via Stock Reservations, and process physical returns for already-delivered items."
                );
            }

            $restoredItems = [];
            $tenantId = $sale->tenant_id ?? (session('tenant_id') ?? $actor->tenant_id);

            // 1. Rollback Inventory & Stock Reservations (Symmetrical Contra-Logs)
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

                $isDelivered = in_array(strtoupper($sale->deliveryStatus ?? ''), ['DELIVERED', 'SUPPLIED'], true);

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
                        'notes' => trim(($reservation->notes ?? '') . " [Sale #{$saleId} reversed by Admin {$actor->name}: {$reason}]"),
                    ]);
                } else {
                    StockReservation::where('sale_id', $saleId)
                        ->where('product_id', $productId)
                        ->update([
                            'status' => 'CANCELLED',
                            'notes' => "Sale #{$saleId} reversed by Admin {$actor->name}: {$reason}",
                        ]);
                }

                // Symmetrical Inflow Inventory Log (No hard-deletes of history!)
                if ($physicalRestored > 0) {
                    InventoryLog::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'productId' => $productId,
                        'warehouse_id' => $warehouseId,
                        'type' => 'SALE_REVERSAL',
                        'quantity' => $physicalRestored,
                        'userId' => $actor->id,
                        'userName' => $actor->name,
                        'productCode' => $product?->code ?? $item->code ?? $item->productCode ?? 'N/A',
                        'productName' => $product?->name ?? $item->productName,
                        'description' => "Reversal for voided Sale #{$saleId} (Restocked {$physicalRestored} units to shelf). Reason: {$reason}",
                        'timestamp' => now()->toIso8601String(),
                    ]);
                } elseif ($allocatedCancelled > 0) {
                    InventoryLog::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'productId' => $productId,
                        'warehouse_id' => $warehouseId,
                        'type' => 'SALE_RESERVED_CANCELLED',
                        'quantity' => 0,
                        'userId' => $actor->id,
                        'userName' => $actor->name,
                        'productCode' => $product?->code ?? $item->code ?? $item->productCode ?? 'N/A',
                        'productName' => $product?->name ?? $item->productName,
                        'description' => "Reversal for unsupplied Sale #{$saleId} (Released {$allocatedCancelled} reserved units, zero physical change). Reason: {$reason}",
                        'timestamp' => now()->toIso8601String(),
                    ]);
                }

                $restoredItems[] = [
                    'sku' => $product?->code ?? $item->code ?? $item->productCode ?? 'N/A',
                    'name' => $product?->name ?? $item->productName,
                    'quantity' => $physicalRestored > 0 ? $physicalRestored : $allocatedCancelled,
                    'is_physical' => ($physicalRestored > 0),
                ];
            }

            // 2. Customer Debt Rollback (Symmetrical Contra-Ledger Entry)
            $customer = null;
            if ($sale->customerId) {
                $customer = Customer::where('id', $sale->customerId)->lockForUpdate()->first();
            }

            $unpaidAmount = max(0.0, (float) $sale->totalAmount - (float) $sale->paidAmount);
            if ($customer && $unpaidAmount > 0) {
                $currentDebtKobo = AccountingReportService::toKobo($customer->total_debt);
                $reductionKobo = AccountingReportService::toKobo($unpaidAmount);
                $customer->total_debt = AccountingReportService::toNaira(max(0, $currentDebtKobo - $reductionKobo));
                $customer->save();

                // Post immutable contra-entry in CustomerLedger
                CustomerLedger::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'sale_id' => $saleId,
                    'warehouse_id' => $warehouseId,
                    'type' => 'INVOICE_REVERSAL',
                    'amount' => -$unpaidAmount,
                    'balance_after' => $customer->total_debt,
                    'payment_method' => 'VOID_REVERSAL',
                    'reference_no' => 'REV-' . substr($saleId, 0, 8),
                    'recorded_by' => $actor->name,
                    'notes' => "Invoice credit cancelled due to voided Sale #{$saleId}. Debt reduced by ₦" . number_format($unpaidAmount, 2) . ". Reason: {$reason}",
                ]);
            }

            // 3. Cash Drawer Contra-Payment (Balances cashier drawer to ₦0 net)
            foreach ($sale->payments as $originalPayment) {
                if ($originalPayment->amount > 0 && !in_array($originalPayment->method, ['REFUND', 'REFUND_CASH', 'REFUND_POS', 'VOID_REVERSAL'], true)) {
                    Payment::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'saleId' => $saleId,
                        'amount' => -$originalPayment->amount,
                        'method' => 'VOID_REVERSAL',
                        'timestamp' => now()->toIso8601String(),
                        'recordedBy' => $actor->name . ' [REVERSAL]',
                        'createdAt' => now()->toIso8601String(),
                    ]);
                }
            }

            // 4. Update Sale Record to 'VOIDED' (NO HARD DELETE - Preserves invoice sequence!)
            $sale->status = 'VOIDED';
            $sale->deliveryStatus = 'CANCELLED';
            $sale->returnReason = "VOIDED: {$reason} (Authorized by {$actor->name})";
            $notePrefix = "[VOIDED by {$actor->name} ({$actor->role}) on " . now()->format('Y-m-d H:i') . ": {$reason}]";
            $sale->note = $sale->note ? ($notePrefix . " " . $sale->note) : $notePrefix;

            if (Schema::hasColumn('sales', 'voided_at')) {
                $sale->voided_at = now();
                $sale->voided_by = $actor->name;
                $sale->void_reason = $reason;
            }
            $sale->save();

            // 5. Record Security & Forensic Audit Log
            $metadata = [
                'sale_id' => $saleId,
                'warehouse_id' => $warehouseId,
                'receipt_ref' => $sale->receipt_ref ?? null,
                'total_amount' => $sale->totalAmount,
                'paid_amount' => $sale->paidAmount,
                'cash_amount' => $sale->cashAmount,
                'pos_amount' => $sale->posAmount,
                'customer_name' => $sale->customerName,
                'items' => $restoredItems,
                'void_reason' => $reason,
                'voided_by_user_id' => $actor->id,
                'voided_by_name' => $actor->name,
                'voided_by_role' => $actor->role,
            ];

            Activity::recordSecurityEvent(
                'TRANSACTION_VOIDED',
                "Admin {$actor->name} ({$actor->role}) reversed Sale #{$saleId} at Branch #{$warehouseId} (₦" . number_format($sale->totalAmount, 2) . "). Reason: {$reason}",
                $metadata,
                $actor
            );

            return [
                'success' => true,
                'message' => "Sale #{$saleId} successfully reversed and watermarked as VOIDED. Restocked " . count($restoredItems) . " item(s).",
            ];
        });
    }

    /**
     * Reverses an accidental or mistaken Debt Payment (Part-Payment) cleanly.
     * Restores customer debt, reverses sales allocation, and balances cashier cash drawer.
     */
    public function voidCustomerPayment(int|string $ledgerId, string $reason, User $actor): array
    {
        $this->assertAdminPrivilege($actor);

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException("A valid audit reason is mandatory for reversing a debt payment.");
        }

        return DB::transaction(function () use ($ledgerId, $reason, $actor) {
            $ledger = CustomerLedger::where('id', $ledgerId)->lockForUpdate()->firstOrFail();

            if ($ledger->type !== 'PAYMENT') {
                throw new \InvalidArgumentException("Only PAYMENT ledger entries can be reversed. Entry #{$ledgerId} is of type '{$ledger->type}'.");
            }

            if (str_starts_with($ledger->notes ?? '', '[REVERSED]')) {
                throw new \InvalidArgumentException("Debt payment entry #{$ledgerId} has already been reversed.");
            }

            $warehouseId = (int) ($ledger->warehouse_id ?? 1);
            $this->assertBranchAuthority($warehouseId, $actor);

            $customer = Customer::where('id', $ledger->customer_id)->lockForUpdate()->firstOrFail();
            $paymentAmount = (float) $ledger->amount;

            // 1. Restore Customer Debt balance
            $currentDebtKobo = AccountingReportService::toKobo($customer->total_debt);
            $reversalKobo = AccountingReportService::toKobo($paymentAmount);
            $customer->total_debt = AccountingReportService::toNaira($currentDebtKobo + $reversalKobo);
            $customer->save();

            // 2. Reverse paidAmount on credited sale invoices
            if (!empty($ledger->sale_id)) {
                $sale = Sale::where('id', $ledger->sale_id)->lockForUpdate()->first();
                if ($sale) {
                    $newPaidKobo = max(0, AccountingReportService::toKobo($sale->paidAmount) - $reversalKobo);
                    $sale->paidAmount = AccountingReportService::toNaira($newPaidKobo);
                    if ($sale->paidAmount < $sale->totalAmount && $sale->status === 'COMPLETED') {
                        $sale->status = ($sale->paidAmount <= 0) ? 'PARTIAL' : 'PARTIAL';
                    }
                    $sale->save();

                    // Offset payment in cash drawer
                    Payment::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $ledger->tenant_id ?? $sale->tenant_id,
                        'saleId' => $sale->id,
                        'amount' => -$paymentAmount,
                        'method' => 'VOID_REVERSAL',
                        'recordedBy' => $actor->name . ' [DEBT_PAYMENT_REVERSAL]',
                        'timestamp' => now()->toIso8601String(),
                        'createdAt' => now()->toIso8601String(),
                    ]);
                }
            } else {
                // If payment was split across multiple open sales, offset the recent debt recovery payments
                $allocatedPayments = Payment::where('amount', '>', 0)
                    ->where('recordedBy', 'like', '%[DEBT_RECOVERY]%')
                    ->whereHas('sale', fn($sq) => $sq->where('customerId', $customer->id))
                    ->orderBy('createdAt', 'desc')
                    ->lockForUpdate()
                    ->get();

                $remainingToOffsetKobo = $reversalKobo;
                foreach ($allocatedPayments as $aPay) {
                    if ($remainingToOffsetKobo <= 0) break;
                    $payKobo = AccountingReportService::toKobo($aPay->amount);
                    $offsetKobo = min($remainingToOffsetKobo, $payKobo);
                    $offsetAmount = AccountingReportService::toNaira($offsetKobo);

                    $pSale = Sale::where('id', $aPay->saleId)->lockForUpdate()->first();
                    if ($pSale) {
                        $pSale->paidAmount = AccountingReportService::toNaira(
                            max(0, AccountingReportService::toKobo($pSale->paidAmount) - $offsetKobo)
                        );
                        if ($pSale->paidAmount < $pSale->totalAmount) {
                            $pSale->status = 'PARTIAL';
                        }
                        $pSale->save();
                    }

                    Payment::create([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $ledger->tenant_id,
                        'saleId' => $aPay->saleId,
                        'amount' => -$offsetAmount,
                        'method' => 'VOID_REVERSAL',
                        'recordedBy' => $actor->name . ' [DEBT_PAYMENT_REVERSAL]',
                        'timestamp' => now()->toIso8601String(),
                        'createdAt' => now()->toIso8601String(),
                    ]);

                    $remainingToOffsetKobo -= $offsetKobo;
                }
            }

            // 3. Post Contra-Ledger entry
            CustomerLedger::create([
                'tenant_id' => $ledger->tenant_id,
                'customer_id' => $customer->id,
                'sale_id' => $ledger->sale_id,
                'warehouse_id' => $warehouseId,
                'type' => 'PAYMENT_REVERSAL',
                'amount' => -$paymentAmount,
                'balance_after' => $customer->total_debt,
                'payment_method' => $ledger->payment_method ?? 'CASH',
                'reference_no' => 'REV-' . ($ledger->reference_no ?? substr((string) Str::uuid(), 0, 8)),
                'recorded_by' => $actor->name,
                'notes' => "Payment reversal for Ledger #{$ledgerId}. Reason: {$reason}",
            ]);

            // 4. Mark original ledger entry as reversed
            $ledger->notes = "[REVERSED by {$actor->name}: {$reason}] " . ($ledger->notes ?? '');
            $ledger->save();

            // 5. Activity log
            Activity::recordSecurityEvent(
                'DEBT_PAYMENT_REVERSED',
                "Admin {$actor->name} reversed Debt Payment #{$ledgerId} of ₦" . number_format($paymentAmount, 2) . " for Customer {$customer->name}. Reason: {$reason}",
                [
                    'ledger_id' => $ledgerId,
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'amount_reversed' => $paymentAmount,
                    'new_debt_balance' => (float) $customer->total_debt,
                    'warehouse_id' => $warehouseId,
                    'reason' => $reason,
                ],
                $actor
            );

            return [
                'success' => true,
                'message' => "Debt payment of ₦" . number_format($paymentAmount, 2) . " successfully reversed. Customer debt restored to ₦" . number_format($customer->total_debt, 2) . ".",
                'new_balance' => $customer->total_debt,
            ];
        });
    }

    /**
     * Atomically reverses a Stock In entry and reverses shelf count.
     * Prevents negative inventory and protects pending customer reservations.
     */
    public function voidStockIn(string $logId, string $reason, User $actor): array
    {
        $this->assertAdminPrivilege($actor);

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException("A valid audit reason is mandatory for reversing Stock In.");
        }

        return DB::transaction(function () use ($logId, $reason, $actor) {
            $log = InventoryLog::where('id', $logId)->lockForUpdate()->firstOrFail();

            if ($log->type !== 'STOCK_IN' && $log->type !== 'PURCHASE') {
                throw new \InvalidArgumentException("Entry #{$logId} is not an active Stock In entry.");
            }

            $warehouseId = (int) $log->warehouse_id;
            $this->assertBranchAuthority($warehouseId, $actor);

            $productId = $log->productId;
            $qty = abs((int) $log->quantity);

            $stock = StockLevel::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$stock || $stock->physical_stock < $qty) {
                $available = $stock ? $stock->physical_stock : 0;
                throw new \InvalidArgumentException("Cannot reverse Stock In: Physical stock on ground ({$available}) is lower than the reversal amount ({$qty}). Products may already have been sold.");
            }

            // Protect customer reservations: reversing stock-in must not leave physical stock below unsupplied allocations
            $postReversalPhysical = $stock->physical_stock - $qty;
            if ($postReversalPhysical < $stock->allocated_stock) {
                throw new \InvalidArgumentException(
                    "Cannot reverse Stock In: Reversing {$qty} units would leave physical stock ({$postReversalPhysical}) below active customer reservations ({$stock->allocated_stock}). Please fulfill or cancel pending unsupplied orders first."
                );
            }

            $stock->physical_stock -= $qty;
            $stock->save();

            $product = Product::find($productId);
            if ($product) {
                $product->currentStock = StockLevel::where('product_id', $productId)->sum('physical_stock');
                $product->save();
            }

            // Record Contra-Log in InventoryLog (Preserves history!)
            InventoryLog::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $log->tenant_id,
                'productId' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => 'STOCK_IN_REVERSAL',
                'quantity' => -$qty,
                'userId' => $actor->id,
                'userName' => $actor->name,
                'productCode' => $product?->code ?? $log->productCode ?? 'N/A',
                'productName' => $log->productName,
                'description' => "Reversal of Stock In entry #{$logId} (-{$qty} units). Reason: {$reason}",
                'timestamp' => now()->toIso8601String(),
            ]);

            // Mark original log
            $log->description = "[REVERSED by {$actor->name}: {$reason}] " . $log->description;
            $log->type = 'STOCK_IN_REVERSED';
            $log->save();

            Activity::recordSecurityEvent(
                'STOCK_IN_VOIDED',
                "Admin {$actor->name} reversed Stock In entry #{$logId} (-{$qty} units of {$log->productName}) at Branch #{$warehouseId}. Reason: {$reason}",
                [
                    'log_id' => $logId,
                    'product_id' => $productId,
                    'product_name' => $log->productName,
                    'quantity_reversed' => $qty,
                    'warehouse_id' => $warehouseId,
                    'reason' => $reason,
                ],
                $actor
            );

            return [
                'success' => true,
                'message' => "Stock In entry successfully reversed. Deducted {$qty} units from inventory.",
            ];
        });
    }

    /**
     * Atomically reverses a Stock Out log or Stock Adjustment.
     */
    public function voidStockOutOrAdjustment(string $adjustmentId, string $reason, User $actor): array
    {
        $this->assertAdminPrivilege($actor);

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException("A valid audit reason is mandatory for reversing Stock Out / Adjustment.");
        }

        return DB::transaction(function () use ($adjustmentId, $reason, $actor) {
            $adj = StockAdjustment::where('id', $adjustmentId)->lockForUpdate()->first();

            if ($adj) {
                $qty = (int) $adj->quantity;
                $warehouseId = (int) $adj->warehouse_id;
                $this->assertBranchAuthority($warehouseId, $actor);

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

                // Symmetrical contra-log
                InventoryLog::create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $adj->tenant_id,
                    'productId' => $productId,
                    'warehouse_id' => $warehouseId,
                    'type' => 'STOCK_ADJUSTMENT_REVERSAL',
                    'quantity' => $qty,
                    'userId' => $actor->id,
                    'userName' => $actor->name,
                    'productCode' => $product?->code ?? 'N/A',
                    'productName' => $product?->name ?? 'Adjusted Product',
                    'description' => "Reversal of Stock Adjustment #{$adj->id} (+{$qty} units restored). Reason: {$reason}",
                    'timestamp' => now()->toIso8601String(),
                ]);

                $adj->reason = "[REVERSED by {$actor->name}: {$reason}] " . ($adj->reason ?? '');
                $adj->status = 'VOIDED';
                $adj->save();

                Activity::recordSecurityEvent(
                    'STOCK_ADJUSTMENT_VOIDED',
                    "Admin {$actor->name} reversed Stock Adjustment #{$adj->id} (+{$qty} units restored) at Branch #{$warehouseId}. Reason: {$reason}",
                    [
                        'adjustment_id' => $adj->id,
                        'product_id' => $productId,
                        'qty' => $qty,
                        'warehouse_id' => $warehouseId,
                        'reason' => $reason,
                    ],
                    $actor
                );

                return [
                    'success' => true,
                    'message' => "Stock adjustment reversed and {$qty} units restored to inventory.",
                ];
            }

            // Fallback: Check InventoryLog directly for Stock Out
            $log = InventoryLog::where('id', $adjustmentId)->lockForUpdate()->firstOrFail();
            $qty = abs((int) $log->quantity);
            $warehouseId = (int) $log->warehouse_id;
            $this->assertBranchAuthority($warehouseId, $actor);

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

            InventoryLog::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $log->tenant_id,
                'productId' => $productId,
                'warehouse_id' => $warehouseId,
                'type' => 'STOCK_OUT_REVERSAL',
                'quantity' => $qty,
                'userId' => $actor->id,
                'userName' => $actor->name,
                'productCode' => $product?->code ?? $log->productCode ?? 'N/A',
                'productName' => $product?->name ?? $log->productName ?? 'Dispatched Item',
                'description' => "Reversal of Stock Out entry #{$log->id} (+{$qty} units restored). Reason: {$reason}",
                'timestamp' => now()->toIso8601String(),
            ]);

            $log->description = "[REVERSED by {$actor->name}: {$reason}] " . $log->description;
            $log->type = 'STOCK_OUT_REVERSED';
            $log->save();

            Activity::recordSecurityEvent(
                'STOCK_OUT_VOIDED',
                "Admin {$actor->name} reversed Stock Out log #{$log->id} (+{$qty} units restored) at Branch #{$warehouseId}. Reason: {$reason}",
                [
                    'log_id' => $log->id,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'warehouse_id' => $warehouseId,
                    'reason' => $reason,
                ],
                $actor
            );

            return [
                'success' => true,
                'message' => "Stock out entry reversed and {$qty} units restored to inventory.",
            ];
        });
    }
}
