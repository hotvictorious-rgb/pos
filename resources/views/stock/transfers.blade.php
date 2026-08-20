@extends('layouts.app')

@section('title', 'Inter-Branch Transfers')

@push('styles')
<style>
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
            <h2 style="font-size: 1.5rem; font-weight: 800;">Inter-Branch Transfers & Shipments 🚚</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Move goods between branches with 2-step theft-proof dispatch and count verification.
            </p>
        </div>
        <button class="btn btn-primary btn-lg" onclick="openModal('modalDispatchTransfer')">
            🚚 Dispatch New Transfer
        </button>
    </div>

    <!-- Section 1: In-Transit Transfers Waiting to be Accepted -->
    <div style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #93c5fd;">
                📦 In-Transit Shipments Awaiting Acceptance ({{ $pendingTransfers->count() }})
            </h3>
            <span class="badge badge-warning">Action Required</span>
        </div>

        @if($pendingTransfers->isNotEmpty())
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

                    <!-- Prominent Accept & Count Button -->
                    <button class="btn btn-success btn-block btn-lg" style="margin-top: 0.5rem; font-size: 1.05rem;"
                            onclick="openAcceptModal({{ json_encode($trf) }})">
                        ✅ Accept & Count Goods
                    </button>
                </div>
                @endforeach
            </div>
        @else
            <div class="card" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🚚</div>
                <h4>No In-Transit Shipments</h4>
                <p style="font-size: 0.85rem; margin-top: 0.25rem;">
                    All transfers have been received and verified. Tap <strong>Dispatch New Transfer</strong> above to send goods.
                </p>
            </div>
        @endif
    </div>

    <!-- Section 2: Completed Transfers Audit Trail -->
    <div class="card">
        <h3 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 1.25rem;">
            Completed Transfers Audit Trail (Last 20)
        </h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Transfer #</th>
                        <th>Origin Branch</th>
                        <th>Destination Branch</th>
                        <th>Carrier Driver</th>
                        <th>Received Date</th>
                        <th>Received By</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($completedTransfers as $cTrf)
                    <tr>
                        <td><strong style="color: #93c5fd;">{{ $cTrf->transfer_no }}</strong></td>
                        <td>{{ $cTrf->source->name ?? 'Origin' }}</td>
                        <td><strong>{{ $cTrf->destination->name ?? 'Destination' }}</strong></td>
                        <td>{{ $cTrf->carrier_name }}</td>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            {{ date('d M Y, h:i A', strtotime($cTrf->received_at ?? $cTrf->updated_at)) }}
                        </td>
                        <td>{{ $cTrf->received_by }}</td>
                        <td>
                            @if($cTrf->status === 'DISCREPANCY')
                                <span class="badge badge-danger">🚨 THEFT / DISCREPANCY</span>
                            @else
                                <span class="badge badge-success">✓ RECEIVED (Verified)</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            No past transfers recorded.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Accept & Count Goods -->
    <div id="modalAcceptTransfer" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 600px;">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">✅ Count & Accept Incoming Transfer</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;" id="acceptSubtitle">
                Physically count the items offloaded from the carrier before confirming.
            </p>

            <form id="acceptTransferForm" method="POST" action="">
                @csrf

                <div id="acceptItemsContainer" style="margin-bottom: 1.25rem;">
                    <!-- Items populated dynamically -->
                </div>

                <div class="form-group">
                    <label>Storekeeper Receiving Notes / Observations</label>
                    <textarea name="discrepancy_notes" rows="2" placeholder="e.g. All cartons intact, seal verified."></textarea>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalAcceptTransfer')">Cancel</button>
                    <button type="submit" class="btn btn-success" style="flex: 1;">✓ Confirm Physical Count & Add to Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Dispatch New Transfer -->
    <div id="modalDispatchTransfer" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 600px;">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">🚚 Dispatch Transfer to Another Shop</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Select origin, destination, driver, and items to send.
            </p>

            <form method="POST" action="{{ route('stock.transfer.out') }}">
                @csrf

                <div class="grid-2">
                    <div class="form-group">
                        <label>Source Branch (Sending From)</label>
                        <select name="source_warehouse_id" required>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Destination Branch (Receiving)</label>
                        <select name="destination_warehouse_id" required>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ $loop->last ? 'selected' : '' }}>{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Carrier Driver Name / Vehicle No</label>
                    <input type="text" name="carrier_name" placeholder="e.g. Driver Emeka (KJA-123-XY)" required>
                </div>

                <label style="font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.4rem; display: block;">Select Item to Dispatch:</label>
                <div style="background: rgba(11,15,25,0.7); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
                    <div class="grid-2">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Product</label>
                            <select name="items[0][product_id]" required>
                                @foreach($allProducts as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Quantity to Send</label>
                            <input type="number" name="items[0][quantity]" min="1" placeholder="e.g. 20" required>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalDispatchTransfer')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">✓ Dispatch Shipment</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openAcceptModal(trf) {
    document.getElementById('acceptTransferForm').action = '/stock/transfer-in/' + trf.id;
    document.getElementById('acceptSubtitle').textContent = `Transfer #${trf.transfer_no} from ${trf.source ? trf.source.name : 'Origin'} (Driver: ${trf.carrier_name})`;

    let html = '<label style="font-size:0.8rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;margin-bottom:0.5rem;display:block;">Physical Offload Count:</label>';

    (trf.items || []).forEach(item => {
        html += `
        <div style="background:rgba(15,23,42,0.7);border:1px solid var(--border);border-radius:12px;padding:0.85rem;margin-bottom:0.6rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;">
            <div>
                <strong style="font-size:0.95rem;color:#f9fafb;">${item.product_name}</strong>
                <div style="font-size:0.8rem;color:#60a5fa;">Dispatched from origin: ${item.dispatched_qty} units</div>
            </div>
            <div style="max-width:140px;">
                <label style="font-size:0.7rem;color:#4ade80;">Counted on Ground:</label>
                <input type="number" name="counted_items[${item.id}]" value="${item.dispatched_qty}" min="0" required
                       style="padding:0.5rem 0.75rem;font-size:1.1rem;font-weight:800;text-align:center;color:#4ade80;background:#030712;border-color:#22c55e;">
            </div>
        </div>
        `;
    });

    document.getElementById('acceptItemsContainer').innerHTML = html;
    openModal('modalAcceptTransfer');
}
</script>
@endpush
