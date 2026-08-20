@extends('layouts.app')

@section('title', 'Receipt #' . $sale->id)

@push('styles')
<style>
    .receipt-wrap {
        max-width: 460px;
        margin: 0 auto;
        background: #fff;
        color: #0f172a;
        border-radius: 16px;
        padding: 2rem 1.5rem;
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        font-family: 'Plus Jakarta Sans', monospace, sans-serif;
    }

    .receipt-header {
        text-align: center;
        border-bottom: 2px dashed #cbd5e1;
        padding-bottom: 1rem;
        margin-bottom: 1rem;
    }

    .receipt-title {
        font-size: 1.3rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .receipt-item {
        display: flex;
        justify-content: space-between;
        padding: 0.4rem 0;
        font-size: 0.85rem;
    }

    .receipt-summary {
        border-top: 2px dashed #cbd5e1;
        margin-top: 1rem;
        padding-top: 1rem;
    }

    .receipt-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.35rem;
        font-size: 0.9rem;
    }

    .receipt-badge {
        display: block;
        text-align: center;
        padding: 0.6rem;
        border-radius: 8px;
        font-weight: 800;
        font-size: 0.85rem;
        margin-top: 1rem;
    }

    @media print {
        body { background: #fff; color: #000; }
        .navbar, .no-print { display: none !important; }
        .receipt-wrap { box-shadow: none; padding: 0; max-width: 100%; border-radius: 0; }
    }
</style>
@endpush

@section('content')

<div style="max-width: 460px; margin: 0 auto 1.5rem;" class="no-print">
    <div style="display: flex; gap: 0.75rem;">
        <button onclick="window.print()" class="btn btn-primary" style="flex: 1; font-size: 1.05rem;">
            🖨️ Print Receipt
        </button>
        <a href="{{ route('pos.index') }}" class="btn btn-success" style="flex: 1; font-size: 1.05rem;">
            💰 New Sale →
        </a>
    </div>
</div>

<div class="receipt-wrap" id="printableReceipt">
    <div class="receipt-header">
        <div style="font-size: 2rem; margin-bottom: 0.25rem;">📦</div>
        <div class="receipt-title">HYSAM VENTURES</div>
        <div style="font-size: 0.75rem; color: #64748b;">Inventory & Retail Distribution</div>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
            Location: <strong>{{ $warehouse->name ?? 'Main Branch' }}</strong>
        </div>
        <div style="font-size: 0.75rem; color: #64748b;">
            Date: {{ date('d M Y, h:i A') }} · Ref: #{{ substr($sale->id, 0, 8) }}
        </div>
    </div>

    <div style="font-size: 0.85rem; margin-bottom: 0.75rem;">
        Customer: <strong>{{ $sale->customerName }}</strong>
    </div>

    <!-- Line items -->
    <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
        @foreach($sale->items as $item)
        <div class="receipt-item">
            <div>
                <strong>{{ $item->productName }}</strong>
                <div style="font-size: 0.75rem; color: #64748b;">₦{{ number_format($item->unitPrice, 2) }} x {{ $item->quantity }}</div>
            </div>
            <div style="font-weight: 700;">
                ₦{{ number_format($item->totalPrice, 2) }}
            </div>
        </div>
        @endforeach
    </div>

    <!-- Summary -->
    <div class="receipt-summary">
        <div class="receipt-row" style="font-size: 1.1rem; font-weight: 800;">
            <span>TOTAL:</span>
            <span>₦{{ number_format($sale->totalAmount, 2) }}</span>
        </div>
        <div class="receipt-row" style="color: #16a34a; font-weight: 700;">
            <span>Amount Paid:</span>
            <span>₦{{ number_format($sale->paidAmount, 2) }}</span>
        </div>

        @php $debtRemaining = max(0, $sale->totalAmount - $sale->paidAmount); @endphp
        @if($debtRemaining > 0)
        <div class="receipt-row" style="color: #dc2626; font-weight: 800;">
            <span>Balance Remaining:</span>
            <span>₦{{ number_format($debtRemaining, 2) }}</span>
        </div>
        @endif
    </div>

    <!-- Physical Fulfillment Status Badge -->
    @if($sale->deliveryStatus === 'DELIVERED')
        <div class="receipt-badge" style="background: #dcfce7; color: #15803d; border: 1px solid #86efac;">
            ✓ GOODS SUPPLIED & COLLECTED
        </div>
    @else
        <div class="receipt-badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fcd34d;">
            ⏳ GOODS IN SHOP (PENDING PICKUP)
        </div>
    @endif

    <div style="text-align: center; font-size: 0.75rem; color: #94a3b8; margin-top: 1.5rem;">
        Thank you for your patronage!<br>
        Cashier: {{ $sale->userName }}
    </div>
</div>

@endsection
