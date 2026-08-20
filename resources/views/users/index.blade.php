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
                Create and manage workers, assign branch responsibilities, and set anti-theft permission barriers.
            </p>
        </div>
        <button class="btn btn-success btn-lg" onclick="openModal('modalAddUser')">
            ➕ Add New Worker
        </button>
    </div>

    <!-- Role Explanation Banner -->
    <div style="background: rgba(37,99,235,0.1); border: 1px solid rgba(37,99,235,0.3); border-radius: 16px; padding: 1.25rem; margin-bottom: 2rem;">
        <h4 style="font-size: 0.85rem; font-weight: 800; color: #93c5fd; text-transform: uppercase; margin-bottom: 0.5rem;">
            🛡️ Role Permission Hierarchy:
        </h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem; font-size: 0.85rem; color: #cbd5e1;">
            <div>• <strong style="color: #f87171;">Auditor:</strong> Full access to profits, audits, stock resets, theft alerts.</div>
            <div>• <strong style="color: #60a5fa;">Manager:</strong> Branch sales, dispatching transfers, daily shop reports.</div>
            <div>• <strong style="color: #fbbf24;">Storekeeper:</strong> Supplier goods receipt, inter-shop transfer counts.</div>
            <div>• <strong style="color: #4ade80;">Cashier:</strong> POS sales, collecting cash/POS, recording part-payments.</div>
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
                            'manager' => 'role-badge-manager',
                            'storekeeper' => 'role-badge-storekeeper',
                            default => 'role-badge-cashier',
                        };
                        $roleIcon = match($u->role) {
                            'admin' => '🛡️',
                            'manager' => '🏢',
                            'storekeeper' => '📦',
                            default => '💰',
                        };
                    @endphp
                    <span class="badge {{ $roleClass }}">
                        {{ $roleIcon }} {{ strtoupper($u->role) }}
                    </span>
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
            <div style="display: flex; gap: 0.5rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                <form method="POST" action="{{ route('users.toggle', $u->id) }}" style="flex: 1;" onsubmit="return confirm('Change access status for this worker?')">
                    @csrf
                    <button type="submit" class="btn {{ $u->disabled ? 'btn-success' : 'btn-danger' }} btn-block" style="padding: 0.5rem; font-size: 0.8rem;">
                        {{ $u->disabled ? '🔓 Unlock' : '🔒 Lock Access' }}
                    </button>
                </form>

                <button class="btn btn-secondary" style="padding: 0.5rem 0.85rem; font-size: 0.8rem;" onclick="openPasswordModal('{{ $u->id }}', '{{ addslashes($u->name) }}')">
                    🔑 Reset PIN
                </button>
            </div>
        </div>
        @empty
        <div style="grid-column: 1/-1; text-align: center; padding: 3rem; background: var(--card-bg); border-radius: 18px;">
            <h3>No Workers Registered</h3>
        </div>
        @endforelse
    </div>

    <!-- Modal: Add New Worker -->
    <div id="modalAddUser" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">➕ Add New Worker Account</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Set up a login for cashiers, storekeepers, or branch managers.
            </p>

            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" placeholder="e.g. John Okoro" required>
                </div>

                <div class="form-group">
                    <label>Email Address / Username</label>
                    <input type="email" name="email" placeholder="e.g. john@hysam.com" required>
                </div>

                <div class="form-group">
                    <label>Password / PIN</label>
                    <input type="password" name="password" placeholder="Minimum 6 characters" required>
                </div>

                <div class="form-group">
                    <label>Assign Role & Authority</label>
                    <select name="role" required>
                        <option value="cashier">💰 Cashier / Sales Officer (POS & Part-Payments only)</option>
                        <option value="storekeeper">📦 Storekeeper (Stock In & Transfers only)</option>
                        <option value="manager">🏢 Branch Manager (Local Shop Sales & Stock)</option>
                        <option value="admin">🛡️ Auditor / Super Admin (Full Unrestricted Access)</option>
                    </select>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalAddUser')">Cancel</button>
                    <button type="submit" class="btn btn-success" style="flex: 1;">✓ Create Worker Account</button>
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
                    <input type="password" name="new_password" placeholder="Minimum 6 characters" required>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalResetPass')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">✓ Update Password</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openPasswordModal(id, name) {
    document.getElementById('resetPassForm').action = '/users/reset-password/' + id;
    document.getElementById('resetPassSubtitle').textContent = 'Updating password for ' + name;
    openModal('modalResetPass');
}
</script>
@endpush
