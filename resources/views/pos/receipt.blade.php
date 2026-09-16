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

@php
    $custObj = \App\Models\Customer::where('name', $sale->customerName)->first();
    $totalUnits = $sale->items->sum('quantity');
    $isSupplied = in_array(strtoupper($sale->deliveryStatus ?? ''), ['DELIVERED', 'SUPPLIED']);
@endphp

<div style="max-width: 480px; margin: 0 auto 1.5rem;" class="no-print">
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
        <div style="font-size: 2rem; margin-bottom: 0.25rem;">🧾</div>
        <div class="receipt-title">{{ $tenant->name ?? 'VMARKET POS' }}</div>
        <div style="font-size: 0.8rem; font-weight: 800; color: #2563eb; text-transform: uppercase; margin-top: 0.2rem;">
            Inventory & Retail Distribution
        </div>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
            Branch / Location: <strong>{{ $warehouse->name ?? 'Main Branch' }}</strong>
        </div>
        <div style="font-size: 0.75rem; color: #64748b;">
            Date: {{ date('d M Y, h:i A') }} · Ref: #{{ substr($sale->id, 0, 8) }}
        </div>
    </div>

    <div style="font-size: 0.85rem; margin-bottom: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.5rem 0.75rem;">
        <div>Customer: <strong>{{ $sale->customerName }}</strong> @if($custObj && $custObj->customer_code)<span style="color: #2563eb; font-weight: 700;">[{{ $custObj->customer_code }}]</span>@endif</div>
        @if($custObj && $custObj->phone)<div style="font-size: 0.75rem; color: #64748b;">Phone: {{ $custObj->phone }}</div>@endif
        @if(preg_match('/\[RECEIPT REF:\s*#?([^\]]+)\]/i', $sale->note ?? '', $m))
            <div style="font-size: 0.8rem; font-weight: 800; color: #1e40af; margin-top: 0.25rem;">🧾 Physical Slip / Booklet Ref: #{{ trim($m[1]) }}</div>
        @endif
    </div>

    <!-- Line items -->
    <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
        <div style="font-size: 0.72rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.35rem;">
            New Items Purchased:
        </div>
        @foreach($sale->items as $item)
        <div class="receipt-item">
            <div>
                <strong style="font-weight: 800; font-size: 0.95rem;">{{ $item->product->code ?? $item->productCode ?? $item->productName }}</strong>
                <div style="font-size: 0.75rem; color: #64748b;">₦{{ number_format($item->unitPrice, 0) }} x {{ $item->quantity }}</div>
            </div>
            <div style="font-weight: 700;">
                ₦{{ number_format($item->totalPrice, 0) }}
            </div>
        </div>
        @endforeach
    </div>

    @php
        $exchangePayment = \App\Models\Payment::where('saleId', $sale->id)->where('method', 'EXCHANGE_CREDIT')->first();
        $exchangeCreditAmount = $exchangePayment ? (float) $exchangePayment->amount : 0;
        $hasExchange = ($exchangeCreditAmount > 0) || (str_contains($sale->note ?? '', 'EXCHANGE'));
    @endphp

    @if($hasExchange)
    <!-- Dedicated Exchange Return Breakdown Box -->
    <div style="background: #fffbeb; border: 1.5px dashed #f59e0b; border-radius: 10px; padding: 0.65rem 0.85rem; margin: 0.75rem 0; font-size: 0.82rem;">
        <div style="font-weight: 800; color: #b45309; display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
            <span style="display: flex; align-items: center; gap: 0.3rem;">🔄 Exchange Credit Deduction</span>
            @if($exchangeCreditAmount > 0)
                <span style="color: #dc2626; font-size: 0.95rem; font-weight: 800;">-₦{{ number_format($exchangeCreditAmount, 2) }}</span>
            @endif
        </div>
        @if($sale->note && str_contains($sale->note, 'EXCHANGE'))
            @php
                preg_match('/\[EXCHANGE[^\]]+\]/', $sale->note, $matches);
                $exDetails = $matches[0] ?? $sale->note;
            @endphp
            <div style="font-size: 0.74rem; color: #78350f; line-height: 1.4;">
                {{ trim($exDetails, '[]') }}
            </div>
        @endif
    </div>
    @endif

    <!-- Summary -->
    @php
        $debtRemaining = max(0, $sale->totalAmount - $sale->paidAmount);
        if ($sale->paidAmount >= $sale->totalAmount) {
            $paymentStatus = 'PAID';
            if ($hasExchange) {
                $combinedStatus = $isSupplied ? 'EXCHANGED & SUPPLIED' : 'EXCHANGED & NOT SUPPLIED';
                $badgeBg = '#fef3c7';
                $badgeColor = '#b45309';
                $badgeBorder = '#f59e0b';
            } else {
                $combinedStatus = $isSupplied ? 'PAID & SUPPLIED' : 'PAID & NOT SUPPLIED';
                $badgeBg = $isSupplied ? '#dcfce7' : '#fef3c7';
                $badgeColor = $isSupplied ? '#15803d' : '#b45309';
                $badgeBorder = $isSupplied ? '#86efac' : '#fcd34d';
            }
        } elseif ($sale->paidAmount > 0) {
            $paymentStatus = 'PART-PAID';
            $combinedStatus = $isSupplied ? 'PART-PAID & SUPPLIED' : 'PART-PAID & NOT SUPPLIED';
            $badgeBg = '#fef3c7';
            $badgeColor = '#b45309';
            $badgeBorder = '#fcd34d';
        } else {
            $paymentStatus = 'NOT PAID';
            $combinedStatus = $isSupplied ? 'NOT PAID & SUPPLIED' : 'NOT PAID & NOT SUPPLIED';
            $badgeBg = '#fee2e2';
            $badgeColor = '#b91c1c';
            $badgeBorder = '#fca5a5';
        }
        $netTopUpTender = max(0, $sale->paidAmount - $exchangeCreditAmount);
    @endphp

    <div class="receipt-summary">
        <div class="receipt-row" style="font-size: 1rem; font-weight: 700; color: #475569;">
            <span>Gross Items Total:</span>
            <span>₦{{ number_format($sale->totalAmount, 0) }}</span>
        </div>

        @if($exchangeCreditAmount > 0)
        <div class="receipt-row" style="font-size: 0.9rem; color: #b45309; font-weight: 700;">
            <span>Less Exchange Credit:</span>
            <span style="color: #dc2626;">-₦{{ number_format($exchangeCreditAmount, 0) }}</span>
        </div>
        <div class="receipt-row" style="font-size: 1.05rem; font-weight: 800; border-top: 1px dashed #cbd5e1; padding-top: 0.35rem; margin-top: 0.25rem;">
            <span>Net Top-Up Due:</span>
            <span style="color: #0f172a;">₦{{ number_format(max(0, $sale->totalAmount - $exchangeCreditAmount), 0) }}</span>
        </div>
        @endif

        @if($sale->tenderedAmount > 0 && $sale->tenderedAmount != $sale->paidAmount)
        <div class="receipt-row" style="font-size: 0.85rem; color: #475569;">
            <span>Total Tendered:</span>
            <span>₦{{ number_format($sale->tenderedAmount, 0) }}</span>
        </div>
        @endif

        <div class="receipt-row" style="color: #16a34a; font-weight: 700;">
            <span>Net Paid Tender:</span>
            <span>₦{{ number_format($netTopUpTender, 0) }} ({{ $paymentStatus }})</span>
        </div>

        @if($sale->changeAmount > 0)
        <div class="receipt-row" style="color: #2563eb; font-weight: 800;">
            <span>Change Returned:</span>
            <span>₦{{ number_format($sale->changeAmount, 0) }}</span>
        </div>
        @endif

        @if($debtRemaining > 0)
        <div class="receipt-row" style="color: #dc2626; font-weight: 800;">
            <span>Debt Balance ({{ $paymentStatus }}):</span>
            <span>₦{{ number_format($debtRemaining, 0) }}</span>
        </div>
        @endif

        <div class="receipt-row" style="font-size: 0.85rem; color: #64748b; margin-top: 0.25rem;">
            <span>Goods Status:</span>
            <strong style="color: {{ $isSupplied ? '#15803d' : '#b45309' }};">
                {{ $isSupplied ? '✓ SUPPLIED' : '⏳ NOT SUPPLIED' }}
            </strong>
        </div>
    </div>

    <div class="receipt-badge" style="background: {{ $badgeBg }}; color: {{ $badgeColor }}; border: 1.5px solid {{ $badgeBorder }};">
        <div style="font-size: 0.95rem; font-weight: 900; letter-spacing: 0.02em;">
            {{ $combinedStatus }}
        </div>
        @if($isSupplied)
            <div style="font-size: 0.75rem; font-weight: 600; margin-top: 0.25rem;">
                {{ $hasExchange ? '✓ ITEMS EXCHANGED & GOODS SUPPLIED' : '✓ GOODS SUPPLIED & COLLECTED' }}
                @if($sale->deliveredAt)
                    <br><span style="font-weight: 400;">Handed over: {{ \Carbon\Carbon::parse($sale->deliveredAt)->format('d M Y, h:i A') }}@if($sale->deliveredBy) · By: {{ $sale->deliveredBy }}@endif</span>
                @endif
            </div>
        @else
            <div style="font-size: 0.75rem; font-weight: 600; margin-top: 0.25rem;">
                ⏳ GOODS NOT SUPPLIED (AWAITING CUSTOMER PICKUP IN SHOP)
                <div style="font-weight: 400; font-size: 0.7rem; opacity: 0.9; margin-top: 0.15rem;">
                    Items locked in shop stock buffer until customer pickup
                </div>
            </div>
        @endif
    </div>

    <div style="text-align: center; font-size: 0.75rem; color: #94a3b8; margin-top: 1.5rem;">
        Thank you for your patronage!<br>
        Attendant / Cashier: {{ $sale->userName }}
        <div style="font-size: 0.72rem; font-weight: 700; color: #475569; margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px dashed #cbd5e1;">
            Powered by Victorious Market — Your trusted online market
        </div>
    </div>
</div>

@endsection
