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
        cursor: pointer;
        font-family: inherit;
        outline: none;
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
        font-family: inherit;
        outline: none;
        transition: all 0.15s;
    }
    .date-pill.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    .tab-filter-section {
        display: contents;
    }

    .ledger-tab-pane {
        animation: fadeInPane 0.15s ease-in-out;
    }

    @keyframes fadeInPane {
        from { opacity: 0; transform: translateY(2px); }
        to { opacity: 1; transform: translateY(0); }
    }

    #ledgerPanesContainer {
        position: relative;
        transition: opacity 0.15s ease;
    }

    #ledgerPanesContainer.is-loading {
        opacity: 0.45;
        pointer-events: none;
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

    <!-- 8 Independent Tabs Navigation Bar (Instant Client-Side Zero-Reload) -->
    <div class="tab-nav-container">
        <!-- 1. Sales -->
        <button type="button" 
           onclick="switchLedgerTab('sales')" 
           id="tab-btn-sales"
           class="tab-btn {{ $activeTab === 'sales' ? 'active' : '' }}">
            <span>💰 Sales Invoices</span>
            <span class="badge-pill" id="badge-sales">{{ number_format($totalSalesCount) }}</span>
        </button>

        <!-- 2. Stock In -->
        <button type="button" 
           onclick="switchLedgerTab('stock_in')" 
           id="tab-btn-stock_in"
           class="tab-btn {{ $activeTab === 'stock_in' ? 'active' : '' }}">
            <span>📥 Stock In</span>
            <span class="badge-pill" id="badge-stock_in">{{ number_format($stockInBatches) }}</span>
        </button>

        <!-- 3. Stock Out -->
        <button type="button" 
           onclick="switchLedgerTab('stock_out')" 
           id="tab-btn-stock_out"
           class="tab-btn {{ $activeTab === 'stock_out' ? 'active' : '' }}">
            <span>📤 Stock Out & Dispatches</span>
            <span class="badge-pill" id="badge-stock_out">{{ number_format($stockOutCount) }}</span>
        </button>

        <!-- 4. In Transit -->
        <button type="button" 
           onclick="switchLedgerTab('in_transit')" 
           id="tab-btn-in_transit"
           class="tab-btn {{ $activeTab === 'in_transit' ? 'active' : '' }}">
            <span>🚚 In-Transit Buffer</span>
            <span class="badge-pill" id="badge-in_transit">{{ number_format($inTransitCount) }}</span>
        </button>

        <!-- 5. Incoming Transfers -->
        <button type="button" 
           onclick="switchLedgerTab('transfers_in')" 
           id="tab-btn-transfers_in"
           class="tab-btn {{ $activeTab === 'transfers_in' ? 'active' : '' }}">
            <span>🏢 Incoming Transfers</span>
            <span class="badge-pill" id="badge-transfers_in">{{ number_format($incomingTotal) }}</span>
        </button>

        <!-- 6. Returns -->
        <button type="button" 
           onclick="switchLedgerTab('returns')" 
           id="tab-btn-returns"
           class="tab-btn {{ $activeTab === 'returns' ? 'active' : '' }}">
            <span>🔄 Returns</span>
            <span class="badge-pill" id="badge-returns">{{ number_format($returnsCount) }}</span>
        </button>

        <!-- 7. Refunds -->
        <button type="button" 
           onclick="switchLedgerTab('refunds')" 
           id="tab-btn-refunds"
           class="tab-btn {{ $activeTab === 'refunds' ? 'active' : '' }}">
            <span>💸 Customer Refunds</span>
            <span class="badge-pill" id="badge-refunds">{{ number_format($refundsCount) }}</span>
        </button>

        <!-- 8. Debts -->
        <button type="button" 
           onclick="switchLedgerTab('debts')" 
           id="tab-btn-debts"
           class="tab-btn {{ $activeTab === 'debts' ? 'active' : '' }}">
            <span>💳 Debts Ledger</span>
            <span class="badge-pill" id="badge-debts">{{ number_format($debtsEntryCount) }}</span>
        </button>
    </div>

    <!-- Multi-Criteria Filter Bar (Adaptive per active tab) -->
    <div class="filter-card">
        <form method="GET" action="{{ route('transactions.index') }}" id="filterForm" onsubmit="handleFilterFormSubmit(event)">
            <input type="hidden" name="tab" id="activeTabInput" value="{{ $activeTab }}">
            <input type="hidden" name="date_preset" id="datePresetInput" value="{{ $datePreset }}">

            <!-- Quick Date Pills (Zero-Reload AJAX) -->
            <div style="display: flex; gap: 0.4rem; margin-bottom: 0.85rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Quick Dates:</span>
                <button type="button" onclick="setQuickDate('ALL', this)" class="date-pill {{ $datePreset === 'ALL' && !request('from_date') ? 'active' : '' }}">All Time</button>
                <button type="button" onclick="setQuickDate('TODAY', this)" class="date-pill {{ $datePreset === 'TODAY' ? 'active' : '' }}">Today</button>
                <button type="button" onclick="setQuickDate('YESTERDAY', this)" class="date-pill {{ $datePreset === 'YESTERDAY' ? 'active' : '' }}">Yesterday</button>
                <button type="button" onclick="setQuickDate('THIS_WEEK', this)" class="date-pill {{ $datePreset === 'THIS_WEEK' ? 'active' : '' }}">This Week</button>
                <button type="button" onclick="setQuickDate('THIS_MONTH', this)" class="date-pill {{ $datePreset === 'THIS_MONTH' ? 'active' : '' }}">This Month</button>
            </div>

            <!-- Filter Inputs Grid -->
            <div class="grid-4" style="gap: 0.75rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">From Date</label>
                    <input type="date" name="from_date" id="inputFromDate" value="{{ request('from_date') }}" onchange="handleCustomDateChange()">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">To Date</label>
                    <input type="date" name="to_date" id="inputToDate" value="{{ request('to_date') }}" onchange="handleCustomDateChange()">
                </div>

                @if($warehouses->count() > 1 && (!Auth::user() || !Auth::user()->isBranchScoped()))
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Branch / Warehouse</label>
                    <select name="warehouse_id">
                        <option value="ALL">-- All Branches (Consolidated) --</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ (string)$warehouseId === (string)$wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <!-- TAB 1: SALES FILTERS -->
                <div id="tab-filters-sales" class="tab-filter-section" style="{{ $activeTab === 'sales' ? 'display: contents;' : 'display: none;' }}">
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
                </div>

                <!-- TAB 2: STOCK IN FILTERS -->
                <div id="tab-filters-stock_in" class="tab-filter-section" style="{{ $activeTab === 'stock_in' ? 'display: contents;' : 'display: none;' }}">
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
                </div>

                <!-- TAB 3: STOCK OUT FILTERS -->
                <div id="tab-filters-stock_out" class="tab-filter-section" style="{{ $activeTab === 'stock_out' ? 'display: contents;' : 'display: none;' }}">
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
                </div>

                <!-- TAB 4: IN TRANSIT FILTERS -->
                <div id="tab-filters-in_transit" class="tab-filter-section" style="{{ $activeTab === 'in_transit' ? 'display: contents;' : 'display: none;' }}">
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
                </div>

                <!-- TAB 5: INCOMING TRANSFERS FILTERS -->
                <div id="tab-filters-transfers_in" class="tab-filter-section" style="{{ $activeTab === 'transfers_in' ? 'display: contents;' : 'display: none;' }}">
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
                </div>

                <!-- TAB 6: RETURNS FILTERS -->
                <div id="tab-filters-returns" class="tab-filter-section" style="{{ $activeTab === 'returns' ? 'display: contents;' : 'display: none;' }}">
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
                </div>

                <!-- TAB 7: REFUNDS FILTERS -->
                <div id="tab-filters-refunds" class="tab-filter-section" style="{{ $activeTab === 'refunds' ? 'display: contents;' : 'display: none;' }}">
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
                </div>

                <!-- TAB 8: DEBTS FILTERS -->
                <div id="tab-filters-debts" class="tab-filter-section" style="{{ $activeTab === 'debts' ? 'display: contents;' : 'display: none;' }}">
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
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bottom Action Row -->
            <div style="display: flex; gap: 0.75rem; margin-top: 0.85rem; flex-wrap: wrap; align-items: center;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" name="search" id="inputMainSearch" value="{{ request('search') }}" placeholder="🔍 Search references, customer names, SKUs, drivers across database...">
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem; font-weight: 700;">
                    🔍 Apply Filters
                </button>

                <button type="button" onclick="resetLedgerFilters()" class="btn btn-secondary" style="padding: 0.65rem 1rem;">
                    Reset
                </button>

                <div style="display: flex; gap: 0.5rem; margin-left: auto; flex-wrap: wrap;">
                    <a id="btnExportAllCsv" href="{{ route('transactions.export.csv', array_merge(request()->all(), ['tab' => 'all'])) }}" class="btn btn-primary" style="padding: 0.65rem 1.15rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border: 1px solid #4338ca; box-shadow: 0 4px 12px rgba(79,70,229,0.35); text-decoration: none;" title="Export all 8 tabs consolidated into one Master CSV with current filters">
                        <span>📦</span> Export All Tabs (Master CSV)
                    </a>
                    <a id="btnExportCsv" href="{{ route('transactions.export.csv', array_merge(request()->all(), ['tab' => $activeTab])) }}" class="btn btn-success" style="padding: 0.65rem 1.15rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem; box-shadow: 0 4px 12px rgba(22,163,74,0.35);">
                        <span>📥</span> Export Filtered CSV
                    </a>
                    <a id="btnExportJson" href="{{ route('transactions.export.json', array_merge(request()->all(), ['tab' => $activeTab])) }}" class="btn btn-secondary" style="padding: 0.65rem 1.15rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                        <span>📄</span> Export JSON
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- ALL 8 TAB PANES CONTAINER (Pre-rendered for Instant Switching) -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div id="ledgerPanesContainer">
        @include('transactions.partials.panes')
    </div>

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
let currentAbortController = null;

