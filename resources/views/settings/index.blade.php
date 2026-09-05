@extends('layouts.app')

@section('title', 'System Settings')

@push('styles')
<style>
    .settings-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border);
        padding-bottom: 0.5rem;
    }

    .set-tab {
        padding: 0.75rem 1.25rem;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        color: var(--text-muted);
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
    }
    .set-tab.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(37,99,235,0.3);
    }

    .tab-content { display: none; }
    .tab-content.active { display: block; }
</style>
@endpush

@section('content')

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 800;">System Settings & Configuration ⚙️</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Configure business details, custom receipt footers, branch shop locations, and data safety.
            </p>
        </div>
    </div>

    <!-- Settings Tabs -->
    <div class="settings-tabs">
        <button class="set-tab active" onclick="showTab('tabBusiness', this)">🏢 Business & Receipts</button>
        <button class="set-tab" onclick="showTab('tabLocations', this)">🏬 Branch Locations ({{ $warehouses->count() }})</button>
        <button class="set-tab" onclick="showTab('tabInventory', this)">📦 Inventory Rules</button>
        @if(auth()->check() && (auth()->user()->hasCapability('tenant.backup') || auth()->user()->isPlatformAdmin()))
        <button class="set-tab" onclick="showTab('tabBackups', this)">💾 Data Safety & Backups</button>
        @endif
    </div>

    <!-- Tab 1: Business & Receipt Information -->
    <div id="tabBusiness" class="tab-content active">
        <div class="card" style="max-width: 800px;">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem;">
                🏢 Business Profile & Printable Receipt Customizer
            </h3>

            <form id="settingsForm" method="POST" action="{{ route('settings.update') }}">
                @csrf

                <div class="form-group">
                    <label>Business Name (Shows on top of receipts & reports)</label>
                    <input type="text" name="businessName" id="setBizName" value="{{ old('businessName', $settings->businessName) }}" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Business Phone Number(s)</label>
                        <input type="text" name="businessPhone" id="setBizPhone" value="{{ old('businessPhone', $settings->businessPhone) }}" placeholder="+234 800 000 0000">
                    </div>

                    <div class="form-group">
                        <label>Official Email</label>
                        <input type="email" name="businessEmail" id="setBizEmail" value="{{ old('businessEmail', $settings->businessEmail) }}" placeholder="admin@hysam.com">
                    </div>
                </div>

                <div class="form-group">
                    <label>Headquarters / Business Address</label>
                    <textarea name="businessAddress" id="setBizAddress" rows="2">{{ old('businessAddress', $settings->businessAddress) }}</textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Currency Symbol</label>
                        <input type="text" name="currency" id="setCurrency" value="{{ old('currency', $settings->currency ?? '₦') }}" required>
                    </div>

                    <div class="form-group">
                        <label>Default Low Stock Alert Threshold</label>
                        <input type="number" name="lowStockThreshold" id="setThreshold" value="{{ old('lowStockThreshold', $settings->lowStockThreshold ?? 5) }}" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Product Categories (Comma separated)</label>
                    <input type="text" name="categories" value="{{ is_array($settings->categories) ? implode(', ', $settings->categories) : '' }}" placeholder="Groceries, Beverages, Hardware">
                </div>

                <div class="form-group">
                    <label>Receipt Footer Note (Printed at bottom of customer receipt)</label>
                    <textarea name="reportFooter" rows="2">{{ old('reportFooter', $settings->reportFooter) }}</textarea>
                </div>

                <button type="button" class="btn btn-success btn-lg" onclick="confirmSettingsUpdate()">
                    ✓ Save Settings
                </button>
            </form>
        </div>
    </div>

    <!-- Tab 2: Branch Shops & Locations -->
    <div id="tabLocations" class="tab-content">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800;">🏬 Branch Locations & Warehouses</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Manage your shops, outlets, and central storehouses.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('modalAddBranch')">
                    ➕ Add New Branch Shop
                </button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Shop Name</th>
                            <th>Code</th>
                            <th>Address / Location</th>
                            <th>Phone</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($warehouses as $wh)
                        <tr>
                            <td><strong>🏢 {{ $wh->name }}</strong></td>
                            <td><span class="badge badge-info">{{ $wh->code }}</span></td>
                            <td>{{ $wh->address ?? 'N/A' }}</td>
                            <td>{{ $wh->phone ?? 'N/A' }}</td>
                            <td>{{ $wh->manager_name ?? 'Branch Manager' }}</td>
                            <td>
                                <span class="badge {{ $wh->is_active ? 'badge-success' : 'badge-danger' }}">
                                    {{ $wh->is_active ? '✓ Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.4rem; align-items: center;">
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="openEditBranchModal({
                                        id: '{{ $wh->id }}',
                                        name: '{{ addslashes($wh->name) }}',
                                        code: '{{ addslashes($wh->code) }}',
                                        manager_name: '{{ addslashes($wh->manager_name ?? '') }}',
                                        address: '{{ addslashes($wh->address ?? '') }}',
                                        phone: '{{ addslashes($wh->phone ?? '') }}'
                                    })">
                                        ✏️ Edit
                                    </button>
                                    <form id="toggleWhForm_{{ $wh->id }}" method="POST" action="{{ route('settings.warehouse.toggle', $wh->id) }}" style="margin: 0;">
                                        @csrf
                                        <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="confirmToggleWarehouse('{{ $wh->id }}', '{{ addslashes($wh->name) }}', {{ $wh->is_active ? 'true' : 'false' }})">
                                            {{ $wh->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 3: Inventory Rules -->
    <div id="tabInventory" class="tab-content">
        <div class="card" style="max-width: 800px;">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">
                📦 Inventory & Anti-Theft Policies
            </h3>

            <div style="background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.3); border-radius: 14px; padding: 1.25rem; margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.95rem; font-weight: 800; color: #4ade80; margin-bottom: 0.35rem;">
                    ✓ Active Policy 1: Physical Closing Stock Law
                </h4>
                <p style="font-size: 0.85rem; color: #cbd5e1;">
                    When goods are sold on credit or for later delivery, they are locked as <em>Unsupplied</em> and remain counted in physical shelf closing stock until an authorized handover note is issued.
                </p>
            </div>

            <div style="background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.3); border-radius: 14px; padding: 1.25rem; margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.95rem; font-weight: 800; color: #60a5fa; margin-bottom: 0.35rem;">
                    ✓ Active Policy 2: Two-Step Inter-Location Transfer Handshake
                </h4>
                <p style="font-size: 0.85rem; color: #cbd5e1;">
                    Goods moving between shops are tracked in an in-transit buffer. Destination branches count physical items upon arrival. Any shortage automatically raises an instant theft alert to the Auditor.
                </p>
            </div>

            <div style="background: rgba(217,119,6,0.08); border: 1px solid rgba(217,119,6,0.3); border-radius: 14px; padding: 1.25rem;">
                <h4 style="font-size: 0.95rem; font-weight: 800; color: #fbbf24; margin-bottom: 0.35rem;">
                    ✓ Active Policy 3: Immutable Activity Logging & Anti-Backdating
                </h4>
                <p style="font-size: 0.85rem; color: #cbd5e1;">
                    Timestamps are strictly enforced server-side. Workers cannot backdate or silently delete sales or stock records.
                </p>
            </div>
        </div>
    </div>

    @if(auth()->check() && (auth()->user()->hasCapability('tenant.backup') || auth()->user()->isPlatformAdmin()))
    <!-- Tab 4: Data Safety & Database Backups -->
    <div id="tabBackups" class="tab-content">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800;">💾 Database Snapshots & Safety Backups</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">
                        Take snapshots of your entire inventory, sales history, customer debts, and audit trails.
                    </p>
                </div>

                <form id="backupForm" method="POST" action="/settings/backups">
                    @csrf
                    <button type="button" class="btn btn-success" onclick="confirmCreateBackup()">
                        📦 Create Instant DB Backup
                    </button>
                </form>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Backup Filename</th>
                            <th>Created Date</th>
                            <th>Size</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($backups as $b)
                        <tr>
                            <td><strong>{{ $b->filename ?? 'backup_snapshot.json' }}</strong></td>
                            <td>{{ date('d M Y, h:i A', strtotime($b->created_at)) }}</td>
                            <td>{{ number_format(($b->size ?? 1024) / 1024, 1) }} KB</td>
                            <td>
                                <a href="/settings/backups/{{ $b->id }}/download" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
                                    ⬇️ Download
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                No backup snapshots generated yet. Tap <strong>Create Instant DB Backup</strong> above to safeguard your data!
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Modal: Add Branch Shop -->
    <div id="modalAddBranch" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">🏬 Add New Branch Location</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Set up a new shop, outlet, or depot to track physical stock independently.
            </p>

            <form id="addBranchForm" method="POST" action="{{ route('settings.warehouse.store') }}">
                @csrf
                <div class="form-group">
                    <label>Branch Name</label>
                    <input type="text" name="name" id="newBranchName" placeholder="e.g. Lekki Outlet / Shop 3" required>
                </div>

                <div class="form-group">
                    <label>Location Code</label>
                    <input type="text" name="code" id="newBranchCode" placeholder="e.g. SHOP-03" required>
                </div>

                <div class="form-group">
                    <label>Branch Manager Name</label>
                    <input type="text" name="manager_name" id="newBranchManager" placeholder="e.g. Samuel Ade">
                </div>

                <div class="form-group">
                    <label>Address / Street</label>
                    <input type="text" name="address" placeholder="Physical shop address">
                </div>

                <div class="form-group">
                    <label>Branch Phone</label>
                    <input type="text" name="phone" placeholder="Contact number">
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalAddBranch')">Cancel</button>
                    <button type="button" class="btn btn-primary" style="flex: 1;" onclick="confirmAddBranch()">✓ Save Branch Shop</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Branch Shop -->
    <div id="modalEditBranch" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">🏬 Edit Branch Location</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Update branch name, code, address, contact number, and assigned manager.
            </p>

            <form id="editBranchForm" method="POST" action="">
                @csrf
                <div class="form-group">
                    <label>Branch Name</label>
                    <input type="text" name="name" id="editBranchName" required>
                </div>

                <div class="form-group">
                    <label>Location Code</label>
                    <input type="text" name="code" id="editBranchCode" required>
                </div>

                <div class="form-group">
                    <label>Branch Manager Name</label>
                    <input type="text" name="manager_name" id="editBranchManager" placeholder="e.g. Samuel Ade">
                </div>

                <div class="form-group">
                    <label>Address / Street</label>
                    <input type="text" name="address" id="editBranchAddress" placeholder="Physical shop address">
                </div>

                <div class="form-group">
                    <label>Branch Phone</label>
                    <input type="text" name="phone" id="editBranchPhone" placeholder="Contact number">
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalEditBranch')">Cancel</button>
                    <button type="button" class="btn btn-primary" style="flex: 1;" onclick="confirmEditBranch()">✓ Save Changes</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.set-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

    btn.classList.add('active');
    document.getElementById(tabId).classList.add('active');
}

function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function confirmSettingsUpdate() {
    const form = document.getElementById('settingsForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const bizName = document.getElementById('setBizName').value;
    const currency = document.getElementById('setCurrency').value;

    showConfirmPopup({
        icon: '🏢',
        title: 'Confirm Business Settings Update',
        subtitle: 'Review updated company profile & receipt templates:',
        borderColor: '#3b82f6',
        items: [
            { label: 'Business Name', value: bizName, color: '#f8fafc' },
            { label: 'Currency Symbol', value: currency, color: '#4ade80', size: '1rem' }
        ],
        impact: {
            text: '⚙️ PROFILE UPDATE: Header titles, currency, and receipt footers will update across all terminals.',
            type: 'info'
        },
        confirmText: '✅ Yes, Save Settings',
        confirmClass: 'btn-success',
        form: form
    });
}

function confirmToggleWarehouse(id, name, isActive) {
    const isDeactivating = isActive;
    showConfirmPopup({
        icon: isDeactivating ? '🏬' : '🏪',
        title: isDeactivating ? 'Confirm Deactivating Branch' : 'Confirm Activating Branch',
        subtitle: 'Review branch availability change:',
        borderColor: isDeactivating ? '#ef4444' : '#22c55e',
        items: [
            { label: 'Location Name', value: name, color: '#f8fafc' },
            { label: 'Action', value: isDeactivating ? 'DEACTIVATE' : 'ACTIVATE', color: isDeactivating ? '#f87171' : '#4ade80' }
        ],
        impact: {
            text: isDeactivating ? '⚠️ DEACTIVATION: Workers will not be able to select or checkout in this branch.' : '✓ ACTIVATION: Re-enables POS checkout and stock additions for this location.',
            type: isDeactivating ? 'danger' : 'success'
        },
        confirmText: isDeactivating ? '🏬 Yes, Deactivate' : '🏪 Yes, Activate',
        confirmClass: isDeactivating ? 'btn-danger' : 'btn-success',
        form: document.getElementById('toggleWhForm_' + id)
    });
}

function confirmCreateBackup() {
    showConfirmPopup({
        icon: '💾',
        title: 'Confirm Database Snapshot Backup',
        subtitle: 'Create a full instant snapshot of system data:',
        borderColor: '#22c55e',
        items: [
            { label: 'Backup Type', value: 'Complete JSON Relational Snapshot', color: '#60a5fa' },
            { label: 'Includes', value: 'Products, Stock, Sales, Debtors & Logs', color: '#4ade80' }
        ],
        impact: {
            text: '💾 INSTANT SNAPSHOT: Generates a timestamped JSON file ready for download or one-click restoration.',
            type: 'success'
        },
        confirmText: '💾 Yes, Generate Snapshot',
        confirmClass: 'btn-success',
        form: document.getElementById('backupForm')
    });
}

function confirmAddBranch() {
    const form = document.getElementById('addBranchForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const name = document.getElementById('newBranchName').value;
    const code = document.getElementById('newBranchCode').value;
    const manager = document.getElementById('newBranchManager').value || 'Store Manager';

    closeModal('modalAddBranch');

    showConfirmPopup({
        icon: '🏬',
        title: 'Confirm New Branch Creation',
        subtitle: 'Review new shop location details:',
        borderColor: '#22c55e',
        items: [
            { label: 'Branch Name', value: name, color: '#f8fafc' },
            { label: 'Branch Code', value: code, color: '#93c5fd' },
            { label: 'Manager Assigned', value: manager, color: '#fbbf24' }
        ],
        impact: {
            text: '🏬 MULTI-LOCATION REGISTRY: Initialises dedicated physical stock and ledger records for this new branch.',
            type: 'success'
        },
        confirmText: '🏬 Yes, Create Branch',
        confirmClass: 'btn-success',
        form: form
    });
}

function openEditBranchModal(wh) {
    document.getElementById('editBranchForm').action = '/settings/warehouse/update/' + wh.id;
    document.getElementById('editBranchName').value = wh.name;
    document.getElementById('editBranchCode').value = wh.code;
    document.getElementById('editBranchManager').value = wh.manager_name || '';
    document.getElementById('editBranchAddress').value = wh.address || '';
    document.getElementById('editBranchPhone').value = wh.phone || '';
    openModal('modalEditBranch');
}

function confirmEditBranch() {
    const form = document.getElementById('editBranchForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const name = document.getElementById('editBranchName').value;
    const code = document.getElementById('editBranchCode').value;
    const manager = document.getElementById('editBranchManager').value || 'Store Manager';

    closeModal('modalEditBranch');

    showConfirmPopup({
        icon: '🏬',
        title: 'Confirm Branch Details Update',
        subtitle: 'Review updated shop location details:',
        borderColor: '#3b82f6',
        items: [
            { label: 'Branch Name', value: name, color: '#f8fafc' },
            { label: 'Branch Code', value: code, color: '#93c5fd' },
            { label: 'Manager Assigned', value: manager, color: '#fbbf24' }
        ],
        impact: {
            text: '🏬 LOCATION UPDATE: Modifies branch profile across all POS terminals, inventory logs, and receipts.',
            type: 'info'
        },
        confirmText: '🏬 Yes, Save Changes',
        confirmClass: 'btn-primary',
        form: form
    });
}
</script>
@endpush
