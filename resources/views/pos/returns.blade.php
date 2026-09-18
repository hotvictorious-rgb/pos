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
                        <th>Restock Action</th>
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
                        <td>
                            @if(isset($ret->wasDelivered) && !$ret->wasDelivered)
                                <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #facc15; border: 1px solid rgba(245, 158, 11, 0.4); font-size: 0.75rem;">
                                    ⏳ 0 Restocked (Unsupplied Buffer Released)
                                </span>
                            @else
                                <span class="badge" style="background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); font-size: 0.75rem;">
                                    📦 +{{ $ret->quantity }} Shelf Restocked
                                </span>
                            @endif
                        </td>
                        <td style="font-weight: 800; color: #4ade80; font-size: 1.05rem;">
                            ₦{{ number_format($ret->refundAmount, 0) }}
                        </td>
                        <td style="color: #cbd5e1;">{{ $ret->reason }}</td>
                        <td>{{ $ret->userName }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-muted);">
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

    <!-- Modal: Process Return (Searchable & Isolated) -->
    <div id="modalProcessReturn" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 680px;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.5rem;">🔄</span>
                    <h3 style="font-size: 1.3rem; font-weight: 800; margin: 0;">Process Customer Return & Refund</h3>
                </div>
                <button type="button" onclick="closeModal('modalProcessReturn')" style="background: none; border: none; color: #9ca3af; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                Search any invoice by <strong>Physical Slip #</strong>, <strong>Invoice ID</strong>, or <strong>Phone</strong>. Unsupplied goods will cancel reservations without adding fake inventory to shelves.
            </p>

            <!-- 1. Searchable Invoice Finder Box -->
            <div style="background: rgba(15, 23, 42, 0.8); border: 1.5px solid #3b82f6; border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem;">
                <label style="font-size: 0.8rem; font-weight: 800; color: #93c5fd; text-transform: uppercase; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.4rem;">
                    🔍 Step 1: Search Invoice at this Branch
                </label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="returnSearchQuery" placeholder="e.g. 4082 (Slip #), 08012345678, #4eae6d29, or Customer..." style="flex: 1; padding: 0.65rem; border-radius: 8px; border: 1px solid var(--border); background: #1e293b; color: #fff;" onkeydown="if(event.key === 'Enter'){ event.preventDefault(); executeReturnInvoiceLookup(); }">
                    <button type="button" class="btn btn-primary" onclick="executeReturnInvoiceLookup()" style="padding: 0.65rem 1.2rem; font-weight: 700; white-space: nowrap;">
                        🔍 Find Invoice
                    </button>
                </div>
                <div id="returnLookupFeedback" style="font-size: 0.78rem; margin-top: 0.4rem; display: none;"></div>

                <!-- Multi-match list if multiple sales match -->
                <div id="returnMultiMatchBox" style="display: none; margin-top: 0.65rem; max-height: 140px; overflow-y: auto; border-top: 1px dashed rgba(255,255,255,0.15); padding-top: 0.5rem;">
                    <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.35rem; font-weight: 700;">Multiple Invoices Found — Click to select:</div>
                    <div id="returnMultiMatchItems" style="display: flex; flex-direction: column; gap: 0.35rem;"></div>
                </div>
            </div>

            <form id="returnForm" method="POST" action="{{ route('pos.returns.process') }}">
                @csrf
                <input type="hidden" name="idempotency_key" id="returnIdempotencyKey" value="">
                <input type="hidden" name="sale_id" id="returnSaleIdInput" value="">

                <!-- 2. Selected Invoice Preview Banner -->
                <div id="returnSelectedSaleCard" style="display: none; background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(59, 130, 246, 0.4); border-radius: 12px; padding: 0.85rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <strong style="color: #60a5fa; font-size: 1rem;" id="cardSaleRef">Sale #--------</strong>
                            <div style="font-size: 0.8rem; color: #cbd5e1;" id="cardSaleCustomer">Customer: --</div>
                            <div style="font-size: 0.75rem; color: #94a3b8;" id="cardSaleDate">Date: --</div>
                        </div>
                        <div style="text-align: right;">
                            <div id="cardDeliveryStatusBadge" style="margin-bottom: 0.25rem;"></div>
                            <div style="font-weight: 800; color: #4ade80;" id="cardSaleTotal">Total: ₦0</div>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #cbd5e1; text-transform: uppercase;">Receiving Branch Shop</label>
                    <select name="warehouse_id" id="returnWarehouse" required style="width: 100%; padding: 0.6rem; border-radius: 8px; background: #1e293b; color: #fff; border: 1px solid var(--border);">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- 3. Dynamic Items Selection Box -->
                <div id="returnItemsContainer" style="margin-bottom: 1rem;">
                    <div style="padding: 1.5rem; text-align: center; color: var(--text-muted); background: rgba(15,23,42,0.4); border: 1px dashed var(--border); border-radius: 12px;">
                        Please search and select an invoice above to view and return items.
                    </div>
                </div>

                <!-- Live Refund Calculation Summary -->
                <div id="returnRefundPreviewBox" style="display: none; background: rgba(15, 23, 42, 0.9); border: 1px solid #10b981; border-radius: 12px; padding: 0.85rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <span style="font-size: 0.85rem; color: #94a3b8;">Total Value of Returned Items:</span>
                        <strong style="font-size: 1.15rem; color: #f8fafc;" id="returnTotalValuePreview">₦0</strong>
                    </div>
                    <div id="returnDebtSplitRow" style="display: none; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; border-top: 1px dashed rgba(255,255,255,0.1); padding-top: 0.35rem;">
                        <span style="font-size: 0.82rem; color: #f59e0b;">📉 Debt to Cancel:</span>
                        <strong style="font-size: 0.95rem; color: #fbbf24;" id="returnDebtReductionPreview">-₦0</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <span style="font-size: 0.85rem; color: #86efac; font-weight: 700;">💵 Actual Cash/Money to Refund:</span>
                        <strong style="font-size: 1.25rem; color: #4ade80;" id="returnTotalRefundPreview">₦0</strong>
                    </div>
                    <div id="returnInventoryImpactNote" style="font-size: 0.8rem; color: #cbd5e1; margin-top: 0.4rem; padding-top: 0.35rem; border-top: 1px solid rgba(255,255,255,0.08);"></div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #cbd5e1; text-transform: uppercase;">Restitution Action</label>
                    <select name="refund_method" id="returnRefundMethod" required onchange="recalculateReturnRefundTotals()" style="width: 100%; padding: 0.6rem; border-radius: 8px; background: #1e293b; color: #fff; border: 1px solid var(--border);">
                        <option value="REFUND" selected>🔄 Process Return & Refund</option>
                        <option value="DEBT_REDUCTION">📉 Reduce Customer Debt Only</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label style="font-size: 0.8rem; font-weight: 700; color: #cbd5e1; text-transform: uppercase;">Mandatory Audit Reason for Return</label>
                    <select name="reason" id="returnReason" required style="width: 100%; padding: 0.6rem; border-radius: 8px; background: #1e293b; color: #fff; border: 1px solid var(--border);">
                        <option value="Customer cancelled unsupplied order (Buffer refund)">Customer cancelled unsupplied order (Buffer refund)</option>
                        <option value="Defective or Damaged product packaging">Defective or Damaged product packaging</option>
                        <option value="Wrong product delivered">Wrong product delivered</option>
                        <option value="Customer changed mind / Exchange">Customer changed mind / Exchange</option>
                        <option value="Expired date discovered">Expired date discovered</option>
                    </select>
                </div>

                <div style="display: flex; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalProcessReturn')">Cancel</button>
                    <button type="button" class="btn btn-warning" id="btnSubmitReturn" style="flex: 1; font-weight: 800;" onclick="confirmProcessReturn()" disabled>✓ Process Return & Restitution</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
