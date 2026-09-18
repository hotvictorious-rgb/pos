@extends('reports.pdf.layout')

@section('content')
<table class="executive-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th class="text-left" style="width: 120px;">DATE & TIME</th>
            <th class="text-left" style="width: 120px;">BRANCH</th>
            <th class="text-left" style="width: 100px;">LOGGED BY</th>
            <th class="text-center" style="width: 85px;">SKU</th>
            <th class="text-left">PRODUCT NAME & SPEC</th>
            <th class="text-right" style="width: 90px;">QTY DEDUCTED</th>
            <th class="text-center" style="width: 130px;">REASON / TYPE</th>
            <th class="text-left">EXPLANATORY NOTES</th>
        </tr>
    </thead>
    <tbody>
        @forelse($adjustments as $adj)
            <tr>
                <td class="text-center text-muted font-mono">{{ $loop->iteration }}</td>
                <td class="text-left font-mono" style="font-size: 9px; color: #475569;">
                    {{ \Carbon\Carbon::parse($adj['createdAt'] ?? $adj['created_at'])->format('d M Y, h:i A') }}
                </td>
                <td class="text-left" style="font-size: 9.5px;">{{ $adj['warehouse']['name'] ?? 'Warehouse' }}</td>
                <td class="text-left font-bold" style="font-size: 9.5px;">{{ $adj['recorded_by'] ?? $adj['user']['name'] ?? 'Staff' }}</td>
                <td class="text-center font-mono font-bold" style="color: #0c2340;">
                    {{ $adj['product_code'] ?? $adj['product']['code'] ?? '—' }}
                </td>
                <td class="text-left font-bold">
                    {{ $adj['product_name'] ?? $adj['product']['name'] ?? 'Item' }}
                </td>
                <td class="text-right font-mono font-bold" style="color: #b91c1c;">
                    -{{ number_format(abs((int) ($adj['quantity'] ?? 0))) }}
                </td>
                <td class="text-center">
                    <span class="badge badge-outstock">{{ strtoupper(str_replace('_', ' ', $adj['type'] ?? 'DEDUCTION')) }}</span>
                </td>
                <td class="text-left" style="font-size: 9px; color: #64748b;">
                    {{ $adj['reason'] ?? $adj['notes'] ?? '—' }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 30px; color: #64748b;">
                    No non-sale stock deductions or damage records found for the active filter.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" class="text-left">TOTAL PHYSICAL STOCK DEDUCTIONS & WRITE-OFFS</td>
            <td class="text-right font-mono font-bold" style="color: #b91c1c; font-size: 11px;">
                -{{ number_format($totalDeductedUnits) }}
            </td>
            <td colspan="2" class="text-center font-bold" style="font-size: 8.5px;">{{ count($adjustments) }} Records</td>
        </tr>
    </tfoot>
</table>
@endsection
