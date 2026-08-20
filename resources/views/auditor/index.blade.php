@extends('layouts.app')

@section('title', 'Auditor & Anti-Theft Hub')

@push('styles')
<style>
    .auditor-header {
        background: linear-gradient(135deg, rgba(220,38,38,0.2) 0%, rgba(15,23,42,0.9) 100%);
        border: 2px solid rgba(220,38,38,0.4);
        border-radius: 20px;
        padding: 1.75rem 2rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .discrepancy-card {
        background: rgba(220,38,38,0.1);
        border: 2px solid #ef4444;
        border-radius: 18px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1rem;
        animation: pulseBorder 2s infinite;
    }

    @keyframes pulseBorder {
        0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.4); }
        50% { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
    }
</style>
@endpush

@section('content')

    <!-- Auditor Header -->
    <div class="auditor-header">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                <span style="font-size: 1.75rem;">🛡️</span>
                <h2 style="font-size: 1.6rem; font-weight: 800; color: #fca5a5;">Auditor Anti-Theft & Control Hub</h2>
            </div>
            <p style="font-size: 0.9rem; color: #cbd5e1;">
                Real-time stock reconciliation, transfer discrepancy detection, and unsupplied sales tracking.
            </p>
        </div>
    </div>

    <!-- 1. Theft & Discrepancy Alert Radar -->
    @if($discrepancyTransfers->isNotEmpty())
    <div style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.2rem; font-weight: 800; color: #f87171; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
            🚨 ACTIVE THEFT / VARIANCE ALERTS ({{ $discrepancyTransfers->count() }})
        </h3>

        @foreach($discrepancyTransfers as $trf)
        <div class="discrepancy-card">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                <div>
                    <strong style="font-size: 1.15rem; color: #fca5a5;">
                        Transfer Discrepancy: {{ $trf->transfer_no }}
                    </strong>
                    <div style="font-size: 0.85rem; color: #e2e8f0; margin-top: 0.25rem;">
                        Route: <strong>{{ $trf->source->name ?? 'Shop A' }}</strong> ➔ <strong>{{ $trf->destination->name ?? 'Shop B' }}</strong> · Carrier/Driver: <strong>{{ $trf->carrier_name }}</strong>
                    </div>
                    <div style="font-size: 0.8rem; color: #94a3b8;">
                        Dispatched by: {{ $trf->dispatched_by }} · Counted & Flagged by: {{ $trf->received_by }} on {{ date('d M Y, h:i A', strtotime($trf->received_at)) }}
                    </div>
                </div>
                <span class="badge badge-danger" style="font-size: 0.85rem;">⚠️ MISSING UNITS DETECTED</span>
            </div>

            <div style="background: rgba(15,23,42,0.8); border: 1px solid rgba(220,38,38,0.3); border-radius: 12px; padding: 0.75rem 1rem;">
                <table style="width: 100%; font-size: 0.85rem;">
                    <thead>
                        <tr style="color: #94a3b8;">
                            <th>Item Name</th>
                            <th>Dispatched</th>
                            <th>Counted at Destination</th>
                            <th style="color: #f87171;">Shortage (Missing)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trf->items as $item)
                        @if($item->discrepancy_qty != 0)
                        <tr>
                            <td><strong>{{ $item->product_name }}</strong></td>
                            <td>{{ $item->dispatched_qty }}</td>
                            <td>{{ $item->received_qty }}</td>
                            <td style="font-weight: 800; color: #f87171;">
                                {{ $item->discrepancy_qty }} units MISSING
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
                @if($trf->discrepancy_notes)
                <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #fde047;">
                    <strong>Driver/Storekeeper Note:</strong> {{ $trf->discrepancy_notes }}
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <!-- 2. Physical Stock on Ground vs. Sold Unsupplied Goods Matrix -->
    <div class="card" style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem;">
            🏢 Physical Stock Matrix Across All Locations
        </h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Branch / Shop Location</th>
                        <th style="color: #4ade80;">Physical Count (On Shelves)</th>
                        <th style="color: #fbbf24;">Sold (Uncollected Liability)</th>
                        <th style="color: #60a5fa;">Available to Sell</th>
                        <th>Estimated Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stockOverview as $row)
                    <tr>
                        <td>
                            <strong style="font-size: 1.05rem;">🏢 {{ $row['warehouse']->name }}</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Code: {{ $row['warehouse']->code }}</div>
                        </td>
                        <td>
                            <span style="font-size: 1.2rem; font-weight: 800; color: #4ade80;">
                                {{ $row['total_physical'] }}
                            </span> units
                        </td>
                        <td>
                            <span style="font-size: 1.2rem; font-weight: 800; color: #fbbf24;">
                                {{ $row['total_allocated'] }}
                            </span> units
                        </td>
                        <td>
                            <span style="font-size: 1.2rem; font-weight: 800; color: #60a5fa;">
                                {{ $row['total_available'] }}
                            </span> units
                        </td>
                        <td style="font-weight: 800; font-size: 1.05rem;">
                            ₦{{ number_format($row['stock_value'], 0) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. Immutable Activity Audit Log -->
    <div class="card">
        <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem;">
            📜 System Activity Audit Log (Immutable)
        </h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Action Type</th>
                        <th>Description</th>
                        <th>Staff / User</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentActivities as $act)
                    <tr>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            {{ date('d M Y, h:i A', strtotime($act->timestamp)) }}
                        </td>
                        <td>
                            <span class="badge badge-info">{{ $act->type }}</span>
                        </td>
                        <td>{{ $act->description }}</td>
                        <td><strong>{{ $act->userName }}</strong></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            No recent activity logs.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
</script>
@endpush
