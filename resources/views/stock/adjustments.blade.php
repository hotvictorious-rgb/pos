@extends('layouts.app')

@section('title', 'Stock Out & Adjustments')

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
                <span style="font-size: 1.75rem;">📦</span>
                <h2 style="font-size: 1.5rem; font-weight: 800;">Stock Out & Inventory Adjustments</h2>
            </div>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Officially record stock write-offs, damages, expiry, internal store usage, or count corrections to maintain 100% physical count accuracy.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            @if(auth()->user()?->role !== 'viewer')
                <button class="btn btn-danger" onclick="openModal('modalStockAdjustment')">
                    📉 Record Stock Out / Adjustment
                </button>
            @else
                <span style="font-size: 0.82rem; font-weight: 800; color: #facc15; background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.4); padding: 0.5rem 1rem; border-radius: 10px;">
                    👑 Executive Observer
                </span>
            @endif
            <a href="{{ route('stock.index') }}" class="btn btn-secondary">
                📦 Stock Hub
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <h4>Stock Out & Adjustment Events</h4>
            <div class="val" style="color: #fbbf24;">{{ number_format($totalAdjustmentsCount) }}</div>
        </div>
        <div class="summary-card">
            <h4>Total Physical Units Adjusted Out</h4>
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
                    <label style="font-size: 0.75rem;">Stock Out / Reason Type</label>
                    <select name="type">
                        <option value="">-- All Stock Out Types --</option>
                        <option value="DAMAGE" {{ request('type') === 'DAMAGE' ? 'selected' : '' }}>💥 Physical Damage / Breakage</option>
                        <option value="EXPIRED" {{ request('type') === 'EXPIRED' ? 'selected' : '' }}>⏳ Expired / Past Shelf Life</option>
                        <option value="INTERNAL_USE" {{ request('type') === 'INTERNAL_USE' ? 'selected' : '' }}>🏢 Internal Store Use</option>
                        <option value="SAMPLE" {{ request('type') === 'SAMPLE' ? 'selected' : '' }}>🎁 Sample / Giveaway</option>
                        <option value="SHRINKAGE" {{ request('type') === 'SHRINKAGE' ? 'selected' : '' }}>🔍 Stock Shrinkage / Missing</option>
                        <option value="THEFT" {{ request('type') === 'THEFT' ? 'selected' : '' }}>🚨 Theft / Pilferage</option>
                        <option value="SUPPLIER_RETURN" {{ request('type') === 'SUPPLIER_RETURN' ? 'selected' : '' }}>🔄 Return to Supplier</option>
                        <option value="CORRECTION" {{ request('type') === 'CORRECTION' ? 'selected' : '' }}>⚖️ Downward Count Correction</option>
                        <option value="CUSTOMER_GOODWILL" {{ request('type') === 'CUSTOMER_GOODWILL' ? 'selected' : '' }}>🤝 Customer Replacement</option>
                        <option value="OTHER" {{ request('type') === 'OTHER' ? 'selected' : '' }}>📝 Other / General</option>
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
                Stock Out & Adjustments Audit Log
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
                        <th>Branch Shop</th>
                        <th>Product SKU</th>
                        <th>Stock Out Type</th>
                        <th style="color: #f87171;">Qty Deducted</th>
                        <th>Reason / Notes</th>
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
                            <strong style="color: #60a5fa; font-size: 1.05rem; letter-spacing: 0.03em;">{{ $adj->product_code ?? $adj->product_name }}</strong>
                        </td>
                        <td>
                            @php
                                $typeKey = strtoupper($adj->type);
                                $badgeConfig = match($typeKey) {
                                    'DAMAGE' => ['class' => 'badge-danger', 'icon' => '💥', 'label' => 'Damage'],
                                    'EXPIRED' => ['class' => 'badge-warning', 'icon' => '⏳', 'label' => 'Expired'],
                                    'INTERNAL_USE' => ['class' => 'badge-info', 'icon' => '🏢', 'label' => 'Internal Use'],
                                    'SAMPLE' => ['class' => 'badge-primary', 'icon' => '🎁', 'label' => 'Sample'],
                                    'SHRINKAGE' => ['class' => 'badge-warning', 'icon' => '🔍', 'label' => 'Shrinkage'],
                                    'THEFT' => ['class' => 'badge-danger', 'icon' => '🚨', 'label' => 'Theft / Loss'],
                                    'SUPPLIER_RETURN' => ['class' => 'badge-secondary', 'icon' => '🔄', 'label' => 'Supplier Return'],
                                    'CORRECTION' => ['class' => 'badge-info', 'icon' => '⚖️', 'label' => 'Correction'],
                                    'CUSTOMER_GOODWILL' => ['class' => 'badge-success', 'icon' => '🤝', 'label' => 'Replacement'],
                                    default => ['class' => 'badge-secondary', 'icon' => '📉', 'label' => $adj->type],
                                };
                            @endphp
                            <span class="badge {{ $badgeConfig['class'] }}">
                                {{ $badgeConfig['icon'] }} {{ $badgeConfig['label'] }}
                            </span>
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
                            No stock out or adjustment records matching your filters.
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

    <!-- Modal: Record Stock Out / Adjustment -->
    <div id="modalStockAdjustment" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">📉 Record Stock Out / Stock Adjustment</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Deduct items from physical inventory count due to damage, expiry, internal store usage, sample, loss, or count correction.
            </p>

            <form id="adjustmentForm" method="POST" action="{{ route('stock.adjustments.record') }}">
                @csrf

                <div class="form-group">
                    <label>Branch Shop Location</label>
                    <select name="warehouse_id" id="adjWarehouse" required onchange="updateAdjStockBadge()">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Product (Search by Name or SKU)</label>
                    @include('components.searchable-product-picker', [
                        'id' => 'adjProduct',
                        'name' => 'product_id',
                        'products' => $products,
                        'placeholder' => '🔍 Search item by name, brand, or SKU code...',
                        'required' => true
                    ])
                    <div id="adjStockBadge" style="display: none; margin-top: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 8px; font-size: 0.82rem; font-weight: 700;"></div>
                </div>

                <div class="form-group">
                    <label>Reason / Stock Out Type</label>
                    <select name="type" id="adjType" required>
                        <option value="DAMAGE">💥 Physical Damage / Broken / Defective Goods</option>
                        <option value="EXPIRED">⏳ Expired / Past Shelf Life</option>
                        <option value="INTERNAL_USE">🏢 Internal Store Use / Staff Consumption</option>
                        <option value="SAMPLE">🎁 Promotional Sample / Marketing Giveaway</option>
                        <option value="SHRINKAGE">🔍 Stock Shrinkage / Missing from Shelf</option>
                        <option value="THEFT">🚨 Theft / Pilferage / Unaccounted Loss</option>
                        <option value="SUPPLIER_RETURN">🔄 Return of Defective Batch to Supplier</option>
                        <option value="CORRECTION">⚖️ Downward Count Correction / Audit Reconciliation</option>
                        <option value="CUSTOMER_GOODWILL">🤝 Customer Compensation / Goodwill Replacement</option>
                        <option value="OTHER">📝 Other / General Stock Out (Custom Note)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity to Deduct (Units)</label>
                    <input type="number" name="quantity" id="adjQty" min="1" placeholder="e.g. 5" required oninput="updateAdjStockBadge()">
                </div>

                <div class="form-group">
                    <label>Reason Note / Additional Details <span style="font-size: 0.78rem; font-weight: 500; color: var(--text-muted);">(Optional — leave blank to use reason above)</span></label>
                    <input type="text" name="reason" id="adjReason" placeholder="Optional: explain incident or leave blank">
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalStockAdjustment')">Cancel</button>
                    <button type="button" class="btn btn-danger" style="flex: 1;" onclick="confirmAdjustment()">📉 Confirm Stock Out / Deduction</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
