@extends('layouts.app')

@section('title', 'Stock Management')

@push('styles')
<style>
    .stock-action-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2rem;
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

    /* Table */
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
            <h2 style="font-size: 1.5rem; font-weight: 800;">Stock & Multi-Location Hub 📦</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Managing Physical Counts for: <strong style="color: #60a5fa;">{{ $activeWarehouse->name }}</strong>
            </p>
        </div>

        <form method="GET" action="{{ route('stock.index') }}" style="display: flex; align-items: center; gap: 0.5rem;">
            <label style="margin: 0; white-space: nowrap;">Change Shop:</label>
            <select name="warehouse_id" onchange="this.form.submit()" style="width: auto; padding: 0.6rem 1rem;">
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ $activeWarehouse->id == $wh->id ? 'selected' : '' }}>
                        🏢 {{ $wh->name }} ({{ $wh->code }})
                    </option>
                @endforeach
            </select>
        </form>
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

    <!-- 3 Big Action Cards (Touch Friendly) -->
    <div class="stock-action-cards">
        <!-- 1. Stock In -->
        <div class="stock-card" style="border-color: rgba(34,197,94,0.4);" onclick="openModal('modalStockIn')">
            <div class="card-icon-wrap" style="background: rgba(34,197,94,0.15); color: #4ade80;">
                📥
            </div>
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #f8fafc;">New Goods Arrived</h3>
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

        <!-- 3. Unsupplied Goods Waiting in Shop -->
        <a href="{{ route('stock.unsupplied') }}" class="stock-card" style="border-color: rgba(217,119,6,0.4); text-decoration: none; color: inherit;">
            <div class="card-icon-wrap" style="background: rgba(217,119,6,0.15); color: #fbbf24;">
                ⏳
            </div>
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #f8fafc;">Pickup & Dispatch</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted);">{{ $unsuppliedCount }} sold orders waiting for customer handover.</p>
            </div>
        </a>
    </div>

    <!-- Stock Table -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.2rem; font-weight: 800;">
                Physical Stock on Ground ({{ $activeWarehouse->name }})
            </h3>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product Details</th>
                        <th>Category</th>
                        <th>Unit Price</th>
                        <th style="color: #4ade80;">Physical Count (On Ground)</th>
                        <th style="color: #fbbf24;">Sold (Awaiting Pickup)</th>
                        <th style="color: #60a5fa;">Available to Sell</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockLevels as $level)
                    <tr>
                        <td>
                            <strong>{{ $level->product->name ?? 'Unknown Product' }}</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Code: {{ $level->product->code ?? 'N/A' }}</div>
                        </td>
                        <td>{{ $level->product->category ?? 'General' }}</td>
                        <td style="font-weight: 700;">₦{{ number_format($level->product->unitPrice ?? 0, 2) }}</td>
                        <td>
                            <span style="font-size: 1.1rem; font-weight: 800; color: #4ade80;">
                                {{ $level->physical_stock }}
                            </span> units
                        </td>
                        <td>
                            <span style="font-size: 1.1rem; font-weight: 800; color: #fbbf24;">
                                {{ $level->allocated_stock }}
                            </span> units
                        </td>
                        <td>
                            <span style="font-size: 1.1rem; font-weight: 800; color: #60a5fa;">
                                {{ $level->available_stock }}
                            </span> units
                        </td>
                        <td>
                            @if($level->available_stock <= ($level->min_stock_alert ?? 5))
                                <span class="badge badge-danger">⚠️ Low Stock</span>
                            @else
                                <span class="badge badge-success">✓ Sufficient</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            No stock records found for this branch yet. Tap <strong>📥 New Goods Arrived</strong> to add inventory!
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

            <form method="POST" action="{{ route('stock.in') }}">
                @csrf
                <input type="hidden" name="warehouse_id" value="{{ $activeWarehouse->id }}">

                <div class="form-group">
                    <label>Select Product</label>
                    <select name="product_id" required>
                        @foreach($allProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity Received (Units)</label>
                    <input type="number" name="quantity" min="1" placeholder="e.g. 50" required>
                </div>

                <div class="form-group">
                    <label>Supplier Name / Invoice Ref</label>
                    <input type="text" name="supplier_name" placeholder="e.g. Dangote Dist., Waybill #9821">
                </div>

                <div class="form-group">
                    <label>Notes / Remarks</label>
                    <input type="text" name="notes" placeholder="Optional notes">
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalStockIn')">Cancel</button>
                    <button type="submit" class="btn btn-success" style="flex: 1;">✓ Save & Increase Count</button>
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

            <form method="POST" action="{{ route('stock.transfer.out') }}">
                @csrf
                <input type="hidden" name="source_warehouse_id" value="{{ $activeWarehouse->id }}">

                <div class="form-group">
                    <label>Destination Shop</label>
                    <select name="destination_warehouse_id" required>
                        @foreach($warehouses as $wh)
                            @if($wh->id != $activeWarehouse->id)
                                <option value="{{ $wh->id }}">🏢 {{ $wh->name }} ({{ $wh->code }})</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Product</label>
                    <select name="items[0][productId]" required>
                        @foreach($allProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity to Send</label>
                    <input type="number" name="items[0][quantity]" min="1" placeholder="e.g. 10" required>
                </div>

                <div class="form-group">
                    <label>Driver / Carrier Name</label>
                    <input type="text" name="carrier_name" placeholder="e.g. Musa Delivery Van, Plate #KJA-123" required>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalTransferOut')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">🚚 Dispatch Transfer</button>
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

                <form method="POST" action="{{ route('stock.transfer.in', $trf->id) }}">
                    @csrf
                    @foreach($trf->items as $tItem)
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.75rem;">
                        <div>
                            <strong>{{ $tItem->product_name }}</strong>
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

                    <button type="submit" class="btn btn-success btn-block" style="margin-top: 0.75rem;">
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
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
</script>
@endpush
