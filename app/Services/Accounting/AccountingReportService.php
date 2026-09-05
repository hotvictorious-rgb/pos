<?php

namespace App\Services\Accounting;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\InventoryLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingReportService
{
    /**
     * Convert any Naira denomination strictly to integer kobo without binary floating-point representation.
     * Parses decimal strings, integers, and numeric values directly.
     * Handles signs, commas, whitespace, and performs half-up rounding on fractional sub-kobo digits.
     */
    public static function toKobo(float|int|string|null $naira): int
    {
        if ($naira === null || $naira === '') {
            return 0;
        }

        if (is_int($naira)) {
            return $naira * 100;
        }

        $str = trim((string) $naira);
        if ($str === '' || $str === '0') {
            return 0;
        }

        $isNegative = str_starts_with($str, '-');
        if ($isNegative) {
            $str = substr($str, 1);
        }

        // Clean out thousands commas and whitespace
        $str = str_replace([',', ' '], '', $str);

        if (str_contains($str, '.')) {
            [$whole, $fraction] = explode('.', $str, 2);
            $whole = ($whole === '' || $whole === '0') ? '0' : ltrim($whole, '0');
            if ($whole === '') {
                $whole = '0';
            }

            // Normalise fraction to at least 3 digits for half-up rounding
            $fraction = rtrim($fraction, " \t\n\r\0\x0B");
            $padded = substr($fraction . '000', 0, 3);
            $cents = (int) substr($padded, 0, 2);
            $subKobo = (int) substr($padded, 2, 1);

            $kobo = ((int) $whole * 100) + $cents;
            if ($subKobo >= 5) {
                $kobo += 1;
            }
        } else {
            $whole = ($str === '' || $str === '0') ? '0' : ltrim($str, '0');
            if ($whole === '') {
                $whole = '0';
            }
            $kobo = (int) $whole * 100;
        }

        return $isNegative ? -$kobo : $kobo;
    }

    /**
     * Convert integer kobo strictly back to standard two-decimal Naira representation.
     */
    public static function toNaira(int $kobo): float
    {
        return round($kobo / 100, 2);
    }

    /**
     * Format integer kobo strictly to an exact two-decimal string representation with zero float conversion.
     */
    public static function formatKoboToNaira(int $kobo): string
    {
        $isNegative = $kobo < 0;
        $abs = abs($kobo);
        $whole = intdiv($abs, 100);
        $cents = $abs % 100;
        return sprintf('%s%d.%02d', $isNegative ? '-' : '', $whole, $cents);
    }

    /**
     * Authoritative calculation and validation for checkout pricing and multi-tender settlement.
     * Strictly limits payment options to CASH and POS.
     *
     * @param array $items Line items from client
     * @param array $tender Tender breakdown ['cashAmount' => ..., 'posAmount' => ..., 'paidAmount' => ...]
     * @param string|null $saleType Sale type (defaults to RETAIL)
     * @return array Calculated authoritative sale and tender attributes
     */
    public function calculateCheckout(array $items, array $tender, ?string $saleType = 'RETAIL'): array
    {
        if (empty($items)) {
            throw new \InvalidArgumentException("Checkout requires at least one sale line item.");
        }

        // 1. Consolidate duplicate product SKU lines deterministically before pricing math
        $consolidated = [];
        foreach ($items as $idx => $item) {
            $productId = $item['productId'] ?? $item['product_id'] ?? null;
            if (!$productId) {
                throw new \InvalidArgumentException("Invalid line item at index {$idx}: Product ID missing.");
            }

            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                throw new \InvalidArgumentException("Quantity for item #{$productId} must be an integer greater than zero.");
            }

            if (!isset($consolidated[$productId])) {
                $consolidated[$productId] = [
                    'productId' => $productId,
                    'quantity' => $qty,
                    'unitPrice' => isset($item['unitPrice']) ? (float)$item['unitPrice'] : null,
                ];
            } else {
                $consolidated[$productId]['quantity'] += $qty;
                // Preserve worker-negotiated price if explicitly provided
                if (isset($item['unitPrice']) && $consolidated[$productId]['unitPrice'] === null) {
                    $consolidated[$productId]['unitPrice'] = (float)$item['unitPrice'];
                }
            }
        }

        // 2. Compute server-authoritative line totals and gross sale in pure integer kobo
        $validatedItems = [];
        $grossTotalKobo = 0;

        foreach ($consolidated as $pId => $cItem) {
            $product = Product::findOrFail($pId);
            $qty = $cItem['quantity'];

            // Server authoritative pricing: product catalog unitPrice is default.
            // Client tampering is strictly ignored; catalog price is enforced.
            if (isset($cItem['authorized_unit_price']) && (float)$cItem['authorized_unit_price'] >= 0) {
                $unitPrice = (float) $cItem['authorized_unit_price'];
            } else {
                $unitPrice = (float) $product->unitPrice;
            }

            if ($unitPrice < 0) {
                throw new \InvalidArgumentException("Unit price for product '{$product->name}' cannot be negative.");
            }

            $unitPriceKobo = self::toKobo($unitPrice);
            $lineTotalKobo = $qty * $unitPriceKobo;
            $grossTotalKobo += $lineTotalKobo;

            $validatedItems[] = [
                'product'        => $product,
                'quantity'       => $qty,
                'unitPrice'      => self::toNaira($unitPriceKobo),
                'totalPrice'     => self::toNaira($lineTotalKobo),
                'unitPriceKobo'  => $unitPriceKobo,
                'totalPriceKobo' => $lineTotalKobo,
            ];
        }

