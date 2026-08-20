<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * Display searchable, filterable Transactions History.
     */
    public function index(Request $request)
    {
        $query = Sale::with('items');

        // 1. Date Range Filter
        $datePreset = $request->get('date_preset', 'ALL');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        if ($fromDate && $toDate) {
            $query->whereBetween('createdAt', [
                Carbon::parse($fromDate)->startOfDay()->toIso8601String(),
                Carbon::parse($toDate)->endOfDay()->toIso8601String()
            ]);
        } elseif ($datePreset === 'TODAY') {
            $query->whereDate('createdAt', Carbon::today());
        } elseif ($datePreset === 'YESTERDAY') {
            $query->whereDate('createdAt', Carbon::yesterday());
        } elseif ($datePreset === 'THIS_WEEK') {
            $query->whereBetween('createdAt', [
                Carbon::now()->startOfWeek()->toIso8601String(),
                Carbon::now()->endOfWeek()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_MONTH') {
            $query->whereBetween('createdAt', [
                Carbon::now()->startOfMonth()->toIso8601String(),
                Carbon::now()->endOfMonth()->toIso8601String()
            ]);
        }

        // 2. Payment Status Filter
        if ($request->filled('payment_status')) {
            $status = strtoupper($request->payment_status);
            if ($status === 'PAID') {
                $query->whereColumn('paidAmount', '>=', 'totalAmount');
            } elseif (in_array($status, ['PART_PAID', 'PARTIAL'])) {
                $query->whereColumn('paidAmount', '<', 'totalAmount')->where('paidAmount', '>', 0);
            } elseif (in_array($status, ['NOT_PAID', 'UNPAID'])) {
                $query->where('paidAmount', '<=', 0);
            } elseif ($status === 'DEBT') {
                $query->whereColumn('paidAmount', '<', 'totalAmount');
            }
        }

        // 3. Fulfillment / Handover Status Filter
        if ($request->filled('delivery_status')) {
            $dStatus = strtoupper($request->delivery_status);
            if (in_array($dStatus, ['DELIVERED', 'SUPPLIED'])) {
                $query->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
            } elseif (in_array($dStatus, ['UNSUPPLIED', 'NOT_SUPPLIED', 'PENDING'])) {
                $query->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
            } elseif ($dStatus === 'PAID_SUPPLIED') {
                $query->whereColumn('paidAmount', '>=', 'totalAmount')->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
            } elseif ($dStatus === 'PAID_NOT_SUPPLIED') {
                $query->whereColumn('paidAmount', '>=', 'totalAmount')->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
            } elseif ($dStatus === 'PART_PAID_SUPPLIED') {
                $query->whereColumn('paidAmount', '<', 'totalAmount')->where('paidAmount', '>', 0)->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
            } elseif ($dStatus === 'PART_PAID_NOT_SUPPLIED') {
                $query->whereColumn('paidAmount', '<', 'totalAmount')->where('paidAmount', '>', 0)->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
            } else {
                $query->where('deliveryStatus', $dStatus);
            }
        }

        // 4. Staff / Cashier Filter
        if ($request->filled('user_name')) {
            $query->where('userName', 'like', "%{$request->user_name}%");
        }

        // 5. Keyword Search (Invoice ID, Customer Name, Phone)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('customerName', 'like', "%{$search}%")
                  ->orWhere('customerPhone', 'like', "%{$search}%");
            });
        }

        // Clone query for high-level aggregate metrics before pagination
        $totalSalesCount = (clone $query)->count();
        $totalRevenue = (clone $query)->sum('totalAmount');
        $totalPaid = (clone $query)->sum('paidAmount');
        $totalDebt = max(0, $totalRevenue - $totalPaid);

        $sales = $query->orderBy('createdAt', 'desc')->paginate(25)->withQueryString();
        $warehouses = Warehouse::where('is_active', true)->get();
        $cashiers = User::orderBy('name')->pluck('name');

        return view('transactions.index', compact(
            'sales',
            'warehouses',
            'cashiers',
            'totalSalesCount',
            'totalRevenue',
            'totalPaid',
            'totalDebt',
            'datePreset',
            'fromDate',
            'toDate'
        ));
    }
}
