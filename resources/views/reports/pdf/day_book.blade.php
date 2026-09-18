@extends('reports.pdf.layout')

@section('content')
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
    <!-- Financial Cash Drawer & POS Flow -->
    <div>
        <h3 style="font-size: 11px; font-weight: 700; color: #0c2340; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
            💵 Cash Flow & POS Settlement Breakdown
        </h3>
        <table class="executive-table">
            <thead>
                <tr>
                    <th class="text-left">Revenue & Inflow Stream</th>
                    <th class="text-right" style="width: 140px;">Amount (₦)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-left">Direct Sales Gross Realized</td>
                    <td class="text-right font-mono font-bold">₦{{ number_format((float) ($dayBook['financials']['gross_sales'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td class="text-left" style="padding-left: 20px; color: #475569;">↳ Physical Cash in Drawer</td>
                    <td class="text-right font-mono font-bold" style="color: #0f766e;">₦{{ number_format((float) ($dayBook['financials']['cash_collected'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td class="text-left" style="padding-left: 20px; color: #475569;">↳ Electronic POS / Bank Transfers</td>
                    <td class="text-right font-mono font-bold" style="color: #2563eb;">₦{{ number_format((float) ($dayBook['financials']['pos_collected'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td class="text-left">Debtors Old Credit Recovered Today</td>
                    <td class="text-right font-mono font-bold" style="color: #059669;">₦{{ number_format((float) ($dayBook['financials']['debt_recovered'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td class="text-left">Customer Refunds & Returns Deducted</td>
                    <td class="text-right font-mono font-bold" style="color: #b91c1c;">-₦{{ number_format((float) ($dayBook['financials']['refunds_paid'] ?? 0), 2) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td class="text-left">NET CASH & BANK SETTLEMENTS</td>
                    <td class="text-right font-mono font-bold" style="font-size: 12px; color: #0c2340;">
                        ₦{{ number_format((float) ($dayBook['financials']['net_settlement'] ?? 0), 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Stock & Logistics Summary -->
    <div>
        <h3 style="font-size: 11px; font-weight: 700; color: #0c2340; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
            📦 Physical Stock Movement & Audit Count
        </h3>
        <table class="executive-table">
            <thead>
                <tr>
                    <th class="text-left">Stock Inflow / Outflow Metric</th>
                    <th class="text-right" style="width: 140px;">Quantity (Units)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-left">Sales Units Dispatched & Handed Over</td>
                    <td class="text-right font-mono font-bold">{{ number_format((int) ($dayBook['logistics']['sales_units_dispatched'] ?? 0)) }}</td>
                </tr>
                <tr>
                    <td class="text-left">Inter-Branch Transfers Sent (Out)</td>
                    <td class="text-right font-mono font-bold">{{ number_format((int) ($dayBook['logistics']['transfers_out'] ?? 0)) }}</td>
                </tr>
                <tr>
                    <td class="text-left">Inter-Branch Transfers Received (In)</td>
                    <td class="text-right font-mono font-bold">{{ number_format((int) ($dayBook['logistics']['transfers_in'] ?? 0)) }}</td>
                </tr>
                <tr>
                    <td class="text-left">Supplier Restocks / Inflow Received</td>
                    <td class="text-right font-mono font-bold" style="color: #0f766e;">+{{ number_format((int) ($dayBook['logistics']['restocks_in'] ?? 0)) }}</td>
                </tr>
                <tr>
                    <td class="text-left">Damaged / Non-Sale Deductions</td>
                    <td class="text-right font-mono font-bold" style="color: #b91c1c;">-{{ number_format((int) ($dayBook['logistics']['damages_units'] ?? 0)) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td class="text-left">TOTAL NET UNIT TURNOVER</td>
                    <td class="text-right font-mono font-bold" style="font-size: 12px; color: #0c2340;">
                        {{ number_format((int) ($dayBook['logistics']['net_units_moved'] ?? 0)) }} Units
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Cashier / Staff Shift Audit Breakdown Table -->
<h3 style="font-size: 11px; font-weight: 700; color: #0c2340; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
    👤 Cashier / Staff Performance & Drawer Reconciliation
</h3>
<table class="executive-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 35px;">#</th>
            <th class="text-left">Cashier / Staff Name</th>
            <th class="text-left" style="width: 140px;">Branch Assigned</th>
            <th class="text-right" style="width: 100px;">Invoices</th>
            <th class="text-right" style="width: 110px;">Units Sold</th>
            <th class="text-right" style="width: 130px;">Cash Handled (₦)</th>
            <th class="text-right" style="width: 130px;">POS Handled (₦)</th>
            <th class="text-right" style="width: 140px; background: #07172c !important;">TOTAL VOLUME (₦)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($dayBook['staff_breakdown'] ?? [] as $idx => $st)
            <tr>
                <td class="text-center text-muted font-mono">{{ $idx + 1 }}</td>
                <td class="text-left font-bold" style="color: #0c2340;">{{ $st['name'] }}</td>
                <td class="text-left" style="font-size: 9.5px;">{{ $st['branch'] }}</td>
                <td class="text-right font-mono font-bold">{{ number_format($st['invoices_count']) }}</td>
                <td class="text-right font-mono">{{ number_format($st['units_sold']) }}</td>
                <td class="text-right font-mono font-bold" style="color: #0f766e;">₦{{ number_format($st['cash_amount'], 2) }}</td>
                <td class="text-right font-mono font-bold" style="color: #2563eb;">₦{{ number_format($st['pos_amount'], 2) }}</td>
                <td class="text-right font-mono font-bold" style="font-size: 10.5px;">₦{{ number_format($st['total_amount'], 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="text-center" style="padding: 24px; color: #64748b;">
                    No cashier shift activity recorded for the active date filter.
                </td>
            </tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="text-left">CONSOLIDATED SHIFT RECONCILIATION</td>
            <td class="text-right font-mono font-bold">{{ number_format($dayBook['total_invoices'] ?? 0) }}</td>
            <td class="text-right font-mono font-bold">{{ number_format($dayBook['total_units_sold'] ?? 0) }}</td>
            <td class="text-right font-mono font-bold" style="color: #0f766e;">₦{{ number_format($dayBook['total_cash'] ?? 0, 2) }}</td>
            <td class="text-right font-mono font-bold" style="color: #2563eb;">₦{{ number_format($dayBook['total_pos'] ?? 0, 2) }}</td>
            <td class="text-right font-mono font-bold" style="font-size: 11.5px;">₦{{ number_format($dayBook['total_volume'] ?? 0, 2) }}</td>
        </tr>
    </tfoot>
</table>
@endsection
