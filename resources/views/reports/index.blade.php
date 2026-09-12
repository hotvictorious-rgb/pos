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
            <a href="{{ route('reports.export.json', array_merge(['type' => 'sales'], request()->query())) }}" class="btn btn-primary" style="background: #6366f1;">
                🤖 AI Data Digest (JSON)
            </a>
        </div>
    </div>

    <!-- 1. ADVANCED GLOBAL FILTERS -->
    <div class="filter-card">
        <form method="GET" action="{{ route('reports.index') }}">
            <input type="hidden" name="tab" id="activeTabInput" value="{{ $currentTab ?? 'repDayBook' }}">
            <div class="preset-pills">
                <span style="font-size: 0.8rem; font-weight: 800; color: var(--text-muted); align-self: center; margin-right: 0.25rem;">DATE PRESETS:</span>
                <a href="{{ route('reports.index', array_merge(request()->except(['from_date', 'to_date', 'page']), ['date_preset' => 'ALL'])) }}" class="preset-pill {{ request('date_preset', 'ALL') === 'ALL' ? 'active' : '' }}">All Time</a>
                <a href="{{ route('reports.index', array_merge(request()->except(['from_date', 'to_date', 'page']), ['date_preset' => 'TODAY'])) }}" class="preset-pill {{ request('date_preset') === 'TODAY' ? 'active' : '' }}">Today</a>
                <a href="{{ route('reports.index', array_merge(request()->except(['from_date', 'to_date', 'page']), ['date_preset' => 'YESTERDAY'])) }}" class="preset-pill {{ request('date_preset') === 'YESTERDAY' ? 'active' : '' }}">Yesterday</a>
                <a href="{{ route('reports.index', array_merge(request()->except(['from_date', 'to_date', 'page']), ['date_preset' => 'THIS_WEEK'])) }}" class="preset-pill {{ request('date_preset') === 'THIS_WEEK' ? 'active' : '' }}">This Week</a>
                <a href="{{ route('reports.index', array_merge(request()->except(['from_date', 'to_date', 'page']), ['date_preset' => 'LAST_WEEK'])) }}" class="preset-pill {{ request('date_preset') === 'LAST_WEEK' ? 'active' : '' }}">Last Week</a>
                <a href="{{ route('reports.index', array_merge(request()->except(['from_date', 'to_date', 'page']), ['date_preset' => 'THIS_MONTH'])) }}" class="preset-pill {{ request('date_preset') === 'THIS_MONTH' ? 'active' : '' }}">This Month</a>
                <a href="{{ route('reports.index', array_merge(request()->except(['from_date', 'to_date', 'page']), ['date_preset' => 'LAST_MONTH'])) }}" class="preset-pill {{ request('date_preset') === 'LAST_MONTH' ? 'active' : '' }}">Last Month</a>
                <a href="{{ route('reports.index', array_merge(request()->except(['from_date', 'to_date', 'page']), ['date_preset' => 'THIS_YEAR'])) }}" class="preset-pill {{ request('date_preset') === 'THIS_YEAR' ? 'active' : '' }}">This Year</a>
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
                        <option value="PAID" {{ request('payment_status') === 'PAID' ? 'selected' : '' }}>✓ Paid (Full)</option>
                        <option value="PART_PAID" {{ in_array(request('payment_status'), ['PART_PAID', 'PARTIAL']) ? 'selected' : '' }}>💳 Part-Paid</option>
                        <option value="NOT_PAID" {{ in_array(request('payment_status'), ['NOT_PAID', 'UNPAID']) ? 'selected' : '' }}>🔴 Not Paid</option>
                        <option value="DEBT" {{ request('payment_status') === 'DEBT' ? 'selected' : '' }}>🤝 All Debtors</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Handover Status</label>
                    <select name="delivery_status">
                        <option value="">-- All Deliveries --</option>
                        <option value="SUPPLIED" {{ in_array(request('delivery_status'), ['DELIVERED', 'SUPPLIED']) ? 'selected' : '' }}>✓ Supplied</option>
                        <option value="NOT_SUPPLIED" {{ in_array(request('delivery_status'), ['UNSUPPLIED', 'NOT_SUPPLIED', 'PENDING']) ? 'selected' : '' }}>⏳ Not Supplied</option>
                        <option value="PAID_SUPPLIED" {{ request('delivery_status') === 'PAID_SUPPLIED' ? 'selected' : '' }}>🟢 Paid & Supplied</option>
                        <option value="PAID_NOT_SUPPLIED" {{ request('delivery_status') === 'PAID_NOT_SUPPLIED' ? 'selected' : '' }}>🟠 Paid & Not Supplied</option>
                        <option value="PART_PAID_SUPPLIED" {{ request('delivery_status') === 'PART_PAID_SUPPLIED' ? 'selected' : '' }}>⚠️ Part-Paid & Supplied</option>
                        <option value="PART_PAID_NOT_SUPPLIED" {{ request('delivery_status') === 'PART_PAID_NOT_SUPPLIED' ? 'selected' : '' }}>⏳ Part-Paid & Not Supplied</option>
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
            <div class="val" style="color: #4ade80;">₦{{ number_format($totalRevenue, 0) }}</div>
            <div class="sub">{{ $totalInvoices }} Invoices Generated</div>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #3b82f6;">
            <h4>Cash / POS Realized</h4>
            <div class="val" style="color: #60a5fa;">₦{{ number_format($periodSummary['totalNetMoneyRealized'] ?? $totalCollected, 0) }}</div>
            <div class="sub">
                💵 ₦{{ number_format($periodSummary['netCashInflow'] ?? $totalCollected, 0) }} Cash · 💳 ₦{{ number_format($periodSummary['netPosInflow'] ?? 0, 0) }} POS
            </div>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #ef4444;">
            <h4>New Credit / Debts Created</h4>
            <div class="val" style="color: #f87171;">₦{{ number_format($periodSummary['newCreditIssued'] ?? $totalDebtCreated, 0) }}</div>
            <div class="sub">All-Time Market Debt: ₦{{ number_format($totalDebtOwedAllTime, 0) }}</div>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #a855f7;">
            <h4>Physical Stock Asset Value</h4>
            <div class="val" style="color: #c084fc;">₦{{ number_format($totalStockValuation, 0) }}</div>
            <div class="sub">{{ number_format($totalPhysicalUnits) }} Total Units on Ground</div>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #f59e0b;">
            <h4>Transfer Discrepancies</h4>
            <div class="val" style="color: #fbbf24;">{{ $totalDiscrepancyUnits }} Units</div>
            <div class="sub">Flagged on In-Transit Radar</div>
        </div>

        <div class="kpi-card" style="border-top: 4px solid #ec4899;">
            <h4>Stock Out & Deductions</h4>
            <div class="val" style="color: #f472b6;">{{ $totalDamagedUnits }} Units</div>
            <div class="sub">Non-Sale Deductions & Write-offs</div>
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
                    <strong style="color: #4ade80;">₦{{ number_format($tp->total_revenue, 0) }}</strong>
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
                        <strong style="color: #60a5fa;">₦{{ number_format($st['total'], 0) }}</strong>
                        <div style="font-size: 0.7rem; color: #4ade80;">Cash: ₦{{ number_format($st['collected'], 0) }}</div>
                    </div>
                </div>
            @empty
                <p style="font-size: 0.85rem; color: var(--text-muted);">No cashier sales recorded yet.</p>
            @endforelse
        </div>
    </div>

    <!-- 4. TABBED REPORTS NAVIGATION -->
    <div class="report-tabs">
        <button class="rep-tab-btn {{ ($currentTab ?? 'repDayBook') === 'repDayBook' ? 'active' : '' }}" onclick="showReport('repDayBook', this)">📅 Daily Day-Book & Shift Hub</button>
        <button class="rep-tab-btn {{ ($currentTab ?? 'repDayBook') === 'repSales' ? 'active' : '' }}" onclick="showReport('repSales', this)">📊 Sales & Invoices ({{ $sales->count() }})</button>
        <button class="rep-tab-btn {{ ($currentTab ?? 'repDayBook') === 'repPending' ? 'active' : '' }}" onclick="showReport('repPending', this)">⏳ Pending Orders ({{ $pendingOrders['total_orders'] ?? 0 }})</button>
        <button class="rep-tab-btn {{ ($currentTab ?? 'repDayBook') === 'repStock' ? 'active' : '' }}" onclick="showReport('repStock', this)">📦 Multi-Branch Stock ({{ $products->count() }})</button>
        <button class="rep-tab-btn {{ ($currentTab ?? 'repDayBook') === 'repTransfers' ? 'active' : '' }}" onclick="showReport('repTransfers', this)">🚚 Transfers & Waybills ({{ $transfers->count() }})</button>
        <button class="rep-tab-btn {{ ($currentTab ?? 'repDayBook') === 'repDebts' ? 'active' : '' }}" onclick="showReport('repDebts', this)">💳 Debtors Aging ({{ $debtors->count() }})</button>
        <button class="rep-tab-btn {{ ($currentTab ?? 'repDayBook') === 'repDamages' ? 'active' : '' }}" onclick="showReport('repDamages', this)">📉 Stock Out & Deductions ({{ $adjustments->count() }})</button>
        <button class="rep-tab-btn {{ ($currentTab ?? 'repDayBook') === 'repReturns' ? 'active' : '' }}" onclick="showReport('repReturns', this)">🔄 Returns & Refunds ({{ $returns->count() }})</button>
        <button class="rep-tab-btn {{ ($currentTab ?? 'repDayBook') === 'repAi' ? 'active' : '' }}" onclick="showReport('repAi', this)">🤖 AI Export Hub</button>
    </div>

    <!-- ========================================================================= -->
    <!-- MASTER TAB: DAILY DAY-BOOK & SHIFT RECONCILIATION HUB -->
    <!-- ========================================================================= -->
    <div id="repDayBook" class="report-section {{ ($currentTab ?? 'repDayBook') === 'repDayBook' ? 'active' : '' }}">
        <!-- Day-Book Action & Export Bar -->
        <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.95)); border: 1px solid rgba(59, 130, 246, 0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span style="font-size: 1.5rem;">📅</span>
                        <div>
                            <h3 style="font-size: 1.3rem; font-weight: 800; margin: 0; color: #f8fafc;">
                                Daily Operations & Shift Reconciliation Day-Book
                            </h3>
                            <p style="font-size: 0.85rem; color: #94a3b8; margin: 0.2rem 0 0 0;">
                                Period: <strong style="color: #60a5fa;">{{ $dailyReport['dateInfo']['label'] }}</strong> · Timezone: <strong style="color: #a78bfa;">Africa/Lagos (UTC+1)</strong>
                            </p>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'daily_summary'], request()->query())) }}" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.5rem 1rem; background: #2563eb; display: inline-flex; align-items: center; gap: 0.4rem;">
                        📥 Export One-Sheet CSV
                    </a>
                    <a href="{{ route('reports.export.json', array_merge(['type' => 'daily_summary'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem; color: #93c5fd; display: inline-flex; align-items: center; gap: 0.4rem;">
                        🤖 Export JSON
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                        🖨️ Print Day-Book
                    </button>
                </div>
            </div>
        </div>

        <!-- 7 OPERATIONAL METRIC CARDS IN RICH GRID -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">

            <!-- CARD 1: TOTAL AMOUNT SOLD -->
            <div class="card" style="border-left: 5px solid #22c55e; background: var(--card-bg);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #86efac; text-transform: uppercase;">💰 Total Amount Sold</div>
                        <h4 style="font-size: 1.5rem; font-weight: 800; color: #f8fafc; margin: 0.25rem 0;">
                            ₦{{ number_format($dailyReport['total_amount_sold'], 2) }}
                        </h4>
                    </div>
                    <span style="font-size: 1.5rem;">🏷️</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.6rem;">
                    <div>• Invoiced Sales: <strong style="color: #f8fafc;">{{ $dailyReport['invoice_count'] }} transactions</strong></div>
                    <div>• Average Ticket: <strong style="color: #4ade80;">₦{{ number_format($dailyReport['average_invoice'], 2) }}</strong></div>
                    <div>• Status: Gross invoiced period volume</div>
                </div>
            </div>

            <!-- CARD 2: POS & CASH COLLECTED -->
            <div class="card" style="border-left: 5px solid #3b82f6; background: var(--card-bg);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #93c5fd; text-transform: uppercase;">💵 POS & Cash Collections</div>
                        <h4 style="font-size: 1.5rem; font-weight: 800; color: #60a5fa; margin: 0.25rem 0;">
                            ₦{{ number_format($dailyReport['total_net_collections'], 2) }}
                        </h4>
                    </div>
                    <span style="font-size: 1.5rem;">💳</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.6rem;">
                    <div>• 💵 Net Cash Inflow: <strong style="color: #4ade80;">₦{{ number_format($dailyReport['net_cash_inflow'], 2) }}</strong></div>
                    <div>• 💳 Net POS Inflow: <strong style="color: #60a5fa;">₦{{ number_format($dailyReport['net_pos_inflow'], 2) }}</strong></div>
                    <div>• 🏧 Expected Cash in Drawer: <strong style="color: #fde047;">₦{{ number_format($dailyReport['drawer_physical_cash'], 2) }}</strong></div>
                </div>
            </div>

            <!-- CARD 3: CREDIT ISSUED & DEBTS RECOVERED -->
            <div class="card" style="border-left: 5px solid #f59e0b; background: var(--card-bg);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #fde047; text-transform: uppercase;">💳 Credit Issued & Debt Recovered</div>
                        <h4 style="font-size: 1.5rem; font-weight: 800; color: #fbbf24; margin: 0.25rem 0;">
                            ₦{{ number_format($dailyReport['debt_recovered'], 2) }} <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: normal;">Recovered</span>
                        </h4>
                    </div>
                    <span style="font-size: 1.5rem;">🤝</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.6rem;">
                    <div>• 🔴 New Credit Issued: <strong style="color: #f87171;">₦{{ number_format($dailyReport['new_credit_issued'], 2) }}</strong> ({{ $dailyReport['new_credit_sales_count'] }} invoices)</div>
                    <div>• 🟢 Debt Recovered: <strong style="color: #4ade80;">₦{{ number_format($dailyReport['debt_recovered'], 2) }}</strong> (Cash: ₦{{ number_format($dailyReport['debt_recovered_cash'], 0) }} · POS: ₦{{ number_format($dailyReport['debt_recovered_pos'], 0) }})</div>
                    <div>• ⚖️ Net Debt Change: <strong style="color: {{ $dailyReport['net_debt_change'] > 0 ? '#f87171' : '#4ade80' }};">{{ $dailyReport['net_debt_change'] >= 0 ? '+' : '' }}₦{{ number_format($dailyReport['net_debt_change'], 2) }}</strong></div>
                </div>
            </div>

            <!-- CARD 4: STOCK MOVEMENTS (STOCK OUT VS STOCK IN) -->
            <div class="card" style="border-left: 5px solid #a855f7; background: var(--card-bg);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #c084fc; text-transform: uppercase;">📦 Stock Movements (Out vs In)</div>
                        <h4 style="font-size: 1.3rem; font-weight: 800; color: #f8fafc; margin: 0.25rem 0;">
                            <span style="color: #f87171;">Out: {{ number_format($dailyReport['stock_out_total_units']) }}</span> · <span style="color: #4ade80;">In: {{ number_format($dailyReport['stock_in_total_units']) }}</span>
                        </h4>
                    </div>
                    <span style="font-size: 1.5rem;">🚚</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.6rem;">
                    <div>• 📤 Stock Out: Delivered ({{ number_format($dailyReport['stock_out_sales_dispatch_units']) }}) · Transferred Out ({{ number_format($dailyReport['stock_out_transfer_units']) }}) · Deductions ({{ number_format($dailyReport['stock_out_damages_units']) }})</div>
                    <div>• 📥 Stock In: Restocked ({{ number_format($dailyReport['stock_in_supplier_restock_units']) }}) · Transferred In ({{ number_format($dailyReport['stock_in_transfer_units']) }}) · Returns ({{ number_format($dailyReport['stock_in_returns_units']) }})</div>
                    <div>• 🔄 Net Inventory Movement: <strong style="color: {{ $dailyReport['net_inventory_movement_units'] >= 0 ? '#4ade80' : '#f87171' }};">{{ $dailyReport['net_inventory_movement_units'] >= 0 ? '+' : '' }}{{ number_format($dailyReport['net_inventory_movement_units']) }} units</strong></div>
                </div>
            </div>

            <!-- CARD 5: PENDING ORDERS (NEW VS CARRIED BACKLOG) -->
            <div class="card" style="border-left: 5px solid #06b6d4; background: var(--card-bg);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #67e8f9; text-transform: uppercase;">⏳ Pending Orders & Carried Backlog</div>
                        <h4 style="font-size: 1.4rem; font-weight: 800; color: #38bdf8; margin: 0.25rem 0;">
                            {{ $dailyReport['pending_orders_new_count'] + $dailyReport['pending_orders_carried_count'] }} Orders <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: normal;">Unsupplied</span>
                        </h4>
                    </div>
                    <span style="font-size: 1.5rem;">📋</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.6rem;">
                    <div>• 🆕 New in Period: <strong style="color: #38bdf8;">{{ $dailyReport['pending_orders_new_count'] }} orders ({{ number_format($dailyReport['pending_orders_new_units']) }} units) · ₦{{ number_format($dailyReport['pending_orders_new_value'], 0) }}</strong></div>
                    <div>• 📦 Carried from Earlier: <strong style="color: #fbbf24;">{{ $dailyReport['pending_orders_carried_count'] }} orders ({{ number_format($dailyReport['pending_orders_carried_units']) }} units) · ₦{{ number_format($dailyReport['pending_orders_carried_value'], 0) }}</strong></div>
                    <div style="display: flex; gap: 0.35rem; flex-wrap: wrap; margin-top: 0.25rem;">
                        <span style="background: rgba(34,197,94,0.2); color: #86efac; padding: 0.15rem 0.45rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700;">&lt;24h: {{ $dailyReport['carried_aging_under_24h'] }}</span>
                        <span style="background: rgba(245,158,11,0.2); color: #fde047; padding: 0.15rem 0.45rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700;">24-48h: {{ $dailyReport['carried_aging_24h_to_48h'] }}</span>
                        <span style="background: rgba(249,115,22,0.2); color: #fdba74; padding: 0.15rem 0.45rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700;">3-7d: {{ $dailyReport['carried_aging_3d_to_7d'] }}</span>
                        <span style="background: rgba(239,68,68,0.2); color: #fca5a5; padding: 0.15rem 0.45rem; border-radius: 6px; font-size: 0.7rem; font-weight: 700;">&gt;7d: {{ $dailyReport['carried_aging_over_7d'] }}</span>
                    </div>
                </div>
            </div>

            <!-- CARD 6: RETURNS & REFUNDS -->
            <div class="card" style="border-left: 5px solid #ec4899; background: var(--card-bg);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #f472b6; text-transform: uppercase;">🔄 Returns & Refunds Disbursed</div>
                        <h4 style="font-size: 1.5rem; font-weight: 800; color: #f472b6; margin: 0.25rem 0;">
                            ₦{{ number_format($dailyReport['refunds_amount'], 2) }}
                        </h4>
                    </div>
                    <span style="font-size: 1.5rem;">↩️</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.6rem;">
                    <div>• Return Incidents: <strong style="color: #f8fafc;">{{ $dailyReport['returns_count'] }} returns processed</strong></div>
                    <div>• Units Restocked: <strong style="color: #4ade80;">{{ number_format($dailyReport['returned_units']) }} physical units</strong></div>
                    <div>• Cash Refunds Paid Out: <strong style="color: #f472b6;">₦{{ number_format($dailyReport['refunds_amount'], 2) }}</strong></div>
                </div>
            </div>

            <!-- CARD 7: PHYSICAL STOCK REMAINING (CLOSING INVENTORY) -->
            <div class="card" style="border-left: 5px solid #10b981; background: var(--card-bg);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 800; color: #6ee7b7; text-transform: uppercase;">🏬 Closing Physical Stock on Hand</div>
                        <h4 style="font-size: 1.5rem; font-weight: 800; color: #34d399; margin: 0.25rem 0;">
                            ₦{{ number_format($dailyReport['physical_stock_remaining_value'], 2) }}
                        </h4>
                    </div>
                    <span style="font-size: 1.5rem;">📊</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.6rem;">
                    <div>• Total Shelf Units on Ground: <strong style="color: #f8fafc;">{{ number_format($dailyReport['physical_stock_remaining_units']) }} units</strong></div>
                    <div>• Valuation Basis: <strong style="color: #34d399;">Retail Selling Price</strong> (Clamped &ge; 0)</div>
                    <div>• Excludes COGS/Profit: Enforces accurate shelf value</div>
                </div>
            </div>

        </div>

        <!-- EXPANDABLE SECTION 1: NEW CREDIT & DEBT RECOVERY JOURNAL -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: #fde047;">
                    💳 Credit Issued & Debt Recovery Journal ({{ $dailyReport['new_credit_sales_count'] }} Credit Sales · {{ $dailyReport['debt_recoveries_count'] }} Recoveries)
                </h3>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <!-- New Credit Sales -->
                <div class="table-wrap">
                    <h5 style="font-size: 0.85rem; font-weight: 800; color: #f87171; padding: 0.75rem 1rem; margin: 0; background: rgba(239, 68, 68, 0.1);">
                        🔴 New Credit / Part-Paid Invoices Created
                    </h5>
                    <table>
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Invoiced</th>
                                <th>Credit Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dailyReport['new_credit_sales'] as $cs)
                                <tr>
                                    <td><strong style="color: #f8fafc;">#{{ $cs['id'] }}</strong></td>
                                    <td>
                                        <div>{{ $cs['customer_name'] }}</div>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);">{{ $cs['customer_phone'] }}</div>
                                    </td>
                                    <td>₦{{ number_format($cs['total_amount'], 0) }}</td>
                                    <td><strong style="color: #f87171;">₦{{ number_format($cs['credit_balance'], 0) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">No new credit issued in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Debt Payments Recovered -->
                <div class="table-wrap">
                    <h5 style="font-size: 0.85rem; font-weight: 800; color: #4ade80; padding: 0.75rem 1rem; margin: 0; background: rgba(34, 197, 94, 0.1);">
                        🟢 Debt Payments Recovered
                    </h5>
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Customer</th>
                                <th>Method</th>
                                <th>Recovered</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($dailyReport['debt_recoveries'] as $dr)
                                <tr>
                                    <td style="font-size: 0.75rem;">{{ \Carbon\Carbon::parse($dr->created_at)->format('H:i') }}</td>
                                    <td>
                                        <div>{{ $dr->customer->name ?? 'Debtor' }}</div>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);">{{ $dr->customer->phone ?? '—' }}</div>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 700; background: {{ $dr->payment_method === 'CASH' ? 'rgba(34,197,94,0.2)' : 'rgba(59,130,246,0.2)' }}; color: {{ $dr->payment_method === 'CASH' ? '#4ade80' : '#60a5fa' }};">
                                            {{ $dr->payment_method }}
                                        </span>
                                    </td>
                                    <td><strong style="color: #4ade80;">₦{{ number_format($dr->amount, 0) }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">No debt repayments collected in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: SALES & INVOICES -->
    <!-- ========================================================================= -->
    <div id="repSales" class="report-section {{ ($currentTab ?? 'repDayBook') === 'repSales' ? 'active' : '' }}">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Filtered Sales Transactions</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'sales'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', array_merge(['type' => 'sales'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
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
                        @php
                            $paid = $s->event_paid_amount ?? $s->paidAmount;
                            $debt = $s->debt_balance ?? max(0, $s->totalAmount - $paid);
                        @endphp
                        <tr>
                            <td><strong>#{{ substr($s->id, 0, 8) }}</strong></td>
                            <td style="font-size: 0.75rem; color: var(--text-muted);">{{ date('d M Y, h:i A', strtotime($s->createdAt)) }}</td>
                            <td>
                                <strong>{{ $s->customerName }}</strong>
                                @if($s->customerPhone)<div style="font-size: 0.75rem; color: var(--text-muted);">{{ $s->customerPhone }}</div>@endif
                            </td>
                            <td>{{ $s->items->count() }} items</td>
                            <td style="font-weight: 800;">₦{{ number_format($s->totalAmount, 0) }}</td>
                            <td style="color: #4ade80;">₦{{ number_format($paid, 0) }}</td>
                            <td>
                                @if($debt <= 0.01)
                                    <span class="badge badge-success">✓ Paid</span>
                                @elseif($paid > 0.01)
                                    <span class="badge badge-warning" style="background: #fef3c7; color: #b45309; border: 1px solid #fcd34d;">💳 Part-Paid (₦{{ number_format($debt, 0) }})</span>
                                @else
                                    <span class="badge badge-danger">🔴 Not Paid (₦{{ number_format($debt, 0) }})</span>
                                @endif
                            </td>
                            <td>
                                @if(in_array(strtoupper($s->deliveryStatus ?? ''), ['DELIVERED', 'SUPPLIED']))
                                    <span class="badge badge-success">✓ Supplied</span>
                                @else
                                    <span class="badge badge-warning" style="background: #fef3c7; color: #b45309; border: 1px solid #fcd34d;">⏳ Not Supplied</span>
                                @endif
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
    <!-- TAB: PENDING ORDERS & BACKLOG AGING -->
    <!-- ========================================================================= -->
    <div id="repPending" class="report-section {{ ($currentTab ?? 'repDayBook') === 'repPending' ? 'active' : '' }}">
        <!-- 4 Aging Buckets -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
            <div style="background: rgba(30,41,59,0.7); border: 1px solid rgba(59,130,246,0.3); border-radius: 12px; padding: 1rem;">
                <div style="font-size: 0.75rem; font-weight: 700; color: #93c5fd; text-transform: uppercase;">Fresh (&lt; 24 Hours)</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #60a5fa; margin-top: 0.25rem;">{{ $pendingOrders['under_24h'] ?? 0 }} Orders</div>
                <div style="font-size: 0.75rem; color: #94a3b8;">Normal dispatch queue</div>
            </div>
            <div style="background: rgba(30,41,59,0.7); border: 1px solid rgba(234,179,8,0.3); border-radius: 12px; padding: 1rem;">
                <div style="font-size: 0.75rem; font-weight: 700; color: #fde047; text-transform: uppercase;">Normal (24h - 48h)</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #facc15; margin-top: 0.25rem;">{{ $pendingOrders['from_24h_to_48h'] ?? 0 }} Orders</div>
                <div style="font-size: 0.75rem; color: #94a3b8;">Due for customer release</div>
            </div>
            <div style="background: rgba(30,41,59,0.7); border: 1px solid rgba(249,115,22,0.3); border-radius: 12px; padding: 1rem;">
                <div style="font-size: 0.75rem; font-weight: 700; color: #fdba74; text-transform: uppercase;">Delayed (3d - 7d)</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #fb923c; margin-top: 0.25rem;">{{ $pendingOrders['from_3d_to_7d'] ?? 0 }} Orders</div>
                <div style="font-size: 0.75rem; color: #94a3b8;">Follow-up required</div>
            </div>
            <div style="background: rgba(30,41,59,0.7); border: 1px solid rgba(239,68,68,0.3); border-radius: 12px; padding: 1rem; {{ ($pendingOrders['over_7d'] ?? 0) > 0 ? 'box-shadow: 0 0 15px rgba(239,68,68,0.25);' : '' }}">
                <div style="font-size: 0.75rem; font-weight: 700; color: #fca5a5; text-transform: uppercase;">Critical (&gt; 7 Days)</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #f87171; margin-top: 0.25rem;">
                    {{ $pendingOrders['over_7d'] ?? 0 }} Orders
                    @if(($pendingOrders['over_7d'] ?? 0) > 0)
                        <span style="font-size: 0.7rem; background: #ef4444; color: #fff; padding: 0.15rem 0.4rem; border-radius: 6px; vertical-align: middle;">⚠️ URGENT</span>
                    @endif
                </div>
                <div style="font-size: 0.75rem; color: #94a3b8;">Severely delayed collection</div>
            </div>
        </div>

        <div class="card">
            <div class="export-bar">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #fbbf24;">⏳ Pending & Unsupplied Customer Backlog</h3>
                    <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.2rem;">
                        {{ $pendingOrders['total_orders'] ?? 0 }} orders awaiting pickup · {{ number_format($pendingOrders['total_units'] ?? 0) }} total units · ₦{{ number_format($pendingOrders['total_value'] ?? 0, 0) }} committed value
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'pending_orders'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', array_merge(['type' => 'pending_orders'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice / Sale</th>
                            <th>Age</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Branch</th>
                            <th>Units</th>
                            <th>Total Value</th>
                            <th>Paid Amount</th>
                            <th>Debt Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingOrders['backlog'] ?? [] as $po)
                            <tr>
                                <td>
                                    <strong style="color: #60a5fa;">#{{ substr($po['sale_id'], 0, 8) }}</strong>
                                    <div style="font-size: 0.7rem; color: var(--text-muted);">{{ \Carbon\Carbon::parse($po['created_at'])->format('d M Y, h:i A') }}</div>
                                </td>
                                <td>
                                    @if($po['age_days'] >= 7)
                                        <span class="badge badge-danger" style="font-size: 0.72rem;">{{ $po['age_days'] }}d old (Critical)</span>
                                    @elseif($po['age_days'] >= 3)
                                        <span class="badge badge-warning" style="font-size: 0.72rem;">{{ $po['age_days'] }}d old</span>
                                    @else
                                        <span class="badge badge-info" style="font-size: 0.72rem;">{{ $po['age_days'] }}d old</span>
                                    @endif
                                </td>
                                <td><strong>{{ $po['customer_name'] }}</strong></td>
                                <td><code style="font-size: 0.8rem; color: #cbd5e1;">{{ $po['customer_phone'] ?: 'N/A' }}</code></td>
                                <td><span class="badge badge-secondary" style="font-size: 0.72rem;">{{ $po['warehouse_name'] }}</span></td>
                                <td><strong style="color: #fbbf24;">{{ number_format($po['total_units']) }}</strong></td>
                                <td><strong>₦{{ number_format($po['total_value'], 0) }}</strong></td>
                                <td><span style="color: #4ade80;">₦{{ number_format($po['paid_amount'], 0) }}</span></td>
                                <td>
                                    @if($po['debt_balance'] > 0)
                                        <span style="color: #f87171; font-weight: 700;">₦{{ number_format($po['debt_balance'], 0) }}</span>
                                    @else
                                        <span style="color: #4ade80;">Paid in Full</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-warning" style="font-size: 0.72rem;">⏳ {{ strtoupper($po['delivery_status']) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                    🎉 No pending orders! All customer orders have been supplied and collected.
                                </td>
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
    <div id="repStock" class="report-section {{ ($currentTab ?? 'repDayBook') === 'repStock' ? 'active' : '' }}">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Multi-Branch Inventory & Stock Health</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'inventory'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', array_merge(['type' => 'inventory'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
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
                            <td>₦{{ number_format($p->unitPrice, 0) }}</td>
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
                            <td style="font-weight: 800; color: #4ade80;">₦{{ number_format($p->total_valuation, 0) }}</td>
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
    <div id="repTransfers" class="report-section {{ ($currentTab ?? 'repDayBook') === 'repTransfers' ? 'active' : '' }}">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Inter-Branch Transfers & Discrepancies</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'transfers'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', array_merge(['type' => 'transfers'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
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
    <div id="repDebts" class="report-section {{ ($currentTab ?? 'repDayBook') === 'repDebts' ? 'active' : '' }}">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Customer Debt Aging & Recovery Ledger</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'debtors'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', array_merge(['type' => 'debtors'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
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
                                ₦{{ number_format($d->total_debt, 0) }}
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
    <div id="repDamages" class="report-section {{ ($currentTab ?? 'repDayBook') === 'repDamages' ? 'active' : '' }}">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Stock Out, Non-Sale Deductions & Write-Offs Audit</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'damages'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', array_merge(['type' => 'damages'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Shop Location</th>
                            <th>Product Item</th>
                            <th>Stock Out Category</th>
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
                        <tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">No stock out or deduction records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 6: RETURNS & REFUNDS -->
    <!-- ========================================================================= -->
    <div id="repReturns" class="report-section {{ ($currentTab ?? 'repDayBook') === 'repReturns' ? 'active' : '' }}">
        <div class="card">
            <div class="export-bar">
                <h3 style="font-size: 1.15rem; font-weight: 800;">Customer Returns & Refunds Audit Ledger</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'returns'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem;">📥 Export CSV</a>
                    <a href="{{ route('reports.export.json', array_merge(['type' => 'returns'], request()->query())) }}" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.85rem; color: #93c5fd;">🤖 Export JSON</a>
                </div>
            </div>

            <div style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); border-radius: 12px; padding: 0.75rem 1rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.9rem; color: #cbd5e1;">Total Value Refunded/Credited in Selected Period:</span>
                <strong style="color: #fca5a5; font-size: 1.2rem;">₦{{ number_format($totalRefunded, 0) }}</strong>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Sale Invoice</th>
                            <th>Customer Name</th>
                            <th>Returned Product</th>
                            <th>Qty Returned</th>
                            <th>Refunded Amount</th>
                            <th>Reason</th>
                            <th>Staff Responsible</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($returns as $r)
                        <tr>
                            <td style="font-size: 0.75rem; color: var(--text-muted);">{{ date('d M Y, h:i A', strtotime($r->createdAt)) }}</td>
                            <td>
                                <span style="font-family: monospace; font-size: 0.8rem; background: rgba(255,255,255,0.06); padding: 0.2rem 0.4rem; border-radius: 6px;">
                                    #{{ substr($r->saleId, 0, 8) }}
                                </span>
                            </td>
                            <td><strong>{{ $r->customerName ?? 'Walk-in Customer' }}</strong></td>
                            <td>
                                <strong>{{ $r->productName }}</strong>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $r->productCode }}</div>
                            </td>
                            <td style="font-weight: 800; color: #fbbf24;">{{ $r->quantity }}</td>
                            <td style="font-weight: 800; color: #f87171;">₦{{ number_format($r->refundAmount, 0) }}</td>
                            <td style="font-size: 0.8rem; color: #cbd5e1;">{{ $r->reason }}</td>
                            <td><strong>{{ $r->userName }}</strong></td>
                        </tr>
                        @empty
                        <tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-muted);">No sales returns recorded in the selected period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 7: AI DATA EXPORT HUB -->
    <!-- ========================================================================= -->
    <div id="repAi" class="report-section {{ ($currentTab ?? 'repDayBook') === 'repAi' ? 'active' : '' }}">
        <div class="card">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; color: #93c5fd;">
                🤖 AI Business Intelligence & Prompt Ingestion Hub
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Download pre-structured JSON and CSV datasets optimized for feeding into Large Language Models (ChatGPT, Claude, Gemini, DeepSeek) or Python/Excel for forecasting, profit margin analysis, and inventory optimization.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                <div style="background: rgba(15,23,42,0.6); border: 1px solid rgba(37,99,235,0.4); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #93c5fd; margin-bottom: 0.35rem;">📅 Daily Operations Day-Book</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">One-sheet consolidated shift reconciliation, total sales, drawer cash, new credit, debts recovered, and stock valuation.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', array_merge(['type' => 'day_book'], request()->query())) }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', array_merge(['type' => 'day_book'], request()->query())) }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #4ade80; margin-bottom: 0.35rem;">📊 Complete Sales Data</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">All customer transactions, payment methods, debt balances, and cashier records.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', array_merge(['type' => 'sales'], request()->query())) }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', array_merge(['type' => 'sales'], request()->query())) }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #60a5fa; margin-bottom: 0.35rem;">📦 Inventory & Branch Valuations</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">All catalog SKUs, shelf counts per branch, low stock warnings, and total ₦ asset values.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', array_merge(['type' => 'inventory'], request()->query())) }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', array_merge(['type' => 'inventory'], request()->query())) }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #fbbf24; margin-bottom: 0.35rem;">🚚 Logistics & In-Transit Transfers</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Transfer history, carrier driver tracking, and verified count discrepancy flags.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', array_merge(['type' => 'transfers'], request()->query())) }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', array_merge(['type' => 'transfers'], request()->query())) }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #f87171; margin-bottom: 0.35rem;">💳 Customer Debtors & Aging</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Complete debtor contact details, total debt exposure, and aging risk buckets.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', array_merge(['type' => 'debtors'], request()->query())) }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', array_merge(['type' => 'debtors'], request()->query())) }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #fca5a5; margin-bottom: 0.35rem;">🔄 Sales Returns & Refunds</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Complete logs of returned items, quantity, refund amounts, and reasons.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', array_merge(['type' => 'returns'], request()->query())) }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', array_merge(['type' => 'returns'], request()->query())) }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #fde047; margin-bottom: 0.35rem;">⏳ Pending Orders & Aging Backlog</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Unsupplied customer orders, pickup status, committed stock value, and aging buckets.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', array_merge(['type' => 'pending_orders'], request()->query())) }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', array_merge(['type' => 'pending_orders'], request()->query())) }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #fb7185; margin-bottom: 0.35rem;">📉 Stock Out & Deductions</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Write-offs for damages, expiration, inventory losses, and manual admin adjustments.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', array_merge(['type' => 'stock_out'], request()->query())) }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', array_merge(['type' => 'stock_out'], request()->query())) }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
                    </div>
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem;">
                    <h4 style="font-size: 1rem; font-weight: 800; color: #a78bfa; margin-bottom: 0.35rem;">🛡️ Activity Audit Log</h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">Immutable chronological audit trail of all staff logins, sales, returns, and configuration edits.</p>
                    <div style="display: flex; gap: 0.5rem;">
                        <a href="{{ route('reports.export.csv', array_merge(['type' => 'activities'], request()->query())) }}" class="btn btn-secondary" style="flex: 1; font-size: 0.8rem;">CSV (Excel)</a>
                        <a href="{{ route('reports.export.json', array_merge(['type' => 'activities'], request()->query())) }}" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; background: #6366f1;">JSON (AI)</a>
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
    const target = document.getElementById(repId);
    if (target) {
        target.classList.add('active');
    }
    const tabInput = document.getElementById('activeTabInput');
    if (tabInput) {
        tabInput.value = repId;
    }
    const url = new URL(window.location);
    url.searchParams.set('tab', repId);
    window.history.replaceState({}, '', url);
}
</script>
@endpush
