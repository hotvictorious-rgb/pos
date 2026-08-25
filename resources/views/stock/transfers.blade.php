@extends('layouts.app')

@section('title', 'Inter-Branch Transfers')

@push('styles')
<style>
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

    .transfer-pending-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2rem;
    }

    .transfer-card {
        background: var(--card-bg);
        border: 2px solid rgba(59,130,246,0.5);
        border-radius: 18px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        transition: transform 0.2s;
    }
    .transfer-card:hover { transform: translateY(-2px); border-color: #3b82f6; }

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
                <span style="font-size: 1.75rem;">🚚</span>
                <h2 style="font-size: 1.5rem; font-weight: 800;">Inter-Branch Transfers & Shipments</h2>
            </div>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Move goods between branches with 2-step theft-proof dispatch and count verification.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            @if(auth()->user()?->role !== 'viewer')
                <button class="btn btn-primary" onclick="openModal('modalDispatchTransfer')">
                    🚚 Dispatch New Transfer
                </button>
            @else
                <span style="font-size: 0.82rem; font-weight: 800; color: #facc15; background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.4); padding: 0.5rem 1rem; border-radius: 10px;">
                    👑 Executive Observer
                </span>
            @endif
            <a href="{{ route('stock.index') }}" class="btn btn-secondary">
                📦 Stock Hub
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <h4>In-Transit on Vehicles</h4>
            <div class="val" style="color: #fbbf24;">{{ number_format($pendingCount) }} shipments</div>
        </div>
        <div class="summary-card">
            <h4>Verified & Received</h4>
            <div class="val" style="color: #4ade80;">{{ number_format($receivedCount) }}</div>
        </div>
        <div class="summary-card">
            <h4>Discrepancy Variance Alerts</h4>
            <div class="val" style="color: #f87171;">{{ number_format($discrepancyCount) }}</div>
        </div>
    </div>

    <!-- Multi-Criteria Filter Card -->
    <div class="filter-card">
        <form method="GET" action="{{ route('stock.transfers') }}">
            <!-- Quick Date Pills -->
            <div style="display: flex; gap: 0.4rem; margin-bottom: 0.85rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Quick Dates:</span>
                <a href="{{ route('stock.transfers', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'ALL'])) }}" class="date-pill {{ $datePreset === 'ALL' && !request('from_date') ? 'active' : '' }}">All Time</a>
                <a href="{{ route('stock.transfers', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'TODAY'])) }}" class="date-pill {{ $datePreset === 'TODAY' ? 'active' : '' }}">Today</a>
                <a href="{{ route('stock.transfers', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'YESTERDAY'])) }}" class="date-pill {{ $datePreset === 'YESTERDAY' ? 'active' : '' }}">Yesterday</a>
                <a href="{{ route('stock.transfers', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'THIS_WEEK'])) }}" class="date-pill {{ $datePreset === 'THIS_WEEK' ? 'active' : '' }}">This Week</a>
                <a href="{{ route('stock.transfers', array_merge(request()->except('date_preset', 'from_date', 'to_date'), ['date_preset' => 'THIS_MONTH'])) }}" class="date-pill {{ $datePreset === 'THIS_MONTH' ? 'active' : '' }}">This Month</a>
            </div>

            <div class="grid-4" style="gap: 0.75rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">From Date</label>
                    <input type="date" name="from_date" value="{{ request('from_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">To Date</label>
                    <input type="date" name="to_date" value="{{ request('to_date') }}">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Transfer Status</label>
                    <select name="status">
                        <option value="">-- All Statuses --</option>
                        <option value="DISPATCHED" {{ request('status') === 'DISPATCHED' ? 'selected' : '' }}>🚚 In-Transit (Pending Count)</option>
                        <option value="RECEIVED" {{ request('status') === 'RECEIVED' ? 'selected' : '' }}>✓ Received & Verified</option>
                        <option value="DISCREPANCY" {{ request('status') === 'DISCREPANCY' ? 'selected' : '' }}>🚨 Discrepancy / Variance</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Carrier / Driver</label>
                    <select name="carrier_name">
                        <option value="">-- All Carriers --</option>
                        @foreach($carriers as $c)
                            <option value="{{ $c }}" {{ request('carrier_name') === $c ? 'selected' : '' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; margin-top: 0.85rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="🔍 Search by Waybill Ref, Driver Name, Officers...">
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.25rem;">
                    🔍 Apply Filters
                </button>

                <a href="{{ route('stock.transfers') }}" class="btn btn-secondary" style="padding: 0.65rem 1rem;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Section 1: In-Transit Transfers Waiting to be Accepted -->
    @php
        $pendingTransfers = $allTransfers->where('status', 'DISPATCHED');
    @endphp
    @if($pendingTransfers->isNotEmpty())
    <div style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #93c5fd;">
                📦 In-Transit Shipments Awaiting Physical Count ({{ $pendingTransfers->count() }})
            </h3>
            <span class="badge badge-warning">Action Required</span>
        </div>

        <div class="transfer-pending-grid">
            @foreach($pendingTransfers as $trf)
            <div class="transfer-card">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div>
                            <span class="badge badge-warning" style="font-size: 0.85rem;">{{ $trf->transfer_no }}</span>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                                Sent: {{ date('d M Y, h:i A', strtotime($trf->created_at)) }}
                            </div>
                        </div>
                        <span class="badge badge-info">🚚 In Transit</span>
                    </div>

                    <!-- Route visual -->
                    <div style="background: rgba(11,15,25,0.7); border: 1px solid var(--border); border-radius: 12px; padding: 0.85rem; margin-bottom: 1rem;">
                        <div style="font-size: 0.85rem; margin-bottom: 0.35rem;">
                            From: <strong style="color: #fca5a5;">🏢 {{ $trf->source->name ?? 'Origin Shop' }}</strong>
                        </div>
                        <div style="font-size: 0.85rem; margin-bottom: 0.35rem;">
                            To: <strong style="color: #86efac;">🏢 {{ $trf->destination->name ?? 'Destination Shop' }}</strong>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                            Carrier / Driver: <strong style="color: #cbd5e1;">{{ $trf->carrier_name }}</strong>
                        </div>
                    </div>

                    <!-- Items list -->
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">Items in Shipment:</div>
                        @foreach($trf->items as $item)
                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; padding: 0.25rem 0; border-bottom: 1px dashed rgba(255,255,255,0.08);">
                                <span>{{ $item->product_name }}</span>
                                <strong style="color: #60a5fa;">{{ $item->dispatched_qty }} units</strong>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                    <a href="{{ route('stock.waybill', $trf->id) }}" class="btn btn-secondary" style="flex: 1;" target="_blank">
                        📄 Waybill
                    </a>
                    @if(auth()->user()?->role !== 'viewer')
                        <button class="btn btn-success" style="flex: 2; font-size: 0.95rem;" onclick="openAcceptModal({{ json_encode($trf) }})">
                            ✅ Accept & Count
                        </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Section 2: All Transfers Audit Trail Table -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <h3 style="font-size: 1.2rem; font-weight: 800;">
                All Transfers Master Ledger
            </h3>
            <div style="width: 280px;">
                <input type="text" placeholder="⚡ Live search table rows..." onkeyup="filterTableRows('transfersTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
            </div>
        </div>

        <div class="table-wrap">
            <table id="transfersTable">
                <thead>
                    <tr>
                        <th>Transfer #</th>
                        <th>Origin Branch</th>
                        <th>Destination Branch</th>
                        <th>Carrier Driver</th>
                        <th>Date Dispatched</th>
                        <th>Dispatched By</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allTransfers as $cTrf)
                    <tr>
                        <td><strong style="color: #93c5fd;">{{ $cTrf->transfer_no }}</strong></td>
                        <td>🏢 {{ $cTrf->source->name ?? 'Origin' }}</td>
                        <td>🏪 <strong>{{ $cTrf->destination->name ?? 'Destination' }}</strong></td>
                        <td>{{ $cTrf->carrier_name }}</td>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            {{ date('d M Y, h:i A', strtotime($cTrf->created_at)) }}
                        </td>
                        <td>{{ $cTrf->dispatched_by }}</td>
                        <td>
                            @if($cTrf->status === 'RECEIVED')
                                <span class="badge badge-success">✓ Received & Counted</span>
                            @elseif($cTrf->status === 'DISCREPANCY')
                                <span class="badge badge-danger">🚨 Discrepancy (Missing)</span>
                            @else
                                <span class="badge badge-warning">🚚 In Transit</span>
                            @endif
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                                <a href="{{ route('stock.waybill', $cTrf->id) }}" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" target="_blank">
                                    📄 Waybill
                                </a>
                                @if($cTrf->status === 'DISPATCHED')
                                    @php
                                        $authUser = auth()->user();
                                        $canReceive = ($authUser && ($authUser->role === 'admin' || $authUser->warehouse_id == $cTrf->destination_warehouse_id));
                                        $canRecall = ($authUser && ($authUser->role === 'admin' || $authUser->warehouse_id == $cTrf->source_warehouse_id));
                                    @endphp

                                    @if($canReceive)
                                        <button type="button" class="btn btn-success" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick='openAcceptModal(@json($cTrf))'>
                                            ✓ Receive & Count
                                        </button>
                                    @endif

                                    @if($canRecall && !$canReceive)
                                        <form method="POST" action="{{ route('stock.transfer.recall', $cTrf->id) }}" style="display: inline;" onsubmit="return confirm('Recall this transfer back to {{ $cTrf->source->name ?? 'Origin' }}? Deducted goods will be restored immediately to your shop shelf count.')">
                                            @csrf
                                            <button type="submit" class="btn btn-danger" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;">
                                                ↩ Recall / Cancel
                                            </button>
                                        </form>
                                    @endif
                                @elseif($cTrf->status === 'CANCELLED')
                                    <span class="badge badge-secondary" style="font-size: 0.75rem;">Cancelled & Restocked</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No transfer records found matching filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1.25rem;">
            {{ $allTransfers->links() }}
        </div>
    </div>

    <!-- Modal: Dispatch New Transfer -->
    <div id="modalDispatchTransfer" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 600px;">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">🚚 Dispatch New Stock Transfer</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Deducts stock from source shop immediately and puts goods in-transit until destination shop verifies count.
            </p>

            <form id="dispatchForm" method="POST" action="{{ route('stock.transfer.out') }}" onsubmit="return validateTransferDispatch(event)">
                @csrf
                <div class="grid-2" style="gap: 1rem;">
                    @if(!empty($isBranchStaff) && !empty($userWarehouse))
                        <div class="form-group">
                            <label>Source Branch (Origin - Locked)</label>
                            <div style="padding: 0.55rem 0.8rem; background: rgba(30,41,59,0.85); border: 1px solid #3b82f6; border-radius: 8px; font-weight: 700; color: #93c5fd; font-size: 0.88rem;">
                                📍 {{ $userWarehouse->name }} [{{ $userWarehouse->code }}]
                            </div>
                            <input type="hidden" name="source_warehouse_id" id="dispSourceWh" value="{{ $userWarehouse->id }}">
                        </div>
                    @else
                        <div class="form-group">
                            <label>Source Branch (Origin)</label>
                            <select name="source_warehouse_id" id="dispSourceWh" required>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="form-group">
                        <label>Destination Branch (Receiving)</label>
                        <select name="destination_warehouse_id" id="dispDestWh" required>
                            @foreach($allWarehouses ?? $warehouses as $wh)
                                @if(empty($isBranchStaff) || empty($userWarehouse) || $wh->id != $userWarehouse->id)
                                    <option value="{{ $wh->id }}">🏢 {{ $wh->name }} ({{ $wh->code }})</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Select Product SKU</label>
                    <select name="items[0][productId]" id="dispProduct" required>
                        @foreach($allProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->code }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity to Send (Units)</label>
                    <input type="number" name="items[0][quantity]" id="dispQty" min="1" placeholder="e.g. 20" required>
                </div>

                <div class="form-group">
                    <label>Driver / Carrier Name & Vehicle Plate</label>
                    <input type="text" name="carrier_name" id="dispCarrier" placeholder="e.g. Driver Musa, Van #ABC-123" required>
                </div>

                <div class="form-group">
                    <label>Notes / Waybill Instructions</label>
                    <input type="text" name="notes" placeholder="Optional notes">
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalDispatchTransfer')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">🚚 Dispatch Goods</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Accept & Count Goods -->
    <div id="modalAcceptTransfer" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 600px;">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;" id="acceptTitle">✅ Accept & Count Goods</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Enter physical quantity received. If any unit is missing, system flags a discrepancy audit.
            </p>

            <form id="acceptForm" method="POST" action="">
                @csrf
                <div id="acceptItemsContainer" style="margin-bottom: 1rem;"></div>

                <div class="form-group">
                    <label>Discrepancy Notes (if missing items)</label>
                    <input type="text" name="discrepancy_notes" placeholder="Optional notes">
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalAcceptTransfer')">Cancel</button>
                    <button type="submit" class="btn btn-success" style="flex: 1;">✓ Confirm Physical Count</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
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

function openAcceptModal(trf) {
    document.getElementById('acceptTitle').textContent = '✅ Accept Transfer #' + trf.transfer_no;
    document.getElementById('acceptForm').action = '/stock/transfers/' + trf.id + '/receive';

    let html = '';
    (trf.items || []).forEach(item => {
        html += `
        <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 12px; padding: 0.85rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong style="color: #60a5fa;">${item.product_code || item.product_name}</strong>
                <div style="font-size: 0.8rem; color: var(--text-muted);">Dispatched: ${item.dispatched_qty} units</div>
            </div>
            <div style="width: 140px;">
                <label style="font-size: 0.75rem;">Counted Qty:</label>
                <input type="number" name="counted_items[${item.product_id}]" value="${item.dispatched_qty}" min="0" required>
            </div>
        </div>
        `;
    });

    document.getElementById('acceptItemsContainer').innerHTML = html;
    openModal('modalAcceptTransfer');
}

function validateTransferDispatch(e) {
    const sourceWh = document.getElementById('dispSourceWh').value;
    const destWh = document.getElementById('dispDestWh').value;
    const qty = parseInt(document.getElementById('dispQty').value) || 0;
    const carrier = document.getElementById('dispCarrier').value.trim();

    const errors = [];

    if (sourceWh === destWh) {
        errors.push({
            title: 'Identical Branches Selected',
            desc: 'Source branch (Origin) and Destination branch (Receiving) cannot be the same shop.',
            focus: 'dispDestWh'
        });
    }

    if (qty <= 0) {
        errors.push({
            title: 'Invalid Transfer Quantity',
            desc: 'Quantity to send must be at least 1 unit.',
            focus: 'dispQty'
        });
    }

    if (!carrier || carrier.length < 3) {
        errors.push({
            title: 'Driver / Transporter Required',
            desc: 'Please enter the name of the driver or logistics vehicle carrying the goods for chain of custody verification.',
            focus: 'dispCarrier'
        });
    }

    if (errors.length > 0) {
        e.preventDefault();
        showActionBlockedModal({
            title: 'Transfer Cannot Be Dispatched',
            subtitle: 'Please resolve the following logistics transfer requirements:',
            errors: errors
        });
        return false;
    }
    return true;
}
</script>
@endpush
