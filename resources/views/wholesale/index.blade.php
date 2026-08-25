@extends('layouts.app')

@section('title', 'Wholesale Management & Office Pricing Hub')

@push('styles')
<style>
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .kpi-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1.15rem 1.25rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .kpi-card h4 {
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 0.4rem;
        letter-spacing: 0.03em;
    }

    .kpi-card .val {
        font-size: 1.45rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .filter-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
        align-items: flex-end;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 800;
    }

    .status-pending {
        background: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
        border: 1px solid rgba(245, 158, 11, 0.35);
    }

    .status-paid {
        background: rgba(34, 197, 94, 0.15);
        color: #4ade80;
        border: 1px solid rgba(34, 197, 94, 0.35);
    }

    .status-debt {
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.35);
    }

    .status-partial {
        background: rgba(139, 92, 246, 0.15);
        color: #c084fc;
        border: 1px solid rgba(139, 92, 246, 0.35);
    }

    /* Modal Backdrop & Dialog */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.82);
        backdrop-filter: blur(6px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1050;
        padding: 1rem;
    }

    .modal-content-box {
        background: #0f172a;
        border: 1px solid #334155;
        border-radius: 18px;
        width: 100%;
        max-width: 680px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 1.75rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
    }

    .pricing-table th, .pricing-table td {
        padding: 0.6rem 0.75rem;
        border-bottom: 1px solid #1e293b;
        font-size: 0.85rem;
    }
</style>
@endpush

@section('content')

<!-- Top Header with Executive Title -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 1.6rem; font-weight: 800; display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.2rem;">
            <span>📦 Wholesale Operations & Office Pricing Hub</span>
        </h1>
        <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
            Review floor dispatches, apply confidential negotiated prices, reconcile bank settlements, and issue commercial invoices.
        </p>
    </div>

    <div style="display: flex; gap: 0.5rem;">
        <a href="{{ route('pos.index') }}" class="btn btn-primary" style="font-size: 0.85rem; font-weight: 700;">
            ➕ New Floor Dispatch
        </a>
    </div>
</div>

@if(session('success'))
<div style="background: rgba(34,197,94,0.15); border: 1px solid #22c55e; color: #4ade80; padding: 0.85rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 700;">
    {{ session('success') }}
</div>
@endif

@if(isset($errors) && $errors->any())
<div style="background: rgba(239,68,68,0.15); border: 1px solid #ef4444; color: #f87171; padding: 0.85rem 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 700;">
    {{ $errors->first() }}
</div>
@endif

<!-- Executive KPI Summary Radar -->
<div class="kpi-grid">
    <div class="kpi-card">
        <h4>Total Wholesale Orders</h4>
        <div class="val" style="color: #93c5fd;">{{ number_format($totalDispatches) }}</div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;">Dispatches issued from shop floor</div>
    </div>

    <div class="kpi-card" style="border-color: rgba(245,158,11,0.4);">
        <h4>Pending Office Pricing</h4>
        <div class="val" style="color: #fbbf24;">{{ number_format($pendingPricingCount) }}</div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;">Awaiting Madam's private price entry</div>
    </div>

    <div class="kpi-card">
        <h4>Total Invoiced Value</h4>
        <div class="val" style="color: #4ade80;">₦{{ number_format($totalInvoicedValue, 0) }}</div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;">Billed wholesale revenue</div>
    </div>

    <div class="kpi-card">
        <h4>Settled Bank Collections</h4>
        <div class="val" style="color: #60a5fa;">₦{{ number_format($totalSettledValue, 0) }}</div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;">Cash & bank transfers received</div>
    </div>

    <div class="kpi-card" style="border-color: rgba(239,68,68,0.4);">
        <h4>Open Wholesale Receivables</h4>
        <div class="val" style="color: #f87171;">₦{{ number_format($totalWholesaleDebt, 0) }}</div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;">Outstanding wholesale credit debt</div>
    </div>
</div>

