@extends('layouts.app')

@section('title', 'Executive Reports & Business Intelligence')

@push('styles')
<style>
    .filter-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .preset-pills {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .preset-pill {
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid var(--border);
        color: var(--text-muted);
        font-size: 0.8rem;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.2s;
    }
    .preset-pill:hover, .preset-pill.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .kpi-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.25rem;
        position: relative;
        overflow: hidden;
    }
    .kpi-card h4 { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; font-weight: 800; }
    .kpi-card .val { font-size: 1.4rem; font-weight: 800; }
    .kpi-card .sub { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }

    .insights-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
        margin-bottom: 1.5rem;
    }
    @media (max-width: 900px) { .insights-grid { grid-template-columns: 1fr; } }

    .report-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border);
        padding-bottom: 0.5rem;
    }

    .rep-tab-btn {
        padding: 0.65rem 1.15rem;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        color: var(--text-muted);
        font-size: 0.85rem;
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

    .table-wrap {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: rgba(11, 15, 25, 0.8); padding: 0.9rem 1.15rem; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border); }
    td { padding: 0.9rem 1.15rem; border-bottom: 1px solid var(--border); font-size: 0.88rem; }
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
            <h2 style="font-size: 1.6rem; font-weight: 800;">Executive Business Intelligence & Reports 📊</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Complete real-time analytics across sales, multi-branch inventory valuations, debts, and logistics with AI exports.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Summary</button>
            <a href="{{ route('reports.export.json', 'sales') }}" class="btn btn-primary" style="background: #6366f1;">
                🤖 AI Data Digest (JSON)
            </a>
        </div>
    </div>

    <!-- 1. ADVANCED GLOBAL FILTERS -->
    <div class="filter-card">
        <form method="GET" action="{{ route('reports.index') }}">
            <div class="preset-pills">
                <span style="font-size: 0.8rem; font-weight: 800; color: var(--text-muted); align-self: center; margin-right: 0.25rem;">DATE PRESETS:</span>
                <a href="{{ route('reports.index', ['date_preset' => 'ALL']) }}" class="preset-pill {{ request('date_preset', 'ALL') === 'ALL' ? 'active' : '' }}">All Time</a>
                <a href="{{ route('reports.index', ['date_preset' => 'TODAY']) }}" class="preset-pill {{ request('date_preset') === 'TODAY' ? 'active' : '' }}">Today</a>
                <a href="{{ route('reports.index', ['date_preset' => 'YESTERDAY']) }}" class="preset-pill {{ request('date_preset') === 'YESTERDAY' ? 'active' : '' }}">Yesterday</a>
                <a href="{{ route('reports.index', ['date_preset' => 'THIS_WEEK']) }}" class="preset-pill {{ request('date_preset') === 'THIS_WEEK' ? 'active' : '' }}">This Week</a>
                <a href="{{ route('reports.index', ['date_preset' => 'THIS_MONTH']) }}" class="preset-pill {{ request('date_preset') === 'THIS_MONTH' ? 'active' : '' }}">This Month</a>
                <a href="{{ route('reports.index', ['date_preset' => 'THIS_YEAR']) }}" class="preset-pill {{ request('date_preset') === 'THIS_YEAR' ? 'active' : '' }}">This Year</a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.75rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Cashier / Staff</label>
                    <select name="user_name">
                        <option value="">-- All Staff --</option>
                        @foreach($staffList as $st)
                            <option value="{{ $st->name }}" {{ request('user_name') === $st->name ? 'selected' : '' }}>{{ $st->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Payment Status</label>
                    <select name="payment_status">
                        <option value="">-- All Payments --</option>
                        <option value="PAID" {{ request('payment_status') === 'PAID' ? 'selected' : '' }}>Fully Paid</option>
                        <option value="DEBT" {{ request('payment_status') === 'DEBT' ? 'selected' : '' }}>Part-Payment (Debt)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Handover Status</label>
                    <select name="delivery_status">
                        <option value="">-- All Deliveries --</option>
                        <option value="DELIVERED" {{ request('delivery_status') === 'DELIVERED' ? 'selected' : '' }}>Delivered Now</option>
                        <option value="UNSUPPLIED" {{ request('delivery_status') === 'UNSUPPLIED' ? 'selected' : '' }}>Awaiting Pickup</option>
                    </select>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.65rem;">🔍 Filter</button>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary" style="padding: 0.65rem;">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- 2. HIGH-LEVEL KPI DECK (6 Cards) -->
    <div class="kpi-grid">
        <div class="kpi-card" style="border-top: 4px solid #22c55e;">
            <h4>Total Filtered Revenue</h4>
            <div class="val" style="color: #4ade80;">₦{{ number_format($totalRevenue, 2) }}</div>
            <div class="sub">{{ $totalInvoices }} Invoices Generated</div>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #3b82f6;">
            <h4>Cash / POS Realized</h4>
            <div class="val" style="color: #60a5fa;">₦{{ number_format($totalCollected, 2) }}</div>
            <div class="sub">{{ $totalRevenue > 0 ? round(($totalCollected / $totalRevenue) * 100, 1) : 0 }}% Collection Rate</div>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #ef4444;">
            <h4>New Credit / Debts Created</h4>
            <div class="val" style="color: #f87171;">₦{{ number_format($totalDebtCreated, 2) }}</div>
            <div class="sub">All-Time Market Debt: ₦{{ number_format($totalDebtOwedAllTime, 2) }}</div>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #a855f7;">
            <h4>Physical Stock Asset Value</h4>
            <div class="val" style="color: #c084fc;">₦{{ number_format($totalStockValuation, 2) }}</div>
            <div class="sub">{{ number_format($totalPhysicalUnits) }} Total Units on Ground</div>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #f59e0b;">
            <h4>Transfer Discrepancies</h4>
            <div class="val" style="color: #fbbf24;">{{ $totalDiscrepancyUnits }} Units</div>
            <div class="sub">Flagged on In-Transit Radar</div>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #ec4899;">
            <h4>Damaged Goods Losses</h4>
            <div class="val" style="color: #f472b6;">{{ $totalDamagedUnits }} Units</div>
            <div class="sub">Logged Write-offs on Record</div>
        </div>
    </div>

    <!-- 3. EXECUTIVE RANKINGS & INSIGHTS -->
    <div class="insights-grid">
        <!-- Top Selling Products -->
        <div class="card">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem; color: #93c5fd;">
                🏆 Top 5 Best-Selling Products (By Revenue)
            </h3>
            @forelse($topProducts as $idx => $tp)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <div>
                        <strong style="color: #f9fafb;">#{{ $idx + 1 }} {{ $tp->productName }}</strong>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $tp->code }} • {{ $tp->total_qty }} units sold</div>
                    </div>
                    <strong style="color: #4ade80;">₦{{ number_format($tp->total_revenue, 2) }}</strong>
                </div>
            @empty
                <p style="font-size: 0.85rem; color: var(--text-muted);">No product sales recorded yet.</p>
            @endforelse
        </div>

        <!-- Top Performing Staff -->
        <div class="card">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem; color: #86efac;">
                🥇 Top Staff / Cashiers by Sales Volume
            </h3>
            @forelse($topStaff as $st)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px solid rgba(255,255,255,0.06);">
                    <div>
                        <strong style="color: #f9fafb;">👤 {{ $st['name'] }}</strong>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $st['count'] }} transactions handled</div>
                    </div>
                    <div style="text-align: right;">
                        <strong style="color: #60a5fa;">₦{{ number_format($st['total'], 2) }}</strong>
                        <div style="font-size: 0.7rem; color: #4ade80;">Cash: ₦{{ number_format($st['collected'], 2) }}</div>
                    </div>
                </div>
            @empty
                <p style="font-size: 0.85rem; color: var(--text-muted);">No cashier sales recorded yet.</p>
            @endforelse
        </div>
    </div>

    <!-- 4. TABBED REPORTS NAVIGATION -->
    <div class="report-tabs">
        <button class="rep-tab-btn active" onclick="showReport('repSales', this)">📊 Sales & Invoices ({{ $sales->count() }})</button>
        <button class="rep-tab-btn" onclick="showReport('repStock', this)">📦 Multi-Branch Stock ({{ $products->count() }})</button>
        <button class="rep-tab-btn" onclick="showReport('repTransfers', this)">🚚 Transfers & Waybills ({{ $transfers->count() }})</button>
        <button class="rep-tab-btn" onclick="showReport('repDebts', this)">💳 Debtors Aging ({{ $debtors->count() }})</button>
        <button class="rep-tab-btn" onclick="showReport('repDamages', this)">📉 Damaged Stock ({{ $adjustments->count() }})</button>
        <button class="rep-tab-btn" onclick="showReport('repShifts', this)">⚖️ Cashier Shift Logs</button>
        <button class="rep-tab-btn" onclick="showReport('repAi', this)">🤖 AI Export Hub</button>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: SALES & INVOICES -->
    <!-- ========================================================================= -->
    <div id="repSales" class="report-section active">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Filtered Sales Transactions</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'sales') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', 'sales') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date & Time</th>
                            <th>Customer Name</th>
                            <th>Items</th>
                            <th>Gross Total</th>
                            <th>Paid (Cash/POS)</th>
                            <th>Debt Balance</th>
                            <th>Handover</th>
                            <th>Cashier</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $s)
                        @php $debt = max(0, $s->totalAmount - $s->paidAmount); @endphp
                        <tr>
                            <td><strong>#{{ substr($s->id, 0, 8) }}</strong></td>
                            <td style="font-size: 0.75rem; color: var(--text-muted);">{{ date('d M Y, h:i A', strtotime($s->createdAt)) }}</td>
                            <td>
                                <strong>{{ $s->customerName }}</strong>
                                @if($s->customerPhone)<div style="font-size: 0.75rem; color: var(--text-muted);">{{ $s->customerPhone }}</div>@endif
                            </td>
                            <td>{{ $s->items->count() }} items</td>
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
                        @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem; color: var(--text-muted);">No sales match your active filters.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: MULTI-BRANCH STOCK & VALUATION -->
    <!-- ========================================================================= -->
    <div id="repStock" class="report-section">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Multi-Branch Inventory & Stock Health</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'inventory') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', 'inventory') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
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
                            <th>Health Status</th>
                            <th style="color: #4ade80;">Asset Valuation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $p)
                        <tr>
                            <td>
                                <strong>{{ $p->name }}</strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $p->code }} • {{ $p->brand }}</div>
                            </td>
                            <td><span class="badge badge-info">{{ $p->category }}</span></td>
                            <td>₦{{ number_format($p->unitPrice, 2) }}</td>
                            @foreach($warehouses as $wh)
                                <td>{{ $p->branch_stocks[$wh->id] ?? 0 }}</td>
                            @endforeach
                            <td><strong style="font-size: 1.05rem;">{{ $p->total_physical_stock }}</strong></td>
                            <td>
                                @if($p->stock_status === 'OUT_OF_STOCK')
                                    <span class="badge badge-danger">OUT OF STOCK</span>
                                @elseif($p->stock_status === 'LOW_STOCK')
                                    <span class="badge badge-warning">LOW STOCK (≤5)</span>
                                @else
                                    <span class="badge badge-success">IN STOCK</span>
                                @endif
                            </td>
                            <td style="font-weight: 800; color: #4ade80;">₦{{ number_format($p->total_valuation, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 3: TRANSFERS & WAYBILLS -->
    <!-- ========================================================================= -->
    <div id="repTransfers" class="report-section">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Inter-Branch Transfers & Discrepancies</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'transfers') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', 'transfers') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Transfer #</th>
                            <th>Dispatched Date</th>
                            <th>Origin Branch</th>
                            <th>Destination Branch</th>
                            <th>Carrier Driver</th>
                            <th>Status</th>
                            <th>Dispatched By</th>
                            <th>Waybill</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $t)
                        <tr>
                            <td><strong>{{ $t->transfer_no }}</strong></td>
                            <td style="font-size: 0.75rem; color: var(--text-muted);">{{ date('d M Y, h:i A', strtotime($t->created_at)) }}</td>
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
                        @empty
                        <tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">No transfers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 4: DEBTORS AGING LEDGER -->
    <!-- ========================================================================= -->
    <div id="repDebts" class="report-section">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Customer Debt Aging & Recovery Ledger</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'debtors') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', 'debtors') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>Phone Number</th>
                            <th>Address / Shop</th>
                            <th>Debt Aging Status</th>
                            <th style="color: #f87171;">Outstanding Balance</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($debtors as $d)
                        <tr>
                            <td><strong>{{ $d->name }}</strong></td>
                            <td>{{ $d->phone ?? 'N/A' }}</td>
                            <td>{{ $d->address ?? 'N/A' }}</td>
                            <td>
                                @if(str_contains($d->aging_category, 'CRITICAL'))
                                    <span class="badge badge-danger">{{ $d->aging_category }}</span>
                                @elseif(str_contains($d->aging_category, 'DUE'))
                                    <span class="badge badge-warning">{{ $d->aging_category }}</span>
                                @else
                                    <span class="badge badge-info">{{ $d->aging_category }}</span>
                                @endif
                            </td>
                            <td style="font-weight: 800; color: #f87171; font-size: 1.05rem;">
                                ₦{{ number_format($d->total_debt, 2) }}
                            </td>
                            <td>
                                <a href="{{ route('debts.index') }}" class="btn btn-success" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">
                                    💰 Record Payment
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">No outstanding customer debts on record.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 5: DAMAGED STOCK WRITE-OFFS -->
    <!-- ========================================================================= -->
    <div id="repDamages" class="report-section">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Damaged, Expired & Lost Stock Audit Write-offs</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'damages') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', 'damages') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Shop Location</th>
                            <th>Product Item</th>
                            <th>Incident Category</th>
                            <th>Quantity Deducted</th>
                            <th>Reason / Notes</th>
                            <th>Staff Responsible</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($adjustments as $a)
                        <tr>
                            <td style="font-size: 0.75rem; color: var(--text-muted);">{{ date('d M Y, h:i A', strtotime($a->created_at)) }}</td>
                            <td>{{ $a->warehouse->name ?? 'Shop' }}</td>
                            <td>
                                <strong>{{ $a->product_name }}</strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $a->product_code }}</div>
                            </td>
                            <td><span class="badge badge-danger">{{ $a->type }}</span></td>
                            <td style="font-weight: 800; color: #f87171;">-{{ $a->quantity }}</td>
                            <td>{{ $a->reason }}</td>
                            <td><strong>{{ $a->recorded_by }}</strong></td>
                        </tr>
                        @empty
                        <tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">No damaged stock write-offs recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 6: CASHIER SHIFT BALANCING -->
    <!-- ========================================================================= -->
    <div id="repShifts" class="report-section">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Cashier End-of-Day Shift Balancing Logs</h3>
                <a href="{{ route('reports.export.json', 'shift_logs') }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">
                    🤖 Export JSON
                </a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Cashier Name</th>
                            <th>Expected Cash (₦)</th>
                            <th>Physical Counted (₦)</th>
                            <th>Shortage / Overage</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shiftLogs as $sh)
                        @php $diff = (float) $sh->counted_cash - (float) $sh->expected_cash; @endphp
                        <tr>
                            <td style="font-size: 0.75rem; color: var(--text-muted);">{{ date('d M Y, h:i A', strtotime($sh->created_at)) }}</td>
                            <td><strong>{{ $sh->user_name }}</strong></td>
                            <td>₦{{ number_format($sh->expected_cash, 2) }}</td>
                            <td style="font-weight: 800; color: #4ade80;">₦{{ number_format($sh->counted_cash, 2) }}</td>
                            <td>
                                @if($diff < 0)
                                    <span class="badge badge-danger">🚨 SHORTAGE ₦{{ number_format(abs($diff), 2) }}</span>
                                @elseif($diff > 0)
                                    <span class="badge badge-info">OVERAGE +₦{{ number_format($diff, 2) }}</span>
                                @else
                                    <span class="badge badge-success">✓ EXACT BALANCE</span>
                                @endif
                            </td>
                            <td>{{ $sh->notes ?? 'Shift closed' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">No shift logs recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 7: AI DATA EXPORT HUB -->
    <!-- ========================================================================= -->
    <div id="repAi" class="report-section">
        <div class="card">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; color: #93c5fd;">
                🤖 AI Business Intelligence & Prompt Ingestion Hub
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Download pre-structured JSON and CSV datasets optimized for feeding into Large Language Models (ChatGPT, Claude, Gemini, DeepSeek) or Python/Excel for forecasting, profit margin analysis, and inventory optimization.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #4ade80; margin-bottom: 0.35rem;">📊 Complete Sales Data</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">All customer transactions, payment methods, debt balances, and cashier records.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', 'sales') }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', 'sales') }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #60a5fa; margin-bottom: 0.35rem;">📦 Inventory & Branch Valuations</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">All catalog SKUs, shelf counts per branch, low stock warnings, and total ₦ asset values.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', 'inventory') }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', 'inventory') }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #fbbf24; margin-bottom: 0.35rem;">🚚 Logistics & In-Transit Transfers</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Transfer history, carrier driver tracking, and verified count discrepancy flags.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', 'transfers') }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', 'transfers') }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #f87171; margin-bottom: 0.35rem;">💳 Customer Debtors & Aging</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Complete debtor contact details, total debt exposure, and aging risk buckets.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', 'debtors') }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', 'debtors') }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>
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