let currentReturnIdempotencyKey = null;
let currentLoadedSale = null;

function getOrCreateReturnIdempotencyKey() {
    if (!currentReturnIdempotencyKey) {
        currentReturnIdempotencyKey = 'ret-' + (window.crypto && crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).substring(2)));
    }
    const input = document.getElementById('returnIdempotencyKey');
    if (input) input.value = currentReturnIdempotencyKey;
    return currentReturnIdempotencyKey;
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

/**
 * Live Search Invoice for Return
 */
async function executeReturnInvoiceLookup() {
    const input = document.getElementById('returnSearchQuery');
    const feedback = document.getElementById('returnLookupFeedback');
    const multiBox = document.getElementById('returnMultiMatchBox');
    const multiItems = document.getElementById('returnMultiMatchItems');
    const term = input.value.trim();

    if (!term) {
        feedback.style.display = 'block';
        feedback.style.color = '#f87171';
        feedback.textContent = 'Please enter an invoice ID, Paper Slip #, Phone, or Customer Name.';
        return;
    }

    feedback.style.display = 'block';
    feedback.style.color = '#93c5fd';
    feedback.textContent = 'Searching branch invoices...';
    multiBox.style.display = 'none';

    try {
        const res = await fetch(`{{ route('pos.lookup_sale') }}?term=${encodeURIComponent(term)}`);
        const data = await res.json();

        if (!data.success || !data.sale) {
            feedback.style.color = '#f87171';
            feedback.textContent = data.error || 'No matching sale found at this branch.';
            return;
        }

        feedback.style.color = '#4ade80';
        feedback.textContent = `✓ Found invoice ${data.sale.ref}!`;

        // If multiple matches were returned, offer quick switcher
        if (data.matches && data.matches.length > 1) {
            multiBox.style.display = 'block';
            multiItems.innerHTML = data.matches.map(m => `
                <button type="button" class="btn" style="text-align: left; padding: 0.4rem 0.6rem; font-size: 0.78rem; background: rgba(30,41,59,0.9); border: 1px solid #475569; display: flex; justify-content: space-between; align-items: center;" onclick="selectSpecificSale('${m.id}')">
                    <span><strong>${m.ref}</strong> · ${m.customerName || 'Walk-in'} (₦${Math.round(m.totalAmount).toLocaleString()})</span>
                    <span style="font-size: 0.7rem; color: ${m.isSupplied ? '#86efac' : '#facc15'};">${m.isSupplied ? '✓ Supplied' : '⏳ Unsupplied'}</span>
                </button>
            `).join('');
        }

        populateReturnSale(data.sale);

    } catch (e) {
        feedback.style.color = '#f87171';
        feedback.textContent = 'Error connecting to server to search invoice.';
    }
}