window.warehouseStockMap = @json($warehouseStockMap ?? []);

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
    if (window.initSearchableProductPickers) {
        window.initSearchableProductPickers();
    }
    updateAdjStockBadge();
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function updateAdjStockBadge() {
    const prodEl = document.getElementById('adjProduct');
    const badge = document.getElementById('adjStockBadge');
    const whEl = document.getElementById('adjWarehouse');
    const qtyInput = document.getElementById('adjQty');
    if (!prodEl || !badge || !whEl) return;

    const prodId = prodEl.value;
    const whId = whEl.value;
    if (!prodId || !whId) {
        badge.style.display = 'none';
        return;
    }

    const avail = (window.warehouseStockMap && window.warehouseStockMap[whId] && window.warehouseStockMap[whId][prodId] !== undefined)
        ? parseInt(window.warehouseStockMap[whId][prodId], 10)
        : 0;
    const reqQty = parseInt(qtyInput ? qtyInput.value : 0, 10) || 0;

    badge.style.display = 'block';
    if (avail <= 0) {
        badge.style.background = 'rgba(239, 68, 68, 0.15)';
        badge.style.border = '1px solid rgba(239, 68, 68, 0.4)';
        badge.style.color = '#fca5a5';
        badge.innerHTML = '❌ <strong>0 physical units available</strong> in this branch shop (Cannot Deduct Out-of-Stock Item)';
    } else if (reqQty > avail) {
        badge.style.background = 'rgba(239, 68, 68, 0.15)';
        badge.style.border = '1px solid rgba(239, 68, 68, 0.4)';
        badge.style.color = '#fca5a5';
        badge.innerHTML = '⚠️ Requested deduction of <strong>' + reqQty + ' unit(s)</strong> exceeds available ground stock of <strong>' + avail + ' unit(s)</strong>';
    } else {
        badge.style.background = 'rgba(16, 185, 129, 0.12)';
        badge.style.border = '1px solid rgba(16, 185, 129, 0.35)';
        badge.style.color = '#6ee7b7';
        badge.innerHTML = '✅ <strong>' + avail + ' physical unit(s)</strong> available on ground in this branch';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const prodEl = document.getElementById('adjProduct');
    if (prodEl) {
        prodEl.addEventListener('change', updateAdjStockBadge);
    }
    const whEl = document.getElementById('adjWarehouse');
    if (whEl) {
        whEl.addEventListener('change', updateAdjStockBadge);
    }
    const qtyEl = document.getElementById('adjQty');
    if (qtyEl) {
        qtyEl.addEventListener('input', updateAdjStockBadge);
    }
});