<!-- Interactive Search & Filtering Toolbar -->
<div class="filter-card">
    <form method="GET" action="{{ route('wholesale.index') }}" class="filter-grid">
        <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 0.75rem;">Wholesaler / Ref Search</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search client name, phone or ref...">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 0.75rem;">Pricing & Billing Status</label>
            <select name="pricing_status">
                <option value="ALL" {{ $pricingStatus === 'ALL' ? 'selected' : '' }}>-- All Wholesale Orders --</option>
                <option value="PENDING_PRICING" {{ $pricingStatus === 'PENDING_PRICING' ? 'selected' : '' }}>⏳ Pending Office Pricing (Unpriced)</option>
                <option value="PRICED_PAID" {{ $pricingStatus === 'PRICED_PAID' ? 'selected' : '' }}>🟢 Priced & Paid in Full</option>
                <option value="PRICED_DEBT" {{ $pricingStatus === 'PRICED_DEBT' ? 'selected' : '' }}>⚠️ Priced on Credit / Part-Paid</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 0.75rem;">Date Timeline</label>
            <select name="date_preset" id="datePresetSelect" onchange="toggleCustomDates(this.value)">
                <option value="ALL" {{ $datePreset === 'ALL' ? 'selected' : '' }}>All Time</option>
                <option value="TODAY" {{ $datePreset === 'TODAY' ? 'selected' : '' }}>Today</option>
                <option value="YESTERDAY" {{ $datePreset === 'YESTERDAY' ? 'selected' : '' }}>Yesterday</option>
                <option value="THIS_WEEK" {{ $datePreset === 'THIS_WEEK' ? 'selected' : '' }}>This Week</option>
                <option value="THIS_MONTH" {{ $datePreset === 'THIS_MONTH' ? 'selected' : '' }}>This Month</option>
                <option value="CUSTOM" {{ $datePreset === 'CUSTOM' ? 'selected' : '' }}>Custom Date Range</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 0.75rem;">Branch / Warehouse</label>
            <select name="warehouse_id">
                <option value="">-- All Branches --</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                @endforeach
            </select>
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.55rem 1rem; font-size: 0.85rem; font-weight: 700;">
                ⚡ Apply Filters
            </button>
            <a href="{{ route('wholesale.index') }}" class="btn btn-secondary" style="padding: 0.55rem 0.85rem; font-size: 0.85rem;">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Dispatches Master Table -->
<div class="table-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Invoice Ref</th>
                    <th>Wholesaler Client</th>
                    <th>Physical Units</th>
                    <th>Invoiced Amount</th>
                    <th>Settlement Status</th>
                    <th>Attendant</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dispatches as $d)
                @php
                    $isPending = ($d->totalAmount <= 0);
                    $debtBal = max(0, $d->totalAmount - $d->paidAmount);
                    $cust = $d->customer;
                    $totalUnits = $d->items->sum('quantity');
                @endphp
                <tr>
                    <td style="font-size: 0.82rem; color: var(--text-muted); white-space: nowrap;">
                        {{ date('d M Y, h:i A', strtotime($d->createdAt)) }}
                    </td>
                    <td>
                        <strong style="color: #c084fc; font-family: monospace; font-size: 0.95rem;">
                            #{{ substr($d->id, 0, 8) }}
                        </strong>
                    </td>
                    <td>
                        <div style="font-weight: 700; color: #f8fafc;">{{ $d->customerName }}</div>
                        @if($cust && $cust->phone)
                            <div style="font-size: 0.75rem; color: #94a3b8;">{{ $cust->phone }} @if($cust->customer_code) <span style="color: #60a5fa;">[{{ $cust->customer_code }}]</span> @endif</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-info" style="font-size: 0.82rem; font-weight: 800;">
                            {{ $totalUnits }} units
                        </span>
                    </td>
                    <td>
                        @if($isPending)
                            <span class="status-pill status-pending">
                                ⏳ Pending Pricing
                            </span>
                        @else
                            <strong style="color: #4ade80; font-size: 1rem;">
                                ₦{{ number_format($d->totalAmount, 0) }}
                            </strong>
                        @endif
                    </td>
                    <td>
                        @if($isPending)
                            <span style="font-size: 0.78rem; color: #94a3b8;">Not Invoiced</span>
                        @elseif($d->paidAmount >= $d->totalAmount)
                            <span class="status-pill status-paid">
                                ✓ Paid in Full
                            </span>
                        @elseif($d->paidAmount > 0)
                            <span class="status-pill status-partial">
                                💳 Part-Paid (Owes ₦{{ number_format($debtBal, 0) }})
                            </span>
                        @else
                            <span class="status-pill status-debt">
                                🔴 Debt (Owes ₦{{ number_format($debtBal, 0) }})
                            </span>
                        @endif
                    </td>
                    <td style="font-size: 0.85rem; color: #cbd5e1;">
                        {{ $d->userName ?: 'Floor Attendant' }}
                    </td>
                    <td style="text-align: right;">
                        <div style="display: inline-flex; gap: 0.4rem;">
                            @if(Auth::user()->role !== 'viewer')
                            <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; font-weight: 700;" onclick="openPriceModal({{ json_encode($d) }})">
                                ✏️ {{ $isPending ? 'Price & Bill' : 'Re-Price / Settle' }}
                            </button>
                            @endif

                            @if(!$isPending)
                            <a href="{{ route('wholesale.invoice', $d->id) }}" target="_blank" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem; border-color: #3b82f6; color: #93c5fd;" title="Print Official Commercial Invoice">
                                📄 Invoice
                            </a>
                            @endif

                            <a href="{{ route('pos.receipt', $d->id) }}" target="_blank" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.78rem;" title="View Original Delivery Waybill">
                                📦 Waybill
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        No wholesale dispatches found matching your search.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.25rem;">
        {{ $dispatches->links() }}
    </div>
