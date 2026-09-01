<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Suspended — {{ config('saas.platform_name', 'VMARKET POS') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .card { background: #1e293b; border: 1px solid #7f1d1d; border-radius: 16px; width: 100%; max-width: 480px; padding: 36px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 22px; font-weight: 700; color: #fca5a5; margin-bottom: 12px; }
        p { font-size: 14px; color: #94a3b8; margin-bottom: 24px; line-height: 1.6; }
        .btn-action { display: inline-block; padding: 12px 24px; background: #38bdf8; color: #0f172a; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠️</div>
        <h1>Subscription Suspended</h1>
        <p>Your business subscription has expired or been temporarily suspended. Please contact your SaaS platform administrator to renew your plan and reactivate full access.</p>
        <a href="/logout" class="btn-action">Return to Sign In</a>
    </div>
</body>
</html>
