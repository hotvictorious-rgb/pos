@extends('layouts.app')

@section('title', 'Customer Debts & Part-Payments')

@push('styles')
<style>
    .debt-banner {
        background: linear-gradient(135deg, rgba(139,92,246,0.2) 0%, rgba(220,38,38,0.15) 100%);
        border: 2px solid rgba(139,92,246,0.4);
        border-radius: 20px;
        padding: 1.5rem 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
</style>
@endpush

@section('content')

    <!-- Banner Overview -->
    <div class="debt-banner">
        <div>
            <div style="font-size: 0.85rem; font-weight: 800; color: #c084fc; text-transform: uppercase; letter-spacing: 0.05em;">
                Total Outstanding Customer Debt
            </div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #f87171; margin-top: 0.25rem;">
                ₦{{ number_format($totalOutstandingDebt, 0) }}
            </div>
            <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.25rem;">
                Tracking {{ $debtors->count() }} customer(s) with pending part-payment balances.
            </p>
        </div>
        <div style="font-size: 3rem;">💳</div>
    </div>

    <!-- Debtors List -->
    <div class="card" style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem;">
            Customers with Outstanding Balances
        </h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Phone Number</th>
                        <th style="color: #f87171;">Amount Owed</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($debtors as $debtor)
                    <tr>
                        <td>
                            <strong style="font-size: 1.05rem;">{{ $debtor->name }}</strong>
                        </td>
                        <td>{{ $debtor->phone ?? 'N/A' }}</td>
                        <td style="font-size: 1.2rem; font-weight: 800; color: #f87171;">
                            ₦{{ number_format($debtor->total_debt, 0) }}
                        </td>
                        <td>
                            <span class="badge badge-danger">⚠️ Owning Balance</span>
                        </td>
                        <td>
                            <button class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.85rem;"
                                    onclick="openPaymentModal({{ $debtor->id }}, '{{ addslashes($debtor->name) }}', {{ $debtor->total_debt }})">
                                💵 Record Payment
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            🎉 Zero Customer Debts! All accounts are fully paid.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Payments History -->
    <div class="card">
        <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem;">
            Recent Part-Payment Recoveries (Audit Log)
        </h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Customer</th>
                        <th>Payment Method</th>
                        <th>Amount Paid</th>
                        <th>New Balance</th>
                        <th>Cashier</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPayments as $pay)
                    <tr>
                        <td>{{ date('d M Y, h:i A', strtotime($pay->created_at)) }}</td>
                        <td><strong>{{ $pay->customer->name ?? 'Customer' }}</strong></td>
                        <td>
                            <span class="badge badge-info">{{ $pay->payment_method ?? 'CASH' }}</span>
                        </td>
                        <td style="font-weight: 800; color: #4ade80;">
                            +₦{{ number_format($pay->amount, 0) }}
                        </td>
                        <td style="color: #cbd5e1;">
                            ₦{{ number_format($pay->balance_after, 0) }}
                        </td>
                        <td>{{ $pay->recorded_by }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            No payment recoveries recorded yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Record Customer Payment -->
    <div id="modalPayDebt" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">💵 Record Part-Payment</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;" id="modalCustomerSubtitle">
                Collecting payment for customer.
            </p>

            <form id="payDebtForm" method="POST" action="" onsubmit="return confirmDebtPayment(event)">
                @csrf
                <div class="form-group">
                    <label>Current Debt Balance</label>
                    <input type="text" id="modalCurrentDebt" readonly style="color: #f87171; font-weight: 800; font-size: 1.2rem;">
                </div>

                <div class="form-group">
                    <label>Amount Paying Now (₦)</label>
                    <input type="number" name="amount" id="modalAmountPaying" min="1" step="any" placeholder="e.g. 5000" required onkeyup="calcNewBalance()">
                </div>

                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" id="modalPayMethod" required>
                        <option value="CASH">💵 Cash</option>
                        <option value="POS">💳 POS Terminal Card</option>
                        <option value="TRANSFER">🏦 Direct Bank Transfer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Reference / Bank Receipt No.</label>
                    <input type="text" name="reference_no" placeholder="Optional reference code">
                </div>

                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 12px; padding: 0.85rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                        <span>New Balance Remaining:</span>
                        <strong style="color: #fbbf24;" id="modalNewBalance">₦0</strong>
                    </div>
                </div>

                <div style="display: flex; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closePaymentModal()">Cancel</button>
                    <button type="submit" class="btn btn-success" style="flex: 1;">✓ Save Payment</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
let currentCustomerDebt = 0;
let currentCustomerName = '';

function openPaymentModal(id, name, debt) {
    currentCustomerDebt = debt;
    currentCustomerName = name;
    document.getElementById('payDebtForm').action = '/debts/pay/' + id;
    document.getElementById('modalCustomerSubtitle').textContent = 'Collecting debt installment from ' + name;
    document.getElementById('modalCurrentDebt').value = '₦' + Math.round(debt).toLocaleString('en-US');
    document.getElementById('modalAmountPaying').value = '';
    document.getElementById('modalNewBalance').textContent = '₦' + Math.round(debt).toLocaleString('en-US');
    document.getElementById('modalPayDebt').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('modalPayDebt').style.display = 'none';
}

function calcNewBalance() {
    const paying = parseFloat(document.getElementById('modalAmountPaying').value) || 0;
    const newBal = Math.max(0, currentCustomerDebt - paying);
    document.getElementById('modalNewBalance').textContent = '₦' + Math.round(newBal).toLocaleString('en-US');
}

function confirmDebtPayment(event) {
    const paying = parseFloat(document.getElementById('modalAmountPaying').value) || 0;
    if (paying <= 0) {
        alert('⚠️ Please enter a valid payment amount greater than 0.');
        if (event) event.preventDefault();
        return false;
    }
    const method = document.getElementById('modalPayMethod').value;
    const newBal = Math.max(0, currentCustomerDebt - paying);
    const msg = `💵 Confirm Customer Debt Repayment:\n\n• Customer: ${currentCustomerName}\n• Amount Received: ₦${Math.round(paying).toLocaleString('en-US')} (${method})\n• Previous Debt: ₦${Math.round(currentCustomerDebt).toLocaleString('en-US')}\n• New Remaining Balance: ₦${Math.round(newBal).toLocaleString('en-US')}\n\nThis will record this payment into today's revenue and reduce customer's debt. Proceed?`;
    if (!confirm(msg)) {
        if (event) event.preventDefault();
        return false;
    }
    return true;
}
</script>
@endpush
