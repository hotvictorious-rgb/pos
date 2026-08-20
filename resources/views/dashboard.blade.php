@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .welcome-banner {
        background: linear-gradient(135deg, rgba(37,99,235,0.2) 0%, rgba(139,92,246,0.2) 100%);
        border: 1px solid rgba(59,130,246,0.3);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
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

    /* Big Action Tiles (Child-Simple) */
    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }

    .action-tile {
        background: var(--card-bg);
        border: 2px solid var(--border);
        border-radius: 22px;
        padding: 2rem 1.5rem;
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
        transform: translateY(-6px);
        box-shadow: 0 20px 35px rgba(0,0,0,0.4);
    }

    .action-tile.tile-pos {
        border-color: rgba(22, 163, 74, 0.5);
        background: linear-gradient(180deg, rgba(22, 163, 74, 0.1) 0%, var(--card-bg) 100%);
    }
    .action-tile.tile-pos:hover { border-color: #22c55e; }

    .action-tile.tile-stock {
        border-color: rgba(37, 99, 235, 0.5);
        background: linear-gradient(180deg, rgba(37, 99, 235, 0.1) 0%, var(--card-bg) 100%);
    }
    .action-tile.tile-stock:hover { border-color: #3b82f6; }

    .action-tile.tile-unsupplied {
        border-color: rgba(217, 119, 6, 0.5);
        background: linear-gradient(180deg, rgba(217, 119, 6, 0.1) 0%, var(--card-bg) 100%);
    }
    .action-tile.tile-unsupplied:hover { border-color: #f59e0b; }

    .action-tile.tile-debts {
        border-color: rgba(139, 92, 246, 0.5);
        background: linear-gradient(180deg, rgba(139, 92, 246, 0.1) 0%, var(--card-bg) 100%);
    }
    .action-tile.tile-debts:hover { border-color: #a855f7; }

    .action-tile.tile-auditor {
        border-color: rgba(220, 38, 38, 0.5);
        background: linear-gradient(180deg, rgba(220, 38, 38, 0.1) 0%, var(--card-bg) 100%);
    }
    .action-tile.tile-auditor:hover { border-color: #ef4444; }

    .tile-icon {
        width: 80px;
        height: 80px;
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    }

    .tile-title {
        font-size: 1.35rem;
        font-weight: 800;
        color: #f8fafc;
        margin-bottom: 0.4rem;
    }

    .tile-desc {
        font-size: 0.875rem;
        color: #94a3b8;
        line-height: 1.4;
    }

    /* Metric Badges */
    .tile-badge {
        margin-top: 1rem;
        padding: 0.35rem 0.85rem;
        border-radius: 99px;
        font-size: 0.8rem;
        font-weight: 800;
    }

    /* Stat Cards */
    .stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .stat-val {
        font-size: 1.6rem;
        font-weight: 800;
        color: #f8fafc;
        margin-top: 0.25rem;
    }

    .stat-label {
        font-size: 0.8rem;
        color: #94a3b8;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
</style>
@endpush

@section('content')

    <!-- Welcome Header -->
    <div class="welcome-banner">
        <div class="welcome-text">
            <h2>Hysam Ventures Inventory Hub 📦</h2>
            <p>Welcome! Tap any big button below to make a sale, receive goods, or check stock.</p>
        </div>
        <a href="{{ route('pos.index') }}" class="btn btn-success btn-lg" style="box-shadow: 0 6px 20px rgba(22,163,74,0.4);">
            <span style="font-size:1.4rem;">💰</span> Start Selling Now
        </a>
    </div>

    <!-- Live Status Overview -->
    <div class="grid-4" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div>
                <div class="stat-label">Today's Sales</div>
                <div class="stat-val" style="color: #4ade80;">₦{{ number_format($todaySalesAmount, 0) }}</div>
                <small style="color: #94a3b8;">{{ $todaySalesCount }} transactions today</small>
            </div>
            <div style="font-size: 2.2rem;">💵</div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Sold Awaiting Pickup</div>
                <div class="stat-val" style="color: #fbbf24;">{{ $unsuppliedCount }} Orders</div>
                <small style="color: #fcd34d;">Still in physical stock</small>
            </div>
            <div style="font-size: 2.2rem;">⏳</div>
        </div>

        <div class="stat-card">
            <div>
                <div class="stat-label">Customer Debt</div>
                <div class="stat-val" style="color: #c084fc;">₦{{ number_format($totalDebt, 0) }}</div>
                <small style="color: #94a3b8;">Part-payments owed</small>
            </div>
            <div style="font-size: 2.2rem;">💳</div>
        </div>

        <div class="stat-card" style="{{ $discrepancyCount > 0 ? 'border-color: #ef4444; background: rgba(220,38,38,0.1);' : '' }}">
            <div>
                <div class="stat-label">Theft & Discrepancies</div>
                <div class="stat-val" style="color: {{ $discrepancyCount > 0 ? '#f87171' : '#4ade80' }};">
                    {{ $discrepancyCount }} Alert{{ $discrepancyCount == 1 ? '' : 's' }}
                </div>
                <small style="color: #94a3b8;">{{ $discrepancyCount > 0 ? 'Requires Auditor Check' : 'All counts verified' }}</small>
            </div>
            <div style="font-size: 2.2rem;">{{ $discrepancyCount > 0 ? '🚨' : '🛡️' }}</div>
        </div>
    </div>

    <!-- Big Action Tiles (Easy for Anyone) -->
    <h3 style="font-size: 1.15rem; font-weight: 800; color: #cbd5e1; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.05em;">
        ⚡ Touch an Action Below:
    </h3>

    <div class="action-grid">
        <!-- 1. POS Sale -->
        <a href="{{ route('pos.index') }}" class="action-tile tile-pos">
            <div class="tile-icon" style="background: linear-gradient(135deg, #16a34a, #22c55e);">
                💰
            </div>
            <div class="tile-title">Sell Goods</div>
            <div class="tile-desc">Scan or tap products, collect cash, POS, or give part-payment on credit.</div>
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

        <!-- 3. Unsupplied Goods Waiting in Shop -->
        <a href="{{ route('stock.unsupplied') }}" class="action-tile tile-unsupplied">
            <div class="tile-icon" style="background: linear-gradient(135deg, #d97706, #f59e0b);">
                ⏳
            </div>
            <div class="tile-title">Goods Awaiting Pickup</div>
            <div class="tile-desc">Items already paid for but customer hasn't collected yet. Keep count accurate!</div>
            <div class="tile-badge badge-warning">{{ $unsuppliedCount }} Pending Handover</div>
        </a>

        <!-- 4. Debt & Part-Payments -->
        <a href="{{ route('debts.index') }}" class="action-tile tile-debts">
            <div class="tile-icon" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
                💳
            </div>
            <div class="tile-title">Customer Debts</div>
            <div class="tile-desc">View customer debt balances, collect installment payments, and print receipts.</div>
            <div class="tile-badge" style="background: rgba(139,92,246,0.2); color: #c084fc;">Track Ledgers</div>
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
