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
                        <th style="color: #fbbf24;">Reserved (Awaiting Customer Pickup)</th>
                        <th>Estimated Physical Stock Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stockOverview as $row)
                    <tr>
                        <td>
                            <strong style="font-size: 1.05rem; color: #f8fafc;">🏢 {{ $row['warehouse']->name }}</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Code: {{ $row['warehouse']->code }}</div>
                        </td>
                        <td>
                            <span style="font-size: 1.2rem; font-weight: 800; color: #4ade80;">
                                {{ number_format($row['total_physical']) }}
                            </span> units
                        </td>
                        <td>
                            <span style="font-size: 1.2rem; font-weight: 800; color: #fbbf24;">
                                {{ number_format($row['total_allocated']) }}
                            </span> units
                        </td>
                        <td style="font-weight: 800; font-size: 1.05rem; color: #f8fafc;">
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
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    📜 System Activity Audit Log (Immutable)
                </h3>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                    Complete forensic ledger tracking logins, sales checkouts, returns, product changes, and user management.
                </p>
            </div>
            
            <form method="GET" action="{{ route('auditor.index') }}" style="display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <label for="filterActivityType" style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Action Filter:</label>
                <select id="filterActivityType" name="activity_type" onchange="this.form.submit()" class="form-control" style="font-size: 0.85rem; padding: 0.35rem 0.75rem; border-radius: 8px; width: auto; min-width: 200px;">
                    <option value="ALL" {{ ($selectedType ?? 'ALL') === 'ALL' ? 'selected' : '' }}>🔍 All Actions (50 Latest)</option>
                    @foreach($availableActivityTypes ?? [] as $typeOption)
                        <option value="{{ $typeOption }}" {{ ($selectedType ?? '') === $typeOption ? 'selected' : '' }}>
                            {{ $typeOption }}
                        </option>
                    @endforeach
                </select>
                @if(($selectedType ?? 'ALL') !== 'ALL')
                    <a href="{{ route('auditor.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size: 0.8rem; padding: 0.35rem 0.6rem;">Reset</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Action Type</th>
                        <th>Description</th>
                        <th>Staff / User</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentActivities as $act)
                    @php
                        $badgeClass = match(true) {
                            str_contains($act->type, 'SUCCESS') || str_contains($act->type, 'CREATED') => 'badge-success',
                            str_contains($act->type, 'FAIL') || str_contains($act->type, 'DISABLED') || str_contains($act->type, 'BLOCKED') => 'badge-danger',
                            str_contains($act->type, 'SALE') => 'badge-info',
                            str_contains($act->type, 'RETURN') || str_contains($act->type, 'REFUND') => 'badge-purple',
                            str_contains($act->type, 'ARCHIVE') || str_contains($act->type, 'DELETE') || str_contains($act->type, 'VOID') => 'badge-warning',
                            default => 'badge-info',
                        };
                    @endphp
                    <tr>
                        <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                            {{ date('d M Y, h:i A', strtotime($act->timestamp)) }}
                        </td>
                        <td>
                            <span class="badge {{ $badgeClass }}">{{ $act->type }}</span>
                        </td>
                        <td style="max-width: 380px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $act->description }}">
                            {{ $act->description }}
                        </td>
                        <td><strong>{{ $act->userName }}</strong></td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-primary btn-act-details" 
                                    style="font-size: 0.78rem; padding: 0.25rem 0.65rem; border-radius: 6px;"
                                    data-activity="{{ json_encode($act) }}">
                                🔍 Details
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                            No matching audit logs found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audit Activity Forensic Details Modal -->
    <div id="modalActivityDetails" class="modal-backdrop" style="display: none; z-index: 1070;" onclick="if(event.target === this) closeActivityModal()">
        <div class="modal" style="max-width: 680px; width: 95%; max-height: 90vh; display: flex; flex-direction: column;">
            <!-- Modal Header -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 1rem; border-bottom: 1px solid var(--border);">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <span id="actModalIcon" style="font-size: 1.35rem;">🛡️</span>
                        <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #f8fafc;" id="actModalTypeTitle">Action Details</h3>
                        <span id="actModalTypeBadge" class="badge badge-info"></span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);" id="actModalTimestamp"></div>
                </div>
                <button type="button" onclick="closeActivityModal()" style="background: none; border: none; color: #9ca3af; font-size: 1.4rem; cursor: pointer; line-height: 1; padding: 0.2rem 0.5rem;">✕</button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div style="flex: 1; overflow-y: auto; padding: 1.25rem 0; display: flex; flex-direction: column; gap: 1rem;">
                
                <!-- Actor & Security Fingerprint Grid -->
                <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 12px; padding: 1rem;">
                    <h4 style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; margin-bottom: 0.75rem;">
                        👤 Identity & Security Fingerprint
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; font-size: 0.85rem;">
                        <div>
                            <span style="color: var(--text-muted); display: block; font-size: 0.75rem;">Staff / Initiator</span>
                            <strong id="actModalUser" style="color: #f1f5f9;"></strong>
                            <div id="actModalUserId" style="font-size: 0.7rem; color: #64748b;"></div>
                        </div>
                        <div>
                            <span style="color: var(--text-muted); display: block; font-size: 0.75rem;">Client IP Address</span>
                            <code id="actModalIp" style="background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px; color: #38bdf8;"></code>
                        </div>
                        <div>
                            <span style="color: var(--text-muted); display: block; font-size: 0.75rem;">Branch Location</span>
                            <strong id="actModalBranch" style="color: #f1f5f9;">All Branches / Global</strong>
                        </div>
                        <div>
                            <span style="color: var(--text-muted); display: block; font-size: 0.75rem;">Request / Trace ID</span>
                            <div id="actModalRequestId" style="font-size: 0.7rem; color: #94a3b8; word-break: break-all; font-family: monospace;"></div>
                        </div>
                    </div>
                    <div style="margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.05);">
                        <span style="color: var(--text-muted); font-size: 0.75rem; display: block;">Device / User Agent</span>
                        <div id="actModalUserAgent" style="font-size: 0.75rem; color: #cbd5e1; word-break: break-word; font-family: monospace; background: rgba(0,0,0,0.25); padding: 4px 8px; border-radius: 6px; margin-top: 2px;"></div>
                    </div>
                </div>

                <!-- Event Narrative Description -->
                <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(56,189,248,0.2); border-radius: 12px; padding: 1rem;">
                    <h4 style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #38bdf8; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                        📋 Activity Narrative
                    </h4>
                    <p id="actModalDescription" style="font-size: 0.95rem; color: #f8fafc; margin: 0; line-height: 1.5;"></p>
                </div>

                <!-- Forensic Breakdown Cards (Dynamically generated) -->
                <div id="actModalForensicSection" style="display: none;">
                    <h4 style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                        📊 Transaction & Forensic Metrics
                    </h4>
                    <div id="actModalForensicGrid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.65rem;">
                        <!-- Injected dynamically -->
                    </div>
                </div>

                <!-- Raw Metadata Payload (Collapsible) -->
                <div style="background: rgba(15,23,42,0.8); border: 1px solid var(--border); border-radius: 12px; overflow: hidden;">
                    <details id="actModalRawDetails">
                        <summary style="padding: 0.75rem 1rem; cursor: pointer; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); user-select: none; display: flex; justify-content: space-between; align-items: center;">
                            <span>📦 Raw Audit Metadata (JSON Payload)</span>
                            <button type="button" onclick="copyActivityJson(event)" class="btn btn-sm btn-outline-secondary" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">📋 Copy JSON</button>
                        </summary>
                        <div style="padding: 0.75rem 1rem; border-top: 1px solid var(--border); background: #0b0f19;">
                            <pre id="actModalRawJson" style="margin: 0; font-size: 0.75rem; color: #38bdf8; overflow-x: auto; max-height: 220px; font-family: monospace; white-space: pre-wrap; word-break: break-word;"></pre>
                        </div>
                    </details>
                </div>

            </div>

            <!-- Modal Footer -->
            <div style="padding-top: 1rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.5rem;">
                <button type="button" onclick="closeActivityModal()" class="btn btn-secondary" style="padding: 0.45rem 1.25rem;">
                    Close
                </button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function closeActivityModal() {
    const modal = document.getElementById('modalActivityDetails');
    if (modal) modal.style.display = 'none';
}

