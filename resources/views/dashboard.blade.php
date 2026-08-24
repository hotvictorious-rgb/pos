@extends('layouts.app')

@section('title', 'Executive Dashboard')

@push('styles')
<style>
    .welcome-banner {
        background: linear-gradient(135deg, rgba(37,99,235,0.2) 0%, rgba(139,92,246,0.2) 100%);
        border: 1px solid rgba(59,130,246,0.3);
        border-radius: 20px;
        padding: 1.75rem 2rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    .welcome-text h2 {
        font-size: 1.75rem;
        font-weight: 800;
        color: #f8fafc;
        margin-bottom: 0.35rem;
    }

    .welcome-text p {
        color: #94a3b8;
        font-size: 0.95rem;
    }

    /* Date Filter Toolbar */
    .filter-toolbar {
        background: var(--card-bg, #1e293b);
        border: 1px solid var(--border, #334155);
        border-radius: 18px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .filter-presets {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .filter-btn {
        padding: 0.45rem 1rem;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 700;
        text-decoration: none;
        color: #94a3b8;
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--border, #334155);
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .filter-btn:hover {
        color: #f8fafc;
        background: rgba(255,255,255,0.1);
        border-color: #64748b;
    }

    .filter-btn.active {
        color: #ffffff;
        background: #2563eb;
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(37,99,235,0.35);
    }

    .custom-date-form {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px dashed rgba(255,255,255,0.1);
    }

    .date-input-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .date-input {
        background: rgba(15, 23, 42, 0.7);
        border: 1px solid var(--border, #334155);
        border-radius: 8px;
        color: #f8fafc;
        padding: 0.4rem 0.75rem;
        font-size: 0.85rem;
    }

    .date-input:focus {
        outline: none;
        border-color: #3b82f6;
    }

    /* Section Headings */
    .section-title {
        font-size: 1rem;
        font-weight: 800;
        color: #cbd5e1;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Metric Grid & Cards */
    .stats-grid-4 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 1.25rem;
        margin-bottom: 1.75rem;
    }

    .stat-card {
        background: var(--card-bg, #1e293b);
        border: 1px solid var(--border, #334155);
        border-radius: 18px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.25);
    }

    .stat-val {
        font-size: 1.55rem;
        font-weight: 800;
        color: #f8fafc;
        margin-top: 0.35rem;
        line-height: 1.2;
    }

    .stat-label {
        font-size: 0.78rem;
        color: #94a3b8;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .stat-sub {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 0.35rem;
        display: block;
    }

    .stat-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        flex-shrink: 0;
    }

    /* Big Action Tiles (Child-Simple) */
    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2.5rem;
    }

    .action-tile {
        background: var(--card-bg, #1e293b);
        border: 2px solid var(--border, #334155);
        border-radius: 20px;
        padding: 1.75rem 1.25rem;
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .action-tile:hover {
        transform: translateY(-5px);
        box-shadow: 0 16px 30px rgba(0,0,0,0.4);
    }

    .action-tile.tile-pos {
        border-color: rgba(22, 163, 74, 0.5);
        background: linear-gradient(180deg, rgba(22, 163, 74, 0.12) 0%, var(--card-bg, #1e293b) 100%);
    }
    .action-tile.tile-pos:hover { border-color: #22c55e; }

    .action-tile.tile-stock {
        border-color: rgba(37, 99, 235, 0.5);
        background: linear-gradient(180deg, rgba(37, 99, 235, 0.12) 0%, var(--card-bg, #1e293b) 100%);
    }
    .action-tile.tile-stock:hover { border-color: #3b82f6; }

    .action-tile.tile-unsupplied {
        border-color: rgba(217, 119, 6, 0.5);
        background: linear-gradient(180deg, rgba(217, 119, 6, 0.12) 0%, var(--card-bg, #1e293b) 100%);
    }
    .action-tile.tile-unsupplied:hover { border-color: #f59e0b; }

    .action-tile.tile-debts {
        border-color: rgba(139, 92, 246, 0.5);
        background: linear-gradient(180deg, rgba(139, 92, 246, 0.12) 0%, var(--card-bg, #1e293b) 100%);
    }
    .action-tile.tile-debts:hover { border-color: #a855f7; }

    .action-tile.tile-auditor {
        border-color: rgba(220, 38, 38, 0.5);
        background: linear-gradient(180deg, rgba(220, 38, 38, 0.12) 0%, var(--card-bg, #1e293b) 100%);
    }
    .action-tile.tile-auditor:hover { border-color: #ef4444; }

    .tile-icon {
        width: 68px;
        height: 68px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    }

    .tile-title {
        font-size: 1.2rem;
        font-weight: 800;
        color: #f8fafc;
        margin-bottom: 0.35rem;
    }

    .tile-desc {
        font-size: 0.825rem;
        color: #94a3b8;
        line-height: 1.35;
    }

    .tile-badge {
        margin-top: 0.85rem;
        padding: 0.3rem 0.75rem;
        border-radius: 99px;
        font-size: 0.75rem;
        font-weight: 800;
    }
</style>
@endpush

@section('content')

    <!-- Welcome Header & Quick Action -->
    <div class="welcome-banner">
        <div class="welcome-text">
            <h2>Hysam Ventures Executive Hub 📦</h2>
            <p>Real-time multi-location inventory, anti-theft tracking, and financial performance.</p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <span class="badge" style="background: rgba(37,99,235,0.25); color: #93c5fd; border: 1px solid #3b82f6; padding: 0.5rem 1rem; border-radius: 12px; font-weight: 700; font-size: 0.9rem;">
                📅 {{ $rangeLabel }}
            </span>
            <a href="{{ route('pos.index') }}" class="btn btn-success" style="box-shadow: 0 6px 20px rgba(22,163,74,0.4); font-weight: 800; padding: 0.65rem 1.25rem;">
                <span style="font-size:1.2rem;">💰</span> Start Selling Now
            </a>
        </div>
    </div>

    <!-- Date Filter Toolbar -->
    <div class="filter-toolbar">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div style="font-size: 0.85rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.4rem;">
                <span>⚡ Filter Statistics Period:</span>
            </div>
            @if($datePreset !== 'TODAY')
                <a href="{{ route('dashboard', ['date_preset' => 'TODAY']) }}" class="btn btn-sm btn-outline-secondary" style="font-size: 0.78rem; padding: 0.25rem 0.65rem;">
                    ↺ Reset to Today
                </a>
            @endif
        </div>

        <!-- Preset Pills -->
        <div class="filter-presets">
            <a href="{{ route('dashboard', ['date_preset' => 'TODAY']) }}" class="filter-btn {{ $datePreset === 'TODAY' ? 'active' : '' }}">
                📅 Today
            </a>
            <a href="{{ route('dashboard', ['date_preset' => 'YESTERDAY']) }}" class="filter-btn {{ $datePreset === 'YESTERDAY' ? 'active' : '' }}">
                ⏪ Yesterday
            </a>
            <a href="{{ route('dashboard', ['date_preset' => 'THIS_WEEK']) }}" class="filter-btn {{ $datePreset === 'THIS_WEEK' ? 'active' : '' }}">
                📊 This Week
            </a>
            <a href="{{ route('dashboard', ['date_preset' => 'THIS_MONTH']) }}" class="filter-btn {{ $datePreset === 'THIS_MONTH' ? 'active' : '' }}">
                🗓️ This Month
            </a>
            <a href="{{ route('dashboard', ['date_preset' => 'THIS_YEAR']) }}" class="filter-btn {{ $datePreset === 'THIS_YEAR' ? 'active' : '' }}">
                📈 This Year
            </a>
            <a href="{{ route('dashboard', ['date_preset' => 'ALL']) }}" class="filter-btn {{ $datePreset === 'ALL' ? 'active' : '' }}">
                🌐 All-Time
            </a>
        </div>

        <!-- Custom Date Range Form -->
        <form method="GET" action="{{ route('dashboard') }}" class="custom-date-form">
            <input type="hidden" name="date_preset" value="CUSTOM">
            <span style="font-size: 0.85rem; color: #94a3b8; font-weight: 600;">Custom Range:</span>
            <div class="date-input-group">
                <label style="font-size: 0.75rem; color: #64748b;">From:</label>
                <input type="date" name="from_date" value="{{ $fromDate ?? request('from_date') }}" class="date-input" required>
            </div>
            <div class="date-input-group">
                <label style="font-size: 0.75rem; color: #64748b;">To:</label>
                <input type="date" name="to_date" value="{{ $toDate ?? request('to_date') }}" class="date-input" required>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="font-weight: 700; padding: 0.4rem 0.9rem; border-radius: 8px;">
                Apply Filter
            </button>
        </form>
    </div>

    <!-- 1. SALES & CASH REVENUE -->
    <div class="section-title">
        <span>💰</span> Sales & Cash Inflow in Filtered Period
    </div>
    <div class="stats-grid-4">
        <!-- Gross Sales -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Gross Sales</div>
                <div class="stat-val" style="color: #4ade80;">₦{{ number_format($totalSalesAmount, 0) }}</div>
                <span class="stat-sub">{{ $salesCount }} invoice{{ $salesCount == 1 ? '' : 's' }} recorded</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(74, 222, 128, 0.15); color: #4ade80;">
                💵
            </div>
        </div>

        <!-- Physical Cash in Drawer -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Cash Collected</div>
                <div class="stat-val" style="color: #38bdf8;">₦{{ number_format($totalCashAmount, 0) }}</div>
                <span class="stat-sub">Physical drawer cash inflow</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
                🪙
            </div>
        </div>

        <!-- POS / Electronic & Bank -->
        <div class="stat-card">
            <div>
                <div class="stat-label">POS & Transfers</div>
                <div class="stat-val" style="color: #a78bfa;">₦{{ number_format($totalPosAmount, 0) }}</div>
                <span class="stat-sub">Card & direct bank inflow</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(167, 139, 250, 0.15); color: #a78bfa;">
                💳
            </div>
        </div>

        <!-- New Debt Issued -->
        <div class="stat-card">
            <div>
                <div class="stat-label">New Credit / Debt</div>
                <div class="stat-val" style="color: {{ $newDebtIncurred > 0 ? '#fbbf24' : '#94a3b8' }};">
                    ₦{{ number_format($newDebtIncurred, 0) }}
                </div>
                <span class="stat-sub">Unpaid balances in period</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(251, 191, 36, 0.15); color: #fbbf24;">
                📝
            </div>
        </div>
    </div>

    <!-- 2. STOCK MOVEMENTS (IN vs OUT) & VALUATION -->
    <div class="section-title">
        <span>📦</span> Stock Logistics & Physical Closing Stock
    </div>
    <div class="stats-grid-4">
        <!-- Stock In Units -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Stock In (Inflow)</div>
                <div class="stat-val" style="color: #34d399;">+{{ number_format($totalStockInUnits) }} <span style="font-size: 0.9rem; font-weight:600; color:#94a3b8;">units</span></div>
                <span class="stat-sub">Suppliers, transfers & returns</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(52, 211, 153, 0.15); color: #34d399;">
                📥
            </div>
        </div>

        <!-- Stock Out Units -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Stock Out (Outflow)</div>
                <div class="stat-val" style="color: #f87171;">-{{ number_format($totalStockOutUnits) }} <span style="font-size: 0.9rem; font-weight:600; color:#94a3b8;">units</span></div>
                <span class="stat-sub">Dispatched sales, transfers, damages</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(248, 113, 113, 0.15); color: #f87171;">
                📤
            </div>
        </div>

        <!-- Total Stock Valuation -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Stock Valuation</div>
                <div class="stat-val" style="color: #60a5fa;">₦{{ number_format($totalStockValuation, 0) }}</div>
                <span class="stat-sub">{{ number_format($totalPhysicalUnits) }} units across {{ $totalWarehouses }} branch{{ $totalWarehouses == 1 ? '' : 'es' }}</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(96, 165, 250, 0.15); color: #60a5fa;">
                🏷️
            </div>
        </div>

        <!-- Low Stock / Out of Stock Alert -->
        <div class="stat-card" style="{{ ($lowStockCount + $outOfStockCount) > 0 ? 'border-color: rgba(234, 179, 8, 0.4);' : '' }}">
            <div>
                <div class="stat-label">Stock Alerts</div>
                <div class="stat-val" style="color: {{ ($lowStockCount + $outOfStockCount) > 0 ? '#facc15' : '#4ade80' }};">
                    {{ $lowStockCount + $outOfStockCount }} SKU{{ ($lowStockCount + $outOfStockCount) == 1 ? '' : 's' }}
                </div>
                <span class="stat-sub">{{ $outOfStockCount }} Out of Stock, {{ $lowStockCount }} Low Stock</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(250, 204, 21, 0.15); color: #facc15;">
                ⚠️
            </div>
        </div>
    </div>

    <!-- 3. FULFILLMENT, DEBT RECOVERY & ANTI-THEFT -->
    <div class="section-title">
        <span>🛡️</span> Fulfillment Backlog, Debts & Anti-Theft Protection
    </div>
    <div class="stats-grid-4">
        <!-- Unsupplied Goods in Shop -->
        <div class="stat-card" style="{{ $unsuppliedCount > 0 ? 'border-color: rgba(217, 119, 6, 0.4);' : '' }}">
            <div>
                <div class="stat-label">Not Supplied (In Shop)</div>
                <div class="stat-val" style="color: #fbbf24;">{{ $unsuppliedCount }} Orders</div>
                <span class="stat-sub" style="color: #fcd34d;">₦{{ number_format($unsuppliedValue, 0) }} liability on ground</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(251, 191, 36, 0.15); color: #fbbf24;">
                ⏳
            </div>
        </div>

        <!-- Debt Collected in Period -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Debt Recovered</div>
                <div class="stat-val" style="color: #c084fc;">₦{{ number_format($debtRecoveredInPeriod, 0) }}</div>
                <span class="stat-sub">{{ $debtRecoveryCount }} payment{{ $debtRecoveryCount == 1 ? '' : 's' }} (Total debt: ₦{{ number_format($totalOutstandingDebt, 0) }})</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(192, 132, 252, 0.15); color: #c084fc;">
                🤝
            </div>
        </div>

        <!-- Returns & Refunds -->
        <div class="stat-card">
            <div>
                <div class="stat-label">Returns & Refunds</div>
                <div class="stat-val" style="color: {{ $returnsCount > 0 ? '#f87171' : '#94a3b8' }};">
                    ₦{{ number_format($totalRefundAmount, 0) }}
                </div>
                <span class="stat-sub">{{ $returnsCount }} order{{ $returnsCount == 1 ? '' : 's' }} ({{ $returnedUnits }} units returned)</span>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(248, 113, 113, 0.15); color: #f87171;">
                🔄
            </div>
        </div>

        <!-- Transfer Discrepancies & In-Transit -->
        <div class="stat-card" style="{{ $discrepancyCount > 0 ? 'border-color: #ef4444; background: rgba(220,38,38,0.08);' : '' }}">
            <div>
                <div class="stat-label">Theft & Discrepancies</div>
                <div class="stat-val" style="color: {{ $discrepancyCount > 0 ? '#f87171' : '#4ade80' }};">
                    {{ $discrepancyCount }} Alert{{ $discrepancyCount == 1 ? '' : 's' }}
                </div>
                <span class="stat-sub">{{ $inTransitCount }} in-transit, {{ $damagedUnits }} damages recorded</span>
            </div>
            <div class="stat-icon-wrapper" style="background: {{ $discrepancyCount > 0 ? 'rgba(239, 68, 68, 0.15)' : 'rgba(74, 222, 128, 0.15)' }}; color: {{ $discrepancyCount > 0 ? '#f87171' : '#4ade80' }};">
                {{ $discrepancyCount > 0 ? '🚨' : '🛡️' }}
            </div>
        </div>
    </div>

    <!-- 4. BIG ACTION TILES (CHILD-SIMPLE NAVIGATION) -->
    <h3 style="font-size: 1.05rem; font-weight: 800; color: #cbd5e1; margin-top: 1rem; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">
        ⚡ Touch an Action Below:
    </h3>

    <div class="action-grid">
        <!-- 1. POS Sale -->
        <a href="{{ route('pos.index') }}" class="action-tile tile-pos">
            <div class="tile-icon" style="background: linear-gradient(135deg, #16a34a, #22c55e);">
                💰
            </div>
            <div class="tile-title">Sell Goods</div>
            <div class="tile-desc">Scan or tap products, collect cash, POS, or record part-payment / debt.</div>
            <div class="tile-badge badge-success">Point of Sale</div>
        </a>

        <!-- 2. Stock Management -->
        <a href="{{ route('stock.index') }}" class="action-tile tile-stock">
            <div class="tile-icon" style="background: linear-gradient(135deg, #2563eb, #3b82f6);">
                📦
            </div>
            <div class="tile-title">Stock In / Transfer</div>
            <div class="tile-desc">Receive new goods from suppliers, or send goods to another shop branch.</div>
            <div class="tile-badge badge-info">{{ $totalProducts }} Products in System</div>
        </a>

        <!-- 3. Not Supplied Goods Waiting in Shop -->
        <a href="{{ route('stock.unsupplied') }}" class="action-tile tile-unsupplied">
            <div class="tile-icon" style="background: linear-gradient(135deg, #d97706, #f59e0b);">
                ⏳
            </div>
            <div class="tile-title">Not Supplied Orders</div>
            <div class="tile-desc">Goods paid or part-paid but not yet collected. Mark as Supplied upon customer pickup.</div>
            <div class="tile-badge badge-warning">{{ $unsuppliedCount }} Not Supplied</div>
        </a>

        <!-- 4. Debt & Part-Payments -->
        <a href="{{ route('debts.index') }}" class="action-tile tile-debts">
            <div class="tile-icon" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
                💳
            </div>
            <div class="tile-title">Customer Debts</div>
            <div class="tile-desc">View customer debt balances, collect installment payments, and print receipts.</div>
            <div class="tile-badge" style="background: rgba(139,92,246,0.2); color: #c084fc;">{{ $activeDebtorsCount }} Active Debtors</div>
        </a>

        <!-- 5. Auditor Anti-Theft Hub -->
        <a href="{{ route('auditor.index') }}" class="action-tile tile-auditor">
            <div class="tile-icon" style="background: linear-gradient(135deg, #b91c1c, #dc2626);">
                🚨
            </div>
            <div class="tile-title">Auditor Control</div>
            <div class="tile-desc">Check physical vs system stock variances, transfer losses, and end-of-day drawer cash.</div>
            <div class="tile-badge badge-danger">Anti-Theft Protection</div>
        </a>
    </div>

@endsection
