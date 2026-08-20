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
use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Http\Request;
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
        $warehouses = Warehouse::where('is_active', true)->get();
        $staffList = User::all();
        $categories = Product::distinct()->pluck('category')->filter()->values();

        // 1. Build Filter Query for Sales
        $salesQuery = Sale::with('items');

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
            if ($request->payment_status === 'PAID') {
                $salesQuery->whereColumn('paidAmount', '>=', 'totalAmount');
            } elseif ($request->payment_status === 'DEBT') {
                $salesQuery->whereColumn('paidAmount', '<', 'totalAmount');
            }
        }

        if ($request->filled('delivery_status')) {
            $salesQuery->where('deliveryStatus', strtoupper($request->delivery_status));
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

        // Top Selling Products (by revenue)
        $topProducts = SaleItem::selectRaw('productName, code, sum(quantity) as total_qty, sum(totalPrice) as total_revenue')
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

        // 7. Cashier Shift Logs
        $shiftLogs = CashierShift::orderBy('created_at', 'desc')->take(30)->get();

        // 8. Immutable Activity Logs
        $activities = Activity::orderBy('timestamp', 'desc')->take(50)->get();

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
            'shiftLogs',
            'activities',
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
                foreach (Sale::with('items')->orderBy('createdAt', 'desc')->get() as $s) {
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
                foreach (Product::where('archived', false)->get() as $p) {
                    $stock = StockLevel::where('product_id', $p->id)->sum('physical_stock');
                    $status = $stock <= 0 ? 'OUT_OF_STOCK' : ($stock <= 5 ? 'LOW_STOCK' : 'IN_STOCK');
                    fputcsv($handle, [$p->id, $p->code, $p->name, $p->category, $p->brand, $p->size, $p->unitPrice, $stock, $status, $stock * (float)$p->unitPrice]);
                }
            } elseif ($type === 'transfers') {
                fputcsv($handle, ['Transfer No', 'Dispatched Date', 'Origin Branch', 'Destination Branch', 'Carrier Driver', 'Status', 'Dispatched By', 'Received By', 'Notes']);
                foreach (Transfer::with(['source', 'destination'])->orderBy('created_at', 'desc')->get() as $t) {
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
                foreach (Customer::where('total_debt', '>', 0)->get() as $c) {
                    fputcsv($handle, [$c->name, $c->phone, $c->address, $c->total_debt, $c->updated_at]);
                }
            } elseif ($type === 'damages') {
                fputcsv($handle, ['Date & Time', 'Shop Location', 'SKU', 'Product Name', 'Incident Category', 'Quantity Deducted', 'Reason / Notes', 'Staff Responsible']);
                foreach (StockAdjustment::with('warehouse')->orderBy('created_at', 'desc')->get() as $a) {
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
            'shift_logs' => [
                'metadata' => ['report' => 'Cashier Shift Cash Balancing Logs', 'generated_at' => now()->toIso8601String(), 'currency' => 'NGN'],
                'data' => CashierShift::orderBy('created_at', 'desc')->get()
            ],
            'activities' => [
                'metadata' => ['report' => 'Immutable System Audit Activity Log', 'generated_at' => now()->toIso8601String()],
                'data' => Activity::orderBy('timestamp', 'desc')->get()
            ],
            default => ['error' => 'Invalid report type'],
        };

        return response()->json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ], JSON_PRETTY_PRINT);
    }
}