</div>

<!-- Modal: Live Office Pricing & Reconciliation Engine -->
<div class="modal-overlay" id="modalPriceOrder">
    <div class="modal-content-box">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem; border-bottom: 1px solid #334155; padding-bottom: 0.75rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #c084fc;">
                    ✏️ Price & Reconcile Wholesale Order
                </h3>
                <p style="margin: 0.25rem 0 0; font-size: 0.8rem; color: var(--text-muted);" id="modalWholesaleCustTitle">
                    Wholesaler: Alhaji Musa Wholesale Ltd · Ref #56209D02
                </p>
            </div>
            <button type="button" onclick="closePriceModal()" style="background: transparent; border: none; font-size: 1.4rem; color: #94a3b8; cursor: pointer;">
                ✕
            </button>
        </div>

        <form id="priceOrderForm" method="POST" action="">
            @csrf

            <div style="background: #0b0f19; border: 1px solid #1e293b; border-radius: 12px; padding: 0.75rem; margin-bottom: 1rem;">
                <label style="font-size: 0.78rem; font-weight: 800; color: #93c5fd; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">
                    📋 Line Items Dispatched from Shop Floor
                </label>
                <table class="pricing-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="color: #64748b; font-size: 0.72rem; text-transform: uppercase; text-align: left;">
                            <th>Item Description</th>
                            <th>Qty</th>
                            <th style="width: 130px;">Agreed Price (₦)</th>
                            <th style="text-align: right;">Subtotal (₦)</th>
                        </tr>
                    </thead>
                    <tbody id="modalPricingItemsBody">
                        <!-- Rendered via JS -->
                    </tbody>
                </table>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed #334155;">
                    <span style="font-weight: 700; color: #cbd5e1;">Total Invoiced Bill:</span>
                    <strong style="font-size: 1.35rem; color: #4ade80;" id="modalDisplayCalculatedTotal">₦0.00</strong>
                </div>
            </div>

            <!-- Financial Settlement Section -->
            <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(59,130,246,0.3); border-radius: 12px; padding: 0.85rem; margin-bottom: 1rem;">
                <label style="font-size: 0.78rem; font-weight: 800; color: #93c5fd; text-transform: uppercase; margin-bottom: 0.5rem; display: block;">
                    💳 Financial Settlement & Payment Status
                </label>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.72rem;">Settlement Term</label>
                        <select name="payment_status" id="modalPaymentStatusSelect" onchange="onModalPaymentStatusChange(this.value)">
                            <option value="PAID">🟢 Paid in Full (Bank Transfer / Cash)</option>
                            <option value="DEBT">🔴 Unpaid / Billed to Debt Ledger</option>
                            <option value="PARTIAL">⚠️ Part-Paid (Deposit + Debt Balance)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.72rem;">Payment Method</label>
                        <select name="payment_method" id="modalPaymentMethodSelect">
                            <option value="TRANSFER">🏦 Direct Bank Transfer</option>
                            <option value="CASH">💵 Cash</option>
                            <option value="POS">💳 POS Terminal / Card</option>
                        </select>
                    </div>
                </div>

                <div id="modalPartPayContainer" style="display: none; margin-bottom: 0.75rem; background: rgba(139,92,246,0.1); padding: 0.65rem; border-radius: 8px; border: 1px solid rgba(139,92,246,0.3);">
                    <label style="font-size: 0.72rem; color: #c084fc;">Amount Received Now (₦):</label>
                    <input type="number" step="any" min="0" name="paid_amount" id="modalPartPayInput" placeholder="e.g. 100000" onkeyup="recalcWholesaleTotal()">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.72rem;">Payment Reference / Bank Alert ID</label>
                        <input type="text" name="reference_no" placeholder="e.g. GTB-TRF-982341 (Optional)">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.72rem;">Office Notes / Agreement Terms</label>
                        <input type="text" name="notes" placeholder="e.g. 5% volume discount applied">
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closePriceModal()" style="padding: 0.65rem 1.25rem;">
                    Cancel
                </button>
                <button type="submit" class="btn btn-success" style="padding: 0.65rem 1.5rem; font-weight: 800;">
                    💾 Save Price & Reconcile
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
let currentModalSale = null;

