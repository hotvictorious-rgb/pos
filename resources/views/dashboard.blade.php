@extends('layouts.app')

@section('title', 'Executive Dashboard')

@push('styles')
<style>
    /* Executive Header */
    .exec-header {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }

    .exec-title h2 {
        font-size: 1.6rem;
        font-weight: 800;
        color: #f8fafc;
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .exec-title p {
        color: #94a3b8;
        font-size: 0.88rem;
    }

    .exec-badges {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .exec-badge {
        padding: 0.45rem 0.9rem;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .badge-branch {
        background: rgba(139, 92, 246, 0.18);
        color: #c084fc;
        border: 1px solid rgba(168, 85, 247, 0.3);
    }

    .badge-date {
        background: rgba(37, 99, 235, 0.18);
        color: #93c5fd;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }

    /* Filter Command Hub */
    .filter-hub {
        background: var(--card-bg, #1e293b);
        border: 1px solid var(--border, #334155);
        border-radius: 18px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.75rem;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }

    .filter-row-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .filter-control-group {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .filter-label {
        font-size: 0.8rem;
        font-weight: 800;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
    }

    .filter-select {
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid var(--border, #334155);
        color: #f8fafc;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        font-size: 0.88rem;
        font-weight: 700;
        cursor: pointer;
        min-width: 220px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .filter-select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }

    .custom-range-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.65rem;
        padding-top: 0.75rem;
        border-top: 1px dashed rgba(255,255,255,0.08);
    }

    .custom-range-input {
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid var(--border, #334155);
        border-radius: 8px;
        color: #f8fafc;
        padding: 0.35rem 0.65rem;
        font-size: 0.82rem;
    }

    /* Hero Metrics Grid */
    .hero-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.25rem;
        margin-bottom: 1.75rem;
    }

    .hero-card {
        background: linear-gradient(135deg, rgba(30, 41, 59, 0.8) 0%, rgba(15, 23, 42, 0.9) 100%);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        padding: 1.4rem 1.5rem;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .hero-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
    }

    .hero-card.hero-sales::before { background: linear-gradient(90deg, #10b981, #34d399); }
    .hero-card.hero-cash::before { background: linear-gradient(90deg, #0284c7, #38bdf8); }
    .hero-card.hero-stock::before { background: linear-gradient(90deg, #6366f1, #818cf8); }
    .hero-card.hero-unsupplied::before { background: linear-gradient(90deg, #d97706, #fbbf24); }

    .hero-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.35);
    }

    .hero-label {
        font-size: 0.76rem;
        font-weight: 800;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .hero-val {
        font-size: 1.65rem;
        font-weight: 900;
        color: #f8fafc;
        margin-top: 0.35rem;
        line-height: 1.15;
    }

    .hero-sub {
        font-size: 0.78rem;
        color: #64748b;
        margin-top: 0.35rem;
        display: block;
    }

    .hero-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    /* 3-Column Panels Layout */
    .panels-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.25rem;
        margin-bottom: 2rem;
    }

    .panel-card {
        background: var(--card-bg, #1e293b);
        border: 1px solid var(--border, #334155);
        border-radius: 18px;
        padding: 1.35rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255,255,255,0.06);
        padding-bottom: 0.75rem;
    }

    .panel-title {
        font-size: 0.92rem;
        font-weight: 800;
        color: #f8fafc;
        display: flex;
        align-items: center;
        gap: 0.45rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .panel-list {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }

    .panel-item {
        background: rgba(15, 23, 42, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.04);
        border-radius: 12px;
        padding: 0.85rem 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .panel-item-left {
        display: flex;
        align-items: center;
        gap: 0.65rem;
    }

    .panel-item-icon {
        font-size: 1.25rem;
    }

    .panel-item-name {
        font-size: 0.84rem;
        font-weight: 700;
        color: #cbd5e1;
    }

    .panel-item-sub {
        font-size: 0.74rem;
        color: #64748b;
    }

    .panel-item-val {
        font-size: 1rem;
        font-weight: 800;
        text-align: right;
    }

    /* Multi-Branch Comparison Grid */
    .branch-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .branch-card {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid var(--border, #334155);
        border-radius: 14px;
        padding: 1.15rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        transition: transform 0.15s ease;
    }

    .branch-card:hover {
        transform: translateY(-2px);
        border-color: #a855f7;
    }

    .branch-card-title {
        font-size: 0.95rem;
        font-weight: 800;
        color: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
</style>
@endpush

@section('content')

    <!-- Executive Header -->
    <div class="exec-header">
        <div class="exec-title">
            <h2>🏢 {{ $locationLabel }}</h2>
            <p>Real-time inventory valuation, cash collections, logistics, and anti-theft telemetry.</p>
        </div>
        <div class="exec-badges">
            <span class="exec-badge badge-branch">
                🏬 {{ $locationLabel }}
            </span>
            <span class="exec-badge badge-date">
                📅 {{ $rangeLabel }}
            </span>
            <a href="{{ route('pos.index', $warehouseId ? ['warehouse_id' => $warehouseId] : []) }}" class="btn btn-success btn-sm" style="font-weight: 800; padding: 0.45rem 1rem; border-radius: 10px; box-shadow: 0 4px 15px rgba(22,163,74,0.35);">
                💰 Launch POS
            </a>
        </div>
    </div>

    <!-- Filter Command Hub (Clean Dropdown Selectors) -->
    <div class="filter-hub">
        <form method="GET" action="{{ route('dashboard') }}" id="dashFilterForm">
            <div class="filter-row-top">
                <!-- 1. Location / Branch Selector -->
                <div class="filter-control-group">
                    <label class="filter-label">🏢 Location:</label>
                    <select name="warehouse_id" class="filter-select" onchange="document.getElementById('dashFilterForm').submit()">
                        <option value="">All Branches (Consolidated)</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ $warehouseId == $wh->id ? 'selected' : '' }}>
                                {{ $wh->name }} ({{ $wh->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- 2. Date Period Dropdown Selector -->
                <div class="filter-control-group">
                    <label class="filter-label">📅 Period:</label>
                    <select name="date_preset" id="datePresetSelect" class="filter-select" onchange="handleDatePresetChange(this.value)">
                        <option value="TODAY" {{ $datePreset === 'TODAY' ? 'selected' : '' }}>Today ({{ \Carbon\Carbon::today()->format('d M') }})</option>
                        <option value="YESTERDAY" {{ $datePreset === 'YESTERDAY' ? 'selected' : '' }}>Yesterday</option>
                        <option value="THIS_WEEK" {{ $datePreset === 'THIS_WEEK' ? 'selected' : '' }}>This Week</option>
                        <option value="THIS_MONTH" {{ $datePreset === 'THIS_MONTH' ? 'selected' : '' }}>This Month ({{ \Carbon\Carbon::now()->format('F') }})</option>
                        <option value="THIS_YEAR" {{ $datePreset === 'THIS_YEAR' ? 'selected' : '' }}>This Year ({{ \Carbon\Carbon::now()->format('Y') }})</option>
                        <option value="ALL" {{ $datePreset === 'ALL' ? 'selected' : '' }}>All-Time</option>
                        <option value="CUSTOM" {{ $datePreset === 'CUSTOM' ? 'selected' : '' }}>🗓️ Custom Date Range...</option>
                    </select>
                </div>

                <!-- Reset Button -->
                @if($datePreset !== 'TODAY' || $warehouseId)
                    <div style="margin-left: auto;">
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm" style="font-size: 0.78rem; padding: 0.45rem 0.85rem; border-radius: 8px;">
                            ↺ Reset Filters
                        </a>
                    </div>
                @endif
            </div>

            <!-- Custom Date Range Form (Shown when Custom Range is selected) -->
            <div id="customDateRangeRow" class="custom-range-row" style="{{ $datePreset === 'CUSTOM' ? 'display: flex;' : 'display: none;' }}">
                <span style="font-size: 0.8rem; color: #94a3b8; font-weight: 700;">Custom Range:</span>
                <input type="date" name="from_date" id="fromDateInput" value="{{ $fromDate ?? request('from_date') }}" class="custom-range-input" {{ $datePreset === 'CUSTOM' ? 'required' : '' }}>
                <span style="color: #64748b; font-size: 0.8rem;">to</span>
                <input type="date" name="to_date" id="toDateInput" value="{{ $toDate ?? request('to_date') }}" class="custom-range-input" {{ $datePreset === 'CUSTOM' ? 'required' : '' }}>
                <button type="submit" class="btn btn-primary btn-sm" style="font-weight: 700; padding: 0.35rem 0.85rem; border-radius: 8px;">
                    Apply Range
                </button>
            </div>
        </form>
    </div>

    <!-- 4 HERO KEY METRIC CARDS -->
    <div class="hero-grid">
        <!-- 1. Gross Sales -->
        <div class="hero-card hero-sales">
            <div>
                <div class="hero-label">Gross Sales (Period)</div>
                <div class="hero-val" style="color: #34d399;">₦{{ number_format($totalSalesAmount, 0) }}</div>
                <span class="hero-sub">{{ $salesCount }} invoice{{ $salesCount == 1 ? '' : 's' }} recorded</span>
            </div>
            <div class="hero-icon" style="background: rgba(52, 211, 153, 0.15); color: #34d399;">
                💵
            </div>
        </div>

        <!-- 2. Cash & Electronic Collected -->
        <div class="hero-card hero-cash">
            <div>
                <div class="hero-label">Total Inflow Collected</div>
                <div class="hero-val" style="color: #38bdf8;">₦{{ number_format($totalCollections, 0) }}</div>
                <span class="hero-sub">₦{{ number_format($totalCashAmount, 0) }} Cash · ₦{{ number_format($totalPosAmount, 0) }} POS/Bank</span>
            </div>
            <div class="hero-icon" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
                🪙
            </div>
        </div>

        <!-- 3. Physical Inventory Valuation -->
        <div class="hero-card hero-stock">
            <div>
                <div class="hero-label">Physical Stock Valuation</div>
                <div class="hero-val" style="color: #818cf8;">₦{{ number_format($totalStockValuation, 0) }}</div>
                <span class="hero-sub">{{ number_format($totalPhysicalUnits) }} units on shelves</span>
            </div>
            <div class="hero-icon" style="background: rgba(129, 140, 248, 0.15); color: #818cf8;">
                🏷️
            </div>
        </div>

        <!-- 4. Unsupplied Goods Liability -->
        <div class="hero-card hero-unsupplied">
            <div>
                <div class="hero-label">Unsupplied (In Shop)</div>
                <div class="hero-val" style="color: #fbbf24;">{{ $unsuppliedCount }} Orders</div>
                <span class="hero-sub" style="color: #fcd34d;">₦{{ number_format($unsuppliedValue, 0) }} liability awaiting pickup</span>
            </div>
            <div class="hero-icon" style="background: rgba(251, 191, 36, 0.15); color: #fbbf24;">
                ⏳
            </div>
        </div>
    </div>

    <!-- 3-COLUMN OPERATIONAL PANELS -->
    <div class="panels-grid">
        <!-- Panel 1: Payment Flow & Credit -->
        <div class="panel-card">
            <div class="panel-header">
                <span class="panel-title"><span>💳</span> Cash Flow & Debts</span>
                <a href="{{ route('debts.index') }}" class="btn btn-outline-secondary btn-sm" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">
                    Debtors Ledger →
                </a>
            </div>

            <div class="panel-list">
                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">💵</span>
                        <div>
                            <div class="panel-item-name">Drawer Cash Collected</div>
                            <div class="panel-item-sub">Physical cash inflow</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: #38bdf8;">
                        ₦{{ number_format($totalCashAmount, 0) }}
                    </div>
                </div>

                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">💳</span>
                        <div>
                            <div class="panel-item-name">POS & Bank Transfers</div>
                            <div class="panel-item-sub">Card & electronic payments</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: #a78bfa;">
                        ₦{{ number_format($totalPosAmount, 0) }}
                    </div>
                </div>

                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">📝</span>
                        <div>
                            <div class="panel-item-name">New Credit Issued</div>
                            <div class="panel-item-sub">Unpaid sale balances in period</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: {{ $newDebtIncurred > 0 ? '#fbbf24' : '#94a3b8' }};">
                        ₦{{ number_format($newDebtIncurred, 0) }}
                    </div>
                </div>

                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">🤝</span>
                        <div>
                            <div class="panel-item-name">Debt Recoveries Collected</div>
                            <div class="panel-item-sub">{{ $debtRecoveryCount }} payment{{ $debtRecoveryCount == 1 ? '' : 's' }} in period</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: #4ade80;">
                        ₦{{ number_format($debtRecoveredInPeriod, 0) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel 2: Stock Flow & Logistics -->
        <div class="panel-card">
            <div class="panel-header">
                <span class="panel-title"><span>📦</span> Stock Flow & Logistics</span>
                <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary btn-sm" style="font-size: 0.75rem; padding: 0.2rem 0.5rem;">
                    Stock Hub →
                </a>
            </div>

            <div class="panel-list">
                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">📥</span>
                        <div>
                            <div class="panel-item-name">Stock In (Inflow)</div>
                            <div class="panel-item-sub">Supplier deliveries & transfers received</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: #34d399;">
                        +{{ number_format($totalStockInUnits) }} units
                    </div>
                </div>

                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">📤</span>
                        <div>
                            <div class="panel-item-name">Stock Out (Outflow)</div>
                            <div class="panel-item-sub">Dispatched orders, transfers, damages</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: #f87171;">
                        -{{ number_format($totalStockOutUnits) }} units
                    </div>
                </div>

                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">⚠️</span>
                        <div>
                            <div class="panel-item-name">Low Stock / Out of Stock</div>
                            <div class="panel-item-sub">{{ $outOfStockCount }} Out of Stock, {{ $lowStockCount }} Low (≤5)</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: {{ ($lowStockCount + $outOfStockCount) > 0 ? '#facc15' : '#4ade80' }};">
                        {{ $lowStockCount + $outOfStockCount }} SKU{{ ($lowStockCount + $outOfStockCount) == 1 ? '' : 's' }}
                    </div>
                </div>

                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">🚚</span>
                        <div>
                            <div class="panel-item-name">In-Transit Buffer</div>
                            <div class="panel-item-sub">Shipments on vehicles</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: #38bdf8;">
                        {{ $inTransitCount }} Transfer{{ $inTransitCount == 1 ? '' : 's' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel 3: Anti-Theft & Loss Radar -->
        <div class="panel-card">
            <div class="panel-header">
                <span class="panel-title"><span>🛡️</span> Anti-Theft & Loss Radar</span>
                <a href="{{ route('auditor.index') }}" class="btn btn-outline-secondary btn-sm" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; color: #fca5a5;">
                    Auditor Hub →
                </a>
            </div>

            <div class="panel-list">
                <div class="panel-item" style="{{ $discrepancyCount > 0 ? 'background: rgba(239, 68, 68, 0.12); border-color: rgba(239, 68, 68, 0.3);' : '' }}">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">{{ $discrepancyCount > 0 ? '🚨' : '🛡️' }}</span>
                        <div>
                            <div class="panel-item-name">Transfer Discrepancies</div>
                            <div class="panel-item-sub">{{ $discrepancyCount > 0 ? 'Unaccounted shortage in transit' : 'All counts verified' }}</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: {{ $discrepancyCount > 0 ? '#f87171' : '#4ade80' }};">
                        {{ $discrepancyCount }} Alert{{ $discrepancyCount == 1 ? '' : 's' }}
                    </div>
                </div>

                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">💔</span>
                        <div>
                            <div class="panel-item-name">Damaged Goods Written-off</div>
                            <div class="panel-item-sub">Broken, expired or lost items</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: {{ $damagedUnits > 0 ? '#fbbf24' : '#94a3b8' }};">
                        {{ number_format($damagedUnits) }} units
                    </div>
                </div>

                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">🔄</span>
                        <div>
                            <div class="panel-item-name">Returns & Refunds</div>
                            <div class="panel-item-sub">{{ $returnsCount }} return{{ $returnsCount == 1 ? '' : 's' }} ({{ $returnedUnits }} units)</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: {{ $totalRefundAmount > 0 ? '#f87171' : '#94a3b8' }};">
                        ₦{{ number_format($totalRefundAmount, 0) }}
                    </div>
                </div>

                <div class="panel-item">
                    <div class="panel-item-left">
                        <span class="panel-item-icon">👥</span>
                        <div>
                            <div class="panel-item-name">Total Debt Portfolio</div>
                            <div class="panel-item-sub">{{ $activeDebtorsCount }} customer debtor{{ $activeDebtorsCount == 1 ? '' : 's' }}</div>
                        </div>
                    </div>
                    <div class="panel-item-val" style="color: #c084fc;">
                        ₦{{ number_format($totalOutstandingDebt, 0) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MULTI-BRANCH COMPARISON CARDS (When All Branches view is active) -->
    @if(!$warehouseId && count($branchBreakdown) > 1)
        <div style="font-size: 0.92rem; font-weight: 800; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
            <span>🏬</span> Multi-Branch Stock & Valuation Breakdown
        </div>

        <div class="branch-summary-grid">
            @foreach($branchBreakdown as $b)
                <div class="branch-card">
                    <div class="branch-card-title">
                        <span>{{ $b['name'] }}</span>
                        <span class="badge" style="background: rgba(255,255,255,0.08); font-size: 0.72rem;">{{ $b['code'] }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.84rem; color: #94a3b8; margin-top: 0.25rem;">
                        <span>Physical Stock:</span>
                        <strong style="color: #f8fafc;">{{ number_format($b['units']) }} units</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.84rem; color: #94a3b8;">
                        <span>Valuation:</span>
                        <strong style="color: #818cf8;">₦{{ number_format($b['valuation'], 0) }}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.84rem; color: #94a3b8;">
                        <span>Low Stock SKUs:</span>
                        <strong style="color: {{ $b['low_stock_alerts'] > 0 ? '#facc15' : '#4ade80' }};">{{ $b['low_stock_alerts'] }}</strong>
                    </div>
                    <a href="{{ route('dashboard', ['warehouse_id' => $b['id']]) }}" class="btn btn-outline-secondary btn-sm" style="font-size: 0.75rem; margin-top: 0.4rem; padding: 0.3rem 0.6rem; text-align: center;">
                        Filter Dashboard to {{ $b['name'] }} →
                    </a>
                </div>
            @endforeach
        </div>
    @endif

@endsection

@push('scripts')
<script>
function handleDatePresetChange(val) {
    const customRow = document.getElementById('customDateRangeRow');
    const fromInput = document.getElementById('fromDateInput');
    const toInput = document.getElementById('toDateInput');
    if (val === 'CUSTOM') {
        customRow.style.display = 'flex';
        fromInput.required = true;
        toInput.required = true;
    } else {
        customRow.style.display = 'none';
        fromInput.required = false;
        toInput.required = false;
        fromInput.value = '';
        toInput.value = '';
        document.getElementById('dashFilterForm').submit();
    }
}
</script>
@endpush
