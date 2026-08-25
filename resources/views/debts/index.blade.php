@extends('layouts.app')

@section('title', 'Customer Debts & Part-Payments')

@push('styles')
<style>
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .summary-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.25rem;
    }

    .summary-card h4 {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 0.35rem;
        letter-spacing: 0.05em;
    }
    .summary-card .val {
        font-size: 1.35rem;
        font-weight: 800;
    }

    .filter-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .date-pill {
        padding: 0.35rem 0.75rem;
        border-radius: 99px;
        font-size: 0.78rem;
        font-weight: 700;
        border: 1px solid var(--border);
        background: rgba(11, 15, 25, 0.6);
        color: var(--text-muted);
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s;
    }
    .date-pill.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    .table-wrap {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { background: rgba(11, 15, 25, 0.8); padding: 1rem 1.25rem; font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border); }
    td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); font-size: 0.95rem; }
    tr:last-child td { border-bottom: none; }
</style>
@endpush

@section('content')

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 1.75rem;">💳</span>
                <h2 style="font-size: 1.5rem; font-weight: 800;">Customer Debts & Part-Payments Hub</h2>
            </div>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Real-time debtor ledger with part-payment collection and zero-decimal accounting.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="{{ route('transactions.index', ['tab' => 'debts']) }}" class="btn btn-secondary">
                📜 Full Debts Ledger
            </a>
            <a href="{{ route('pos.index') }}" class="btn btn-primary">
                💰 New Sale
            </a>
        </div>
    </div>

    <!-- Summary Overview Grid -->
    <div class="summary-grid">
        <div class="summary-card">
            <h4>Total Outstanding Debt</h4>
            <div class="val" style="color: #f87171;">₦{{ number_format($totalOutstandingDebt, 0) }}</div>
        </div>
        <div class="summary-card">
            <h4>Active Debtors</h4>
            <div class="val" style="color: #fbbf24;">{{ number_format($totalDebtorsCount) }} customers</div>
        </div>
        <div class="summary-card">
            <h4>High-Risk Accounts (≥ ₦100k)</h4>
            <div class="val" style="color: #f87171;">{{ number_format($highRiskDebtorsCount) }}</div>
        </div>
    </div>

    <!-- Multi-Criteria Filter Card -->
    <div class="filter-card">
        <form method="GET" action="{{ route('debts.index') }}">
            <!-- Quick Debt Brackets -->
            <div style="display: flex; gap: 0.4rem; margin-bottom: 0.85rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Debt Bracket:</span>
                <a href="{{ route('debts.index', array_merge(request()->except('debt_bracket'), ['debt_bracket' => 'ALL'])) }}" class="date-pill {{ $debtBracket === 'ALL' ? 'active' : '' }}">All Debtors</a>
                <a href="{{ route('debts.index', array_merge(request()->except('debt_bracket'), ['debt_bracket' => 'HIGH'])) }}" class="date-pill {{ $debtBracket === 'HIGH' ? 'active' : '' }}" style="{{ $debtBracket === 'HIGH' ? 'background: #dc2626; border-color: #ef4444;' : '' }}">🔴 High Debt (≥ ₦100,000)</a>
                <a href="{{ route('debts.index', array_merge(request()->except('debt_bracket'), ['debt_bracket' => 'MEDIUM'])) }}" class="date-pill {{ $debtBracket === 'MEDIUM' ? 'active' : '' }}">🟠 Medium Debt (₦20k - ₦100k)</a>
                <a href="{{ route('debts.index', array_merge(request()->except('debt_bracket'), ['debt_bracket' => 'LOW'])) }}" class="date-pill {{ $debtBracket === 'LOW' ? 'active' : '' }}">🟢 Low Debt (< ₦20,000)</a>
            </div>

            <div class="grid-2" style="gap: 0.75rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Sort Order</label>
                    <select name="sort_by">
                        <option value="highest_debt" {{ request('sort_by') === 'highest_debt' ? 'selected' : '' }}>Highest Debt First</option>
                        <option value="lowest_debt" {{ request('sort_by') === 'lowest_debt' ? 'selected' : '' }}>Lowest Debt First</option>
                        <option value="name_asc" {{ request('sort_by') === 'name_asc' ? 'selected' : '' }}>Customer Name (A - Z)</option>
                        <option value="name_desc" {{ request('sort_by') === 'name_desc' ? 'selected' : '' }}>Customer Name (Z - A)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Customer Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="🔍 Search customer name, phone, address...">
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 0.85rem; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    🔍 Apply Filters
                </button>
                <a href="{{ route('debts.index') }}" class="btn btn-secondary" style="padding: 0.65rem 1rem;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Debtors List Table -->
    <div class="card" style="margin-bottom: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800;">
                Customers with Outstanding Balances
            </h3>
            <div style="width: 280px;">
                <input type="text" placeholder="⚡ Live filter debtors..." onkeyup="filterTableRows('debtorsTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
            </div>
        </div>

        <div class="table-wrap">
            <table id="debtorsTable">
                <thead>
                    <tr>
                        <th>Customer Details</th>
                        <th>Phone Number</th>
                        <th>Address / Location</th>
                        <th style="color: #f87171;">Amount Owed</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($debtors as $debtor)
                    <tr>
                        <td>
                            <strong style="font-size: 1.05rem; color: #f8fafc;">{{ $debtor->name }}</strong>
                            <div style="font-size: 0.75rem; margin-top: 0.2rem;">
                                <span class="badge badge-info">{{ $debtor->customer_code ?? 'CUST-' . str_pad($debtor->id, 4, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </td>
                        <td>
                            <strong style="color: #93c5fd;">{{ $debtor->phone ?: 'N/A' }}</strong>
                        </td>
                        <td style="color: #cbd5e1;">{{ $debtor->address ?: 'Walk-in' }}</td>
                        <td style="font-size: 1.2rem; font-weight: 800; color: #f87171;">
                            ₦{{ number_format($debtor->total_debt, 0) }}
                        </td>
                        <td>
                            @if($debtor->total_debt >= 100000)
                                <span class="badge badge-danger">🔴 High Risk</span>
                            @else
                                <span class="badge badge-warning">⚠️ Owes Balance</span>
                            @endif
                        </td>
                        <td>
                            @if(auth()->user()?->role !== 'viewer')
                                <button class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.85rem;"
                                        onclick="openPaymentModal({{ $debtor->id }}, '{{ addslashes($debtor->name) }}', {{ $debtor->total_debt }})">
                                    💵 Record Payment
                                </button>
                            @else
                                <span style="font-size: 0.78rem; color: #facc15; font-weight: 700; background: rgba(234,179,8,0.1); padding: 0.3rem 0.6rem; border-radius: 6px; border: 1px solid rgba(234,179,8,0.3);">👑 Read-Only</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            🎉 Zero Customer Debts matching your filters!
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1.25rem;">
            {{ $debtors->links() }}
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
                        <th>New Balance After</th>
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
                        <td style="color: #cbd5e1; font-weight: 700;">
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

    <!-- Modal: Record Payment -->
    <div id="modalPayment" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;" id="payCustTitle">💵 Record Customer Payment</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Deducts collected money from customer balance and creates an audit entry.
            </p>

            <form id="paymentForm" method="POST" action="" onsubmit="return validateDebtPayment(event)">
                @csrf
                <div class="form-group">
                    <label>Current Debt Balance</label>
                    <input type="text" id="payCurrentDebt" disabled style="font-size: 1.1rem; font-weight: 800; color: #f87171; background: rgba(0,0,0,0.3);">
                </div>

                <div class="form-group">
                    <label>Amount Collected (₦)</label>
                    <input type="number" name="amount" id="payAmount" min="1" step="1" placeholder="e.g. 20000" required>
                </div>

                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" id="payMethod" required>
                        <option value="CASH">Cash</option>
                        <option value="POS">POS Terminal</option>
                        <option value="TRANSFER">Bank Transfer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Bank Reference / Receipt #</label>
                    <input type="text" name="reference_no" placeholder="Optional reference">
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <input type="text" name="notes" placeholder="Optional notes">
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalPayment')">Cancel</button>
                    <button type="submit" class="btn btn-success" style="flex: 1;">✓ Receive Payment</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
let activeDebtorDebt = 0;

function filterTableRows(tableId, query) {
    const q = query.toLowerCase().trim();
    const table = document.getElementById(tableId);
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(q) ? '' : 'none';
    });
}

function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function openPaymentModal(custId, custName, currentDebt) {
    activeDebtorDebt = parseFloat(currentDebt) || 0;
    document.getElementById('payCustTitle').textContent = '💵 Payment from: ' + custName;
    document.getElementById('payCurrentDebt').value = '₦' + Math.round(currentDebt).toLocaleString('en-US');
    document.getElementById('payAmount').max = currentDebt;
    document.getElementById('payAmount').value = '';
    document.getElementById('paymentForm').action = '/debts/' + custId + '/payment';
    openModal('modalPayment');
}

function validateDebtPayment(e) {
    const amount = parseFloat(document.getElementById('payAmount').value) || 0;
    const method = document.getElementById('payMethod').value;
    const errors = [];

    if (amount <= 0) {
        errors.push({
            title: 'Invalid Payment Amount',
            desc: 'Payment recovery amount must be greater than ₦0.',
            focus: 'payAmount'
        });
    }

    if (activeDebtorDebt > 0 && amount > activeDebtorDebt) {
        errors.push({
            title: 'Payment Exceeds Outstanding Debt',
            desc: `The customer owes <strong>₦${Math.round(activeDebtorDebt).toLocaleString('en-US')}</strong>, but you entered <strong>₦${Math.round(amount).toLocaleString('en-US')}</strong>.<br>Overpayment is not permitted.`,
            focus: 'payAmount'
        });
    }

    if (!method) {
        errors.push({
            title: 'Payment Method Required',
            desc: 'Please choose Cash, POS Terminal, or Bank Transfer.',
            focus: 'payMethod'
        });
    }

    if (errors.length > 0) {
        e.preventDefault();
        showActionBlockedModal({
            title: 'Debt Payment Blocked',
            subtitle: 'Please resolve the following debt ledger validation errors:',
            errors: errors
        });
        return false;
    }
    return true;
}
</script>
@endpush