async function selectSpecificSale(saleId) {
    try {
        const res = await fetch(`{{ route('pos.lookup_sale') }}?term=${encodeURIComponent(saleId)}`);
        const data = await res.json();
        if (data.success && data.sale) {
            populateReturnSale(data.sale);
        }
    } catch (e) {
        alert('Could not load selected invoice.');
    }
}

function populateReturnSale(sale) {
    currentLoadedSale = sale;
    document.getElementById('returnSaleIdInput').value = sale.id;

    // Show Card
    const card = document.getElementById('returnSelectedSaleCard');
    card.style.display = 'block';
    document.getElementById('cardSaleRef').textContent = `Sale ${sale.ref}`;
    document.getElementById('cardSaleCustomer').textContent = `Customer: ${sale.customerName || 'Walk-in Customer'} ${sale.customerPhone ? '· ' + sale.customerPhone : ''}`;
    document.getElementById('cardSaleDate').textContent = `Date: ${sale.date}`;

    // Total Bill & Outstanding Info
    const totalBill = Math.round(sale.totalAmount || 0).toLocaleString();
    const paidBill = Math.round(sale.paidAmount || 0).toLocaleString();
    const debt = Math.round(sale.outstandingBalance || 0);
    let cardTotalHtml = `<div>Total Bill: ₦${totalBill}</div>`;
    cardTotalHtml += `<div style="font-size:0.75rem; color:#94a3b8;">Paid: ₦${paidBill}`;
    if (debt > 0) {
        cardTotalHtml += ` · <span style="color:#f87171; font-weight:700;">Debt: ₦${debt.toLocaleString()}</span>`;
    }
    cardTotalHtml += `</div>`;
    document.getElementById('cardSaleTotal').innerHTML = cardTotalHtml;

    // Delivery Status Badge
    const badgeEl = document.getElementById('cardDeliveryStatusBadge');
    if (sale.isSupplied) {
        badgeEl.innerHTML = `<span class="badge badge-success" style="background:#166534; color:#86efac; border:1px solid #22c55e;">✓ DELIVERED GOODS (Physical restock will occur)</span>`;
    } else {
        badgeEl.innerHTML = `<span class="badge badge-warning" style="background:#854d0e; color:#fef08a; border:1px solid #eab308;">⏳ UNSUPPLIED ORDER (Reservation released, 0 physical stock added, money refunded only)</span>`;
    }

    // Render Items
    const container = document.getElementById('returnItemsContainer');
    if (!sale.items || sale.items.length === 0) {
        container.innerHTML = `<div style="padding: 1rem; color: #f87171; text-align: center;">No items found on this invoice.</div>`;
        document.getElementById('btnSubmitReturn').disabled = true;
        return;
    }

    let itemsHtml = `
        <label style="font-size:0.8rem; font-weight:800; color:#93c5fd; text-transform:uppercase; margin-bottom:0.5rem; display:block;">
            Step 2: Check Items & Enter Quantity to Return:
        </label>
    `;

    sale.items.forEach((item, index) => {
        const isEligible = item.eligibleQty > 0;
        itemsHtml += `
        <div style="background: rgba(15,23,42,0.8); border: 1px solid ${isEligible ? 'var(--border)' : '#475569'}; border-radius: 12px; padding: 0.75rem; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; opacity: ${isEligible ? '1' : '0.6'};">
            <div style="display: flex; align-items: center; gap: 0.6rem; flex: 1;">
                <input type="checkbox" id="chk_ret_${index}" onchange="recalculateReturnRefundTotals()" ${isEligible ? 'checked' : 'disabled'} style="width: 18px; height: 18px; cursor: pointer;">
                <div>
                    <input type="hidden" name="items[${index}][productId]" value="${item.productId}">
                    <input type="hidden" name="items[${index}][unitPrice]" value="${item.unitPrice}">
                    <input type="hidden" name="items[${index}][was_delivered]" value="${sale.isSupplied ? '1' : '0'}">
                    <strong style="color: #f8fafc; font-size: 0.92rem;">${item.productName}</strong>
                    <div style="font-size: 0.75rem; color: #94a3b8;">
                        SKU: ${item.productCode} · Sold: ${item.soldQty} | Already Returned: ${item.alreadyReturnedQty} | 
                        <span style="color: #4ade80; font-weight: 700;">Eligible: ${item.eligibleQty}</span>
                    </div>
                </div>
            </div>
            <div style="max-width: 110px; text-align: right;">
                <div style="font-size: 0.7rem; color: #94a3b8;">Return Qty:</div>
                <input type="number" id="qty_ret_${index}" name="items[${index}][quantity]" value="${item.eligibleQty}" min="1" max="${item.eligibleQty}" ${isEligible ? '' : 'disabled'} oninput="recalculateReturnRefundTotals()" style="width: 70px; padding: 0.35rem; border-radius: 6px; border: 1px solid var(--border); background: #1e293b; color: #fff; text-align: center; font-weight: 700;">
            </div>
        </div>
        `;
    });

    container.innerHTML = itemsHtml;
    document.getElementById('btnSubmitReturn').disabled = false;
    document.getElementById('returnRefundPreviewBox').style.display = 'block';
    recalculateReturnRefundTotals();
}

