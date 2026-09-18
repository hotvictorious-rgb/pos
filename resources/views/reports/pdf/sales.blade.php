@extends('reports.pdf.layout')

@section('content')
<table class="executive-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 32px;">#</th>
            <th class="text-center" style="width: 90px;">INVOICE #</th>
            <th class="text-left" style="width: 105px;">DATE & TIME</th>
            <th class="text-left" style="width: 85px;">CASHIER</th>
            <th class="text-left" style="width: 95px;">BRANCH</th>
            <th class="text-left">CUSTOMER</th>
            <th class="text-left">ITEMS SUMMARY</th>
            <th class="text-center" style="width: 75px;">METHOD</th>
            <th class="text-center" style="width: 80px;">HANDOVER</th>
            <th class="text-right" style="width: 90px;">TOTAL (₦)</th>
            <th class="text-right" style="width: 90px;">PAID (₦)</th>
            <th class="text-right" style="width: 90px;">BALANCE (₦)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($sales as $s)
            <tr>
                <td class="text-center text-muted font-mono">{{ $loop->iteration }}</td>
                <td class="text-center font-mono font-bold" style="color: #0c2340;">
                    {{ $s['invoiceNumber'] ?? $s['id'] }}
                </td>
                <td class="text-left font-mono" style="font-size: 9px; color: #475569;">
                    {{ \Carbon\Carbon::parse($s['createdAt'] ?? $s['created_at'])->format('d M Y, h:i A') }}
                </td>
                <td class="text-left font-bold" style="color: #334155;">{{ $s['userName'] ?? $s['user']['name'] ?? 'System' }}</td>
                <td class="text-left" style="font-size: 9px;">{{ $s['warehouse']['name'] ?? 'Main Branch' }}</td>
                <td class="text-left font-bold">
                    {{ $s['customer']['name'] ?? $s['customerName'] ?? 'Walk-In Customer' }}
                </td>
                <td class="text-left" style="font-size: 9px; max-width: 220px; color: #334155;">
                    @if(isset($s['items']) && count($s['items']) > 0)
                        {{ collect($s['items'])->map(fn($it) => ($it['productName'] ?? $it['product']['name'] ?? 'Item') . ' (' . ($it['quantity'] ?? 1) . ')')->join(', ') }}
                    @else
                        {{ $s['itemSummary'] ?? '—' }}
                    @endif
                </td>
                <td class="text-center">
                    <span class="badge badge-neutral font-mono">{{ $s['paymentMethod'] ?? 'CASH' }}</span>
                </td>
                <td class="text-center">
                    @php $del = strtoupper($s['deliveryStatus'] ?? 'SUPPLIED'); @endphp
                    @if(in_array($del, ['SUPPLIED', 'DELIVERED', 'COMPLETED']))
                        <span class="badge badge-instock">SUPPLIED</span>
                    @else
                        <span class="badge badge-lowstock">PENDING</span>
                    @endif
                </td>
                <td class="text-right font-mono font-bold">
                    ₦{{ number_format((float) ($s['totalAmount'] ?? 0), 2) }}
                </td>
                <td class="text-right font-mono font-bold" style="color: #0f766e;">
                    ₦{{ number_format((float) ($s['paidAmount'] ?? 0), 2) }}
                </td>
                <td class="text-right font-mono font-bold" style="color: {{ ((float)($s['invoice_balance'] ?? 0)) > 0 ? '#b91c1c' : '#64748b' }};">
                    ₦{{ number_format((float) ($s['invoice_balance'] ?? 0), 2) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="12" class="text-center" style="padding: 30px; color: #64748b;">
                    No sales records found matching the active filter criteria.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="9" class="text-left">TOTAL FILTERED SALES RECONCILIATION</td>
            <td class="text-right font-mono font-bold" style="font-size: 11px;">
                ₦{{ number_format($totalGrossRevenue, 2) }}
            </td>
            <td class="text-right font-mono font-bold" style="color: #0f766e; font-size: 11px;">
                ₦{{ number_format($totalPaidCollected, 2) }}
            </td>
            <td class="text-right font-mono font-bold" style="color: #b91c1c; font-size: 11px;">
                ₦{{ number_format($totalOutstandingDebt, 2) }}
            </td>
        </tr>
    </tfoot>
</table>
@endsection
