@extends('reports.pdf.layout')

@section('content')
<table class="executive-table table-compact">
    <thead>
        <tr>
            <th class="text-center" style="width: 22px;">#</th>
            <th class="text-center" style="width: 65px;">INVOICE #</th>
            <th class="text-left" style="width: 75px;">DATE & TIME</th>
            <th class="text-left" style="width: 65px;">BRANCH</th>
            <th class="text-left" style="width: 60px;">CASHIER</th>
            <th class="text-left" style="width: 80px;">CUSTOMER</th>
            <th class="text-center" style="width: 60px;">SKU</th>
            <th class="text-left">PRODUCT NAME</th>
            <th class="text-left" style="width: 65px;">CATEGORY</th>
            <th class="text-center" style="width: 35px;">QTY</th>
            <th class="text-right" style="width: 65px;">PRICE (₦)</th>
            <th class="text-right" style="width: 70px;">TOTAL (₦)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $it)
            @php
                $sale = $it->sale;
                $sku = $it->code ?: ($it->productCode ?: ($it->product->code ?? 'N/A'));
                $qty = (int) $it->quantity;
                $unitPrice = (float) $it->unitPrice;
                $totalPrice = (float) $it->totalPrice;
            @endphp
            <tr>
                <td class="text-center text-muted font-mono">{{ $loop->iteration }}</td>
                <td class="text-center font-mono font-bold" style="color: #0c2340;">
                    {{ $it->saleId }}
                    @if(!empty($sale?->receipt_ref))
                        <div style="font-size: 7.5px; color: #6366f1; font-weight: 700;">Slip: {{ $sale->receipt_ref }}</div>
                    @endif
                </td>
                <td class="text-left font-mono" style="font-size: 8.5px; color: #475569;">
                    {{ \Carbon\Carbon::parse($sale?->createdAt ?? $it->created_at)->format('d M Y, h:i A') }}
                </td>
                <td class="text-left" style="font-size: 9px;">{{ $sale?->warehouse?->name ?? 'Main Branch' }}</td>
                <td class="text-left font-bold" style="color: #334155; font-size: 9px;">{{ $sale?->userName ?? 'System' }}</td>
                <td class="text-left font-bold" style="font-size: 9px;">
                    {{ $sale?->customerName ?? ($sale?->customer?->name ?? 'Walk-In Customer') }}
                </td>
                <td class="text-center font-mono font-bold" style="font-size: 8.5px; color: #0369a1;">{{ $sku }}</td>
                <td class="text-left font-bold" style="color: #0f172a;">{{ $it->productName }}</td>
                <td class="text-left text-muted" style="font-size: 8.5px;">{{ $it->product?->category ?? 'General' }}</td>
                <td class="text-center font-mono font-bold" style="font-size: 10px; color: #0c2340;">{{ $qty }}</td>
                <td class="text-right font-mono" style="font-size: 9px;">₦{{ number_format($unitPrice, 2) }}</td>
                <td class="text-right font-mono font-bold" style="color: #0f766e; font-size: 10px;">₦{{ number_format($totalPrice, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="12" class="text-center" style="padding: 30px; color: #64748b;">
                    No line items found matching the active filter criteria.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="9" class="text-left">TOTAL FILTERED PRODUCT UNITS & REVENUE</td>
            <td class="text-center font-mono font-bold" style="font-size: 11px; color: #0c2340;">
                {{ number_format($totalUnits) }}
            </td>
            <td></td>
            <td class="text-right font-mono font-bold" style="color: #0f766e; font-size: 11px;">
                ₦{{ number_format($totalRevenue, 2) }}
            </td>
        </tr>
    </tfoot>
</table>
@endsection

