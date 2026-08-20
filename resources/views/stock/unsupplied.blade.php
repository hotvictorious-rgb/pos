@extends('layouts.app')

@section('title', 'Goods Sold Awaiting Pickup')

@push('styles')
<style>
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

    .unsupplied-card.urgent {
        border-color: rgba(220,38,38,0.4);
        background: rgba(220,38,38,0.05);
    }
</style>
@endpush

@section('content')

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 800;">Goods Sold Awaiting Pickup ⏳</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                These items have been purchased, but are **still physically in the shop**. Hand them over when customer arrives!
            </p>
        </div>
        <a href="{{ route('pos.index') }}" class="btn btn-primary">
            💰 Back to POS
        </a>
    </div>

    <!-- Info Box on Physical Closing Stock Law -->
    <div style="background: rgba(217,119,6,0.12); border: 2px solid rgba(217,119,6,0.3); border-radius: 16px; padding: 1.25rem; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="font-size: 1.8rem;">💡</div>
            <div style="font-size: 0.9rem; color: #fde047;">
                <strong>Auditor Stock Rule:</strong> These items remain part of your **Physical Closing Stock** count on the shelves. Only tap "Handover Goods" when the customer physically carries them out of the building.
            </div>
        </div>
    </div>

    <!-- Orders List -->
    @forelse($unsuppliedSales as $sale)
    <div class="unsupplied-card">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <span style="font-size: 1.25rem; font-weight: 800; color: #f8fafc;">
                    Customer: {{ $sale->customerName }}
                </span>
                <span class="badge badge-warning">⏳ Awaiting Collection</span>
            </div>

            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                Invoice #{{ substr($sale->id, 0, 8) }} · Purchased on: {{ date('d M Y, h:i A', strtotime($sale->createdAt)) }}
            </div>

            <!-- Items list -->
            <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 10px; padding: 0.75rem 1rem;">
                <strong style="font-size: 0.8rem; color: #94a3b8; text-transform: uppercase;">Items Reserved on Shelf:</strong>
                <ul style="list-style: none; margin-top: 0.35rem; display: flex; flex-direction: column; gap: 0.35rem;">
                    @foreach($sale->items as $item)
                    <li style="font-size: 0.9rem; color: #e2e8f0;">
                        • <strong>{{ $item->productName }}</strong> — <span style="color: #fbbf24; font-weight: 700;">{{ $item->quantity }} units</span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div style="text-align: right;">
            <div style="font-size: 1.35rem; font-weight: 800; color: #4ade80; margin-bottom: 0.75rem;">
                ₦{{ number_format($sale->totalAmount, 2) }}
            </div>

            <form method="POST" action="{{ route('stock.dispatch', $sale->id) }}" onsubmit="return confirm('Confirm that customer is taking these items away now?')">
                @csrf
                <button type="submit" class="btn btn-success btn-lg">
                    📦 Handover Goods to Customer
                </button>
            </form>
        </div>
    </div>
    @empty
    <div style="text-align: center; padding: 4rem 1rem; background: var(--card-bg); border-radius: 20px;">
        <div style="font-size: 3.5rem; margin-bottom: 1rem;">🎉</div>
        <h3 style="font-size: 1.3rem; font-weight: 800;">All Goods Have Been Collected!</h3>
        <p style="color: var(--text-muted); margin-top: 0.35rem;">
            There are currently zero unsupplied customer orders left in the shop.
        </p>
    </div>
    @endforelse

@endsection
