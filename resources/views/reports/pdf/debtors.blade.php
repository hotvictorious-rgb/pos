@extends('reports.pdf.layout')

@section('content')
<table class="executive-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th class="text-left">Customer Name</th>
            <th class="text-left" style="width: 120px;">Phone Number</th>
            <th class="text-left" style="width: 130px;">Primary Branch</th>
            <th class="text-center" style="width: 120px;">Aging Status</th>
            <th class="text-center" style="width: 110px;">Oldest Invoice</th>
            <th class="text-right" style="width: 130px;">Delivered Debt (₦)</th>
            <th class="text-right" style="width: 140px;">Installment / Unsup (₦)</th>
            <th class="text-right" style="width: 140px; background: #07172c !important;">TOTAL OWED (₦)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($debtors as $d)
            @php
                $tot = (float) ($d['total_debt'] ?? $d['branch_debt'] ?? 0);
                $del = (float) ($d['delivered_debt'] ?? 0);
                $ins = (float) ($d['installment_debt'] ?? 0);
                $aging = $d['aging_category'] ?? 'CURRENT (0-7 Days)';
            @endphp
            <tr>
                <td class="text-center text-muted font-mono">{{ $loop->iteration }}</td>
                <td class="text-left font-bold" style="color: #0c2340;">
                    {{ $d['name'] ?? 'Unknown Customer' }}
                </td>
                <td class="text-left font-mono" style="color: #475569;">
                    {{ $d['phone'] ?? '—' }}
                </td>
                <td class="text-left" style="font-size: 9.5px;">
                    {{ $d['warehouse']['name'] ?? $branchName ?? 'Branch Network' }}
                </td>
                <td class="text-center">
                    @if(str_contains($aging, 'CRITICAL'))
                        <span class="badge badge-outstock">{{ $aging }}</span>
                    @elseif(str_contains($aging, 'DUE'))
                        <span class="badge badge-lowstock">{{ $aging }}</span>
                    @else
                        <span class="badge badge-instock">{{ $aging }}</span>
                    @endif
                </td>
                <td class="text-center font-mono" style="font-size: 9px; color: #64748b;">
                    {{ !empty($d['oldest_debt_date']) ? \Carbon\Carbon::parse($d['oldest_debt_date'])->format('d M Y') : '—' }}
                </td>
                <td class="text-right font-mono font-bold" style="color: #b45309;">
                    ₦{{ number_format($del, 2) }}
                </td>
                <td class="text-right font-mono" style="color: #475569;">
                    ₦{{ number_format($ins, 2) }}
                </td>
                <td class="text-right font-mono font-bold" style="color: #b91c1c; font-size: 10.5px; background: rgba(185, 28, 28, 0.03);">
                    ₦{{ number_format($tot, 2) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 30px; color: #64748b;">
                    No outstanding debtor accounts found matching the active filter criteria.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" class="text-left">TOTAL DEBTORS CREDIT EXPOSURE</td>
            <td class="text-right font-mono font-bold" style="color: #b45309; font-size: 11px;">
                ₦{{ number_format($totalDeliveredDebt, 2) }}
            </td>
            <td class="text-right font-mono font-bold" style="color: #475569; font-size: 11px;">
                ₦{{ number_format($totalInstallmentDebt, 2) }}
            </td>
            <td class="text-right font-mono font-bold" style="color: #b91c1c; font-size: 11.5px;">
                ₦{{ number_format($grandTotalDebt, 2) }}
            </td>
        </tr>
    </tfoot>
</table>
@endsection
