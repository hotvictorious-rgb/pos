@extends('reports.pdf.layout')

@section('content')
<table class="executive-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th class="text-center" style="width: 95px;">ORDER #</th>
            <th class="text-left" style="width: 100px;">ORDER DATE</th>
            <th class="text-left" style="width: 100px;">BRANCH</th>
            <th class="text-left" style="width: 130px;">CUSTOMER</th>
            <th class="text-left" style="width: 100px;">PHONE</th>
            <th class="text-left">PENDING GOODS (UNSUPPLIED)</th>
            <th class="text-right" style="width: 80px;">UNITS</th>
            <th class="text-right" style="width: 95px;">VALUE (₦)</th>
            <th class="text-right" style="width: 95px;">PAID (₦)</th>
            <th class="text-center" style="width: 85px;">PAY STATUS</th>
            <th class="text-center" style="width: 80px;">DAYS</th>
        </tr>
    </thead>
    <tbody>
        @forelse($orders as $o)
            @php
                $tot = (float) ($o['total_amount'] ?? $o['total_value'] ?? $o['totalAmount'] ?? 0);
                $paid = (float) ($o['paid_amount'] ?? $o['paidAmount'] ?? 0);
                $days = $o['days_old'] ?? $o['age_days'] ?? $o['days_pending'] ?? 0;
            @endphp
            <tr>
                <td class="text-center text-muted font-mono">{{ $loop->iteration }}</td>
                <td class="text-center font-mono font-bold" style="color: #0c2340;">
                    #{{ substr($o['sale_id'] ?? $o['id'], 0, 8) }}
                </td>
                <td class="text-left font-mono" style="font-size: 9px; color: #475569;">
                    {{ \Carbon\Carbon::parse($o['created_at'])->format('d M Y') }}
                </td>
                <td class="text-left" style="font-size: 9px;">{{ $o['warehouse_name'] ?? 'Branch' }}</td>
                <td class="text-left font-bold">{{ $o['customer_name'] ?? 'Customer' }}</td>
                <td class="text-left font-mono" style="font-size: 9px; color: #475569;">{{ $o['customer_phone'] ?? '—' }}</td>
                <td class="text-left font-bold" style="color: #b45309; font-size: 9px;">
                    Order Backlog • {{ number_format((int) ($o['total_units'] ?? $o['units_pending'] ?? 1)) }} Units Awaiting Dispatch
                </td>
                <td class="text-right font-mono font-bold" style="color: #b45309;">
                    {{ number_format((int) ($o['total_units'] ?? $o['units_pending'] ?? 1)) }}
                </td>
                <td class="text-right font-mono font-bold">₦{{ number_format($tot, 2) }}</td>
                <td class="text-right font-mono font-bold" style="color: #0f766e;">₦{{ number_format($paid, 2) }}</td>
                <td class="text-center">
                    @if($paid >= $tot && $tot > 0)
                        <span class="badge badge-instock">PAID (FULL)</span>
                    @elseif($paid > 0)
                        <span class="badge badge-lowstock">PART-PAID</span>
                    @else
                        <span class="badge badge-outstock">UNPAID</span>
                    @endif
                </td>
                <td class="text-center font-mono">
                    <span class="badge {{ $days > 7 ? 'badge-outstock' : 'badge-neutral' }}">{{ $days }}d</span>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="12" class="text-center" style="padding: 30px; color: #64748b;">
                    No pending unsupplied orders currently backlogged.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" class="text-left">TOTAL PENDING ORDERS & BACKLOG OBLIGATIONS</td>
            <td class="text-right font-mono font-bold" style="color: #b45309; font-size: 11px;">
                {{ number_format($totalUnitsBacklogged) }}
            </td>
            <td class="text-right font-mono font-bold" style="font-size: 11px;">
                ₦{{ number_format($totalPendingOrderValue, 2) }}
            </td>
            <td class="text-right font-mono font-bold" style="color: #0f766e; font-size: 11px;">
                ₦{{ number_format($totalPendingPaid, 2) }}
            </td>
            <td colspan="2" class="text-center font-bold" style="font-size: 8.5px;">{{ count($orders) }} Orders</td>
        </tr>
    </tfoot>
</table>
@endsection