function confirmAdjustment() {
    const form = document.getElementById('adjustmentForm');
    const prodSelect = document.getElementById('adjProduct');
    const whSelect = document.getElementById('adjWarehouse');
    const qty = parseInt(document.getElementById('adjQty').value) || 0;
    const reasonInput = document.getElementById('adjReason');
    const typeSelect = document.getElementById('adjType');
    const errors = [];

    if (!prodSelect || !prodSelect.value) {
        errors.push({
            title: 'Product Selection Required',
            desc: 'Please search and select which product to adjust or stock out.',
            focus: 'spc_input_adjProduct'
        });
    }

    if (qty <= 0) {
        errors.push({
            title: 'Invalid Stock Out Quantity',
            desc: 'Please enter at least 1 unit to deduct from physical stock.',
            focus: 'adjQty'
        });
    }

    const prodId = prodSelect ? prodSelect.value : null;
    const whId = whSelect ? whSelect.value : null;
    if (prodId && whId) {
        const avail = (window.warehouseStockMap && window.warehouseStockMap[whId] && window.warehouseStockMap[whId][prodId] !== undefined)
            ? parseInt(window.warehouseStockMap[whId][prodId], 10)
            : 0;

        if (avail <= 0) {
            errors.push({
                title: 'No Physical Stock in Shop',
                desc: 'The selected branch shop currently has 0 physical units available for this product. You cannot adjust or write off zero-balance stock.',
                focus: 'spc_input_adjProduct'
            });
        } else if (qty > avail) {
            errors.push({
                title: 'Quantity Exceeds Physical Stock (' + avail + ' available)',
                desc: 'You requested to deduct ' + qty + ' unit(s), but only ' + avail + ' physical unit(s) are present in this shop. Please reduce the quantity.',
                focus: 'adjQty'
            });
        }
    }

    if (errors.length > 0) {
        showActionBlockedModal({
            title: 'Stock Out Cannot Be Authorized',
            subtitle: 'Please resolve the following inventory requirements:',
            errors: errors
        });
        return;
    }

    const selectedTypeText = typeSelect.options[typeSelect.selectedIndex].text;
    const cleanTypeLabel = selectedTypeText.replace(/^[\p{Emoji}\s]+/u, '').trim();
    let reason = reasonInput.value.trim();
    if (!reason) {
        reason = cleanTypeLabel;
        reasonInput.value = cleanTypeLabel; // populate default for form submission
    }

    const prodName = prodSelect.options[prodSelect.selectedIndex].text;
    const whName = whSelect ? whSelect.options[whSelect.selectedIndex].text : 'Branch Shop';

    closeModal('modalStockAdjustment');

    showConfirmPopup({
        icon: '📉',
        title: 'Confirm Stock Out / Adjustment',
        subtitle: 'Authorize deduction of inventory from physical count:',
        borderColor: '#ef4444',
        items: [
            { label: 'Branch Shop', value: whName, color: '#60a5fa' },
            { label: 'Product', value: prodName, color: '#f8fafc' },
            { label: 'Units to Deduct', value: '- ' + qty + ' units', color: '#f87171', size: '1.1rem' },
            { label: 'Reason / Type', value: cleanTypeLabel, color: '#fbbf24' },
            { label: 'Incident Notes', value: reason, color: '#cbd5e1' }
        ],
        impact: {
            text: '🛡️ INVENTORY REDUCTION: Deducts ' + qty + ' physical units from ' + whName + ' and writes an audit ledger entry.',
            type: 'danger'
        },
        confirmText: '📉 Yes, Authorize Stock Out',
        confirmClass: 'btn-danger',
        form: form
    });
}
</script>
@endpush
