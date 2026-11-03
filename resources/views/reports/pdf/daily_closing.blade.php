@extends('reports.pdf.layout')

@section('content')
<!-- EXECUTIVE KPI GRID -->
<div class="kpi-grid">
    <div class="kpi-card">
        <span class="label">GROSS SALES INVOICED</span>
        <div class="value" style="color: #2563eb;">{{ $currency }}{{ number_format($metrics['gross_sales'] ?? 0, 2) }}</div>
        <div class="sub">{{ $metrics['total_invoices'] ?? 0 }} Total Invoices Settled</div>
    </div>

    <div class="kpi-card highlight">
        <span class="label">NET CASH IN TILL (BANK DEPOSIT)</span>
        <div class="value" style="color: #16a34a;">{{ $currency }}{{ number_format($metrics['expected_drawer_cash'] ?? 0, 2) }}</div>
        <div class="sub">Cash to Hand Over & Bank Deposit</div>
    </div>

    <div class="kpi-card">
        <span class="label">DEBTS RECOVERED TODAY</span>
        <div class="value" style="color: #059669;">{{ $currency }}{{ number_format($metrics['debts_recovered'] ?? 0, 2) }}</div>
        <div class="sub">{{ $metrics['debts_recovered_count'] ?? 0 }} Customers Settled Debt</div>
    </div>

    <div class="kpi-card">
        <span class="label">NEW CREDIT ISSUED</span>
        <div class="value" style="color: #dc2626;">{{ $currency }}{{ number_format($metrics['new_debts'] ?? 0, 2) }}</div>
        <div class="sub">{{ $metrics['new_debts_count'] ?? 0 }} Invoices on Credit</div>
    </div>
</div>

<!-- SECTION 1: FINANCIAL & TENDER SUMMARY -->
<div class="section-title">1. Financial Inflows & Tender Collections</div>
<table class="report-table">
    <thead>
        <tr>
            <th>Tender Channel</th>
            <th class="num">Amount Collected</th>
            <th>Verification Status</th>
            <th>Accounting Ledger Mapping</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>💵 Physical Cash in Drawer</strong></td>
            <td class="num font-mono"><strong>{{ $currency }}{{ number_format($metrics['cash_collected'] ?? 0, 2) }}</strong></td>
            <td><span class="status-pill status-paid">Physically Counted in Drawer</span></td>
            <td>Assets: Cash-on-Hand Till Account</td>
        </tr>
        <tr>
            <td><strong>💳 POS Terminal Swipes / Card</strong></td>
            <td class="num font-mono"><strong>{{ $currency }}{{ number_format($metrics['pos_collected'] ?? 0, 2) }}</strong></td>
            <td><span class="status-pill status-partial">Card Settlement Terminal</span></td>
            <td>Assets: Merchant Bank Inflow</td>
        </tr>
        <tr>
            <td><strong>🏛️ Bank Direct Transfers</strong></td>
            <td class="num font-mono"><strong>{{ $currency }}{{ number_format($metrics['transfer_collected'] ?? 0, 2) }}</strong></td>
            <td><span class="status-pill status-partial">Bank Alert Confirmed</span></td>
            <td>Assets: Corporate Bank Account</td>
        </tr>
        <tr>
            <td><strong>🔄 Trade-In Exchange Credit Applied</strong></td>
            <td class="num font-mono"><strong>{{ $currency }}{{ number_format($metrics['exchange_credit_collected'] ?? 0, 2) }}</strong></td>
            <td><span class="status-pill status-unsupplied">Swapped Goods Contra</span></td>
            <td>Contra-Revenue: Returned Exchange Credit</td>
        </tr>
        <tr style="background: #f8fafc; font-weight: 800;">
            <td>TOTAL GROSS TENDER COLLECTED</td>
            <td class="num font-mono" style="font-size: 13px; color: #2563eb;">{{ $currency }}{{ number_format($metrics['total_tender_collected'] ?? 0, 2) }}</td>
            <td colspan="2">Total Settled Receipts Today</td>
        </tr>
    </tbody>
