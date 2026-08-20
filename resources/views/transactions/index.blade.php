@extends('layouts.app')

@section('title', 'Transactions History')

@push('styles')
<style>
    .filter-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

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

    .summary-card h4 { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem; }
    .summary-card .val { font-size: 1.35rem; font-weight: 800; }

    .table-wrap {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow-x: auto;
    }

    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: rgba(11, 15, 25, 0.8); padding: 1rem 1.25rem; font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border); }
    td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(55, 65, 81, 0.25); }

    .date-pill {
        padding: 0.4rem 0.85rem;
        border-radius: 99px;
        font-size: 0.8rem;
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
</style>
@endpush

@section('content')

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 800;">Transactions & Sales Audit Trail 📑</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Detailed history of all POS sales, part-payments, physical handovers, and customer debts.
            </p>
        </div>
        <a href="{{ route('pos.index') }}" class="btn btn-success btn-lg">
            💰 New POS Sale
        </a>
    </div>

    <!-- Accurate Filter Bar -->
    <div class="filter-card">
        <form method="GET" action="{{ route('transactions.index') }}" id="filterForm">
            <!-- Preset Date Pills -->
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Quick Dates:</span>
                <a href="{{ route('transactions.index', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'ALL'])) }}" class="date-pill {{ $datePreset === 'ALL' && !request('from_date') ? 'active' : '' }}">All Time</a>
                <a href="{{ route('transactions.index', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'TODAY'])) }}" class="date-pill {{ $datePreset === 'TODAY' ? 'active' : '' }}">Today</a>
                <a href="{{ route('transactions.index', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'YESTERDAY'])) }}" class="date-pill {{ $datePreset === 'YESTERDAY' ? 'active' : '' }}">Yesterday</a>
                <a href="{{ route('transactions.index', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'THIS_WEEK'])) }}" class="date-pill {{ $datePreset === 'THIS_WEEK' ? 'active' : '' }}">This Week</a>
                <a href="{{ route('transactions.index', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'THIS_MONTH'])) }}" class="date-pill {{ $datePreset === 'THIS_MONTH' ? 'active' : '' }}">This Month</a>
            </div>

            <div class="grid-4" style="gap: 0.75rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Payment Status</label>
                    <select name="payment_status">
                        <option value="">-- All Payments --</option>
                        <option value="PAID" {{ request('payment_status') === 'PAID' ? 'selected' : '' }}>✓ Paid (Full Payment)</option>
                        <option value="PART_PAID" {{ in_array(request('payment_status'), ['PART_PAID', 'PARTIAL']) ? 'selected' : '' }}>💳 Part-Paid (Part Payment)</option>
                        <option value="NOT_PAID" {{ in_array(request('payment_status'), ['NOT_PAID', 'UNPAID']) ? 'selected' : '' }}>🔴 Not Paid (Full Credit)</option>
                        <option value="DEBT" {{ request('payment_status') === 'DEBT' ? 'selected' : '' }}>🤝 All Debtors (Part-Paid & Not Paid)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Goods Delivery</label>
                    <select name="delivery_status">
                        <option value="">-- All Deliveries --</option>
                        <option value="SUPPLIED" {{ in_array(request('delivery_status'), ['DELIVERED', 'SUPPLIED']) ? 'selected' : '' }}>✓ Supplied (Handed Over)</option>
                        <option value="NOT_SUPPLIED" {{ in_array(request('delivery_status'), ['UNSUPPLIED', 'NOT_SUPPLIED', 'PENDING']) ? 'selected' : '' }}>⏳ Not Supplied (In Shop / Pickup)</option>
                        <option value="PAID_SUPPLIED" {{ request('delivery_status') === 'PAID_SUPPLIED' ? 'selected' : '' }}>🟢 Paid & Supplied</option>
                        <option value="PAID_NOT_SUPPLIED" {{ request('delivery_status') === 'PAID_NOT_SUPPLIED' ? 'selected' : '' }}>🟠 Paid & Not Supplied</option>
                        <option value="PART_PAID_SUPPLIED" {{ request('delivery_status') === 'PART_PAID_SUPPLIED' ? 'selected' : '' }}>⚠️ Part-Paid & Supplied</option>
                        <option value="PART_PAID_NOT_SUPPLIED" {{ request('delivery_status') === 'PART_PAID_NOT_SUPPLIED' ? 'selected' : '' }}>⏳ Part-Paid & Not Supplied</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 1rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 260px;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="🔍 Search by Invoice ID, Customer Name, Phone...">
                </div>

                <div style="width: 220px;">
                    <select name="user_name">
                        <option value="">-- All Cashiers / Staff --</option>
                        @foreach($cashiers as $cName)
                            <option value="{{ $cName }}" {{ request('user_name') === $cName ? 'selected' : '' }}>{{ $cName }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                    🔍 Apply Filters
                </button>

                <a href="{{ route('transactions.index') }}" class="btn btn-secondary" style="padding: 0.75rem 1.25rem;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Summary Metrics for Filtered Query -->
    <div class="summary-grid">
        <div class="summary-card">
            <h4>Total Invoices</h4>
            <div class="val" style="color: #60a5fa;">{{ number_format($totalSalesCount) }}</div>
        </div>
        <div class="summary-card">
            <h4>Total Gross Sales</h4>
            <div class="val" style="color: #f9fafb;">₦{{ number_format($totalRevenue, 0) }}</div>
        </div>
        <div class="summary-card">
            <h4>Cash / POS Collected</h4>
            <div class="val" style="color: #4ade80;">₦{{ number_format($totalPaid, 0) }}</div>
        </div>
        <div class="summary-card">
            <h4>Outstanding Debts</h4>
            <div class="val" style="color: #f87171;">₦{{ number_format($totalDebt, 0) }}</div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Invoice Ref</th>
                        <th>Customer</th>
                        <th>Items Count</th>
                        <th>Total Amount</th>
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
                        <td>
                            <strong style="color: #93c5fd;">#{{ substr($sale->id, 0, 8) }}</strong>
                        </td>
                        <td>
                            <strong>{{ $sale->customerName ?: 'Walk-in Customer' }}</strong>
                            @if($sale->customerPhone)
                                <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $sale->customerPhone }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-info">{{ count($sale->items ?? []) }} items</span>
                        </td>
                        <td style="font-weight: 800; font-size: 1rem; color: #f9fafb;">
                            ₦{{ number_format($sale->totalAmount, 0) }}
                        </td>
                        <td style="font-weight: 700; color: #4ade80;">
                            ₦{{ number_format($sale->paidAmount, 0) }}
                        </td>
                        <td>
                            @if($sale->paidAmount >= $sale->totalAmount)
                                <span class="badge badge-success">✓ Paid</span>
                            @elseif($sale->paidAmount > 0)
                                <span class="badge badge-warning" style="background: #fef3c7; color: #b45309; border: 1px solid #fcd34d;">💳 Part-Paid (Owes ₦{{ number_format($balance, 0) }})</span>
                            @else
                                <span class="badge badge-danger">🔴 Not Paid (Owes ₦{{ number_format($balance, 0) }})</span>
                            @endif
                        </td>
                        <td>
                            @if($isSupplied)
                                <span class="badge badge-success">✓ Supplied</span>
                            @else
                                <span class="badge badge-warning" style="background: #fef3c7; color: #b45309; border: 1px solid #fcd34d;">⏳ Not Supplied</span>
                            @endif
                        </td>
                        <td style="font-size: 0.85rem; color: #cbd5e1;">
                            {{ $sale->userName ?: 'Cashier' }}
                        </td>
                        <td style="white-space: nowrap;">
                            <a href="{{ route('pos.receipt', $sale->id) }}" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" target="_blank">
                                🧾 Receipt
                            </a>
                            <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewSaleDetails({{ json_encode($sale) }})">
                                🔍 Details
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No transactions matched the selected filters.
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

    <!-- Modal: View Sale Details -->
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
                    <strong id="dtlTotal" style="font-size: 1.15rem; color: #f9fafb;"></strong>
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

@endsection

@push('scripts')
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function viewSaleDetails(sale) {
    document.getElementById('dtlInvoiceTitle').textContent = 'Sale #' + sale.id.substring(0, 8);
    document.getElementById('dtlDate').textContent = new Date(sale.createdAt).toLocaleString();
    document.getElementById('dtlCustomer').textContent = sale.customerName || 'Walk-in Customer';
    document.getElementById('dtlCashier').textContent = sale.userName || 'Cashier';
    const isSupplied = (sale.deliveryStatus === 'DELIVERED' || sale.deliveryStatus === 'SUPPLIED');
    document.getElementById('dtlDelivery').textContent = isSupplied ? '✓ Supplied to Customer' : '⏳ Awaiting Pickup';
    document.getElementById('dtlReceiptBtn').href = '/pos/receipt/' + sale.id;

    let itemsHtml = '';
    (sale.items || []).forEach(item => {
        const subtotal = item.quantity * item.unitPrice;
        itemsHtml += `
        <div style="background:rgba(15,23,42,0.6);border:1px solid var(--border);border-radius:10px;padding:0.6rem 0.85rem;margin-bottom:0.4rem;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <strong style="font-size:0.9rem;">${item.productName}</strong>
                <div style="font-size:0.75rem;color:#9ca3af;">₦${Number(item.unitPrice).toLocaleString()} × ${item.quantity} units</div>
            </div>
            <strong style="font-size:0.95rem;color:#4ade80;">₦${subtotal.toLocaleString()}</strong>
        </div>
        `;
    });

    document.getElementById('dtlItemsList').innerHTML = itemsHtml;
    document.getElementById('dtlTotal').textContent = '₦' + Number(sale.totalAmount).toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('dtlPaid').textContent = '₦' + Number(sale.paidAmount).toLocaleString('en-US', { minimumFractionDigits: 2 });

    const debt = Math.max(0, sale.totalAmount - sale.paidAmount);
    document.getElementById('dtlDebt').textContent = '₦' + debt.toLocaleString('en-US', { minimumFractionDigits: 2 });

    openModal('modalSaleDetails');
}
</script>
@endpush
