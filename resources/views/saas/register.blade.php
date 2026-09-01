<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Business Account — Hysam SaaS POS</title>
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
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Hysam SaaS POS</div>
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
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>

            <div class="form-group">
                <label>Choose Subscription Plan</label>
                <select name="plan" required>
                    <option value="basic">Starter Plan (1 Branch, 3 Users) — ₦15,000/mo</option>
                    <option value="pro" selected>Professional Growth (5 Branches, 15 Users) — ₦35,000/mo</option>
                    <option value="enterprise">Enterprise Multi-Branch (Unlimited) — ₦75,000/mo</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Start 14-Day Free Trial 🚀</button>
        </form>
    </div>
</body>
</html>
