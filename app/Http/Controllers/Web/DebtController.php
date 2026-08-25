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

        $query = Customer::where('total_debt', '>', 0);

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

        $debtors = $query->paginate(25)->withQueryString();
        $allCustomers = Customer::orderBy('name')->get();

        $totalOutstandingDebt = Customer::sum('total_debt');
        $totalDebtorsCount = Customer::where('total_debt', '>', 0)->count();
        $highRiskDebtorsCount = Customer::where('total_debt', '>=', 100000)->count();

        $recentPayments = CustomerLedger::with('customer')
            ->where('type', 'PAYMENT')
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

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
            'payment_method' => 'required|string',
        ]);

        $userId = Auth::id() ?? 'USER-1';
        $userName = Auth::user()->name ?? 'Cashier';

        try {
            $ledger = $this->stockService->recordCustomerPayment(
                (int) $customerId,
                (float) $request->amount,
                $request->payment_method,
                $request->reference_no,
                $userId,
                $userName,
                $request->notes
            );

            return redirect()->route('debts.index')->with('success', "✓ Payment of ₦" . number_format($request->amount, 0) . " received successfully!");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
