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
        
        $requestedWh = $request->get('warehouse_id', session('active_warehouse_id'));
        if ($requestedWh === 'ALL' || $requestedWh === '' || is_null($requestedWh)) {
            $requestedWh = null;
        }
        $assignedWarehouseId = $isBranchScoped ? (int) $authUser->warehouse_id : ($requestedWh ? (int) $requestedWh : null);

        $agreementType = $request->get('agreement_type', 'ALL');
        $accountingService = app(\App\Services\Accounting\AccountingReportService::class);

        // Fetch sales to calculate delivered debt vs unsupplied installments
        $relevantSalesQuery = \App\Models\Sale::whereNotNull('customerId')
            ->whereNotIn('status', ['CANCELLED', 'RETURNED']);
        if ($assignedWarehouseId) {
            $relevantSalesQuery->where('warehouse_id', $assignedWarehouseId);
        }
        $allOpenSales = $relevantSalesQuery->get(['id', 'customerId', 'totalAmount', 'deliveryStatus', 'warehouse_id']);
        $saleBalances = $accountingService->calculateInvoiceBalancesForSales($allOpenSales);

        $customerBranchDebts = [];
        $custDeliveredDebts = [];
        $custUnsuppliedDebts = [];
        $totalDeliveredDebt = 0.0;
        $totalInstallmentDebt = 0.0;

        foreach ($allOpenSales as $s) {
            $bal = $saleBalances[$s->id] ?? 0.0;
            if ($bal > 0) {
                $cId = $s->customerId;
                $customerBranchDebts[$cId] = ($customerBranchDebts[$cId] ?? 0.0) + $bal;
                $isUnsupplied = in_array($s->deliveryStatus, ['UNSUPPLIED', 'PENDING_PICKUP', 'NOT_SUPPLIED']);
                if ($isUnsupplied) {
                    $custUnsuppliedDebts[$cId] = ($custUnsuppliedDebts[$cId] ?? 0.0) + $bal;
                    $totalInstallmentDebt += $bal;
                } else {
                    $custDeliveredDebts[$cId] = ($custDeliveredDebts[$cId] ?? 0.0) + $bal;
                    $totalDeliveredDebt += $bal;
                }
            }
        }

        $query = Customer::where('total_debt', '>', 0);

        if ($assignedWarehouseId) {
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

        $customersCollection = $query->get()->map(function ($debtor) use ($assignedWarehouseId, $customerBranchDebts, $custDeliveredDebts, $custUnsuppliedDebts) {
            $bDebt = $assignedWarehouseId ? round($customerBranchDebts[$debtor->id] ?? 0.0, 2) : (float) $debtor->total_debt;
            $debtor->branch_debt = $bDebt;
            $debtor->total_debt = $bDebt;
            $debtor->delivered_debt = round($custDeliveredDebts[$debtor->id] ?? 0.0, 2);
            $debtor->unsupplied_debt = round($custUnsuppliedDebts[$debtor->id] ?? 0.0, 2);

            if ($debtor->unsupplied_debt > 0 && $debtor->delivered_debt <= 0) {
                $debtor->agreement_category = 'PAY_SMALL_SMALL';
            } elseif ($debtor->delivered_debt > 0 && $debtor->unsupplied_debt <= 0) {
                $debtor->agreement_category = 'GOODS_CARRIED';
            } elseif ($debtor->delivered_debt > 0 && $debtor->unsupplied_debt > 0) {
                $debtor->agreement_category = 'MIXED';
            } else {
                $debtor->agreement_category = 'GOODS_CARRIED';
            }
            return $debtor;
        });

        // Filter by agreement_type if specified
        if ($agreementType === 'GOODS_CARRIED') {
            $customersCollection = $customersCollection->filter(fn($d) => in_array($d->agreement_category, ['GOODS_CARRIED', 'MIXED']));
        } elseif ($agreementType === 'PAY_SMALL_SMALL') {
            $customersCollection = $customersCollection->filter(fn($d) => in_array($d->agreement_category, ['PAY_SMALL_SMALL', 'MIXED']));
        }

        if ($sortBy === 'lowest_debt') {
            $sorted = $customersCollection->sortBy('total_debt');
        } elseif ($sortBy === 'name_asc') {
            $sorted = $customersCollection->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
        } elseif ($sortBy === 'name_desc') {
            $sorted = $customersCollection->sortByDesc('name', SORT_NATURAL | SORT_FLAG_CASE);
        } else {
            $sorted = $customersCollection->sortByDesc('total_debt');
        }

        $page = (int) $request->get('page', 1);
        $perPage = 25;
        $sliced = $sorted->slice(($page - 1) * $perPage, $perPage)->values();
        $debtors = new \Illuminate\Pagination\LengthAwarePaginator($sliced, $sorted->count(), $perPage, $page, [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]);

        $totalOutstandingDebt = round($sorted->sum('total_debt'), 2);
        $highRiskDebtorsCount = $sorted->filter(fn($c) => $c->total_debt >= 100000)->count();
        $totalDebtorsCount = $sorted->count();

        $recentPaymentsQuery = CustomerLedger::with(['customer', 'sale.items'])->where('type', 'PAYMENT');
        $accountingService->applyDateFilterToQuery($recentPaymentsQuery, 'created_at', [
            'date_preset' => $request->get('date_preset', 'ALL'),
            'from_date'   => $request->get('from_date'),
            'to_date'     => $request->get('to_date'),
        ]);
        if ($assignedWarehouseId) {
            $recentPaymentsQuery->where(function ($q) use ($assignedWarehouseId) {
                $q->where('warehouse_id', $assignedWarehouseId)
                  ->orWhereHas('sale', fn($sq) => $sq->where('warehouse_id', $assignedWarehouseId));
            });
        }
        $recentPayments = $recentPaymentsQuery->orderBy('created_at', 'desc')->take(25)->get();

        return view('debts.index', compact(
            'debtors',
            'allCustomers',
            'totalOutstandingDebt',
            'totalDeliveredDebt',
            'totalInstallmentDebt',
            'totalDebtorsCount',
            'highRiskDebtorsCount',
            'recentPayments',
            'search',
            'debtBracket',
            'agreementType',
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
        $tenantId = session('tenant_id') ?? ($authUser ? $authUser->tenant_id : 'default-tenant');

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
        $userName = $authUser->name ?? 'Cashier';

        $idempotencyKey = $request->header('X-Idempotency-Key')
            ?? $request->input('idempotency_key')
            ?? $request->input('reference_no');

        // 🔒 Invariant VM-032: Enforce Mandatory Idempotency Closure
        if (empty($idempotencyKey)) {
            if ($request->header('X-Strict-Idempotency') || $request->header('X-Require-Idempotency') || $request->is('api/*') || ($request->expectsJson() && !$request->hasSession())) {
                $errorMsg = 'Idempotency key is required for debt payment.';
                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json(['success' => false, 'error' => $errorMsg], 422);
                }
                return back()->withErrors(['error' => $errorMsg])->withInput();
            }
            // Seamless web session fallback: derive unique UUID so all executions route authoritatively through IdempotencyService
            $idempotencyKey = (string) \Illuminate\Support\Str::uuid();
        }

        $idempotencyPayload = [
            'customerId' => (int) $customerId,
            'amount' => (float) $request->amount,
            'payment_method' => strtolower($request->payment_method),
            'warehouse_id' => $warehouseId,
        ];

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

            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "✓ Payment of ₦" . number_format($request->amount, 2) . " successfully credited to customer ledger!",
                    'ledgerId' => $ledger->id ?? null,
                    'balance_after' => $ledger->balance_after ?? null,
                    'ledger' => $ledger,
                ]);
            }

            return redirect()->route('debts.index')->with('success', "✓ Payment of ₦" . number_format($request->amount, 2) . " successfully credited to customer ledger!");
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->expectsJson()) {
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
            $msg = $e->getMessage() ?: 'Unable to process debt payment. Please check your network or contact support.';
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['success' => false, 'error' => $msg], 422);
            }
            return back()->withErrors(['error' => $msg])->withInput();
        }
    }
}