function recalculateReturnRefundTotals() {
    if (!currentLoadedSale || !currentLoadedSale.items) return;

    let totalReturnValue = 0;
    let totalPhysicalRestock = 0;
    let totalBufferReleased = 0;

    currentLoadedSale.items.forEach((item, index) => {
        const chk = document.getElementById(`chk_ret_${index}`);
        const qtyInp = document.getElementById(`qty_ret_${index}`);
        if (chk && chk.checked && item.eligibleQty > 0) {
            let q = parseInt(qtyInp.value) || 0;
            if (q < 1) q = 1;
            if (q > item.eligibleQty) q = item.eligibleQty;
            qtyInp.value = q;

            totalReturnValue += (q * item.unitPrice);
            if (currentLoadedSale.isSupplied) {
                totalPhysicalRestock += q;
            } else {
                totalBufferReleased += q;
            }
        }
    });

    const outstandingDebt = parseFloat(currentLoadedSale.outstandingBalance || 0);
    const refundMethodSelect = document.getElementById('returnRefundMethod');
    const refundMethod = refundMethodSelect ? refundMethodSelect.value : 'REFUND';
    
    let debtReduction = 0;
    let actualMoneyRefund = 0;

    if (refundMethod === 'DEBT_REDUCTION') {
        debtReduction = Math.min(totalReturnValue, outstandingDebt);
        actualMoneyRefund = 0;
    } else { // 'REFUND'
        debtReduction = Math.min(totalReturnValue, outstandingDebt);
        actualMoneyRefund = Math.max(0, totalReturnValue - debtReduction);
        const maxPaidMoney = parseFloat(currentLoadedSale.paidAmount || 0);
        if (actualMoneyRefund > maxPaidMoney) {
            actualMoneyRefund = maxPaidMoney;
        }
    }

    document.getElementById('returnTotalValuePreview').textContent = '₦' + Math.round(totalReturnValue).toLocaleString();
    document.getElementById('returnTotalRefundPreview').textContent = '₦' + Math.round(actualMoneyRefund).toLocaleString();

    const debtSplitRow = document.getElementById('returnDebtSplitRow');
    if (debtReduction > 0) {
        debtSplitRow.style.display = 'flex';
        document.getElementById('returnDebtReductionPreview').textContent = '-₦' + Math.round(debtReduction).toLocaleString();
    } else {
        debtSplitRow.style.display = 'none';
    }

    const noteEl = document.getElementById('returnInventoryImpactNote');
    if (currentLoadedSale.isSupplied) {
        noteEl.innerHTML = `📦 <strong>Physical Inventory:</strong> +${totalPhysicalRestock} units will be returned to shelf stock.`;
        noteEl.style.color = '#86efac';
    } else {
        noteEl.innerHTML = `⏳ <strong>Unsupplied Buffer:</strong> <strong>0 units</strong> added to physical shelf (goods never left store). ${totalBufferReleased} units reservation buffer released.`;
        noteEl.style.color = '#fde047';
    }
}

