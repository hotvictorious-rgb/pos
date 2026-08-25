@extends('layouts.app')

@section('title', 'Universal History & Transaction Ledgers')

@push('styles')
<style>
    .tab-nav-container {
        display: flex;
        gap: 0.5rem;
        border-bottom: 2px solid var(--border);
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
        overflow-x: auto;
        white-space: nowrap;
        scrollbar-width: thin;
    }

    .tab-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.65rem 1.15rem;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.9rem;
        text-decoration: none;
        color: var(--text-muted);
        background: transparent;
        border: 1px solid transparent;
        transition: all 0.15s ease-in-out;
    }

    .tab-btn:hover {
        color: #f8fafc;
        background: rgba(255, 255, 255, 0.05);
    }

    .tab-btn.active {
        color: #ffffff;
        background: var(--primary);
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
    }

    .tab-btn .badge-pill {
        background: rgba(0, 0, 0, 0.35);
        padding: 0.15rem 0.5rem;
        border-radius: 99px;
        font-size: 0.75rem;
        font-weight: 800;
    }

    .filter-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .date-pill {
        padding: 0.35rem 0.75rem;
        border-radius: 99px;
        font-size: 0.78rem;
        font-weight: 700;
        border: 1px solid var(--border);
        background: rgba(11, 15, 25, 0.6);
        color: var(--text-muted);
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s;
    }
    .date-pill.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .summary-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.25rem;
    }

    .summary-card h4 {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 0.35rem;
        letter-spacing: 0.05em;
    }
    .summary-card .val {
        font-size: 1.35rem;
        font-weight: 800;
    }

    .table-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow: hidden;
    }

    .table-top-bar {
        padding: 1rem 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .table-wrap {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    th {
        background: rgba(11, 15, 25, 0.8);
        padding: 1rem 1.25rem;
        font-size: 0.8rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--border);
    }

    td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.95rem;
    }

    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(55, 65, 81, 0.25); }

    .action-btn-group {
        display: flex;
        gap: 0.35rem;
        align-items: center;
        white-space: nowrap;
    }
</style>
@endpush

