@extends('layouts.app')

@section('title', 'Stock Adjustments & Damages')

@push('styles')
<style>
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

    .table-wrap {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: rgba(11, 15, 25, 0.8); padding: 1rem 1.25rem; font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border); }
    td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); font-size: 0.95rem; }
    tr:last-child td { border-bottom: none; }
</style>
@endpush

@section('content')

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 1.75rem;">📉</span>
                <h2 style="font-size: 1.5rem; font-weight: 800;">Damaged & Expired Stock Write-offs</h2>
            </div>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Officially record broken, expired, or lost goods on ground to maintain 100% physical count accuracy.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button class="btn btn-danger" onclick="openModal('modalStockAdjustment')">
                📉 Record Damaged Goods
            </button>
            <a href="{{ route('stock.index') }}" class="btn btn-secondary">
                📦 Stock Hub
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <h4>Adjustment Write-off Events</h4>
            <div class="val" style="color: #fbbf24;">{{ number_format($totalAdjustmentsCount) }}</div>
        </div>
        <div class="summary-card">
            <h4>Total Physical Units Written Off</h4>
            <div class="val" style="color: #f87171;">-{{ number_format($totalUnitsLost) }} units</div>
        </div>
    </div>

    <!-- Multi-Criteria Filter Card -->
    <div class="filter-card">
        <form method="GET" action="{{ route('stock.adjustments') }}">
            <!-- Quick Date Pills -->
            <div style="display: flex; gap: 0.4rem; margin-bottom: 0.85rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Quick Dates:</span>
                <a href="{{ route('stock.adjustments', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'ALL'])) }}" class="date-pill {{ $datePreset === 'ALL' && !request('from_date') ? 'active' : '' }}">All Time</a>
                <a href="{{ route('stock.adjustments', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'TODAY'])) }}" class="date-pill {{ $datePreset === 'TODAY' ? 'active' : '' }}">Today</a>
                <a href="{{ route('stock.adjustments', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'YESTERDAY'])) }}" class="date-pill {{ $datePreset === 'YESTERDAY' ? 'active' : '' }}">Yesterday</a>
                <a href="{{ route('stock.adjustments', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'THIS_WEEK'])) }}" class="date-pill {{ $datePreset === 'THIS_WEEK' ? 'active' : '' }}">This Week</a>
                <a href="{{ route('stock.adjustments', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'THIS_MONTH'])) }}" class="date-pill {{ $datePreset === 'THIS_MONTH' ? 'active' : '' }}">This Month</a>
            </div>

            <div class="grid-4" style="gap: 0.75rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Reason Type</label>
                    <select name="type">
                        <option value="">-- All Types --</option>
                        <option value="DAMAGE" {{ request('type') === 'DAMAGE' ? 'selected' : '' }}>📉 Physical Damage / Breakage</option>
                        <option value="EXPIRED" {{ request('type') === 'EXPIRED' ? 'selected' : '' }}>⏰ Expired Goods</option>
                        <option value="LOST" {{ request('type') === 'LOST' ? 'selected' : '' }}>🔍 Lost / Shrinkage</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Branch Shop</label>
                    <select name="warehouse_id">
                        <option value="">-- All Branches --</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 0.85rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="🔍 Search product name, SKU, reason note, officer...">
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    🔍 Apply Filters
                </button>

                <a href="{{ route('stock.adjustments') }}" class="btn btn-secondary" style="padding: 0.65rem 1rem;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Adjustments Table -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800;">
                Adjustments Audit Log
            </h3>
            <div style="width: 280px;">
                <input type="text" placeholder="⚡ Live search table..." onkeyup="filterTableRows('adjustmentsTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
            </div>
        </div>

        <div class="table-wrap">
            <table id="adjustmentsTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Shop Branch</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th style="color: #f87171;">Qty Deducted</th>
                        <th>Reason / Incident Note</th>
                        <th>Staff Name</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustments as $adj)
                    <tr>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            {{ date('d M Y, h:i A', strtotime($adj->created_at)) }}
                        </td>
                        <td><strong>{{ $adj->warehouse->name ?? 'Shop' }}</strong></td>
                        <td>
                            <strong>{{ $adj->product_name }}</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><span class="badge badge-info">{{ $adj->product_code }}</span></div>
                        </td>
                        <td>
                            @php
                                $badge = match($adj->type) {
                                    'DAMAGE' => 'badge-danger',
                                    'EXPIRED' => 'badge-warning',
                                    default => 'badge-info',
                                };
                            @endphp
                            <span class="badge {{ $badge }}">{{ $adj->type }}</span>
                        </td>
                        <td style="font-weight: 800; color: #f87171; font-size: 1.1rem;">
                            -{{ $adj->quantity }} units
                        </td>
                        <td>{{ $adj->reason }}</td>
                        <td><strong>{{ $adj->recorded_by }}</strong></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No damaged or lost stock adjustments matching your filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1.25rem;">
            {{ $adjustments->links() }}
        </div>
    </div>

    <!-- Modal: Record Stock Adjustment -->
    <div id="modalStockAdjustment" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">📉 Record Stock Damage / Loss</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Deducts unsellable items from the physical closing stock count.
            </p>

            <form id="adjustmentForm" method="POST" action="{{ route('stock.adjustments.record') }}">
                @csrf

                <div class="form-group">
                    <label>Branch Shop Location</label>
                    <select name="warehouse_id" id="adjWarehouse" required>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Product</label>
                    <select name="product_id" id="adjProduct" required>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Adjustment Type</label>
                    <select name="type" id="adjType" required>
                        <option value="DAMAGE">Physical Damage / Broken Goods</option>
                        <option value="EXPIRED">Expired Stock</option>
                        <option value="LOST">Lost / Unaccounted Physical Shrinkage</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity to Deduct (Units)</label>
                    <input type="number" name="quantity" id="adjQty" min="1" placeholder="e.g. 5" required>
                </div>

                <div class="form-group">
                    <label>Reason / Incident Description</label>
                    <input type="text" name="reason" id="adjReason" placeholder="e.g. Broken bag during offloading" required>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalStockAdjustment')">Cancel</button>
                    <button type="button" class="btn btn-danger" style="flex: 1;" onclick="confirmAdjustment()">📉 Confirm Write-off</button>
                </div>
            </form>
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