function confirmProcessReturn() {
    const form = document.getElementById('returnForm');
    const saleId = document.getElementById('returnSaleIdInput').value;
    const errors = [];

    if (!saleId || !currentLoadedSale) {
        errors.push({
            title: 'No Invoice Selected',
            desc: 'Please search and select the original sale invoice first.',
            focus: 'returnSearchQuery'
        });
    }

    let selectedCount = 0;
    if (currentLoadedSale && currentLoadedSale.items) {
        currentLoadedSale.items.forEach((item, index) => {
            const chk = document.getElementById(`chk_ret_${index}`);
            if (chk && chk.checked) selectedCount++;
        });
    }

    if (selectedCount === 0) {
        errors.push({
            title: 'No Items Selected',
            desc: 'Please check at least one product to return.',
            focus: 'returnSearchQuery'
        });
    }

    if (errors.length > 0) {
        showActionBlockedModal({
            title: 'Return Requirements Missing',
            subtitle: 'Please resolve the following before processing:',
            errors: errors
        });
        return;
    }

    const whSelect = document.getElementById('returnWarehouse');
    const whName = whSelect.options[whSelect.selectedIndex].text;
    const refundSelect = document.getElementById('returnRefundMethod');
    const refundName = refundSelect.options[refundSelect.selectedIndex].text;
    const reasonSelect = document.getElementById('returnReason');
    const reasonText = reasonSelect.value;
    const refundTotal = document.getElementById('returnTotalRefundPreview').textContent;
    const totalReturnValue = document.getElementById('returnTotalValuePreview').textContent;

    closeModal('modalProcessReturn');

    const popupItems = [
        { label: 'Customer', value: currentLoadedSale.customerName || 'Walk-in Customer', color: '#f8fafc' },
        { label: 'Original Invoice', value: currentLoadedSale.ref, color: '#93c5fd' },
        { label: 'Delivery Status', value: currentLoadedSale.isSupplied ? '✓ Delivered / Supplied' : '⏳ Unsupplied (Pickup Pending)', color: currentLoadedSale.isSupplied ? '#86efac' : '#fde047' },
        { label: 'Total Value of Items', value: totalReturnValue, color: '#cbd5e1' }
    ];

    const debtSplitRow = document.getElementById('returnDebtSplitRow');
    if (debtSplitRow && debtSplitRow.style.display !== 'none') {
        const debtReductionVal = document.getElementById('returnDebtReductionPreview').textContent;
        popupItems.push({ label: 'Debt to Cancel', value: debtReductionVal, color: '#fbbf24' });
    }

    popupItems.push({ label: 'Actual Cash Refund Due', value: refundTotal, color: '#4ade80' });
    popupItems.push({ label: 'Mandatory Reason', value: reasonText, color: '#93c5fd' });

    showConfirmPopup({
        icon: '🔄',
        title: 'Confirm Sales Return & Restitution',
        subtitle: 'Please verify the refund restitution and inventory effect:',
        borderColor: '#f59e0b',
        items: popupItems,
        impact: {
            text: currentLoadedSale.isSupplied 
                ? '📦 RESTOCK EFFECT: Returned physical items will be restored to branch shelf counts.'
                : '⏳ ZERO RESTOCK GUARANTEE: Unsupplied orders never left store shelves. Zero units will be added to physical stock; reservation allocation will be safely cancelled and money refunded.',
            type: currentLoadedSale.isSupplied ? 'warning' : 'info'
        },
        confirmText: '✓ Yes, Process Return & Restitution',
        confirmClass: 'btn-warning',
        form: form
    });
}
</script>
@endpush
