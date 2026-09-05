<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DebtController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Debtors List & Part-Payment Recovery Manager with dedicated filters and search.
     */
    public function index(Request $request)
    {
        $search = trim($request->get('search', ''));
        $debtBracket = $request->get('debt_bracket', 'ALL');
        $sortBy = $request->get('sort_by', 'highest_debt');
        $authUser = Auth::user();
        $isBranchScoped = ($authUser && $authUser->isBranchScoped());
        $assignedWarehouseId = $isBranchScoped ? (int) $authUser->warehouse_id : null;

        $query = Customer::where('total_debt', '>', 0);

        if ($assignedWarehouseId) {
            $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
            $branchSales = \App\Models\Sale::where('warehouse_id', $assignedWarehouseId)
                ->whereNotNull('customerId')
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->get();

            $customerBranchDebts = [];
            foreach ($branchSales as $bs) {
                $cId = $bs->customerId;
                $bal = $accountingService->calculateInvoiceBalance($bs);
                if ($bal > 0) {
                    $customerBranchDebts[$cId] = ($customerBranchDebts[$cId] ?? 0.0) + $bal;
                }
            }

            // Only customers who actually owe debt originating at this branch
            $customerIdsAtBranch = array_keys(array_filter($customerBranchDebts, fn($b) => round($b, 2) > 0));
            $query->whereIn('id', $customerIdsAtBranch);

            // Scope debt bracket filtering strictly to branch debt
            if ($debtBracket === 'HIGH') {
                $filteredIds = array_keys(array_filter($customerBranchDebts, fn($b) => $b >= 100000));
                $query->whereIn('id', $filteredIds);
            } elseif ($debtBracket === 'MEDIUM') {
                $filteredIds = array_keys(array_filter($customerBranchDebts, fn($b) => $b >= 20000 && $b < 100000));
                $query->whereIn('id', $filteredIds);
            } elseif ($debtBracket === 'LOW') {
                $filteredIds = array_keys(array_filter($customerBranchDebts, fn($b) => $b < 20000));
                $query->whereIn('id', $filteredIds);
            }

            // Strictly scope modal dropdown to branch customers
            $allCustomers = Customer::whereIn('id', $customerIdsAtBranch)->orderBy('name')->get();
        } else {
            if ($debtBracket === 'HIGH') {
                $query->where('total_debt', '>=', 100000);
            } elseif ($debtBracket === 'MEDIUM') {
                $query->where('total_debt', '>=', 20000)->where('total_debt', '<', 100000);
            } elseif ($debtBracket === 'LOW') {
                $query->where('total_debt', '<', 20000);
            }

            $allCustomers = Customer::orderBy('name')->get();
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($sortBy === 'lowest_debt') {
            $query->orderBy('total_debt', 'asc');
        } elseif ($sortBy === 'name_asc') {
            $query->orderBy('name', 'asc');
        } elseif ($sortBy === 'name_desc') {
            $query->orderBy('name', 'desc');
        } else {
            $query->orderBy('total_debt', 'desc');
        }

        $debtors = (clone $query)->paginate(25)->withQueryString();

        if ($assignedWarehouseId) {
            $matchingDebtorIds = (clone $query)->pluck('id')->toArray();
            $totalOutstandingDebt = 0.0;
            $highRiskDebtorsCount = 0;
            foreach ($matchingDebtorIds as $mId) {
                $bDebt = $customerBranchDebts[$mId] ?? 0.0;
                $totalOutstandingDebt += $bDebt;
                if ($bDebt >= 100000) {
                    $highRiskDebtorsCount++;
                }
            }
            $totalOutstandingDebt = round($totalOutstandingDebt, 2);

            $debtors->getCollection()->transform(function ($debtor) use ($customerBranchDebts) {
                $debtor->branch_debt = round($customerBranchDebts[$debtor->id] ?? 0.0, 2);
                return $debtor;
            });
        } else {
            $totalOutstandingDebt = (clone $query)->sum('total_debt');
            $highRiskDebtorsCount = (clone $query)->where('total_debt', '>=', 100000)->count();
        }

        $totalDebtorsCount = (clone $query)->count();

        $recentPaymentsQuery = CustomerLedger::with(['customer', 'sale'])->where('type', 'PAYMENT');
        if ($assignedWarehouseId) {
            $recentPaymentsQuery->where(function ($q) use ($assignedWarehouseId) {
                $q->where('warehouse_id', $assignedWarehouseId)
                  ->orWhereHas('sale', fn($sq) => $sq->where('warehouse_id', $assignedWarehouseId));
            });
        }
        $recentPayments = $recentPaymentsQuery->orderBy('created_at', 'desc')->take(15)->get();

        return view('debts.index', compact(
            'debtors',
            'allCustomers',
            'totalOutstandingDebt',
            'totalDebtorsCount',
            'highRiskDebtorsCount',
            'recentPayments',
            'search',
            'debtBracket',
            'sortBy'
        ));
    }

    /**
     * Record Part-Payment from a debtor customer.
     */
    public function recordPayment(Request $request, $customerId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:CASH,POS,cash,pos',
        ]);

        $authUser = Auth::user();
        $isBranchScoped = ($authUser && $authUser->isBranchScoped());
        $warehouseId = $isBranchScoped ? (int) $authUser->warehouse_id : null;

        // Independent validation: if actor is branch-scoped, customer MUST have open debt at this branch
        if ($warehouseId) {
            $hasAnySales = \App\Models\Sale::where('customerId', $customerId)->exists();
            if ($hasAnySales) {
                $hasBranchDebt = \App\Models\Sale::where('customerId', $customerId)
                    ->where('warehouse_id', $warehouseId)
                    ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                    ->exists();
                if (!$hasBranchDebt) {
                    return back()->withErrors(['error' => 'Unauthorized: Customer has no outstanding invoices at your assigned branch location.'])->withInput();
                }
            }
        }

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Cashier';
        $tenantId = session('tenant_id') ?? Auth::user()->tenant_id ?? 'default-tenant';

        $idempotencyPayload = [
            'customerId' => (int) $customerId,
            'amount' => (float) $request->amount,
            'payment_method' => strtolower($request->payment_method),
            'warehouse_id' => $warehouseId,
        ];

        $idempotencyKey = $request->header('X-Idempotency-Key')
            ?? $request->input('idempotency_key')
            ?? $request->input('reference_no');

        if (empty($idempotencyKey)) {
            if ($request->header('X-Require-Idempotency') || $request->is('api/*')) {
                return response()->json(['success' => false, 'error' => 'Idempotency key is required for debt payment.'], 422);
            }
            $idempotencyKey = (string) \Illuminate\Support\Str::uuid();
        }

        try {
            $idempotencyService = app(\App\Services\IdempotencyService::class);
            $ledger = $idempotencyService->execute(
                'debt_payment',
                (string) $idempotencyKey,
                (string) $tenantId,
                (string) $userId,
                $idempotencyPayload,
                function () use ($customerId, $request, $userId, $userName, $warehouseId) {
                    return $this->stockService->recordCustomerPayment(
                        (int) $customerId,
                        (float) $request->amount,
                        $request->payment_method,
                        $request->reference_no,
                        $userId,
                        $userName,
                        $request->notes,
                        $warehouseId
                    );
                }
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment successfully credited to customer ledger!',
                    'ledgerId' => $ledger->id ?? null,
                    'balance_after' => $ledger->balance_after ?? null,
                ]);
            }

            return redirect()->route('debts.index')->with('success', "✓ Payment of ₦" . number_format($request->amount, 2) . " successfully credited to customer ledger!");
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Debt payment failed for customer {$customerId}: " . $e->getMessage(), [
                'exception' => $e,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'customer_id' => $customerId,
            ]);
            $msg = 'Unable to process debt payment. Please check your network or contact support.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $msg], 422);
            }
            return back()->withErrors(['error' => $msg])->withInput();
        }
    }
}
