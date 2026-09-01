@extends('layouts.app')

@section('title', 'Commercial Invoice #' . substr($sale->id, 0, 8))

@push('styles')
<style>
    .invoice-wrap {
        max-width: 720px;
        margin: 0 auto;
        background: #fff;
        color: #0f172a;
        border-radius: 16px;
        padding: 2.5rem 2rem;
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .invoice-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .inv-table {
        width: 100%;
        border-collapse: collapse;
        margin: 1.5rem 0;
    }

    .inv-table th {
        background: #f8fafc;
        border-bottom: 2px solid #cbd5e1;
        padding: 0.75rem;
        font-size: 0.8rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #475569;
        text-align: left;
    }

    .inv-table td {
        padding: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.88rem;
    }

    @media print {
        body { background: #fff; color: #000; }
        .navbar, .no-print { display: none !important; }
        .invoice-wrap { box-shadow: none; padding: 0; max-width: 100%; border-radius: 0; }
    }
</style>
@endpush

@section('content')

@php
    $cust = $sale->customer;
    $debtRemaining = max(0, $sale->totalAmount - $sale->paidAmount);
    $totalUnits = $sale->items->sum('quantity');
@endphp

<div style="max-width: 720px; margin: 0 auto 1.5rem;" class="no-print">
    <div style="display: flex; gap: 0.75rem; justify-content: space-between;">
        <a href="{{ route('wholesale.index') }}" class="btn btn-secondary" style="font-size: 0.95rem;">
            ← Back to Wholesale Hub
        </a>
        <div style="display: flex; gap: 0.5rem;">
            <button onclick="window.print()" class="btn btn-primary" style="font-size: 0.95rem; font-weight: 700;">
                🖨️ Print Commercial Invoice
            </button>
            <a href="{{ route('pos.receipt', $sale->id) }}" target="_blank" class="btn btn-secondary" style="font-size: 0.95rem;">
                📦 View Waybill
            </a>
        </div>
    </div>
</div>

<div class="invoice-wrap" id="printableInvoice">
    <div class="invoice-header">
        <div>
            <div style="font-size: 1.6rem; font-weight: 900; color: #0f172a; letter-spacing: -0.02em;">{{ $tenant->name ?? 'VMARKET POS' }}</div>
            <div style="font-size: 0.82rem; color: #64748b; margin-top: 0.2rem;">Commercial Wholesale Distribution & Trading</div>
            <div style="font-size: 0.82rem; color: #64748b;">
                Branch / Warehouse: <strong>{{ $warehouse->name ?? 'Main Branch' }}</strong>
            </div>
            @if($warehouse->address)<div style="font-size: 0.78rem; color: #64748b;">{{ $warehouse->address }}</div>@endif
        </div>

        <div style="text-align: right;">
            <div style="font-size: 1.15rem; font-weight: 900; color: #7c3aed; text-transform: uppercase;">
                COMMERCIAL INVOICE
            </div>
            <div style="font-size: 0.85rem; font-family: monospace; font-weight: 700; margin-top: 0.2rem;">
                INV-{{ strtoupper(substr($sale->id, 0, 8)) }}
            </div>
            <div style="font-size: 0.78rem; color: #64748b; margin-top: 0.25rem;">
                Date: {{ date('d M Y, h:i A', strtotime($sale->createdAt)) }}
            </div>
        </div>
    </div>

    <!-- Client / Bill To Card -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
        <div>
            <div style="font-size: 0.72rem; font-weight: 800; color: #64748b; text-transform: uppercase;">BILL TO / WHOLESALER</div>
            <div style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem;">{{ $sale->customerName }}</div>
            @if($cust && $cust->phone)<div style="font-size: 0.82rem; color: #475569;">Phone: {{ $cust->phone }}</div>@endif
            @if($cust && $cust->address)<div style="font-size: 0.78rem; color: #64748b;">Address: {{ $cust->address }}</div>@endif
        </div>

        <div style="text-align: right;">
            <div style="font-size: 0.72rem; font-weight: 800; color: #64748b; text-transform: uppercase;">ACCOUNT DETAILS</div>
            <div style="font-size: 0.88rem; font-weight: 700; color: #2563eb; margin-top: 0.2rem;">
                Code: {{ $cust->customer_code ?? 'WHOLESALE-CLIENT' }}
            </div>
            <div style="font-size: 0.82rem; color: #475569; margin-top: 0.25rem;">
                Order Dispatch Status: <strong style="color: #15803d;">✓ GOODS SUPPLIED</strong>
            </div>
        </div>
    </div>

    <!-- Line Items Table -->
    <table class="inv-table">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Item Description / SKU</th>
                <th style="text-align: center; width: 80px;">Qty</th>
                <th style="text-align: right; width: 140px;">Negotiated Price</th>
                <th style="text-align: right; width: 140px;">Total (₦)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $index => $item)
            <tr>
                <td style="color: #64748b;">{{ $index + 1 }}</td>
                <td>
                    <strong style="color: #0f172a; font-size: 0.95rem;">{{ $item->productName }}</strong>
                    <div style="font-size: 0.75rem; color: #64748b;">{{ $item->code || $item->productCode || 'SKU' }}</div>
                </td>
                <td style="text-align: center; font-weight: 800; font-size: 0.95rem;">
                    {{ $item->quantity }}
                </td>
                <td style="text-align: right; font-weight: 700; color: #475569;">
                    ₦{{ number_format($item->unitPrice, 2) }}
                </td>
                <td style="text-align: right; font-weight: 800; color: #0f172a; font-size: 0.95rem;">
                    ₦{{ number_format($item->totalPrice, 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Financial Breakdown & Totals -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 1.5rem; border-top: 2px solid #e2e8f0; padding-top: 1.25rem;">
        <div style="max-width: 320px;">
            <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Total Physical Volume:</div>
            <div style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-top: 0.15rem;">{{ $totalUnits }} physical units dispatched</div>
            @if($sale->note)
                <div style="font-size: 0.78rem; color: #64748b; margin-top: 0.5rem; background: #f8fafc; padding: 0.4rem 0.6rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                    <strong>Office Note:</strong> {{ $sale->note }}
                </div>
            @endif
        </div>

        <div style="width: 280px;">
            <div style="display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 900; margin-bottom: 0.4rem;">
                <span>TOTAL INVOICED:</span>
                <span style="color: #7c3aed;">₦{{ number_format($sale->totalAmount, 2) }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #16a34a; font-weight: 700; margin-bottom: 0.35rem;">
                <span>Amount Paid:</span>
                <span>₦{{ number_format($sale->paidAmount, 2) }}</span>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 0.95rem; font-weight: 800; color: {{ $debtRemaining > 0 ? '#dc2626' : '#16a34a' }}; border-top: 1px dashed #cbd5e1; padding-top: 0.4rem;">
                <span>Balance Due:</span>
                <span>₦{{ number_format($debtRemaining, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Official Stamp & Authorization Signatures -->
    <div style="margin-top: 3rem; border-top: 1px solid #cbd5e1; padding-top: 1.5rem; font-size: 0.8rem;">
        <div style="display: flex; justify-content: space-between; gap: 2rem;">
            <div style="flex: 1; text-align: center; border-top: 1px solid #94a3b8; padding-top: 0.4rem; margin-top: 2.5rem;">
                <strong>Executive Commercial Office (Hysam)</strong>
                <div style="font-size: 0.72rem; color: #64748b;">Authorized Signatory & Stamp</div>
            </div>

            <div style="flex: 1; text-align: center; border-top: 1px solid #94a3b8; padding-top: 0.4rem; margin-top: 2.5rem;">
                <strong>Wholesaler / Client Representative</strong>
                <div style="font-size: 0.72rem; color: #64748b;">Received & Acknowledged</div>
            </div>
        </div>
        <div style="font-size: 0.72rem; font-weight: 700; color: #475569; margin-top: 1.5rem; padding-top: 0.5rem; border-top: 1px dashed #cbd5e1; text-align: center;">
            Powered by Victorious Market — Your trusted online market
        </div>
    </div>
</div>

@endsection
