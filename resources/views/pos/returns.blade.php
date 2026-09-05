@extends('layouts.app')

@section('title', 'Sales Returns & Refunds')

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
                <span style="font-size: 1.75rem;">🔄</span>
                <h2 style="font-size: 1.5rem; font-weight: 800;">Sales Returns & Customer Refunds</h2>
            </div>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Accept customer returns, restore physical stock to shelves, and refund cash or adjust debt.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button class="btn btn-warning" onclick="openModal('modalProcessReturn')">
                🔄 Process New Return
            </button>
            <a href="{{ route('transactions.index', ['tab' => 'returns']) }}" class="btn btn-secondary">
                📜 Ledgers Hub
            </a>
        </div>
    </div>

    <!-- Summary Overview Grid -->
    <div class="summary-grid">
        <div class="summary-card">
            <h4>Total Return Incidents</h4>
            <div class="val" style="color: #fbbf24;">{{ number_format($totalReturnsCount) }}</div>
        </div>
        <div class="summary-card">
            <h4>Units Restocked to Shelf</h4>
            <div class="val" style="color: #4ade80;">+{{ number_format($totalUnitsRestocked) }} units</div>
        </div>
        <div class="summary-card">
            <h4>Total Restitution Value</h4>
            <div class="val" style="color: #f87171;">₦{{ number_format($totalRefundValue, 0) }}</div>
        </div>
    </div>

    <!-- Multi-Criteria Filter Card -->
    <div class="filter-card">
        <form method="GET" action="{{ route('pos.returns') }}">
            <!-- Quick Date Pills -->
            <div style="display: flex; gap: 0.4rem; margin-bottom: 0.85rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Quick Dates:</span>
                <a href="{{ route('pos.returns', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'ALL'])) }}" class="date-pill {{ $datePreset === 'ALL' && !request('from_date') ? 'active' : '' }}">All Time</a>
                <a href="{{ route('pos.returns', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'TODAY'])) }}" class="date-pill {{ $datePreset === 'TODAY' ? 'active' : '' }}">Today</a>
                <a href="{{ route('pos.returns', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'YESTERDAY'])) }}" class="date-pill {{ $datePreset === 'YESTERDAY' ? 'active' : '' }}">Yesterday</a>
                <a href="{{ route('pos.returns', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'THIS_WEEK'])) }}" class="date-pill {{ $datePreset === 'THIS_WEEK' ? 'active' : '' }}">This Week</a>
                <a href="{{ route('pos.returns', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'THIS_MONTH'])) }}" class="date-pill {{ $datePreset === 'THIS_MONTH' ? 'active' : '' }}">This Month</a>
            </div>

            <div class="grid-3" style="gap: 0.75rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Return Reason</label>
                    <select name="return_reason">
                        <option value="">-- All Return Reasons --</option>
                        <option value="Defective" {{ request('return_reason') === 'Defective' ? 'selected' : '' }}>Defective or Damaged</option>
                        <option value="Wrong" {{ request('return_reason') === 'Wrong' ? 'selected' : '' }}>Wrong Product Delivered</option>
                        <option value="Exchange" {{ request('return_reason') === 'Exchange' ? 'selected' : '' }}>Customer Mind Change / Exchange</option>
                        <option value="Expired" {{ request('return_reason') === 'Expired' ? 'selected' : '' }}>Expired Date Discovered</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 0.85rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="🔍 Search Return Ref, Invoice #, Customer, Product...">
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    🔍 Apply Filters
                </button>

                <a href="{{ route('pos.returns') }}" class="btn btn-secondary" style="padding: 0.65rem 1rem;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Recent Returns Table -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800;">
                Processed Returns Audit Trail
            </h3>
            <div style="width: 280px;">
                <input type="text" placeholder="⚡ Live search table rows..." onkeyup="filterTableRows('returnsTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
            </div>
        </div>

        <div class="table-wrap">
            <table id="returnsTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Return Ref</th>
                        <th>Original Sale</th>
                        <th>Customer</th>
                        <th>Product Returned</th>
                        <th style="color: #f87171;">Refund Amount</th>
                        <th>Reason</th>
                        <th>Staff Officer</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentReturns as $ret)
                    <tr>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            {{ date('d M Y, h:i A', strtotime($ret->created_at ?? $ret->createdAt)) }}
                        </td>
                        <td><span class="badge badge-warning">{{ $ret->code }}</span></td>
                        <td><strong style="color: #93c5fd;">#{{ substr($ret->saleId, 0, 8) }}</strong></td>
                        <td><strong>{{ $ret->customerName ?? 'Walk-in Customer' }}</strong></td>
                        <td><span class="badge badge-info">{{ $ret->productName }} ({{ $ret->quantity }} units)</span></td>
                        <td style="font-weight: 800; color: #4ade80; font-size: 1.05rem;">
                            ₦{{ number_format($ret->refundAmount, 0) }}
                        </td>
                        <td style="color: #cbd5e1;">{{ $ret->reason }}</td>
                        <td>{{ $ret->userName }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No sales returns matching your filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1.25rem;">
            {{ $recentReturns->links() }}
        </div>
    </div>

    <!-- Modal: Process Return -->
    <div id="modalProcessReturn" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 620px;">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">🔄 Process Customer Return</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Select the past sale invoice and specify items returned.
            </p>

            <form id="returnForm" method="POST" action="{{ route('pos.returns.process') }}">
                @csrf
                <input type="hidden" name="idempotency_key" id="returnIdempotencyKey" value="">

                <div class="form-group">
                    <label>Select Original Sale Invoice</label>
                    <select name="sale_id" id="returnSaleSelect" required onchange="loadSaleItems(this)">
                        <option value="">-- Choose Sale Invoice --</option>
                        @foreach($sales as $s)
                            <option value="{{ $s->id }}" data-items="{{ json_encode($s->items) }}" data-customer="{{ $s->customerName }}">
                                Sale #{{ substr($s->id, 0, 8) }} — {{ $s->customerName }} (₦{{ number_format($s->totalAmount, 0) }}) on {{ date('d/m/Y', strtotime($s->createdAt)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Receiving Branch Shop</label>
                    <select name="warehouse_id" id="returnWarehouse" required>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="returnItemsContainer" style="margin-bottom: 1rem;">
                    <!-- Items dynamically populated here -->
                </div>

                <div class="form-group">
                    <label>Refund Action</label>
                    <select name="refund_method" id="returnRefundMethod" required>
                        <option value="CASH_REFUND">💵 Cash Refund to Customer</option>
                        <option value="DEBT_REDUCTION">💳 Reduce Customer Debt Balance</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Reason for Return</label>
                    <select name="reason" id="returnReason" required>
                        <option value="Defective or Damaged packaging">Defective or Damaged packaging</option>
                        <option value="Wrong product delivered">Wrong product delivered</option>
                        <option value="Customer changed mind / Exchange">Customer changed mind / Exchange</option>
                        <option value="Expired date discovered">Expired date discovered</option>
                    </select>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalProcessReturn')">Cancel</button>
                    <button type="button" class="btn btn-warning" style="flex: 1;" onclick="confirmProcessReturn()">✓ Process Return & Restock</button>
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

let currentReturnIdempotencyKey = null;

function getOrCreateReturnIdempotencyKey() {
    if (!currentReturnIdempotencyKey) {
        currentReturnIdempotencyKey = 'ret-' + (window.crypto && crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).substring(2)));
    }
    const input = document.getElementById('returnIdempotencyKey');
    if (input) {
        input.value = currentReturnIdempotencyKey;
    }
    return currentReturnIdempotencyKey;
}

