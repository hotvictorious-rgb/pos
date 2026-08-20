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
            --bg: #0f172a;
            --card-bg: #1e293b;
            --border: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Navigation Bar */
        .navbar {
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0.85rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--text);
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .brand-text h1 {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .brand-text p {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--text-muted);
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .nav-btn:hover {
            background: rgba(51, 65, 85, 0.5);
            color: var(--text);
            border-color: var(--border);
        }

        .nav-btn.active {
            background: rgba(37, 99, 235, 0.15);
            color: #60a5fa;
            border-color: rgba(37, 99, 235, 0.3);
        }

        .nav-btn.pos-highlight {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.3);
        }

        .nav-btn.pos-highlight:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Status indicators */
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

        /* Container */
        .container {
            width: 100%;
            max-width: 1360px;
            margin: 0 auto;
            padding: 1.5rem 1rem;
            flex: 1;
        }

        /* Flash Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
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
            border-radius: 10px;
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
        .btn-lg { padding: 1rem 1.75rem; font-size: 1.1rem; border-radius: 14px; }
        .btn-block { width: 100%; }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
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
            background: rgba(0,0,0,0.7);
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
            max-width: 550px;
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
            background: rgba(15, 23, 42, 0.6);
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

        /* Responsive grid */
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem; }
        .grid-3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem; }
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; }

        @media (max-width: 768px) {
            .nav-links { width: 100%; margin-top: 0.5rem; justify-content: flex-start; }
            .navbar { flex-direction: column; align-items: flex-start; }
        }
    </style>
    @stack('styles')
</head>
<body>

    <!-- Header Navigation -->
    <nav class="navbar">
        <a href="{{ route('dashboard') }}" class="brand">
            <div class="brand-icon">📦</div>
            <div class="brand-text">
                <h1>Hysam Ventures</h1>
                <p>Anti-Theft Inventory & POS</p>
            </div>
        </a>

        <div class="nav-links">
            <a href="{{ route('dashboard') }}" class="nav-btn {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                🏠 Home
            </a>
            <a href="{{ route('pos.index') }}" class="nav-btn pos-highlight">
                💰 Sell Goods (POS)
            </a>
            <a href="{{ route('stock.index') }}" class="nav-btn {{ request()->routeIs('stock.*') ? 'active' : '' }}">
                📦 Stock In/Out
            </a>
            <a href="{{ route('stock.unsupplied') }}" class="nav-btn {{ request()->routeIs('stock.unsupplied') ? 'active' : '' }}">
                ⏳ Pickup List
            </a>
            <a href="{{ route('debts.index') }}" class="nav-btn {{ request()->routeIs('debts.*') ? 'active' : '' }}">
                💳 Customer Debts
            </a>
            <a href="{{ route('auditor.index') }}" class="nav-btn {{ request()->routeIs('auditor.*') ? 'active' : '' }}" style="border-color: rgba(220,38,38,0.4); color: #fca5a5;">
                🚨 Auditor Hub
            </a>
            <div class="online-badge">
                <span class="online-dot"></span> System Online
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
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

        @if($errors->any())
            <div class="alert alert-danger">
                <span>❌</span> {{ $errors->first() }}
            </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
