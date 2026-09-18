@extends('reports.pdf.layout')

@section('content')
<table class="executive-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th class="text-left" style="width: 105px;">DATE</th>
            <th class="text-center" style="width: 95px;">SALE INVOICE</th>
            <th class="text-left" style="width: 95px;">BRANCH</th>
            <th class="text-left" style="width: 85px;">CASHIER</th>
            <th class="text-left" style="width: 110px;">CUSTOMER</th>
            <th class="text-left">RETURNED ITEM(S)</th>
            <th class="text-right" style="width: 90px;">CREDIT (₦)</th>
            <th class="text-left">REPLACEMENT ITEM(S)</th>
            <th class="text-right" style="width: 90px;">REPLACE (₦)</th>
            <th class="text-right" style="width: 95px;">DIFF (₦)</th>
            <th class="text-center" style="width: 85px;">SETTLEMENT</th>
        </tr>
    </thead>
    <tbody>
        @forelse($exchanges as $ex)
            <tr>
                <td class="text-center text-muted font-mono">{{ $loop->iteration }}</td>
                <td class="text-left font-mono" style="font-size: 9px; color: #475569;">
                    {{ \Carbon\Carbon::parse($ex['created_at'] ?? now())->format('d M Y, h:i A') }}
                </td>
                <td class="text-center font-mono font-bold" style="color: #0c2340;">
                    {{ $ex['sale_id'] ?? '—' }}
                </td>
                <td class="text-left" style="font-size: 9px;">{{ $ex['warehouse_name'] ?? 'Main' }}</td>
                <td class="text-left" style="font-size: 9px;">{{ $ex['cashier_name'] ?? 'Staff' }}</td>
                <td class="text-left font-bold">{{ $ex['customer_name'] ?? 'Customer' }}</td>
                <td class="text-left" style="color: #b91c1c; font-size: 9px;">
                    {{ $ex['returned_names'] ?? $ex['returned_skus'] ?? '—' }}
                    <span class="badge badge-outstock font-mono">{{ $ex['returned_units'] ?? 1 }}u</span>
                </td>
                <td class="text-right font-mono font-bold" style="color: #b91c1c;">
                    ₦{{ number_format((float) ($ex['exchange_credit'] ?? 0), 2) }}
                </td>
                <td class="text-left" style="color: #0f766e; font-size: 9px;">
                    {{ $ex['replacement_names'] ?? $ex['replacement_skus'] ?? '—' }}
                    <span class="badge badge-instock font-mono">{{ $ex['replacement_units'] ?? 1 }}u</span>
                </td>
                <td class="text-right font-mono font-bold" style="color: #0f766e;">
                    ₦{{ number_format((float) ($ex['replacement_total'] ?? 0), 2) }}
                </td>
                <td class="text-right font-mono font-bold">
                    ₦{{ number_format((float) ($ex['differential'] ?? 0), 2) }}
                </td>
                <td class="text-center">
                    <span class="badge badge-neutral font-mono">{{ $ex['payment_status'] ?? 'SETTLED' }}</span>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="12" class="text-center" style="padding: 30px; color: #64748b;">
                    No customer product exchange records found matching the active filter criteria.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" class="text-left">TOTAL EXCHANGES AUDIT RECONCILIATION</td>
            <td class="text-right font-mono font-bold" style="color: #b91c1c; font-size: 11px;">
                ₦{{ number_format($totalReturnCredit, 2) }}
            </td>
            <td></td>
            <td class="text-right font-mono font-bold" style="color: #0f766e; font-size: 11px;">
                ₦{{ number_format($totalReplacementValue, 2) }}
            </td>
            <td class="text-right font-mono font-bold" style="font-size: 11px;">
                ₦{{ number_format($totalDifferentialCollected, 2) }}
            </td>
            <td class="text-center font-bold" style="font-size: 8.5px;">{{ count($exchanges) }} Exch</td>
        </tr>
    </tfoot>
</table>
@endsection