function resetReturnIdempotencyKey() {
    currentReturnIdempotencyKey = null;
    const input = document.getElementById('returnIdempotencyKey');
    if (input) input.value = '';
}

function openModal(id) { 
    if (id === 'modalProcessReturn') {
        getOrCreateReturnIdempotencyKey();
    }
    document.getElementById(id).style.display = 'flex'; 
}

function closeModal(id) { 
    document.getElementById(id).style.display = 'none'; 
}

function loadSaleItems(select) {
    const container = document.getElementById('returnItemsContainer');
    const selectedOption = select.options[select.selectedIndex];
    const itemsJson = selectedOption.getAttribute('data-items');

    if (!itemsJson) {
        container.innerHTML = '';
        return;
    }

    const items = JSON.parse(itemsJson);
    let html = '<label style="font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:0.4rem;display:block;">Select Items to Return:</label>';

    items.forEach((item, index) => {
        html += `
        <div style="background:rgba(15,23,42,0.6);border:1px solid var(--border);border-radius:12px;padding:0.75rem;margin-bottom:0.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
            <div>
                <input type="hidden" name="items[${index}][productId]" value="${item.productId}">
                <input type="hidden" name="items[${index}][unitPrice]" value="${item.unitPrice}">
                <strong>${item.productName}</strong>
                <div style="font-size:0.75rem;color:#9ca3af;">Bought ${item.quantity} units @ ₦${Math.round(item.unitPrice).toLocaleString()}</div>
            </div>
            <div style="max-width:120px;">
                <label style="font-size:0.7rem;">Return Qty:</label>
                <input type="number" name="items[${index}][quantity]" value="${item.quantity}" min="1" max="${item.quantity}" required>
            </div>
        </div>
        `;
    });

    container.innerHTML = html;
}