</table>

<!-- SECTION 2: DRAWER CLOSING RECONCILIATION -->
<div class="section-title" style="margin-top: 20px;">2. End-of-Day Drawer Reconciliation Formula</div>
<div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; font-size: 11px; line-height: 1.8;">
    <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding-bottom: 4px;">
        <span>(+) Cash Inflow from Today's Invoices:</span>
        <span class="font-mono"><strong>{{ $currency }}{{ number_format($metrics['cash_collected'] ?? 0, 2) }}</strong></span>
    </div>
    <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding: 4px 0;">
        <span>(+) Cash Collected from Customer Debt Recoveries:</span>
        <span class="font-mono"><strong>{{ $currency }}{{ number_format($metrics['debts_recovered'] ?? 0, 2) }}</strong></span>
    </div>
    @if((float) ($metrics['returns_refunded'] ?? 0) > 0)
    <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #e2e8f0; padding: 4px 0; color: #dc2626;">
        <span>(-) Cash Refunds Paid Out of Drawer:</span>
        <span class="font-mono"><strong>-{{ $currency }}{{ number_format($metrics['returns_refunded'] ?? 0, 2) }}</strong></span>
    </div>
    @endif
    <div style="display: flex; justify-content: space-between; padding-top: 6px; font-weight: 800; font-size: 13px; color: #16a34a;">
        <span>(=) TOTAL PHYSICAL CASH IN HAND TO DEPOSIT:</span>
        <span class="font-mono">{{ $currency }}{{ number_format($metrics['expected_drawer_cash'] ?? 0, 2) }}</span>
    </div>
</div>

<!-- SECTION 3: PHYSICAL INVENTORY & LOGISTICS -->
<div class="section-title">3. Physical Inventory Flow & Branch Logistics</div>
<table class="report-table">
    <thead>
        <tr>
            <th>Operational Category</th>
            <th class="num">Physical Units</th>
            <th>Batches / Trips</th>
            <th>Logistics Status & Notes</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>📦 Supplier Stock In (Restocked)</strong></td>
            <td class="num font-mono" style="color: #16a34a;"><strong>+{{ number_format($metrics['stock_in_units'] ?? 0) }}</strong></td>
            <td>{{ $metrics['stock_in_batches'] ?? 0 }} Supplier Batches</td>
            <td>Added to physical shelf inventory</td>
        </tr>
        <tr>
            <td><strong>🛒 Sales Dispatches (Stock Out)</strong></td>
            <td class="num font-mono" style="color: #dc2626;"><strong>-{{ number_format($metrics['stock_out_units'] ?? 0) }}</strong></td>
            <td>Retail & Wholesale Dispatches</td>
            <td>Fulfilled and handed over to customers</td>
        </tr>
        <tr>
            <td><strong>⚠️ Stock Written-Off (Damages / Lost / Expired)</strong></td>
            <td class="num font-mono" style="color: #d97706;"><strong>-{{ number_format($metrics['damage_units'] ?? 0) }}</strong></td>
            <td>{{ $metrics['damage_batches'] ?? 0 }} Adjustment Logs</td>
            <td>Supervisor-approved shrinkage write-offs</td>
        </tr>
        <tr>
            <td><strong>🚚 Inter-Branch Dispatches (Outbound)</strong></td>
            <td class="num font-mono"><strong>{{ number_format($metrics['outbound_units'] ?? 0) }}</strong></td>
            <td>{{ $metrics['outbound_transfers_count'] ?? 0 }} Transfer Trips</td>
            <td>Dispatched to other company locations</td>
        </tr>
        <tr>
            <td><strong>📥 Inter-Branch Receipts (Inbound)</strong></td>
            <td class="num font-mono"><strong>{{ number_format($metrics['inbound_units'] ?? 0) }}</strong></td>
            <td>{{ $metrics['inbound_transfers_count'] ?? 0 }} Completed Transfers</td>
            <td>Physical items received and verified</td>
        </tr>
        <tr>
            <td><strong>⏳ Pending Customer Deliveries (Backlog)</strong></td>
            <td class="num font-mono" style="color: #2563eb;"><strong>{{ number_format($metrics['unsupplied_backlog_units'] ?? 0) }}</strong></td>
            <td>{{ $metrics['pending_deliveries_count'] ?? 0 }} Orders Awaiting Pickup</td>
            <td>Reserved in warehouse (Value: {{ $currency }}{{ number_format($metrics['pending_deliveries_value'] ?? 0, 2) }})</td>
        </tr>
        <tr style="background: #f1f5f9; font-weight: 800;">
            <td>NET PHYSICAL INVENTORY DELTA</td>
            <td class="num font-mono">{{ number_format($metrics['net_stock_delta'] ?? 0) }} units</td>
            <td colspan="2">Net shelf stock movement for the day</td>
        </tr>
    </tbody>