        // 3. Tender Math: Strictly CASH and POS calculated in pure integer kobo
        $cashTenderedKobo = max(0, self::toKobo($tender['cashAmount'] ?? 0));
        $posTenderedKobo  = max(0, self::toKobo($tender['posAmount'] ?? 0));

        // Invariant: Electronic overpayment rejected! POS cannot exceed gross total
        if ($posTenderedKobo > $grossTotalKobo) {
            $posNaira = self::toNaira($posTenderedKobo);
            $grossNaira = self::toNaira($grossTotalKobo);
            throw new \InvalidArgumentException(
                "Electronic payments (POS tender: ₦" . number_format($posNaira, 2) . 
                ") cannot exceed sale total amount of ₦" . number_format($grossNaira, 2) . 
                ". Cash change cannot be disbursed from card/transfer overpayment."
            );
        }

        $totalTenderedKobo = $cashTenderedKobo + $posTenderedKobo;

        // Invariant: Change calculation and cash source boundary
        $changeAmountKobo = 0;
        if ($totalTenderedKobo > $grossTotalKobo) {
            $changeAmountKobo = $totalTenderedKobo - $grossTotalKobo;
            if ($changeAmountKobo > $cashTenderedKobo) {
                $changeNaira = self::toNaira($changeAmountKobo);
                $cashNaira = self::toNaira($cashTenderedKobo);
                throw new \InvalidArgumentException(
                    "Impossible change: Calculated change (₦" . number_format($changeNaira, 2) . 
                    ") exceeds physical cash tendered (₦" . number_format($cashNaira, 2) . ")."
                );
            }
        }

        // Net paid amount applied to the invoice: Paid = Total Tendered - Change
        $paidAmountKobo = min($grossTotalKobo, max(0, $totalTenderedKobo - $changeAmountKobo));

        // Retained Cash in drawer: Cash Tendered - Change
        $retainedCashKobo = max(0, $cashTenderedKobo - $changeAmountKobo);
        $retainedPosKobo  = $posTenderedKobo;

        // Authoritative integer kobo precision check (strictly prevents IEEE 754 floating point inaccuracies)
        if (($retainedCashKobo + $retainedPosKobo) !== $paidAmountKobo) {
            throw new \InvalidArgumentException("Accounting ledger error: Retained cash and POS do not sum to net paid amount.");
        }

        $outstandingDebtKobo = max(0, $grossTotalKobo - $paidAmountKobo);

        $status = ($paidAmountKobo >= $grossTotalKobo) 
            ? 'COMPLETED' 
            : (($paidAmountKobo > 0) ? 'PARTIAL' : 'PENDING');

