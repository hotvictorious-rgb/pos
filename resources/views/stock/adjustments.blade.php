@extends('layouts.app')

@section('title', 'Stock Adjustments & Damages')

@push('styles')
<style>
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
            <h2 style="font-size: 1.5rem; font-weight: 800;">Damaged & Expired Stock Write-offs 📉</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Officially record broken, expired, or lost goods on ground to maintain 100% physical count accuracy.
            </p>
        </div>
        <button class="btn btn-danger btn-lg" onclick="openModal('modalStockAdjustment')">
            📉 Record Damaged Goods
        </button>
    </div>

    <!-- Info Banner -->
    <div style="background: rgba(220,38,38,0.1); border: 1px solid rgba(220,38,38,0.3); border-radius: 16px; padding: 1.25rem; margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="font-size: 1.75rem;">🛡️</div>
            <div style="font-size: 0.85rem; color: #fca5a5;">
                <strong>Anti-Theft Rule:</strong> No item can be discarded without an official adjustment record. Every adjustment reduces physical closing stock and is permanently written to the Auditor's Activity Log.
            </div>
        </div>
    </div>

    <!-- Adjustments Table -->
    <div class="card">
        <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem;">
            Adjustments Audit Log ({{ $adjustments->count() }})
        </h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Shop Branch</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th style="color: #f87171;">Qty Deducted</th>
                        <th>Reason / Incident Note</th>
                        <th>Staff Name</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($adjustments as $adj)
                    <tr>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            {{ date('d M Y, h:i A', strtotime($adj->created_at)) }}
                        </td>
                        <td><strong>{{ $adj->warehouse->name ?? 'Shop' }}</strong></td>
                        <td>
                            <strong>{{ $adj->product_name }}</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $adj->product_code }}</div>
                        </td>
                        <td>
                            @php
                                $badge = match($adj->type) {
                                    'DAMAGE' => 'badge-danger',
                                    'EXPIRED' => 'badge-warning',
                                    default => 'badge-info',
                                };
                            @endphp
                            <span class="badge {{ $badge }}">{{ $adj->type }}</span>
                        </td>
                        <td style="font-weight: 800; color: #f87171; font-size: 1.1rem;">
                            -{{ $adj->quantity }} units
                        </td>
                        <td>{{ $adj->reason }}</td>
                        <td>{{ $adj->recorded_by }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No damaged or lost stock adjustments logged yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Record Stock Adjustment -->
    <div id="modalStockAdjustment" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">📉 Record Stock Damage / Loss</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Deducts unsellable items from the physical closing stock count.
            </p>

            <form method="POST" action="{{ route('stock.adjustments.record') }}">
                @csrf

                <div class="form-group">
                    <label>Branch Shop Location</label>
                    <select name="warehouse_id" required>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Product</label>
                    <select name="product_id" required>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Adjustment Type</label>
                        <select name="type" required>
                            <option value="DAMAGE">💥 Physical Damage / Broken</option>
                            <option value="EXPIRED">⏳ Expired Expiry Date</option>
                            <option value="LOST">🔍 Missing / Unaccounted</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quantity Damaged (Units)</label>
                        <input type="number" name="quantity" min="1" placeholder="e.g. 2" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Incident Reason / Note</label>
                    <textarea name="reason" rows="2" placeholder="e.g. Carton got wet during offloading from supplier truck" required></textarea>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalStockAdjustment')">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="flex: 1;">✓ Record & Deduct Stock</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>
@endpush
