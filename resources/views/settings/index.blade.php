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

                <!-- WhatsApp 9:00 PM Automated Daily Report Configuration -->
                <div style="background: rgba(37, 99, 235, 0.05); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 1.25rem; margin-top: 1.5rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <h4 style="font-size: 1.05rem; font-weight: 800; color: #60a5fa; display: flex; align-items: center; gap: 0.5rem;">
                                <span>📱</span> Owner WhatsApp Automated Daily Reconciliation Report
                            </h4>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                At 9:00 PM closing, the system automatically audits the day's sales, drawer cash, customer debt recoveries, and reversals, dispatching an executive summary directly to the store owner's WhatsApp.
                            </p>
                        </div>
                        <span style="font-size: 0.75rem; background: rgba(16, 185, 129, 0.15); color: #34d399; padding: 0.3rem 0.75rem; border-radius: 999px; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.3);">
                            Platform Gateway: {{ strtoupper(config('whatsapp.gateway', 'LOG')) }}
                        </span>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label style="font-weight: 700;">Owner WhatsApp Phone Number</label>
                            <input type="text" name="owner_whatsapp_phone" id="ownerWhatsappPhone" 
                                   value="{{ old('owner_whatsapp_phone', $settings->owner_whatsapp_phone) }}" 
                                   placeholder="e.g. 08012345678 or +2348012345678" 
                                   style="font-family: monospace; font-size: 0.95rem;">
                            <small style="color: var(--text-muted); font-size: 0.75rem;">Supports Nigerian (080...) and international (+234...) formats.</small>
                        </div>

                        <div class="form-group">
                            <label style="font-weight: 700;">Scheduled Daily Dispatch Time</label>
                            <input type="time" name="whatsapp_report_time" 
                                   value="{{ old('whatsapp_report_time', $settings->whatsapp_report_time ?? '21:00') }}" 
                                   style="font-size: 0.95rem;">
                            <small style="color: var(--text-muted); font-size: 0.75rem;">Default is 21:00 (9:00 PM closing reconciliation).</small>
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.6rem; margin-top: 0.75rem;">
                        <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.88rem; font-weight: 600;">
                            <input type="checkbox" name="whatsapp_report_enabled" value="1" {{ old('whatsapp_report_enabled', $settings->whatsapp_report_enabled) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--primary);">
                            <span>Enable Automated End-of-Day WhatsApp Daily Report</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.85rem; color: var(--text-muted);">
                            <input type="checkbox" name="whatsapp_branch_breakdown" value="1" {{ old('whatsapp_branch_breakdown', $settings->whatsapp_branch_breakdown ?? true) ? 'checked' : '' }} style="width: 16px; height: 16px; accent-color: var(--primary);">
                            <span>Include individual branch performance breakdown (for multi-branch stores)</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.85rem; color: var(--text-muted);">
                            <input type="checkbox" name="whatsapp_include_reversals" value="1" {{ old('whatsapp_include_reversals', $settings->whatsapp_include_reversals ?? true) ? 'checked' : '' }} style="width: 16px; height: 16px; accent-color: var(--primary);">
                            <span>Include supervisor voids & reversals audit log</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.85rem; color: var(--text-muted);">
                            <input type="checkbox" name="whatsapp_include_low_stock" value="1" {{ old('whatsapp_include_low_stock', $settings->whatsapp_include_low_stock ?? true) ? 'checked' : '' }} style="width: 16px; height: 16px; accent-color: var(--primary);">
                            <span>Include catalog low stock reorder alerts</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.85rem; color: var(--text-muted);">
                            <input type="checkbox" name="whatsapp_include_inventory" value="1" {{ old('whatsapp_include_inventory', $settings->whatsapp_include_inventory ?? true) ? 'checked' : '' }} style="width: 16px; height: 16px; accent-color: var(--primary);">
                            <span>Include daily physical stock movement (Stock In batches, Stock Out units & damage adjustments)</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.85rem; color: var(--text-muted);">
                            <input type="checkbox" name="whatsapp_include_transfers" value="1" {{ old('whatsapp_include_transfers', $settings->whatsapp_include_transfers ?? true) ? 'checked' : '' }} style="width: 16px; height: 16px; accent-color: var(--primary);">
                            <span>Include inter-branch logistics & transfers (Outbound, Inbound, and In-Transit goods)</span>
                        </label>

                        <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.85rem; color: var(--text-muted);">
                            <input type="checkbox" name="whatsapp_include_deliveries" value="1" {{ old('whatsapp_include_deliveries', $settings->whatsapp_include_deliveries ?? true) ? 'checked' : '' }} style="width: 16px; height: 16px; accent-color: var(--primary);">
                            <span>Include pending deliveries & unsupplied customer pickup backlog</span>
                        </label>
                    </div>

                    <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px dashed rgba(255,255,255,0.1); display: flex; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="sendTestWhatsAppReport()" style="font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                            <span>📲</span> Send Test WhatsApp Notification
                        </button>
                    </div>
                </div>

                <!-- Live Site Versioning & Overlay Updates Hub -->
                <div style="background: rgba(168, 85, 247, 0.05); border: 1px solid rgba(168, 85, 247, 0.3); border-radius: 12px; padding: 1.25rem; margin-top: 1.5rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <h4 style="font-size: 1.05rem; font-weight: 800; color: #c084fc; display: flex; align-items: center; gap: 0.5rem;">
                                <span>🏷️</span> System Version & Live Site Overlay Hub
                            </h4>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
                                Manage deployment overlays on live production servers (cPanel/FTP/VPS). Each release produces an overlay bundle to unpack directly over the live site.
                            </p>
                        </div>
                        <span style="font-size: 0.85rem; background: rgba(168, 85, 247, 0.2); color: #e9d5ff; padding: 0.35rem 0.85rem; border-radius: 999px; font-weight: 800; border: 1px solid rgba(168, 85, 247, 0.5);">
                            Release Tag: {{ config('version.tag', 'v1.2.0') }}
                        </span>
                    </div>

                    <div style="background: rgba(0,0,0,0.25); border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1rem; font-size: 0.82rem; line-height: 1.6;">
                        <div style="font-weight: 700; color: #f8fafc; margin-bottom: 0.3rem;">Latest Release Highlights (Overlaid Changes):</div>
                        <ul style="padding-left: 1.2rem; color: #cbd5e1; margin: 0;">
                            <li>Dedicated Admin Reversals & Voids Center (Tab 10) with 5 sub-consoles.</li>
                            <li>Automated 9:00 PM WhatsApp Daily Closing Reconciliation for store owners.</li>
                            <li>Deep System Check: Stock-In, Stock-Out, Inter-Branch Transfers, Debts & Backlog.</li>
                            <li>Delta Overlay Packager (<code>php artisan app:make-overlay</code>) for seamless cPanel updates.</li>
                        </ul>
                    </div>

                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                        <button type="button" class="btn btn-secondary" onclick="openVersionModal()" style="font-size: 0.82rem;">
                            📜 View Detailed Changelog
                        </button>
                        <button type="button" class="btn btn-warning" id="btnRunMigrations" onclick="runLiveOverlayMigrations()" style="font-size: 0.82rem; font-weight: 700;">
                            ⚡ Run Pending Migrations & Clear Cache
                        </button>
                    </div>
                    <div id="migrationOutputBox" style="display: none; margin-top: 1rem; background: #000; color: #4ade80; font-family: monospace; font-size: 0.78rem; padding: 0.75rem 1rem; border-radius: 6px; max-height: 180px; overflow-y: auto; white-space: pre-wrap;"></div>
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
                            <td>
                                <div>{{ $wh->manager_name ?? 'Branch Manager' }}</div>
                                @if(!empty($wh->manager_whatsapp_phone))
                                    <div style="font-size: 0.72rem; color: #4ade80; display: flex; align-items: center; gap: 3px; margin-top: 2px;">
                                        <span>💬</span> {{ $wh->manager_whatsapp_phone }}
                                    </div>
                                @endif
                            </td>
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
                                        phone: '{{ addslashes($wh->phone ?? '') }}',
                                        manager_whatsapp_phone: '{{ addslashes($wh->manager_whatsapp_phone ?? '') }}',
                                        whatsapp_report_enabled: {{ ($wh->whatsapp_report_enabled ?? true) ? 'true' : 'false' }}
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

                <div class="form-group">
                    <label>Manager WhatsApp Phone (for Daily Closing Alerts)</label>
                    <input type="text" name="manager_whatsapp_phone" id="newBranchManagerWhatsapp" placeholder="e.g. 08012345678">
                    <small style="color: var(--text-muted); font-size: 0.72rem;">Manager receives isolated daily closing reconciliations for this branch only.</small>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                    <input type="checkbox" name="whatsapp_report_enabled" id="newBranchWhatsappEnabled" value="1" checked style="width: 16px; height: 16px; accent-color: var(--primary);">
                    <label for="newBranchWhatsappEnabled" style="margin: 0; font-size: 0.85rem; cursor: pointer;">Enable Automated WhatsApp Reports for this Branch</label>
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

                <div class="form-group">
                    <label>Manager WhatsApp Phone (for Daily Closing Alerts)</label>
                    <input type="text" name="manager_whatsapp_phone" id="editBranchManagerWhatsapp" placeholder="e.g. 08012345678">
                    <small style="color: var(--text-muted); font-size: 0.72rem;">Manager receives isolated daily closing reconciliations for this branch only.</small>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                    <input type="checkbox" name="whatsapp_report_enabled" id="editBranchWhatsappEnabled" value="1" style="width: 16px; height: 16px; accent-color: var(--primary);">
                    <label for="editBranchWhatsappEnabled" style="margin: 0; font-size: 0.85rem; cursor: pointer;">Enable Automated WhatsApp Reports for this Branch</label>
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
    document.getElementById('editBranchManagerWhatsapp').value = wh.manager_whatsapp_phone || '';
    document.getElementById('editBranchWhatsappEnabled').checked = (wh.whatsapp_report_enabled !== false);
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

function sendTestWhatsAppReport() {
    const phoneInput = document.getElementById('ownerWhatsappPhone');
    const phone = phoneInput ? phoneInput.value.trim() : '';
    if (!phone) {
        alert('Please enter an Owner WhatsApp Phone Number first.');
        if (phoneInput) phoneInput.focus();
        return;
    }

    if (!confirm('Send a test WhatsApp reconciliation report to ' + phone + '?')) {
        return;
    }

    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const btn = event.currentTarget || event.target;
    const origText = btn.innerHTML;
    btn.innerHTML = '⏳ Sending...';
    btn.disabled = true;

    fetch('{{ route('settings.test.whatsapp') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ phone: phone })
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = origText;
        btn.disabled = false;
        if (data.success) {
            alert('✓ Success: Test WhatsApp report sent to ' + phone + ' via ' + data.gateway + '!\n' + (data.simulated ? '(Gateway running in local log/simulation mode)' : ''));
        } else {
            alert('❌ Failed to send WhatsApp message: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        btn.innerHTML = origText;
        btn.disabled = false;
        alert('❌ Network or server error: ' + err.message);
    });
}

function runLiveOverlayMigrations() {
    if (!confirm('Run database migrations and clear application cache now? This is safe to run after applying an overlay update.')) {
        return;
    }

    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const btn = document.getElementById('btnRunMigrations');
    const box = document.getElementById('migrationOutputBox');
    btn.disabled = true;
    btn.innerHTML = '⏳ Refreshing Database & Cache...';
    box.style.display = 'block';
    box.textContent = '> Initializing migration runner...\n';

    fetch('{{ route('settings.run-migrations') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '⚡ Run Pending Migrations & Clear Cache';
        if (data.success) {
            box.textContent += '\n✓ SUCCESS: ' + data.message + '\n\n' + (data.migrate_output || 'No pending migrations.') + '\n' + (data.cache_output || '');
            box.style.color = '#4ade80';
        } else {
            box.textContent += '\n❌ ERROR: ' + (data.error || 'Migration failed');
            box.style.color = '#f87171';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '⚡ Run Pending Migrations & Clear Cache';
        box.textContent += '\n❌ EXCEPTION: ' + err.message;
        box.style.color = '#f87171';
    });
}
</script>
@endpush
