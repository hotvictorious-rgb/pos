<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — {{ config('saas.platform_name', 'Victorious Market POS') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(17, 24, 39, 0.95);
            --border: #1e293b;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-glow: rgba(37, 99, 235, 0.35);
            --text: #f9fafb;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --success: #22c55e;
            --danger: #ef4444;
            --warning: #f59e0b;
            --cyan: #38bdf8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            background-image: 
                radial-gradient(ellipse at 50% 0%, rgba(37, 99, 235, 0.15) 0%, transparent 60%),
                radial-gradient(ellipse at 50% 100%, rgba(16, 185, 129, 0.08) 0%, transparent 60%);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: auto 0;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-subtle);
            border-radius: 24px;
            width: 100%;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.05);
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
            background: linear-gradient(90deg, #2563eb, #38bdf8, #10b981);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-icon {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, #1e40af, #2563eb);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4);
        }

        .brand-header h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.025em;
            line-height: 1.25;
        }

        .brand-header p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
            font-weight: 500;
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

        .alert-success { 
            background: rgba(34, 197, 94, 0.12); 
            color: #4ade80; 
            border: 1px solid rgba(34, 197, 94, 0.3); 
        }
        
        .alert-danger { 
            background: rgba(239, 68, 68, 0.12); 
            color: #f87171; 
            border: 1px solid rgba(239, 68, 68, 0.3); 
        }
        
        .alert-warning { 
            background: rgba(245, 158, 11, 0.12); 
            color: #fbbf24; 
            border: 1px solid rgba(245, 158, 11, 0.3); 
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 0.45rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            height: 48px;
            padding: 0 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid #334155;
            border-radius: 12px;
            color: #f8fafc;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
            background: rgba(15, 23, 42, 0.98);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
        }

        input::placeholder {
            color: #475569;
        }

        .password-field-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .password-field-wrapper input {
            padding-right: 2.85rem;
        }

        .password-toggle-btn {
            position: absolute;
            right: 0.6rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.15rem;
            padding: 0.3rem 0.45rem;
            line-height: 1;
            border-radius: 8px;
            transition: color 0.15s, background 0.15s;
            user-select: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
        }

        .forgot-link {
            color: var(--cyan);
            font-size: 0.8rem;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.15s;
        }

        .forgot-link:hover {
            color: #93c5fd;
            text-decoration: underline;
        }

        .btn-submit {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px var(--primary-glow);
            margin-top: 0.75rem;
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.5);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 0.82rem;
            color: var(--text-dim);
            line-height: 1.6;
            width: 100%;
        }

        .login-footer strong {
            color: #cbd5e1;
            font-weight: 600;
        }

        .login-footer a {
            color: var(--cyan);
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.25rem;
            transition: color 0.15s;
        }

        .login-footer a:hover {
            color: #93c5fd;
            text-decoration: underline;
        }

        /* Mobile Layout Refinements */
        @media (max-width: 480px) {
            body {
                padding: 1.25rem 0.85rem;
            }

            .login-card {
                padding: 2rem 1.25rem;
                border-radius: 20px;
            }

            .brand-header {
                margin-bottom: 1.5rem;
            }

            .brand-header h1 {
                font-size: 1.3rem;
            }

            .brand-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
                margin-bottom: 0.85rem;
            }

            .btn-submit {
                height: 48px;
                font-size: 0.95rem;
            }

            .login-footer {
                margin-top: 1.5rem;
                font-size: 0.78rem;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-card">
            <div class="brand-header">
                <div class="brand-icon">🏪</div>
                <h1>{{ config('saas.platform_name', 'Victorious Market POS') }}</h1>
                <p>Modern Retail & Multi-Branch Cloud POS</p>
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

            <form method="POST" action="{{ route('login.post') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Work Email, Username or Phone</label>
                    <input type="text" id="email" name="email" value="{{ old('email') }}" placeholder="e.g. admin or staff@example.com or 08012345678" required autofocus>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                        <label for="password" style="margin-bottom: 0;">Password</label>
                        <a href="{{ \Illuminate\Support\Facades\Route::has('password.request') ? route('password.request') : url('/forgot-password') }}" class="forgot-link" title="Recover account password">
                            Forgot Password?
                        </a>
                    </div>
                    <div class="password-field-wrapper">
                        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility" tabindex="-1" title="Show password">👁️</button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="loginSubmitBtn">
                    🔐 Sign In to Dashboard
                </button>
            </form>
        </div>

        <div class="login-footer">
            Powered by <strong>Victorious Market</strong> — Your Trusted Online Market<br>
            <a href="https://www.victoriousmarket.com.ng" target="_blank" rel="noopener">www.victoriousmarket.com.ng</a>
        </div>
    </div>

    <script>
        // Show / Hide Password Toggle
        const passInput = document.getElementById('password');
        const toggleBtn = document.getElementById('togglePasswordBtn');
        if (passInput && toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (passInput.type === 'password') {
                    passInput.type = 'text';
                    toggleBtn.innerHTML = '🙈';
                    toggleBtn.title = 'Hide password';
                } else {
                    passInput.type = 'password';
                    toggleBtn.innerHTML = '👁️';
                    toggleBtn.title = 'Show password';
                }
                passInput.focus();
            });
        }

        // Universal Enter Key Progression: email -> Enter -> password -> Enter -> submit
        const emailInput = document.getElementById('email');
        if (emailInput && passInput) {
            emailInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    passInput.focus();
                    if (typeof passInput.select === 'function') passInput.select();
                }
            });
        }
    </script>

</body>
</html>
