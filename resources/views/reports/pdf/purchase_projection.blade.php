@extends('reports.pdf.layout')

@section('content')
<div style="margin-bottom: 12px; padding: 10px 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <strong style="color: #0c2340; font-size: 11px;">Restock Algorithm:</strong>
        <span style="color: #475569; font-size: 10.5px;">Strictly 30-Day Rolling Velocity ({{ $projection['period_label'] ?? 'Trailing 30 Days' }})</span>
    </div>
    <div style="font-size: 10.5px;">
        <span style="color: #64748b;">Target Branch:</span>
        <strong style="color: #0f766e;">{{ $projection['warehouse_name'] ?? 'All Branches' }}</strong>
    </div>
</div>

<table class="executive-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 32px;">RANK</th>
            <th class="text-center" style="width: 75px;">SKU</th>
            <th class="text-left">PRODUCT NAME</th>
            <th class="text-left" style="width: 85px;">CATEGORY</th>
            <th class="text-center" style="width: 60px;">30D SOLD</th>
            <th class="text-center" style="width: 65px;">DAILY BURN</th>
            <th class="text-center" style="width: 65px;">7D DEMAND</th>
            <th class="text-center" style="width: 50px;">BUFFER</th>
            <th class="text-center" style="width: 65px;">SHELF STOCK</th>
            <th class="text-center" style="width: 75px;">RUNWAY</th>
            <th class="text-center" style="width: 85px;">STATUS</th>
            <th class="text-center" style="width: 80px; background: #1e3a8a !important; color: #ffffff;">REORDER QTY</th>
            <th class="text-right" style="width: 80px;">PRICE (₦)</th>
            <th class="text-right" style="width: 95px; background: #064e3b !important; color: #ffffff;">EST. CAPITAL (₦)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($projection['items'] as $row)
            @php
                $isCritical = in_array($row['urgency'], ['OUT_OF_STOCK', 'CRITICAL']);
                $isReorderNeeded = $row['suggested_reorder'] > 0;
            @endphp
            <tr style="{{ $isCritical ? 'background-color: #fff1f2;' : '' }}">
                <td class="text-center font-mono font-bold" style="color: #0c2340;">#{{ $row['rank'] }}</td>
                <td class="text-center font-mono font-bold" style="font-size: 8.5px; color: #0369a1;">{{ $row['sku'] }}</td>
                <td class="text-left font-bold" style="color: #0f172a;">
                    {{ $row['name'] }}
                    @if(!empty($row['size']))
                        <span style="font-size: 8.5px; color: #64748b; font-weight: normal;">({{ $row['size'] }})</span>
                    @endif
                </td>
                <td class="text-left text-muted" style="font-size: 8.5px;">{{ $row['category'] }}</td>
                <td class="text-center font-mono font-bold" style="color: #0c2340;">{{ number_format($row['net_qty_30']) }}</td>
                <td class="text-center font-mono" style="font-size: 9px; color: #475569;">{{ number_format($row['daily_velocity'], 2) }}/d</td>
                <td class="text-center font-mono" style="font-size: 9px; color: #334155;">{{ number_format($row['demand_7_days'], 1) }}</td>
                <td class="text-center font-mono text-muted" style="font-size: 9px;">{{ $row['safety_buffer'] }}</td>
                <td class="text-center font-mono font-bold" style="font-size: 10px; color: {{ $row['physical_stock'] <= 0 ? '#b91c1c' : '#0c2340' }};">
                    {{ number_format($row['physical_stock']) }}
                </td>
                <td class="text-center font-mono font-bold" style="font-size: 9px; color: {{ $isCritical ? '#b91c1c' : '#334155' }};">
                    @if($row['runway_days'] >= 900)
                        <span style="color: #64748b;">—</span>
                    @else
                        {{ number_format($row['runway_days'], 1) }}d
                    @endif
                </td>
                <td class="text-center">
                    @if($row['urgency'] === 'OUT_OF_STOCK')
                        <span class="badge badge-danger">OUT OF STOCK</span>
                    @elseif($row['urgency'] === 'CRITICAL')
                        <span class="badge badge-danger">CRITICAL &lt;2d</span>
                    @elseif($row['urgency'] === 'LOW')
                        <span class="badge badge-warning">LOW 2-5d</span>
                    @elseif($row['urgency'] === 'ADEQUATE')
                        <span class="badge badge-success">ADEQUATE</span>
                    @else
                        <span class="badge badge-info">HEALTHY</span>
                    @endif
                </td>
                <td class="text-center font-mono font-bold" style="font-size: 11px; {{ $isReorderNeeded ? 'background: #eff6ff; color: #1e40af;' : 'color: #94a3b8;' }}">
                    @if($isReorderNeeded)
                        +{{ number_format($row['suggested_reorder']) }}
                    @else
                        0
                    @endif
                </td>
                <td class="text-right font-mono" style="font-size: 9px;">₦{{ number_format($row['unit_price'], 2) }}</td>
                <td class="text-right font-mono font-bold" style="font-size: 10px; {{ $isReorderNeeded ? 'background: #f0fdf4; color: #047857;' : 'color: #94a3b8;' }}">
                    ₦{{ number_format($row['est_capital'], 2) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="14" class="text-center" style="padding: 30px; color: #64748b;">
                    No sales recorded in the past 30 days to project purchase reorders.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="11" class="text-left font-bold">TOTAL ESTIMATED 7-DAY REORDER REQUIREMENTS</td>
            <td class="text-center font-mono font-bold" style="font-size: 12px; background: #dbeafe; color: #1e3a8a;">
                +{{ number_format($projection['total_reorder_units'] ?? 0) }}
            </td>
            <td></td>
            <td class="text-right font-mono font-bold" style="font-size: 12px; background: #d1fae5; color: #065f46;">
                ₦{{ number_format($projection['total_reorder_capital'] ?? 0, 2) }}
            </td>
        </tr>
    </tfoot>
</table>
@endsection