/**
 * Instant Client-Side Zero-Reload Tab Switching (0ms)
 */
function switchLedgerTab(tabId) {
    // 1. Update Tab Navigation Buttons
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById('tab-btn-' + tabId);
    if (activeBtn) activeBtn.classList.add('active');

    // 2. Switch Tab Panes in DOM
    document.querySelectorAll('.ledger-tab-pane').forEach(p => p.style.display = 'none');
    const activePane = document.getElementById('pane-' + tabId);
    if (activePane) activePane.style.display = 'block';

    // 3. Switch Tab-Specific Filter Dropdowns
    document.querySelectorAll('.tab-filter-section').forEach(f => f.style.display = 'none');
    const activeFilter = document.getElementById('tab-filters-' + tabId);
    if (activeFilter) activeFilter.style.display = 'contents';

    // 4. Update hidden input for active tab
    const tabInput = document.getElementById('activeTabInput');
    if (tabInput) tabInput.value = tabId;

    // 5. Update browser address bar seamlessly without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({ tab: tabId }, '', url);

    // 6. Update Export CSV and JSON URLs
    updateExportUrls(tabId);
}

/**
 * Handle Quick Date Preset Selection with Smooth Background Fetch
 */
function setQuickDate(preset, btn) {
    document.querySelectorAll('.date-pill').forEach(p => p.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const fromInput = document.getElementById('inputFromDate');
    const toInput = document.getElementById('inputToDate');
    if (fromInput) fromInput.value = '';
    if (toInput) toInput.value = '';

    const presetInput = document.getElementById('datePresetInput');
    if (presetInput) presetInput.value = preset;

    fetchLedgerData();
}

/**
 * When operator manually edits custom date inputs, reset date pills
 */
function handleCustomDateChange() {
    document.querySelectorAll('.date-pill').forEach(p => p.classList.remove('active'));
    const presetInput = document.getElementById('datePresetInput');
    if (presetInput) presetInput.value = '';
}

/**
 * Intercept standard form submission for smooth AJAX filtering
 */
function handleFilterFormSubmit(e) {
    e.preventDefault();
    fetchLedgerData();
}

/**
 * Reset filters without losing the current tab
 */
function resetLedgerFilters() {
    const activeTab = document.getElementById('activeTabInput').value || 'sales';
    const form = document.getElementById('filterForm');
    form.reset();

    document.getElementById('activeTabInput').value = activeTab;
    document.getElementById('datePresetInput').value = 'ALL';

    document.querySelectorAll('.date-pill').forEach(p => p.classList.remove('active'));
    const allPill = document.querySelector('.date-pill');
    if (allPill) allPill.classList.add('active');

    fetchLedgerData();
}

/**
 * Asynchronous Background Fetch Engine (Zero Reload & Race Condition Safe)
 */
function fetchLedgerData(customParams = null) {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();

    for (const [key, value] of formData.entries()) {
        if (value !== '' && value !== null) {
            params.set(key, value);
        }
    }

    if (customParams) {
        for (const [key, value] of Object.entries(customParams)) {
            if (value !== '' && value !== null) {
                params.set(key, value);
            } else {
                params.delete(key);
            }
        }
    }

    const currentTab = document.getElementById('activeTabInput').value || 'sales';
    params.set('tab', currentTab);

    // Abort pending in-flight request if user rapidly clicks
    if (currentAbortController) {
        currentAbortController.abort();
    }
    currentAbortController = new AbortController();

    const targetUrl = '{{ route("transactions.index") }}?' + params.toString();
    const panesContainer = document.getElementById('ledgerPanesContainer');

    if (panesContainer) {
        panesContainer.classList.add('is-loading');
    }

    fetch(targetUrl, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-Partial-Update': 'true'
        },
        signal: currentAbortController.signal
    })
    .then(response => {
        if (!response.ok) throw new Error('Network error: ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // 1. Update tab count badge pills
            if (data.counts) {
                for (const [tKey, tCount] of Object.entries(data.counts)) {
                    const badge = document.getElementById('badge-' + tKey);
                    if (badge) badge.textContent = tCount;
                }
            }

            // 2. Replace tab panes HTML container
            if (data.panes_html && panesContainer) {
                panesContainer.innerHTML = data.panes_html;
            }

            // 3. Keep current active tab visible
            switchLedgerTab(currentTab);

            // 4. Update address bar URL seamlessly
            window.history.replaceState({ tab: currentTab }, '', targetUrl);

            // 5. Synchronize Export CSV and JSON buttons
            updateExportUrls(currentTab);
        }
    })
    .catch(err => {
        if (err.name === 'AbortError') return; // Cancelled intentionally
        console.error('Asynchronous filter error:', err);
        // Graceful fallback to standard form submit
        form.submit();
    })
    .finally(() => {
        if (panesContainer) {
            panesContainer.classList.remove('is-loading');
        }
    });
}

