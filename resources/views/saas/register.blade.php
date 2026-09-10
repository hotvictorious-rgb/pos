<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Business Account — {{ config('saas.platform_name', 'VMARKET POS') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; width: 100%; max-width: 540px; padding: 36px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .logo { font-size: 24px; font-weight: 800; background: linear-gradient(135deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 8px; display: inline-block; }
        h1 { font-size: 22px; font-weight: 700; color: #f8fafc; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #94a3b8; margin-bottom: 6px; }
        input, select { width: 100%; padding: 12px 14px; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #f8fafc; font-size: 14px; outline: none; transition: border 0.2s; }
        input:focus, select:focus { border-color: #38bdf8; }
        .btn-submit { width: 100%; padding: 14px; background: linear-gradient(135deg, #0284c7, #6366f1); border: none; border-radius: 8px; color: white; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 12px; transition: opacity 0.2s; }
        .btn-submit:hover { opacity: 0.9; }
        .alert-error { background: #450a0a; border: 1px solid #991b1b; color: #fca5a5; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .password-field-wrapper { position: relative; display: flex; align-items: center; width: 100%; }
        .password-field-wrapper input { padding-right: 44px; }
        .password-toggle-btn { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: #94a3b8; cursor: pointer; font-size: 18px; padding: 4px 6px; line-height: 1; border-radius: 6px; transition: color 0.15s; user-select: none; }
        .password-toggle-btn:hover { color: #f8fafc; }
        @media (max-width: 600px) {
            body { padding: 12px; }
            .card { padding: 20px 16px; border-radius: 12px; }
            .grid-2 { grid-template-columns: 1fr; gap: 8px; }
            input, select { font-size: 16px; } /* Prevent iOS zoom */
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">{{ config('saas.platform_name', 'VMARKET POS') }}</div>
        <h1>Register Your Business Account</h1>

        @if($errors->any())
            <div class="alert-error">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('saas.register.post') }}" method="POST">
            @csrf

            <!-- Anti-abuse honeypot field (hidden from human users) -->
            <div style="display:none;" aria-hidden="true">
                <input type="text" name="registration_hp_check" value="" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label>Business / Company Name</label>
                <input type="text" name="business_name" required placeholder="e.g. Grace Supermarket & Provisions">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Owner Name</label>
                    <input type="text" name="owner_name" required placeholder="e.g. Madam Grace">
                </div>
                <div class="form-group">
                    <label>Owner Phone</label>
                    <input type="text" name="owner_phone" required placeholder="e.g. 08012345678">
                </div>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="owner_email" required placeholder="e.g. grace@supermarket.com">
            </div>

            <div class="form-group">
                <label>Password (Min 8 characters, at least 1 uppercase & 1 digit)</label>
                <div class="password-field-wrapper">
                    <input type="password" name="password" id="regPassword" required placeholder="••••••••" minlength="8">
                    <button type="button" class="password-toggle-btn" id="regToggleBtn" aria-label="Toggle password visibility" tabindex="-1" title="Show password">👁️</button>
                </div>
            </div>

            <div class="form-group">
                <label>Choose Subscription Plan</label>
                <select name="plan" required id="regPlan">
                    <option value="basic" {{ request('plan') === 'basic' ? 'selected' : '' }}>Starter Plan ({{ $settings['currency_symbol'] ?? '₦' }}{{ number_format((float)($settings['price_basic'] ?? 15000)) }}/mo — 1 Branch, 3 Users)</option>
                    <option value="pro" {{ (request('plan') === 'pro' || !request('plan')) ? 'selected' : '' }}>Professional Growth ({{ $settings['currency_symbol'] ?? '₦' }}{{ number_format((float)($settings['price_pro'] ?? 35000)) }}/mo — 5 Branches, 15 Users)</option>
                    <option value="enterprise" {{ request('plan') === 'enterprise' ? 'selected' : '' }}>Enterprise Multi-Branch ({{ $settings['currency_symbol'] ?? '₦' }}{{ number_format((float)($settings['price_enterprise'] ?? 75000)) }}/mo — Unlimited)</option>
                </select>
            </div>

            <button type="submit" class="btn-submit" id="regSubmitBtn">Start Free Trial 🚀</button>
        </form>

        <script>
            // Show / Hide Password Toggle
            const pInput = document.getElementById('regPassword');
            const tBtn = document.getElementById('regToggleBtn');
            if (pInput && tBtn) {
                tBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (pInput.type === 'password') {
                        pInput.type = 'text';
                        tBtn.innerHTML = '🙈';
                        tBtn.title = 'Hide password';
                    } else {
                        pInput.type = 'password';
                        tBtn.innerHTML = '👁️';
                        tBtn.title = 'Show password';
                    }
                    pInput.focus();
                });
            }

            // Universal Enter Key Progression across registration fields
            const form = document.querySelector('form');
            if (form) {
                const inputs = Array.from(form.querySelectorAll('input:not([type="hidden"]), select'));
                inputs.forEach((input, index) => {
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            if (index + 1 < inputs.length) {
                                inputs[index + 1].focus();
                                if (typeof inputs[index + 1].select === 'function') inputs[index + 1].select();
                            } else {
                                form.submit();
                            }
                        }
                    });
                });
            }
        </script>
    </div>
</body>
</html>
