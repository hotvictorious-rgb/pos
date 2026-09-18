@extends('reports.pdf.layout')

@section('content')
<table class="executive-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th class="text-center" style="width: 110px;">WAYBILL #</th>
            <th class="text-left" style="width: 110px;">DISPATCH DATE</th>
            <th class="text-left" style="width: 130px;">FROM (ORIGIN)</th>
            <th class="text-left" style="width: 130px;">TO (DESTINATION)</th>
            <th class="text-left" style="width: 100px;">INITIATED BY</th>
            <th class="text-left">ITEMS & SKUS</th>
            <th class="text-right" style="width: 80px;">SENT</th>
            <th class="text-right" style="width: 80px;">RECEIVED</th>
            <th class="text-right" style="width: 90px;">VARIANCE</th>
            <th class="text-center" style="width: 90px;">STATUS</th>
        </tr>
    </thead>
    <tbody>
        @forelse($transfers as $t)
            @php
                $sent = (int) ($t['quantity_sent'] ?? 0);
                $rec = (int) ($t['quantity_received'] ?? 0);
                $diff = (int) ($t['discrepancy'] ?? 0);
                $st = strtoupper($t['status'] ?? 'IN_TRANSIT');
            @endphp
            <tr>
                <td class="text-center text-muted font-mono">{{ $loop->iteration }}</td>
                <td class="text-center font-mono font-bold" style="color: #0c2340;">
                    {{ $t['transferNumber'] ?? $t['id'] }}
                </td>
                <td class="text-left font-mono" style="font-size: 9px; color: #475569;">
                    {{ \Carbon\Carbon::parse($t['createdAt'] ?? $t['created_at'])->format('d M Y, h:i A') }}
                </td>
                <td class="text-left font-bold">{{ $t['fromWarehouse']['name'] ?? 'Warehouse' }}</td>
                <td class="text-left font-bold" style="color: #0369a1;">{{ $t['toWarehouse']['name'] ?? 'Branch' }}</td>
                <td class="text-left" style="font-size: 9px;">{{ $t['senderUser']['name'] ?? $t['userName'] ?? 'Staff' }}</td>
                <td class="text-left" style="font-size: 9px; max-width: 200px;">
                    @if(isset($t['items']) && count($t['items']) > 0)
                        {{ collect($t['items'])->map(fn($it) => ($it['product']['code'] ?? 'SKU') . ' (' . $it['quantity'] . ')')->join(', ') }}
                    @else
                        {{ $t['productCode'] ?? 'Goods' }}
                    @endif
                </td>
                <td class="text-right font-mono font-bold">{{ number_format($sent) }}</td>
                <td class="text-right font-mono font-bold" style="color: #0f766e;">{{ number_format($rec) }}</td>
                <td class="text-right font-mono font-bold" style="color: {{ $diff > 0 ? '#b91c1c' : '#64748b' }};">
                    {{ $diff > 0 ? '-' . number_format($diff) : '0' }}
                </td>
                <td class="text-center">
                    @if(in_array($st, ['RECEIVED', 'COMPLETED']))
                        <span class="badge badge-instock">COMPLETED</span>
                    @elseif($st === 'DISCREPANCY')
                        <span class="badge badge-outstock">DISCREPANCY</span>
                    @else
                        <span class="badge badge-lowstock">IN-TRANSIT</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="11" class="text-center" style="padding: 30px; color: #64748b;">
                    No inter-branch transfer movements found matching the active filter criteria.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" class="text-left">TOTAL INTER-BRANCH MOVEMENTS RECONCILIATION</td>
            <td class="text-right font-mono font-bold">{{ number_format($totalUnitsSent) }}</td>
            <td class="text-right font-mono font-bold" style="color: #0f766e;">{{ number_format($totalUnitsReceived) }}</td>
            <td class="text-right font-mono font-bold" style="color: {{ $totalDiscrepancies > 0 ? '#b91c1c' : '#0c2340' }};">
                {{ number_format($totalDiscrepancies) }}
            </td>
            <td class="text-center font-bold" style="font-size: 8.5px;">{{ count($transfers) }} Trans</td>
        </tr>
    </tfoot>
</table>
@endsection
