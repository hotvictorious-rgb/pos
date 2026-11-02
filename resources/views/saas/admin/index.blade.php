<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Master Control Suite — Platform Super Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0284c7;
            --primary-glow: rgba(2, 132, 199, 0.35);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #090e17;
            --card-bg: #111827;
            --card-hover: #172236;
            --border: #1f293d;
            --text: #f8fafc;
            --text-muted: #94a3b8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); color: var(--text); padding: 2rem; min-height: 100vh; }
        
        /* Header */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .header-title h1 { font-size: 1.75rem; font-weight: 800; background: linear-gradient(135deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: -0.5px; }
        .header-title p { color: var(--text-muted); font-size: 0.88rem; margin-top: 0.25rem; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--card-bg); border: 1px solid var(--border); padding: 1.25rem; border-radius: 14px; position: relative; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .stat-title { font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 1.55rem; font-weight: 800; color: #38bdf8; margin-top: 0.4rem; }

        /* Cards & Containers */
        .card-section { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 8px 30px rgba(0,0,0,0.25); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem; }
        .section-title { font-size: 1.15rem; font-weight: 800; color: #f8fafc; display: flex; align-items: center; gap: 0.5rem; }

        /* Tables */
        .table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border); background: #0b1120; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 0.85rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.84rem; vertical-align: middle; }
        th { background: #131d31; color: var(--text-muted); font-weight: 700; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; }
        tr:hover { background: rgba(30, 41, 59, 0.4); }

        /* Badges */
        .badge { padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; display: inline-flex; align-items: center; text-transform: uppercase; letter-spacing: 0.04em; }
        .badge-active { background: rgba(16, 185, 129, 0.18); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.35); }
        .badge-trial { background: rgba(59, 130, 246, 0.18); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.35); }
        .badge-suspended { background: rgba(239, 68, 68, 0.18); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.35); }
        .badge-plan { background: rgba(99, 102, 241, 0.18); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.35); }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.9rem; border-radius: 8px; font-weight: 700; font-size: 0.82rem; text-decoration: none; border: none; cursor: pointer; transition: all 0.15s ease; font-family: inherit; }
        .btn:hover { opacity: 0.92; transform: translateY(-1px); }
        .btn-primary { background: linear-gradient(135deg, #0284c7, #6366f1); color: white; box-shadow: 0 4px 12px var(--primary-glow); }
        .btn-success { background: #059669; color: white; }
        .btn-warning { background: #d97706; color: white; }
        .btn-danger  { background: #dc2626; color: white; }
        .btn-secondary { background: #1f293d; color: #cbd5e1; border: 1px solid #334155; }
        .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.74rem; border-radius: 6px; }

        /* Search & Filter Bar */
        .search-bar-wrap { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
        .search-input { min-width: 260px; padding: 0.5rem 0.85rem; background: #090e17; border: 1px solid var(--border); border-radius: 8px; color: #f8fafc; font-size: 0.84rem; outline: none; }
        .search-input:focus { border-color: var(--primary); }
        .filter-pill { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #131d31; color: var(--text-muted); border: 1px solid var(--border); cursor: pointer; transition: all 0.15s ease; }
        .filter-pill.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* Forms */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-size: 0.74rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.04em; }
        input, select, textarea { width: 100%; padding: 0.65rem 0.85rem; background: #090e17; border: 1px solid var(--border); border-radius: 8px; color: #f8fafc; font-size: 0.86rem; outline: none; font-family: inherit; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); }

        /* Alerts */
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; padding: 0.9rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 0.9rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }

        /* Modals */
        .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
        .modal-backdrop.active { display: flex; }
        .modal-card { background: #0f172a; border: 1px solid #334155; border-radius: 16px; width: 100%; max-width: 520px; padding: 1.75rem; box-shadow: 0 25px 50px rgba(0,0,0,0.6); }

        /* Password input toggle */
        .password-field-wrapper { position: relative; display: flex; align-items: center; width: 100%; }
        .password-field-wrapper input { padding-right: 40px; }
        .password-toggle-btn { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: #94a3b8; cursor: pointer; font-size: 16px; padding: 4px; line-height: 1; border-radius: 4px; }
        .password-toggle-btn:hover { color: #f8fafc; }

        @media (max-width: 768px) {
            body { padding: 1rem; }
            .header { flex-direction: column; align-items: flex-start; }
            .card-section { padding: 1.1rem; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <div class="header">
        <div class="header-title">
            <h1>👑 SaaS Platform Master Control Suite</h1>
            <p>Global multi-tenant platform oversight, revenue metrics, billing verifications, and quota management</p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="{{ route('landing') }}" class="btn btn-secondary">🌐 Public Landing Page</a>
            <form action="{{ route('logout') }}" method="POST" style="display: inline; margin: 0;">
                @csrf
                <button type="submit" class="btn btn-danger">🚪 Sign Out</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">
            <span>✓</span> <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="alert-error">
            <span>⚠️</span> <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- 1. FINANCIAL & VOLUME ANALYTICS BAR -->
    <div class="stats-grid">
        @if($canTenants)
        <div class="stat-card">
            <div class="stat-title">Monthly Recurring Revenue (MRR)</div>
            <div class="stat-value" style="color: #34d399;">
                {{ $settings['currency_symbol'] ?? '₦' }}{{ number_format($mrr ?? 0, 2) }}
            </div>
            <span style="font-size: 0.72rem; color: #94a3b8; margin-top: 0.25rem; display: block;">Across active paying tenants</span>
        </div>
        <div class="stat-card">
            <div class="stat-title">Total Registered Tenants</div>
            <div class="stat-value">{{ $totalTenants }}</div>
            <span style="font-size: 0.72rem; color: #94a3b8; margin-top: 0.25rem; display: block;">Platform business accounts</span>
        </div>
        <div class="stat-card">
            <div class="stat-title">Tenant Status Breakdown</div>
            <div style="margin-top: 0.4rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <span class="badge badge-active">{{ $activeTenants }} Active</span>
                <span class="badge badge-trial">{{ $trialTenants }} Trial</span>
                <span class="badge badge-suspended">{{ $suspendedTenants }} Suspended</span>
            </div>
        </div>
        <div class="stat-card" style="{{ $pendingPayments->count() > 0 ? 'border-color: #f59e0b; background: rgba(245, 158, 11, 0.08);' : '' }}">
            <div class="stat-title">Pending Bank Verifications</div>
            <div class="stat-value" style="color: {{ $pendingPayments->count() > 0 ? '#fbbf24' : '#94a3b8' }};">
                {{ $pendingPayments->count() }} Notice{{ $pendingPayments->count() === 1 ? '' : 's' }}
            </div>
            <span style="font-size: 0.72rem; color: {{ $pendingPayments->count() > 0 ? '#fde68a' : '#64748b' }}; margin-top: 0.25rem; display: block;">
                {{ $pendingPayments->count() > 0 ? 'Requires action below' : 'All clear' }}
            </span>
        </div>
        @endif

        @if($canHealth)
        <div class="stat-card">
            <div class="stat-title">Platform Total Branches</div>
            <div class="stat-value" style="color: #c084fc;">{{ $totalBranchesPlatform }} Branches</div>
            <span style="font-size: 0.72rem; color: #94a3b8; margin-top: 0.25rem; display: block;">All retail locations</span>
        </div>
        @endif
    </div>

    @if($canTenants)
    <!-- 2. PENDING BANK PAYMENT VERIFICATIONS QUEUE -->
    <div class="card-section" style="{{ $pendingPayments->count() > 0 ? 'border-color: rgba(245, 158, 11, 0.4);' : '' }}">
        <div class="section-header">
            <div>
                <div class="section-title">
                    <span>💳</span> <span>Pending Bank Transfer Verifications</span>
                    @if($pendingPayments->count() > 0)
                        <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.5);">
                            {{ $pendingPayments->count() }} Action Required
                        </span>
                    @endif
                </div>
                <p style="color: #94a3b8; font-size: 0.8rem; margin-top: 0.2rem;">
                    Notices submitted by tenant business owners via the in-app Subscription & Billing Hub awaiting corporate bank confirmation.
                </p>
            </div>
        </div>

        @if($pendingPayments->count() > 0)
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tenant / Business</th>
                        <th>Amount Paid</th>
                        <th>Target Plan</th>
                        <th>Bank & Reference Code</th>
                        <th>Submitted At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingPayments as $payment)
                        @php
                            $pmeta = $payment->metadata ?? [];
                            $tTarget = \App\Models\Tenant::find($pmeta['tenant_id'] ?? $payment->tenant_id);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $pmeta['tenant_name'] ?? ($tTarget?->name ?? 'Unknown Business') }}</strong><br>
                                <span style="font-size: 0.72rem; color: #94a3b8;">{{ $pmeta['owner_email'] ?? ($tTarget?->owner_email ?? '') }}</span>
                            </td>
                            <td>
                                <strong style="color: #34d399; font-size: 1rem;">
                                    {{ $settings['currency_symbol'] ?? '₦' }}{{ number_format($pmeta['amount'] ?? 0, 2) }}
                                </strong>
                            </td>
                            <td>
                                <span class="badge badge-plan">{{ strtoupper($pmeta['plan'] ?? 'pro') }}</span>
                            </td>
                            <td>
                                <strong>{{ $pmeta['bank'] ?? 'Bank Transfer' }}</strong><br>
                                <code style="font-family: 'JetBrains Mono', monospace; color: #38bdf8; font-size: 0.75rem;">
                                    {{ $pmeta['reference'] ?? 'NO-REF' }}
                                </code>
                            </td>
                            <td>
                                <span style="color: #cbd5e1;">{{ date('M d, Y', strtotime($pmeta['submitted_at'] ?? $payment->created_at)) }}</span><br>
                                <span style="font-size: 0.72rem; color: #94a3b8;">{{ \Carbon\Carbon::parse($pmeta['submitted_at'] ?? $payment->created_at)->diffForHumans() }}</span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap;">
                                    <!-- Approve Form -->
                                    <form action="{{ route('saas.admin.payments.approve', $payment->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Confirm receipt of payment and activate this tenant?');">
                                        @csrf
                                        <select name="months" style="width: auto; padding: 0.3rem 0.5rem; font-size: 0.75rem; border-radius: 6px; margin-right: 0.25rem;">
                                            <option value="1">1 Mo</option>
                                            <option value="3">3 Mos</option>
                                            <option value="6">6 Mos</option>
                                            <option value="12">12 Mos</option>
                                        </select>
                                        <button type="submit" class="btn btn-success btn-sm">✅ Approve &amp; Activate</button>
                                    </form>

                                    <!-- Reject Form -->
                                    <form action="{{ route('saas.admin.payments.reject', $payment->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('Reject this payment notice?');">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5;">✕ Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div style="background: #0b1120; border: 1px dashed var(--border); border-radius: 10px; padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
            <span>✓</span> Zero pending bank transfers. All submitted teller notices have been processed.
        </div>
        @endif
    </div>

    <!-- 3. BUSINESS TENANTS DIRECTORY -->
    <div class="card-section">
        <div class="section-header">
            <div>
                <div class="section-title">
                    <span>🏢</span> <span>Business Tenants Directory</span>
                </div>
                <p style="color: #94a3b8; font-size: 0.8rem; margin-top: 0.2rem;">
                    Manage active store subscriptions, customize branch and staff quotas, and inspect business accounts.
                </p>
            </div>
            
            <!-- Live Search & Status Filters -->
            <div class="search-bar-wrap">
                <input type="text" id="tenantSearchInput" class="search-input" placeholder="🔍 Search business, email, phone, ID..." onkeyup="filterTenantsTable()">
                <button type="button" class="filter-pill active" onclick="setTenantStatusFilter('ALL', this)">All ({{ $tenants->count() }})</button>
                <button type="button" class="filter-pill" onclick="setTenantStatusFilter('active', this)">Active ({{ $activeTenants }})</button>
                <button type="button" class="filter-pill" onclick="setTenantStatusFilter('trial', this)">Trial ({{ $trialTenants }})</button>
                <button type="button" class="filter-pill" onclick="setTenantStatusFilter('suspended', this)">Suspended ({{ $suspendedTenants }})</button>
            </div>
        </div>

        <div class="table-wrap">
            <table id="tenantsTable">
                <thead>
                    <tr>
                        <th>Company & Tenant ID</th>
                        <th>Owner Details</th>
                        <th>Plan Tier</th>
                        <th>Status</th>
                        <th>Branch & User Quotas</th>
                        <th>Expiry / Renewal</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tenants as $t)
                        @php
                            $bPct = $t->max_branches > 0 ? min(100, round(($t->warehouses_count / $t->max_branches) * 100)) : 100;
                            $uPct = $t->max_users > 0 ? min(100, round(($t->users_count / $t->max_users) * 100)) : 100;
                        @endphp
                        <tr class="tenant-row" data-status="{{ $t->status }}" data-search="{{ strtolower($t->name . ' ' . $t->id . ' ' . $t->owner_email . ' ' . $t->owner_phone) }}">
                            <td>
                                <strong>{{ $t->name }}</strong><br>
                                <code style="font-family: 'JetBrains Mono', monospace; color: #94a3b8; font-size: 0.72rem;">{{ $t->id }}</code>
                            </td>
                            <td>
                                <span style="color: #f1f5f9;">{{ $t->owner_email }}</span><br>
                                <span style="color: #94a3b8; font-size: 0.75rem;">{{ $t->owner_phone ?: 'No Phone' }}</span>
                            </td>
                            <td>
                                <span class="badge badge-plan">
                                    {{ strtoupper($t->plan) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $t->status }}">
                                    {{ $t->status }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.35rem; min-width: 140px;">
                                    <div>
                                        <span style="font-size: 0.75rem; font-weight: 700;">🏬 {{ $t->warehouses_count }} / {{ $t->max_branches > 500 ? '∞' : $t->max_branches }}</span>
                                        <span style="font-size: 0.7rem; color: #94a3b8;">Branches</span>
                                    </div>
                                    <div>
                                        <span style="font-size: 0.75rem; font-weight: 700;">👥 {{ $t->users_count }} / {{ $t->max_users > 500 ? '∞' : $t->max_users }}</span>
                                        <span style="font-size: 0.7rem; color: #94a3b8;">Users</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($t->trial_ends_at)
                                    <span style="color: {{ $t->trial_ends_at->isPast() ? '#f87171' : '#38bdf8' }}; font-weight: 700;">
                                        {{ $t->trial_ends_at->format('M d, Y') }}
                                    </span><br>
                                    <span style="color: #94a3b8; font-size: 0.72rem;">({{ $t->trial_ends_at->diffForHumans() }})</span>
                                @else
                                    <span style="color: #94a3b8;">Standing / Lifetime</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.35rem; flex-wrap: wrap; align-items: center;">
                                    <!-- Edit Limits Modal Button -->
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openEditLimitsModal('{{ $t->id }}', '{{ addslashes($t->name) }}', '{{ $t->plan }}', '{{ $t->max_branches }}', '{{ $t->max_users }}', '{{ $t->status }}', '{{ $t->trial_ends_at ? $t->trial_ends_at->format('Y-m-d') : '' }}')">
                                        ⚙️ Limits
                                    </button>

                                    <!-- Reset Password Modal Button -->
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openResetPasswordModal('{{ $t->id }}', '{{ addslashes($t->name) }}', '{{ $t->owner_email }}')">
                                        🔑 Reset Pass
                                    </button>

                                    <!-- Activate / Suspend Toggle -->
                                    <form action="{{ route('saas.admin.toggle', $t->id) }}" method="POST" style="display: inline;">
                                        @csrf
                                        @if($t->status === 'active')
                                            <input type="hidden" name="status" value="suspended">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Suspend Tenant">Suspend</button>
                                        @else
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="btn btn-success btn-sm" title="Activate Tenant">Activate</button>
                                        @endif
                                    </form>

                                    <!-- +14d Quick Extension -->
                                    <form action="{{ route('saas.admin.limits', $t->id) }}" method="POST" style="display: inline;">
                                        @csrf
                                        <input type="hidden" name="extend_trial" value="14">
                                        <button type="submit" class="btn btn-warning btn-sm" title="Add 14 days">+14d</button>
                                    </form>

                                    <!-- Delete Button -->
                                    @if($t->id !== 'default-tenant')
                                        <form action="{{ route('saas.admin.delete', $t->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('⚠️ DANGER: Permanently delete tenant \'{{ addslashes($t->name) }}\' and purge all their sales, stock, and records?');">
                                            @csrf
                                            <button type="submit" class="btn btn-danger btn-sm" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5;" title="Permanently Delete">🗑️</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($canTenants || $canSettings)
    <!-- 4. QUICK ADD TENANT & GLOBAL SETTINGS DUAL GRID -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">

        @if($canTenants)
        <!-- Section: Create New Business Tenant -->
        <div class="card-section" style="margin-bottom: 0;">
            <div class="section-title">➕ Create New Business Tenant</div>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                Instantly provision a company tenant with its initial main headquarters branch and owner admin account.
            </p>
            <form action="{{ route('saas.admin.tenant.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Business / Company Name</label>
                    <input type="text" name="business_name" required placeholder="e.g. Victorious Supermarket Ltd">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" required placeholder="e.g. Victor E.">
                    </div>
                    <div class="form-group">
                        <label>Owner Phone</label>
                        <input type="text" name="owner_phone" required placeholder="e.g. 08012345678">
                    </div>
                </div>

                <div class="form-group">
                    <label>Owner Email Address (Login ID)</label>
                    <input type="email" name="owner_email" required placeholder="e.g. owner@victoriousmarket.com">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Subscription Plan</label>
                        <select name="plan" required>
                            <option value="basic">Basic Plan (1 Branch)</option>
                            <option value="pro" selected>Pro Plan (5 Branches)</option>
                            <option value="enterprise">Enterprise Plan (Unlimited)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Account Status</label>
                        <select name="status" required>
                            <option value="active" selected>Active & Verified</option>
                            <option value="trial">14-Day Free Trial</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem; padding: 0.75rem;">
                    🚀 Create Business Account
                </button>
            </form>
        </div>
        @endif

        @if($canSettings)
        <!-- Section: Global SaaS Configuration Settings -->
        <div class="card-section" style="margin-bottom: 0;">
            <div class="section-title">⚙️ SaaS Global Platform Settings</div>
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                Configure dynamic pricing, direct corporate bank transfer details, and Paystack payment gateway.
            </p>
            <form action="{{ route('saas.admin.settings') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Platform Name</label>
                    <input type="text" name="platform_name" value="{{ $settings['platform_name'] ?? '' }}" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Support Email</label>
                        <input type="email" name="support_email" value="{{ $settings['support_email'] ?? '' }}" required>
                    </div>
                    <div class="form-group">
                        <label>Support Phone</label>
                        <input type="text" name="support_phone" value="{{ $settings['support_phone'] ?? '' }}" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Currency Symbol</label>
                        <input type="text" name="currency_symbol" value="{{ $settings['currency_symbol'] ?? '₦' }}" required>
                    </div>
                    <div class="form-group">
                        <label>Trial Duration (Days)</label>
                        <input type="number" name="trial_days" value="{{ $settings['trial_days'] ?? '14' }}" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Public Self-Registration (`/saas/register`)</label>
                    <select name="allow_registration" required>
                        <option value="1" {{ ($settings['allow_registration'] ?? '1') == '1' ? 'selected' : '' }}>Enabled (Public Signups Allowed)</option>
                        <option value="0" {{ ($settings['allow_registration'] ?? '1') == '0' ? 'selected' : '' }}>Disabled (Signups Paused)</option>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Basic Plan Monthly Price ({{ $settings['currency_symbol'] ?? '₦' }})</label>
                        <input type="number" name="monthly_price_basic" value="{{ $settings['monthly_price_basic'] ?? 15000 }}" required>
                    </div>
                    <div class="form-group">
                        <label>Pro Plan Monthly Price ({{ $settings['currency_symbol'] ?? '₦' }})</label>
                        <input type="number" name="monthly_price_pro" value="{{ $settings['monthly_price_pro'] ?? 35000 }}" required>
                    </div>
                    <div class="form-group">
                        <label>Enterprise Monthly Price ({{ $settings['currency_symbol'] ?? '₦' }})</label>
                        <input type="number" name="monthly_price_enterprise" value="{{ $settings['monthly_price_enterprise'] ?? 75000 }}" required>
                    </div>
                </div>

                <!-- Bank Details -->
                <div style="border-top: 1px solid var(--border); margin: 1.25rem 0; padding-top: 1.25rem;">
                    <div style="font-size: 0.9rem; font-weight: 800; color: #38bdf8; margin-bottom: 0.75rem;">
                        🏛️ Official Corporate Bank Transfer Details
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" value="{{ $settings['bank_name'] ?? '' }}" placeholder="e.g. Zenith Bank Plc">
                        </div>
                        <div class="form-group">
                            <label>Account Number</label>
                            <input type="text" name="bank_account_number" value="{{ $settings['bank_account_number'] ?? '' }}" placeholder="e.g. 1012345678">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Account Name</label>
                        <input type="text" name="bank_account_name" value="{{ $settings['bank_account_name'] ?? '' }}" placeholder="e.g. Victorious Market SaaS Ltd">
                    </div>
                    <div class="form-group">
                        <label>Payment Notice Instructions</label>
                        <input type="text" name="bank_instructions" value="{{ $settings['bank_instructions'] ?? '' }}" placeholder="e.g. Please pay and enter your reference number.">
                    </div>
                </div>

                <!-- Paystack Settings -->
                <div style="border-top: 1px solid var(--border); margin: 1.25rem 0; padding-top: 1.25rem;">
                    <div style="font-size: 0.9rem; font-weight: 800; color: #818cf8; margin-bottom: 0.75rem;">
                        ⚡ Paystack Payment Gateway Keys
                    </div>
                    <div class="form-group">
                        <label>Paystack Automated Checkout</label>
                        <select name="paystack_enabled">
                            <option value="1" {{ ($settings['paystack_enabled'] ?? '1') == '1' ? 'selected' : '' }}>Enabled (Automated Card & Bank)</option>
                            <option value="0" {{ ($settings['paystack_enabled'] ?? '1') == '0' ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Paystack Public Key</label>
                            <input type="text" name="paystack_public_key" value="{{ $settings['paystack_public_key'] ?? '' }}" placeholder="pk_live_...">
                        </div>
                        <div class="form-group">
                            <label>Paystack Secret Key</label>
                            <div class="password-field-wrapper">
                                <input type="password" id="paystack_secret_key" name="paystack_secret_key" value="" placeholder="{{ !empty($settings['paystack_secret_configured']) ? '•••••••• (Configured — leave blank to keep unchanged)' : 'sk_live_...' }}" autocomplete="new-password">
                                <button type="button" class="password-toggle-btn" onclick="toggleSaasPassword('paystack_secret_key', this)" aria-label="Toggle visibility">👁️</button>
                            </div>
                            @if(!empty($settings['paystack_secret_configured']))
                                <span style="font-size: 0.72rem; color: #34d399; display: block; margin-top: 0.25rem;">✓ Secret Key is securely stored server-side.</span>
                            @endif
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success" style="width: 100%; padding: 0.75rem; margin-top: 0.5rem;">
                    💾 Save Platform & Payment Settings
                </button>
            </form>
        </div>
        @endif

    </div>
    @endif

    @if($canBackup)
    <!-- 5. PLATFORM DATABASE SNAPSHOTS -->
    <div class="card-section">
        <div class="section-header">
            <div>
                <div class="section-title">💾 Platform Database Backups & Snapshots</div>
                <p style="color: #94a3b8; font-size: 0.8rem; margin-top: 0.2rem;">
                    Generate and download full platform infrastructure and configuration snapshots for disaster recovery.
                </p>
            </div>
            <form method="POST" action="/api/backups" onsubmit="return confirm('Generate an instant database safety backup now?');">
                @csrf
                <button type="submit" class="btn btn-primary">📦 Create Instant Backup</button>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Created Date</th>
                        <th>File Size</th>
                        <th>Origin / Creator</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($backups as $b)
                    <tr>
                        <td><strong>{{ $b->filename }}</strong></td>
                        <td>{{ date('d M Y, h:i A', strtotime($b->created_at)) }}</td>
                        <td>{{ number_format(($b->size ?? 1024) / 1024, 1) }} KB</td>
                        <td><span style="color: #38bdf8;">{{ $b->created_by }}</span></td>
                        <td>
                            <a href="/api/backups/{{ $b->id }}/download" class="btn btn-secondary btn-sm">⬇️ Download</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align: center; color: #94a3b8; padding: 2rem;">
                            No platform database snapshots found. Click "Create Instant Backup" above to generate one.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <!-- MODAL 1: EDIT TENANT LIMITS & PLAN -->
    <div id="modalEditLimits" class="modal-backdrop" onclick="closeModalOnBackdrop(event, 'modalEditLimits')">
        <div class="modal-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #f8fafc;">⚙️ Edit Limits & Plan Quotas</h3>
                    <p id="editLimitsTenantName" style="font-size: 0.8rem; color: #38bdf8; font-weight: 700; margin-top: 0.2rem;">Loading...</p>
                </div>
                <button type="button" onclick="closeModal('modalEditLimits')" style="background: none; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer;">✕</button>
            </div>

            <form id="formEditLimits" method="POST" action="">
                @csrf
                <div class="form-group">
                    <label>Subscription Plan Tier</label>
                    <select name="plan" id="editLimitsPlan" required>
                        <option value="basic">Starter Plan (Basic)</option>
                        <option value="pro">Professional Growth (Pro)</option>
                        <option value="enterprise">Enterprise Multi-Branch (Enterprise)</option>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Max Retail Branches</label>
                        <input type="number" name="max_branches" id="editLimitsMaxBranches" min="1" max="999" required>
                    </div>
                    <div class="form-group">
                        <label>Max Staff / Cashiers</label>
                        <input type="number" name="max_users" id="editLimitsMaxUsers" min="1" max="999" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Account Status</label>
                        <select name="status" id="editLimitsStatus" required>
                            <option value="active">Active & Verified</option>
                            <option value="trial">Trial</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Exact Expiration Date</label>
                        <input type="date" name="expiry_date" id="editLimitsExpiryDate">
                    </div>
                </div>

                <div class="form-group">
                    <label>Quick Extension (+Days)</label>
                    <select name="extend_days">
                        <option value="">-- No Extension --</option>
                        <option value="14">+14 Days Extension</option>
                        <option value="30">+30 Days (1 Month)</option>
                        <option value="90">+90 Days (Quarterly)</option>
                        <option value="365">+365 Days (1 Year)</option>
                    </select>
                </div>

                <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalEditLimits')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1.4;">Save Quota Changes 💾</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: RESET TENANT ADMIN PASSWORD -->
    <div id="modalResetPassword" class="modal-backdrop" onclick="closeModalOnBackdrop(event, 'modalResetPassword')">
        <div class="modal-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #f8fafc;">🔑 Reset Business Owner Password</h3>
                    <p id="resetPassTenantName" style="font-size: 0.8rem; color: #38bdf8; font-weight: 700; margin-top: 0.2rem;">Loading...</p>
                </div>
                <button type="button" onclick="closeModal('modalResetPassword')" style="background: none; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer;">✕</button>
            </div>

            <form id="formResetPassword" method="POST" action="">
                @csrf
                <div class="form-group">
                    <label>Owner Email Account</label>
                    <input type="text" id="resetPassOwnerEmail" readonly style="background: #090e17; color: #94a3b8; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                        <label style="margin: 0;">New Custom Password</label>
                        <button type="button" onclick="generateRandomPassword()" style="background: none; border: none; color: #38bdf8; font-size: 0.72rem; font-weight: 700; cursor: pointer;">🎲 Auto-Generate</button>
                    </div>
                    <div class="password-field-wrapper">
                        <input type="text" name="custom_password" id="resetPassCustomPassword" placeholder="Leave blank to auto-generate...">
                    </div>
                    <span style="font-size: 0.72rem; color: var(--text-muted); display: block; margin-top: 0.25rem;">
                        If blank, the system automatically assigns a secure random password and displays it for you to copy.
                    </span>
                </div>

                <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalResetPassword')">Cancel</button>
                    <button type="submit" class="btn btn-warning" style="flex: 1.4;">Reset Password Now 🔑</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal helpers
        function openEditLimitsModal(tenantId, tenantName, plan, maxBranches, maxUsers, status, expiryDate) {
            document.getElementById('editLimitsTenantName').textContent = tenantName + ' (' + tenantId + ')';
            document.getElementById('editLimitsPlan').value = plan;
            document.getElementById('editLimitsMaxBranches').value = maxBranches;
            document.getElementById('editLimitsMaxUsers').value = maxUsers;
            document.getElementById('editLimitsStatus').value = status;
            document.getElementById('editLimitsExpiryDate').value = expiryDate;
            document.getElementById('formEditLimits').action = '/saas/admin/limits/' + tenantId;
            document.getElementById('modalEditLimits').classList.add('active');
        }

        function openResetPasswordModal(tenantId, tenantName, ownerEmail) {
            document.getElementById('resetPassTenantName').textContent = tenantName;
            document.getElementById('resetPassOwnerEmail').value = ownerEmail;
            document.getElementById('resetPassCustomPassword').value = '';
            document.getElementById('formResetPassword').action = '/saas/admin/tenant/' + tenantId + '/reset-password';
            document.getElementById('modalResetPassword').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function closeModalOnBackdrop(e, modalId) {
            if (e.target.id === modalId) {
                closeModal(modalId);
            }
        }

        function generateRandomPassword() {
            const rand = Math.floor(100000 + Math.random() * 900000);
            document.getElementById('resetPassCustomPassword').value = 'VMPos#' + rand;
        }

        // Live Tenant Search & Filtering
        let currentStatusFilter = 'ALL';

        function setTenantStatusFilter(status, btn) {
            currentStatusFilter = status;
            document.querySelectorAll('.filter-pill').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
            filterTenantsTable();
        }

        function filterTenantsTable() {
            const query = (document.getElementById('tenantSearchInput').value || '').toLowerCase().trim();
            const rows = document.querySelectorAll('.tenant-row');

            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                const rowStatus = row.getAttribute('data-status') || '';

                const matchesQuery = query === '' || searchData.includes(query);
                const matchesStatus = currentStatusFilter === 'ALL' || rowStatus === currentStatusFilter;

                if (matchesQuery && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function toggleSaasPassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁️';
            }
        }
    </script>
</body>
</html>
