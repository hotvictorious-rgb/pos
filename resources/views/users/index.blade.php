@extends('layouts.app')

@section('title', 'Workers & Roles Management')

@push('styles')
<style>
    .user-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2rem;
    }

    .user-card {
        background: var(--card-bg);
        border: 2px solid var(--border);
        border-radius: 18px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.2s;
    }
    .user-card:hover { transform: translateY(-3px); border-color: #3b82f6; }
    .user-card.disabled-card { opacity: 0.6; border-color: #ef4444; background: rgba(220,38,38,0.05); }

    .role-badge-admin { background: rgba(220,38,38,0.2); color: #f87171; border: 1px solid rgba(220,38,38,0.4); }
    .role-badge-manager { background: rgba(59,130,246,0.2); color: #60a5fa; border: 1px solid rgba(59,130,246,0.4); }
    .role-badge-storekeeper { background: rgba(217,119,6,0.2); color: #fbbf24; border: 1px solid rgba(217,119,6,0.4); }
    .role-badge-cashier { background: rgba(34,197,94,0.2); color: #4ade80; border: 1px solid rgba(34,197,94,0.4); }
</style>
@endpush

@section('content')

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 800;">Staff & Role Permissions 👥</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                {{ auth()->user()?->role === 'viewer' ? 'Live roster of staff, assigned branch responsibilities, and permissions.' : 'Create and manage workers, assign branch responsibilities, and set anti-theft permission barriers.' }}
            </p>
        </div>
        @if(auth()->user()?->role !== 'viewer')
            <button class="btn btn-success btn-lg" onclick="openModal('modalAddUser')">
                ➕ Add New Worker
            </button>
        @else
            <span style="font-size: 0.82rem; font-weight: 800; color: #facc15; background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.4); padding: 0.5rem 1rem; border-radius: 10px;">
                👑 Executive Observer (View-Only Mode)
            </span>
        @endif
    </div>

    <!-- Role Explanation Banner -->
    <div style="background: rgba(37,99,235,0.1); border: 1px solid rgba(37,99,235,0.3); border-radius: 16px; padding: 1.25rem; margin-bottom: 2rem;">
        <h4 style="font-size: 0.85rem; font-weight: 800; color: #93c5fd; text-transform: uppercase; margin-bottom: 0.5rem;">
            🛡️ Role Permission Hierarchy:
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; font-size: 0.85rem; color: #cbd5e1;">
            <div>• <strong style="color: #f87171;">Auditor:</strong> Full access to profits, audits, stock resets, theft alerts.</div>
            <div>• <strong style="color: #fbbf24;">👑 Owner (View-Only):</strong> Silent executive view of all sales, stock, and reports without write access.</div>
            <div>• <strong style="color: #60a5fa;">Manager:</strong> Branch sales, dispatching transfers, daily shop reports.</div>
            <div>• <strong style="color: #c084fc;">Sales & Stock:</strong> Combined role for solo shop attendants (POS + Stock In/Out).</div>
            <div>• <strong style="color: #38bdf8;">Storekeeper:</strong> Supplier goods receipt, inter-shop transfer counts only.</div>
            <div>• <strong style="color: #4ade80;">Cashier:</strong> POS sales, collecting cash/POS, customer debts only.</div>
        </div>
    </div>

    <!-- Workers Grid -->
    <div class="user-grid">
        @forelse($users as $u)
        <div class="user-card {{ $u->disabled ? 'disabled-card' : '' }}">
            <div>
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                    <div>
                        <h3 style="font-size: 1.15rem; font-weight: 800; color: #f8fafc;">{{ $u->name }}</h3>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">{{ $u->email }}</div>
                    </div>
                    @php
                        $roleClass = match($u->role) {
                            'admin' => 'role-badge-admin',
                            'viewer' => 'role-badge-admin',
                            'manager' => 'role-badge-manager',
                            'sales_stock' => 'role-badge-manager',
                            'storekeeper' => 'role-badge-storekeeper',
                            default => 'role-badge-cashier',
                        };
                        $roleIcon = match($u->role) {
                            'admin' => '🛡️',
                            'viewer' => '👑',
                            'manager' => '🏢',
                            'sales_stock' => '💼',
                            'storekeeper' => '📦',
                            default => '💰',
                        };
                        $roleTitle = match($u->role) {
                            'viewer' => 'OWNER (VIEW-ONLY)',
                            'sales_stock' => 'SALES & STOCK',
                            default => strtoupper($u->role),
                        };
                        $roleStyle = match($u->role) {
                            'viewer' => 'background: rgba(234, 179, 8, 0.2); color: #facc15; border: 1px solid #eab308;',
                            'sales_stock' => 'background: rgba(168, 85, 247, 0.2); color: #c084fc; border: 1px solid #a855f7;',
                            default => '',
                        };
                    @endphp
                    <span class="badge {{ $roleClass }}" style="{{ $roleStyle }}">
                        {{ $roleIcon }} {{ $roleTitle }}
                    </span>
                </div>

                <div style="font-size: 0.8rem; color: #cbd5e1; margin-bottom: 0.5rem;">
                    Branch: <strong style="color: #60a5fa;">{{ $u->warehouse->name ?? 'All Branches / Central HQ' }}</strong>
                </div>

                <div style="font-size: 0.8rem; color: #cbd5e1; margin-bottom: 1rem;">
                    Status: 
                    @if($u->disabled)
                        <strong style="color: #f87171;">🔒 LOCKED / DISABLED</strong>
                    @else
                        <strong style="color: #4ade80;">✓ ACTIVE</strong>
                    @endif
                </div>
            </div>

            <!-- Action buttons -->
            @if(auth()->user()?->role !== 'viewer')
                <div style="display: flex; gap: 0.5rem; border-top: 1px solid var(--border); padding-top: 1rem; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary" style="padding: 0.5rem 0.85rem; font-size: 0.8rem; border-color: #3b82f6; color: #93c5fd; flex: 1;" onclick="openEditUserModal({{ json_encode($u) }})">
                        ✏️ Edit / Reassign
                    </button>

                    <form id="toggleForm_{{ $u->id }}" method="POST" action="{{ route('users.toggle', $u->id) }}" style="flex: 1;">
                        @csrf
                        <button type="button" class="btn {{ $u->disabled ? 'btn-success' : 'btn-danger' }} btn-block" style="padding: 0.5rem; font-size: 0.8rem;" onclick="confirmToggleWorker('{{ $u->id }}', '{{ addslashes($u->name) }}', {{ $u->disabled ? 'true' : 'false' }}, '{{ addslashes($u->role) }}')">
                            {{ $u->disabled ? '🔓 Unlock' : '🔒 Lock' }}
                        </button>
                    </form>

                    <button class="btn btn-secondary" style="padding: 0.5rem 0.85rem; font-size: 0.8rem;" onclick="openPasswordModal('{{ $u->id }}', '{{ addslashes($u->name) }}')">
                        🔑 PIN
                    </button>
                </div>
            @endif
        </div>
        @empty
        <div style="grid-column: 1/-1; text-align: center; padding: 3rem; background: var(--card-bg); border-radius: 18px;">
            <h3>No Workers Registered</h3>
        </div>
        @endforelse
    </div>

    <!-- Modal: Edit Worker Profile & Location Reassignment -->
    <div id="modalEditUser" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">✏️ Edit Worker & Reassign Location</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Reassign branch location, update role permissions, or modify contact information.
            </p>

            <form id="editUserForm" method="POST" action="">
                @csrf
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="editUserName" required>
                </div>

                <div class="form-group">
                    <label>Email Address / Username</label>
                    <input type="email" name="email" id="editUserEmail" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Assign Role & Authority</label>
                        <select name="role" id="editUserRole" required>
                            <option value="cashier">💰 Cashier / Sales Officer</option>
                            <option value="sales_stock">💼 Sales & Stock Officer (Combined)</option>
                            <option value="storekeeper">📦 Storekeeper (Inventory Only)</option>
                            <option value="manager">🏢 Branch Manager</option>
                            <option value="admin">🛡️ Auditor / Super Admin</option>
                            <option value="viewer">👑 Executive Owner (View-Only / Silent Auditor)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assigned Branch Location</label>
                        <select name="warehouse_id" id="editUserWarehouse">
                            <option value="">🏢 All Branches / Central HQ</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">
                                    {{ $wh->name }} ({{ $wh->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>New Password / PIN (Optional - Leave blank to keep current)</label>
                    <input type="password" name="password" id="editUserPass" placeholder="Leave blank to keep unchanged">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditUser')">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmEditUser()">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add New Worker -->
    <div id="modalAddUser" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">➕ Add New Worker Account</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Set up a login for cashiers, storekeepers, or branch managers.
            </p>

            <form id="addUserForm" method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="newUserName" placeholder="e.g. John Okoro" required>
                </div>

                <div class="form-group">
                    <label>Email Address / Username</label>
                    <input type="email" name="email" id="newUserEmail" placeholder="e.g. john@hysam.com" required>
                </div>

                <div class="form-group">
                    <label>Password / PIN</label>
                    <input type="password" name="password" id="newUserPass" placeholder="Minimum 6 characters" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Assign Role & Authority</label>
                        <select name="role" id="newUserRole" required>
                            <option value="cashier">💰 Cashier / Sales Officer</option>
                            <option value="sales_stock">💼 Sales & Stock Officer (Combined)</option>
                            <option value="storekeeper">📦 Storekeeper (Inventory Only)</option>
                            <option value="manager">🏢 Branch Manager</option>
                            <option value="admin">🛡️ Auditor / Super Admin</option>
                            <option value="viewer">👑 Executive Owner (View-Only / Silent Auditor)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Assigned Branch Location</label>
                        <select name="warehouse_id" id="newUserWarehouse">
                            <option value="">-- Central HQ (All Shops) --</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalAddUser')">Cancel</button>
                    <button type="button" class="btn btn-success" style="flex: 1;" onclick="confirmAddUser()">✓ Create Worker Account</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Modal: Reset Password -->
    <div id="modalResetPass" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">🔑 Reset Worker Password</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;" id="resetPassSubtitle">
                Enter a new password for worker.
            </p>

            <form id="resetPassForm" method="POST" action="">
                @csrf
                <div class="form-group">
                    <label>New Password / PIN</label>
                    <input type="password" name="new_password" id="resetPassInput" placeholder="Minimum 6 characters" required>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalResetPass')">Cancel</button>
                    <button type="button" class="btn btn-primary" style="flex: 1;" onclick="confirmResetPass()">✓ Update Password</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
let currentResetWorkerName = '';

function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openPasswordModal(id, name) {
    currentResetWorkerName = name;
    document.getElementById('resetPassForm').action = '/users/reset-password/' + id;
    document.getElementById('resetPassSubtitle').textContent = 'Updating password for ' + name;
    openModal('modalResetPass');
}

function openEditUserModal(user) {
    document.getElementById('editUserForm').action = '/users/update/' + user.id;
    document.getElementById('editUserName').value = user.name || '';
    document.getElementById('editUserEmail').value = user.email || '';
    document.getElementById('editUserRole').value = user.role || 'cashier';
    document.getElementById('editUserWarehouse').value = user.warehouse_id || '';
    document.getElementById('editUserPass').value = '';
    openModal('modalEditUser');
}

function confirmEditUser() {
    const form = document.getElementById('editUserForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const name = document.getElementById('editUserName').value;
    const email = document.getElementById('editUserEmail').value;
    const roleSelect = document.getElementById('editUserRole');
    const roleName = roleSelect.options[roleSelect.selectedIndex].text;
    const whSelect = document.getElementById('editUserWarehouse');
    const whName = whSelect.options[whSelect.selectedIndex].text;

    closeModal('modalEditUser');

    showConfirmPopup({
        icon: '✏️',
        title: 'Confirm Worker Updates & Reassignment',
        subtitle: 'Review profile changes and location transfer:',
        borderColor: '#3b82f6',
        items: [
            { label: 'Worker Name', value: name, color: '#f8fafc' },
            { label: 'Username / Email', value: email, color: '#93c5fd' },
            { label: 'Role Authority', value: roleName, color: '#4ade80' },
            { label: 'Assigned Location', value: whName, color: '#fbbf24' }
        ],
        impact: {
            text: '🏢 LOCATION REASSIGNMENT: Worker POS and inventory access will immediately switch to the new assigned branch.',
            type: 'info'
        },
        confirmText: '💾 Yes, Save Updates',
        confirmClass: 'btn-primary',
        form: form
    });
}

function confirmToggleWorker(id, name, isDisabled, role) {
    const isUnlocking = isDisabled;
    showConfirmPopup({
        icon: isUnlocking ? '🔓' : '🔒',
        title: isUnlocking ? 'Confirm Unlocking Worker' : 'Confirm Locking Worker',
        subtitle: 'Review account security status change:',
        borderColor: isUnlocking ? '#22c55e' : '#ef4444',
        items: [
            { label: 'Worker Name', value: name, color: '#f8fafc' },
            { label: 'Role Authority', value: role.toUpperCase(), color: '#60a5fa' },
            { label: 'Current State', value: isUnlocking ? 'LOCKED' : 'ACTIVE', color: isUnlocking ? '#f87171' : '#4ade80' },
            { label: 'New State', value: isUnlocking ? 'ACTIVE (Permitted to Log In)' : 'LOCKED (Access Suspended)', color: isUnlocking ? '#4ade80' : '#f87171' }
        ],
        impact: {
            text: isUnlocking ? '🔓 UNLOCK: Immediately allows this worker to log in and access POS / Inventory modules.' : '🔒 LOCK: Immediately revokes all active login sessions and prevents POS checkout.',
            type: isUnlocking ? 'success' : 'danger'
        },
        confirmText: isUnlocking ? '🔓 Yes, Unlock Worker' : '🔒 Yes, Lock Worker',
        confirmClass: isUnlocking ? 'btn-success' : 'btn-danger',
        form: document.getElementById('toggleForm_' + id)
    });
}

function confirmAddUser() {
    const form = document.getElementById('addUserForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const name = document.getElementById('newUserName').value;
    const email = document.getElementById('newUserEmail').value;
    const roleSelect = document.getElementById('newUserRole');
    const roleName = roleSelect.options[roleSelect.selectedIndex].text;
    const whSelect = document.getElementById('newUserWarehouse');
    const whName = whSelect.options[whSelect.selectedIndex].text;

    closeModal('modalAddUser');

    showConfirmPopup({
        icon: '➕',
        title: 'Confirm Worker Account Creation',
        subtitle: 'Review new staff profile before saving:',
        borderColor: '#22c55e',
        items: [
            { label: 'Full Name', value: name, color: '#f8fafc' },
            { label: 'Username / Email', value: email, color: '#93c5fd' },
            { label: 'Role & Authority', value: roleName, color: '#4ade80' },
            { label: 'Assigned Location', value: whName, color: '#fbbf24' }
        ],
        impact: {
            text: '🛡️ SECURITY: Worker account will be activated and bound to assigned branch permissions immediately.',
            type: 'success'
        },
        confirmText: '✅ Yes, Create Account',
        confirmClass: 'btn-success',
        form: form
    });
}

function confirmResetPass() {
    const form = document.getElementById('resetPassForm');
    const input = document.getElementById('resetPassInput');
    if (!input.value || input.value.length < 6) {
        input.reportValidity();
        return;
    }

    closeModal('modalResetPass');

    showConfirmPopup({
        icon: '🔑',
        title: 'Confirm Password Reset',
        subtitle: 'Review credential update for staff member:',
        borderColor: '#3b82f6',
        items: [
            { label: 'Worker Name', value: currentResetWorkerName, color: '#f8fafc' },
            { label: 'Action', value: 'Overwriting Login Password', color: '#60a5fa' }
        ],
        impact: {
            text: '🔑 CREDENTIAL UPDATE: The worker must use this new password for all subsequent logins.',
            type: 'warning'
        },
        confirmText: '🔑 Yes, Update Password',
        confirmClass: 'btn-primary',
        form: form
    });
}
</script>
@endpush