/**
 * Keep CSV and JSON Export URLs synchronized with active tab and filters
 */
function updateExportUrls(tabId) {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();

    for (const [key, value] of formData.entries()) {
        if (value !== '' && value !== null) {
            params.set(key, value);
        }
    }
    params.set('tab', tabId);

    const btnAllCsv = document.getElementById('btnExportAllCsv');
    const btnCsv = document.getElementById('btnExportCsv');
    const btnJson = document.getElementById('btnExportJson');

    if (btnAllCsv) {
        const allParams = new URLSearchParams(params);
        allParams.set('tab', 'all');
        btnAllCsv.href = '/transactions/export-csv/all?' + allParams.toString();
    }
    if (btnCsv) {
        btnCsv.href = '/transactions/export-csv/' + encodeURIComponent(tabId) + '?' + params.toString();
    }
    if (btnJson) {
        btnJson.href = '/transactions/export-json/' + encodeURIComponent(tabId) + '?' + params.toString();
    }
}

/**
 * Handle in-page table pagination clicks asynchronously
 */
document.addEventListener('click', function(e) {
    const pageLink = e.target.closest('.pagination a');
    if (pageLink && pageLink.href && pageLink.closest('#ledgerPanesContainer')) {
        e.preventDefault();
        const linkUrl = new URL(pageLink.href);
        const customParams = {};
        for (const [key, val] of linkUrl.searchParams.entries()) {
            customParams[key] = val;
        }
        fetchLedgerData(customParams);
    }
});

/**
 * Support browser Back / Forward history without full reload
 */
window.addEventListener('popstate', function(e) {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab') || 'sales';
    switchLedgerTab(tab);
});

/**
 * In-Page Client-Side Row Quick Filtering
 */
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

/**
 * Modal Handling
 */
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
