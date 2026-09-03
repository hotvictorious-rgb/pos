<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $meta['title'] }} — {{ config('saas.platform_name', 'VMARKET POS') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: #111827;
            --border: #1e293b;
            --accent: {{ $meta['badge_color'] }};
            --text: #f9fafb;
            --text-muted: #94a3b8;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background-image: 
                radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.1) 0px, transparent 50%);
        }

        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            width: 100%;
            max-width: 460px;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent), #38bdf8, #22c55e);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .portal-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--accent);
            color: var(--accent);
            margin-bottom: 0.85rem;
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }

        .brand-header h1 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #f9fafb;
            letter-spacing: -0.02em;
        }

        .brand-header p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
            line-height: 1.4;
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            line-height: 1.4;
        }

        .alert-success { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
        .alert-danger { background: rgba(220, 38, 38, 0.15); color: #f87171; border: 1px solid rgba(220, 38, 38, 0.3); }
        .alert-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        input {
            width: 100%;
            padding: 0.85rem 1rem;
            background: rgba(11, 15, 25, 0.8);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.15s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25);
            background: rgba(11, 15, 25, 0.95);
        }

        .btn-submit {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, var(--accent), #1d4ed8);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            filter: brightness(1.1);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        .portal-switch {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.78rem;
            color: var(--text-muted);
            text-align: center;
        }

        .portal-switch-links {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 0.6rem;
        }

        .portal-switch-link {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #93c5fd;
            text-decoration: none;
            font-size: 0.75rem;
            transition: all 0.15s ease;
        }

        .portal-switch-link:hover {
            background: rgba(37, 99, 235, 0.2);
            border-color: #3b82f6;
            color: #fff;
        }

        .portal-switch-link.active {
            border-color: var(--accent);
            color: var(--accent);
            font-weight: 700;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-header">
            <div class="brand-icon">{{ $meta['icon'] }}</div>
            <div class="portal-badge">{{ $meta['badge'] }}</div>
            <h1>{{ $meta['title'] }}</h1>
            <p>{{ $meta['subtitle'] }}</p>
        </div>

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

        @if(session('error'))
            <div class="alert alert-danger">
                <span>❌</span> {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <span>❌</span> {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ $meta['route'] }}">
            @csrf

            <div class="form-group">
                <label for="email">Account Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="user@domain.com" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-submit">
                🔐 Sign In to Portal
            </button>
        </form>

        <div class="portal-switch">
            <div>Switch Portal:</div>
            <div class="portal-switch-links">
                <a href="{{ route('portal.tenant.login') }}" class="portal-switch-link {{ $meta['portal'] === 'tenant' ? 'active' : '' }}">🏢 Tenant Owner</a>
                <a href="{{ route('portal.tenant_employee.login') }}" class="portal-switch-link {{ $meta['portal'] === 'tenant-employee' ? 'active' : '' }}">💼 Staff & Cashier</a>
                <a href="{{ route('portal.super_admin.login') }}" class="portal-switch-link {{ $meta['portal'] === 'super-admin' ? 'active' : '' }}">🛡️ Super-Admin</a>
                <a href="{{ route('portal.super_admin_employee.login') }}" class="portal-switch-link {{ $meta['portal'] === 'super-admin-employee' ? 'active' : '' }}">👥 Platform Staff</a>
            </div>
        </div>
    </div>

</body>
</html>
