<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — {{ config('saas.platform_name', 'VMARKET POS') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: #111827;
            --border: #1e293b;
            --accent: #22c55e;
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
                radial-gradient(at 0% 0%, rgba(34, 197, 94, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(37, 99, 235, 0.12) 0px, transparent 50%);
        }

        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            width: 100%;
            max-width: 480px;
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
            background: linear-gradient(90deg, #22c55e, #38bdf8, #6366f1);
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
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #22c55e;
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

        .alert-danger { background: rgba(220, 38, 38, 0.15); color: #f87171; border: 1px solid rgba(220, 38, 38, 0.3); }

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
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.15s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25);
            background: rgba(11, 15, 25, 0.95);
        }

        .password-field-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .password-field-wrapper input {
            padding-right: 2.75rem;
        }

        .password-toggle-btn {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.15rem;
            padding: 0.25rem 0.4rem;
            line-height: 1;
            border-radius: 6px;
            transition: color 0.15s, background 0.15s;
            user-select: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle-btn:hover {
            color: #f8fafc;
            background: rgba(255, 255, 255, 0.08);
        }

        .btn-submit {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
            margin-top: 0.75rem;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            filter: brightness(1.1);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        /* Policy Checklist */
        .policy-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.78rem;
        }

        .policy-item {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--text-muted);
            margin-bottom: 0.3rem;
            transition: color 0.2s;
        }
        .policy-item:last-child { margin-bottom: 0; }
        .policy-item.valid { color: #4ade80; font-weight: 600; }
        .policy-icon { font-size: 0.85rem; }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-header">
            <div class="brand-icon">🛡️</div>
            <div class="portal-badge">🔒 PASSWORD RESET</div>
            <h1>Create New Password</h1>
            <p>Enter a secure new password for <strong>{{ $email }}</strong>.</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <span>❌</span> {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" id="resetForm">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="email">Account Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required readonly style="opacity: 0.85; background: rgba(15, 23, 42, 0.9);">
            </div>

            <div class="form-group">
                <label for="password">New Password</label>
                <div class="password-field-wrapper">
                    <input type="password" id="password" name="password" placeholder="••••••••" required autofocus oninput="checkPolicy()">
                    <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility" tabindex="-1" title="Show password">👁️</button>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <div class="password-field-wrapper">
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="••••••••" required oninput="checkPolicy()">
                    <button type="button" class="password-toggle-btn" id="toggleConfirmPasswordBtn" aria-label="Toggle password visibility" tabindex="-1" title="Show password">👁️</button>
                </div>
            </div>

            <!-- Password Policy Checklist -->
            <div class="policy-box">
                <div class="policy-item" id="rule-len">
                    <span class="policy-icon" id="icon-len">⚪</span> At least 8 characters
                </div>
                <div class="policy-item" id="rule-upper">
                    <span class="policy-icon" id="icon-upper">⚪</span> At least one uppercase letter (A-Z)
                </div>
                <div class="policy-item" id="rule-num">
                    <span class="policy-icon" id="icon-num">⚪</span> At least one number (0-9)
                </div>
                <div class="policy-item" id="rule-match">
                    <span class="policy-icon" id="icon-match">⚪</span> Passwords match
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                🔐 Save New Password & Sign In
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 1.25rem;">
            <a href="{{ route('portal.tenant.login') }}" style="color: var(--text-muted); font-size: 0.85rem; text-decoration: none;">
                ← Cancel & Return to Login
            </a>
        </div>
    </div>

    <script>
        // Password Visibility Toggles
        function bindToggle(inputId, btnId) {
            const input = document.getElementById(inputId);
            const btn = document.getElementById(btnId);
            if (input && btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (input.type === 'password') {
                        input.type = 'text';
                        btn.innerHTML = '🙈';
                        btn.title = 'Hide password';
                    } else {
                        input.type = 'password';
                        btn.innerHTML = '👁️';
                        btn.title = 'Show password';
                    }
                    input.focus();
                });
            }
        }
        bindToggle('password', 'togglePasswordBtn');
        bindToggle('password_confirmation', 'toggleConfirmPasswordBtn');

        function checkPolicy() {
            const pwd = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirmation').value;

            // 1. Length
            const hasLen = pwd.length >= 8;
            updateRule('len', hasLen);

            // 2. Uppercase
            const hasUpper = /[A-Z]/.test(pwd);
            updateRule('upper', hasUpper);

            // 3. Number
            const hasNum = /[0-9]/.test(pwd);
            updateRule('num', hasNum);

            // 4. Match
            const matches = pwd && confirm && pwd === confirm;
            updateRule('match', matches);
        }

        function updateRule(ruleId, valid) {
            const item = document.getElementById('rule-' + ruleId);
            const icon = document.getElementById('icon-' + ruleId);
            if (valid) {
                item.classList.add('valid');
                icon.textContent = '✅';
            } else {
                item.classList.remove('valid');
                icon.textContent = '⚪';
            }
        }

        // Enter-key advancement across fields
        const inputs = [document.getElementById('password'), document.getElementById('password_confirmation')];
        inputs.forEach((inp, idx) => {
            inp.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (idx + 1 < inputs.length) {
                        inputs[idx + 1].focus();
                        if (typeof inputs[idx + 1].select === 'function') inputs[idx + 1].select();
                    } else {
                        document.getElementById('resetForm').submit();
                    }
                }
            });
        });
    </script>

</body>
</html>
