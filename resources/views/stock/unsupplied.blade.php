@extends('layouts.app')

@section('title', 'Goods Sold Awaiting Pickup')

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

    .unsupplied-card {
        background: var(--card-bg);
        border: 2px solid rgba(217,119,6,0.3);
        border-radius: 18px;
        padding: 1.5rem;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1.25rem;
    }
</style>
@endpush

@section('content')

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 1.75rem;">⏳</span>
                <h2 style="font-size: 1.5rem; font-weight: 800;">Goods Sold & Not Supplied (Pickup Queue)</h2>
            </div>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Items purchased and awaiting customer pickup in <strong style="color: #60a5fa;">{{ $activeWarehouse->name ?? 'Main Shop' }}</strong>. Hand them over when customer arrives!
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="{{ route('pos.index') }}" class="btn btn-primary">
                💰 Back to POS
            </a>
            <a href="{{ route('stock.index') }}" class="btn btn-secondary">
                📦 Stock Hub
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <h4>Orders Awaiting Pickup</h4>
            <div class="val" style="color: #fbbf24;">{{ number_format($totalUnsuppliedOrders) }} orders</div>
        </div>
        <div class="summary-card">
            <h4>Total Reserved Value</h4>
            <div class="val" style="color: #4ade80;">₦{{ number_format($totalUnsuppliedValue, 0) }}</div>
        </div>
    </div>

    <!-- Multi-Criteria Filter Card -->
    <div class="filter-card">
        <form method="GET" action="{{ route('stock.unsupplied') }}">
            <!-- Quick Date Pills -->
            <div style="display: flex; gap: 0.4rem; margin-bottom: 0.85rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Quick Dates:</span>
                <a href="{{ route('stock.unsupplied', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'ALL'])) }}" class="date-pill {{ $datePreset === 'ALL' && !request('from_date') ? 'active' : '' }}">All Time</a>
                <a href="{{ route('stock.unsupplied', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'TODAY'])) }}" class="date-pill {{ $datePreset === 'TODAY' ? 'active' : '' }}">Today</a>
                <a href="{{ route('stock.unsupplied', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'YESTERDAY'])) }}" class="date-pill {{ $datePreset === 'YESTERDAY' ? 'active' : '' }}">Yesterday</a>
                <a href="{{ route('stock.unsupplied', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'THIS_WEEK'])) }}" class="date-pill {{ $datePreset === 'THIS_WEEK' ? 'active' : '' }}">This Week</a>
                <a href="{{ route('stock.unsupplied', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'THIS_MONTH'])) }}" class="date-pill {{ $datePreset === 'THIS_MONTH' ? 'active' : '' }}">This Month</a>
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
                    <label style="font-size: 0.75rem;">Payment State</label>
                    <select name="payment_status">
                        <option value="">-- All Payment States --</option>
                        <option value="PAID" {{ request('payment_status') === 'PAID' ? 'selected' : '' }}>🟢 Paid (Fully Paid)</option>
                        <option value="PARTIAL" {{ request('payment_status') === 'PARTIAL' ? 'selected' : '' }}>⚠️ Part-Paid</option>
                        <option value="UNPAID" {{ request('payment_status') === 'UNPAID' ? 'selected' : '' }}>🔴 Unpaid / Full Debt</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 0.85rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="🔍 Search by Invoice #, Customer Name, Phone...">
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    🔍 Apply Filters
                </button>

                <a href="{{ route('stock.unsupplied') }}" class="btn btn-secondary" style="padding: 0.65rem 1rem;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Orders List -->
    <div id="unsuppliedOrdersContainer">
        @forelse($unsuppliedSales as $sale)
        <div class="unsupplied-card order-item-card">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                    <span style="font-size: 1.25rem; font-weight: 800; color: #f8fafc;">
                        Customer: {{ $sale->customerName ?: 'Walk-in Customer' }}
                    </span>
                    <span class="badge badge-warning">⏳ Not Supplied (Awaiting Pickup)</span>
                    @if($sale->paidAmount >= $sale->totalAmount)
                        <span class="badge badge-success">✓ Fully Paid</span>
                    @elseif($sale->paidAmount > 0)
                        <span class="badge badge-warning">💳 Part-Paid</span>
                    @else
                        <span class="badge badge-danger">🔴 Unpaid</span>
                    @endif
                </div>

                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                    Invoice <strong style="color: #93c5fd;">#{{ substr($sale->id, 0, 8) }}</strong> · Purchased on: {{ date('d M Y, h:i A', strtotime($sale->createdAt)) }}
                    @if($sale->customerPhone) · Tel: {{ $sale->customerPhone }} @endif
                </div>

                <!-- Items list -->
                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 10px; padding: 0.75rem 1rem;">
                    <strong style="font-size: 0.8rem; color: #94a3b8; text-transform: uppercase;">Items Reserved on Shelf:</strong>
                    <ul style="list-style: none; margin-top: 0.35rem; display: flex; flex-direction: column; gap: 0.35rem;">
                        @foreach($sale->items as $item)
                        <li style="font-size: 0.9rem; color: #e2e8f0;">
                            • <strong style="color: #60a5fa;">{{ $item->product->code ?? $item->productName }}</strong> — <span style="color: #fbbf24; font-weight: 700;">{{ $item->quantity }} units</span> (₦{{ number_format($item->unitPrice, 0) }}/ea)
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div style="text-align: right;">
                <div style="font-size: 1.35rem; font-weight: 800; color: #4ade80; margin-bottom: 0.75rem;">
                    ₦{{ number_format($sale->totalAmount, 0) }}
                </div>

                @if(auth()->user()?->role !== 'viewer')
                    <form id="dispatchForm_{{ $sale->id }}" method="POST" action="{{ route('stock.dispatch', $sale->id) }}">
                        @csrf
                        <button type="button" class="btn btn-success btn-lg" onclick="confirmDispatchOrder('{{ $sale->id }}', '{{ addslashes($sale->customerName ?: 'Customer') }}', '{{ $sale->items->sum('quantity') }}', '₦{{ number_format($sale->totalAmount, 0) }}')">
                            📦 Mark as Supplied (Handover Goods)
                        </button>
                    </form>
                @else
                    <span style="font-size: 0.85rem; font-weight: 800; color: #facc15; background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.4); padding: 0.6rem 1.2rem; border-radius: 10px;">
                        👑 Awaiting Customer Pickup
                    </span>
                @endif
            </div>
        </div>
        @empty
        <div style="text-align: center; padding: 4rem 1rem; background: var(--card-bg); border-radius: 20px;">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🎉</div>
            <h3 style="font-size: 1.3rem; font-weight: 800;">No Pending Pickup Orders!</h3>
            <p style="color: var(--text-muted); margin-top: 0.35rem;">
                There are currently zero Not Supplied customer orders matching your filters.
            </p>
        </div>
        @endforelse
    </div>

    <div style="margin-top: 1.25rem;">
        {{ $unsuppliedSales->links() }}
    </div>

@endsection

@push('scripts')
<script>
function confirmDispatchOrder(saleId, customerName, totalUnits, totalVal) {
    const form = document.getElementById('dispatchForm_' + saleId);

    showConfirmPopup({
        icon: '📦',
        title: 'Confirm Physical Handover',
        subtitle: 'Authorize handover of goods physically leaving the shop:',
        borderColor: '#22c55e',
        items: [
            { label: 'Customer', value: customerName, color: '#f8fafc' },
            { label: 'Total Units Handed Over', value: totalUnits + ' units', color: '#4ade80', size: '1.1rem' },
            { label: 'Order Value', value: totalVal, color: '#60a5fa' }
        ],
        impact: {
            text: '📉 PHYSICAL DEDUCTION: This action officially records the handover, updates physical closing stock, and removes the order from unsupplied queue.',
            type: 'warning'
        },
        confirmText: '📦 Yes, Confirm Handover',
        confirmClass: 'btn-success',
        form: form
    });
}
</script>
@endpush