function openPriceModal(sale) {
    currentModalSale = sale;
    const form = document.getElementById('priceOrderForm');
    form.action = `/wholesale/price/${sale.id}`;

    document.getElementById('modalWholesaleCustTitle').textContent = `Wholesaler: ${sale.customerName} · Ref #${sale.id.substring(0, 8)}`;

    const tbody = document.getElementById('modalPricingItemsBody');
    tbody.innerHTML = '';

    sale.items.forEach((item, index) => {
        const tr = document.createElement('tr');
        const unitPriceVal = (item.unitPrice > 0) ? item.unitPrice : '';

        tr.innerHTML = `
            <td>
                <input type="hidden" name="items[${index}][id]" value="${item.id}">
                <div style="font-weight: 700; color: #f8fafc;">${item.productName}</div>
                <div style="font-size: 0.72rem; color: #94a3b8;">${item.code || item.productCode || 'SKU'}</div>
            </td>
            <td>
                <span style="font-weight: 800; color: #60a5fa;">${item.quantity}</span>
            </td>
            <td>
                <input type="number" step="any" min="0" name="items[${index}][unit_price]" 
                       value="${unitPriceVal}" 
                       placeholder="₦ Price" 
                       style="width: 100%; padding: 0.35rem 0.5rem; font-size: 0.85rem; font-weight: 700; color: #4ade80; background: #000; border: 1px solid #475569; border-radius: 6px;" 
                       oninput="recalcWholesaleTotal()" required>
            </td>
            <td style="text-align: right; font-weight: 700; color: #f8fafc;" class="item-subtotal-cell">
                ₦0
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Set default payment mode
    const statusSel = document.getElementById('modalPaymentStatusSelect');
    if (sale.totalAmount > 0) {
        if (sale.paidAmount >= sale.totalAmount) {
            statusSel.value = 'PAID';
        } else if (sale.paidAmount > 0) {
            statusSel.value = 'PARTIAL';
            document.getElementById('modalPartPayInput').value = sale.paidAmount;
        } else {
            statusSel.value = 'DEBT';
        }
    } else {
        statusSel.value = 'PAID';
    }

    onModalPaymentStatusChange(statusSel.value);
    recalcWholesaleTotal();

    document.getElementById('modalPriceOrder').style.display = 'flex';
}

function closePriceModal() {
    document.getElementById('modalPriceOrder').style.display = 'none';
}

function onModalPaymentStatusChange(status) {
    const partPayBox = document.getElementById('modalPartPayContainer');
    const methodSel = document.getElementById('modalPaymentMethodSelect');

    if (status === 'PARTIAL') {
        partPayBox.style.display = 'block';
        methodSel.disabled = false;
    } else if (status === 'DEBT') {
        partPayBox.style.display = 'none';
        methodSel.disabled = true;
    } else {
        partPayBox.style.display = 'none';
        methodSel.disabled = false;
    }
}

function recalcWholesaleTotal() {
    let total = 0;
    const tbody = document.getElementById('modalPricingItemsBody');
    const rows = tbody.querySelectorAll('tr');

    rows.forEach((row, idx) => {
        const qty = currentModalSale.items[idx].quantity;
        const priceInput = row.querySelector('input[type="number"]');
        const price = parseFloat(priceInput ? priceInput.value : 0) || 0;
        const subtotal = qty * price;
        total += subtotal;

        const subtotalCell = row.querySelector('.item-subtotal-cell');
        if (subtotalCell) {
            subtotalCell.textContent = '₦' + Math.round(subtotal).toLocaleString('en-US');
        }
    });

    document.getElementById('modalDisplayCalculatedTotal').textContent = '₦' + Math.round(total).toLocaleString('en-US');
}
</script>
@endpush