@section('content')

    <!-- Top Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 1.75rem;">📜</span>
                <h2 style="font-size: 1.5rem; font-weight: 800;">Universal History & Ledgers Hub</h2>
            </div>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Complete, verifiable audit trail and printable receipts/vouchers for all stock and financial transactions.
            </p>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="{{ route('reports.index') }}" class="btn btn-secondary" style="font-size: 0.85rem;">
                📊 Executive Reports
            </a>
            <a href="{{ route('auditor.index') }}" class="btn btn-secondary" style="font-size: 0.85rem; color: #fca5a5;">
                🛡️ Anti-Theft Hub
            </a>
        </div>
    </div>

    <!-- 8 Independent Tabs Navigation Bar -->
    <div class="tab-nav-container">
        <!-- 1. Sales -->
        <a href="{{ route('transactions.index', ['tab' => 'sales']) }}" 
           class="tab-btn {{ $activeTab === 'sales' ? 'active' : '' }}">
            <span>💰 Sales Invoices</span>
            <span class="badge-pill">{{ number_format($totalSalesCount) }}</span>
        </a>

        <!-- 2. Stock In -->
        <a href="{{ route('transactions.index', ['tab' => 'stock_in']) }}" 
           class="tab-btn {{ $activeTab === 'stock_in' ? 'active' : '' }}">
            <span>📥 Stock In</span>
            <span class="badge-pill">{{ number_format($stockInBatches) }}</span>
        </a>

        <!-- 3. Stock Out -->
        <a href="{{ route('transactions.index', ['tab' => 'stock_out']) }}" 
           class="tab-btn {{ $activeTab === 'stock_out' ? 'active' : '' }}">
            <span>📤 Stock Out & Dispatches</span>
            <span class="badge-pill">{{ number_format($stockOutCount) }}</span>
        </a>

        <!-- 4. In Transit -->
        <a href="{{ route('transactions.index', ['tab' => 'in_transit']) }}" 
           class="tab-btn {{ $activeTab === 'in_transit' ? 'active' : '' }}">
            <span>🚚 In-Transit Buffer</span>
            <span class="badge-pill">{{ number_format($inTransitCount) }}</span>
        </a>

        <!-- 5. Incoming Transfers -->
        <a href="{{ route('transactions.index', ['tab' => 'transfers_in']) }}" 
           class="tab-btn {{ $activeTab === 'transfers_in' ? 'active' : '' }}">
            <span>🏢 Incoming Transfers</span>
            <span class="badge-pill">{{ number_format($incomingTotal) }}</span>
        </a>

        <!-- 6. Returns -->
        <a href="{{ route('transactions.index', ['tab' => 'returns']) }}" 
           class="tab-btn {{ $activeTab === 'returns' ? 'active' : '' }}">
            <span>🔄 Returns</span>
            <span class="badge-pill">{{ number_format($returnsCount) }}</span>
        </a>

        <!-- 7. Refunds -->
        <a href="{{ route('transactions.index', ['tab' => 'refunds']) }}" 
           class="tab-btn {{ $activeTab === 'refunds' ? 'active' : '' }}">
            <span>💸 Customer Refunds</span>
            <span class="badge-pill">{{ number_format($refundsCount) }}</span>
        </a>

        <!-- 8. Debts -->
        <a href="{{ route('transactions.index', ['tab' => 'debts']) }}" 
           class="tab-btn {{ $activeTab === 'debts' ? 'active' : '' }}">
            <span>💳 Debts Ledger</span>
            <span class="badge-pill">{{ number_format($debtsEntryCount) }}</span>
        </a>
    </div>

    <!-- Multi-Criteria Filter Bar (Adaptive per active tab) -->
    <div class="filter-card">
        <form method="GET" action="{{ route('transactions.index') }}" id="filterForm">
            <input type="hidden" name="tab" value="{{ $activeTab }}">

            <!-- Quick Date Pills -->
            <div style="display: flex; gap: 0.4rem; margin-bottom: 0.85rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Quick Dates:</span>
                <a href="{{ route('transactions.index', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['tab' => $activeTab, 'date_preset' => 'ALL'])) }}" class="date-pill {{ $datePreset === 'ALL' && !request('from_date') ? 'active' : '' }}">All Time</a>
                <a href="{{ route('transactions.index', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['tab' => $activeTab, 'date_preset' => 'TODAY'])) }}" class="date-pill {{ $datePreset === 'TODAY' ? 'active' : '' }}">Today</a>
                <a href="{{ route('transactions.index', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['tab' => $activeTab, 'date_preset' => 'YESTERDAY'])) }}" class="date-pill {{ $datePreset === 'YESTERDAY' ? 'active' : '' }}">Yesterday</a>
                <a href="{{ route('transactions.index', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['tab' => $activeTab, 'date_preset' => 'THIS_WEEK'])) }}" class="date-pill {{ $datePreset === 'THIS_WEEK' ? 'active' : '' }}">This Week</a>
                <a href="{{ route('transactions.index', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['tab' => $activeTab, 'date_preset' => 'THIS_MONTH'])) }}" class="date-pill {{ $datePreset === 'THIS_MONTH' ? 'active' : '' }}">This Month</a>
            </div>

            <!-- Filter Inputs Grid -->
            <div class="grid-4" style="gap: 0.75rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </div>

                <!-- Tab-Specific Filters -->
                @if($activeTab === 'sales')
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Payment Status</label>
                        <select name="payment_status">
                            <option value="">-- All Payment Statuses --</option>
                            <option value="PAID" {{ request('payment_status') === 'PAID' ? 'selected' : '' }}>🟢 Paid in Full</option>
                            <option value="PARTIAL" {{ request('payment_status') === 'PARTIAL' ? 'selected' : '' }}>⚠️ Part-Paid (Debt)</option>
                            <option value="UNPAID" {{ request('payment_status') === 'UNPAID' ? 'selected' : '' }}>🔴 Unpaid (Full Debt)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Delivery / Handover</label>
                        <select name="delivery_status">
                            <option value="">-- All Handover States --</option>
                            <option value="SUPPLIED" {{ in_array(request('delivery_status'), ['SUPPLIED', 'DELIVERED']) ? 'selected' : '' }}>🟢 Supplied & Handed Over</option>
                            <option value="UNSUPPLIED" {{ request('delivery_status') === 'UNSUPPLIED' ? 'selected' : '' }}>⏳ Not Supplied (Awaiting Pickup)</option>
                        </select>
                    </div>
                @elseif($activeTab === 'stock_in')
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Product SKU</label>
                        <select name="product_id">
                            <option value="">-- All Products --</option>
                            @foreach($products as $prod)
                                <option value="{{ $prod->id }}" {{ request('product_id') == $prod->id ? 'selected' : '' }}>{{ $prod->name }} ({{ $prod->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Received By Staff</label>
                        <select name="user_name">
                            <option value="">-- All Staff --</option>
                            @foreach($cashiers as $cName)
                                <option value="{{ $cName }}" {{ request('user_name') === $cName ? 'selected' : '' }}>{{ $cName }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($activeTab === 'stock_out')
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Outflow Event Type</label>
                        <select name="movement_type">
                            <option value="">-- All Outflow Types --</option>
                            <option value="DISPATCH" {{ request('movement_type') === 'DISPATCH' ? 'selected' : '' }}>📦 Customer Pickup Handover</option>
                            <option value="TRANSFER_OUT" {{ request('movement_type') === 'TRANSFER_OUT' ? 'selected' : '' }}>🚚 Transfer Out</option>
                            <option value="DAMAGE" {{ request('movement_type') === 'DAMAGE' ? 'selected' : '' }}>📉 Damaged Goods Write-off</option>
                            <option value="EXPIRED" {{ request('movement_type') === 'EXPIRED' ? 'selected' : '' }}>⏰ Expired Stock</option>
                            <option value="LOST" {{ request('movement_type') === 'LOST' ? 'selected' : '' }}>🔍 Lost / Audit Adjustment</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Product SKU</label>
                        <select name="product_id">
                            <option value="">-- All Products --</option>
                            @foreach($products as $prod)
                                <option value="{{ $prod->id }}" {{ request('product_id') == $prod->id ? 'selected' : '' }}>{{ $prod->name }} ({{ $prod->code }})</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($activeTab === 'in_transit')
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Carrier / Driver</label>
                        <select name="carrier_name">
                            <option value="">-- All Carriers --</option>
                            @foreach($carriers as $c)
                                <option value="{{ $c }}" {{ request('carrier_name') === $c ? 'selected' : '' }}>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Origin Branch</label>
                        <select name="source_warehouse_id">
                            <option value="">-- All Origin Branches --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ request('source_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($activeTab === 'transfers_in')
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Transfer Status</label>
                        <select name="transfer_status">
                            <option value="">-- All Statuses --</option>
                            <option value="RECEIVED" {{ request('transfer_status') === 'RECEIVED' ? 'selected' : '' }}>✓ Received & Verified</option>
                            <option value="DISCREPANCY" {{ request('transfer_status') === 'DISCREPANCY' ? 'selected' : '' }}>🚨 Discrepancy / Variance</option>
                            <option value="DISPATCHED" {{ request('transfer_status') === 'DISPATCHED' ? 'selected' : '' }}>🚚 In-Transit (Pending Count)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Receiving Branch</label>
                        <select name="dest_warehouse_id">
                            <option value="">-- All Receiving Branches --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ request('dest_warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($activeTab === 'returns')
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Return Reason</label>
                        <select name="return_reason">
                            <option value="">-- All Reasons --</option>
                            <option value="Defective" {{ request('return_reason') === 'Defective' ? 'selected' : '' }}>Defective or Damaged</option>
                            <option value="Wrong" {{ request('return_reason') === 'Wrong' ? 'selected' : '' }}>Wrong Product</option>
                            <option value="Exchange" {{ request('return_reason') === 'Exchange' ? 'selected' : '' }}>Customer Mind Change / Exchange</option>
                            <option value="Expired" {{ request('return_reason') === 'Expired' ? 'selected' : '' }}>Expired Date</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Staff Officer</label>
                        <select name="user_name">
                            <option value="">-- All Officers --</option>
                            @foreach($cashiers as $cName)
                                <option value="{{ $cName }}" {{ request('user_name') === $cName ? 'selected' : '' }}>{{ $cName }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($activeTab === 'refunds')
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Min Refund (₦)</label>
                        <input type="number" name="min_amount" value="{{ request('min_amount') }}" placeholder="e.g. 5000">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Authorized Officer</label>
                        <select name="user_name">
                            <option value="">-- All Officers --</option>
                            @foreach($cashiers as $cName)
                                <option value="{{ $cName }}" {{ request('user_name') === $cName ? 'selected' : '' }}>{{ $cName }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($activeTab === 'debts')
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Ledger Entry Type</label>
                        <select name="ledger_type">
                            <option value="">-- All Entry Types --</option>
                            <option value="PAYMENT" {{ request('ledger_type') === 'PAYMENT' ? 'selected' : '' }}>💵 Part Payment Received</option>
                            <option value="INVOICE" {{ request('ledger_type') === 'INVOICE' ? 'selected' : '' }}>💳 Debt Incurred</option>
                            <option value="RETURN_CREDIT" {{ request('ledger_type') === 'RETURN_CREDIT' ? 'selected' : '' }}>🔄 Return Credit Offset</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.75rem;">Payment Method</label>
                        <select name="payment_method">
                            <option value="">-- All Methods --</option>
                            <option value="CASH" {{ request('payment_method') === 'CASH' ? 'selected' : '' }}>Cash</option>
                            <option value="POS" {{ request('payment_method') === 'POS' ? 'selected' : '' }}>POS Terminal</option>
                            <option value="TRANSFER" {{ request('payment_method') === 'TRANSFER' ? 'selected' : '' }}>Bank Transfer</option>
                        </select>
                    </div>
                @endif
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 0.85rem; flex-wrap: wrap; align-items: center;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="🔍 Search references, customer names, SKUs, drivers across database...">
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem; font-weight: 700;">
                    🔍 Apply Filters
                </button>

                <a href="{{ route('transactions.index', ['tab' => $activeTab]) }}" class="btn btn-secondary" style="padding: 0.65rem 1rem;">
                    Reset
                </a>

                <div style="display: flex; gap: 0.5rem; margin-left: auto; flex-wrap: wrap;">
                    <a href="{{ route('transactions.export.csv', array_merge(request()->all(), ['tab' => $activeTab])) }}" class="btn btn-success" style="padding: 0.65rem 1.15rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem; box-shadow: 0 4px 12px rgba(22,163,74,0.35);">
                        <span>📥</span> Export Filtered CSV
                    </a>
                    <a href="{{ route('transactions.export.json', array_merge(request()->all(), ['tab' => $activeTab])) }}" class="btn btn-secondary" style="padding: 0.65rem 1.15rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <span>📄</span> Export JSON
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- TAB 1: SALES INVOICES HISTORY -->
    <!-- ───────────────────────────────────────────────────────────── -->
    @if($activeTab === 'sales')
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Invoices</h4>
                <div class="val" style="color: #60a5fa;">{{ number_format($totalSalesCount) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Gross Sales</h4>
                <div class="val" style="color: #f8fafc;">₦{{ number_format($totalRevenue, 0) }}</div>
            </div>
            <div class="summary-card">
                <h4>Cash / POS Collected</h4>
                <div class="val" style="color: #4ade80;">₦{{ number_format($totalPaid, 0) }}</div>
            </div>
            <div class="summary-card">
                <h4>Outstanding Debt Created</h4>
                <div class="val" style="color: #f87171;">₦{{ number_format($totalDebt, 0) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" id="liveSearchSales" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('salesTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'sales') }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                    <a href="{{ route('reports.export.json', 'sales') }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📊 Export JSON
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="salesTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Invoice Ref</th>
                            <th>Customer</th>
                            <th>Items Count</th>
                            <th>Total Bill</th>
                            <th>Paid Amount</th>
                            <th>Payment Status</th>
                            <th>Handover Status</th>
                            <th>Cashier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                        @php
                            $balance = max(0, $sale->totalAmount - $sale->paidAmount);
                            $isSupplied = in_array(strtoupper($sale->deliveryStatus ?? ''), ['DELIVERED', 'SUPPLIED']);
                        @endphp
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($sale->createdAt)) }}
                            </td>
                            <td><strong style="color: #93c5fd;">#{{ substr($sale->id, 0, 8) }}</strong></td>
                            <td>
                                <strong>{{ $sale->customerName ?: 'Walk-in Customer' }}</strong>
                                @if($sale->customerPhone)
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $sale->customerPhone }}</div>
                                @endif
                            </td>
                            <td><span class="badge badge-info">{{ count($sale->items ?? []) }} items</span></td>
                            <td style="font-weight: 800; font-size: 1rem; color: #f8fafc;">
                                ₦{{ number_format($sale->totalAmount, 0) }}
                            </td>
                            <td style="font-weight: 700; color: #4ade80;">
                                ₦{{ number_format($sale->paidAmount, 0) }}
                            </td>
                            <td>
                                @if($sale->paidAmount >= $sale->totalAmount)
                                    <span class="badge badge-success">✓ Paid</span>
                                @elseif($sale->paidAmount > 0)
                                    <span class="badge badge-warning">💳 Part-Paid (Owes ₦{{ number_format($balance, 0) }})</span>
                                @else
                                    <span class="badge badge-danger">🔴 Unpaid (Owes ₦{{ number_format($balance, 0) }})</span>
                                @endif
                            </td>
                            <td>
                                @if($isSupplied)
                                    <span class="badge badge-success">🟢 Supplied</span>
                                @else
                                    <span class="badge badge-warning">⏳ Awaiting Pickup</span>
                                @endif
                            </td>
                            <td style="font-size: 0.85rem; color: #cbd5e1;">{{ $sale->userName ?: 'Cashier' }}</td>
                            <td>
                                <div class="action-btn-group">
                                    <a href="{{ route('pos.receipt', $sale->id) }}" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" target="_blank">
                                        🧾 Receipt
                                    </a>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewSaleDetails({{ json_encode($sale) }})">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No sales invoices found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $sales->links() }}
            </div>
        </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- TAB 2: STOCK IN HISTORY -->
    <!-- ───────────────────────────────────────────────────────────── -->
    @elseif($activeTab === 'stock_in')
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Stock In Batches</h4>
                <div class="val" style="color: #4ade80;">{{ number_format($stockInBatches) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Physical Units Added</h4>
                <div class="val" style="color: #4ade80;">+{{ number_format($stockInUnits) }} units</div>
            </div>
            <div class="summary-card">
                <h4>Distinct SKUs Stocked</h4>
                <div class="val" style="color: #60a5fa;">{{ number_format($stockInProducts) }} items</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('stockInTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'stock') }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="stockInTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Product SKU</th>
                            <th>Quantity Added</th>
                            <th>Description / Supplier</th>
                            <th>Received By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stockInLogs as $log)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($log->timestamp)) }}
                            </td>
                            <td><strong style="color: #60a5fa; font-size: 1.05rem; letter-spacing: 0.03em;">{{ $log->productCode ?: $log->productName }}</strong></td>
                            <td style="font-weight: 800; font-size: 1.05rem; color: #4ade80;">
                                +{{ number_format($log->quantity) }} units
                            </td>
                            <td style="color: #cbd5e1;">{{ $log->description ?: 'Supplier Arrival / Purchase' }}</td>
                            <td><strong>{{ $log->userName ?: 'Storekeeper' }}</strong></td>
                            <td>
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="printGenericVoucher('GOODS RECEIVED NOTE (GRN)', 'GRN-{{ substr(md5($log->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($log->timestamp)) }}', 'Supplier / Source', '{{ addslashes($log->description ?: 'Official Supplier') }}', 'STOCK INFLOW', '#22c55e', [{name: '{{ addslashes($log->productCode ?: $log->productName) }}', qty: '{{ $log->quantity }} units', note: 'Added directly to physical shelf count'}], 'Total Units: +{{ $log->quantity }} units', '{{ addslashes($log->userName ?: 'Storekeeper') }}', 'Physical stock verified and added to shelf balance.')">
                                        📄 Print GRN
                                    </button>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewGenericDetails('Goods Received Entry (Stock In)', 'GRN-{{ substr(md5($log->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($log->timestamp)) }}', 'Supplier / Description', '{{ addslashes($log->description ?: 'Supplier Arrival') }}', 'Stock Inflow', '#22c55e', [{label: 'Product SKU', val: '{{ addslashes($log->productCode ?: $log->productName) }}'}, {label: 'Quantity Added', val: '+{{ $log->quantity }} units', color: '#4ade80'}, {label: 'Officer', val: '{{ addslashes($log->userName ?: 'Storekeeper') }}'}], 'Physical inventory count increased by {{ $log->quantity }} units.')">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No stock in entries found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $stockInLogs->links() }}
            </div>
        </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- TAB 3: STOCK OUT & DISPATCHES HISTORY -->
    <!-- ───────────────────────────────────────────────────────────── -->
    @elseif($activeTab === 'stock_out')
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Outflow Events</h4>
                <div class="val" style="color: #f87171;">{{ number_format($stockOutCount) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Physical Units Out</h4>
                <div class="val" style="color: #f87171;">-{{ number_format($stockOutUnits) }} units</div>
            </div>
            <div class="summary-card">
                <h4>Pickup Deliveries</h4>
                <div class="val" style="color: #4ade80;">{{ number_format($stockOutFulfilled) }} orders</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('stockOutTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'stock') }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="stockOutTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Event Type</th>
                            <th>Product SKU</th>
                            <th>Units Out</th>
                            <th>Description</th>
                            <th>Authorized Officer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stockOutLogs as $log)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($log->timestamp)) }}
                            </td>
                            <td>
                                @if(str_contains($log->type, 'DISPATCH_FULFILLED'))
                                    <span class="badge badge-success">📦 Customer Pickup</span>
                                @elseif(str_contains($log->type, 'TRANSFER_OUT'))
                                    <span class="badge badge-info">🚚 Transfer Dispatch</span>
                                @elseif(str_contains($log->type, 'DAMAGE'))
                                    <span class="badge badge-danger">📉 Damage Write-off</span>
                                @elseif(str_contains($log->type, 'EXPIRED'))
                                    <span class="badge badge-warning">⏰ Expired Stock</span>
                                @elseif(str_contains($log->type, 'LOST'))
                                    <span class="badge badge-secondary">🔍 Lost / Audit</span>
                                @else
                                    <span class="badge badge-secondary">{{ $log->type }}</span>
                                @endif
                            </td>
                            <td><strong style="color: #60a5fa; font-size: 1.05rem; letter-spacing: 0.03em;">{{ $log->productCode ?: $log->productName }}</strong></td>
                            <td style="font-weight: 800; font-size: 1.05rem; color: #f87171;">
                                {{ number_format($log->quantity) }} units
                            </td>
                            <td style="color: #cbd5e1;">{{ $log->description }}</td>
                            <td><strong>{{ $log->userName }}</strong></td>
                            <td>
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="printGenericVoucher('STOCK DISPATCH & OUTFLOW SLIP', 'OUT-{{ substr(md5($log->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($log->timestamp)) }}', 'Outflow Type', '{{ addslashes($log->type) }}', 'PHYSICAL OUTFLOW', '#ef4444', [{name: '{{ addslashes($log->productCode ?: $log->productName) }}', qty: '-{{ $log->quantity }} units', note: '{{ addslashes($log->description) }}'}], 'Total Outflow: -{{ $log->quantity }} units', '{{ addslashes($log->userName) }}', 'Goods officially dispatched from physical shelf inventory.')">
                                        📄 Print Slip
                                    </button>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewGenericDetails('Stock Outflow Record', 'OUT-{{ substr(md5($log->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($log->timestamp)) }}', 'Event Type', '{{ addslashes($log->type) }}', 'Stock Outflow', '#ef4444', [{label: 'Product SKU', val: '{{ addslashes($log->productCode ?: $log->productName) }}'}, {label: 'Deducted Units', val: '-{{ $log->quantity }} units', color: '#f87171'}, {label: 'Description', val: '{{ addslashes($log->description) }}'}, {label: 'Authorized By', val: '{{ addslashes($log->userName) }}'}], 'Physical count reduced by {{ $log->quantity }} units.')">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No stock outflow records found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $stockOutLogs->links() }}
            </div>
        </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- TAB 4: IN-TRANSIT BUFFER (ON VEHICLES) -->
    <!-- ───────────────────────────────────────────────────────────── -->
    @elseif($activeTab === 'in_transit')
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Active In-Transit Shipments</h4>
                <div class="val" style="color: #fbbf24;">{{ number_format($inTransitCount) }}</div>
            </div>
            <div class="summary-card">
                <h4>Units on Vehicles Moving Between Shops</h4>
                <div class="val" style="color: #fbbf24;">{{ number_format($inTransitUnits) }} units</div>
            </div>
            <div class="summary-card">
                <h4>Assigned Drivers / Carriers</h4>
                <div class="val" style="color: #60a5fa;">{{ number_format($inTransitCarriers) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('inTransitTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'transfers') }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="inTransitTable">
                    <thead>
                        <tr>
                            <th>Dispatched Date</th>
                            <th>Waybill Ref</th>
                            <th>Source Branch (Origin)</th>
                            <th>Destination Branch</th>
                            <th>Driver / Carrier</th>
                            <th>Items Count</th>
                            <th>Dispatched Units</th>
                            <th>Dispatched By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inTransitTransfers as $trf)
                        @php
                            $totalUnits = $trf->items->sum('dispatched_qty');
                        @endphp
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($trf->created_at)) }}
                            </td>
                            <td><strong style="color: #93c5fd;">{{ $trf->transfer_no }}</strong></td>
                            <td>🏢 {{ $trf->source->name ?? 'Origin Branch' }}</td>
                            <td>🏪 <strong>{{ $trf->destination->name ?? 'Destination' }}</strong></td>
                            <td>{{ $trf->carrier_name }}</td>
                            <td><span class="badge badge-info">{{ count($trf->items) }} SKUs</span></td>
                            <td style="font-weight: 800; color: #fbbf24; font-size: 1.05rem;">
                                {{ number_format($totalUnits) }} units
                            </td>
                            <td>{{ $trf->dispatched_by }}</td>
                            <td>
                                <div class="action-btn-group">
                                    <a href="{{ route('stock.waybill', $trf->id) }}" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" target="_blank">
                                        📄 Waybill
                                    </a>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewTransferDetails({{ json_encode($trf) }})">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No shipments currently in-transit matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $inTransitTransfers->links() }}
            </div>
        </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- TAB 5: INCOMING TRANSFERS & COUNT VERIFICATIONS HISTORY -->
    <!-- ───────────────────────────────────────────────────────────── -->
    @elseif($activeTab === 'transfers_in')
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Transfers Received</h4>
                <div class="val" style="color: #4ade80;">{{ number_format($incomingReceived) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Units Verified & Added</h4>
                <div class="val" style="color: #4ade80;">+{{ number_format($incomingUnits) }} units</div>
            </div>
            <div class="summary-card">
                <h4>Discrepancy Alerts</h4>
                <div class="val" style="color: #f87171;">{{ number_format($incomingDiscrepancies) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('incomingTransfersTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'transfers') }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="incomingTransfersTable">
                    <thead>
                        <tr>
                            <th>Date Dispatched</th>
                            <th>Transfer Waybill</th>
                            <th>Origin Branch</th>
                            <th>Receiving Branch</th>
                            <th>Carrier Name</th>
                            <th>Status</th>
                            <th>Dispatched</th>
                            <th>Counted</th>
                            <th>Missing Variance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incomingTransfers as $trf)
                        @php
                            $dispUnits = $trf->items->sum('dispatched_qty');
                            $recvUnits = $trf->items->sum('received_qty');
                            $discUnits = $trf->items->sum('discrepancy_qty');
                        @endphp
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($trf->dispatched_at ?: $trf->created_at)) }}
                            </td>
                            <td><strong style="color: #93c5fd;">{{ $trf->transfer_no }}</strong></td>
                            <td>🏢 {{ $trf->source->name ?? 'Shop A' }}</td>
                            <td>🏪 {{ $trf->destination->name ?? 'Shop B' }}</td>
                            <td>{{ $trf->carrier_name }}</td>
                            <td>
                                @if($trf->status === 'RECEIVED')
                                    <span class="badge badge-success">✓ Received & Verified</span>
                                @elseif($trf->status === 'DISCREPANCY')
                                    <span class="badge badge-danger">🚨 Variance / Missing</span>
                                @else
                                    <span class="badge badge-warning">🚚 In-Transit / Pending Count</span>
                                @endif
                            </td>
                            <td style="font-weight: 700;">{{ number_format($dispUnits) }}</td>
                            <td style="font-weight: 700; color: #4ade80;">{{ $trf->status === 'DISPATCHED' ? '-' : number_format($recvUnits) }}</td>
                            <td>
                                @if($discUnits > 0)
                                    <strong style="color: #f87171;">{{ number_format($discUnits) }} units MISSING</strong>
                                @else
                                    <span style="color: #4ade80;">0 Variance</span>
                                @endif
                            </td>
                            <td>
                                <div class="action-btn-group">
                                    <a href="{{ route('stock.waybill', $trf->id) }}" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" target="_blank">
                                        📄 Waybill
                                    </a>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewTransferDetails({{ json_encode($trf) }})">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No incoming transfer records found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $incomingTransfers->links() }}
            </div>
        </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- TAB 6: RETURNS & SHELF RESTITUTIONS HISTORY -->
    <!-- ───────────────────────────────────────────────────────────── -->
    @elseif($activeTab === 'returns')
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Return Cases</h4>
                <div class="val" style="color: #fbbf24;">{{ number_format($returnsCount) }}</div>
            </div>
            <div class="summary-card">
                <h4>Units Restocked to Shelves</h4>
                <div class="val" style="color: #4ade80;">+{{ number_format($returnedUnits) }} units</div>
            </div>
            <div class="summary-card">
                <h4>Total Restituted Value</h4>
                <div class="val" style="color: #f8fafc;">₦{{ number_format($returnedValue, 0) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('returnsTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
            </div>

            <div class="table-wrap">
                <table id="returnsTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Return Ref</th>
                            <th>Sale Invoice #</th>
                            <th>Customer</th>
                            <th>Restocked Items</th>
                            <th>Refund Amount</th>
                            <th>Reason for Return</th>
                            <th>Processed By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesReturns as $ret)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($ret->createdAt)) }}
                            </td>
                            <td><strong style="color: #fbbf24;">{{ $ret->code }}</strong></td>
                            <td><strong style="color: #93c5fd;">#{{ substr($ret->saleId, 0, 8) }}</strong></td>
                            <td><strong>{{ $ret->customerName ?: 'Walk-in Customer' }}</strong></td>
                            <td><span class="badge badge-info">{{ $ret->productName }} ({{ $ret->quantity }} units)</span></td>
                            <td style="font-weight: 800; font-size: 1rem; color: #4ade80;">
                                ₦{{ number_format($ret->refundAmount, 0) }}
                            </td>
                            <td style="color: #cbd5e1;">{{ $ret->reason }}</td>
                            <td>{{ $ret->userName }}</td>
                            <td>
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="printGenericVoucher('SALES RETURN & RESTOCK SLIP', '{{ $ret->code }}', '{{ date('d M Y, h:i A', strtotime($ret->createdAt)) }}', 'Customer', '{{ addslashes($ret->customerName ?: 'Walk-in') }}', 'RESTOCK & REFUND', '#f59e0b', [{name: '{{ addslashes($ret->productName) }}', qty: '{{ $ret->quantity }} units', note: 'Restocked to shelf. Ref Original Sale #{{ substr($ret->saleId, 0, 8) }}'}], 'Refund Total: ₦{{ number_format($ret->refundAmount, 0) }}', '{{ addslashes($ret->userName) }}', 'Reason: {{ addslashes($ret->reason) }}')">
                                        📄 Print Slip
                                    </button>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewGenericDetails('Sales Return Record', '{{ $ret->code }}', '{{ date('d M Y, h:i A', strtotime($ret->createdAt)) }}', 'Customer', '{{ addslashes($ret->customerName ?: 'Customer') }}', 'Return & Restock', '#f59e0b', [{label: 'Original Sale Invoice', val: '#{{ substr($ret->saleId, 0, 8) }}'}, {label: 'Product Restocked', val: '{{ addslashes($ret->productName) }}'}, {label: 'Restocked Units', val: '+{{ $ret->quantity }} units', color: '#4ade80'}, {label: 'Refund Amount', val: '₦{{ number_format($ret->refundAmount, 0) }}', color: '#fbbf24'}, {label: 'Reason', val: '{{ addslashes($ret->reason) }}'}, {label: 'Officer', val: '{{ addslashes($ret->userName) }}'}], 'Items verified and physically restored to inventory.')">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No sales return records found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $salesReturns->links() }}
            </div>
        </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- TAB 7: REFUNDS & FINANCIAL REVERSALS HISTORY -->
    <!-- ───────────────────────────────────────────────────────────── -->
    @elseif($activeTab === 'refunds')
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Refunds Processed</h4>
                <div class="val" style="color: #f87171;">{{ number_format($refundsCount) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Financial Refund Sum</h4>
                <div class="val" style="color: #f87171;">₦{{ number_format($totalRefundAmount, 0) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('refundsTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
            </div>

            <div class="table-wrap">
                <table id="refundsTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Return Ref</th>
                            <th>Original Invoice</th>
                            <th>Customer Name</th>
                            <th>Refund Amount</th>
                            <th>Reason / Description</th>
                            <th>Authorized Officer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($refundRecords as $ref)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($ref->createdAt)) }}
                            </td>
                            <td><strong style="color: #fbbf24;">{{ $ref->code }}</strong></td>
                            <td><strong style="color: #93c5fd;">#{{ substr($ref->saleId, 0, 8) }}</strong></td>
                            <td><strong>{{ $ref->customerName ?: 'Customer' }}</strong></td>
                            <td style="font-weight: 800; font-size: 1.05rem; color: #f87171;">
                                ₦{{ number_format($ref->refundAmount, 0) }}
                            </td>
                            <td style="color: #cbd5e1;">{{ $ref->reason }}</td>
                            <td><strong>{{ $ref->userName }}</strong></td>
                            <td>
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="printGenericVoucher('OFFICIAL REFUND VOUCHER', 'REF-{{ substr(md5($ref->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($ref->createdAt)) }}', 'Beneficiary (Customer)', '{{ addslashes($ref->customerName ?: 'Customer') }}', 'CASH REFUND', '#ef4444', [{name: 'Refund Payout for Invoice #{{ substr($ref->saleId, 0, 8) }}', qty: '1 event', note: '{{ addslashes($ref->reason) }}'}], 'Total Refunded: ₦{{ number_format($ref->refundAmount, 0) }}', '{{ addslashes($ref->userName) }}', 'Paid out in full from cash drawer.')">
                                        📄 Print Voucher
                                    </button>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewGenericDetails('Financial Refund Record', 'REF-{{ substr(md5($ref->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($ref->createdAt)) }}', 'Beneficiary', '{{ addslashes($ref->customerName ?: 'Customer') }}', 'Cash Refund', '#ef4444', [{label: 'Original Sale Invoice', val: '#{{ substr($ref->saleId, 0, 8) }}'}, {label: 'Refund Amount', val: '₦{{ number_format($ref->refundAmount, 0) }}', color: '#f87171'}, {label: 'Reason', val: '{{ addslashes($ref->reason) }}'}, {label: 'Authorized Officer', val: '{{ addslashes($ref->userName) }}'}], 'Financial payout logged to cash reconciliation register.')">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No customer refunds recorded matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $refundRecords->links() }}
            </div>
        </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- TAB 8: CUSTOMER DEBTS & REPAYMENT LEDGER HISTORY -->
    <!-- ───────────────────────────────────────────────────────────── -->
    @elseif($activeTab === 'debts')
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Repayments Collected</h4>
                <div class="val" style="color: #4ade80;">₦{{ number_format($totalRepayments, 0) }}</div>
            </div>
            <div class="summary-card">
                <h4>Credit / Debt Incurred</h4>
                <div class="val" style="color: #fbbf24;">₦{{ number_format($totalDebtCreated, 0) }}</div>
            </div>
            <div class="summary-card">
                <h4>Current Total Open Debt</h4>
                <div class="val" style="color: #f87171;">₦{{ number_format($totalOpenDebt, 0) }}</div>
            </div>
            <div class="summary-card">
                <h4>Ledger Entries</h4>
                <div class="val" style="color: #60a5fa;">{{ number_format($debtsEntryCount) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('debtsTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', 'debtors') }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="debtsTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Customer Name</th>
                            <th>Transaction Type</th>
                            <th>Amount</th>
                            <th>Balance Remaining After</th>
                            <th>Payment Method / Ref</th>
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($debtLedgers as $entry)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($entry->created_at)) }}
                            </td>
                            <td>
                                <strong>{{ $entry->customer->name ?? 'Customer' }}</strong>
                                @if($entry->customer && $entry->customer->phone)
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $entry->customer->phone }}</div>
                                @endif
                            </td>
                            <td>
                                @if($entry->type === 'PAYMENT')
                                    <span class="badge badge-success">💵 Part Payment</span>
                                @elseif($entry->type === 'INVOICE')
                                    <span class="badge badge-danger">💳 Debt Incurred</span>
                                @elseif($entry->type === 'RETURN_CREDIT')
                                    <span class="badge badge-info">🔄 Return Offset</span>
                                @else
                                    <span class="badge badge-secondary">{{ $entry->type }}</span>
                                @endif
                            </td>
                            <td style="font-weight: 800; font-size: 1rem; color: {{ $entry->type === 'PAYMENT' ? '#4ade80' : '#f87171' }};">
                                {{ $entry->type === 'PAYMENT' ? '-' : '+' }}₦{{ number_format($entry->amount, 0) }}
                            </td>
                            <td style="font-weight: 700; color: {{ $entry->balance_after > 0 ? '#f87171' : '#4ade80' }};">
                                ₦{{ number_format($entry->balance_after, 0) }}
                            </td>
                            <td><span class="badge badge-info">{{ $entry->payment_method ?: 'N/A' }}</span></td>
                            <td>{{ $entry->recorded_by }}</td>
                            <td>
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="printGenericVoucher('CUSTOMER PAYMENT RECEIPT', 'REC-{{ substr(md5($entry->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($entry->created_at)) }}', 'Customer', '{{ addslashes($entry->customer->name ?? 'Customer') }}', '{{ $entry->type }}', '#22c55e', [{name: 'Payment via {{ $entry->payment_method ?: 'CASH' }} (Ref: {{ $entry->reference_no ?: 'Standard' }})', qty: '1 entry', note: 'New Balance Owed: ₦{{ number_format($entry->balance_after, 0) }}'}], 'Amount Paid: ₦{{ number_format($entry->amount, 0) }}', '{{ addslashes($entry->recorded_by) }}', 'Customer ledger balance updated.')">
                                        📄 Print Receipt
                                    </button>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewGenericDetails('Customer Debtor Statement Entry', 'REC-{{ substr(md5($entry->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($entry->created_at)) }}', 'Customer Name', '{{ addslashes($entry->customer->name ?? 'Customer') }}', '{{ $entry->type }}', '#22c55e', [{label: 'Transaction Type', val: '{{ $entry->type }}'}, {label: 'Amount Paid', val: '₦{{ number_format($entry->amount, 0) }}', color: '{{ $entry->type === 'PAYMENT' ? '#4ade80' : '#f87171' }}'}, {label: 'Balance Remaining After', val: '₦{{ number_format($entry->balance_after, 0) }}', color: '#fbbf24'}, {label: 'Payment Method', val: '{{ $entry->payment_method ?: 'N/A' }}'}, {label: 'Cashier / Officer', val: '{{ addslashes($entry->recorded_by) }}'}, {label: 'Notes', val: '{{ addslashes($entry->notes ?: 'None') }}'}], 'Ledger balance recalculated automatically.')">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No debt ledger records found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $debtLedgers->links() }}
            </div>
        </div>
    @endif

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- MODALS: SALES DETAILS & UNIVERSAL RECORD DETAILS -->
    <!-- ───────────────────────────────────────────────────────────── -->

    <!-- Modal 1: View Sale Details -->
    <div id="modalSaleDetails" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800;" id="dtlInvoiceTitle">Sale Details</h3>
                    <div style="font-size: 0.8rem; color: var(--text-muted);" id="dtlDate"></div>
                </div>
                <button type="button" onclick="closeModal('modalSaleDetails')" style="background: none; border: none; color: #9ca3af; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <div style="background: rgba(11,15,25,0.6); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                    <span style="color: var(--text-muted);">Customer:</span>
                    <strong id="dtlCustomer"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                    <span style="color: var(--text-muted);">Cashier / Sales Officer:</span>
                    <strong id="dtlCashier"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                    <span style="color: var(--text-muted);">Delivery Status:</span>
                    <strong id="dtlDelivery"></strong>
                </div>
            </div>

            <!-- Items Table -->
            <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem; display: block;">Purchased Items:</label>
            <div id="dtlItemsList" style="max-height: 220px; overflow-y: auto; margin-bottom: 1.25rem;">
                <!-- Dynamically populated -->
            </div>

            <!-- Totals -->
            <div style="border-top: 1px solid var(--border); padding-top: 1rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.95rem; margin-bottom: 0.35rem;">
                    <span>Total Amount:</span>
                    <strong id="dtlTotal" style="font-size: 1.15rem; color: #f8fafc;"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.35rem;">
                    <span style="color: #4ade80;">Amount Paid:</span>
                    <strong id="dtlPaid" style="color: #4ade80;"></strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                    <span style="color: #f87171;">Remaining Debt:</span>
                    <strong id="dtlDebt" style="color: #f87171;"></strong>
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalSaleDetails')">Close</button>
                <a id="dtlReceiptBtn" href="#" class="btn btn-success" style="flex: 1;" target="_blank">🖨️ View & Print Receipt</a>
            </div>
        </div>
    </div>

    <!-- Modal 2: Universal Generic Record Details -->
    <div id="modalGenericDetails" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800;" id="genModalTitle">Transaction Details</h3>
                    <div style="font-size: 0.8rem; color: var(--text-muted);" id="genModalDate"></div>
                </div>
                <button type="button" onclick="closeModal('modalGenericDetails')" style="background: none; border: none; color: #9ca3af; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <div style="background: rgba(11,15,25,0.6); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem;" id="genModalInfoBox">
                <!-- Dynamically populated info rows -->
            </div>

            <div id="genModalImpact" style="background: rgba(37,99,235,0.1); border: 1px solid rgba(37,99,235,0.3); border-radius: 10px; padding: 0.85rem; font-size: 0.85rem; color: #93c5fd; margin-bottom: 1.5rem;">
                <!-- Impact text -->
            </div>

            <div style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalGenericDetails')">Close</button>
                <button type="button" class="btn btn-primary" id="genModalPrintBtn" style="flex: 1;">🖨️ Print Voucher</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function filterTableRows(tableId, query) {
    const q = query.toLowerCase().trim();
    const table = document.getElementById(tableId);
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(q) ? '' : 'none';
    });
}

function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function viewSaleDetails(sale) {
    document.getElementById('dtlInvoiceTitle').textContent = 'Sale #' + sale.id.substring(0, 8);
    document.getElementById('dtlDate').textContent = new Date(sale.createdAt).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    document.getElementById('dtlCustomer').textContent = sale.customerName || 'Walk-in Customer';
    document.getElementById('dtlCashier').textContent = sale.userName || 'Cashier';
    const isSupplied = (sale.deliveryStatus === 'DELIVERED' || sale.deliveryStatus === 'SUPPLIED');
    document.getElementById('dtlDelivery').textContent = isSupplied ? '🟢 Supplied & Collected' : '⏳ Goods in Shop (Awaiting Pickup)';
    document.getElementById('dtlReceiptBtn').href = '/pos/receipt/' + sale.id;

    let itemsHtml = '';
    (sale.items || []).forEach(item => {
        const subtotal = item.quantity * item.unitPrice;
        itemsHtml += `
        <div style="background:rgba(15,23,42,0.6);border:1px solid var(--border);border-radius:10px;padding:0.6rem 0.85rem;margin-bottom:0.4rem;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <strong style="font-size:0.9rem;color:#f8fafc;">${item.productName}</strong>
                <div style="font-size:0.75rem;color:#94a3b8;">₦${Math.round(item.unitPrice).toLocaleString('en-US')} × ${item.quantity} units</div>
            </div>
            <div style="text-align:right;">
                <span style="background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.3);padding:0.15rem 0.45rem;border-radius:6px;font-weight:800;font-size:0.75rem;margin-right:0.4rem;">× ${item.quantity}</span>
                <strong style="font-size:0.95rem;color:#4ade80;">₦${Math.round(subtotal).toLocaleString('en-US')}</strong>
            </div>
        </div>
        `;
    });

    document.getElementById('dtlItemsList').innerHTML = itemsHtml;
    document.getElementById('dtlTotal').textContent = '₦' + Math.round(sale.totalAmount).toLocaleString('en-US');
    document.getElementById('dtlPaid').textContent = '₦' + Math.round(sale.paidAmount).toLocaleString('en-US');

    const debt = Math.max(0, sale.totalAmount - sale.paidAmount);
    document.getElementById('dtlDebt').textContent = debt > 0 ? '₦' + Math.round(debt).toLocaleString('en-US') : '₦0 (Fully Settled)';
    document.getElementById('dtlDebt').style.color = debt > 0 ? '#f87171' : '#4ade80';

    openModal('modalSaleDetails');
}

function viewTransferDetails(trf) {
    let items = (trf.items || []).map(i => ({
        label: i.product_name,
        val: i.dispatched_qty + ' units dispatched' + (i.received_qty !== null ? ' (Counted: ' + i.received_qty + ')' : '')
    }));

    viewGenericDetails(
        'Transfer Waybill #' + trf.transfer_no,
        trf.transfer_no,
        new Date(trf.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
        'Driver / Carrier',
        trf.carrier_name,
        trf.status,
        '#3b82f6',
        [
            { label: 'Origin Branch', val: (trf.source ? trf.source.name : 'Origin') },
            { label: 'Destination Branch', val: (trf.destination ? trf.destination.name : 'Destination') },
            { label: 'Dispatched By', val: trf.dispatched_by },
            ...items
        ],
        'Inter-branch stock transfer buffer protects against loss during transit.'
    );

    document.getElementById('genModalPrintBtn').onclick = function() {
        window.open('/stock/transfers/' + trf.id + '/waybill', '_blank');
    };
}

function viewGenericDetails(title, refNo, dateStr, partyLabel, partyVal, badgeText, badgeColor, fields, impactText) {
    document.getElementById('genModalTitle').textContent = title;
    document.getElementById('genModalDate').textContent = 'Ref: #' + refNo + ' · ' + dateStr;

    let html = '';
    fields.forEach(f => {
        html += `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.4rem 0;border-bottom:1px solid rgba(255,255,255,0.06);">
            <span style="font-size:0.85rem;color:var(--text-muted);">${f.label}:</span>
            <strong style="font-size:0.9rem;color:${f.color || '#f8fafc'};">${f.val}</strong>
        </div>
        `;
    });

    document.getElementById('genModalInfoBox').innerHTML = html;
    document.getElementById('genModalImpact').textContent = impactText;

    document.getElementById('genModalPrintBtn').onclick = function() {
        printGenericVoucher(title, refNo, dateStr, partyLabel, partyVal, badgeText, badgeColor, fields.map(f => ({ name: f.label, qty: f.val, note: '' })), '', '', impactText);
    };

    openModal('modalGenericDetails');
}

/**
 * Universal Printable Voucher Generator
 */
function printGenericVoucher(title, refNo, dateStr, partyLabel, partyVal, badgeText, badgeColor, items, totalSummary, staffName, notes) {
    const printWin = window.open('', '_blank', 'width=800,height=900');
    if (!printWin) {
        alert('Please allow popups to print voucher.');
        return;
    }

    let itemsRows = '';
    items.forEach((item, idx) => {
        itemsRows += `
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">${idx + 1}</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>${item.name}</strong><br><small style="color:#666;">${item.note || ''}</small></td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: right; font-weight: bold;">${item.qty}</td>
        </tr>
        `;
    });

    const doc = `
    <!DOCTYPE html>
    <html>
    <head>
        <title>${title} - ${refNo}</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; color: #111; max-width: 700px; margin: auto; }
            .header { text-align: center; border-bottom: 2px solid #111; padding-bottom: 15px; margin-bottom: 20px; }
            .company { font-size: 24px; font-weight: 900; letter-spacing: 1px; }
            .doc-title { font-size: 18px; font-weight: bold; margin-top: 5px; color: #333; text-transform: uppercase; }
            .meta-grid { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
            .meta-box { background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 12px; width: 46%; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
            th { background: #f1f5f9; padding: 10px; text-align: left; border-bottom: 2px solid #cbd5e1; font-size: 12px; text-transform: uppercase; }
            .summary { margin-top: 20px; text-align: right; font-size: 16px; font-weight: bold; }
            .notes { margin-top: 25px; padding: 12px; background: #fffbe8; border-left: 4px solid #f59e0b; font-size: 13px; }
            .signatures { display: flex; justify-content: space-between; margin-top: 50px; padding-top: 20px; }
            .sig-line { width: 40%; border-top: 1px solid #111; text-align: center; font-size: 12px; padding-top: 5px; }
            @media print {
                body { padding: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print()" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">🖨️ Click to Print</button>
        </div>

        <div class="header">
            <div class="company">HYSAM VENTURES</div>
            <div style="font-size: 13px; color: #555;">Official Inventory & Financial Management System</div>
            <div class="doc-title">${title}</div>
        </div>

        <div class="meta-grid">
            <div class="meta-box">
                <div><strong>Voucher Ref:</strong> ${refNo}</div>
                <div><strong>Date & Time:</strong> ${dateStr}</div>
                <div><strong>Transaction Status:</strong> ${badgeText}</div>
            </div>
            <div class="meta-box">
                <div><strong>${partyLabel}:</strong> ${partyVal}</div>
                <div><strong>Authorized Officer:</strong> ${staffName || 'Official Staff'}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>Item Description</th>
                    <th style="text-align: right; width: 140px;">Quantity / Value</th>
                </tr>
            </thead>
            <tbody>
                ${itemsRows}
            </tbody>
        </table>

        ${totalSummary ? `<div class="summary">${totalSummary}</div>` : ''}

        ${notes ? `<div class="notes"><strong>Remarks / Audit Note:</strong> ${notes}</div>` : ''}

        <div class="signatures">
            <div class="sig-line">
                Prepared By (Officer)
            </div>
            <div class="sig-line">
                Verified / Received By
            </div>
        </div>

        <div style="text-align: center; font-size: 11px; color: #888; margin-top: 40px;">
            Hysam Ventures ERP · Generated on ${new Date().toLocaleString()} · Permanent Immutable Audit Record
        </div>

        <script>
            window.onload = function() {
                window.print();
            }
        <\/script>
    </body>
    </html>
    `;

    printWin.document.write(doc);
    printWin.document.close();
}
</script>
@endpush
