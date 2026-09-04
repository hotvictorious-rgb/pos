<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Master Control Suite — Hysam Super Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #0b1329; color: #f8fafc; padding: 28px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .header h1 { font-size: 24px; font-weight: 800; background: linear-gradient(135deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .header p { color: #94a3b8; font-size: 14px; margin-top: 4px; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #1e293b; border: 1px solid #334155; padding: 20px; border-radius: 14px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .stat-title { font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { font-size: 26px; font-weight: 800; color: #38bdf8; margin-top: 6px; }

        /* Banner */
        .banner-impersonating { background: #451a03; border: 1px solid #d97706; color: #fef08a; padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }

        /* Tabs & Section Cards */
        .card-section { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .section-title { font-size: 18px; font-weight: 700; color: #f8fafc; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; background: #0f172a; border-radius: 12px; overflow: hidden; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #1e293b; font-size: 13px; }
        th { background: #1e293b; color: #94a3b8; font-weight: 700; font-size: 11px; text-transform: uppercase; }
        tr:hover { background: #1e293b55; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block; text-transform: uppercase; }
        .badge-active { background: #065f46; color: #34d399; }
        .badge-trial { background: #1e3a8a; color: #60a5fa; }
        .badge-suspended { background: #7f1d1d; color: #fca5a5; }

        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 13px; text-decoration: none; border: none; cursor: pointer; transition: opacity 0.2s; }
        .btn-primary { background: linear-gradient(135deg, #0284c7, #6366f1); color: white; }
        .btn-success { background: #059669; color: white; }
        .btn-warning { background: #d97706; color: white; }
        .btn-danger  { background: #dc2626; color: white; }
        .btn-secondary { background: #334155; color: white; }
        .btn-sm { padding: 4px 8px; font-size: 11px; border-radius: 6px; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 6px; text-transform: uppercase; }
        input, select { width: 100%; padding: 10px 12px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #f8fafc; font-size: 14px; outline: none; }
        input:focus, select:focus { border-color: #38bdf8; }

        .alert-success { background: #065f46; color: #34d399; padding: 14px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
        .alert-error { background: #7f1d1d; color: #fca5a5; padding: 14px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
    </style>
</head>
<body>



    <div class="header">
        <div>
            <h1>SaaS Master Platform Control Suite</h1>
            <p>Global multi-tenant platform oversight, revenue metrics, and configuration engine</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="/" class="btn btn-secondary">🏠 POS Dashboard</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    <!-- Financial & Usage Analytics Bar -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-title">Estimated Monthly Revenue (MRR)</div>
            <div class="stat-value" style="color: #34d399;">{{ $settings['currency_symbol'] }}{{ number_format($mrr, 2) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Total Business Tenants</div>
            <div class="stat-value">{{ $totalTenants }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Active / Trial / Suspended</div>
            <div class="stat-value" style="font-size: 18px; color: #cbd5e1; margin-top: 10px;">
                <span style="color:#34d399;">{{ $activeTenants }} Active</span> | 
                <span style="color:#60a5fa;">{{ $trialTenants }} Trial</span> | 
                <span style="color:#fca5a5;">{{ $suspendedTenants }} Suspended</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Platform Total Branches</div>
            <div class="stat-value" style="color: #c084fc;">{{ $totalBranchesPlatform }} Branches</div>
        </div>
        <div class="stat-card">
            <div class="stat-title">Total Products & Sales</div>
            <div class="stat-value" style="font-size: 18px; color: #cbd5e1; margin-top: 10px;">
                {{ number_format($totalProductsPlatform) }} Products | {{ number_format($totalSalesPlatform) }} Sales
            </div>
        </div>
    </div>

    <!-- Tenants Directory Section -->
    <div class="card-section">
        <div class="section-title">🏢 Business Tenants Directory</div>

        <table>
            <thead>
                <tr>
                    <th>Tenant ID & Company</th>
                    <th>Owner Details</th>
                    <th>Plan Tier</th>
                    <th>Status</th>
                    <th>Branch / User Limits</th>
                    <th>Trial Expiration</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tenants as $t)
                    <tr>
                        <td>
                            <strong>{{ $t->name }}</strong><br>
                            <code style="color: #94a3b8; font-size: 11px;">{{ $t->id }}</code>
                        </td>
                        <td>
                            {{ $t->owner_email }}<br>
                            <span style="color: #94a3b8; font-size: 11px;">{{ $t->owner_phone }}</span>
                        </td>
                        <td>
                            <span class="badge" style="background: #334155; color: #f8fafc;">
                                {{ strtoupper($t->plan) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-{{ $t->status }}">
                                {{ $t->status }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ $t->warehouses_count }}</strong> / {{ $t->max_branches }} Branches<br>
                            <span style="color: #94a3b8; font-size: 11px;">{{ $t->users_count }} / {{ $t->max_users }} Users</span>
                        </td>
                        <td>
                            @if($t->trial_ends_at)
                                {{ $t->trial_ends_at->format('M d, Y') }}<br>
                                <span style="color: #94a3b8; font-size: 11px;">({{ $t->trial_ends_at->diffForHumans() }})</span>
                            @else
                                <span style="color: #94a3b8;">N/A</span>
                            @endif
                        </td>
                        <td>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">


                                <!-- Activate / Suspend Toggle -->
                                <form action="{{ route('saas.admin.toggle', $t->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    @if($t->status === 'active')
                                        <input type="hidden" name="status" value="suspended">
                                        <button type="submit" class="btn btn-danger btn-sm">Suspend</button>
                                    @else
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" class="btn btn-success btn-sm">Activate</button>
                                    @endif
                                </form>

                                <!-- Extend Trial Button (+14 Days) -->
                                <form action="{{ route('saas.admin.limits', $t->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <input type="hidden" name="extend_trial" value="14">
                                    <button type="submit" class="btn btn-warning btn-sm">+14d Trial</button>
                                </form>

                                <!-- Delete Button -->
                                @if($t->id !== 'default-tenant')
                                    <form action="{{ route('saas.admin.delete', $t->id) }}" method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this tenant and all associated records?');">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm" style="background: #450a0a;">🗑️</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Quick Add Tenant & Global Configuration Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">

        <!-- Section: Create New Business Tenant -->
        <div class="card-section">
            <div class="section-title">➕ Create New Business Tenant</div>
            <form action="{{ route('saas.admin.tenant.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Business / Company Name</label>
                    <input type="text" name="business_name" required placeholder="e.g. Ebuka Provisions & Supermarket">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" required placeholder="e.g. Chief Ebuka">
                    </div>
                    <div class="form-group">
                        <label>Owner Phone</label>
                        <input type="text" name="owner_phone" required placeholder="e.g. 08099998888">
                    </div>
                </div>

                <div class="form-group">
                    <label>Owner Email Address</label>
                    <input type="email" name="owner_email" required placeholder="e.g. ebuka@provisions.com">
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
                            <option value="active" selected>Active</option>
                            <option value="trial">Trial</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Create Business Account 🚀</button>
            </form>
        </div>

        <!-- Section: Global SaaS Configuration Settings -->
        <div class="card-section">
            <div class="section-title">⚙️ SaaS Global Platform Settings</div>
            <form action="{{ route('saas.admin.settings') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Platform Name</label>
                    <input type="text" name="platform_name" value="{{ $settings['platform_name'] }}" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Support Email</label>
                        <input type="email" name="support_email" value="{{ $settings['support_email'] }}" required>
                    </div>
                    <div class="form-group">
                        <label>Support Phone</label>
                        <input type="text" name="support_phone" value="{{ $settings['support_phone'] }}" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Currency Symbol</label>
                        <input type="text" name="currency_symbol" value="{{ $settings['currency_symbol'] }}" required>
                    </div>
                    <div class="form-group">
                        <label>Free Trial Duration (Days)</label>
                        <input type="number" name="trial_days" value="{{ $settings['trial_days'] }}" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Public Self-Registration (`/saas/register`)</label>
                    <select name="allow_registration" required>
                        <option value="1" {{ $settings['allow_registration'] == '1' ? 'selected' : '' }}>Enabled (Public Signups Allowed)</option>
                        <option value="0" {{ $settings['allow_registration'] == '0' ? 'selected' : '' }}>Disabled (Registration Closed)</option>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Basic Plan Monthly Price ({{ $settings['currency_symbol'] }})</label>
                        <input type="number" name="monthly_price_basic" value="{{ $settings['monthly_price_basic'] }}" required>
                    </div>
                    <div class="form-group">
                        <label>Pro Plan Monthly Price ({{ $settings['currency_symbol'] }})</label>
                        <input type="number" name="monthly_price_pro" value="{{ $settings['monthly_price_pro'] }}" required>
                    </div>
                    <div class="form-group">
                        <label>Enterprise Monthly Price ({{ $settings['currency_symbol'] }})</label>
                        <input type="number" name="monthly_price_enterprise" value="{{ $settings['monthly_price_enterprise'] }}" required>
                    </div>
                </div>

                <div style="border-top: 1px solid #334155; margin: 20px 0; padding-top: 20px;">
                    <div class="section-title" style="font-size: 16px; color: #38bdf8;">💳 Bank Account Details (Direct Bank Transfer)</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" value="{{ $settings['bank_name'] }}" placeholder="e.g. Zenith Bank / GTBank">
                        </div>
                        <div class="form-group">
                            <label>Account Number</label>
                            <input type="text" name="bank_account_number" value="{{ $settings['bank_account_number'] }}" placeholder="e.g. 1012345678">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Account Name</label>
                        <input type="text" name="bank_account_name" value="{{ $settings['bank_account_name'] }}" placeholder="e.g. Hysam Ventures SaaS Ltd">
                    </div>
                    <div class="form-group">
                        <label>Transfer Instructions Notice</label>
                        <input type="text" name="bank_instructions" value="{{ $settings['bank_instructions'] }}" placeholder="e.g. Send payment proof to support@hysamventures.com">
                    </div>
                </div>

                <div style="border-top: 1px solid #334155; margin: 20px 0; padding-top: 20px;">
                    <div class="section-title" style="font-size: 16px; color: #818cf8;">⚡ Paystack Payment Gateway Integration</div>
                    <div class="form-group">
                        <label>Paystack Automated Checkout</label>
                        <select name="paystack_enabled">
                            <option value="1" {{ $settings['paystack_enabled'] == '1' ? 'selected' : '' }}>Enabled (Automated Card / USSD Payment)</option>
                            <option value="0" {{ $settings['paystack_enabled'] == '0' ? 'selected' : '' }}>Disabled</option>
                        </select>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Paystack Public Key (`pk_live_...` or `pk_test_...`)</label>
                            <input type="text" name="paystack_public_key" value="{{ $settings['paystack_public_key'] }}" placeholder="pk_test_...">
                        </div>
                        <div class="form-group">
                            <label>Paystack Secret Key (`sk_live_...` or `sk_test_...`)</label>
                            <input type="password" name="paystack_secret_key" value="{{ $settings['paystack_secret_key'] }}" placeholder="sk_test_...">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 10px;">Save Global SaaS & Payment Settings 💾</button>
            </form>
        </div>

        <!-- Section: Super-Admin Exclusive Platform Database Backups -->
        <div class="card-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div class="section-title" style="margin-bottom: 4px;">💾 Platform Database Backups & Snapshots</div>
                    <p style="color: #94a3b8; font-size: 13px;">Manage and download platform infrastructure and configuration backup snapshots. Restricted exclusively to Platform Super-Administrators (contains zero tenant business records).</p>
                </div>
                <form method="POST" action="/api/backups" onsubmit="return confirm('Generate an instant database safety backup now?');">
                    @csrf
                    <button type="submit" class="btn btn-primary">📦 Create Instant Backup</button>
                </form>
            </div>

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
                        <td colspan="5" style="text-align: center; color: #94a3b8; padding: 24px;">No database snapshots found. Click "Create Instant Backup" above to generate one.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>
