<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockLevel;
use App\Models\Transfer;
use App\Models\Customer;
use App\Models\StockAdjustment;
use App\Models\Activity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display the Central Reports Hub.
     */
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'sales');
        $warehouses = Warehouse::where('is_active', true)->get();

        // 1. Sales & Revenue Summary
        $sales = Sale::with('items')->orderBy('createdAt', 'desc')->take(50)->get();
        $totalRevenue = Sale::sum('totalAmount');
        $totalCollected = Sale::sum('paidAmount');
        $totalDebtOwed = Customer::sum('total_debt');

        // 2. Physical Stock & Valuation
        $products = Product::where('archived', false)->get()->map(function ($p) use ($warehouses) {
            $p->branch_stocks = StockLevel::where('product_id', $p->id)->pluck('physical_stock', 'warehouse_id')->toArray();
            $p->total_physical_stock = array_sum($p->branch_stocks);
            $p->total_valuation = $p->total_physical_stock * (float) $p->unitPrice;
            return $p;
        });
        $totalStockValuation = $products->sum('total_valuation');

        // 3. Transfers & Discrepancies
        $transfers = Transfer::with(['source', 'destination', 'items'])->orderBy('created_at', 'desc')->take(30)->get();

        // 4. Debtors List
        $debtors = Customer::where('total_debt', '>', 0)->orderBy('total_debt', 'desc')->get();

        // 5. Stock Damages Write-offs
        $adjustments = StockAdjustment::with('warehouse')->orderBy('created_at', 'desc')->take(30)->get();

        // 6. Complete Activity Logs
        $activities = Activity::orderBy('timestamp', 'desc')->take(50)->get();

        return view('reports.index', compact(
            'activeTab',
            'warehouses',
            'sales',
            'totalRevenue',
            'totalCollected',
            'totalDebtOwed',
            'products',
            'totalStockValuation',
            'transfers',
            'debtors',
            'adjustments',
            'activities'
        ));
    }

    /**
     * Export Report to CSV for Excel, Google Sheets, or AI prompt ingestion.
     */
    public function exportCsv(Request $request, $type)
    {
        $fileName = "hysam_{$type}_report_" . date('Y_m_d_His') . ".csv";

        return new StreamedResponse(function () use ($type) {
            $handle = fopen('php://output', 'w');

            if ($type === 'sales') {
                fputcsv($handle, ['Invoice ID', 'Date', 'Customer Name', 'Phone', 'Total Amount (NGN)', 'Paid Amount (NGN)', 'Debt Balance (NGN)', 'Delivery Status', 'Cashier']);
                foreach (Sale::with('items')->orderBy('createdAt', 'desc')->get() as $s) {
                    $debt = max(0, $s->totalAmount - $s->paidAmount);
                    fputcsv($handle, [$s->id, $s->createdAt, $s->customerName, $s->customerPhone, $s->totalAmount, $s->paidAmount, $debt, $s->deliveryStatus, $s->userName]);
                }
            } elseif ($type === 'inventory') {
                fputcsv($handle, ['Product ID', 'SKU', 'Product Name', 'Category', 'Brand', 'Size', 'Selling Price (NGN)', 'Total Physical Stock', 'Valuation (NGN)']);
                foreach (Product::where('archived', false)->get() as $p) {
                    $stock = StockLevel::where('product_id', $p->id)->sum('physical_stock');
                    fputcsv($handle, [$p->id, $p->code, $p->name, $p->category, $p->brand, $p->size, $p->unitPrice, $stock, $stock * (float)$p->unitPrice]);
                }
            } elseif ($type === 'transfers') {
                fputcsv($handle, ['Transfer No', 'Date Dispatched', 'Origin Branch ID', 'Destination Branch ID', 'Carrier Name', 'Status', 'Dispatched By', 'Received By']);
                foreach (Transfer::orderBy('created_at', 'desc')->get() as $t) {
                    fputcsv($handle, [$t->transfer_no, $t->created_at, $t->source_warehouse_id, $t->destination_warehouse_id, $t->carrier_name, $t->status, $t->dispatched_by, $t->received_by]);
                }
            } elseif ($type === 'debtors') {
                fputcsv($handle, ['Customer ID', 'Customer Name', 'Phone', 'Address', 'Total Debt Owed (NGN)']);
                foreach (Customer::where('total_debt', '>', 0)->get() as $c) {
                    fputcsv($handle, [$c->id, $c->name, $c->phone, $c->address, $c->total_debt]);
                }
            } elseif ($type === 'damages') {
                fputcsv($handle, ['Date', 'Shop ID', 'Product SKU', 'Product Name', 'Type', 'Quantity Deducted', 'Reason', 'Staff']);
                foreach (StockAdjustment::orderBy('created_at', 'desc')->get() as $a) {
                    fputcsv($handle, [$a->created_at, $a->warehouse_id, $a->product_code, $a->product_name, $a->type, $a->quantity, $a->reason, $a->recorded_by]);
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
        $fileName = "hysam_{$type}_data_" . date('Y_m_d_His') . ".json";

        $data = match($type) {
            'sales' => Sale::with('items')->orderBy('createdAt', 'desc')->get(),
            'inventory' => Product::with('stockLevels')->where('archived', false)->get(),
            'transfers' => Transfer::with('items')->orderBy('created_at', 'desc')->get(),
            'debtors' => Customer::where('total_debt', '>', 0)->get(),
            'damages' => StockAdjustment::orderBy('created_at', 'desc')->get(),
            'activities' => Activity::orderBy('timestamp', 'desc')->get(),
            default => ['error' => 'Invalid report type'],
        };

        return response()->json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ], JSON_PRETTY_PRINT);
    }
}
