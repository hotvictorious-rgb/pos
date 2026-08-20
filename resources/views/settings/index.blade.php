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
        <button class="set-tab" onclick="showTab('tabBackups', this)">💾 Data Safety & Backups</button>
    </div>

    <!-- Tab 1: Business & Receipt Information -->
    <div id="tabBusiness" class="tab-content active">
        <div class="card" style="max-width: 800px;">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem;">
                🏢 Business Profile & Printable Receipt Customizer
            </h3>

            <form method="POST" action="{{ route('settings.update') }}" onsubmit="return confirm('🏢 Confirm Settings Update:\n\nThis will update your business profile, currency symbol, and receipt template across the entire system. Proceed?')">
                @csrf

                <div class="form-group">
                    <label>Business Name (Shows on top of receipts & reports)</label>
                    <input type="text" name="businessName" value="{{ old('businessName', $settings->businessName) }}" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Business Phone Number(s)</label>
                        <input type="text" name="businessPhone" value="{{ old('businessPhone', $settings->businessPhone) }}" placeholder="+234 800 000 0000">
                    </div>

                    <div class="form-group">
                        <label>Official Email</label>
                        <input type="email" name="businessEmail" value="{{ old('businessEmail', $settings->businessEmail) }}" placeholder="admin@hysam.com">
                    </div>
                </div>

                <div class="form-group">
                    <label>Headquarters / Business Address</label>
                    <textarea name="businessAddress" rows="2">{{ old('businessAddress', $settings->businessAddress) }}</textarea>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Currency Symbol</label>
                        <input type="text" name="currency" value="{{ old('currency', $settings->currency ?? '₦') }}" required>
                    </div>

                    <div class="form-group">
                        <label>Default Low Stock Alert Threshold</label>
                        <input type="number" name="lowStockThreshold" value="{{ old('lowStockThreshold', $settings->lowStockThreshold ?? 5) }}" min="1" required>
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

                <button type="submit" class="btn btn-success btn-lg">
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
                                <form method="POST" action="{{ route('settings.warehouse.toggle', $wh->id) }}" onsubmit="return confirm('{{ $wh->is_active ? '🏬 Confirm Deactivating Location:\n\nThis will disable POS checkout and stock activities for ' . addslashes($wh->name) . '. Proceed?' : '🏬 Confirm Activating Location:\n\nThis will re-enable ' . addslashes($wh->name) . ' across the system. Proceed?' }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;">
                                        {{ $wh->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
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

                <form method="POST" action="/api/backups" onsubmit="return confirm('💾 Confirm Database Snapshot:\n\nThis will generate an instant JSON backup file containing all products, stock levels, sales history, and audit ledgers. Proceed?')">
                    @csrf
                    <button type="submit" class="btn btn-success">
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
                                <a href="/api/backups/{{ $b->id }}/download" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
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

    <!-- Modal: Add Branch Shop -->
    <div id="modalAddBranch" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">🏬 Add New Branch Location</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Set up a new shop, outlet, or depot to track physical stock independently.
            </p>

            <form method="POST" action="{{ route('settings.warehouse.store') }}" onsubmit="return confirm('🏬 Confirm New Branch Location:\n\nThis will initialize an independent physical stock registry for this new shop location. Proceed?')">
                @csrf
                <div class="form-group">
                    <label>Branch Name</label>
                    <input type="text" name="name" placeholder="e.g. Lekki Outlet / Shop 3" required>
                </div>

                <div class="form-group">
                    <label>Location Code</label>
                    <input type="text" name="code" placeholder="e.g. SHOP-03" required>
                </div>

                <div class="form-group">
                    <label>Branch Manager Name</label>
                    <input type="text" name="manager_name" placeholder="e.g. Samuel Ade">
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
                    <button type="submit" class="btn btn-primary" style="flex: 1;">✓ Save Branch Shop</button>
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
</script>
@endpush
