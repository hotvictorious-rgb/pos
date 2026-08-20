@extends('layouts.app')

@section('title', 'Sales Returns & Refunds')

@push('styles')
<style>
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
            <h2 style="font-size: 1.5rem; font-weight: 800;">Sales Returns & Customer Refunds 🔄</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Accept customer returns, restore physical stock to shelves, and refund cash or adjust debt.
            </p>
        </div>
        <button class="btn btn-warning btn-lg" onclick="openModal('modalProcessReturn')">
            🔄 Process New Return
        </button>
    </div>

    <!-- Info Box on Returns -->
    <div style="background: rgba(37,99,235,0.1); border: 1px solid rgba(37,99,235,0.3); border-radius: 16px; padding: 1.25rem; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="font-size: 1.75rem;">🛡️</div>
            <div style="font-size: 0.85rem; color: #cbd5e1;">
                <strong>Auditor Anti-Theft Policy on Returns:</strong> Every returned item is automatically added back to **Physical Closing Stock** count and logged with staff attribution to prevent fake return write-offs.
            </div>
        </div>
    </div>

    <!-- Recent Returns Table -->
    <div class="card">
        <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem;">
            Recent Processed Returns (Audit Trail)
        </h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Return Ref</th>
                        <th>Original Sale</th>
                        <th>Customer</th>
                        <th>Product Returned</th>
                        <th style="color: #f87171;">Refund Amount</th>
                        <th>Reason</th>
                        <th>Staff</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentReturns as $ret)
                    <tr>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            {{ date('d M Y, h:i A', strtotime($ret->created_at ?? $ret->createdAt)) }}
                        </td>
                        <td><span class="badge badge-warning">{{ $ret->code }}</span></td>
                        <td>#{{ substr($ret->saleId, 0, 8) }}</td>
                        <td><strong>{{ $ret->customerName ?? 'Customer' }}</strong></td>
                        <td>{{ $ret->productName }} ({{ $ret->quantity }} items)</td>
                        <td style="font-weight: 800; color: #f87171;">
                            ₦{{ number_format($ret->refundAmount, 2) }}
                        </td>
                        <td>{{ $ret->reason }}</td>
                        <td>{{ $ret->userName }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No sales returns recorded yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Process Return -->
    <div id="modalProcessReturn" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 620px;">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">🔄 Process Customer Return</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Select the past sale invoice and specify items returned.
            </p>

            <form method="POST" action="{{ route('pos.returns.process') }}">
                @csrf

                <div class="form-group">
                    <label>Select Original Sale Invoice</label>
                    <select name="sale_id" id="returnSaleSelect" required onchange="loadSaleItems(this)">
                        <option value="">-- Choose Sale Invoice --</option>
                        @foreach($sales as $s)
                            <option value="{{ $s->id }}" data-items="{{ json_encode($s->items) }}">
                                Sale #{{ substr($s->id, 0, 8) }} — {{ $s->customerName }} (₦{{ number_format($s->totalAmount, 2) }}) on {{ date('d/m/Y', strtotime($s->createdAt)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Receiving Branch Shop</label>
                    <select name="warehouse_id" required>
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
                    <select name="refund_method" required>
                        <option value="CASH_REFUND">💵 Cash Refund to Customer</option>
                        <option value="DEBT_REDUCTION">💳 Reduce Customer Debt Balance</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Reason for Return</label>
                    <select name="reason" required>
                        <option value="Defective or Damaged packaging">Defective or Damaged packaging</option>
                        <option value="Wrong product delivered">Wrong product delivered</option>
                        <option value="Customer changed mind / Exchange">Customer changed mind / Exchange</option>
                        <option value="Expired date discovered">Expired date discovered</option>
                    </select>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalProcessReturn')">Cancel</button>
                    <button type="submit" class="btn btn-warning" style="flex: 1;">✓ Process Return & Restock</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

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
                <div style="font-size:0.75rem;color:#9ca3af;">Bought ${item.quantity} units @ ₦${item.unitPrice.toLocaleString()}</div>
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
</script>
@endpush
