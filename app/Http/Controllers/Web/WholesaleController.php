<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Payment;
use App\Models\Warehouse;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WholesaleController extends Controller
{
    /**
     * Executive Wholesale Management & Office Pricing Portal.
     */
    public function index(Request $request)
    {
        $search = trim($request->get('search', ''));
        $datePreset = $request->get('date_preset', 'ALL');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $pricingStatus = $request->get('pricing_status', 'ALL');
        $warehouseId = $request->get('warehouse_id');

        $query = Sale::with(['items', 'customer'])->where('sale_type', 'WHOLESALE_DISPATCH');

        // Branch filtering
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        // Date range filtering
        if ($fromDate && $toDate) {
            $query->whereBetween('createdAt', [
                Carbon::parse($fromDate)->startOfDay()->toIso8601String(),
                Carbon::parse($toDate)->endOfDay()->toIso8601String(),
            ]);
        } elseif ($datePreset === 'TODAY') {
            $query->whereDate('createdAt', Carbon::today());
        } elseif ($datePreset === 'YESTERDAY') {
            $query->whereDate('createdAt', Carbon::yesterday());
        } elseif ($datePreset === 'THIS_WEEK') {
            $query->whereBetween('createdAt', [
                Carbon::now()->startOfWeek()->toIso8601String(),
                Carbon::now()->endOfWeek()->toIso8601String(),
            ]);
        } elseif ($datePreset === 'THIS_MONTH') {
            $query->whereBetween('createdAt', [
                Carbon::now()->startOfMonth()->toIso8601String(),
                Carbon::now()->endOfMonth()->toIso8601String(),
            ]);
        }

        // Pricing / Settlement Status Filter
        if ($pricingStatus === 'PENDING_PRICING') {
            $query->where('totalAmount', '<=', 0);
        } elseif ($pricingStatus === 'PRICED_PAID') {
            $query->where('totalAmount', '>', 0)->whereColumn('paidAmount', '>=', 'totalAmount');
        } elseif ($pricingStatus === 'PRICED_DEBT') {
            $query->where('totalAmount', '>', 0)->whereColumn('paidAmount', '<', 'totalAmount');
        }

        // Customer search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customerName', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('userName', 'like', "%{$search}%");
            });
        }

        // KPI Summary Aggregations
        $kpiQuery = Sale::where('sale_type', 'WHOLESALE_DISPATCH');
        $totalDispatches = (clone $kpiQuery)->count();
        $pendingPricingCount = (clone $kpiQuery)->where('totalAmount', '<=', 0)->count();
        $totalInvoicedValue = (clone $kpiQuery)->sum('totalAmount');
        $totalSettledValue = (clone $kpiQuery)->sum('paidAmount');
        $totalWholesaleDebt = max(0, $totalInvoicedValue - $totalSettledValue);

        $dispatches = $query->orderBy('createdAt', 'desc')->paginate(20)->withQueryString();
        $warehouses = Warehouse::where('is_active', true)->get();
        $customers = Customer::orderBy('name')->get();

        return view('wholesale.index', compact(
            'dispatches',
            'warehouses',
            'customers',
            'totalDispatches',
            'pendingPricingCount',
            'totalInvoicedValue',
            'totalSettledValue',
            'totalWholesaleDebt',
            'search',
            'datePreset',
            'fromDate',
            'toDate',
            'pricingStatus',
            'warehouseId'
        ));
    }

    /**
     * Live Office Pricing & Financial Reconciliation Engine.
     */
    public function priceOrder(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:sale_items,id',
            'items.*.unit_price' => 'required|numeric|min:0',
            'payment_status' => 'required|in:PAID,DEBT,PARTIAL',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|in:TRANSFER,CASH,POS',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $userId = Auth::id() ?? 'ADMIN-1';
        $userName = Auth::user()->name ?? 'Executive Office';

        try {
            DB::transaction(function () use ($request, $id, $userId, $userName) {
                $sale = Sale::with('items')->where('id', $id)->lockForUpdate()->firstOrFail();

                if ($sale->sale_type !== 'WHOLESALE_DISPATCH') {
                    throw new \InvalidArgumentException("Security Violation: Only wholesale dispatch orders can be priced through this portal.");
                }

                if ($sale->status === 'COMPLETED') {
                    throw new \InvalidArgumentException("Terminal State: This wholesale dispatch has already been settled and marked COMPLETED. It cannot be re-priced.");
                }

                $calculatedTotal = 0;

                // 1. Update line item prices
                foreach ($request->items as $itemData) {
                    $saleItem = SaleItem::where('id', $itemData['id'])->where('saleId', $sale->id)->firstOrFail();
                    $unitPrice = (float) $itemData['unit_price'];
                    $totalPrice = $unitPrice * $saleItem->quantity;

                    $saleItem->unitPrice = $unitPrice;
                    $saleItem->totalPrice = $totalPrice;
                    $saleItem->save();

                    $calculatedTotal += $totalPrice;
                }

                // 2. Determine Settlement Amounts
                $paymentStatus = $request->payment_status;
                if ($paymentStatus === 'PAID') {
                    $paidAmount = $calculatedTotal;
                    $cashAmount = ($request->payment_method === 'CASH') ? $calculatedTotal : 0;
                    $posAmount = ($request->payment_method !== 'CASH') ? $calculatedTotal : 0;
                } elseif ($paymentStatus === 'DEBT') {
                    $paidAmount = 0;
                    $cashAmount = 0;
                    $posAmount = 0;
                } else { // PARTIAL
                    $paidAmount = min($calculatedTotal, (float) ($request->paid_amount ?? 0));
                    $cashAmount = ($request->payment_method === 'CASH') ? $paidAmount : 0;
                    $posAmount = ($request->payment_method !== 'CASH') ? $paidAmount : 0;
                }

                $previousTotal = (float) $sale->totalAmount;
                $previousPaid = (float) $sale->paidAmount;
                $previousDebt = max(0, $previousTotal - $previousPaid);

                $newDebt = max(0, $calculatedTotal - $paidAmount);

                // 3. Update Sale Record
                $sale->totalAmount = $calculatedTotal;
                $sale->paidAmount = $paidAmount;
                $sale->cashAmount = $cashAmount;
                $sale->posAmount = $posAmount;
                $sale->status = ($paidAmount >= $calculatedTotal) ? 'COMPLETED' : 'PARTIAL';
                if ($request->notes) {
                    $sale->note = trim(($sale->note ? $sale->note . " | " : '') . "Office Pricing: " . $request->notes);
                }
                $sale->save();

                // 4. Record Payment Entry if paidAmount > 0
                if ($paidAmount > 0) {
                    Payment::create([
                        'id' => (string) Str::uuid(),
                        'saleId' => $sale->id,
                        'amount' => $paidAmount,
                        'method' => $request->payment_method ?? 'TRANSFER',
                        'timestamp' => now()->toIso8601String(),
                        'recordedBy' => $userName,
                        'createdAt' => now()->toIso8601String(),
                    ]);
                }

                // 5. Update Customer Ledger & Outstanding Debt
                $customer = null;
                if ($sale->customerId) {
                    $customer = Customer::where('id', $sale->customerId)->lockForUpdate()->first();
                }
                if (!$customer && $sale->customerName && strtolower($sale->customerName) !== 'walk-in customer') {
                    $customer = Customer::where('name', $sale->customerName)->lockForUpdate()->first();
                }

                if ($customer) {
                    // Net debt difference adjustment
                    $debtDelta = $newDebt - $previousDebt;
                    $customer->total_debt = max(0, $customer->total_debt + $debtDelta);
                    $customer->save();

                    CustomerLedger::create([
                        'customer_id' => $customer->id,
                        'sale_id' => $sale->id,
                        'type' => 'INVOICE',
                        'amount' => $calculatedTotal,
                        'balance_after' => $customer->total_debt,
                        'payment_method' => $paymentStatus === 'PAID' ? ($request->payment_method ?? 'TRANSFER') : 'DEBT_BILLED',
                        'reference_no' => $request->reference_no ?: $sale->id,
                        'recorded_by' => $userName,
                        'notes' => "Wholesale Dispatch priced by Madam. Total: ₦" . number_format($calculatedTotal, 2) . ", Paid: ₦" . number_format($paidAmount, 2) . ($request->reference_no ? " (Ref: {$request->reference_no})" : ""),
                    ]);
                }

                // 6. Immutable Activity Log
                Activity::create([
                    'id' => (string) Str::uuid(),
                    'type' => 'WHOLESALE_PRICING',
                    'description' => "{$userName} priced Wholesale Dispatch #{$sale->id} ({$sale->customerName}) for ₦" . number_format($calculatedTotal, 2) . " [Paid: ₦" . number_format($paidAmount, 2) . "]",
                    'userId' => $userId,
                    'userName' => $userName,
                    'timestamp' => now()->toIso8601String(),
                    'metadata' => json_encode([
                        'sale_id' => $sale->id,
                        'customer' => $sale->customerName,
                        'total_amount' => $calculatedTotal,
                        'paid_amount' => $paidAmount,
                        'new_debt' => $newDebt,
                    ]),
                ]);
            });

            return redirect()->route('wholesale.index')->with('success', "✓ Wholesale Order #{$id} priced and reconciled successfully!");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Formal Commercial Wholesale Invoice (Priced Printable Document).
     */
    public function commercialInvoice($id)
    {
        $sale = Sale::with(['items', 'customer'])->findOrFail($id);
        $warehouse = Warehouse::find(session('active_warehouse_id', 1)) ?? Warehouse::first();

        return view('wholesale.invoice', compact('sale', 'warehouse'));
    }
}
