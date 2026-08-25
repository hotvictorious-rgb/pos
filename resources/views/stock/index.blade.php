@extends('layouts.app')

@section('title', 'Stock Management Hub')

@push('styles')
<style>
    .stock-action-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .stock-card {
        background: var(--card-bg);
        border: 2px solid var(--border);
        border-radius: 18px;
        padding: 1.5rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 1.25rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .stock-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.3);
    }

    .card-icon-wrap {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
    }

    .filter-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
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

    .table-wrap {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    th {
        background: rgba(15, 23, 42, 0.7);
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
    tr:hover td { background: rgba(51, 65, 85, 0.25); }

    /* Incoming Transfer Alert Box */
    .incoming-alert {
        background: rgba(37, 99, 235, 0.15);
        border: 2px solid rgba(37, 99, 235, 0.4);
        border-radius: 16px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }
</style>
@endpush

@section('content')

    <!-- Top Branch Switcher & Title -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 1.75rem;">📦</span>
                <h2 style="font-size: 1.5rem; font-weight: 800;">Stock & Inventory Management Hub</h2>
            </div>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Managing Physical Counts & Warehouse Stocks for: <strong style="color: #60a5fa;">{{ $activeWarehouse->name }}</strong>
            </p>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="{{ route('stock.transfers') }}" class="btn btn-secondary">
                🚚 Shop Transfers
            </a>
            <a href="{{ route('transactions.index') }}" class="btn btn-secondary">
                📜 Ledgers Hub
            </a>
        </div>
    </div>

    <!-- Incoming Transfer Notification (if any) -->
    @if($incomingTransfers->isNotEmpty())
    <div class="incoming-alert">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="font-size: 2.2rem;">🚚</div>
            <div>
                <strong style="color: #93c5fd; font-size: 1.05rem;">Incoming Stock Transfer from Another Shop!</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.2rem;">
                    {{ $incomingTransfers->count() }} transfer order(s) arriving at {{ $activeWarehouse->name }}. Verify count to prevent loss.
                </p>
            </div>
        </div>
        <button class="btn btn-primary" onclick="openModal('modalReceiveTransfers')">
            📦 Verify & Receive Goods
        </button>
    </div>
    @endif

    @if(auth()->user()?->role !== 'viewer')
    <!-- 3 Big Action Cards (Touch Friendly) -->
    <div class="stock-action-cards">
        <!-- 1. Stock In -->
        <div class="stock-card" style="border-color: rgba(34,197,94,0.4);" onclick="openModal('modalStockIn')">
            <div class="card-icon-wrap" style="background: rgba(34,197,94,0.15); color: #4ade80;">
                📥
            </div>
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #f8fafc;">New Goods Arrived (Stock In)</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Receive stock from supplier into this shop.</p>
            </div>
        </div>

        <!-- 2. Transfer Out -->
        <div class="stock-card" style="border-color: rgba(59,130,246,0.4);" onclick="openModal('modalTransferOut')">
            <div class="card-icon-wrap" style="background: rgba(59,130,246,0.15); color: #60a5fa;">
                🚚
            </div>
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #f8fafc;">Send to Another Shop</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Dispatch items to another branch location.</p>
            </div>
        </div>

        <!-- 3. Goods Sold & Not Supplied (Awaiting Pickup) -->
        <a href="{{ route('stock.unsupplied') }}" class="stock-card" style="border-color: rgba(217,119,6,0.4); text-decoration: none; color: inherit;">
            <div class="card-icon-wrap" style="background: rgba(217,119,6,0.15); color: #fbbf24;">
                ⏳
            </div>
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #f8fafc;">Not Supplied (Pickups)</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted);">Items sold, paid for, but awaiting customer pickup.</p>
            </div>
        </a>
    </div>
    @else
    <!-- View-Only Executive Stock Banner -->
    <div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <strong style="color: #facc15; font-size: 1.05rem;">👑 Executive Inventory Observer</strong>
            <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.2rem; margin-bottom: 0;">
                Monitoring real-time physical counts, supplier deliveries, and multi-branch inventory balances.
            </p>
        </div>
        <a href="{{ route('reports.export.csv', 'inventory') }}" class="btn btn-secondary" style="font-size: 0.82rem; color: #facc15; border-color: rgba(234, 179, 8, 0.4);">
            📥 Export Stock Valuation CSV
        </a>
    </div>
    @endif

    <!-- Stock Summary KPI Grid -->
    <div class="summary-grid">
        <div class="summary-card">
            <h4>Total Tracked SKUs</h4>
            <div class="val" style="color: #60a5fa;">{{ number_format($totalItemsCount) }}</div>
        </div>
        <div class="summary-card">
            <h4>Total Physical Shelf Units</h4>
            <div class="val" style="color: #4ade80;">{{ number_format($totalPhysicalUnits) }} units</div>
        </div>
        <div class="summary-card">
            <h4>Low Stock Alerts (1 - 10)</h4>
            <div class="val" style="color: #fbbf24;">{{ number_format($lowStockCount) }}</div>
        </div>
        <div class="summary-card">
            <h4>Out of Stock Items</h4>
            <div class="val" style="color: #f87171;">{{ number_format($outOfStockCount) }}</div>
        </div>
    </div>

    <!-- Multi-Criteria Filter Bar for Stock -->
    <div class="filter-card">
        <form method="GET" action="{{ route('stock.index') }}" id="stockFilterForm">
            <div class="grid-4" style="gap: 0.75rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Branch / Warehouse</label>
                    <select name="warehouse_id" onchange="this.form.submit()">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ $activeWarehouse->id == $wh->id ? 'selected' : '' }}>
                                🏢 {{ $wh->name }} ({{ $wh->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Stock Health Status</label>
                    <select name="stock_status">
                        <option value="">-- All Stock Statuses --</option>
                        <option value="HEALTHY" {{ request('stock_status') === 'HEALTHY' ? 'selected' : '' }}>🟢 Healthy Stock (> 10)</option>
                        <option value="LOW" {{ request('stock_status') === 'LOW' ? 'selected' : '' }}>⚠️ Low Stock (1 - 10)</option>
                        <option value="OUT" {{ request('stock_status') === 'OUT' ? 'selected' : '' }}>🔴 Out of Stock (0)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Category</label>
                    <select name="category">
                        <option value="">-- All Categories --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Search Item / SKU</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="🔍 Product name or SKU code...">
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 0.85rem; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    🔍 Filter Stock
                </button>
                <a href="{{ route('stock.index', ['warehouse_id' => $activeWarehouse->id]) }}" class="btn btn-secondary" style="padding: 0.65rem 1rem;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Stock Table Card with Live Filter Box -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <h3 style="font-size: 1.2rem; font-weight: 800;">
                Physical Stock on Ground ({{ $activeWarehouse->name }})
            </h3>
            <div style="width: 280px;">
                <input type="text" placeholder="⚡ Live search table..." onkeyup="filterTableRows('stockTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
            </div>
        </div>

        <div class="table-wrap">
            <table id="stockTable">
                <thead>
                    <tr>
                        <th>Product SKU</th>
                        <th>Category</th>
                        <th>Unit Price</th>
                        <th style="color: #4ade80;">Physical Count (Units on Ground)</th>
                        <th>Min Alert Level</th>
                        <th>Stock Health</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockLevels as $level)
                    <tr>
                        <td>
                            <strong style="color: #60a5fa; font-size: 1.05rem; letter-spacing: 0.03em;">{{ $level->product->code ?? 'N/A' }}</strong>
                        </td>
                        <td>{{ $level->product->category ?? 'General' }}</td>
                        <td style="font-weight: 700;">₦{{ number_format($level->product->unitPrice ?? 0, 0) }}</td>
                        <td>
                            <span style="font-size: 1.15rem; font-weight: 800; color: #4ade80;">
                                {{ number_format($level->physical_stock) }}
                            </span> units
                        </td>
                        <td style="color: var(--text-muted);">
                            {{ $level->min_stock_alert ?? 5 }} units
                        </td>
                        <td>
                            @if($level->physical_stock <= 0)
                                <span class="badge badge-danger">🔴 Out of Stock</span>
                            @elseif($level->physical_stock <= ($level->min_stock_alert ?? 5))
                                <span class="badge badge-warning">⚠️ Low Stock ({{ $level->physical_stock }})</span>
                            @else
                                <span class="badge badge-success">✓ Sufficient ({{ $level->physical_stock }})</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            No stock records found matching filters. Tap <strong>📥 New Goods Arrived</strong> to add inventory!
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal 1: Stock In (New Goods Arrived) -->
    <div id="modalStockIn" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">📥 Record New Goods Arrived</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Add supplier delivery directly to <strong>{{ $activeWarehouse->name }}</strong> physical count.
            </p>

            <form id="stockInForm" method="POST" action="{{ route('stock.in') }}">
                @csrf
                <input type="hidden" name="warehouse_id" value="{{ $activeWarehouse->id }}">

                <div class="form-group">
                    <label>Select Product SKU</label>
                    <select name="product_id" id="stockInProduct" required>
                        @foreach($allProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->code }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity Received (Units)</label>
                    <input type="number" name="quantity" id="stockInQty" min="1" placeholder="e.g. 50" required>
                </div>

                <div class="form-group">
                    <label>Supplier Name / Invoice Ref</label>
                    <input type="text" name="supplier_name" id="stockInSupplier" placeholder="e.g. Dangote Dist., Waybill #9821">
                </div>

                <div class="form-group">
                    <label>Notes / Remarks</label>
                    <input type="text" name="notes" id="stockInNotes" placeholder="Optional notes">
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalStockIn')">Cancel</button>
                    <button type="button" class="btn btn-success" style="flex: 1;" onclick="confirmStockIn()">✓ Save & Increase Count</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2: Transfer Out (Send to Another Branch) -->
    <div id="modalTransferOut" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">🚚 Send Goods to Another Shop</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Dispatches items from <strong>{{ $activeWarehouse->name }}</strong>. Destination shop will verify count on arrival.
            </p>

            <form id="transferOutForm" method="POST" action="{{ route('stock.transfer.out') }}">
                @csrf
                <input type="hidden" name="source_warehouse_id" value="{{ $activeWarehouse->id }}">

                <div class="form-group">
                    <label>Destination Shop</label>
                    <select name="destination_warehouse_id" id="transferDestWh" required>
                        @foreach($warehouses as $wh)
                            @if($wh->id != $activeWarehouse->id)
                                <option value="{{ $wh->id }}">🏢 {{ $wh->name }} ({{ $wh->code }})</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Product SKU</label>
                    <select name="items[0][productId]" id="transferProduct" required>
                        @foreach($allProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->code }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity to Send</label>
                    <input type="number" name="items[0][quantity]" id="transferQty" min="1" placeholder="e.g. 10" required>
                </div>

                <div class="form-group">
                    <label>Driver / Carrier Name</label>
                    <input type="text" name="carrier_name" id="transferCarrier" placeholder="e.g. Musa Delivery Van, Plate #KJA-123" required>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalTransferOut')">Cancel</button>
                    <button type="button" class="btn btn-primary" style="flex: 1;" onclick="confirmTransferOut()">🚚 Dispatch Transfer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 3: Verify & Receive Incoming Transfers -->
    <div id="modalReceiveTransfers" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 650px;">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">📦 Verify & Receive Incoming Transfers</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Count physical items delivered. If any item is missing, enter the actual count to raise an Auditor Discrepancy Alert!
            </p>

            @foreach($incomingTransfers as $trf)
            <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                    <div>
                        <strong>Ref: {{ $trf->transfer_no }}</strong>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">From: {{ $trf->source->name ?? 'Shop' }} · Driver: {{ $trf->carrier_name }}</div>
                    </div>
                    <span class="badge badge-info">In-Transit</span>
                </div>

                <form id="recvTrfForm_{{ $trf->id }}" method="POST" action="{{ route('stock.transfer.in', $trf->id) }}">
                    @csrf
                    @foreach($trf->items as $tItem)
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.75rem;">
                        <div>
                            <strong style="color: #60a5fa;">{{ $tItem->product_code ?? $tItem->product_name }}</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Dispatched: {{ $tItem->dispatched_qty }} units</div>
                        </div>
                        <div style="max-width: 140px;">
                            <label style="font-size: 0.7rem;">Counted Qty:</label>
                            <input type="number" name="counted_items[{{ $tItem->product_id }}]" value="{{ $tItem->dispatched_qty }}" min="0" required>
                        </div>
                    </div>
                    @endforeach

                    <div class="form-group">
                        <label>Discrepancy Notes (if missing items)</label>
                        <input type="text" name="discrepancy_notes" placeholder="Explain missing or damaged units">
                    </div>

                    <button type="button" class="btn btn-success btn-block" style="margin-top: 0.75rem;" onclick="confirmReceiveTransfer('{{ $trf->id }}', '{{ $trf->transfer_no }}', '{{ addslashes($trf->source->name ?? 'Shop') }}')">
                        ✓ Confirm Count & Receive into Stock
                    </button>
                </form>
            </div>
            @endforeach

            <button type="button" class="btn btn-secondary btn-block" onclick="closeModal('modalReceiveTransfers')">Close</button>
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

function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function confirmStockIn() {
    const form = document.getElementById('stockInForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const prodSelect = document.getElementById('stockInProduct');
    const prodName = prodSelect.options[prodSelect.selectedIndex].text;
    const qty = document.getElementById('stockInQty').value;
    const supplier = document.getElementById('stockInSupplier').value || 'Unspecified Supplier';

    closeModal('modalStockIn');

    showConfirmPopup({
        icon: '📥',
        title: 'Confirm Goods Arrival (Stock In)',
        subtitle: 'Review incoming inventory before updating physical stock:',
        borderColor: '#22c55e',
        items: [
            { label: 'Product', value: prodName, color: '#f8fafc' },
            { label: 'Quantity Arrived', value: '+ ' + qty + ' units', color: '#4ade80', size: '1.05rem' },
            { label: 'Location', value: '{{ addslashes($activeWarehouse->name) }}', color: '#60a5fa' },
            { label: 'Supplier / Ref', value: supplier, color: '#cbd5e1' }
        ],
        impact: {
            text: '🟢 PHYSICAL COUNT INCREMENT: Shelf stock in {{ addslashes($activeWarehouse->name) }} will increase by ' + qty + ' units with permanent audit logging.',
            type: 'success'
        },
        confirmText: '📥 Yes, Record Stock In',
        confirmClass: 'btn-success',
        form: form
    });
}

function confirmTransferOut() {
    const form = document.getElementById('transferOutForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const destSelect = document.getElementById('transferDestWh');
    const destName = destSelect.options[destSelect.selectedIndex].text;
    const prodSelect = document.getElementById('transferProduct');
    const prodName = prodSelect.options[prodSelect.selectedIndex].text;
    const qty = document.getElementById('transferQty').value;
    const carrier = document.getElementById('transferCarrier').value;

    closeModal('modalTransferOut');

    showConfirmPopup({
        icon: '🚚',
        title: 'Confirm Transfer Dispatch',
        subtitle: 'Review goods leaving origin shop:',
        borderColor: '#3b82f6',
        items: [
            { label: 'Origin Branch', value: '{{ addslashes($activeWarehouse->name) }}', color: '#f87171' },
            { label: 'Destination', value: destName, color: '#60a5fa' },
            { label: 'Product & Qty', value: qty + ' units (' + prodName + ')', color: '#fbbf24', size: '0.95rem' },
            { label: 'Driver / Carrier', value: carrier, color: '#f8fafc' }
        ],
        impact: {
            text: '🚚 IN-TRANSIT BUFFER: Deducts ' + qty + ' units from {{ addslashes($activeWarehouse->name) }} and moves them to in-transit holding until verified by destination.',
            type: 'info'
        },
        confirmText: '🚚 Yes, Dispatch Transfer',
        confirmClass: 'btn-primary',
        form: form
    });
}

function confirmReceiveTransfer(trfId, trfNo, sourceName) {
    const form = document.getElementById('recvTrfForm_' + trfId);
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    closeModal('modalReceiveTransfers');

    showConfirmPopup({
        icon: '📦',
        title: 'Confirm Transfer Physical Receipt',
        subtitle: 'Review counted stock to be added to shelf balance:',
        borderColor: '#22c55e',
        items: [
            { label: 'Transfer Waybill', value: '#' + trfNo, color: '#93c5fd' },
            { label: 'Dispatched From', value: sourceName, color: '#cbd5e1' },
            { label: 'Receiving Branch', value: '{{ addslashes($activeWarehouse->name) }}', color: '#4ade80' }
        ],
        impact: {
            text: '📦 PHYSICAL STOCK ADDITION: Verified counted units will be added to this shop. Any shortage from dispatched count will be flagged on the Auditor Hub.',
            type: 'success'
        },
        confirmText: '✓ Yes, Receive into Stock',
        confirmClass: 'btn-success',
        form: form
    });
}
</script>
@endpush