</table>

<!-- SECTION 4: SECURITY & FORENSIC REVERSALS -->
<div class="section-title" style="margin-top: 20px;">4. Security, Contra-Reversals & Audit Trail</div>
<table class="report-table">
    <thead>
        <tr>
            <th>Security Category</th>
            <th class="num">Count</th>
            <th class="num">Value (₦)</th>
            <th>Integrity Status</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Supervisor Transaction Reversals</td>
            <td class="num font-mono">{{ $metrics['reversals_count'] ?? 0 }}</td>
            <td class="num font-mono">{{ $currency }}{{ number_format($metrics['reversals_amount'] ?? 0, 2) }}</td>
            <td>
                @if(($metrics['reversals_count'] ?? 0) > 0)
                    <span class="status-pill status-partial">Authorized with Contra-Log</span>
                @else
                    <span class="status-pill status-paid">Clean: Zero Reversals Executed ✓</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>Customer Sales Returns Processed</td>
            <td class="num font-mono">{{ $metrics['returns_count'] ?? 0 }}</td>
            <td class="num font-mono">{{ $currency }}{{ number_format($metrics['returns_refunded'] ?? 0, 2) }}</td>
            <td>{{ $metrics['returns_units'] ?? 0 }} units returned to stock</td>
        </tr>
        <tr>
            <td>Cumulative Open Debtor Receivables</td>
            <td class="num font-mono">—</td>
            <td class="num font-mono" style="color: #dc2626;"><strong>{{ $currency }}{{ number_format($metrics['total_cumulative_debt'] ?? 0, 2) }}</strong></td>
            <td>Total outstanding balance across all credit customers</td>
        </tr>
    </tbody>
</table>

<!-- SIGNATURE & AUDIT VERIFICATION BLOCK -->
<div class="signature-section" style="margin-top: 35px; border-top: 2px solid #0f172a; padding-top: 20px;">
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 30px;">
        <div style="border-top: 1px solid #94a3b8; padding-top: 8px; text-align: center;">
            <div style="font-weight: 700; color: #0f172a;">Cashier / Duty Officer</div>
            <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Signature & Date</div>
        </div>
        <div style="border-top: 1px solid #94a3b8; padding-top: 8px; text-align: center;">
            <div style="font-weight: 700; color: #0f172a;">Branch Store Supervisor</div>
            <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Signature & Date</div>
        </div>
        <div style="border-top: 1px solid #94a3b8; padding-top: 8px; text-align: center;">
            <div style="font-weight: 700; color: #0f172a;">Managing Director / Owner</div>
            <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Executive Audit Sign-off</div>
        </div>
    </div>
    <div style="margin-top: 20px; font-size: 9px; color: #94a3b8; text-align: center;">
        Official Enterprise Ledger Record • Generated by Victorious Market POS ({{ config('version.tag', 'v1.0.1') }}) • SHA256 Audit Hash Verified
    </div>
</div>
@endsection

