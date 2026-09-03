<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VMARKET POS – The Modern Cloud POS & Inventory Built for Nigerian Commerce</title>
    <meta name="description" content="The cloud POS and inventory management system designed specifically for Nigerian supermarkets, wholesale depots, and retail chains. Reconcile bank transfers, prevent stock theft, track customer debt, and manage multiple branches.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-glow: rgba(37, 99, 235, 0.35);
            --accent: #3b82f6;
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.3);
            --warning: #f59e0b;
            --bg-dark: #070a12;
            --bg-surface: #0e1626;
            --bg-card: rgba(19, 29, 49, 0.7);
            --bg-card-hover: rgba(28, 43, 73, 0.85);
            --border: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(37, 99, 235, 0.4);
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-dark);
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
            background-image:
                radial-gradient(circle at 50% -10%, rgba(37, 99, 235, 0.22) 0%, transparent 60%),
                radial-gradient(circle at 90% 40%, rgba(16, 185, 129, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 10% 80%, rgba(37, 99, 235, 0.12) 0%, transparent 50%);
            background-attachment: fixed;
        }

        /* Utility Container */
        .container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Top Announcement Ribbon */
        .ribbon {
            background: linear-gradient(90deg, #1e3a8a, #2563eb, #059669);
            color: #ffffff;
            font-size: 0.825rem;
            font-weight: 600;
            text-align: center;
            padding: 0.55rem 1rem;
            letter-spacing: 0.02em;
        }

        .ribbon a {
            color: #fde047;
            text-decoration: underline;
            margin-left: 0.4rem;
            font-weight: 700;
        }

        /* Navigation */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(7, 10, 18, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 76px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #ffffff;
        }

        .logo-symbol {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            box-shadow: 0 4px 14px var(--primary-glow);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }

        .logo-title span {
            color: var(--accent);
        }

        .logo-tag {
            font-size: 0.68rem;
            font-weight: 700;
            color: var(--success);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2rem;
            list-style: none;
        }

        .nav-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 600;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: #ffffff;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.4rem;
            font-size: 0.92rem;
            font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid transparent;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            box-shadow: 0 4px 16px var(--primary-glow);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--primary-glow);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.12);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .btn-portal-dropdown {
            position: relative;
            display: inline-block;
        }

        .portal-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            width: 250px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.5rem;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.6);
            z-index: 100;
        }

        .btn-portal-dropdown:hover .portal-menu {
            display: block;
        }

        .portal-menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.65rem 0.85rem;
            color: #ffffff;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 8px;
            transition: background 0.15s;
        }

        .portal-menu-item:hover {
            background: rgba(37, 99, 235, 0.15);
            color: var(--accent);
        }

        .portal-menu-item span.icon {
            font-size: 1.1rem;
        }

        /* Hero Section */
        .hero {
            padding: 5.5rem 0 4.5rem;
            text-align: center;
            position: relative;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: rgba(37, 99, 235, 0.12);
            border: 1px solid rgba(37, 99, 235, 0.3);
            border-radius: 100px;
            color: #93c5fd;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1.8rem;
            letter-spacing: 0.02em;
        }

        .hero-title {
            font-size: clamp(2.4rem, 5.5vw, 4.2rem);
            font-weight: 900;
            letter-spacing: -0.035em;
            line-height: 1.1;
            max-width: 1020px;
            margin: 0 auto 1.5rem;
        }

        .hero-title .gradient-text {
            background: linear-gradient(135deg, #60a5fa 20%, #3b82f6 50%, #10b981 90%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: clamp(1.05rem, 2vw, 1.25rem);
            color: var(--text-muted);
            max-width: 800px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
        }

        .hero-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.25rem;
            flex-wrap: wrap;
            margin-bottom: 2.5rem;
        }

        .hero-trust {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            color: var(--text-dim);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .hero-trust-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .hero-trust-item span.check {
            color: var(--success);
            font-weight: 800;
        }

        /* Hero POS Mockup Card */
        .hero-mockup-wrapper {
            margin-top: 3.5rem;
            position: relative;
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
        }

        .mockup-glow {
            position: absolute;
            top: -20px;
            left: 5%;
            right: 5%;
            bottom: -20px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.25), transparent 70%);
            filter: blur(40px);
            z-index: 1;
            border-radius: 24px;
        }

        .mockup-card {
            position: relative;
            z-index: 2;
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(255, 255, 255, 0.05);
            overflow: hidden;
            text-align: left;
        }

        .mockup-header {
            background: #1e293b;
            padding: 0.85rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
        }

        .mockup-dots {
            display: flex;
            gap: 6px;
        }

        .mockup-dot {
            width: 11px;
            height: 11px;
            border-radius: 50%;
        }

        .mockup-dot.red { background: #ef4444; }
        .mockup-dot.yellow { background: #f59e0b; }
        .mockup-dot.green { background: #10b981; }

        .mockup-header-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .mockup-status-live {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--success);
            font-weight: 700;
        }

        .mockup-status-live::before {
            content: '';
            width: 7px;
            height: 7px;
            background: var(--success);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--success);
        }

        .mockup-body {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            background: #0b1120;
        }

        @media (max-width: 900px) {
            .mockup-body {
                grid-template-columns: 1fr;
            }
        }

        .mockup-catalog {
            padding: 1.5rem;
            border-right: 1px solid var(--border);
        }

        .catalog-title {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 0.85rem;
        }

        .product-item {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 0.85rem;
            transition: border-color 0.2s;
        }

        .product-item:hover {
            border-color: var(--primary);
        }

        .product-badge {
            font-size: 0.65rem;
            padding: 0.2rem 0.45rem;
            background: rgba(37, 99, 235, 0.2);
            color: #93c5fd;
            border-radius: 4px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 0.4rem;
        }

        .product-name {
            font-size: 0.88rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            line-height: 1.3;
        }

        .product-stock {
            font-size: 0.72rem;
            color: var(--text-dim);
            margin-bottom: 0.5rem;
        }

        .product-price {
            font-size: 1rem;
            font-weight: 800;
            color: #38bdf8;
        }

        .mockup-cart {
            padding: 1.5rem;
            background: #0f172a;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .cart-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .cart-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.6rem 0.75rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.82rem;
        }

        .cart-row-title {
            font-weight: 600;
        }

        .cart-row-qty {
            color: var(--text-dim);
            font-size: 0.75rem;
        }

        .cart-row-price {
            font-weight: 700;
            color: #ffffff;
        }

        .cart-summary {
            border-top: 1px solid var(--border);
            padding-top: 1rem;
        }

        .cart-total-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .cart-total-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .cart-total-amount {
            font-size: 1.6rem;
            font-weight: 900;
            color: var(--success);
            font-family: inherit;
        }

        .split-tender-box {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 10px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.78rem;
        }

        .split-tender-title {
            font-weight: 700;
            color: var(--success);
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .split-tender-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.4rem;
            text-align: center;
        }

        .split-pill {
            background: rgba(255, 255, 255, 0.05);
            padding: 0.3rem;
            border-radius: 6px;
            font-weight: 600;
        }

        .split-pill strong {
            display: block;
            color: #ffffff;
            font-size: 0.75rem;
        }

        .mockup-checkout-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #ffffff;
            border: none;
            padding: 0.85rem;
            border-radius: 10px;
            font-weight: 800;
            font-size: 0.95rem;
            width: 100%;
            cursor: pointer;
            box-shadow: 0 4px 16px var(--success-glow);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Section Layouts */
        .section {
            padding: 6rem 0;
            position: relative;
        }

        .section-header {
            text-align: center;
            max-width: 800px;
            margin: 0 auto 3.5rem;
        }

        .section-tag {
            font-size: 0.825rem;
            font-weight: 800;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.75rem;
            display: inline-block;
        }

        .section-title {
            font-size: clamp(2rem, 3.8vw, 2.8rem);
            font-weight: 900;
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: var(--text-muted);
            line-height: 1.7;
        }

        /* Pain Points Grid */
        .pain-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.75rem;
        }

        .pain-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .pain-card:hover {
            transform: translateY(-4px);
            border-color: var(--border-highlight);
            background: var(--bg-card-hover);
        }

        .pain-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(239, 68, 68, 0.12);
            color: #f87171;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }

        .pain-icon.solution {
            background: rgba(16, 185, 129, 0.12);
            color: var(--success);
        }

        .pain-problem {
            color: #fca5a5;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.4rem;
        }

        .pain-title {
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            color: #ffffff;
        }

        .pain-desc {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 2.25rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--border-highlight);
            box-shadow: 0 12px 32px -8px rgba(37, 99, 235, 0.2);
            background: var(--bg-card-hover);
        }

        .feature-icon-wrapper {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.2), rgba(37, 99, 235, 0.05));
            border: 1px solid rgba(37, 99, 235, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 1.5rem;
        }

        .feature-title {
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            letter-spacing: -0.01em;
        }

        .feature-desc {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.7;
            margin-bottom: 1.25rem;
        }

        .feature-points {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .feature-point {
            font-size: 0.88rem;
            color: #cbd5e1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .feature-point span.check {
            color: var(--success);
            font-weight: 800;
        }

        /* Industry Badges */
        .industry-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-top: 3rem;
        }

        .industry-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.5rem 1rem;
            text-align: center;
            transition: all 0.2s;
        }

        .industry-card:hover {
            border-color: var(--accent);
            background: rgba(37, 99, 235, 0.08);
            transform: translateY(-3px);
        }

        .industry-icon {
            font-size: 2rem;
            margin-bottom: 0.6rem;
        }

        .industry-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
        }

        .industry-sub {
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-top: 0.2rem;
        }

        /* Pricing Section */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
            align-items: stretch;
            max-width: 1100px;
            margin: 0 auto;
        }

        .pricing-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 2.75rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: all 0.3s;
        }

        .pricing-card.featured {
            border-color: var(--primary);
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.12) 0%, rgba(15, 23, 42, 0.85) 100%);
            box-shadow: 0 20px 50px -10px rgba(37, 99, 235, 0.3);
            transform: scale(1.03);
        }

        @media (max-width: 900px) {
            .pricing-card.featured {
                transform: none;
            }
        }

        .featured-badge {
            position: absolute;
            top: -14px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(90deg, #2563eb, #10b981);
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0.35rem 1rem;
            border-radius: 100px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }

        .plan-header {
            margin-bottom: 2rem;
        }

        .plan-name {
            font-size: 1.35rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .plan-desc {
            font-size: 0.88rem;
            color: var(--text-muted);
            min-height: 42px;
        }

        .plan-price-box {
            display: flex;
            align-items: baseline;
            gap: 0.35rem;
            margin: 1.5rem 0;
        }

        .plan-currency {
            font-size: 1.5rem;
            font-weight: 800;
            color: #ffffff;
        }

        .plan-amount {
            font-size: 3rem;
            font-weight: 900;
            color: #ffffff;
            line-height: 1;
        }

        .plan-interval {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .plan-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            margin-bottom: 2.25rem;
            border-top: 1px solid var(--border);
            padding-top: 1.5rem;
        }

        .plan-feature-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-size: 0.92rem;
            color: #e2e8f0;
        }

        .plan-feature-item span.check {
            color: var(--success);
            font-weight: 800;
            font-size: 1.1rem;
        }

        /* Testimonials */
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
        }

        .testimonial-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .testimonial-quote {
            font-size: 1rem;
            color: #cbd5e1;
            font-style: italic;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #10b981);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
            color: #ffffff;
        }

        .author-info h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
        }

        .author-info p {
            font-size: 0.8rem;
            color: var(--text-dim);
        }

        /* FAQs Accordion */
        .faq-accordion {
            max-width: 820px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .faq-item {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .faq-question {
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 1.05rem;
            font-weight: 700;
            color: #ffffff;
            cursor: pointer;
            user-select: none;
        }

        .faq-question:hover {
            color: var(--accent);
        }

        .faq-icon {
            font-size: 1.25rem;
            transition: transform 0.25s;
        }

        .faq-answer {
            padding: 0 1.5rem 1.25rem;
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.7;
            display: none;
        }

        .faq-item.active .faq-answer {
            display: block;
        }

        .faq-item.active .faq-icon {
            transform: rotate(45deg);
        }

        /* Call To Action Banner */
        .cta-banner {
            background: radial-gradient(circle at 50% 50%, rgba(37, 99, 235, 0.3) 0%, rgba(15, 23, 42, 0.95) 100%);
            border: 1px solid rgba(37, 99, 235, 0.4);
            border-radius: 24px;
            padding: 4.5rem 2rem;
            text-align: center;
            box-shadow: 0 25px 60px -15px rgba(37, 99, 235, 0.3);
            margin: 4rem 0;
            position: relative;
            overflow: hidden;
        }

        .cta-banner h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 900;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
        }

        .cta-banner p {
            font-size: 1.15rem;
            color: #cbd5e1;
            max-width: 650px;
            margin: 0 auto 2.25rem;
        }

        /* Footer */
        .footer {
            background: #050811;
            border-top: 1px solid var(--border);
            padding: 4.5rem 0 2rem;
            color: var(--text-dim);
            font-size: 0.88rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr repeat(3, 1fr);
            gap: 3rem;
            margin-bottom: 3.5rem;
        }

        @media (max-width: 850px) {
            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        .footer-brand p {
            margin-top: 1rem;
            max-width: 320px;
            line-height: 1.7;
            color: var(--text-muted);
        }

        .footer-heading {
            font-size: 0.95rem;
            font-weight: 800;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1.25rem;
        }

        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .footer-link {
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-link:hover {
            color: #ffffff;
        }

        .footer-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-copy {
            color: var(--text-dim);
        }

        /* Responsive menu */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: #ffffff;
            font-size: 1.5rem;
            cursor: pointer;
        }

        @media (max-width: 992px) {
            .nav-links {
                display: none;
            }
            .mobile-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>

    <!-- Top Ribbon -->
    <div class="ribbon">
        🚀 Built specifically for Nigerian Supermarkets, Wholesalers & Multi-Branch Chains • <a href="{{ route('saas.register') }}">Start 14-Day Free Trial (No Card Required) →</a>
    </div>

    <!-- Sticky Navigation Header -->
    <header class="navbar">
        <div class="container nav-inner">
            <a href="{{ route('landing') }}" class="brand-logo">
                <div class="logo-symbol">🛡️</div>
                <div class="logo-text">
                    <div class="logo-title">VMARKET<span>POS</span></div>
                    <div class="logo-tag">🇳🇬 NIGERIAN RETAIL CLOUD</div>
                </div>
            </a>

            <ul class="nav-links">
                <li><a href="#solutions" class="nav-link">Nigerian Solutions</a></li>
                <li><a href="#features" class="nav-link">Features</a></li>
                <li><a href="#multibranch" class="nav-link">Multi-Branch</a></li>
                <li><a href="#pricing" class="nav-link">Naira Pricing</a></li>
                <li><a href="#testimonials" class="nav-link">Merchant Reviews</a></li>
                <li><a href="#faqs" class="nav-link">FAQs</a></li>
            </ul>

            <div class="nav-actions">
                <div class="btn-portal-dropdown">
                    <a href="javascript:void(0)" class="btn btn-secondary">
                        🔐 Portals ▾
                    </a>
                    <div class="portal-menu">
                        <a href="{{ route('portal.tenant.login') }}" class="portal-menu-item">
                            <span class="icon">🏢</span>
                            <div>
                                <div>Tenant Owner</div>
                                <small style="color: var(--text-dim); font-size: 0.72rem;">Business admin & analytics</small>
                            </div>
                        </a>
                        <a href="{{ route('portal.tenant_employee.login') }}" class="portal-menu-item">
                            <span class="icon">💼</span>
                            <div>
                                <div>Staff & Cashier</div>
                                <small style="color: var(--text-dim); font-size: 0.72rem;">POS cashier & stockroom</small>
                            </div>
                        </a>
                        <a href="{{ route('portal.super_admin.login') }}" class="portal-menu-item">
                            <span class="icon">🛡️</span>
                            <div>
                                <div>Platform Super-Admin</div>
                                <small style="color: var(--text-dim); font-size: 0.72rem;">Platform control center</small>
                            </div>
                        </a>
                    </div>
                </div>

                <a href="{{ route('saas.register') }}" class="btn btn-primary">
                    Start Free Trial ⚡
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-badge">
                🇳🇬 THE #1 CLOUD POS & INVENTORY SYSTEM FOR NIGERIAN COMMERCE
            </div>

            <h1 class="hero-title">
                Run Your Supermarket & Wholesale Across Nigeria <br />
                <span class="gradient-text">Without Stock Leaks or Missing Money.</span>
            </h1>

            <p class="hero-subtitle">
                Stop writing customer debts in dirty exercise books. Reconcile bank transfers and POS cards instantly, track stock across Lagos, Kano, Onitsha and Abuja in real-time, and know your exact daily profit from your phone.
            </p>

            <div class="hero-cta">
                <a href="{{ route('saas.register') }}" class="btn btn-primary" style="padding: 0.95rem 2rem; font-size: 1.05rem;">
                    🚀 Start 14-Day Free Trial (Naira Pricing)
                </a>
                <a href="{{ route('portal.tenant.login') }}" class="btn btn-secondary" style="padding: 0.95rem 1.8rem; font-size: 1.05rem;">
                    Sign In to Store Portal →
                </a>
            </div>

            <div class="hero-trust">
                <div class="hero-trust-item"><span class="check">✓</span> 100% Online Cloud Sync</div>
                <div class="hero-trust-item"><span class="check">✓</span> Moniepoint, OPay & Bank Transfer Matching</div>
                <div class="hero-trust-item"><span class="check">✓</span> Wholesale Cartons & Retail Pieces</div>
                <div class="hero-trust-item"><span class="check">✓</span> Anti-Theft Cashier Limits</div>
            </div>

            <!-- Hero Live POS Visual Card -->
            <div class="hero-mockup-wrapper">
                <div class="mockup-glow"></div>
                <div class="mockup-card">
                    <div class="mockup-header">
                        <div class="mockup-dots">
                            <div class="mockup-dot red"></div>
                            <div class="mockup-dot yellow"></div>
                            <div class="mockup-dot green"></div>
                        </div>
                        <div class="mockup-header-info">
                            <span>📍 Lekki Mega Branch (Store #01)</span>
                            <span>👤 Cashier: Amina Yusuf</span>
                            <span class="mockup-status-live">ONLINE & SECURE</span>
                        </div>
                    </div>

                    <div class="mockup-body">
                        <!-- Product catalog preview -->
                        <div class="mockup-catalog">
                            <div class="catalog-title">📦 Fast Scan & Touch Grid (Popular Items)</div>
                            <div class="product-grid">
                                <div class="product-item">
                                    <span class="product-badge">GROCERY</span>
                                    <div class="product-name">Royal Stallion Rice 50kg</div>
                                    <div class="product-stock">In Stock: 48 Bags</div>
                                    <div class="product-price">₦85,000</div>
                                </div>
                                <div class="product-item">
                                    <span class="product-badge">COOKING</span>
                                    <div class="product-name">Golden Penny Veg Oil 25L</div>
                                    <div class="product-stock">In Stock: 24 Cans</div>
                                    <div class="product-price">₦52,000</div>
                                </div>
                                <div class="product-item">
                                    <span class="product-badge">FOODSTUFF</span>
                                    <div class="product-name">Dangote White Sugar 50kg</div>
                                    <div class="product-stock">In Stock: 31 Bags</div>
                                    <div class="product-price">₦78,000</div>
                                </div>
                                <div class="product-item">
                                    <span class="product-badge">WHOLESALE</span>
                                    <div class="product-name">Indomie Super Pack (Carton 40)</div>
                                    <div class="product-stock">In Stock: 112 Cartons</div>
                                    <div class="product-price">₦14,500</div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Checkout preview -->
                        <div class="mockup-cart">
                            <div>
                                <div class="cart-title">
                                    <span>🛒 Current Customer Cart (Walk-in)</span>
                                    <span style="color: var(--accent);">3 ITEMS</span>
                                </div>

                                <div class="cart-items">
                                    <div class="cart-row">
                                        <div>
                                            <div class="cart-row-title">Royal Stallion Rice 50kg</div>
                                            <div class="cart-row-qty">Qty: 2 Bags</div>
                                        </div>
                                        <div class="cart-row-price">₦170,000.00</div>
                                    </div>
                                    <div class="cart-row">
                                        <div>
                                            <div class="cart-row-title">Golden Penny Veg Oil 25L</div>
                                            <div class="cart-row-qty">Qty: 1 Can</div>
                                        </div>
                                        <div class="cart-row-price">₦52,000.00</div>
                                    </div>
                                    <div class="cart-row">
                                        <div>
                                            <div class="cart-row-title">Indomie Super Pack (Carton)</div>
                                            <div class="cart-row-qty">Qty: 3 Cartons</div>
                                        </div>
                                        <div class="cart-row-price">₦43,500.00</div>
                                    </div>
                                </div>
                            </div>

                            <div class="cart-summary">
                                <div class="cart-total-row">
                                    <div class="cart-total-label">Total Payable Amount:</div>
                                    <div class="cart-total-amount">₦265,500.00</div>
                                </div>

                                <div class="split-tender-box">
                                    <div class="split-tender-title">
                                        <span>🏦 RECONCILED SPLIT PAYMENT:</span>
                                    </div>
                                    <div class="split-tender-grid">
                                        <div class="split-pill">
                                            💵 CASH
                                            <strong>₦65,500</strong>
                                        </div>
                                        <div class="split-pill">
                                            📱 TRANSFER
                                            <strong>₦150,000</strong>
                                        </div>
                                        <div class="split-pill">
                                            💳 POS CARD
                                            <strong>₦50,000</strong>
                                        </div>
                                    </div>
                                </div>

                                <button class="mockup-checkout-btn">
                                    ✓ Complete Sale & Print Receipt (₦265,500)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Nigerian Realities Section -->
    <section id="solutions" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">TAILORED FOR NIGERIA</div>
                <h2 class="section-title">Why Foreign POS Software Fails in the Nigerian Market</h2>
                <p class="section-subtitle">
                    Foreign software assumes everyone pays with Apple Pay and credit cards. In Nigerian commerce, cash, bank transfer delays, customer credit books, and multiple branches demand a system built specifically for how we trade.
                </p>
            </div>

            <div class="pain-grid">
                <div class="pain-card">
                    <div class="pain-icon">⚠️</div>
                    <div class="pain-problem">THE COMMON PROBLEM</div>
                    <h3 class="pain-title">Fake Bank Transfer Alerts</h3>
                    <p class="pain-desc">
                        Cashiers getting tricked by SMS fake credit alerts or releasing goods before transfer verification.
                    </p>
                    <div style="margin-top: 1.25rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                        <div style="color: var(--success); font-weight: 700; font-size: 0.85rem;">✓ VMARKET FIX:</div>
                        <p style="font-size: 0.88rem; color: #cbd5e1; margin-top: 0.3rem;">
                            Dedicated Split-Tender recording that matches Moniepoint, OPay, and bank payment references with instant cashier audit logs.
                        </p>
                    </div>
                </div>

                <div class="pain-card">
                    <div class="pain-icon">📖</div>
                    <div class="pain-problem">THE COMMON PROBLEM</div>
                    <h3 class="pain-title">Torn & Lost Debt Notebooks</h3>
                    <p class="pain-desc">
                        Regular buyers taking goods on credit ("pay next week"), recorded in physical exercise books that get lost or manipulated.
                    </p>
                    <div style="margin-top: 1.25rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                        <div style="color: var(--success); font-weight: 700; font-size: 0.85rem;">✓ VMARKET FIX:</div>
                        <p style="font-size: 0.88rem; color: #cbd5e1; margin-top: 0.3rem;">
                            Automated Customer Debt Ledger with hard credit limits, balance statements, and WhatsApp payment reminders.
                        </p>
                    </div>
                </div>

                <div class="pain-card">
                    <div class="pain-icon">📦</div>
                    <div class="pain-problem">THE COMMON PROBLEM</div>
                    <h3 class="pain-title">Carton vs Unit Inventory Leaks</h3>
                    <p class="pain-desc">
                        Buying goods in wholesale cartons but selling some as full cartons and some as loose units, leading to missing stock.
                    </p>
                    <div style="margin-top: 1.25rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                        <div style="color: var(--success); font-weight: 700; font-size: 0.85rem;">✓ VMARKET FIX:</div>
                        <p style="font-size: 0.88rem; color: #cbd5e1; margin-top: 0.3rem;">
                            Seamless carton breaking and unit deduction with automated retail and wholesale pricing controls.
                        </p>
                    </div>
                </div>

                <div class="pain-card">
                    <div class="pain-icon">🏢</div>
                    <div class="pain-problem">THE COMMON PROBLEM</div>
                    <h3 class="pain-title">Blind Multi-Branch Operations</h3>
                    <p class="pain-desc">
                        Operating shops in Lekki, Ikeja, or Kano while trusting branch managers on phone calls for stock and cash balances.
                    </p>
                    <div style="margin-top: 1.25rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                        <div style="color: var(--success); font-weight: 700; font-size: 0.85rem;">✓ VMARKET FIX:</div>
                        <p style="font-size: 0.88rem; color: #cbd5e1; margin-top: 0.3rem;">
                            Live multi-branch consolidation. See real-time cash collections, inter-branch waybills, and physical stock from your phone.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Features Section -->
    <section id="features" class="section" style="background: rgba(14, 22, 38, 0.4);">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">ENTERPRISE CLOUD POS</div>
                <h2 class="section-title">Built for Speed, Accuracy & Total Cash Control</h2>
                <p class="section-subtitle">
                    Every feature was engineered to eliminate cashier theft, speed up checkout during rush hours, and keep your business running smoothly.
                </p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon-wrapper">💳</div>
                    <h3 class="feature-title">Dual & Split Tender Reconciliation</h3>
                    <p class="feature-desc">
                        Accept any combination of Cash, POS Card, and Bank Transfer on a single sale. Exact tender matching guarantees cash drawers balance to the last Naira at close of business.
                    </p>
                    <ul class="feature-points">
                        <li class="feature-point"><span class="check">✓</span> Reconcile Moniepoint, OPay, and Bank Transfers</li>
                        <li class="feature-point"><span class="check">✓</span> Automatic change computation for cash tenders</li>
                        <li class="feature-point"><span class="check">✓</span> Prevents underpayment and cashier discrepancies</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-wrapper">🚚</div>
                    <h3 class="feature-title">Multi-Branch & Waybill Transfers</h3>
                    <p class="feature-desc">
                        Move goods seamlessly from central warehouses (e.g. Trade Fair, Idumota, or Dawanau) to retail branches with digital waybills and receiver confirmation.
                    </p>
                    <ul class="feature-points">
                        <li class="feature-point"><span class="check">✓</span> In-transit stock accountability</li>
                        <li class="feature-point"><span class="check">✓</span> Receiver confirmation prevents missing goods</li>
                        <li class="feature-point"><span class="check">✓</span> Recall transfer controls for administrative safety</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-wrapper">🔒</div>
                    <h3 class="feature-title">Anti-Theft Role Boundaries</h3>
                    <p class="feature-desc">
                        Cashiers cannot alter catalog prices, delete historical transactions, or tamper with customer debt. Database prices are 100% authoritative and locked.
                    </p>
                    <ul class="feature-points">
                        <li class="feature-point"><span class="check">✓</span> Cashier cannot modify item selling price</li>
                        <li class="feature-point"><span class="check">✓</span> Append-only audit trail logs every stock deduction</li>
                        <li class="feature-point"><span class="check">✓</span> Prevents backdoor stock tampering</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-wrapper">📒</div>
                    <h3 class="feature-title">Customer "Pay-Later" Debt Ledger</h3>
                    <p class="feature-desc">
                        Give trusted customers credit without losing track. Set strict credit limits per customer so cashiers cannot over-credit without manager approval.
                    </p>
                    <ul class="feature-points">
                        <li class="feature-point"><span class="check">✓</span> Automatic balance tracking with debt history</li>
                        <li class="feature-point"><span class="check">✓</span> Customer credit ceiling warnings</li>
                        <li class="feature-point"><span class="check">✓</span> Partial and installment debt repayments</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-wrapper">⚡</div>
                    <h3 class="feature-title">Fast Barcode & Touch Screen POS</h3>
                    <p class="feature-desc">
                        Handle heavy weekend and holiday rush without lagging. Works with standard USB/Bluetooth barcode scanners, touchscreen monitors, iPads, and Android tablets.
                    </p>
                    <ul class="feature-points">
                        <li class="feature-point"><span class="check">✓</span> Sub-second barcode scanner response</li>
                        <li class="feature-point"><span class="check">✓</span> Visual touch categories for fast items</li>
                        <li class="feature-point"><span class="check">✓</span> Zero lag during peak shopping hours</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-wrapper">🧾</div>
                    <h3 class="feature-title">Thermal Print & WhatsApp Receipts</h3>
                    <p class="feature-desc">
                        Print professional 58mm/80mm thermal receipts or send instant electronic receipts directly to your customer's WhatsApp with one click.
                    </p>
                    <ul class="feature-points">
                        <li class="feature-point"><span class="check">✓</span> Compatible with all ESC/POS thermal printers</li>
                        <li class="feature-point"><span class="check">✓</span> Branded paperless WhatsApp invoices</li>
                        <li class="feature-point"><span class="check">✓</span> Saves receipt paper costs for online orders</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Industries Section -->
    <section id="multibranch" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">INDUSTRY EXCELLENCE</div>
                <h2 class="section-title">Powering Nigerian Retail Across All Sectors</h2>
                <p class="section-subtitle">
                    Whether you operate a neighborhood mini-mart or a nationwide distribution network, VMarket scales effortlessly.
                </p>
            </div>

            <div class="industry-strip">
                <div class="industry-card">
                    <div class="industry-icon">🛒</div>
                    <div class="industry-name">Supermarkets & Marts</div>
                    <div class="industry-sub">Fast checkout & barcode scan</div>
                </div>
                <div class="industry-card">
                    <div class="industry-icon">🌾</div>
                    <div class="industry-name">Grain & Foodstuff Depots</div>
                    <div class="industry-sub">Wholesale bags, cartons & debt</div>
                </div>
                <div class="industry-card">
                    <div class="industry-icon">💊</div>
                    <div class="industry-name">Pharmacies & Chemists</div>
                    <div class="industry-sub">Expiry tracking & item batches</div>
                </div>
                <div class="industry-card">
                    <div class="industry-icon">👗</div>
                    <div class="industry-name">Fashion & Boutiques</div>
                    <div class="industry-sub">Sizes, colors & variants</div>
                </div>
                <div class="industry-card">
                    <div class="industry-icon">📱</div>
                    <div class="industry-name">Phones & Gadgets</div>
                    <div class="industry-sub">IMEI & serial number control</div>
                </div>
                <div class="industry-card">
                    <div class="industry-icon">🏗️</div>
                    <div class="industry-name">Hardware & Materials</div>
                    <div class="industry-sub">Bulk dispatch & contractor debt</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="section" style="background: rgba(14, 22, 38, 0.4);">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">SIMPLE NAIRA PRICING</div>
                <h2 class="section-title">Transparent Plans Built for Nigerian Businesses</h2>
                <p class="section-subtitle">
                    No hidden implementation fees. No foreign exchange surprises. Start with a 14-day free trial on any plan.
                </p>
            </div>

            <div class="pricing-grid">
                <!-- Starter Plan -->
                <div class="pricing-card">
                    <div class="plan-header">
                        <h3 class="plan-name">{{ $plans['basic']['name'] ?? 'Starter Plan' }}</h3>
                        <p class="plan-desc">Perfect for single-location supermarkets, neighborhood stores, and boutiques.</p>
                        <div class="plan-price-box">
                            <span class="plan-currency">₦</span>
                            <span class="plan-amount">{{ number_format($plans['basic']['price_monthly'] ?? 15000) }}</span>
                            <span class="plan-interval">/ month</span>
                        </div>
                    </div>

                    <ul class="plan-features">
                        <li class="plan-feature-item"><span class="check">✓</span> <strong>1 Branch Location</strong></li>
                        <li class="plan-feature-item"><span class="check">✓</span> Up to <strong>3 Staff & Cashiers</strong></li>
                        <li class="plan-feature-item"><span class="check">✓</span> Full Point of Sale & Touchscreen</li>
                        <li class="plan-feature-item"><span class="check">✓</span> Reconciled Cash, Transfer & Card</li>
                        <li class="plan-feature-item"><span class="check">✓</span> Customer Debt Ledger & WhatsApp</li>
                        <li class="plan-feature-item"><span class="check">✓</span> Thermal Receipt Printing (58/80mm)</li>
                    </ul>

                    <a href="{{ route('saas.register') }}?plan=basic" class="btn btn-secondary" style="width: 100%;">
                        Start 14-Day Free Trial
                    </a>
                </div>

                <!-- Growth Plan (Featured) -->
                <div class="pricing-card featured">
                    <div class="featured-badge">MOST POPULAR IN NIGERIA</div>
                    <div class="plan-header">
                        <h3 class="plan-name">{{ $plans['pro']['name'] ?? 'Professional Growth' }}</h3>
                        <p class="plan-desc">Designed for expanding retail stores, wholesale depots, and multi-branch chains.</p>
                        <div class="plan-price-box">
                            <span class="plan-currency">₦</span>
                            <span class="plan-amount">{{ number_format($plans['pro']['price_monthly'] ?? 35000) }}</span>
                            <span class="plan-interval">/ month</span>
                        </div>
                    </div>

                    <ul class="plan-features">
                        <li class="plan-feature-item"><span class="check">✓</span> Up to <strong>5 Branch Locations</strong></li>
                        <li class="plan-feature-item"><span class="check">✓</span> Up to <strong>15 Staff & Cashiers</strong></li>
                        <li class="plan-feature-item"><span class="check">✓</span> <strong>Inter-Branch Stock Transfers</strong></li>
                        <li class="plan-feature-item"><span class="check">✓</span> <strong>Wholesale Module & Waybills</strong></li>
                        <li class="plan-feature-item"><span class="check">✓</span> Daily Shift Cash Reconciliation</li>
                        <li class="plan-feature-item"><span class="check">✓</span> Consolidated Real-time Owner Analytics</li>
                    </ul>

                    <a href="{{ route('saas.register') }}?plan=pro" class="btn btn-primary" style="width: 100%;">
                        Start 14-Day Free Trial
                    </a>
                </div>

                <!-- Enterprise Plan -->
                <div class="pricing-card">
                    <div class="plan-header">
                        <h3 class="plan-name">{{ $plans['enterprise']['name'] ?? 'Enterprise Multi-Branch' }}</h3>
                        <p class="plan-desc">For large supermarket chains, major distribution hubs, and nationwide brands.</p>
                        <div class="plan-price-box">
                            <span class="plan-currency">₦</span>
                            <span class="plan-amount">{{ number_format($plans['enterprise']['price_monthly'] ?? 75000) }}</span>
                            <span class="plan-interval">/ month</span>
                        </div>
                    </div>

                    <ul class="plan-features">
                        <li class="plan-feature-item"><span class="check">✓</span> <strong>Unlimited Branch Locations</strong></li>
                        <li class="plan-feature-item"><span class="check">✓</span> <strong>Unlimited Staff Accounts</strong></li>
                        <li class="plan-feature-item"><span class="check">✓</span> Dedicated Account Manager</li>
                        <li class="plan-feature-item"><span class="check">✓</span> Priority WhatsApp VIP Support</li>
                        <li class="plan-feature-item"><span class="check">✓</span> Automated Cloud Database Backups</li>
                        <li class="plan-feature-item"><span class="check">✓</span> Custom CSV / Excel Export Feeds</li>
                    </ul>

                    <a href="{{ route('saas.register') }}?plan=enterprise" class="btn btn-secondary" style="width: 100%;">
                        Start 14-Day Free Trial
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="section">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">TESTED ACROSS NIGERIA</div>
                <h2 class="section-title">What Nigerian Store Owners Say</h2>
                <p class="section-subtitle">
                    Real merchants who switched from paper books and clunky desktop software to VMarket POS.
                </p>
            </div>

            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <p class="testimonial-quote">
                        "The bank transfer reconciliation alone saved my supermarket over ₦1.8M during Christmas rush. Cashiers can no longer claim they saw an alert when money didn't hit our account. Every payment is verified before checkout."
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">NA</div>
                        <div class="author-info">
                            <h4>Mrs. Ngozi Adeleke</h4>
                            <p>Prime Choice Supermarket, Lekki Phase 1, Lagos</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="testimonial-quote">
                        "Before VMarket, our staff in Kano and Abuja kept giving different stories about missing bags of rice during inter-warehouse transfers. Now every waybill must be confirmed digitally. Our stock losses dropped to zero."
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">SB</div>
                        <div class="author-info">
                            <h4>Alhaji Sani Bello</h4>
                            <p>Bello Grain & Wholesale Hub, Dawanau Market, Kano</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <p class="testimonial-quote">
                        "Managing 4 stores across Onitsha Main Market and Asaba used to give me high blood pressure. With VMarket on my tablet, I see every sale, debtor payment, and cash balance as it happens from my house."
                    </p>
                    <div class="testimonial-author">
                        <div class="author-avatar">KO</div>
                        <div class="author-info">
                            <h4>Chief Kenneth Okafor</h4>
                            <p>Chisco Hardware & Wholesale, Onitsha</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQs Section -->
    <section id="faqs" class="section" style="background: rgba(14, 22, 38, 0.4);">
        <div class="container">
            <div class="section-header">
                <div class="section-tag">FREQUENTLY ASKED QUESTIONS</div>
                <h2 class="section-title">Got Questions? We Have Answers.</h2>
                <p class="section-subtitle">
                    Everything you need to know about getting started with VMarket POS in your store.
                </p>
            </div>

            <div class="faq-accordion">
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Does VMarket work with my existing thermal receipt printer & scanner?</span>
                        <span class="faq-icon">+</span>
                    </div>
                    <div class="faq-answer">
                        Yes! VMarket POS works seamlessly with standard 58mm and 80mm thermal receipt printers (Xprinter, Epson, POS-58, etc.) via USB, Bluetooth, or LAN, as well as standard USB and wireless barcode scanners. You do not need to buy expensive proprietary hardware.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Can cashiers steal or change product selling prices?</span>
                        <span class="faq-icon">+</span>
                    </div>
                    <div class="faq-answer">
                        No. Cashiers cannot alter catalog prices or delete completed sales. The server strictly enforces authoritative database pricing. Furthermore, cashiers are locked to their assigned store branch and cannot access administrative settings or financial margins.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>How does the 14-day free trial work?</span>
                        <span class="faq-icon">+</span>
                    </div>
                    <div class="faq-answer">
                        You can sign up in less than 60 seconds with just your business name and email. No debit card is required. You get full access to all features during the 14 days. If you love it, you can subscribe in Naira using your preferred plan.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Can I track customer credit / debt (Ajo & Pay Later)?</span>
                        <span class="faq-icon">+</span>
                    </div>
                    <div class="faq-answer">
                        Yes! VMarket includes an automated Customer Debt Book. When a customer pays partially or takes goods on approved credit, their balance updates automatically. You can set maximum credit ceilings and record partial debt repayments with full receipt printing.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Can I monitor my branches from my phone when I travel?</span>
                        <span class="faq-icon">+</span>
                    </div>
                    <div class="faq-answer">
                        Yes! Because VMarket is 100% cloud-based, you can log in as a Tenant Admin from your iPhone, Android phone, tablet, or laptop from anywhere in Nigeria or abroad and see real-time sales, cash drawer balances, and inventory levels.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bottom CTA Banner -->
    <div class="container">
        <div class="cta-banner">
            <h2>Ready to Modernize Your Nigerian Retail Business?</h2>
            <p>Join smart supermarket owners and wholesale merchants across Lagos, Kano, Abuja, and Port Harcourt running their stores on VMarket POS.</p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="{{ route('saas.register') }}" class="btn btn-primary" style="padding: 1rem 2.2rem; font-size: 1.1rem;">
                    🚀 Create Your Store in 60 Seconds
                </a>
                <a href="{{ route('portal.tenant.login') }}" class="btn btn-secondary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                    Sign In to Portal →
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="brand-logo">
                        <div class="logo-symbol">🛡️</div>
                        <div class="logo-text">
                            <div class="logo-title">VMARKET<span>POS</span></div>
                            <div class="logo-tag">🇳🇬 NIGERIAN COMMERCE CLOUD</div>
                        </div>
                    </div>
                    <p>
                        The high-performance cloud point of sale and inventory management platform designed specifically for Nigerian supermarkets, wholesale commodity depots, and retail chains.
                    </p>
                </div>

                <div>
                    <h4 class="footer-heading">Access Portals</h4>
                    <ul class="footer-links">
                        <li><a href="{{ route('portal.tenant.login') }}" class="footer-link">🏢 Tenant Owner Login</a></li>
                        <li><a href="{{ route('portal.tenant_employee.login') }}" class="footer-link">💼 Staff & Cashier Login</a></li>
                        <li><a href="{{ route('portal.super_admin.login') }}" class="footer-link">🛡️ Super-Admin Portal</a></li>
                        <li><a href="{{ route('portal.super_admin_employee.login') }}" class="footer-link">👥 Platform Staff Portal</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="footer-heading">Product & Solutions</h4>
                    <ul class="footer-links">
                        <li><a href="#features" class="footer-link">Split-Tender Reconciliation</a></li>
                        <li><a href="#multibranch" class="footer-link">Multi-Branch Transfers</a></li>
                        <li><a href="#features" class="footer-link">Customer Debt Book</a></li>
                        <li><a href="#pricing" class="footer-link">Naira Pricing Plans</a></li>
                        <li><a href="{{ route('saas.register') }}" class="footer-link">Start 14-Day Trial</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="footer-heading">Support & Contact</h4>
                    <ul class="footer-links">
                        <li style="color: var(--text-muted);">📍 Victoria Island, Lagos, Nigeria</li>
                        <li style="color: var(--text-muted);">✉️ info@vmarketpos.com</li>
                        <li style="color: var(--text-muted);">📞 +234 800 000 0000</li>
                        <li><a href="{{ route('saas.register') }}" class="footer-link" style="color: var(--success); font-weight: 700;">💬 WhatsApp Support</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="footer-copy">
                    &copy; {{ date('Y') }} VMarket POS Platform. Built for Nigerian Retailers & Wholesalers. All rights reserved.
                </div>
                <div style="display: flex; gap: 1.5rem;">
                    <a href="{{ route('landing') }}" class="footer-link">Privacy Policy</a>
                    <a href="{{ route('landing') }}" class="footer-link">Terms of Service</a>
                    <a href="{{ route('portal.tenant.login') }}" class="footer-link">Merchant Portal</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Interactive FAQ Script -->
    <script>
        document.querySelectorAll('.faq-question').forEach(button => {
            button.addEventListener('click', () => {
                const item = button.parentElement;
                item.classList.toggle('active');
            });
        });
    </script>
</body>
</html>
