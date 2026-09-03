<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Transfer;
use App\Models\Customer;
use App\Models\StockAdjustment;
use App\Models\Activity;
use App\Models\User;
use App\Models\SalesReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Display the Central Reports & Business Intelligence Hub with Advanced Filters.
     */
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'overview');
        $authUser = Auth::user();

        // 1. Build Filter Query for Sales
        $salesQuery = Sale::with('items');

        if ($authUser && !empty($authUser->warehouse_id)) {
            $warehouses = Warehouse::where('id', $authUser->warehouse_id)->get();
            $staffList = User::where('warehouse_id', $authUser->warehouse_id)->get();
            $shopStaffIds = $staffList->pluck('id');
            $salesQuery->where(function($sq) use ($authUser, $shopStaffIds) {
                $sq->where('warehouse_id', $authUser->warehouse_id);
                if ($shopStaffIds->isNotEmpty()) {
                    $sq->orWhereIn('userId', $shopStaffIds);
                }
            });
        } else {
            $warehouses = Warehouse::where('is_active', true)->get();
            $staffList = User::all();
        }

        $categories = Product::distinct()->pluck('category')->filter()->values();

        $datePreset = $request->get('date_preset', 'ALL');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        if ($fromDate && $toDate) {
            $salesQuery->whereBetween('createdAt', [
                Carbon::parse($fromDate)->startOfDay()->toIso8601String(),
                Carbon::parse($toDate)->endOfDay()->toIso8601String()
            ]);
        } elseif ($datePreset === 'TODAY') {
            $salesQuery->whereDate('createdAt', Carbon::today());
        } elseif ($datePreset === 'YESTERDAY') {
            $salesQuery->whereDate('createdAt', Carbon::yesterday());
        } elseif ($datePreset === 'THIS_WEEK') {
            $salesQuery->whereBetween('createdAt', [
                Carbon::now()->startOfWeek()->toIso8601String(),
                Carbon::now()->endOfWeek()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_MONTH') {
            $salesQuery->whereBetween('createdAt', [
                Carbon::now()->startOfMonth()->toIso8601String(),
                Carbon::now()->endOfMonth()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_YEAR') {
            $salesQuery->whereBetween('createdAt', [
                Carbon::now()->startOfYear()->toIso8601String(),
                Carbon::now()->endOfYear()->toIso8601String()
            ]);
        }

        if ($request->filled('user_name')) {
            $salesQuery->where('userName', 'like', "%{$request->user_name}%");
        }

        if ($request->filled('payment_status')) {
            $pStatus = strtoupper($request->payment_status);
            if ($pStatus === 'PAID') {
                $salesQuery->whereColumn('paidAmount', '>=', 'totalAmount');
            } elseif (in_array($pStatus, ['PART_PAID', 'PARTIAL'])) {
                $salesQuery->whereColumn('paidAmount', '<', 'totalAmount')->where('paidAmount', '>', 0);
            } elseif (in_array($pStatus, ['NOT_PAID', 'UNPAID'])) {
                $salesQuery->where('paidAmount', '<=', 0);
            } elseif ($pStatus === 'DEBT') {
                $salesQuery->whereColumn('paidAmount', '<', 'totalAmount');
            }
        }

        if ($request->filled('delivery_status')) {
            $dStatus = strtoupper($request->delivery_status);
            if (in_array($dStatus, ['DELIVERED', 'SUPPLIED'])) {
                $salesQuery->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
            } elseif (in_array($dStatus, ['UNSUPPLIED', 'NOT_SUPPLIED', 'PENDING'])) {
                $salesQuery->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
            } elseif ($dStatus === 'PAID_SUPPLIED') {
                $salesQuery->whereColumn('paidAmount', '>=', 'totalAmount')->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
            } elseif ($dStatus === 'PAID_NOT_SUPPLIED') {
                $salesQuery->whereColumn('paidAmount', '>=', 'totalAmount')->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
            } elseif ($dStatus === 'PART_PAID_SUPPLIED') {
                $salesQuery->whereColumn('paidAmount', '<', 'totalAmount')->where('paidAmount', '>', 0)->whereIn('deliveryStatus', ['DELIVERED', 'SUPPLIED']);
            } elseif ($dStatus === 'PART_PAID_NOT_SUPPLIED') {
                $salesQuery->whereColumn('paidAmount', '<', 'totalAmount')->where('paidAmount', '>', 0)->whereIn('deliveryStatus', ['UNSUPPLIED', 'NOT_SUPPLIED', 'pending']);
            } else {
                $salesQuery->where('deliveryStatus', $dStatus);
            }
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $salesQuery->where(function ($q) use ($s) {
                $q->where('id', 'like', "%{$s}%")
                  ->orWhere('customerName', 'like', "%{$s}%")
                  ->orWhere('customerPhone', 'like', "%{$s}%");
            });
        }

        // Executed Sales Collection
        $sales = (clone $salesQuery)->orderBy('createdAt', 'desc')->get();

        // 2. High-Level Aggregates
        $totalRevenue = $sales->sum('totalAmount');
        $totalCollected = $sales->sum('paidAmount');
        $totalDebtCreated = max(0, $totalRevenue - $totalCollected);
        $totalInvoices = $sales->count();
        $totalDebtOwedAllTime = Customer::sum('total_debt');

        // Top Selling Products (by revenue) - Strictly scoped to filtered sales within tenant & branch
        $topProductsQuery = SaleItem::selectRaw('productName, code, sum(quantity) as total_qty, sum(totalPrice) as total_revenue');
        if ($sales->isNotEmpty()) {
            $topProductsQuery->whereIn('saleId', $sales->pluck('id'));
        } else {
            $topProductsQuery->whereRaw('1 = 0');
        }
        $topProducts = $topProductsQuery
            ->groupBy('productName', 'code')
            ->orderBy('total_revenue', 'desc')
            ->take(5)
            ->get();

        // Top Staff by Sales Volume
        $topStaff = $sales->groupBy('userName')->map(function ($group, $name) {
            return [
                'name' => $name,
                'count' => $group->count(),
                'total' => $group->sum('totalAmount'),
                'collected' => $group->sum('paidAmount'),
            ];
        })->sortByDesc('total')->take(5);

        // 3. Physical Stock & Valuation Matrix
        $prodQuery = Product::where('archived', false);
        if ($request->filled('category')) {
            $prodQuery->where('category', $request->category);
        }

        $products = $prodQuery->get()->map(function ($p) use ($warehouses) {
            $p->branch_stocks = StockLevel::where('product_id', $p->id)->pluck('physical_stock', 'warehouse_id')->toArray();
            $p->total_physical_stock = array_sum($p->branch_stocks);
            $p->total_valuation = $p->total_physical_stock * (float) $p->unitPrice;
            $p->stock_status = $p->total_physical_stock <= 0 ? 'OUT_OF_STOCK' : ($p->total_physical_stock <= 5 ? 'LOW_STOCK' : 'IN_STOCK');
            return $p;
        });
        $totalStockValuation = $products->sum('total_valuation');
        $totalPhysicalUnits = $products->sum('total_physical_stock');

        // 4. Transfers & Logistics
        $transfersQuery = Transfer::with(['source', 'destination', 'items']);
        if ($request->filled('transfer_status')) {
            $transfersQuery->where('status', $request->transfer_status);
        }
        $transfers = $transfersQuery->orderBy('created_at', 'desc')->take(50)->get();
        $totalDiscrepancyUnits = 0;
        foreach ($transfers as $trf) {
            if ($trf->status === 'DISCREPANCY') {
                foreach ($trf->items as $item) {
                    $totalDiscrepancyUnits += max(0, $item->discrepancy_qty);
                }
            }
        }

        // 5. Debt Aging Analysis
        $debtors = Customer::where('total_debt', '>', 0)->orderBy('total_debt', 'desc')->get()->map(function ($c) {
            $daysOld = $c->updated_at ? Carbon::parse($c->updated_at)->diffInDays(now()) : 0;
            $c->aging_category = $daysOld > 30 ? 'CRITICAL (30+ Days)' : ($daysOld > 7 ? 'DUE (8-30 Days)' : 'CURRENT (0-7 Days)');
            return $c;
        });

        // 6. Damaged Goods Write-offs
        $adjustments = StockAdjustment::with('warehouse')->orderBy('created_at', 'desc')->take(50)->get();
        $totalDamagedUnits = $adjustments->sum('quantity');

        // 7. Immutable Activity Logs
        $activities = Activity::orderBy('timestamp', 'desc')->take(50)->get();

        // 8. Returns & Refunds Query
        $returnsQuery = SalesReturn::query();
        if ($fromDate && $toDate) {
            $returnsQuery->whereBetween('createdAt', [
                Carbon::parse($fromDate)->startOfDay()->toIso8601String(),
                Carbon::parse($toDate)->endOfDay()->toIso8601String()
            ]);
        } elseif ($datePreset === 'TODAY') {
            $returnsQuery->whereDate('createdAt', Carbon::today());
        } elseif ($datePreset === 'YESTERDAY') {
            $returnsQuery->whereDate('createdAt', Carbon::yesterday());
        } elseif ($datePreset === 'THIS_WEEK') {
            $returnsQuery->whereBetween('createdAt', [
                Carbon::now()->startOfWeek()->toIso8601String(),
                Carbon::now()->endOfWeek()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_MONTH') {
            $returnsQuery->whereBetween('createdAt', [
                Carbon::now()->startOfMonth()->toIso8601String(),
                Carbon::now()->endOfMonth()->toIso8601String()
            ]);
        } elseif ($datePreset === 'THIS_YEAR') {
            $returnsQuery->whereBetween('createdAt', [
                Carbon::now()->startOfYear()->toIso8601String(),
                Carbon::now()->endOfYear()->toIso8601String()
            ]);
        }

        if ($request->filled('user_name')) {
            $returnsQuery->where('userName', 'like', "%{$request->user_name}%");
        }

        if ($request->filled('search')) {
            $sText = trim($request->search);
            $returnsQuery->where(function ($q) use ($sText) {
                $q->where('saleId', 'like', "%{$sText}%")
                  ->orWhere('customerName', 'like', "%{$sText}%")
                  ->orWhere('productName', 'like', "%{$sText}%");
            });
        }

        $returns = $returnsQuery->orderBy('createdAt', 'desc')->get();
        $totalRefunded = $returns->sum('refundAmount');

        return view('reports.index', compact(
            'activeTab',
            'warehouses',
            'staffList',
            'categories',
            'sales',
            'totalRevenue',
            'totalCollected',
            'totalDebtCreated',
            'totalInvoices',
            'totalDebtOwedAllTime',
            'topProducts',
            'topStaff',
            'products',
            'totalStockValuation',
            'totalPhysicalUnits',
            'transfers',
            'totalDiscrepancyUnits',
            'debtors',
            'adjustments',
            'totalDamagedUnits',
            'activities',
            'returns',
            'totalRefunded',
            'datePreset',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * Export Filtered Report to CSV for Excel, Google Sheets, or AI prompt ingestion.
     */
    public function exportCsv(Request $request, $type)
    {
        $fileName = "hysam_{$type}_report_" . date('Y_m_d_His') . ".csv";

        return new StreamedResponse(function () use ($type) {
            $handle = fopen('php://output', 'w');

            if ($type === 'sales') {
                fputcsv($handle, ['Invoice ID', 'Date & Time', 'Customer Name', 'Customer Phone', 'Items Count', 'Gross Total (NGN)', 'Paid Amount (NGN)', 'Debt Balance (NGN)', 'Delivery / Handover Status', 'Cashier Name']);
                foreach (Sale::with('items')->orderBy('createdAt', 'desc')->cursor() as $s) {
                    $debt = max(0, $s->totalAmount - $s->paidAmount);
                    fputcsv($handle, [
                        $s->id,
                        $s->createdAt,
                        $s->customerName,
                        $s->customerPhone ?? 'N/A',
                        $s->items->count(),
                        $s->totalAmount,
                        $s->paidAmount,
                        $debt,
                        $s->deliveryStatus,
                        $s->userName
                    ]);
                }
            } elseif ($type === 'inventory') {
                fputcsv($handle, ['Product ID', 'SKU', 'Product Name', 'Category', 'Brand', 'Size', 'Selling Price (NGN)', 'Total Physical Shelf Units', 'Stock Status', 'Total Asset Valuation (NGN)']);
                foreach (Product::where('archived', false)->cursor() as $p) {
                    $stock = StockLevel::where('product_id', $p->id)->sum('physical_stock');
                    $status = $stock <= 0 ? 'OUT_OF_STOCK' : ($stock <= 5 ? 'LOW_STOCK' : 'IN_STOCK');
                    fputcsv($handle, [$p->id, $p->code, $p->name, $p->category, $p->brand, $p->size, $p->unitPrice, $stock, $status, $stock * (float)$p->unitPrice]);
                }
            } elseif ($type === 'transfers') {
                fputcsv($handle, ['Transfer No', 'Dispatched Date', 'Origin Branch', 'Destination Branch', 'Carrier Driver', 'Status', 'Dispatched By', 'Received By', 'Notes']);
                foreach (Transfer::with(['source', 'destination'])->orderBy('created_at', 'desc')->cursor() as $t) {
                    fputcsv($handle, [
                        $t->transfer_no,
                        $t->created_at,
                        $t->source->name ?? 'Origin',
                        $t->destination->name ?? 'Destination',
                        $t->carrier_name,
                        $t->status,
                        $t->dispatched_by,
                        $t->received_by ?? 'Pending',
                        $t->notes ?? ''
                    ]);
                }
            } elseif ($type === 'debtors') {
                fputcsv($handle, ['Customer Name', 'Phone Number', 'Address / Market Location', 'Total Debt Owed (NGN)', 'Last Updated']);
                foreach (Customer::where('total_debt', '>', 0)->cursor() as $c) {
                    fputcsv($handle, [$c->name, $c->phone, $c->address, $c->total_debt, $c->updated_at]);
                }
            } elseif ($type === 'damages') {
                fputcsv($handle, ['Date & Time', 'Shop Location', 'SKU', 'Product Name', 'Incident Category', 'Quantity Deducted', 'Reason / Notes', 'Staff Responsible']);
                foreach (StockAdjustment::with('warehouse')->orderBy('created_at', 'desc')->cursor() as $a) {
                    fputcsv($handle, [
                        $a->created_at,
                        $a->warehouse->name ?? 'Shop',
                        $a->product_code,
                        $a->product_name,
                        $a->type,
                        $a->quantity,
                        $a->reason,
                        $a->recorded_by
                    ]);
                }
            } elseif ($type === 'returns') {
                fputcsv($handle, ['Date & Time', 'Original Invoice ID', 'Customer Name', 'SKU', 'Product Name', 'Returned Qty', 'Refunded Amount (NGN)', 'Reason', 'Handled By']);
                foreach (SalesReturn::orderBy('createdAt', 'desc')->cursor() as $r) {
                    fputcsv($handle, [
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
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Export Report to Structured JSON format for AI analysis.
     */
    public function exportJson(Request $request, $type)
    {
        $fileName = "hysam_{$type}_business_data_" . date('Y_m_d_His') . ".json";

        $data = match($type) {
            'sales' => [
                'metadata' => ['report' => 'Sales & Revenue Analysis', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'data' => Sale::with('items')->orderBy('createdAt', 'desc')->get()
            ],
            'inventory' => [
                'metadata' => ['report' => 'Multi-Branch Inventory Valuation', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'data' => Product::with('stockLevels')->where('archived', false)->get()
            ],
            'transfers' => [
                'metadata' => ['report' => 'Inter-Branch Transfer Movements & Discrepancies', 'generated_at' => now()->toIso8601String()],
                'data' => Transfer::with(['source', 'destination', 'items'])->orderBy('created_at', 'desc')->get()
            ],
            'debtors' => [
                'metadata' => ['report' => 'Debtors Ledger & Credit Exposure', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'data' => Customer::where('total_debt', '>', 0)->get()
            ],
            'damages' => [
                'metadata' => ['report' => 'Damaged Goods & Loss Audit Trail', 'generated_at' => now()->toIso8601String()],
                'data' => StockAdjustment::with('warehouse')->orderBy('created_at', 'desc')->get()
            ],
            'activities' => [
                'metadata' => ['report' => 'Immutable System Audit Activity Log', 'generated_at' => now()->toIso8601String()],
                'data' => Activity::orderBy('timestamp', 'desc')->get()
            ],
            'returns' => [
                'metadata' => ['report' => 'Customer Returns & Refunds Ledger', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'data' => SalesReturn::orderBy('createdAt', 'desc')->get()
            ],
            default => ['error' => 'Invalid report type'],
        };

        return response()->json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ], JSON_PRETTY_PRINT);
    }
}
