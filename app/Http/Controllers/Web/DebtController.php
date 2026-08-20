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
     * Debtors List & Part-Payment Recovery Manager.
     */
    public function index()
    {
        $debtors = Customer::where('total_debt', '>', 0)
            ->orderBy('total_debt', 'desc')
            ->get();

        $allCustomers = Customer::orderBy('name')->get();
        $totalOutstandingDebt = $debtors->sum('total_debt');
        $recentPayments = CustomerLedger::with('customer')
            ->where('type', 'PAYMENT')
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();

        return view('debts.index', compact('debtors', 'allCustomers', 'totalOutstandingDebt', 'recentPayments'));
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

            return redirect()->route('debts.index')->with('success', "✓ Payment of ₦" . number_format($request->amount, 2) . " received successfully!");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
