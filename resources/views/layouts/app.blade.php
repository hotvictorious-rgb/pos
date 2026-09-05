<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Hysam Ventures') – Inventory & POS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
            --bg: #0b0f19;
            --sidebar-bg: #111827;
            --card-bg: #1f2937;
            --border: #374151;
            --text: #f9fafb;
            --text-muted: #9ca3af;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 50;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid var(--border);
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
        }

        .brand-text h1 {
            font-size: 1.1rem;
            font-weight: 800;
            color: #f9fafb;
        }

        .brand-text p {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .sidebar-menu {
            flex: 1;
            padding: 1rem 0.75rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .menu-category {
            font-size: 0.7rem;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.75rem 0.75rem 0.25rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .nav-item:hover {
            background: rgba(55, 65, 81, 0.6);
            color: var(--text);
        }

        .nav-item.active {
            background: rgba(37, 99, 235, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(37, 99, 235, 0.4);
        }

        .nav-item.pos-btn {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.3);
            margin: 0.5rem 0;
        }
        .nav-item.pos-btn:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        .nav-item.auditor-btn {
            color: #fca5a5;
            border: 1px solid rgba(220, 38, 38, 0.2);
        }
        .nav-item.auditor-btn.active {
            background: rgba(220, 38, 38, 0.2);
            border-color: rgba(220, 38, 38, 0.5);
        }

        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Main Content Wrapper */
        .main-wrapper {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar {
            background: rgba(17, 24, 39, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0.85rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .container {
            width: 100%;
            max-width: 1360px;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
        }

        /* Online Badge */
        .online-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.65rem;
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 99px;
            font-size: 0.75rem;
            color: #4ade80;
            font-weight: 700;
        }

        .online-dot {
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.2); }
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            font-weight: 600;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success { background: rgba(22, 163, 74, 0.2); border: 1px solid rgba(22, 163, 74, 0.4); color: #86efac; }
        .alert-warning { background: rgba(217, 119, 6, 0.2); border: 1px solid rgba(217, 119, 6, 0.4); color: #fde047; }
        .alert-danger  { background: rgba(220, 38, 38, 0.2); border: 1px solid rgba(220, 38, 38, 0.4); color: #fca5a5; }

        /* General UI components */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            font-family: inherit;
        }

        .btn:active { transform: scale(0.97); }
        .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .btn-success { background: var(--success); color: #fff; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3); }
        .btn-warning { background: var(--warning); color: #fff; box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3); }
        .btn-danger  { background: var(--danger);  color: #fff; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
        .btn-secondary { background: var(--card-bg); color: var(--text-muted); border: 1px solid var(--border); }
        .btn-lg { padding: 1rem 1.75rem; font-size: 1.05rem; border-radius: 14px; }
        .btn-block { width: 100%; }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.65rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .badge-success { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
        .badge-warning { background: rgba(217,119,6,0.15); color: #fde047; border: 1px solid rgba(217,119,6,0.3); }
        .badge-danger  { background: rgba(220,38,38,0.15); color: #f87171; border: 1px solid rgba(220,38,38,0.3); }
        .badge-info    { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }

        /* Modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 100;
        }
        .modal {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            width: 100%;
            max-width: 580px;
            padding: 2rem;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
            max-height: 90vh;
            overflow-y: auto;
        }

        /* Form elements */
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em; }
        input, select, textarea {
            width: 100%; padding: 0.85rem 1rem;
            background: rgba(11, 15, 25, 0.7);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            font-size: 1rem;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
        }

        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; }
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; }

        @media (max-width: 1024px) {
            .sidebar { width: 75px; }
            .sidebar-header .brand-text, .menu-category, .nav-item span, .sidebar-footer { display: none; }
            .sidebar-header { justify-content: center; padding: 1rem; }
            .nav-item { justify-content: center; padding: 0.75rem; }
            .main-wrapper { margin-left: 75px; }
        }
    </style>
    @stack('styles')
</head>
<body>

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        @php
            $activeTenantId = session('tenant_id', 'default-tenant');
            $tenantModel = \App\Models\Tenant::find($activeTenantId);
            $displayBrandName = ($activeTenantId !== 'default-tenant' && $tenantModel) 
                ? $tenantModel->name 
                : config('saas.platform_name', 'VMARKET POS');
        @endphp
        <div class="sidebar-header">
            <div class="brand-icon">📦</div>
            <div class="brand-text">
                <h1>{{ $displayBrandName }}</h1>
                <p>{{ $activeTenantId === 'default-tenant' ? 'Platform Master Suite' : 'Multi-Branch POS' }}</p>
            </div>
        </div>

        @php
            $currentRole = auth()->user()->role ?? $authUser->role ?? 'admin';
        @endphp
        <nav class="sidebar-menu">
            <div class="menu-category">Main Operations</div>
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span>🏠</span> <span>{{ $currentRole === 'cashier' ? 'My Shift Summary' : ($currentRole === 'storekeeper' ? 'Stock Hub' : 'Dashboard') }}</span>
            </a>

            @if(in_array($currentRole, ['admin', 'manager', 'sales_stock', 'cashier']))
                <!-- Big POS Button -->
                <a href="{{ route('pos.index') }}" class="nav-item pos-btn {{ request()->routeIs('pos.index') ? 'active' : '' }}">
                    <span>💰</span> <span>Sell Goods (POS)</span>
                </a>
            @endif

            <div class="menu-category">Inventory & Stock</div>
            <a href="{{ route('products.index') }}" class="nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}">
                <span>🛍️</span> <span>Products Catalog</span>
            </a>

            @if(in_array($currentRole, ['admin', 'manager', 'sales_stock', 'storekeeper', 'viewer']))
                <a href="{{ route('stock.index') }}" class="nav-item {{ request()->routeIs('stock.index') ? 'active' : '' }}">
                    <span>📦</span> <span>Stock In / Out</span>
                </a>
                <a href="{{ route('stock.transfers') }}" class="nav-item {{ request()->routeIs('stock.transfers') ? 'active' : '' }}">
                    <span>🚚</span> <span>Shop Transfers</span>
                </a>
                <a href="{{ route('stock.unsupplied') }}" class="nav-item {{ request()->routeIs('stock.unsupplied') ? 'active' : '' }}">
                    <span>⏳</span> <span>Pickup Orders</span>
                </a>
                <a href="{{ route('stock.adjustments') }}" class="nav-item {{ request()->routeIs('stock.adjustments') ? 'active' : '' }}">
                    <span>📉</span> <span>Damaged Goods</span>
                </a>
            @endif

            <div class="menu-category">Ledgers & History</div>
            <a href="{{ route('transactions.index') }}" class="nav-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
                <span>📜</span> <span>{{ $currentRole === 'cashier' ? 'My Sales History' : 'History & Ledgers' }}</span>
            </a>

            @if(in_array($currentRole, ['admin', 'manager', 'sales_stock', 'cashier']))
                <a href="{{ route('pos.returns') }}" class="nav-item {{ request()->routeIs('pos.returns') ? 'active' : '' }}">
                    <span>🔄</span> <span>Returns & Refunds</span>
                </a>
            @endif

            @if(in_array($currentRole, ['admin', 'manager', 'sales_stock', 'viewer']))
                <a href="{{ route('debts.index') }}" class="nav-item {{ request()->routeIs('debts.*') ? 'active' : '' }}">
                    <span>💳</span> <span>Customer Debts</span>
                </a>
            @endif


            @if(in_array($currentRole, ['admin', 'manager', 'viewer']))
                <div class="menu-category">Management & Reports</div>
                @if(in_array($currentRole, ['admin', 'viewer']))
                    <a href="{{ route('auditor.index') }}" class="nav-item auditor-btn {{ request()->routeIs('auditor.*') ? 'active' : '' }}">
                        <span>🚨</span> <span>Auditor Control Hub</span>
                    </a>
                @endif
                <a href="{{ route('reports.index') }}" class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <span>📊</span> <span>Reports & AI Exports</span>
                </a>
                @if(in_array($currentRole, ['admin', 'viewer']))
                    <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <span>👥</span> <span>Workers & Roles</span>
                    </a>
                @endif
                @if($currentRole === 'admin')
                    <a href="{{ route('settings.index') }}" class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                        <span>⚙️</span> <span>System Settings</span>
                    </a>
                @endif
                @if(auth()->check() && auth()->user()->isPlatformAdmin())
                    <div class="menu-category" style="color: #38bdf8; margin-top: 0.5rem;">SaaS Control</div>
                    <a href="{{ route('saas.admin.index') }}" class="nav-item {{ request()->routeIs('saas.admin.*') ? 'active' : '' }}" style="border: 1px solid rgba(56, 189, 248, 0.4); background: rgba(56, 189, 248, 0.12);">
                        <span>🌐</span> <span style="color: #38bdf8; font-weight: 800;">SaaS Master Portal</span>
                    </a>
                @endif
            @endif

            <div class="menu-category">Support & Help</div>
            <a href="{{ route('help.index') }}" class="nav-item {{ request()->routeIs('help.*') ? 'active' : '' }}" style="color: #93c5fd;">
                <span>📖</span> <span>User Guide & FAQs</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="online-badge">
                <span class="online-dot"></span> Online
            </div>
            <div style="font-size: 0.75rem; color: #6b7280;">v1.2.0</div>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <header class="topbar">
            <!-- Left: Live Clock & Date -->
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <div id="liveClockWidget" style="background: rgba(31,41,55,0.7); border: 1px solid var(--border); border-radius: 10px; padding: 0.4rem 0.85rem; font-size: 0.85rem; font-weight: 700; color: #93c5fd; display: flex; align-items: center; gap: 0.5rem;">
                    <span>📅</span> <span id="headerDate">--</span>
                    <span style="color: #4b5563;">|</span>
                    <span>⏰</span> <span id="headerTime" style="color: #4ade80;">--:--:--</span>
                </div>

                @if($currentRole === 'viewer')
                    <div style="background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.4); border-radius: 10px; padding: 0.4rem 0.85rem; font-size: 0.82rem; font-weight: 800; color: #facc15; display: inline-flex; align-items: center; gap: 0.4rem;">
                        <span>👑</span> <span>Executive Observer (View-Only Mode)</span>
                    </div>
                @endif
            </div>

            <!-- Right: Quick Calculator, Operator & Logout -->
            <div style="display: flex; align-items: center; gap: 0.85rem;">
                <button type="button" class="btn btn-secondary" style="padding: 0.4rem 0.85rem; font-size: 0.85rem; background: rgba(31,41,55,0.9); border-color: #4b5563; color: #f3f4f6;" onclick="toggleCalculator()">
                    🧮 Calculator
                </button>

                <div style="font-size: 0.85rem; color: var(--text-muted);">
                    Operator: <strong style="color: #f3f4f6;">{{ auth()->user()->name ?? session('user_name', 'Auditor / Lead') }}</strong>
                </div>

                <a href="{{ route('logout') }}" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; background: rgba(220,38,38,0.15); border-color: rgba(220,38,38,0.4); color: #fca5a5; display: inline-flex; align-items: center; gap: 0.35rem;" title="Sign out of system">
                    <span>🚪</span> <span>Log Out</span>
                </a>
            </div>
        </header>

        <main class="container">
            @if(session('success'))
                <div class="alert alert-success">
                    <span>✓</span> {{ session('success') }}
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning">
                    <span>⚠️</span> {{ session('warning') }}
                </div>
            @endif

            @if(isset($errors) && $errors->any())
                <div class="alert alert-danger">
                    <span>❌</span> {{ $errors->first() }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Universal Action Confirmation Modal (What Will Happen) -->
    <div id="modalGlobalConfirm" class="modal-backdrop" style="display: none; z-index: 9999;">
        <div class="modal" id="globalConfirmCard" style="max-width: 480px; padding: 1.75rem; background: #0f172a; border: 2px solid #3b82f6; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.7); animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
            <div style="text-align: center; margin-bottom: 1.25rem;">
                <div id="globalConfirmIcon" style="font-size: 2.75rem; margin-bottom: 0.35rem; line-height: 1;">⚡</div>
                <h3 id="globalConfirmTitle" style="font-size: 1.25rem; font-weight: 800; color: #f8fafc;">Confirm Action</h3>
                <p id="globalConfirmSubtitle" style="font-size: 0.82rem; color: #94a3b8; margin-top: 0.25rem;">Review what will happen before proceeding:</p>
            </div>

            <div id="globalConfirmBody" style="background: rgba(15,23,42,0.85); border: 1px solid var(--border); border-radius: 14px; padding: 1rem; margin-bottom: 1.25rem; font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.6rem;">
            </div>

            <div id="globalConfirmImpactWrap" style="margin-bottom: 1.25rem; display: none;">
                <div id="globalConfirmImpact" style="font-weight: 700; padding: 0.65rem 0.85rem; border-radius: 10px; font-size: 0.82rem;"></div>
            </div>

            <div style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn btn-secondary" style="flex: 1; padding: 0.75rem; font-weight: 700;" onclick="closeGlobalConfirm()">
                    ✕ Cancel / Edit
                </button>
                <button type="button" id="globalConfirmProceedBtn" class="btn btn-success" style="flex: 1.3; padding: 0.75rem; font-weight: 800;" onclick="executeGlobalConfirm()">
                    ✅ Yes, Proceed
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Header Calculator Modal -->
    <div id="modalCalculator" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 360px; padding: 1.5rem; background: #111827; border: 2px solid #374151;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: #f9fafb;">🧮 POS Calculator</h3>
                <button type="button" onclick="toggleCalculator()" style="background: none; border: none; color: #9ca3af; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <!-- Display -->
            <div id="calcDisplay" style="background: #030712; border: 1px solid #374151; border-radius: 12px; padding: 1rem; font-size: 1.8rem; font-weight: 800; text-align: right; color: #4ade80; overflow-x: auto; margin-bottom: 1rem; min-height: 60px; font-family: monospace;">
                0
            </div>

            <!-- Keypad Grid -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #dc2626; color: #fff;" onclick="calcClear()">C</button>
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem;" onclick="calcInput('(')">(</button>
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem;" onclick="calcInput(')')">)</button>
                <button type="button" class="btn btn-primary" style="padding: 0.85rem; font-size: 1.1rem;" onclick="calcInput('/')">÷</button>

                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('7')">7</button>
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('8')">8</button>
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('9')">9</button>
                <button type="button" class="btn btn-primary" style="padding: 0.85rem; font-size: 1.1rem;" onclick="calcInput('*')">×</button>

                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('4')">4</button>
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('5')">5</button>
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('6')">6</button>
                <button type="button" class="btn btn-primary" style="padding: 0.85rem; font-size: 1.1rem;" onclick="calcInput('-')">−</button>

                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('1')">1</button>
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('2')">2</button>
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('3')">3</button>
                <button type="button" class="btn btn-primary" style="padding: 0.85rem; font-size: 1.1rem;" onclick="calcInput('+')">+</button>

                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('0')">0</button>
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('00')">00</button>
                <button type="button" class="btn btn-secondary" style="padding: 0.85rem; font-size: 1.1rem; background: #1f2937;" onclick="calcInput('.')">.</button>
                <button type="button" class="btn btn-success" style="padding: 0.85rem; font-size: 1.1rem;" onclick="calcEquals()">=</button>
            </div>
        </div>
    </div>

    <!-- Global Action Blocked / Business Rule Constraint Reason Modal -->
    <div id="modalActionBlocked" class="modal-backdrop" style="display: none; z-index: 1200;">
        <div class="modal" style="max-width: 480px; padding: 1.75rem; background: #0f172a; border: 2px solid #ef4444; border-radius: 20px; box-shadow: 0 25px 70px rgba(239,68,68,0.25);">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(239,68,68,0.15); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0;">
                    ⛔
                </div>
                <div>
                    <h3 style="font-size: 1.2rem; font-weight: 800; color: #f87171; margin-bottom: 0.15rem;" id="actionBlockedTitle">Action Blocked</h3>
                    <span style="font-size: 0.78rem; color: #94a3b8;" id="actionBlockedSubtitle">Business Rule & Constraint Validation Failed</span>
                </div>
                <button type="button" onclick="closeActionBlockedModal()" style="margin-left: auto; background: none; border: none; color: #94a3b8; font-size: 1.25rem; cursor: pointer;">✕</button>
            </div>

            <p style="font-size: 0.84rem; color: #cbd5e1; margin-bottom: 0.75rem;">
                This request cannot be submitted because the following rules were not met:
            </p>

            <div id="actionBlockedReasonsList" style="background: rgba(15,23,42,0.85); border: 1px solid rgba(239,68,68,0.3); border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem; max-height: 250px; overflow-y: auto;">
                <!-- Dynamically populated reason rows -->
            </div>

            <button type="button" class="btn btn-primary btn-block" style="font-weight: 800; padding: 0.75rem; border-radius: 10px; background: #ef4444; border-color: #dc2626; box-shadow: 0 4px 15px rgba(239,68,68,0.35);" onclick="closeActionBlockedModal()">
                🔧 Fix Requirements & Continue
            </button>
        </div>
    </div>

    <!-- Live Clock & Calculator Scripts -->
    <script>
    // 1. Live Clock Engine
    function updateClock() {
        const now = new Date();
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        const dayName = days[now.getDay()];
        const day = String(now.getDate()).padStart(2, '0');
        const month = months[now.getMonth()];
        const year = now.getFullYear();

        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // 0 should be 12
        const strHours = String(hours).padStart(2, '0');

        const dateEl = document.getElementById('headerDate');
        const timeEl = document.getElementById('headerTime');

        if (dateEl) dateEl.textContent = `${dayName}, ${day} ${month} ${year}`;
        if (timeEl) timeEl.textContent = `${strHours}:${minutes}:${seconds} ${ampm}`;
    }

    setInterval(updateClock, 1000);
    updateClock();

    // 2. Interactive Calculator Engine
    let calcExpression = '';

    function toggleCalculator() {
        const modal = document.getElementById('modalCalculator');
        modal.style.display = (modal.style.display === 'none' || modal.style.display === '') ? 'flex' : 'none';
    }

    function calcInput(val) {
        if (calcExpression === '0' && val !== '.') calcExpression = '';
        calcExpression += val;
        document.getElementById('calcDisplay').textContent = calcExpression;
    }

    function calcClear() {
        calcExpression = '';
        document.getElementById('calcDisplay').textContent = '0';
    }

    function calcEquals() {
        try {
            // Safe evaluation of arithmetic only
            const sanitized = calcExpression.replace(/[^0-9+\-*/().]/g, '');
            if (!sanitized) return;
            const result = Function('"use strict";return (' + sanitized + ')')();
            calcExpression = String(result);
            document.getElementById('calcDisplay').textContent = Number(result).toLocaleString('en-US', { maximumFractionDigits: 4 });
        } catch (e) {
            document.getElementById('calcDisplay').textContent = 'Error';
            calcExpression = '';
        }
    }

    // 3. Universal Action Confirmation Modal Engine
    let pendingConfirmAction = null;

    function showConfirmPopup({
        icon = '⚡',
        title = 'Confirm Action',
        subtitle = 'Review what will happen before proceeding:',
        items = [], // Array of { label: '...', value: '...', color: '...', size: '...' }
        message = '',
        impact = null, // { text: '...', type: 'success'|'warning'|'danger'|'info' }
        confirmText = '✅ Yes, Proceed',
        confirmClass = 'btn-success',
        borderColor = '#3b82f6',
        onConfirm = null,
        form = null
    }) {
        document.getElementById('globalConfirmIcon').textContent = icon;
        document.getElementById('globalConfirmTitle').textContent = title;
        document.getElementById('globalConfirmSubtitle').textContent = subtitle;

        const card = document.getElementById('globalConfirmCard');
        if (card) card.style.borderColor = borderColor;

        const bodyEl = document.getElementById('globalConfirmBody');
        bodyEl.innerHTML = '';

        if (items && items.length > 0) {
            items.forEach(item => {
                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.justifyContent = 'space-between';
                row.style.alignItems = 'center';
                row.style.borderBottom = '1px dashed #334155';
                row.style.paddingBottom = '0.45rem';

                const labelSpan = document.createElement('span');
                labelSpan.style.color = '#94a3b8';
                labelSpan.textContent = item.label + ':';

                const valSpan = document.createElement('strong');
                valSpan.textContent = item.value;
                valSpan.style.color = item.color || '#f8fafc';
                if (item.size) valSpan.style.fontSize = item.size;

                row.appendChild(labelSpan);
                row.appendChild(valSpan);
                bodyEl.appendChild(row);
            });
        } else if (message) {
            const p = document.createElement('div');
            p.style.color = '#cbd5e1';
            p.style.lineHeight = '1.5';
            p.innerHTML = message;
            bodyEl.appendChild(p);
        }

        const impactWrap = document.getElementById('globalConfirmImpactWrap');
        const impactEl = document.getElementById('globalConfirmImpact');
        if (impact && impact.text) {
            impactWrap.style.display = 'block';
            impactEl.textContent = impact.text;
            if (impact.type === 'danger') {
                impactEl.style.background = 'rgba(220,38,38,0.15)';
                impactEl.style.color = '#f87171';
                impactEl.style.border = '1px solid #ef4444';
            } else if (impact.type === 'warning') {
                impactEl.style.background = 'rgba(245,158,11,0.15)';
                impactEl.style.color = '#fbbf24';
                impactEl.style.border = '1px solid #f59e0b';
            } else if (impact.type === 'info') {
                impactEl.style.background = 'rgba(59,130,246,0.15)';
                impactEl.style.color = '#60a5fa';
                impactEl.style.border = '1px solid #3b82f6';
            } else {
                impactEl.style.background = 'rgba(34,197,94,0.15)';
                impactEl.style.color = '#4ade80';
                impactEl.style.border = '1px solid #22c55e';
            }
        } else {
            impactWrap.style.display = 'none';
        }

        const proceedBtn = document.getElementById('globalConfirmProceedBtn');
        proceedBtn.textContent = confirmText;
        proceedBtn.className = 'btn ' + confirmClass;

        pendingConfirmAction = () => {
            if (typeof onConfirm === 'function') {
                onConfirm();
            } else if (form) {
                try {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                } catch (e) {
                    HTMLFormElement.prototype.submit.call(form);
                }
            }
        };

        document.getElementById('modalGlobalConfirm').style.display = 'flex';
    }

    function closeGlobalConfirm() {
        document.getElementById('modalGlobalConfirm').style.display = 'none';
        pendingConfirmAction = null;
    }

    function executeGlobalConfirm() {
        const act = pendingConfirmAction;
        closeGlobalConfirm();
        if (act) act();
    }

    // 4. Universal Action Blocked & Business Rule Interceptor Engine
    let actionBlockedFocusTarget = null;

    function showActionBlockedModal({
        title = 'Action Blocked',
        subtitle = 'Business Rule & Constraint Validation Failed',
        errors = [], // Array of { title: '...', desc: '...', focus: 'elementId' }
        focus = null
    }) {
        const titleEl = document.getElementById('actionBlockedTitle');
        const subEl = document.getElementById('actionBlockedSubtitle');
        if (titleEl) titleEl.textContent = title;
        if (subEl) subEl.textContent = subtitle;

        const listEl = document.getElementById('actionBlockedReasonsList');
        if (listEl) {
            listEl.innerHTML = '';
            actionBlockedFocusTarget = focus || (errors.length > 0 ? errors[0].focus : null);

            errors.forEach(err => {
                const item = document.createElement('div');
                item.style.display = 'flex';
                item.style.alignItems = 'flex-start';
                item.style.gap = '0.65rem';
                item.innerHTML = `
                    <span style="color: #ef4444; font-size: 1.1rem; line-height: 1.2;">⚠️</span>
                    <div style="flex: 1;">
                        <strong style="color: #f8fafc; font-size: 0.88rem; display: block;">${err.title || 'Validation Error'}</strong>
                        <div style="font-size: 0.8rem; color: #cbd5e1; margin-top: 0.15rem; line-height: 1.35;">${err.desc || err}</div>
                    </div>
                `;
                listEl.appendChild(item);
            });
        }

        const modal = document.getElementById('modalActionBlocked');
        if (modal) modal.style.display = 'flex';
    }

    function closeActionBlockedModal() {
        const modal = document.getElementById('modalActionBlocked');
        if (modal) modal.style.display = 'none';

        if (actionBlockedFocusTarget) {
            const target = typeof actionBlockedFocusTarget === 'string' ? document.getElementById(actionBlockedFocusTarget) : actionBlockedFocusTarget;
            if (target && typeof target.focus === 'function') {
                target.focus();
                if (typeof target.select === 'function') target.select();
            }
            actionBlockedFocusTarget = null;
        }
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'flex';
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    // Global click listener to close modals when clicking on the backdrop
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('modal-backdrop')) {
            e.target.style.display = 'none';
            if (e.target.id === 'modalGlobalConfirm') {
                pendingConfirmAction = null;
            }
        }
    });

    // Global keyboard listener (Escape to close all modals)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop').forEach(modal => {
                if (modal.style.display !== 'none') {
                    modal.style.display = 'none';
                }
            });
            pendingConfirmAction = null;
        }
    });
    </script>

    @stack('scripts')
</body>
</html>

