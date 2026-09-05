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
            $customerIdsAtBranch = \App\Models\Sale::where('warehouse_id', $assignedWarehouseId)
                ->whereNotNull('customerId')
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->pluck('customerId')
                ->filter()
                ->unique();
            $query->whereIn('id', $customerIdsAtBranch);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($debtBracket === 'HIGH') {
            $query->where('total_debt', '>=', 100000);
        } elseif ($debtBracket === 'MEDIUM') {
            $query->where('total_debt', '>=', 20000)->where('total_debt', '<', 100000);
        } elseif ($debtBracket === 'LOW') {
            $query->where('total_debt', '<', 20000);
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
        $allCustomers = Customer::orderBy('name')->get();

        if ($assignedWarehouseId) {
            $branchSales = \App\Models\Sale::where('warehouse_id', $assignedWarehouseId)
                ->whereNotNull('customerId')
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->get();
            $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
            $totalOutstandingDebt = 0.0;
            foreach ($branchSales as $bs) {
                $totalOutstandingDebt += $accountingService->calculateInvoiceBalance($bs);
            }
            $totalOutstandingDebt = round($totalOutstandingDebt, 2);

            $debtors->getCollection()->transform(function ($debtor) use ($assignedWarehouseId, $accountingService) {
                $bSales = \App\Models\Sale::where('warehouse_id', $assignedWarehouseId)
                    ->where('customerId', $debtor->id)
                    ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                    ->get();
                $bDebt = 0.0;
                foreach ($bSales as $bs) {
                    $bDebt += $accountingService->calculateInvoiceBalance($bs);
                }
                $debtor->branch_debt = round($bDebt, 2);
                return $debtor;
            });
        } else {
            $totalOutstandingDebt = (clone $query)->sum('total_debt');
        }

        $totalDebtorsCount = (clone $query)->count();
        $highRiskDebtorsCount = (clone $query)->where('total_debt', '>=', 100000)->count();

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
                    ->where(function ($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId)
                          ->orWhereNull('warehouse_id');
                    })
                    ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                    ->exists();
                if (!$hasBranchDebt) {
                    return back()->withErrors(['error' => 'Unauthorized: Customer has no outstanding invoices at your assigned branch location.'])->withInput();
                }
            }
        }

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Cashier';

        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key') ?? $request->input('reference_no') ?? (string) \Illuminate\Support\Str::uuid();
        $tenantId = session('tenant_id') ?? Auth::user()->tenant_id ?? 'default-tenant';

        try {
            $idempotencyService = app(\App\Services\IdempotencyService::class);
            $ledger = $idempotencyService->execute(
                'debt_payment',
                (string) $idempotencyKey,
                (string) $tenantId,
                (string) $userId,
                [
                    'customerId' => (int) $customerId,
                    'amount' => (float) $request->amount,
                    'payment_method' => $request->payment_method,
                    'warehouse_id' => $warehouseId,
                ],
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

            return redirect()->route('debts.index')->with('success', "✓ Payment of ₦" . number_format($request->amount, 2) . " successfully credited to customer ledger!");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