function confirmProcessReturn() {
    const form = document.getElementById('returnForm');
    const saleSelect = document.getElementById('returnSaleSelect');
    const errors = [];

    if (!saleSelect || !saleSelect.value) {
        errors.push({
            title: 'No Sale Invoice Selected',
            desc: 'Please choose the original sale invoice to process a return against.',
            focus: 'returnSaleSelect'
        });
    }

    const returnItems = form.querySelectorAll('input[name*="[quantity]"]');
    if (returnItems.length === 0) {
        errors.push({
            title: 'No Items Available',
            desc: 'This invoice has no items available to return.',
            focus: 'returnSaleSelect'
        });
    }

    let hasNonZero = false;
    returnItems.forEach(input => {
        const qty = parseInt(input.value) || 0;
        const max = parseInt(input.getAttribute('max')) || 0;
        if (qty > 0) hasNonZero = true;
        if (qty > max) {
            errors.push({
                title: 'Quantity Exceeds Original Sale',
                desc: `Return quantity (${qty}) cannot be greater than original quantity purchased (${max}).`,
                focus: input
            });
        }
    });

    if (!hasNonZero) {
        errors.push({
            title: 'Zero Return Quantity',
            desc: 'Please enter at least 1 unit to return.',
            focus: returnItems[0]
        });
    }

    if (errors.length > 0) {
        showActionBlockedModal({
            title: 'Return Cannot Be Processed',
            subtitle: 'Please resolve the following return requirements:',
            errors: errors
        });
        return;
    }

    const selectedOpt = saleSelect.options[saleSelect.selectedIndex];
    const custName = selectedOpt.getAttribute('data-customer') || 'Customer';
    const saleText = selectedOpt.text;
    const whSelect = document.getElementById('returnWarehouse');
    const whName = whSelect.options[whSelect.selectedIndex].text;
    const refundSelect = document.getElementById('returnRefundMethod');
    const refundName = refundSelect.options[refundSelect.selectedIndex].text;
    const reasonSelect = document.getElementById('returnReason');
    const reasonText = reasonSelect.value;

    closeModal('modalProcessReturn');

    showConfirmPopup({
        icon: '🔄',
        title: 'Confirm Sales Return & Restitution',
        subtitle: 'Review return impact on physical stock and finances:',
        borderColor: '#f59e0b',
        items: [
            { label: 'Customer', value: custName, color: '#f8fafc' },
            { label: 'Original Invoice', value: saleText.split('—')[0].trim(), color: '#93c5fd' },
            { label: 'Restock Branch', value: whName, color: '#4ade80' },
            { label: 'Refund Action', value: refundName, color: '#fbbf24' },
            { label: 'Return Reason', value: reasonText, color: '#cbd5e1' }
        ],
        impact: {
            text: '🔄 RESTOCK & AUDIT: Returned items will be added back into physical closing stock and the refund/debt reduction will be recorded in audit logs.',
            type: 'warning'
        },
        confirmText: '✓ Yes, Process Return & Restock',
        confirmClass: 'btn-warning',
        form: form
    });
}
</script>
@endpush
