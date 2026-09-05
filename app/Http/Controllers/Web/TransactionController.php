<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\InventoryLog;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\SalesReturn;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * Helper to apply date filtering consistently across models.
     */
    private function applyDateFilter($query, string $dateColumn, Request $request): void
    {
        $datePreset = strtoupper($request->get('date_preset', 'ALL'));
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        if ($fromDate && $toDate) {
            $start = Carbon::parse($fromDate)->startOfDay();
            $end = Carbon::parse($toDate)->endOfDay();
            if ($dateColumn === 'createdAt' || $dateColumn === 'timestamp') {
                $query->whereBetween($dateColumn, [$start->toIso8601String(), $end->toIso8601String()]);
            } else {
                $query->whereBetween($dateColumn, [$start->toDateTimeString(), $end->toDateTimeString()]);
            }
        } elseif ($fromDate) {
            $start = Carbon::parse($fromDate)->startOfDay();
            $end = Carbon::now()->endOfDay();
            if ($dateColumn === 'createdAt' || $dateColumn === 'timestamp') {
                $query->whereBetween($dateColumn, [$start->toIso8601String(), $end->toIso8601String()]);
            } else {
                $query->whereBetween($dateColumn, [$start->toDateTimeString(), $end->toDateTimeString()]);
            }
        } elseif ($toDate) {
            $start = Carbon::parse('2020-01-01')->startOfDay();
            $end = Carbon::parse($toDate)->endOfDay();
            if ($dateColumn === 'createdAt' || $dateColumn === 'timestamp') {
                $query->whereBetween($dateColumn, [$start->toIso8601String(), $end->toIso8601String()]);
            } else {
                $query->whereBetween($dateColumn, [$start->toDateTimeString(), $end->toDateTimeString()]);
            }
        } elseif ($datePreset === 'TODAY') {
            $start = Carbon::today()->startOfDay();
            $end = Carbon::today()->endOfDay();
            if ($dateColumn === 'createdAt' || $dateColumn === 'timestamp') {
                $query->whereBetween($dateColumn, [$start->toIso8601String(), $end->toIso8601String()]);
            } else {
                $query->whereBetween($dateColumn, [$start->toDateTimeString(), $end->toDateTimeString()]);
            }
        } elseif ($datePreset === 'YESTERDAY') {
            $start = Carbon::yesterday()->startOfDay();
            $end = Carbon::yesterday()->endOfDay();
            if ($dateColumn === 'createdAt' || $dateColumn === 'timestamp') {
                $query->whereBetween($dateColumn, [$start->toIso8601String(), $end->toIso8601String()]);
            } else {
                $query->whereBetween($dateColumn, [$start->toDateTimeString(), $end->toDateTimeString()]);
            }
        } elseif ($datePreset === 'THIS_WEEK') {
            $start = Carbon::now()->startOfWeek()->startOfDay();
            $end = Carbon::now()->endOfWeek()->endOfDay();
            if ($dateColumn === 'createdAt' || $dateColumn === 'timestamp') {
                $query->whereBetween($dateColumn, [$start->toIso8601String(), $end->toIso8601String()]);
            } else {
                $query->whereBetween($dateColumn, [$start->toDateTimeString(), $end->toDateTimeString()]);
            }
        } elseif ($datePreset === 'THIS_MONTH') {
            $start = Carbon::now()->startOfMonth()->startOfDay();
            $end = Carbon::now()->endOfMonth()->endOfDay();
            if ($dateColumn === 'createdAt' || $dateColumn === 'timestamp') {
                $query->whereBetween($dateColumn, [$start->toIso8601String(), $end->toIso8601String()]);
            } else {
                $query->whereBetween($dateColumn, [$start->toDateTimeString(), $end->toDateTimeString()]);
            }
        } elseif ($datePreset === 'THIS_YEAR') {
            $start = Carbon::now()->startOfYear()->startOfDay();
            $end = Carbon::now()->endOfYear()->endOfDay();
            if ($dateColumn === 'createdAt' || $dateColumn === 'timestamp') {
                $query->whereBetween($dateColumn, [$start->toIso8601String(), $end->toIso8601String()]);
            } else {
                $query->whereBetween($dateColumn, [$start->toDateTimeString(), $end->toDateTimeString()]);
            }
        }
    }

    /**
     * Shared Query Builders for each of the 8 Tabs (Role-Scoped for Privacy & Fraud Prevention)
     */
    public function getSalesQuery(Request $request)
    {
        $query = Sale::with('items');
        $this->applyDateFilter($query, 'createdAt', $request);

        // 🔒 Role & Branch Privacy Scoping
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->where('warehouse_id', $user->warehouse_id);
            if ($user->role === 'cashier') {
                $query->where('userId', $user->id);
            }
        } elseif ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('payment_status')) {
            $pStatus = strtoupper($request->payment_status);

            // Authoritative event-based financial calculation subqueries:
            // When payment records exist for the sale, calculate net paid from payment events.
            // Otherwise, fall back to cached paidAmount for legacy/mock test records.
            $hasPaymentsSql = "(SELECT COUNT(*) FROM payments WHERE payments.saleId = sales.id)";
            $eventNetPaidSql = "COALESCE((SELECT SUM(amount) FROM payments WHERE payments.saleId = sales.id AND payments.amount > 0 AND payments.method != 'REFUND_CASH'), 0) - COALESCE((SELECT ABS(SUM(amount)) FROM payments WHERE payments.saleId = sales.id AND payments.method = 'REFUND_CASH'), 0)";
            $netPaidSql = "CASE WHEN {$hasPaymentsSql} > 0 THEN ({$eventNetPaidSql}) ELSE sales.paidAmount END";
            $returnCreditsSql = "COALESCE((SELECT SUM(refundAmount) FROM sales_returns WHERE sales_returns.saleId = sales.id), 0)";
            $netBalanceSql = "(sales.totalAmount - ({$returnCreditsSql}) - ({$netPaidSql}))";

            if ($pStatus === 'PAID') {
                $query->whereRaw("{$netBalanceSql} <= 0.01");
            } elseif (in_array($pStatus, ['PART_PAID', 'PARTIAL'])) {
                $query->whereRaw("{$netBalanceSql} > 0.01 AND ({$netPaidSql}) > 0.01");
            } elseif (in_array($pStatus, ['NOT_PAID', 'UNPAID'])) {
                $query->whereRaw("{$netBalanceSql} > 0.01 AND ({$netPaidSql}) <= 0.01");
            } elseif ($pStatus === 'DEBT') {
                $query->whereRaw("{$netBalanceSql} > 0.01");
            }
        }

        if ($request->filled('delivery_status')) {
            $dStatus = strtoupper($request->delivery_status);
            if (in_array($dStatus, ['DELIVERED', 'SUPPLIED'])) {
                $query->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
            } elseif (in_array($dStatus, ['UNSUPPLIED', 'NOT_SUPPLIED', 'PENDING'])) {
                $query->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
            } else {
                $query->where('deliveryStatus', $dStatus);
            }
        }

        if ($request->filled('user_name')) {
            $query->where('userName', 'like', "%{$request->user_name}%");
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('customerName', 'like', "%{$search}%")
                  ->orWhere('customerPhone', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function getStockInQuery(Request $request)
    {
        $query = InventoryLog::where('type', 'STOCK_IN');
        $this->applyDateFilter($query, 'timestamp', $request);

        // 🔒 Privacy Scoping: Branch staff see their shop
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->where('warehouse_id', $user->warehouse_id);
            if ($user->role === 'cashier') {
                $query->where('userId', $user->id);
            }
        } elseif ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('inflow_category')) {
            $cat = strtoupper($request->inflow_category);
            if ($cat === 'SUPPLIER') {
                $query->where('description', 'not like', '%Initial%')->where('description', 'not like', '%Audit%');
            } elseif ($cat === 'OPENING') {
                $query->where('description', 'like', '%Initial%');
            } elseif ($cat === 'AUDIT') {
                $query->where('description', 'like', '%Audit%');
            }
        }

        if ($request->filled('product_id')) {
            $query->where('productId', $request->product_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('productName', 'like', "%{$search}%")
                  ->orWhere('productCode', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('userName', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user_name')) {
            $query->where('userName', 'like', "%{$request->user_name}%");
        }

        return $query;
    }

    public function getStockOutQuery(Request $request)
    {
        $query = InventoryLog::where(function ($q) {
            $q->whereIn('type', [
                'DISPATCH_FULFILLED',
                'STOCK_ADJUSTMENT_DAMAGE',
                'STOCK_ADJUSTMENT_EXPIRED',
                'STOCK_ADJUSTMENT_LOST',
                'TRANSFER_OUT'
            ])->orWhere(function ($sub) {
                $sub->where('quantity', '<', 0)->whereNotIn('type', ['STOCK_IN', 'SALES_RETURN']);
            });
        });
        $this->applyDateFilter($query, 'timestamp', $request);

        // 🔒 Privacy Scoping: Branch staff see their shop
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->where('warehouse_id', $user->warehouse_id);
            if ($user->role === 'cashier') {
                $query->where('userId', $user->id);
            }
        } elseif ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        $outflowParam = $request->get('outflow_type') ?: $request->get('movement_type');
        if (!empty($outflowParam)) {
            $oType = strtoupper($outflowParam);
            if ($oType === 'CUSTOMER_PICKUP' || $oType === 'DISPATCH_FULFILLED') {
                $query->where('type', 'DISPATCH_FULFILLED');
            } elseif ($oType === 'TRANSFER' || $oType === 'TRANSFER_OUT') {
                $query->where('type', 'TRANSFER_OUT');
            } elseif (str_contains($oType, 'DAMAGE')) {
                $query->where('type', 'like', '%DAMAGE%');
            } elseif (str_contains($oType, 'EXPIRED')) {
                $query->where('type', 'like', '%EXPIRED%');
            } elseif (str_contains($oType, 'LOST')) {
                $query->where('type', 'like', '%LOST%');
            }
        }

        if ($request->filled('product_id')) {
            $query->where('productId', $request->product_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('productName', 'like', "%{$search}%")
                  ->orWhere('productCode', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('userName', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user_name')) {
            $query->where('userName', 'like', "%{$request->user_name}%");
        }

        return $query;
    }

    public function getInTransitQuery(Request $request)
    {
        $query = Transfer::with(['items', 'sourceWarehouse', 'destinationWarehouse'])
            ->where('status', 'DISPATCHED');
        $this->applyDateFilter($query, 'dispatched_at', $request);

        // 🔒 Branch Privacy Scoping
        $user = Auth::user();
        if ($user && $user->role !== 'admin' && !empty($user->warehouse_id)) {
            $query->where(function ($q) use ($user) {
                $q->where('source_warehouse_id', $user->warehouse_id)
                  ->orWhere('destination_warehouse_id', $user->warehouse_id);
            });
        }

        if ($request->filled('carrier_name')) {
            $query->where('carrier_name', $request->carrier_name);
        }
        if ($request->filled('source_warehouse_id')) {
            $query->where('source_warehouse_id', $request->source_warehouse_id);
        }
        if ($request->filled('destination_warehouse_id')) {
            $query->where('destination_warehouse_id', $request->destination_warehouse_id);
        }
        if ($request->filled('warehouse_id')) {
            $wId = $request->warehouse_id;
            $query->where(function ($q) use ($wId) {
                $q->where('source_warehouse_id', $wId)
                  ->orWhere('destination_warehouse_id', $wId);
            });
        }
        if ($request->filled('user_name')) {
            $query->where('dispatched_by', 'like', "%{$request->user_name}%");
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('transfer_no', 'like', "%{$search}%")
                  ->orWhere('carrier_name', 'like', "%{$search}%")
                  ->orWhere('dispatched_by', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function getIncomingQuery(Request $request)
    {
        $query = Transfer::with(['items', 'sourceWarehouse', 'destinationWarehouse']);
        $this->applyDateFilter($query, 'created_at', $request);

        // 🔒 Branch Privacy Scoping
        $user = Auth::user();
        if ($user && $user->role !== 'admin' && !empty($user->warehouse_id)) {
            $query->where('destination_warehouse_id', $user->warehouse_id);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('destination_warehouse_id', $request->warehouse_id);
        }
        if ($request->filled('source_warehouse_id')) {
            $query->where('source_warehouse_id', $request->source_warehouse_id);
        }
        if ($request->filled('destination_warehouse_id')) {
            $query->where('destination_warehouse_id', $request->destination_warehouse_id);
        }
        if ($request->filled('transfer_status')) {
            $query->where('status', strtoupper($request->transfer_status));
        }
        if ($request->filled('carrier_name')) {
            $query->where('carrier_name', $request->carrier_name);
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('transfer_no', 'like', "%{$search}%")
                  ->orWhere('carrier_name', 'like', "%{$search}%")
                  ->orWhere('dispatched_by', 'like', "%{$search}%")
                  ->orWhere('received_by', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function getReturnsQuery(Request $request)
    {
        $query = SalesReturn::query();
        $this->applyDateFilter($query, 'createdAt', $request);

        // 🔒 Branch Privacy Scoping
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $user->warehouse_id));
            if ($user->role === 'cashier') {
                $query->where('userId', $user->id);
            }
        } elseif ($request->filled('warehouse_id')) {
            $whId = (int) $request->warehouse_id;
            $query->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $whId));
        }

        if ($request->filled('return_reason')) {
            $query->where('reason', 'like', "%{$request->return_reason}%");
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('saleId', 'like', "%{$search}%")
                  ->orWhere('customerName', 'like', "%{$search}%")
                  ->orWhere('productName', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('userName', 'like', "%{$search}%");
            });
        }
        if ($request->filled('user_name')) {
            $query->where('userName', 'like', "%{$request->user_name}%");
        }

        return $query;
    }

    public function getRefundsQuery(Request $request)
    {
        $query = SalesReturn::where('refundAmount', '>', 0);
        $this->applyDateFilter($query, 'createdAt', $request);

        // 🔒 Branch Privacy Scoping
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $user->warehouse_id));
            if ($user->role === 'cashier') {
                $query->where('userId', $user->id);
            }
        } elseif ($request->filled('warehouse_id')) {
            $whId = (int) $request->warehouse_id;
            $query->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $whId));
        }

        if ($request->filled('min_amount')) {
            $query->where('refundAmount', '>=', (float)$request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where('refundAmount', '<=', (float)$request->max_amount);
        }
        if ($request->filled('user_name')) {
            $query->where('userName', 'like', "%{$request->user_name}%");
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('saleId', 'like', "%{$search}%")
                  ->orWhere('customerName', 'like', "%{$search}%")
                  ->orWhere('userName', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function getDebtsQuery(Request $request)
    {
        $query = CustomerLedger::with('customer');
        $this->applyDateFilter($query, 'created_at', $request);

        // 🔒 Role & Branch Privacy Scoping
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $user->warehouse_id))
                  ->orWhereNull('sale_id');
            });
            if ($user->role === 'cashier') {
                $query->where('recorded_by', $user->name);
            }
        } elseif ($request->filled('warehouse_id')) {
            $whId = (int) $request->warehouse_id;
            $query->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $whId));
        }

        if ($request->filled('ledger_type')) {
            $query->where('type', strtoupper($request->ledger_type));
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_method', strtoupper($request->payment_method));
        }
        if ($request->filled('user_name')) {
            $query->where('recorded_by', 'like', "%{$request->user_name}%");
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                  ->orWhere('recorded_by', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        return $query;
    }

    /**
     * Display searchable, filterable Universal 8-Tab History & Ledgers Hub.
     */
    public function index(Request $request)
    {
        $activeTab = strtolower($request->get('tab', 'sales'));
        $datePreset = $request->get('date_preset', 'ALL');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $warehouseId = $request->get('warehouse_id');
        $search = trim($request->get('search', ''));
        $userName = $request->get('user_name');

        $authUser = Auth::user();
        if ($authUser && $authUser->isBranchScoped()) {
            $warehouses = Warehouse::where('id', $authUser->warehouse_id)->get();
        } else {
            $warehouses = Warehouse::where('is_active', true)->get();
        }
        $cashiers = User::orderBy('name')->pluck('name');
        $carriers = Transfer::distinct()->whereNotNull('carrier_name')->where('carrier_name', '!=', '')->pluck('carrier_name');
        $products = \App\Models\Product::orderBy('name')->get();

        // TAB 1: SALES
        $salesQuery = $this->getSalesQuery($request);
        $totalSalesCount = (clone $salesQuery)->count();
        $totalRevenue = (clone $salesQuery)->sum('totalAmount');
        $saleIds = (clone $salesQuery)->pluck('id');
        $inflows = (float) \App\Models\Payment::whereIn('saleId', $saleIds)
            ->where('amount', '>', 0)
            ->where('method', '!=', 'REFUND_CASH')
            ->sum('amount');
        $cashRefunds = abs((float) \App\Models\Payment::whereIn('saleId', $saleIds)
            ->where('method', 'REFUND_CASH')
            ->sum('amount'));
        $totalPaid = max(0.0, round($inflows - $cashRefunds, 2));
        $returnCredits = (float) \App\Models\SalesReturn::whereIn('saleId', $saleIds)->sum('refundAmount');
        $netPayable = max(0.0, round($totalRevenue - $returnCredits, 2));
        $totalDebt = max(0.0, round($netPayable - $totalPaid, 2));
        $sales = (clone $salesQuery)->orderBy('createdAt', 'desc')->paginate(20, ['*'], 'sales_page')->withQueryString();

        // TAB 2: STOCK IN
        $stockInQuery = $this->getStockInQuery($request);
        $stockInBatches = (clone $stockInQuery)->count();
        $stockInUnits = (clone $stockInQuery)->sum('quantity');
        $stockInProducts = (clone $stockInQuery)->distinct('productId')->count('productId');
        $stockInLogs = (clone $stockInQuery)->orderBy('timestamp', 'desc')->paginate(20, ['*'], 'stock_in_page')->withQueryString();

        // TAB 3: STOCK OUT
        $stockOutQuery = $this->getStockOutQuery($request);
        $stockOutCount = (clone $stockOutQuery)->count();
        $stockOutUnits = abs((clone $stockOutQuery)->sum('quantity'));
        $stockOutProducts = (clone $stockOutQuery)->distinct('productId')->count('productId');
        $stockOutFulfilled = (clone $stockOutQuery)->where('type', 'DISPATCH_FULFILLED')->count();
        $stockOutLogs = (clone $stockOutQuery)->orderBy('timestamp', 'desc')->paginate(20, ['*'], 'stock_out_page')->withQueryString();

        // TAB 4: IN-TRANSIT
        $inTransitQuery = $this->getInTransitQuery($request);
        $inTransitCount = (clone $inTransitQuery)->count();
        $inTransitIds = (clone $inTransitQuery)->pluck('id');
        $inTransitUnits = TransferItem::whereIn('transfer_id', $inTransitIds)->sum('dispatched_qty');
        $inTransitCarriers = (clone $inTransitQuery)->distinct('carrier_name')->count('carrier_name');
        $inTransitTransfers = (clone $inTransitQuery)->orderBy('dispatched_at', 'desc')->paginate(20, ['*'], 'transit_page')->withQueryString();

        // TAB 5: INCOMING TRANSFERS
        $incomingQuery = $this->getIncomingQuery($request);
        $incomingTotal = (clone $incomingQuery)->count();
        $incomingPending = (clone $incomingQuery)->where('status', 'DISPATCHED')->count();
        $incomingReceived = (clone $incomingQuery)->where('status', 'RECEIVED')->count();
        $incomingDiscrepancies = (clone $incomingQuery)->where('status', 'DISCREPANCY')->count();
        $incomingUnits = (clone $incomingQuery)->where('status', 'RECEIVED')->with('items')->get()->sum(fn($t) => $t->items->sum('received_qty'));
        $incomingTransfers = (clone $incomingQuery)->orderBy('created_at', 'desc')->paginate(20, ['*'], 'incoming_page')->withQueryString();

        // TAB 6: RETURNS
        $returnsQuery = $this->getReturnsQuery($request);
        $returnsCount = (clone $returnsQuery)->count();
        $returnedUnits = (clone $returnsQuery)->sum('quantity');
        $returnedValue = (clone $returnsQuery)->sum('refundAmount');
        $salesReturns = (clone $returnsQuery)->orderBy('createdAt', 'desc')->paginate(20, ['*'], 'returns_page')->withQueryString();

        // TAB 7: REFUNDS
        $refundsQuery = $this->getRefundsQuery($request);
        $refundsCount = (clone $refundsQuery)->count();
        $totalRefundAmount = (clone $refundsQuery)->sum('refundAmount');
        $refundRecords = (clone $refundsQuery)->orderBy('createdAt', 'desc')->paginate(20, ['*'], 'refunds_page')->withQueryString();

        // TAB 8: DEBTS
        $debtsQuery = $this->getDebtsQuery($request);
        $debtsEntryCount = (clone $debtsQuery)->count();
        $totalRepayments = (clone $debtsQuery)->where('type', 'PAYMENT')->sum('amount');
        $totalDebtCreated = (clone $debtsQuery)->where('type', 'INVOICE')->sum('amount');
        if ($authUser && $authUser->isBranchScoped()) {
            $openSalesBranch = Sale::where('warehouse_id', $authUser->warehouse_id)
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->get();
            $branchOpenDebt = 0.0;
            $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
            foreach ($openSalesBranch as $os) {
                $branchOpenDebt += $accountingService->calculateInvoiceBalance($os);
            }
            $totalOpenDebt = round($branchOpenDebt, 2);
        } else {
            $totalOpenDebt = (float) Customer::sum('total_debt');
        }
        $debtLedgers = (clone $debtsQuery)->orderBy('created_at', 'desc')->paginate(20, ['*'], 'debts_page')->withQueryString();

        return view('transactions.index', compact(
            'activeTab',
            'warehouses',
            'cashiers',
            'carriers',
            'datePreset',
            'fromDate',
            'toDate',
            'warehouseId',
            'search',
            'userName',
            'products',
            // Tab 1: Sales
            'sales', 'totalSalesCount', 'totalRevenue', 'totalPaid', 'totalDebt',
            // Tab 2: Stock In
            'stockInLogs', 'stockInBatches', 'stockInUnits', 'stockInProducts',
            // Tab 3: Stock Out
            'stockOutLogs', 'stockOutCount', 'stockOutUnits', 'stockOutProducts', 'stockOutFulfilled',
            // Tab 4: In-Transit
            'inTransitTransfers', 'inTransitCount', 'inTransitUnits', 'inTransitCarriers',
            // Tab 5: Incoming Transfers
            'incomingTransfers', 'incomingTotal', 'incomingPending', 'incomingReceived', 'incomingDiscrepancies', 'incomingUnits',
            // Tab 6: Returns
            'salesReturns', 'returnsCount', 'returnedUnits', 'returnedValue',
            // Tab 7: Refunds
            'refundRecords', 'refundsCount', 'totalRefundAmount',
            // Tab 8: Debts
            'debtLedgers', 'debtsEntryCount', 'totalRepayments', 'totalDebtCreated', 'totalOpenDebt'
        ));
    }

    /**
     * Export Filtered Dataset for any of the 8 Tabs to Memory-Safe CSV.
     */
    public function exportCsv(Request $request, string $tab)
    {
        $tab = strtolower($tab);
        $fileName = "hysam_{$tab}_filtered_" . date('Y_m_d_His') . ".csv";

        return new StreamedResponse(function () use ($request, $tab) {
            $handle = fopen('php://output', 'w');

            if ($tab === 'sales') {
                fputcsv($handle, ['Invoice ID', 'Date & Time', 'Customer Name', 'Customer Phone', 'Items Count', 'Gross Total (NGN)', 'Paid Amount (NGN)', 'Debt Balance (NGN)', 'Payment Status', 'Handover Status', 'Cashier Name']);
                $query = $this->getSalesQuery($request)->orderBy('createdAt', 'desc');
                foreach ($query->cursor() as $s) {
                    $debt = max(0, $s->totalAmount - $s->paidAmount);
                    $pStatus = ($s->paidAmount >= $s->totalAmount) ? 'PAID' : (($s->paidAmount > 0) ? 'PART_PAID' : 'NOT_PAID');
                    fputcsv($handle, [
                        $s->id,
                        $s->createdAt,
                        $s->customerName,
                        $s->customerPhone ?? 'N/A',
                        $s->items->count(),
                        $s->totalAmount,
                        $s->paidAmount,
                        $debt,
                        $pStatus,
                        $s->deliveryStatus,
                        $s->userName
                    ]);
                }
            } elseif ($tab === 'stock_in') {
                fputcsv($handle, ['Log ID', 'Date & Time', 'SKU / Barcode', 'Product Name', 'Inflow Type', 'Quantity (Units)', 'Received By Staff', 'Supplier & Notes']);
                $query = $this->getStockInQuery($request)->orderBy('timestamp', 'desc');
                foreach ($query->cursor() as $l) {
                    fputcsv($handle, [
                        $l->id,
                        $l->timestamp,
                        $l->productCode,
                        $l->productName,
                        $l->type,
                        $l->quantity,
                        $l->userName,
                        $l->description
                    ]);
                }
            } elseif ($tab === 'stock_out') {
                fputcsv($handle, ['Log ID', 'Date & Time', 'SKU / Barcode', 'Product Name', 'Outflow Type', 'Quantity Deducted (Units)', 'Authorized Staff', 'Reason & Details']);
                $query = $this->getStockOutQuery($request)->orderBy('timestamp', 'desc');
                foreach ($query->cursor() as $l) {
                    fputcsv($handle, [
                        $l->id,
                        $l->timestamp,
                        $l->productCode,
                        $l->productName,
                        $l->type,
                        abs($l->quantity),
                        $l->userName,
                        $l->description
                    ]);
                }
            } elseif ($tab === 'in_transit') {
                fputcsv($handle, ['Transfer No', 'Dispatched Date', 'Source Branch', 'Destination Branch', 'Carrier Driver', 'Dispatched Units', 'Status', 'Dispatched By', 'Notes']);
                $query = $this->getInTransitQuery($request)->orderBy('dispatched_at', 'desc');
                foreach ($query->cursor() as $t) {
                    fputcsv($handle, [
                        $t->transfer_no,
                        $t->dispatched_at ?? $t->created_at,
                        $t->sourceWarehouse->name ?? 'Origin',
                        $t->destinationWarehouse->name ?? 'Destination',
                        $t->carrier_name,
                        $t->items->sum('dispatched_qty'),
                        $t->status,
                        $t->dispatched_by,
                        $t->notes ?? ''
                    ]);
                }
            } elseif ($tab === 'incoming' || $tab === 'transfers_in') {
                fputcsv($handle, ['Transfer No', 'Date Created', 'Source Branch', 'Destination Branch', 'Carrier Driver', 'Dispatched Units', 'Received Units', 'Discrepancy Units', 'Status', 'Dispatched By', 'Received By']);
                $query = $this->getIncomingQuery($request)->orderBy('created_at', 'desc');
                foreach ($query->cursor() as $t) {
                    fputcsv($handle, [
                        $t->transfer_no,
                        $t->created_at,
                        $t->sourceWarehouse->name ?? 'Origin',
                        $t->destinationWarehouse->name ?? 'Destination',
                        $t->carrier_name,
                        $t->items->sum('dispatched_qty'),
                        $t->items->sum('received_qty'),
                        $t->items->sum('discrepancy_qty'),
                        $t->status,
                        $t->dispatched_by,
                        $t->received_by ?? 'Pending'
                    ]);
                }
            } elseif ($tab === 'returns') {
                fputcsv($handle, ['Return ID', 'Date & Time', 'Original Sale ID', 'Customer Name', 'SKU', 'Product Name', 'Returned Qty', 'Refunded Amount (NGN)', 'Reason', 'Received By Staff']);
                $query = $this->getReturnsQuery($request)->orderBy('createdAt', 'desc');
                foreach ($query->cursor() as $r) {
                    fputcsv($handle, [
                        $r->code ?? $r->id,
                        $r->createdAt,
                        $r->saleId,
                        $r->customerName,
                        $r->productCode,
                        $r->productName,
                        $r->quantity,
                        $r->refundAmount,
                        $r->reason,
                        $r->userName
                    ]);
                }
            } elseif ($tab === 'refunds') {
                fputcsv($handle, ['Return/Refund ID', 'Date & Time', 'Original Sale ID', 'Customer Name', 'Product Name', 'Refund Amount (NGN)', 'Refund Mode / Reason', 'Processed By']);
                $query = $this->getRefundsQuery($request)->orderBy('createdAt', 'desc');
                foreach ($query->cursor() as $r) {
                    fputcsv($handle, [
                        $r->code ?? $r->id,
                        $r->createdAt,
                        $r->saleId,
                        $r->customerName,
                        $r->productName,
                        $r->refundAmount,
                        $r->reason,
                        $r->userName
                    ]);
                }
            } elseif ($tab === 'debts') {
                fputcsv($handle, ['Ledger ID', 'Date & Time', 'Customer Name', 'Customer Phone', 'Transaction Type', 'Amount (NGN)', 'Balance After (NGN)', 'Payment Method', 'Reference No', 'Recorded By', 'Notes']);
                $query = $this->getDebtsQuery($request)->orderBy('created_at', 'desc');
                foreach ($query->cursor() as $d) {
                    fputcsv($handle, [
                        $d->id,
                        $d->created_at,
                        $d->customer->name ?? 'N/A',
                        $d->customer->phone ?? 'N/A',
                        $d->type,
                        $d->amount,
                        $d->balance_after,
                        $d->payment_method ?? 'N/A',
                        $d->reference_no ?? 'N/A',
                        $d->recorded_by,
                        $d->notes ?? ''
                    ]);
                }
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Export Filtered Dataset for any of the 8 Tabs to Structured JSON.
     */
    public function exportJson(Request $request, string $tab)
    {
        $tab = strtolower($tab);
        $fileName = "hysam_{$tab}_filtered_" . date('Y_m_d_His') . ".json";

        $records = match ($tab) {
            'sales' => $this->getSalesQuery($request)->orderBy('createdAt', 'desc')->get(),
            'stock_in' => $this->getStockInQuery($request)->orderBy('timestamp', 'desc')->get(),
            'stock_out' => $this->getStockOutQuery($request)->orderBy('timestamp', 'desc')->get(),
            'in_transit' => $this->getInTransitQuery($request)->orderBy('dispatched_at', 'desc')->get(),
            'incoming', 'transfers_in' => $this->getIncomingQuery($request)->orderBy('created_at', 'desc')->get(),
            'returns' => $this->getReturnsQuery($request)->orderBy('createdAt', 'desc')->get(),
            'refunds' => $this->getRefundsQuery($request)->orderBy('createdAt', 'desc')->get(),
            'debts' => $this->getDebtsQuery($request)->orderBy('created_at', 'desc')->get(),
            default => [],
        };

        $data = [
            'metadata' => [
                'tab' => $tab,
                'generated_at' => now()->toIso8601String(),
                'filters_applied' => $request->except(['tab']),
                'total_records' => count($records),
            ],
            'data' => $records,
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ], JSON_PRETTY_PRINT);
    }
}