function copyActivityJson(e) {
    e.stopPropagation();
    const rawPre = document.getElementById('actModalRawJson');
    if (!rawPre) return;
    navigator.clipboard.writeText(rawPre.innerText).then(() => {
        const btn = e.target;
        const orig = btn.innerText;
        btn.innerText = '✓ Copied!';
        setTimeout(() => { btn.innerText = orig; }, 1500);
    }).catch(err => {
        console.warn('Copy failed:', err);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // Warehouse map from server
    const warehouseMap = {
        @foreach($warehouses as $w)
            "{{ $w->id }}": "{{ addslashes($w->name) }}",
        @endforeach
    };

    // Attach click handlers to all activity details buttons
    document.querySelectorAll('.btn-act-details').forEach(button => {
        button.addEventListener('click', function () {
            try {
                const activity = JSON.parse(this.getAttribute('data-activity'));
                showActivityDetails(activity, warehouseMap);
            } catch (err) {
                console.error('Failed to parse activity data:', err);
            }
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeActivityModal();
        }
    });
});

function formatNaira(num) {
    if (num === null || num === undefined || isNaN(num)) return '₦0.00';
    return '₦' + Number(num).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function showActivityDetails(act, warehouseMap) {
    if (!act) return;

    // Header elements
    const iconEl = document.getElementById('actModalIcon');
    const titleEl = document.getElementById('actModalTypeTitle');
    const badgeEl = document.getElementById('actModalTypeBadge');
    const timeEl = document.getElementById('actModalTimestamp');

    const type = act.type || 'SYSTEM_EVENT';
    titleEl.innerText = type.replace(/_/g, ' ');
    badgeEl.innerText = type;

    // Badge styling & icon
    if (type.includes('SUCCESS') || type.includes('CREATED')) {
        badgeEl.className = 'badge badge-success';
        iconEl.innerText = '✅';
    } else if (type.includes('FAIL') || type.includes('DISABLED') || type.includes('BLOCKED')) {
        badgeEl.className = 'badge badge-danger';
        iconEl.innerText = '⛔';
    } else if (type.includes('SALE')) {
        badgeEl.className = 'badge badge-info';
        iconEl.innerText = '🛒';
    } else if (type.includes('RETURN') || type.includes('REFUND')) {
        badgeEl.className = 'badge badge-purple';
        iconEl.innerText = '🔄';
    } else if (type.includes('ARCHIVE') || type.includes('DELETE') || type.includes('VOID')) {
        badgeEl.className = 'badge badge-warning';
        iconEl.innerText = '🗑️';
    } else {
        badgeEl.className = 'badge badge-info';
        iconEl.innerText = '🛡️';
    }

    // Timestamp
    if (act.timestamp) {
        const d = new Date(act.timestamp);
        timeEl.innerText = isNaN(d) ? act.timestamp : d.toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
    } else {
        timeEl.innerText = 'N/A';
    }

    // Actor details
    document.getElementById('actModalUser').innerText = act.userName || 'System';
    document.getElementById('actModalUserId').innerText = act.userId ? 'ID: ' + act.userId : '';

    // Metadata unpacking
    const meta = act.metadata || {};

    // IP, Device, Request ID
    const ip = meta.ip || meta.client_ip || '127.0.0.1';
    document.getElementById('actModalIp').innerText = ip;

    const reqId = meta.request_id || act.id || 'N/A';
    document.getElementById('actModalRequestId').innerText = reqId;

    const userAgent = meta.user_agent || 'Standard Browser / Direct API';
    document.getElementById('actModalUserAgent').innerText = userAgent;

    // Warehouse / Branch
    const branchId = meta.warehouse_id || null;
    let branchName = 'All Branches / Global';
    if (branchId && warehouseMap && warehouseMap[branchId]) {
        branchName = warehouseMap[branchId] + ' (ID: ' + branchId + ')';
    } else if (branchId) {
        branchName = 'Branch #' + branchId;
    }
    document.getElementById('actModalBranch').innerText = branchName;

    // Narrative description
    document.getElementById('actModalDescription').innerText = act.description || 'No descriptive summary provided.';

    // Forensic breakdown cards
    const forensicSection = document.getElementById('actModalForensicSection');
    const forensicGrid = document.getElementById('actModalForensicGrid');
    forensicGrid.innerHTML = '';

    const cards = [];

    // Financial / Sale metrics
    if (meta.total_amount !== undefined) {
        cards.push({ label: 'Total Amount', value: formatNaira(meta.total_amount), color: '#38bdf8' });
    }
    if (meta.paid_amount !== undefined) {
        cards.push({ label: 'Paid Amount', value: formatNaira(meta.paid_amount), color: '#4ade80' });
    }
    if (meta.refund_amount !== undefined) {
        cards.push({ label: 'Refund Amount', value: formatNaira(meta.refund_amount), color: '#f43f5e' });
    }
    if (meta.cash_amount !== undefined && meta.cash_amount > 0) {
        cards.push({ label: 'Cash Tender', value: formatNaira(meta.cash_amount), color: '#fbbf24' });
    }
    if (meta.pos_amount !== undefined && meta.pos_amount > 0) {
        cards.push({ label: 'POS Terminal Tender', value: formatNaira(meta.pos_amount), color: '#a78bfa' });
    }
    if (meta.exchange_credit !== undefined && meta.exchange_credit > 0) {
        cards.push({ label: 'Exchange Credit', value: formatNaira(meta.exchange_credit), color: '#f472b6' });
    }

    // Identifiers
    if (meta.sale_id) {
        cards.push({ label: 'Sale Invoice', value: '#' + meta.sale_id, color: '#f1f5f9' });
    }
    if (meta.return_code) {
        cards.push({ label: 'Return Code', value: meta.return_code, color: '#f1f5f9' });
    }
    if (meta.items_count !== undefined) {
        cards.push({ label: 'Items Count', value: meta.items_count + ' items', color: '#f1f5f9' });
    }
    if (meta.customer_name) {
        cards.push({ label: 'Customer', value: meta.customer_name, color: '#e2e8f0' });
    }
    if (meta.customer_phone) {
        cards.push({ label: 'Phone', value: meta.customer_phone, color: '#94a3b8' });
    }
    if (meta.refund_method) {
        cards.push({ label: 'Refund Method', value: meta.refund_method, color: '#cbd5e1' });
    }
    if (meta.reason) {
        cards.push({ label: 'Audit Reason', value: meta.reason, color: '#fca5a5' });
    }
    if (meta.portal) {
        cards.push({ label: 'Portal', value: meta.portal, color: '#818cf8' });
    }
    if (meta.attempted_email) {
        cards.push({ label: 'Attempted Email', value: meta.attempted_email, color: '#f87171' });
    }
    if (meta.role) {
        cards.push({ label: 'Assigned Role', value: meta.role, color: '#38bdf8' });
    }
    if (meta.product_code) {
        cards.push({ label: 'Product Code', value: meta.product_code, color: '#38bdf8' });
    }
    if (meta.unit_price !== undefined) {
        cards.push({ label: 'Unit Price', value: formatNaira(meta.unit_price), color: '#4ade80' });
    }

    if (cards.length > 0) {
        forensicSection.style.display = 'block';
        cards.forEach(card => {
            const div = document.createElement('div');
            div.style.cssText = 'background: rgba(15,23,42,0.7); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 0.5rem 0.75rem;';
            div.innerHTML = `
                <span style="font-size: 0.7rem; color: var(--text-muted); display: block; text-transform: uppercase;">${card.label}</span>
                <strong style="font-size: 0.9rem; color: ${card.color}; word-break: break-word;">${card.value}</strong>
            `;
            forensicGrid.appendChild(div);
        });
    } else {
        forensicSection.style.display = 'none';
    }

    // Raw JSON details
    const rawPre = document.getElementById('actModalRawJson');
    rawPre.innerText = JSON.stringify(act, null, 2);

    // Show modal
    document.getElementById('modalActivityDetails').style.display = 'flex';
}
</script>
@endpush
