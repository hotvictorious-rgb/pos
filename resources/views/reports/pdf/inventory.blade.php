@extends('reports.pdf.layout')

@section('content')
<table class="executive-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 32px;">#</th>
            <th class="text-center" style="width: 75px;">SKU</th>
            <th class="text-left">Product Name & Specifications</th>
            <th class="text-left" style="width: 85px;">Brand</th>
            <th class="text-left" style="width: 110px;">Category</th>
            @foreach($warehouses as $wh)
                <th class="text-right" style="width: 90px;">{{ strtoupper($wh->name) }}</th>
            @endforeach
            <th class="text-right" style="width: 95px; background: #07172c !important;">TOTAL STOCK</th>
            <th class="text-right" style="width: 90px;">UNIT PRICE</th>
            <th class="text-right" style="width: 105px;">VALUATION (₦)</th>
            <th class="text-center" style="width: 85px;">HEALTH</th>
        </tr>
    </thead>
    <tbody>
        @forelse($items as $item)
            <tr data-stock="{{ $item['total_stock'] }}">
                <td class="text-center text-muted font-mono">{{ $loop->iteration }}</td>
                <td class="text-center font-mono font-bold" style="color: #0c2340; letter-spacing: 0.4px;">
                    {{ $item['sku'] }}
                </td>
                <td class="text-left font-bold">
                    {{ $item['name'] }}
                    @if(!empty($item['size']) && $item['size'] !== 'Standard' && !str_contains($item['name'], $item['size']))
                        <span style="font-weight: normal; color: #64748b;">({{ $item['size'] }})</span>
                    @endif
                </td>
                <td class="text-left text-muted">{{ $item['brand'] ?? '—' }}</td>
                <td class="text-left" style="font-size: 9px; color: #475569;">{{ $item['category'] ?? 'General' }}</td>
                
                @foreach($warehouses as $wh)
                    @php $qty = $item['branches'][$wh->id] ?? 0; @endphp
                    <td class="text-right font-mono {{ $qty > 0 ? 'font-bold' : 'text-muted' }}" style="{{ $qty > 0 ? 'color: #0f172a;' : 'color: #cbd5e1;' }}">
                        {{ number_format($qty) }}
                    </td>
                @endforeach

                <td class="text-right font-mono font-bold" style="background: rgba(12, 35, 64, 0.03); color: #0c2340; font-size: 10.5px;">
                    {{ number_format($item['total_stock']) }}
                </td>
                <td class="text-right font-mono">
                    ₦{{ number_format($item['unit_price'], 2) }}
                </td>
                <td class="text-right font-mono font-bold" style="color: {{ $item['valuation'] > 0 ? '#0f766e' : '#94a3b8' }};">
                    ₦{{ number_format($item['valuation'], 2) }}
                </td>
                <td class="text-center">
                    @if($item['total_stock'] <= 0)
                        <span class="badge badge-outstock">OUT OF STOCK</span>
                    @elseif($item['total_stock'] <= ($item['min_stock'] ?? 5))
                        <span class="badge badge-lowstock">LOW (≤{{ $item['min_stock'] ?? 5 }})</span>
                    @else
                        <span class="badge badge-instock">IN STOCK</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ 7 + count($warehouses) }}" class="text-center" style="padding: 30px; color: #64748b;">
                    No products or stock records found matching the active filter criteria.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="text-left">TOTAL PHYSICAL STOCK & VALUATION SUMMARY</td>
            @foreach($warehouses as $wh)
                <td class="text-right font-mono font-bold">
                    {{ number_format($branchTotals[$wh->id] ?? 0) }}
                </td>
            @endforeach
            <td class="text-right font-mono font-bold" style="font-size: 11px;">
                {{ number_format($grandTotalUnits) }}
            </td>
            <td class="text-right">—</td>
            <td class="text-right font-mono font-bold" style="color: #0f766e; font-size: 11px;">
                ₦{{ number_format($grandTotalValuation, 2) }}
            </td>
            <td class="text-center font-bold" style="font-size: 8.5px;">
                {{ count($items) }} SKUs
            </td>
        </tr>
    </tfoot>
</table>
@endsection