function confirmAdjustment() {
    const form = document.getElementById('adjustmentForm');
    const prodSelect = document.getElementById('adjProduct');
    const qty = parseInt(document.getElementById('adjQty').value) || 0;
    const reason = document.getElementById('adjReason').value.trim();
    const type = document.getElementById('adjType').value;
    const errors = [];

    if (!prodSelect || !prodSelect.value) {
        errors.push({
            title: 'Product Selection Required',
            desc: 'Please select which product suffered damage or physical shrinkage.',
            focus: 'adjProduct'
        });
    }

    if (qty <= 0) {
        errors.push({
            title: 'Invalid Write-off Quantity',
            desc: 'Please enter at least 1 unit to deduct.',
            focus: 'adjQty'
        });
    }

    if (!reason || reason.length < 4) {
        errors.push({
            title: 'Incident Description Mandatory',
            desc: 'Please enter a detailed explanation of how and why the stock was damaged or lost for audit records.',
            focus: 'adjReason'
        });
    }

    if (errors.length > 0) {
        showActionBlockedModal({
            title: 'Write-off Cannot Be Authorized',
            subtitle: 'Please resolve the following audit requirements:',
            errors: errors
        });
        return;
    }

    const prodName = prodSelect.options[prodSelect.selectedIndex].text;

    closeModal('modalStockAdjustment');

    showConfirmPopup({
        icon: '📉',
        title: 'Confirm Stock Write-off',
        subtitle: 'Authorize deduction of unsellable goods from physical inventory:',
        borderColor: '#ef4444',
        items: [
            { label: 'Product', value: prodName, color: '#f8fafc' },
            { label: 'Units to Write-off', value: '- ' + qty + ' units', color: '#f87171', size: '1.1rem' },
            { label: 'Adjustment Type', value: type, color: '#fbbf24' },
            { label: 'Reason', value: reason, color: '#cbd5e1' }
        ],
        impact: {
            text: '🛡️ AUDIT WRITE-OFF: Reduces physical closing stock by ' + qty + ' units and writes a permanent entry in the Auditor activity ledger.',
            type: 'danger'
        },
        confirmText: '📉 Yes, Authorize Write-off',
        confirmClass: 'btn-danger',
        form: form
    });
}
</script>
@endpush