        return [
            'grossTotal'          => self::toNaira($grossTotalKobo),
            'totalAmount'         => self::toNaira($grossTotalKobo),
            'validatedItems'      => $validatedItems,
            'cashTendered'        => self::toNaira($cashTenderedKobo),
            'posTendered'         => self::toNaira($posTenderedKobo),
            'totalTendered'       => self::toNaira($totalTenderedKobo),
            'changeAmount'        => self::toNaira($changeAmountKobo),
            'paidAmount'          => self::toNaira($paidAmountKobo),
            'retainedCash'        => self::toNaira($retainedCashKobo),
            'retainedPos'         => self::toNaira($retainedPosKobo),
            'outstandingDebt'     => self::toNaira($outstandingDebtKobo),
            'status'              => $status,
            'grossTotalKobo'      => $grossTotalKobo,
            'cashTenderedKobo'    => $cashTenderedKobo,
            'posTenderedKobo'     => $posTenderedKobo,
            'totalTenderedKobo'   => $totalTenderedKobo,
            'changeAmountKobo'    => $changeAmountKobo,
            'paidAmountKobo'      => $paidAmountKobo,
            'retainedCashKobo'    => $retainedCashKobo,
            'retainedPosKobo'     => $retainedPosKobo,
            'outstandingDebtKobo' => $outstandingDebtKobo,
        ];
    }

    /**
     * Authoritatively calculates the outstanding balance for a specific sale invoice.
     * Net Invoice = Gross Invoice - Return Credits
     * Net Money Applied = Inflow Payments - Cash Refunds
     * Balance = max(0, Net Invoice - Net Money Applied)
     */
    public function calculateInvoiceBalance(Sale $sale): float
    {
        $grossInvoiceKobo = self::toKobo($sale->totalAmount);

        // Returns credited against this sale invoice
        $returnCreditsKobo = self::toKobo(
            SalesReturn::where('saleId', $sale->id)->sum('refundAmount')
        );

        $netInvoiceKobo = max(0, $grossInvoiceKobo - $returnCreditsKobo);

        // Authoritative financial events: Materialized Payment records are the sole source of truth
        $inflowPaymentsKobo = self::toKobo(
            Payment::where('saleId', $sale->id)
                ->where('amount', '>', 0)
                ->where('method', '!=', 'REFUND_CASH')
                ->sum('amount')
        );

        $cashRefundsKobo = abs(self::toKobo(
            Payment::where('saleId', $sale->id)
                ->where('method', 'REFUND_CASH')
                ->sum('amount')
        ));

        $netMoneyAppliedKobo = max(0, $inflowPaymentsKobo - $cashRefundsKobo);

        return self::toNaira(max(0, $netInvoiceKobo - $netMoneyAppliedKobo));
    }

    /**
     * Authoritatively derives customer total outstanding debt from all open invoices.
     */
    public function calculateCustomerDebt(int|string $customerId): float
    {
        $openSales = Sale::where('customerId', $customerId)
            ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
            ->get();

        $derivedDebtKobo = 0;
        foreach ($openSales as $sale) {
            $derivedDebtKobo += self::toKobo($this->calculateInvoiceBalance($sale));
        }

        return self::toNaira($derivedDebtKobo);
    }

    /**
     * Reconciles a customer's stored total_debt against derived invoice debt.
     * Strictly READ-ONLY. Detects and reports variances without mutating customer records.
     */
    public function reconcileCustomerDebt(Customer $customer): array
    {
        $storedDebt = (float) $customer->total_debt;
        $derivedDebt = $this->calculateCustomerDebt($customer->id);
        $variance = round($storedDebt - $derivedDebt, 2);

        return [
            'customerId'   => $customer->id,
            'customerName' => $customer->name,
            'storedDebt'   => $storedDebt,
            'derivedDebt'  => $derivedDebt,
            'variance'     => $variance,
            'balanced'     => (abs($variance) <= 0.01),
        ];
    }

    /**
     * Explicit, authorized administrative correction of customer total debt with audit logging.
     * Requires capability 'debt.correct' (or tenant admin) and mandatory business justification.
     * Enforces tenant boundary, blocks platform users and branch-scoped personnel from unilaterally altering customer liability.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException|\InvalidArgumentException
     */
    public function correctCustomerDebt(Customer $customer, float $newDebt, string $reason, ?string $userId = null, ?string $userName = null, ?User $actor = null): array
    {
        $actingUser = Auth::user() ?? $actor;

        if (!$actingUser) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Unauthorized: No authenticated actor provided for debt correction.");
        }

        if ($actingUser->isPlatformUser()) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Security Violation: Platform users cannot modify tenant customer debt records.");
        }

        if (config('saas.enabled')) {
            $activeTenantId = session('tenant_id') ?? $actingUser->tenant_id;
            if ($activeTenantId && $customer->tenant_id !== $activeTenantId) {
                throw new \Illuminate\Auth\Access\AuthorizationException("Security Violation: Customer #{$customer->id} does not belong to active tenant '{$activeTenantId}'.");
            }
        }

        if ($actingUser->isBranchScoped()) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Security Violation: Branch-scoped employees are not authorized to unilaterally modify company-wide customer debt. Modifying customer debt requires 'debt.correct' capability and tenant-wide administrator authority.");
        }

        $hasCapability = $actingUser->hasCapability('debt.correct') || $actingUser->isTenantAdmin();
        if (!$hasCapability) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Unauthorized: Modifying customer debt requires 'debt.correct' capability.");
        }

        if (trim($reason) === '') {
            throw new \InvalidArgumentException("A valid business justification/reason is required to adjust customer debt.");
        }

        $oldDebt = (float) $customer->total_debt;
        $customer->total_debt = max(0.0, round($newDebt, 2));
        $customer->save();

        $auditUserId = $actingUser->id;
        $auditUserName = $actingUser->name;

        Activity::create([
            'id'          => (string) Str::uuid(),
            'tenant_id'   => session('tenant_id') ?? $customer->tenant_id ?? null,
            'type'        => 'DEBT_CORRECTION',
            'description' => "Customer '{$customer->name}' debt corrected from ₦" . number_format($oldDebt, 2) . " to ₦" . number_format($customer->total_debt, 2) . " by {$auditUserName}. Reason: {$reason}",
            'userId'      => $auditUserId,
            'userName'    => $auditUserName,
            'timestamp'   => now()->toIso8601String(),
        ]);

        return [
            'customerId'   => $customer->id,
            'customerName' => $customer->name,
            'oldDebt'      => $oldDebt,
            'newDebt'      => (float) $customer->total_debt,
            'reason'       => $reason,
            'correctedBy'  => $auditUserName,
            'timestamp'    => now()->toIso8601String(),
        ];
    }

    /**
     * Unified Date Resolver supporting ALL, TODAY, YESTERDAY, THIS_WEEK, LAST_WEEK,
     * THIS_MONTH, LAST_MONTH, THIS_QUARTER, LAST_QUARTER, YEAR_TO_DATE, THIS_YEAR, LAST_YEAR, CUSTOM, AS_OF.
     */
    public function resolveDateRange(?string $period = 'TODAY', ?string $from = null, ?string $to = null, string $tz = 'Africa/Lagos'): array
    {
        $now = Carbon::now($tz);
        $period = !empty($period) ? strtoupper(trim($period)) : null;

        if (empty($period)) {
            if (!empty($from) || !empty($to)) {
                $period = 'CUSTOM';
            } else {
                $period = 'TODAY';
            }
        } elseif (($period === 'CUSTOM' || $period === 'TODAY') && (!empty($from) || !empty($to))) {
            $period = 'CUSTOM';
        }

        switch ($period) {
            case 'TODAY':
                $start = $now->copy()->startOfDay();
                $end   = $now->copy()->endOfDay();
                $label = 'Today (' . $start->format('M d, Y') . ')';
                break;

            case 'YESTERDAY':
                $start = $now->copy()->subDay()->startOfDay();
                $end   = $now->copy()->subDay()->endOfDay();
                $label = 'Yesterday (' . $start->format('M d, Y') . ')';
                break;

            case 'THIS_WEEK':
                $start = $now->copy()->startOfWeek();
                $end   = $now->copy()->endOfWeek();
                $label = 'This Week (' . $start->format('M d') . ' - ' . $end->format('M d, Y') . ')';
                break;

            case 'LAST_WEEK':
                $start = $now->copy()->subWeek()->startOfWeek();
                $end   = $now->copy()->subWeek()->endOfWeek();
                $label = 'Last Week (' . $start->format('M d') . ' - ' . $end->format('M d, Y') . ')';
                break;

            case 'THIS_MONTH':
                $start = $now->copy()->startOfMonth();
                $end   = $now->copy()->endOfMonth();
                $label = 'This Month (' . $start->format('F Y') . ')';
                break;

            case 'LAST_MONTH':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end   = $now->copy()->subMonth()->endOfMonth();
                $label = 'Last Month (' . $start->format('F Y') . ')';
                break;

            case 'THIS_QUARTER':
                $start = $now->copy()->startOfQuarter();
                $end   = $now->copy()->endOfQuarter();
                $label = 'This Quarter (' . $start->format('M Y') . ' - ' . $end->format('M Y') . ')';
                break;

            case 'LAST_QUARTER':
                $start = $now->copy()->subQuarter()->startOfQuarter();
                $end   = $now->copy()->subQuarter()->endOfQuarter();
                $label = 'Last Quarter (' . $start->format('M Y') . ' - ' . $end->format('M Y') . ')';
                break;

            case 'YEAR_TO_DATE':
            case 'THIS_YEAR':
                $start = $now->copy()->startOfYear();
                $end   = $now->copy()->endOfDay();
                $label = 'Year to Date (' . $start->format('M d, Y') . ' - ' . $end->format('M d, Y') . ')';
                break;

            case 'LAST_YEAR':
                $start = $now->copy()->subYear()->startOfYear();
                $end   = $now->copy()->subYear()->endOfYear();
                $label = 'Last Year (' . $start->format('Y') . ')';
                break;

            case 'AS_OF':
                $cutoff = $to ?: $from ?: $now->toDateString();
                $start = Carbon::parse('2020-01-01', $tz)->startOfDay();
                $end   = Carbon::parse($cutoff, $tz)->endOfDay();
                $label = 'As of ' . $end->format('M d, Y');
                break;

            case 'CUSTOM':
                if (!empty($from) && !empty($to)) {
                    $cFrom = Carbon::parse($from, $tz)->startOfDay();
                    $cTo   = Carbon::parse($to, $tz)->endOfDay();
                    if ($cFrom->gt($cTo)) {
                        throw new \InvalidArgumentException("Invalid date range: 'From' date ({$from}) cannot be after 'To' date ({$to}).");
                    }
                    $start = $cFrom;
                    $end   = $cTo;
                    $label = $start->format('M d, Y') . ' - ' . $end->format('M d, Y');
                } elseif (!empty($from)) {
                    $start = Carbon::parse($from, $tz)->startOfDay();
                    $end   = $now->copy()->endOfDay();
                    $label = 'From ' . $start->format('M d, Y') . ' onwards';
                } elseif (!empty($to)) {
                    $start = Carbon::parse('2020-01-01', $tz)->startOfDay();
                    $end   = Carbon::parse($to, $tz)->endOfDay();
                    $label = 'Up to ' . $end->format('M d, Y');
                } else {
                    $start = $now->copy()->startOfDay();
                    $end   = $now->copy()->endOfDay();
                    $label = 'Today (' . $start->format('M d, Y') . ')';
                }
                break;

            case 'ALL':
            default:
                $start = Carbon::parse('2020-01-01', $tz)->startOfDay();
                $end   = $now->copy()->endOfDay();
                $label = 'All Historical Records';
                break;
        }

        return [
            'period'    => $period,
            'preset'    => $period,
            'start'     => $start,
            'end'       => $end,
            'from'      => $start,
            'to'        => $end,
            'startIso'  => $start->toIso8601String(),
            'endIso'    => $end->toIso8601String(),
            'label'     => $label,
            'timezone'  => $tz,
        ];
    }

    /**
     * Authoritative filtered query for Sales.
     */
    public function buildSalesQuery(array $filters): Builder
    {
        $dates = $this->resolveDateRange(
            $filters['date_preset'] ?? $filters['date_range'] ?? $filters['period'] ?? null,
            $filters['from_date'] ?? $filters['from'] ?? null,
            $filters['to_date'] ?? $filters['to'] ?? null
        );

        $query = Sale::with(['items', 'customer']);

        // Date range on sale creation
        $query->whereBetween('createdAt', [$dates['startIso'], $dates['endIso']]);

        // Branch scoping: user branch is immutable ceiling. Request filter may narrow, but never widen.
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->where('warehouse_id', $user->warehouse_id);
            if (!empty($filters['warehouse_id']) && (int) $filters['warehouse_id'] !== (int) $user->warehouse_id) {
                $query->whereRaw('1 = 0');
            }
        } elseif (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        // Cashier/Staff filter
        if (!empty($filters['user_id'])) {
            $query->where('userId', $filters['user_id']);
        }

        // Customer filter
        if (!empty($filters['customer_id'])) {
            $query->where('customerId', $filters['customer_id']);
        }

        // Payment status filter
        if (!empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }

        // Fulfillment status filter
        if (!empty($filters['delivery_status'])) {
            $query->where('deliveryStatus', strtoupper($filters['delivery_status']));
        }

        // Sale Type filter
        if (!empty($filters['sale_type'])) {
            $query->where('sale_type', strtoupper($filters['sale_type']));
        }

        // Product SKU filter
        if (!empty($filters['product_id'])) {
            $pId = $filters['product_id'];
            $query->whereHas('items', function ($iq) use ($pId) {
                $iq->where('productId', $pId);
            });
        }

        // Text Search
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($sq) use ($s) {
                $sq->where('id', 'like', "%{$s}%")
                   ->orWhere('customerName', 'like', "%{$s}%")
                   ->orWhere('userName', 'like', "%{$s}%");
            });
        }

        return $query->orderBy('createdAt', 'desc');
    }

    /**
     * Authoritative filtered query for Payments.
     * Strictly filters for CASH and POS (or REFUND_CASH).
     */
    public function buildPaymentsQuery(array $filters): Builder
    {
        $dates = $this->resolveDateRange(
            $filters['date_preset'] ?? $filters['date_range'] ?? $filters['period'] ?? null,
            $filters['from_date'] ?? $filters['from'] ?? null,
            $filters['to_date'] ?? $filters['to'] ?? null
        );

        $query = Payment::with('sale');

        $query->whereBetween('timestamp', [$dates['startIso'], $dates['endIso']]);

        // Branch scoping via linked sale: user branch is immutable ceiling
        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->whereHas('sale', function ($sq) use ($user) {
                $sq->where('warehouse_id', $user->warehouse_id);
            });
            if (!empty($filters['warehouse_id']) && (int) $filters['warehouse_id'] !== (int) $user->warehouse_id) {
                $query->whereRaw('1 = 0');
            }
        } elseif (!empty($filters['warehouse_id'])) {
            $whId = (int) $filters['warehouse_id'];
            $query->whereHas('sale', function ($sq) use ($whId) {
                $sq->where('warehouse_id', $whId);
            });
        }

        // Payment method filter (CASH or POS)
        if (!empty($filters['method']) || !empty($filters['payment_method'])) {
            $method = strtoupper($filters['method'] ?? $filters['payment_method']);
            if (in_array($method, ['CASH', 'POS', 'REFUND_CASH'])) {
                $query->where('method', $method);
            }
        }

        if (!empty($filters['recorded_by'])) {
            $query->where('recordedBy', $filters['recorded_by']);
        }

        return $query->orderBy('timestamp', 'desc');
    }

    /**
     * Authoritative filtered query for Sales Returns.
     */
    public function buildReturnsQuery(array $filters): Builder
    {
        $dates = $this->resolveDateRange(
            $filters['date_preset'] ?? $filters['date_range'] ?? $filters['period'] ?? null,
            $filters['from_date'] ?? $filters['from'] ?? null,
            $filters['to_date'] ?? $filters['to'] ?? null
        );

        $query = SalesReturn::query();

        $query->whereBetween('createdAt', [$dates['startIso'], $dates['endIso']]);

        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->whereHas('sale', function ($sq) use ($user) {
                $sq->where('warehouse_id', $user->warehouse_id);
            });
            if (!empty($filters['warehouse_id']) && (int) $filters['warehouse_id'] !== (int) $user->warehouse_id) {
                $query->whereRaw('1 = 0');
            }
        } elseif (!empty($filters['warehouse_id'])) {
            $whId = (int) $filters['warehouse_id'];
            $query->whereHas('sale', function ($sq) use ($whId) {
                $sq->where('warehouse_id', $whId);
            });
        }

        if (!empty($filters['product_id'])) {
            $query->where('productId', $filters['product_id']);
        }

        return $query->orderBy('createdAt', 'desc');
    }

    /**
     * Authoritative filtered query for Stock Movements / Inventory Logs.
     */
    public function buildStockMovementsQuery(array $filters): Builder
    {
        $dates = $this->resolveDateRange(
            $filters['date_preset'] ?? $filters['date_range'] ?? $filters['period'] ?? null,
            $filters['from_date'] ?? $filters['from'] ?? null,
            $filters['to_date'] ?? $filters['to'] ?? null
        );

        $query = InventoryLog::with('product');

        $query->whereBetween('timestamp', [$dates['startIso'], $dates['endIso']]);

        // Seamless parameter normalization: handles both movement_type and outflow_type
        $type = $filters['movement_type'] ?? $filters['outflow_type'] ?? $filters['type'] ?? null;
        if (!empty($type)) {
            $query->where('type', strtoupper($type));
        }

        if (!empty($filters['product_id'])) {
            $query->where('productId', $filters['product_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('userId', $filters['user_id']);
        }

        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $query->where('warehouse_id', (int) $user->warehouse_id);
            if (!empty($filters['warehouse_id']) && (int) $filters['warehouse_id'] !== (int) $user->warehouse_id) {
                $query->whereRaw('1 = 0');
            }
        } elseif (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        return $query->orderBy('timestamp', 'desc');
    }

    /**
     * Authoritative filtered query for Transfers.
     */
    public function buildTransfersQuery(array $filters): Builder
    {
        $dates = $this->resolveDateRange(
            $filters['date_preset'] ?? $filters['date_range'] ?? $filters['period'] ?? null,
            $filters['from_date'] ?? $filters['from'] ?? null,
            $filters['to_date'] ?? $filters['to'] ?? null
        );

        $query = Transfer::with(['source', 'destination', 'items']);

        $query->whereBetween('createdAt', [$dates['startIso'], $dates['endIso']]);

        $user = Auth::user();
        if ($user && $user->isBranchScoped()) {
            $whId = (int) $user->warehouse_id;
            $query->where(function ($q) use ($whId) {
                $q->where('source_warehouse_id', $whId)
                  ->orWhere('destination_warehouse_id', $whId);
            });
            if (!empty($filters['source_warehouse_id']) && (int) $filters['source_warehouse_id'] !== $whId) {
                $query->where('destination_warehouse_id', $whId)
                      ->where('source_warehouse_id', (int) $filters['source_warehouse_id']);
            }
            if (!empty($filters['destination_warehouse_id']) && (int) $filters['destination_warehouse_id'] !== $whId) {
                $query->where('source_warehouse_id', $whId)
                      ->where('destination_warehouse_id', (int) $filters['destination_warehouse_id']);
            }
        } else {
            if (!empty($filters['source_warehouse_id'])) {
                $query->where('source_warehouse_id', (int) $filters['source_warehouse_id']);
            }
            if (!empty($filters['destination_warehouse_id'])) {
                $query->where('destination_warehouse_id', (int) $filters['destination_warehouse_id']);
            }
        }

        if (!empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }

        return $query->orderBy('createdAt', 'desc');
    }

    /**
     * Master unified period summary used by Dashboard, Executive Reports, Transactions, and Exports.
     */
    public function getPeriodSummary(array $filters): array
    {
        $dateInfo = $this->resolveDateRange(
            $filters['date_preset'] ?? $filters['date_range'] ?? $filters['period'] ?? null,
            $filters['from_date'] ?? $filters['from'] ?? null,
            $filters['to_date'] ?? $filters['to'] ?? null
        );

        // 1. Sales & Invoices in period
        $salesQuery = $this->buildSalesQuery($filters);
        $sales = $salesQuery->get();

        $grossSales = (float) $sales->sum('totalAmount');
        $invoiceCount = $sales->count();
        $averageInvoice = $invoiceCount > 0 ? round($grossSales / $invoiceCount, 2) : 0.0;

        // 2. Returns & Refunds in period
        $returnsQuery = $this->buildReturnsQuery($filters);
        $returns = $returnsQuery->get();
        $totalReturnCredits = (float) $returns->sum('refundAmount');
        $returnCount = $returns->count();

        $netSales = max(0.0, round($grossSales - $totalReturnCredits, 2));

        // 3. Payment Collections (Inflows)
        $paymentsQuery = $this->buildPaymentsQuery($filters);
        $payments = $paymentsQuery->get();

        $cashCollected = (float) $payments->where('method', 'CASH')->where('amount', '>', 0)->sum('amount');
        $posCollected  = (float) $payments->where('method', 'POS')->where('amount', '>', 0)->sum('amount');
        $totalCollected = round($cashCollected + $posCollected, 2);

        // Refunds paid out in cash
        $cashRefunded = (float) abs($payments->where('method', 'REFUND_CASH')->sum('amount'));
        $netCollected = max(0.0, round($totalCollected - $cashRefunded, 2));

        // 4. Debt & Credit Exposure
        $newDebtCreated = 0.0;
        foreach ($sales as $s) {
            $invBalance = $this->calculateInvoiceBalance($s);
            $newDebtCreated += $invBalance;
        }

        $user = Auth::user();
        $scopedWarehouseId = null;
        if ($user && $user->isBranchScoped()) {
            $scopedWarehouseId = (int) $user->warehouse_id;
        } elseif (!empty($filters['warehouse_id'])) {
            $scopedWarehouseId = (int) $filters['warehouse_id'];
        }

        // Debt recovered in period (CustomerLedger records debt payments with type='PAYMENT')
        $debtPaymentsQuery = CustomerLedger::where('type', 'PAYMENT')
            ->whereBetween('created_at', [$dateInfo['start'], $dateInfo['end']]);

        if ($scopedWarehouseId) {
            $debtPaymentsQuery->where(function ($q) use ($scopedWarehouseId) {
                $q->where('warehouse_id', $scopedWarehouseId)
                  ->orWhereHas('sale', fn($sq) => $sq->where('warehouse_id', $scopedWarehouseId));
            });
        }

        $debtRecovered = (float) $debtPaymentsQuery->sum('amount');
        $cashDebtRecovered = (float) (clone $debtPaymentsQuery)->where('payment_method', 'CASH')->sum('amount');
        $posDebtRecovered  = (float) (clone $debtPaymentsQuery)->where('payment_method', 'POS')->sum('amount');

        if ($scopedWarehouseId) {
            // Branch-isolated debt liability: strictly derive from open sales originating at this branch
            $openSalesBranch = Sale::where('warehouse_id', $scopedWarehouseId)
                ->whereNotIn('status', ['CANCELLED', 'RETURNED'])
                ->get();
            $branchOutstanding = 0.0;
            foreach ($openSalesBranch as $os) {
                $branchOutstanding += $this->calculateInvoiceBalance($os);
            }
            $currentOutstanding = round($branchOutstanding, 2);
        } else {
            $currentOutstanding = (float) Customer::sum('total_debt');
        }

        // 5. Stock & Inventory Valuation
        $user = Auth::user();
        $stockLevelsQuery = StockLevel::query();
        if ($user && $user->isBranchScoped()) {
            $stockLevelsQuery->where('warehouse_id', $user->warehouse_id);
        } elseif (!empty($filters['warehouse_id'])) {
            $stockLevelsQuery->where('warehouse_id', (int) $filters['warehouse_id']);
        }
        $stockLevels = $stockLevelsQuery->with('product')->get();

        $totalPhysicalUnits = (int) $stockLevels->sum('physical_stock');
        $totalAllocatedUnits = (int) $stockLevels->sum('allocated_stock');
        $totalAvailableUnits = max(0, $totalPhysicalUnits - $totalAllocatedUnits);

        $retailInventoryValue = 0.0;
        $costInventoryValue = 0.0;

        foreach ($stockLevels as $sl) {
            $p = $sl->product;
            if ($p) {
                $units = max(0, (int) $sl->physical_stock);
                $retailPrice = (float) ($p->unitPrice ?? 0);
                $costPrice = (float) ($p->costPrice ?? $p->cost_price ?? 0.0); // Exact cost basis without synthetic fallbacks
                $retailInventoryValue += ($units * $retailPrice);
                $costInventoryValue   += ($units * $costPrice);
            }
        }

        // 6. Cashier Shift / Drawer physical cash reconciliation:
        // Physical Cash = Total Cash Inflows - Cash Refunds
        // Identify debt payments that are already captured in $cashCollected to prevent double-counting,
        // while properly counting unlinked debt payments that were not attached to specific sale invoices.
        $cashDebtInPayments = (float) $payments->where('method', 'CASH')
            ->filter(fn($p) => str_contains($p->recordedBy ?? '', '[DEBT_RECOVERY]'))
            ->sum('amount');
        $unlinkedCashDebt = max(0.0, round($cashDebtRecovered - $cashDebtInPayments, 2));

        $expectedCash = round($cashCollected + $unlinkedCashDebt - $cashRefunded, 2);

        return [
            'dateInfo'                   => $dateInfo,
            'grossSales'                 => $grossSales,
            'gross_revenue'              => round($grossSales, 2),
            'netSales'                   => $netSales,
            'invoiceCount'               => $invoiceCount,
            'averageInvoice'             => $averageInvoice,
            'returnCount'                => $returnCount,
            'totalReturnCredits'         => $totalReturnCredits,
            'cashCollected'              => $cashCollected,
            'cash_collected'             => round($cashCollected, 2),
            'posCollected'               => $posCollected,
            'pos_collected'              => round($posCollected, 2),
            'totalCollected'             => $totalCollected,
            'cashRefunded'               => $cashRefunded,
            'refunds'                    => round($cashRefunded, 2),
            'netCollected'               => $netCollected,
            'net_payments'               => round($netCollected, 2),
            'newDebtCreated'             => $newDebtCreated,
            'debtRecovered'              => $debtRecovered,
            'cashDebtRecovered'          => $cashDebtRecovered,
            'posDebtRecovered'           => $posDebtRecovered,
            'currentOutstanding'         => round($currentOutstanding, 2),
            'totalPhysicalUnits'         => $totalPhysicalUnits,
            'totalAllocatedUnits'        => $totalAllocatedUnits,
            'totalAvailableUnits'        => $totalAvailableUnits,
            'retailInventoryValue'       => round($retailInventoryValue, 2),
            'inventory_retail_valuation' => round($retailInventoryValue, 2),
            'costInventoryValue'         => round($costInventoryValue, 2),
            'inventory_cost_valuation'   => round($costInventoryValue, 2),
            'expectedCashInDrawer'       => $expectedCash,
        ];
    }

    /**
     * Master Automated Reconciliation Audit Engine.
     * Evaluates all mathematical invariants and produces a verified report.
     */
    public function runReconciliationAudit(?int $warehouseId = null): array
    {
        $checks = [];

        // Check 1: Sale Totals Invariant: Sale Total == SUM(SaleItem.totalPrice)
        $salesQuery = Sale::with('items');
        if ($warehouseId) {
            $salesQuery->where('warehouse_id', $warehouseId);
        }
        $sales = $salesQuery->get();

        $saleMismatchCount = 0;
        foreach ($sales as $s) {
            $itemSum = round($s->items->sum('totalPrice'), 2);
            if (abs($itemSum - (float)$s->totalAmount) > 0.01) {
                $saleMismatchCount++;
            }
        }
        $checks['sale_totals_integrity'] = [
            'name'     => 'Sale Items Sum == Sale Total',
            'status'   => ($saleMismatchCount === 0) ? 'PASS' : 'FAIL',
            'mismatches'=> $saleMismatchCount,
            'examined' => $sales->count(),
        ];

        // Check 2: Payment Ledger Sum Invariant: Initial Payments == Sale.paidAmount
        $paymentMismatchCount = 0;
        foreach ($sales as $s) {
            $inflows = round(Payment::where('saleId', $s->id)->where('amount', '>', 0)->sum('amount'), 2);
            $refunds = round(abs(Payment::where('saleId', $s->id)->where('method', 'REFUND_CASH')->sum('amount')), 2);
            $netPaid = max(0.0, $inflows - $refunds);
            if (abs($netPaid - (float)$s->paidAmount) > 0.01) {
                $paymentMismatchCount++;
            }
        }
        $checks['payment_ledger_integrity'] = [
            'name'     => 'Net Payments Applied == Sale Paid Amount',
            'status'   => ($paymentMismatchCount === 0) ? 'PASS' : 'FAIL',
            'mismatches'=> $paymentMismatchCount,
            'examined' => $sales->count(),
        ];

        // Check 3: Customer Debt Invariant: Stored Debt == SUM(Invoice Balances)
        $customers = Customer::all();
        $debtMismatchCount = 0;
        foreach ($customers as $c) {
            $derived = $this->calculateCustomerDebt($c->id);
            if (abs((float)$c->total_debt - $derived) > 0.01) {
                $debtMismatchCount++;
            }
        }
        $checks['customer_debt_reconciliation'] = [
            'name'     => 'Stored Customer Debt == Derived Open Invoice Balances',
            'status'   => ($debtMismatchCount === 0) ? 'PASS' : 'FAIL',
            'mismatches'=> $debtMismatchCount,
            'examined' => $customers->count(),
        ];

        // Check 4: Inventory Invariant: Physical >= 0 AND Allocated >= 0
        // Decoupled model: allocated_stock > physical_stock is VALID (represents customer reservation shortfall awaiting restock).
        // Actual corruption is negative physical stock or negative allocated stock.
        $stockLevelsQuery = StockLevel::query();
        if ($warehouseId) {
            $stockLevelsQuery->where('warehouse_id', $warehouseId);
        }
        $stockLevels = $stockLevelsQuery->get();

        $inventoryNegativeCount = 0;
        foreach ($stockLevels as $sl) {
            if ($sl->physical_stock < 0 || $sl->allocated_stock < 0) {
                $inventoryNegativeCount++;
            }
        }
        $checks['inventory_availability_invariant'] = [
            'name'     => 'Physical Stock >= 0 AND Allocated Stock >= 0 (Decoupled Model)',
            'status'   => ($inventoryNegativeCount === 0) ? 'PASS' : 'FAIL',
            'mismatches'=> $inventoryNegativeCount,
            'examined' => $stockLevels->count(),
        ];

        // Check 5: Returns Eligibility: Returned Quantity <= Sold Quantity
        $returnOverdraftCount = 0;
        $returns = SalesReturn::all();
        foreach ($returns as $ret) {
            $saleItem = SaleItem::where('saleId', $ret->saleId)->where('productId', $ret->productId)->first();
            if ($saleItem && $ret->quantity > $saleItem->quantity) {
                $returnOverdraftCount++;
            }
        }
        $checks['returns_eligibility_integrity'] = [
            'name'     => 'Returned Quantity <= Original Sold Quantity',
            'status'   => ($returnOverdraftCount === 0) ? 'PASS' : 'FAIL',
            'mismatches'=> $returnOverdraftCount,
            'examined' => $returns->count(),
        ];

        // Check 6: Transfer Discrepancy Invariant: Dispatched == Received + Missing
        $transfers = Transfer::with('items')->get();
        $transferMismatchCount = 0;
        foreach ($transfers as $tr) {
            foreach ($tr->items as $ti) {
                $dispatched = (int) ($ti->dispatched_qty ?? $ti->quantity_dispatched ?? 0);
                $received   = (int) ($ti->received_qty ?? $ti->quantity_received ?? 0);
                $missing    = (int) ($ti->discrepancy_qty ?? $ti->quantity_discrepancy ?? 0);
                if ($tr->status === 'RECEIVED' && ($dispatched !== ($received + $missing))) {
                    $transferMismatchCount++;
                }
            }
        }
        $checks['transfer_accounting_integrity'] = [
            'name'     => 'Transfer Dispatched == Received + Missing Discrepancy',
            'status'   => ($transferMismatchCount === 0) ? 'PASS' : 'FAIL',
            'mismatches'=> $transferMismatchCount,
            'examined' => $transfers->count(),
        ];

        $allPassed = true;
        $errorCount = 0;
        foreach ($checks as $c) {
            if ($c['status'] !== 'PASS') {
                $allPassed = false;
                $errorCount += ($c['mismatches'] ?? 1);
            }
        }

        return [
            'timestamp'   => now()->toIso8601String(),
            'overall'     => $allPassed ? 'BALANCED' : 'EXCEPTION_DETECTED',
            'status'      => $allPassed ? 'BALANCED' : 'EXCEPTION_DETECTED',
            'error_count' => $errorCount,
            'checks'      => [
                'invoice_balance_vs_line_items' => ($saleMismatchCount === 0) ? 'PASSED' : 'FAILED',
                'payment_tender_integrity'      => ($paymentMismatchCount === 0) ? 'PASSED' : 'FAILED',
                'customer_debt_ledger'          => ($debtMismatchCount === 0) ? 'PASSED' : 'FAILED',
                'stock_levels_vs_inventory_logs'=> ($inventoryNegativeCount === 0) ? 'PASSED' : 'FAILED',
                'returns_eligibility'           => ($returnOverdraftCount === 0) ? 'PASSED' : 'FAILED',
                'transfer_accounting'           => ($transferMismatchCount === 0) ? 'PASSED' : 'FAILED',
                'sale_totals_integrity'         => $checks['sale_totals_integrity'],
                'payment_ledger_integrity'      => $checks['payment_ledger_integrity'],
                'customer_debt_reconciliation'  => $checks['customer_debt_reconciliation'],
                'inventory_availability_invariant' => $checks['inventory_availability_invariant'],
                'returns_eligibility_integrity' => $checks['returns_eligibility_integrity'],
                'transfer_accounting_integrity' => $checks['transfer_accounting_integrity'],
            ],
            'detailed'    => $checks,
        ];
    }

    /**
     * Reconciles physical stock level allocation against active customer reservations.
     * Invariant: StockLevel.allocated_stock == sum(reserved_qty - fulfilled_qty - cancelled_qty).
     */
    public function reconcileReservationAllocations(string|int $productId, int $warehouseId): array
    {
        $stock = \App\Models\StockLevel::where('product_id', (string) $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        $allocatedStock = $stock ? (int) $stock->allocated_stock : 0;

        $reservations = \App\Models\StockReservation::where('product_id', (string) $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', ['ACTIVE', 'PARTIALLY_FULFILLED'])
            ->get();

        $sumOutstanding = 0;
        foreach ($reservations as $res) {
            $sumOutstanding += (int) $res->outstanding_qty;
        }

        $variance = $allocatedStock - $sumOutstanding;

        return [
            'productId'        => $productId,
            'warehouseId'      => $warehouseId,
            'allocatedStock'   => $allocatedStock,
            'sumOutstanding'   => $sumOutstanding,
            'variance'         => $variance,
            'balanced'         => ($variance === 0),
        ];
    }
}
