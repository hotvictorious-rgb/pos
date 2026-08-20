@extends('layouts.app')

@section('title', 'Reports & AI Data Exports')

@push('styles')
<style>
    .report-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border);
        padding-bottom: 0.5rem;
    }

    .rep-tab-btn {
        padding: 0.75rem 1.25rem;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        color: var(--text-muted);
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
    }
    .rep-tab-btn.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(37,99,235,0.3);
    }

    .report-section { display: none; }
    .report-section.active { display: block; }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .summary-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.25rem;
    }
    .summary-card h4 { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; }
    .summary-card .val { font-size: 1.35rem; font-weight: 800; }

    .table-wrap {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: rgba(11, 15, 25, 0.8); padding: 1rem 1.25rem; font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border); }
    td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(55, 65, 81, 0.25); }

    .export-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
</style>
@endpush

@section('content')

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.6rem; font-weight: 800;">Reports & AI Analysis Exports 📊</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Download clean, filterable CSV and JSON reports ready for Python, Excel, or AI analysis.
            </p>
        </div>
    </div>

    <!-- Top Tabs -->
    <div class="report-tabs">
        <button class="rep-tab-btn active" onclick="showReport('repSales', this)">📊 Sales & Revenue</button>
        <button class="rep-tab-btn" onclick="showReport('repStock', this)">📦 Stock & Valuation</button>
        <button class="rep-tab-btn" onclick="showReport('repTransfers', this)">🚚 Transfers & Logistics</button>
        <button class="rep-tab-btn" onclick="showReport('repDebts', this)">💳 Customer Debtors</button>
        <button class="rep-tab-btn" onclick="showReport('repDamages', this)">📉 Damaged Stock</button>
        <button class="rep-tab-btn" onclick="showReport('repAudit', this)">🚨 Activity Logs</button>
    </div>

    <!-- ========================================================================= -->
    <!-- 1. SALES REPORT -->
    <!-- ========================================================================= -->
    <div id="repSales" class="report-section active">
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Revenue</h4>
                <div class="val" style="color: #4ade80;">₦{{ number_format($totalRevenue, 2) }}</div>
            </div>
            <div class="summary-card">
                <h4>Cash / POS Collected</h4>
                <div class="val" style="color: #60a5fa;">₦{{ number_format($totalCollected, 2) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Debt Uncollected</h4>
                <div class="val" style="color: #f87171;">₦{{ number_format($totalDebtOwed, 2) }}</div>
            </div>
        </div>

        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Sales Transactions Report</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'sales') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">
                        📥 Export CSV (Excel)
                    </a>
                    <a href="{{ route('reports.export.json', 'sales') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">
                        🤖 Export JSON (For AI)
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Gross Total</th>
                            <th>Paid Amount</th>
                            <th>Debt Balance</th>
                            <th>Delivery</th>
                            <th>Cashier</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sales as $s)
                        @php $debt = max(0, $s->totalAmount - $s->paidAmount); @endphp
                        <tr>
                            <td><strong>#{{ substr($s->id, 0, 8) }}</strong></td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">{{ date('d M Y, h:i A', strtotime($s->createdAt)) }}</td>
                            <td>{{ $s->customerName }}</td>
                            <td style="font-weight: 800;">₦{{ number_format($s->totalAmount, 2) }}</td>
                            <td style="color: #4ade80;">₦{{ number_format($s->paidAmount, 2) }}</td>
                            <td>
                                @if($debt > 0)
                                    <span class="badge badge-danger">₦{{ number_format($debt, 2) }}</span>
                                @else
                                    <span class="badge badge-success">✓ Paid</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $s->deliveryStatus === 'SUPPLIED' ? 'badge-success' : 'badge-warning' }}">
                                    {{ $s->deliveryStatus }}
                                </span>
                            </td>
                            <td>{{ $s->userName }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. PHYSICAL STOCK & VALUATION REPORT -->
    <!-- ========================================================================= -->
    <div id="repStock" class="report-section">
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Inventory Valuation</h4>
                <div class="val" style="color: #4ade80;">₦{{ number_format($totalStockValuation, 2) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Catalog SKUs</h4>
                <div class="val" style="color: #60a5fa;">{{ $products->count() }} Products</div>
            </div>
        </div>

        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Physical Stock & Valuation Matrix</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'inventory') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">
                        📥 Export CSV (Excel)
                    </a>
                    <a href="{{ route('reports.export.json', 'inventory') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">
                        🤖 Export JSON (For AI)
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>SKU & Product Name</th>
                            <th>Category</th>
                            <th>Unit Price (₦)</th>
                            @foreach($warehouses as $wh)
                                <th style="color: #60a5fa;">{{ $wh->name }}</th>
                            @endforeach
                            <th>Total Units</th>
                            <th style="color: #4ade80;">Total Valuation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $p)
                        <tr>
                            <td>
                                <strong>{{ $p->name }}</strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $p->code }}</div>
                            </td>
                            <td><span class="badge badge-info">{{ $p->category }}</span></td>
                            <td>₦{{ number_format($p->unitPrice, 2) }}</td>
                            @foreach($warehouses as $wh)
                                <td>{{ $p->branch_stocks[$wh->id] ?? 0 }}</td>
                            @endforeach
                            <td><strong style="font-size: 1.05rem;">{{ $p->total_physical_stock }}</strong></td>
                            <td style="font-weight: 800; color: #4ade80;">₦{{ number_format($p->total_valuation, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. TRANSFERS & LOGISTICS REPORT -->
    <!-- ========================================================================= -->
    <div id="repTransfers" class="report-section">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Inter-Branch Transfers & Discrepancies</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'transfers') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">
                        📥 Export CSV (Excel)
                    </a>
                    <a href="{{ route('reports.export.json', 'transfers') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">
                        🤖 Export JSON (For AI)
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Transfer #</th>
                            <th>Date</th>
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Carrier Driver</th>
                            <th>Status</th>
                            <th>Dispatched By</th>
                            <th>Waybill</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transfers as $t)
                        <tr>
                            <td><strong>{{ $t->transfer_no }}</strong></td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">{{ date('d M Y, h:i A', strtotime($t->created_at)) }}</td>
                            <td>{{ $t->source->name ?? 'Origin' }}</td>
                            <td><strong>{{ $t->destination->name ?? 'Destination' }}</strong></td>
                            <td>{{ $t->carrier_name }}</td>
                            <td>
                                @if($t->status === 'DISCREPANCY')
                                    <span class="badge badge-danger">🚨 THEFT / VARIANCE</span>
                                @elseif($t->status === 'RECEIVED')
                                    <span class="badge badge-success">✓ RECEIVED</span>
                                @else
                                    <span class="badge badge-info">🚚 IN TRANSIT</span>
                                @endif
                            </td>
                            <td>{{ $t->dispatched_by }}</td>
                            <td>
                                <a href="{{ route('stock.waybill', $t->id) }}" class="btn btn-secondary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;" target="_blank">
                                    🖨️ Waybill
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 4. CUSTOMER DEBTORS REPORT -->
    <!-- ========================================================================= -->
    <div id="repDebts" class="report-section">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Customer Debt Aging & Balances</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'debtors') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">
                        📥 Export CSV (Excel)
                    </a>
                    <a href="{{ route('reports.export.json', 'debtors') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">
                        🤖 Export JSON (For AI)
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Phone Number</th>
                            <th>Address / Shop</th>
                            <th style="color: #f87171;">Total Debt Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($debtors as $d)
                        <tr>
                            <td><strong>{{ $d->name }}</strong></td>
                            <td>{{ $d->phone ?? 'N/A' }}</td>
                            <td>{{ $d->address ?? 'N/A' }}</td>
                            <td style="font-weight: 800; color: #f87171; font-size: 1.1rem;">
                                ₦{{ number_format($d->total_debt, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 5. DAMAGED STOCK REPORT -->
    <!-- ========================================================================= -->
    <div id="repDamages" class="report-section">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Damaged & Lost Stock Audit Write-offs</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'damages') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">
                        📥 Export CSV (Excel)
                    </a>
                    <a href="{{ route('reports.export.json', 'damages') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">
                        🤖 Export JSON (For AI)
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Shop</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Reason</th>
                            <th>Staff</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($adjustments as $a)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">{{ date('d M Y, h:i A', strtotime($a->created_at)) }}</td>
                            <td>{{ $a->warehouse->name ?? 'Shop' }}</td>
                            <td>{{ $a->product_name }}</td>
                            <td><span class="badge badge-danger">{{ $a->type }}</span></td>
                            <td style="font-weight: 800; color: #f87171;">-{{ $a->quantity }}</td>
                            <td>{{ $a->reason }}</td>
                            <td>{{ $a->recorded_by }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 6. AUDIT ACTIVITY LOGS -->
    <!-- ========================================================================= -->
    <div id="repAudit" class="report-section">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Complete Immutable Audit Trail</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.json', 'activities') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">
                        🤖 Export JSON (For AI)
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Action Type</th>
                            <th>Description</th>
                            <th>Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activities as $act)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">{{ date('d M Y, h:i A', strtotime($act->timestamp)) }}</td>
                            <td><span class="badge badge-info">{{ $act->type }}</span></td>
                            <td>{{ $act->description }}</td>
                            <td><strong>{{ $act->userName }}</strong></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function showReport(repId, btn) {
    document.querySelectorAll('.rep-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.report-section').forEach(s => s.classList.remove('active'));

    btn.classList.add('active');
    document.getElementById(repId).classList.add('active');
}
</script>
@endpush
