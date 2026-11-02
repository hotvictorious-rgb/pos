@extends('reports.pdf.layout')

@section('content')
<table class="executive-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th class="text-left" style="width: 110px;">DATE & TIME</th>
            <th class="text-center" style="width: 95px;">SALE INVOICE</th>
            <th class="text-left" style="width: 110px;">BRANCH</th>
            <th class="text-left" style="width: 95px;">CASHIER</th>
            <th class="text-left" style="width: 120px;">CUSTOMER</th>
            <th class="text-left">ITEMS RETURNED & SKUS</th>
            <th class="text-right" style="width: 75px;">UNITS</th>
            <th class="text-right" style="width: 110px;">REFUND (₦)</th>
            <th class="text-left">REASON & CONDITION</th>
        </tr>
    </thead>
    <tbody>
        @forelse($returns as $ret)
            <tr>
                <td class="text-center text-muted font-mono">{{ $loop->iteration }}</td>
                <td class="text-left font-mono" style="font-size: 9px; color: #475569;">
                    {{ \Carbon\Carbon::parse($ret['createdAt'] ?? $ret['created_at'])->format('d M Y, h:i A') }}
                </td>
                <td class="text-center font-mono font-bold" style="color: #0c2340;">
                    {{ $ret['sale']['invoiceNumber'] ?? $ret['saleId'] ?? '—' }}
                </td>
                <td class="text-left" style="font-size: 9.5px;">{{ $ret['sale']['warehouse']['name'] ?? 'Branch' }}</td>
                <td class="text-left font-bold" style="font-size: 9.5px;">{{ $ret['userName'] ?? 'Staff' }}</td>
                <td class="text-left font-bold">{{ $ret['customerName'] ?? 'Customer' }}</td>
                <td class="text-left" style="font-size: 9px; color: #b91c1c;">
                    {{ $ret['productName'] ?? 'Product Return' }}
                    @if(!empty($ret['productCode']))
                        <span style="color: #64748b; font-weight: normal;">({{ $ret['productCode'] }})</span>
                    @endif
                </td>
                <td class="text-right font-mono font-bold" style="color: #b91c1c;">
                    {{ number_format((int) ($ret['quantity'] ?? 1)) }}
                </td>
                <td class="text-right font-mono font-bold" style="color: #b91c1c;">
                    ₦{{ number_format((float) ($ret['refundAmount'] ?? $ret['refund_amount'] ?? 0), 2) }}
                </td>
                <td class="text-left" style="font-size: 9px; color: #64748b;">
                    {{ $ret['reason'] ?? 'Customer Return' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="text-center" style="padding: 30px; color: #64748b;">
                    No customer return or refund records found for the active filter.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" class="text-left">TOTAL RETURN CREDITS & REFUNDS ISSUED</td>
            <td class="text-right font-mono font-bold" style="color: #b91c1c; font-size: 11px;">
                {{ number_format($totalReturnedUnits) }}
            </td>
            <td class="text-right font-mono font-bold" style="color: #b91c1c; font-size: 11px;">
                ₦{{ number_format($totalRefundAmount, 2) }}
            </td>
            <td class="text-center font-bold" style="font-size: 8.5px;">{{ count($returns) }} Returns</td>
        </tr>
    </tfoot>
</table>
@endsection
