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
     * Helper to apply date filtering consistently across models via centralized AccountingReportService.
     */
    private function applyDateFilter($query, string $dateColumn, Request $request): void
    {
        $accountingService = app(\App\Services\Accounting\AccountingReportService::class);
        $accountingService->applyDateFilterToQuery($query, $dateColumn, [
            'date_preset' => $request->get('date_preset', 'ALL'),
            'from_date'   => $request->get('from_date'),
            'to_date'     => $request->get('to_date'),
        ]);
    }

    /**
     * Resolve effective warehouse filter (request query parameter takes priority, falls back to active session).
     */
    private function getEffectiveWarehouseId(Request $request): ?string
    {
        if ($request->has('warehouse_id')) {
            $val = $request->warehouse_id;
            return ($val === 'ALL' || $val === '' || is_null($val)) ? null : (string) $val;
        }
        $sessionWh = session('active_warehouse_id');
        return ($sessionWh === 'ALL' || $sessionWh === '' || is_null($sessionWh)) ? null : (string) $sessionWh;
    }

    /**
     * Shared Query Builders for each of the 8 Tabs (Role-Scoped for Privacy & Fraud Prevention)
     */
    public function getSalesQuery(Request $request)
    {
        $query = Sale::with('items');
        $this->applyDateFilter($query, 'createdAt', $request);

        $effectiveWh = $this->getEffectiveWarehouseId($request);
        // 🔒 Role & Branch Privacy Scoping
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->where('warehouse_id', $user->warehouse_id);
            if ($user->role === 'cashier') {
                $query->where('userId', $user->id);
            }
        } elseif (!empty($effectiveWh)) {
            $query->where('warehouse_id', $effectiveWh);
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
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('phone', 'like', "%{$search}%");
                  });

                if (\Illuminate\Support\Facades\Schema::hasColumn('sales', 'customerPhone')) {
                    $q->orWhere('customerPhone', 'like', "%{$search}%");
                }
            });
        }

        return $query;
    }

    public function getStockInQuery(Request $request)
    {
        $query = InventoryLog::where('type', 'STOCK_IN');
        $this->applyDateFilter($query, 'timestamp', $request);

        $effectiveWh = $this->getEffectiveWarehouseId($request);
        // 🔒 Privacy Scoping: Branch staff see their shop
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->where('warehouse_id', $user->warehouse_id);
            if ($user->role === 'cashier') {
                $query->where('userId', $user->id);
            }
        } elseif (!empty($effectiveWh)) {
            $query->where('warehouse_id', $effectiveWh);
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
                'SALE',
                'DISPATCH_FULFILLED',
                'STOCK_ADJUSTMENT_DAMAGE',
                'STOCK_ADJUSTMENT_EXPIRED',
                'STOCK_ADJUSTMENT_LOST',
                'TRANSFER_OUT',
                'STOCK_OUT'
            ])->orWhere(function ($sub) {
                $sub->where('quantity', '<', 0)->whereNotIn('type', ['STOCK_IN', 'TRANSFER_IN', 'RETURN', 'SALES_RETURN']);
            });
        });
        $this->applyDateFilter($query, 'timestamp', $request);

        $effectiveWh = $this->getEffectiveWarehouseId($request);
        // 🔒 Privacy Scoping: Branch staff see their shop
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->where('warehouse_id', $user->warehouse_id);
            if ($user->role === 'cashier') {
                $query->where('userId', $user->id);
            }
        } elseif (!empty($effectiveWh)) {
            $query->where('warehouse_id', $effectiveWh);
        }

        $outflowParam = $request->get('outflow_type') ?: $request->get('movement_type');
        if (!empty($outflowParam)) {
            $oType = strtoupper($outflowParam);
            if ($oType === 'CUSTOMER_PICKUP' || $oType === 'DISPATCH_FULFILLED') {
                $query->where('type', 'DISPATCH_FULFILLED');
            } elseif ($oType === 'SALE' || $oType === 'RETAIL_SALE') {
                $query->where('type', 'SALE');
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

        $effectiveWh = $this->getEffectiveWarehouseId($request);
        // 🔒 Branch Privacy Scoping
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $user->warehouse_id));
            if ($user->role === 'cashier') {
                $query->where('userId', $user->id);
            }
        } elseif (!empty($effectiveWh)) {
            $query->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $effectiveWh));
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

        $effectiveWh = $this->getEffectiveWarehouseId($request);
        // 🔒 Branch Privacy Scoping
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $user->warehouse_id));
            if ($user->role === 'cashier') {
                $query->where('userId', $user->id);
            }
        } elseif (!empty($effectiveWh)) {
            $query->whereHas('sale', fn($sq) => $sq->where('warehouse_id', $effectiveWh));
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
        $warehouseId = $this->getEffectiveWarehouseId($request);
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

        $viewData = compact(
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
        );

        if ($request->ajax() || $request->header('X-Partial-Update') || $request->has('_partial')) {
            return response()->json([
                'success'   => true,
                'activeTab' => $activeTab,
                'counts'    => [
                    'sales'        => number_format($totalSalesCount),
                    'stock_in'     => number_format($stockInBatches),
                    'stock_out'    => number_format($stockOutCount),
                    'in_transit'   => number_format($inTransitCount),
                    'transfers_in' => number_format($incomingTotal),
                    'returns'      => number_format($returnsCount),
                    'refunds'      => number_format($refundsCount),
                    'debts'        => number_format($debtsEntryCount),
                ],
                'panes_html' => view('transactions.partials.panes', $viewData)->render(),
            ]);
        }

        return view('transactions.index', $viewData);
    }

    /**
     * Export Filtered Dataset for any of the 8 Tabs to Memory-Safe CSV.
     */
    public function exportCsv(Request $request, string $tab)
    {
        $tab = strtolower($tab);
        $fileName = ($tab === 'all')
            ? "hysam_universal_ledgers_all_tabs_filtered_" . date('Y_m_d_His') . ".csv"
            : "hysam_{$tab}_filtered_" . date('Y_m_d_His') . ".csv";

        return new StreamedResponse(function () use ($request, $tab) {
            $handle = fopen('php://output', 'w');

            if ($tab === 'all') {
                $user = Auth::user();
                $staffDesc = $user ? "{$user->name} (" . ucfirst($user->role ?? 'staff') . ")" : 'System Administrator';

                $datePreset = $request->get('date_preset', 'ALL');
                $fromDate = $request->get('from_date');
                $toDate = $request->get('to_date');
                if (!empty($fromDate) || !empty($toDate)) {
                    $dateDesc = "Custom Range (" . ($fromDate ?: 'Earliest') . " to " . ($toDate ?: 'Latest') . ")";
                } elseif ($datePreset && $datePreset !== 'ALL') {
                    $dateDesc = "Preset: " . str_replace('_', ' ', strtoupper($datePreset));
                } else {
                    $dateDesc = "All Time (Unrestricted)";
                }

                $whId = $this->getEffectiveWarehouseId($request);
                if ($whId) {
                    $wh = Warehouse::find($whId);
                    $whDesc = $wh ? "{$wh->name}" : "Warehouse #{$whId}";
                } else {
                    $whDesc = "All Warehouses & Branches (Consolidated)";
                }

                $searchVal = trim($request->get('search', ''));
                $searchDesc = !empty($searchVal) ? "\"{$searchVal}\"" : "None (All Records)";

                // ─────────────────────────────────────────────────────────────
                // TOP CONSOLIDATED AUDIT HEADER & METADATA
                // ─────────────────────────────────────────────────────────────
                fputcsv($handle, ['====================================================================================================']);
                fputcsv($handle, ['HYSAM UNIVERSAL HISTORY & LEDGERS HUB - MASTER AUDIT REPORT']);
                fputcsv($handle, ['====================================================================================================']);
                fputcsv($handle, ['Export Timestamp:', date('Y-m-d H:i:s T')]);
                fputcsv($handle, ['Generated By:', $staffDesc]);
                fputcsv($handle, ['Active Date Filter:', $dateDesc]);
                fputcsv($handle, ['Branch / Warehouse Scope:', $whDesc]);
                fputcsv($handle, ['Search Filter:', $searchDesc]);
                fputcsv($handle, ['Included Modules (8):', 'Sales, Stock In, Stock Out, Transfers In-Transit, Transfers Received, Returns, Refunds, Debt Ledgers']);
                fputcsv($handle, ['====================================================================================================']);
                fputcsv($handle, []);

                // ─────────────────────────────────────────────────────────────
                // SECTION 1: SALES TRANSACTIONS
                // ─────────────────────────────────────────────────────────────
                fputcsv($handle, ['>>> SECTION 1: SALES TRANSACTIONS']);
                fputcsv($handle, ['Invoice ID', 'Date & Time', 'Customer Name', 'Customer Phone', 'Items Count', 'Gross Total (NGN)', 'Paid Amount (NGN)', 'Debt Balance (NGN)', 'Payment Status', 'Handover Status', 'Cashier Name']);
                $salesQuery = $this->getSalesQuery($request)->orderBy('createdAt', 'desc');
                $salesCount = 0;
                $salesGross = 0;
                $salesPaid = 0;
                $salesDebt = 0;
                foreach ($salesQuery->cursor() as $s) {
                    $salesCount++;
                    $debt = max(0, $s->totalAmount - $s->paidAmount);
                    $salesGross += (float)$s->totalAmount;
                    $salesPaid += (float)$s->paidAmount;
                    $salesDebt += (float)$debt;
                    $pStatus = ($s->paidAmount >= $s->totalAmount) ? 'PAID' : (($s->paidAmount > 0) ? 'PART_PAID' : 'NOT_PAID');
                    fputcsv($handle, [
                        $s->id,
                        $s->createdAt,
                        $s->customerName,
                        $s->customerPhone ?? $s->customer?->phone ?? 'N/A',
                        $s->items->count(),
                        $s->totalAmount,
                        $s->paidAmount,
                        $debt,
                        $pStatus,
                        $s->deliveryStatus,
                        $s->userName
                    ]);
                }
                fputcsv($handle, ['[SALES SUMMARY]', "Total Invoices: {$salesCount}", '', '', '', "Gross: " . number_format($salesGross, 2), "Paid: " . number_format($salesPaid, 2), "Debt: " . number_format($salesDebt, 2), '', '', '']);
                fputcsv($handle, []);

                // ─────────────────────────────────────────────────────────────
                // SECTION 2: STOCK INFLOW & WAREHOUSE RECEIPTS
                // ─────────────────────────────────────────────────────────────
                fputcsv($handle, ['>>> SECTION 2: STOCK INFLOW & WAREHOUSE RECEIPTS']);
                fputcsv($handle, ['Log ID', 'Date & Time', 'SKU / Barcode', 'Product Name', 'Inflow Type', 'Quantity (Units)', 'Received By Staff', 'Supplier & Notes']);
                $stockInQuery = $this->getStockInQuery($request)->orderBy('timestamp', 'desc');
                $inCount = 0;
                $inUnits = 0;
                foreach ($stockInQuery->cursor() as $l) {
                    $inCount++;
                    $inUnits += (float)$l->quantity;
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
                fputcsv($handle, ['[STOCK IN SUMMARY]', "Total Logs: {$inCount}", '', '', '', "Total Units Received: " . number_format($inUnits), '', '']);
                fputcsv($handle, []);

                // ─────────────────────────────────────────────────────────────
                // SECTION 3: STOCK OUTFLOW & INVENTORY DEDUCTIONS
                // ─────────────────────────────────────────────────────────────
                fputcsv($handle, ['>>> SECTION 3: STOCK OUTFLOW & INVENTORY DEDUCTIONS']);
                fputcsv($handle, ['Log ID', 'Date & Time', 'SKU / Barcode', 'Product Name', 'Outflow Type', 'Quantity Deducted (Units)', 'Authorized Staff', 'Reason & Details']);
                $stockOutQuery = $this->getStockOutQuery($request)->orderBy('timestamp', 'desc');
                $outCount = 0;
                $outUnits = 0;
                foreach ($stockOutQuery->cursor() as $l) {
                    $outCount++;
                    $outUnits += abs((float)$l->quantity);
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
                fputcsv($handle, ['[STOCK OUT SUMMARY]', "Total Logs: {$outCount}", '', '', '', "Total Units Deducted: " . number_format($outUnits), '', '']);
                fputcsv($handle, []);

                // ─────────────────────────────────────────────────────────────
                // SECTION 4: SHOP TRANSFERS (IN TRANSIT)
                // ─────────────────────────────────────────────────────────────
                fputcsv($handle, ['>>> SECTION 4: SHOP TRANSFERS (IN TRANSIT)']);
                fputcsv($handle, ['Transfer No', 'Dispatched Date', 'Source Branch', 'Destination Branch', 'Carrier Driver', 'Dispatched Units', 'Status', 'Dispatched By', 'Notes']);
                $inTransitQuery = $this->getInTransitQuery($request)->orderBy('dispatched_at', 'desc');
                $inTransitCount = 0;
                $inTransitUnits = 0;
                foreach ($inTransitQuery->cursor() as $t) {
                    $inTransitCount++;
                    $units = (float)$t->items->sum('dispatched_qty');
                    $inTransitUnits += $units;
                    fputcsv($handle, [
                        $t->transfer_no,
                        $t->dispatched_at ?? $t->created_at,
                        $t->sourceWarehouse->name ?? 'Origin',
                        $t->destinationWarehouse->name ?? 'Destination',
                        $t->carrier_name,
                        $units,
                        $t->status,
                        $t->dispatched_by,
                        $t->notes ?? ''
                    ]);
                }
                fputcsv($handle, ['[IN-TRANSIT SUMMARY]', "Total Transfers: {$inTransitCount}", '', '', '', "Units In Transit: " . number_format($inTransitUnits), '', '', '']);
                fputcsv($handle, []);

                // ─────────────────────────────────────────────────────────────
                // SECTION 5: SHOP TRANSFERS (RECEIVED & RECONCILED)
                // ─────────────────────────────────────────────────────────────
                fputcsv($handle, ['>>> SECTION 5: SHOP TRANSFERS (RECEIVED & RECONCILED)']);
                fputcsv($handle, ['Transfer No', 'Date Created', 'Source Branch', 'Destination Branch', 'Carrier Driver', 'Dispatched Units', 'Received Units', 'Discrepancy Units', 'Status', 'Dispatched By', 'Received By']);
                $incomingQuery = $this->getIncomingQuery($request)->orderBy('created_at', 'desc');
                $recvCount = 0;
                $recvDispUnits = 0;
                $recvUnits = 0;
                $recvDiscUnits = 0;
                foreach ($incomingQuery->cursor() as $t) {
                    $recvCount++;
                    $dQty = (float)$t->items->sum('dispatched_qty');
                    $rQty = (float)$t->items->sum('received_qty');
                    $discQty = (float)$t->items->sum('discrepancy_qty');
                    $recvDispUnits += $dQty;
                    $recvUnits += $rQty;
                    $recvDiscUnits += $discQty;
                    fputcsv($handle, [
                        $t->transfer_no,
                        $t->created_at,
                        $t->sourceWarehouse->name ?? 'Origin',
                        $t->destinationWarehouse->name ?? 'Destination',
                        $t->carrier_name,
                        $dQty,
                        $rQty,
                        $discQty,
                        $t->status,
                        $t->dispatched_by,
                        $t->received_by ?? 'Pending'
                    ]);
                }
                fputcsv($handle, ['[RECEIVED TRANSFERS SUMMARY]', "Total Transfers: {$recvCount}", '', '', '', "Dispatched: " . number_format($recvDispUnits), "Received: " . number_format($recvUnits), "Discrepancy: " . number_format($recvDiscUnits), '', '', '']);
                fputcsv($handle, []);

                // ─────────────────────────────────────────────────────────────
                // SECTION 6: PRODUCT & SALES RETURNS
                // ─────────────────────────────────────────────────────────────
                fputcsv($handle, ['>>> SECTION 6: PRODUCT & SALES RETURNS']);
                fputcsv($handle, ['Return ID', 'Date & Time', 'Original Sale ID', 'Customer Name', 'SKU', 'Product Name', 'Returned Qty', 'Refunded Amount (NGN)', 'Reason', 'Received By Staff']);
                $returnsQuery = $this->getReturnsQuery($request)->orderBy('createdAt', 'desc');
                $retCount = 0;
                $retQty = 0;
                $retRefund = 0;
                foreach ($returnsQuery->cursor() as $r) {
                    $retCount++;
                    $retQty += (float)$r->quantity;
                    $retRefund += (float)$r->refundAmount;
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
                fputcsv($handle, ['[RETURNS SUMMARY]', "Total Returns: {$retCount}", '', '', '', '', "Returned Units: " . number_format($retQty), "Refund Value: " . number_format($retRefund, 2), '', '']);
                fputcsv($handle, []);

                // ─────────────────────────────────────────────────────────────
                // SECTION 7: REFUNDS ISSUED
                // ─────────────────────────────────────────────────────────────
                fputcsv($handle, ['>>> SECTION 7: REFUNDS ISSUED']);
                fputcsv($handle, ['Return/Refund ID', 'Date & Time', 'Original Sale ID', 'Customer Name', 'Product Name', 'Refund Amount (NGN)', 'Refund Mode / Reason', 'Processed By']);
                $refundsQuery = $this->getRefundsQuery($request)->orderBy('createdAt', 'desc');
                $refCount = 0;
                $refTotal = 0;
                foreach ($refundsQuery->cursor() as $r) {
                    $refCount++;
                    $refTotal += (float)$r->refundAmount;
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
                fputcsv($handle, ['[REFUNDS SUMMARY]', "Total Refunds: {$refCount}", '', '', '', "Total Outflow: " . number_format($refTotal, 2), '', '']);
                fputcsv($handle, []);

                // ─────────────────────────────────────────────────────────────
                // SECTION 8: CUSTOMER DEBT RECOVERIES & LEDGER
                // ─────────────────────────────────────────────────────────────
                fputcsv($handle, ['>>> SECTION 8: CUSTOMER DEBT RECOVERIES & LEDGER']);
                fputcsv($handle, ['Ledger ID', 'Date & Time', 'Customer Name', 'Customer Phone', 'Transaction Type', 'Amount (NGN)', 'Balance After (NGN)', 'Payment Method', 'Reference No', 'Recorded By', 'Notes']);
                $debtsQuery = $this->getDebtsQuery($request)->orderBy('created_at', 'desc');
                $debtsCount = 0;
                $debtsVolume = 0;
                foreach ($debtsQuery->cursor() as $d) {
                    $debtsCount++;
                    $debtsVolume += (float)$d->amount;
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
                fputcsv($handle, ['[DEBTS SUMMARY]', "Total Entries: {$debtsCount}", '', '', '', "Total Volume: " . number_format($debtsVolume, 2), '', '', '', '', '']);
                fputcsv($handle, []);

                fputcsv($handle, ['====================================================================================================']);
                fputcsv($handle, ['END OF REPORT - HYSAM VMPOS UNIVERSAL LEDGERS MASTER AUDIT']);
                fputcsv($handle, ['====================================================================================================']);

            } elseif ($tab === 'sales') {
                fputcsv($handle, ['Invoice ID', 'Date & Time', 'Customer Name', 'Customer Phone', 'Items Count', 'Gross Total (NGN)', 'Paid Amount (NGN)', 'Debt Balance (NGN)', 'Payment Status', 'Handover Status', 'Cashier Name']);
                $query = $this->getSalesQuery($request)->orderBy('createdAt', 'desc');
                foreach ($query->cursor() as $s) {
                    $debt = max(0, $s->totalAmount - $s->paidAmount);
                    $pStatus = ($s->paidAmount >= $s->totalAmount) ? 'PAID' : (($s->paidAmount > 0) ? 'PART_PAID' : 'NOT_PAID');
                    fputcsv($handle, [
                        $s->id,
                        $s->createdAt,
                        $s->customerName,
                        $s->customerPhone ?? $s->customer?->phone ?? 'N/A',
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
        $fileName = ($tab === 'all')
            ? "hysam_universal_ledgers_all_tabs_filtered_" . date('Y_m_d_His') . ".json"
            : "hysam_{$tab}_filtered_" . date('Y_m_d_His') . ".json";

        if ($tab === 'all') {
            $data = [
                'metadata' => [
                    'tab' => 'all',
                    'report' => 'Hysam Universal History & Ledgers Hub - Master Audit Report',
                    'generated_at' => now()->toIso8601String(),
                    'filters_applied' => $request->except(['tab']),
                ],
                'data' => [
                    'sales' => $this->getSalesQuery($request)->orderBy('createdAt', 'desc')->get(),
                    'stock_in' => $this->getStockInQuery($request)->orderBy('timestamp', 'desc')->get(),
                    'stock_out' => $this->getStockOutQuery($request)->orderBy('timestamp', 'desc')->get(),
                    'in_transit' => $this->getInTransitQuery($request)->orderBy('dispatched_at', 'desc')->get(),
                    'incoming' => $this->getIncomingQuery($request)->orderBy('created_at', 'desc')->get(),
                    'returns' => $this->getReturnsQuery($request)->orderBy('createdAt', 'desc')->get(),
                    'refunds' => $this->getRefundsQuery($request)->orderBy('createdAt', 'desc')->get(),
                    'debts' => $this->getDebtsQuery($request)->orderBy('created_at', 'desc')->get(),
                ],
            ];

            return response()->json($data, 200, [
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ], JSON_PRETTY_PRINT);
        }

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
