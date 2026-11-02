<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Hysam Ventures') – Inventory & POS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- PWA & Mobile Web Capabilities -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#0c2340">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Victorious POS">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192x192.png') }}">
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
            background: rgba(17, 24, 39, 0.88);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            position: sticky;
            top: 0;
            z-index: 40;
            min-height: 64px;
        }

        .topbar-left-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        #liveClockWidget {
            background: rgba(31, 41, 55, 0.75);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.4rem 0.85rem;
            font-size: 0.83rem;
            font-weight: 700;
            color: #93c5fd;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            user-select: none;
        }

        #liveClockWidget .clock-divider {
            color: #4b5563;
        }

        #liveClockWidget #headerTime {
            color: #4ade80;
            font-variant-numeric: tabular-nums;
        }

        .observer-pill {
            background: rgba(234, 179, 8, 0.15);
            border: 1px solid rgba(234, 179, 8, 0.4);
            border-radius: 10px;
            padding: 0.4rem 0.85rem;
            font-size: 0.82rem;
            font-weight: 800;
            color: #facc15;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            white-space: nowrap;
        }

        /* 🏬 Topbar Branch Context Switcher Styles */
        .topbar-branch-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex: 1;
            max-width: 440px;
            margin: 0 0.5rem;
        }

        .topbar-right-wrap {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            flex-shrink: 0;
        }

        .topbar-operator-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(31, 41, 59, 0.7);
            border: 1px solid rgba(75, 85, 99, 0.45);
            border-radius: 10px;
            padding: 0.4rem 0.75rem;
            font-size: 0.82rem;
            color: #9ca3af;
            white-space: nowrap;
            user-select: none;
        }

        .topbar-operator-badge .op-icon {
            font-size: 0.85rem;
        }

        .topbar-operator-badge .op-label {
            color: #94a3b8;
        }

        .topbar-operator-badge .op-name {
            color: #f3f4f6;
            font-weight: 700;
        }

        .topbar-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.38rem;
            padding: 0.42rem 0.8rem;
            font-size: 0.82rem;
            font-weight: 700;
            border-radius: 10px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            white-space: nowrap;
            line-height: 1.25;
            text-decoration: none;
            border: 1px solid transparent;
        }

        .topbar-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .topbar-btn-calc {
            background: rgba(31, 41, 55, 0.9);
            border-color: #4b5563;
            color: #f3f4f6;
        }

        .topbar-btn-calc:hover {
            background: rgba(55, 65, 81, 0.95);
            border-color: #6b7280;
            color: #ffffff;
        }

        .topbar-btn-password {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.4);
            color: #93c5fd;
        }

        .topbar-btn-password:hover {
            background: rgba(59, 130, 246, 0.28);
            border-color: #3b82f6;
            color: #ffffff;
        }

        .topbar-btn-logout {
            background: rgba(220, 38, 38, 0.15);
            border-color: rgba(220, 38, 38, 0.4);
            color: #fca5a5;
        }

        .topbar-btn-logout:hover {
            background: rgba(220, 38, 38, 0.28);
            border-color: #ef4444;
            color: #ffffff;
        }

        .btn-text-mobile {
            display: none;
        }

        .btn-text-desktop {
            display: inline;
        }

        /* 🧮 POS Calculator Modal Styles */
        .calc-modal-card {
            max-width: 370px !important;
            width: 100%;
            padding: 1.25rem !important;
            background: #0f172a !important;
            border: 1.5px solid #334155 !important;
            border-radius: 20px !important;
            box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.85), 0 0 0 1px rgba(255, 255, 255, 0.05) !important;
            animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .calc-close-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            width: 30px;
            height: 30px;
            color: #94a3b8;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .calc-close-btn:hover {
            background: rgba(220, 38, 38, 0.2);
            color: #fca5a5;
            border-color: rgba(220, 38, 38, 0.4);
        }

        .calc-display-box {
            background: #030712;
            border: 1.5px solid #1e293b;
            border-radius: 14px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.7);
        }

        .calc-history {
            font-size: 0.8rem;
            color: #64748b;
            min-height: 1.2rem;
            text-align: right;
            overflow-x: auto;
            white-space: nowrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            letter-spacing: 0.04em;
        }

        .calc-screen {
            font-size: 1.85rem;
            font-weight: 800;
            text-align: right;
            color: #34d399;
            overflow-x: auto;
            white-space: nowrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            line-height: 1.25;
            margin-top: 0.2rem;
        }

        .calc-keypad-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.55rem;
        }

        .calc-key {
            border: none;
            border-radius: 12px;
            padding: 0.8rem 0.5rem;
            font-size: 1.15rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.12s cubic-bezier(0.4, 0, 0.2, 1);
            user-select: none;
            font-family: inherit;
        }

        .calc-key:active {
            transform: scale(0.94);
        }

        .calc-key-num {
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        }

        .calc-key-num:hover {
            background: #334155;
            color: #ffffff;
        }

        .calc-key-op {
            background: rgba(37, 99, 235, 0.18);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.35);
        }

        .calc-key-op:hover {
            background: rgba(37, 99, 235, 0.35);
            color: #ffffff;
            border-color: #3b82f6;
        }

        .calc-key-clear {
            background: rgba(220, 38, 38, 0.18);
            color: #f87171;
            border: 1px solid rgba(220, 38, 38, 0.35);
        }

        .calc-key-clear:hover {
            background: #dc2626;
            color: #ffffff;
        }

        .calc-key-del {
            background: rgba(217, 119, 6, 0.18);
            color: #fbbf24;
            border: 1px solid rgba(217, 119, 6, 0.35);
        }

        .calc-key-del:hover {
            background: #d97706;
            color: #ffffff;
        }

        .calc-key-equals {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #ffffff;
            border: 1px solid #22c55e;
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.4);
            font-weight: 800;
        }

        .calc-key-equals:hover {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            box-shadow: 0 6px 18px rgba(34, 197, 94, 0.5);
        }

        .branch-selector-dropdown-wrapper {
            position: relative;
        }

        .branch-selector-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.85), rgba(15, 23, 42, 0.95));
            border: 1px solid rgba(59, 130, 246, 0.35);
            border-radius: 9999px;
            padding: 0.4rem 0.95rem 0.4rem 0.65rem;
            color: #f8fafc;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.08);
            white-space: nowrap;
        }

        .branch-selector-btn:hover {
            border-color: #3b82f6;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.25), rgba(15, 23, 42, 0.98));
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.3);
            transform: translateY(-1px);
        }

        .branch-selector-dropdown-wrapper.open .branch-selector-btn {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
            background: rgba(30, 41, 59, 0.95);
        }

        .branch-selector-btn .b-pill-indicator {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.18);
            border: 1px solid rgba(59, 130, 246, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            flex-shrink: 0;
        }

        .branch-selector-btn .b-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
            line-height: 1.15;
        }

        .branch-selector-btn .b-label {
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #93c5fd;
        }

        .branch-selector-btn .b-name {
            font-size: 0.83rem;
            font-weight: 700;
            color: #ffffff;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .branch-selector-btn .b-chevron {
            font-size: 0.72rem;
            color: #94a3b8;
            margin-left: 0.2rem;
            transition: transform 0.2s ease;
        }

        .branch-selector-dropdown-wrapper.open .b-chevron {
            transform: rotate(180deg);
            color: #3b82f6;
        }

        .branch-dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%);
            width: 320px;
            max-width: 92vw;
            background: #0f172a;
            border: 1px solid rgba(59, 130, 246, 0.25);
            border-radius: 18px;
            box-shadow: 0 20px 45px -8px rgba(0, 0, 0, 0.85), 0 0 0 1px rgba(255, 255, 255, 0.05);
            z-index: 1000;
            padding: 0.6rem;
            animation: dropdownFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .branch-selector-dropdown-wrapper.open .branch-dropdown-menu {
            display: block;
        }

        @keyframes dropdownFadeIn {
            from { opacity: 0; transform: translate(-50%, -6px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }

        .branch-dropdown-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.55rem 0.75rem 0.5rem;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            margin-bottom: 0.4rem;
        }

        .branch-dropdown-header .count-pill {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            padding: 0.15rem 0.5rem;
            border-radius: 99px;
            font-size: 0.68rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .branch-dropdown-list {
            max-height: 320px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .branch-dropdown-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.06);
            margin: 0.25rem 0.4rem;
        }

        .branch-option-btn {
            width: 100%;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid transparent;
            border-radius: 12px;
            padding: 0.65rem 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-align: left;
            cursor: pointer;
            transition: all 0.15s ease;
            color: #f1f5f9;
            font-family: inherit;
        }

        .branch-option-btn:hover {
            background: rgba(59, 130, 246, 0.12);
            border-color: rgba(59, 130, 246, 0.3);
            transform: translateX(2px);
        }

        .branch-option-btn.active {
            background: rgba(37, 99, 235, 0.2);
            border-color: #3b82f6;
        }

        .branch-option-btn .bo-icon {
            font-size: 1.25rem;
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .branch-option-btn .bo-content {
            flex: 1;
            min-width: 0;
        }

        .branch-option-btn .bo-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .branch-option-btn .bo-subtitle {
            font-size: 0.72rem;
            color: #94a3b8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .branch-option-btn .bo-badge {
            background: #22c55e;
            color: #ffffff;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 0.2rem 0.5rem;
            border-radius: 99px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            flex-shrink: 0;
        }

        .branch-badge-locked {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background: rgba(30, 41, 59, 0.85);
            border: 1px solid rgba(100, 116, 139, 0.3);
            border-radius: 9999px;
            padding: 0.4rem 0.95rem 0.4rem 0.65rem;
            font-size: 0.84rem;
            font-weight: 700;
            color: #cbd5e1;
            white-space: nowrap;
        }

        .branch-badge-locked .b-pill-indicator {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(148, 163, 184, 0.15);
            border: 1px solid rgba(148, 163, 184, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.88rem;
            flex-shrink: 0;
        }

        .branch-badge-locked .b-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
            line-height: 1.15;
        }

        .branch-badge-locked .b-label {
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
        }

        .branch-badge-locked .b-name {
            font-size: 0.83rem;
            font-weight: 700;
            color: #e2e8f0;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .branch-badge-locked .b-lock-tag {
            background: rgba(234, 179, 8, 0.15);
            color: #facc15;
            border: 1px solid rgba(234, 179, 8, 0.3);
            font-size: 0.65rem;
            padding: 0.15rem 0.45rem;
            border-radius: 99px;
            font-weight: 800;
            margin-left: 0.2rem;
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

        /* Mobile Navigation Drawer & Backdrop */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            z-index: 998;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-backdrop.active {
            display: block;
            opacity: 1;
        }

        .mobile-menu-btn {
            display: none;
            background: rgba(31, 41, 55, 0.85);
            border: 1px solid var(--border);
            color: #f9fafb;
            border-radius: 12px;
            width: 42px;
            height: 42px;
            font-size: 1.4rem;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .mobile-menu-btn:hover, .mobile-menu-btn:active {
            background: rgba(55, 65, 81, 0.95);
            border-color: #60a5fa;
            transform: scale(0.96);
        }

        .mobile-close-sidebar {
            display: none;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            color: var(--text-muted);
            font-size: 1.1rem;
            cursor: pointer;
            margin-left: auto;
            width: 32px;
            height: 32px;
            align-items: center;
            justify-content: center;
            line-height: 1;
            transition: all 0.2s;
        }
        .mobile-close-sidebar:hover {
            color: #fff;
            background: rgba(239, 68, 68, 0.2);
            border-color: #ef4444;
        }

        /* Filter Form Active State */
        form.filtering-active {
            opacity: 0.65;
            pointer-events: none;
            transition: opacity 0.2s;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
                z-index: 999;
                box-shadow: 10px 0 50px rgba(0, 0, 0, 0.8);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .mobile-menu-btn {
                display: inline-flex;
            }
            .mobile-close-sidebar {
                display: inline-flex;
            }
            .main-wrapper {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100vw;
                overflow-x: hidden;
            }
            .topbar {
                padding: 0.75rem 1rem;
                gap: 0.75rem;
            }
            .container {
                padding: 1.25rem 1rem;
                max-width: 100vw;
            }
        }

        @media (max-width: 768px) {
            .topbar {
                flex-direction: column;
                align-items: stretch;
                gap: 0.65rem;
                padding: 0.65rem 0.85rem;
                min-height: auto;
            }
            .topbar-left-wrap {
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                gap: 0.5rem;
            }
            .topbar-left-wrap .mobile-menu-btn {
                flex-shrink: 0;
            }
            #liveClockWidget {
                font-size: 0.76rem;
                padding: 0.32rem 0.6rem;
                border-radius: 8px;
            }
            .topbar-branch-wrap {
                width: 100%;
                max-width: 100%;
                margin: 0;
                order: 2;
                justify-content: center;
            }
            .branch-selector-btn, .branch-badge-locked {
                width: 100%;
                justify-content: center;
                padding: 0.45rem 0.85rem;
                border-radius: 12px;
            }
            .branch-dropdown-menu {
                width: 100%;
                left: 0;
                right: 0;
                transform: none !important;
                max-width: 100%;
            }
            .topbar-right-wrap {
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                gap: 0.45rem;
                order: 3;
                flex-wrap: nowrap;
            }
            .topbar-operator-badge {
                flex: 1.1;
                min-width: 0;
                padding: 0.38rem 0.55rem;
                font-size: 0.76rem;
                justify-content: flex-start;
                overflow: hidden;
            }
            .topbar-operator-badge .op-label {
                display: none;
            }
            .topbar-operator-badge .op-name {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .topbar-btn {
                padding: 0.4rem 0.55rem;
                font-size: 0.78rem;
                justify-content: center;
                flex: 1;
            }
            .btn-text-desktop {
                display: none;
            }
            .btn-text-mobile {
                display: inline;
            }
            .container {
                padding: 1rem 0.6rem;
            }
            .card {
                padding: 1rem;
                border-radius: 14px;
            }
            .table-wrap, .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                width: 100%;
                display: block;
            }
            /* Eliminate iOS auto-zoom on input focus */
            input, select, textarea {
                font-size: 16px !important;
            }
            .btn {
                min-height: 44px;
            }
            .grid-2, .grid-3, .grid-4 {
                grid-template-columns: 1fr;
            }
            .modal-backdrop, .modal-overlay {
                padding: 0.5rem;
                align-items: flex-end !important;
            }
            .modal-backdrop#modalCalculator {
                align-items: center !important;
            }
        }

        /* Mobile Bottom Navigation Bar (App Experience) */
        .mobile-bottom-nav {
            display: none;
        }
        .pwa-install-banner {
            display: none;
        }

        @media (max-width: 768px) {
            body {
                padding-bottom: 76px;
            }
            .container {
                padding-bottom: 5.5rem !important;
            }
            .mobile-bottom-nav {
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: 64px;
                background: rgba(15, 23, 42, 0.96);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-top: 1px solid rgba(255, 255, 255, 0.08);
                box-shadow: 0 -4px 25px rgba(0, 0, 0, 0.5);
                z-index: 900;
                align-items: center;
                justify-content: space-around;
                padding-bottom: max(6px, env(safe-area-inset-bottom));
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
            .mobile-nav-tab {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 0.2rem;
                text-decoration: none;
                color: #94a3b8;
                padding: 0.35rem 0.25rem;
                border-radius: 10px;
                transition: all 0.2s ease;
                min-height: 48px;
                position: relative;
            }
            .mobile-nav-tab .tab-icon {
                font-size: 1.25rem;
                line-height: 1;
                transition: transform 0.2s ease;
            }
            .mobile-nav-tab .tab-label {
                font-size: 0.68rem;
                font-weight: 700;
                letter-spacing: 0.01em;
                line-height: 1;
            }
            .mobile-nav-tab:active {
                transform: scale(0.92);
            }
            .mobile-nav-tab.active {
                color: #38bdf8;
            }
            .mobile-nav-tab.active .tab-icon {
                transform: translateY(-2px);
                filter: drop-shadow(0 2px 8px rgba(56, 189, 248, 0.4));
            }
            .mobile-nav-tab.active::after {
                content: '';
                position: absolute;
                bottom: 2px;
                width: 18px;
                height: 3px;
                background: #38bdf8;
                border-radius: 99px;
                box-shadow: 0 0 10px rgba(56, 189, 248, 0.8);
            }
            /* Quick POS Action Accent */
            .mobile-nav-tab.pos-accent {
                color: #f8fafc;
            }
            .mobile-nav-tab.pos-accent .tab-icon-wrap {
                width: 38px;
                height: 38px;
                background: linear-gradient(135deg, #2563eb, #3b82f6);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
                margin-top: -8px;
            }
            .mobile-nav-tab.pos-accent .tab-label {
                color: #60a5fa;
                font-weight: 800;
            }

            /* PWA Install Banner */
            .pwa-install-banner {
                position: fixed;
                bottom: 74px;
                left: 0.75rem;
                right: 0.75rem;
                background: linear-gradient(135deg, #0c2340, #1e3a8a);
                border: 1px solid rgba(56, 189, 248, 0.35);
                border-radius: 16px;
                padding: 0.85rem 1rem;
                box-shadow: 0 12px 35px rgba(0, 0, 0, 0.6);
                z-index: 890;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                animation: pwaSlideUp 0.35s ease-out;
            }
            @keyframes pwaSlideUp {
                from { transform: translateY(30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            .pwa-banner-content {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                flex: 1;
                min-width: 0;
            }
            .pwa-banner-icon {
                width: 38px;
                height: 38px;
                border-radius: 10px;
                background: #0b0f19;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.25rem;
                flex-shrink: 0;
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            .pwa-banner-text h4 {
                font-size: 0.82rem;
                font-weight: 800;
                color: #f8fafc;
                margin-bottom: 0.1rem;
            }
            .pwa-banner-text p {
                font-size: 0.72rem;
                color: #93c5fd;
                line-height: 1.2;
            }
            .pwa-banner-actions {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                flex-shrink: 0;
            }
            .pwa-btn-install {
                background: #38bdf8;
                color: #0c2340;
                border: none;
                padding: 0.45rem 0.85rem;
                border-radius: 8px;
                font-size: 0.76rem;
                font-weight: 800;
                cursor: pointer;
            }
            .pwa-btn-dismiss {
                background: transparent;
                color: #94a3b8;
                border: none;
                font-size: 1.1rem;
                cursor: pointer;
                padding: 0.25rem;
            }
        }

        /* Password Visibility Toggle */
        .password-field-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .password-field-wrapper input {
            width: 100%;
            padding-right: 2.75rem !important;
        }
        .password-toggle-btn {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-muted, #94a3b8);
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
            z-index: 5;
        }
        .password-toggle-btn:hover {
            color: #f8fafc;
            background: rgba(255, 255, 255, 0.08);
        }

        @media (max-width: 480px) {
            #headerDate, .clock-divider {
                display: none;
            }
            .topbar-operator-badge {
                display: none;
            }
            .topbar-btn {
                flex: 1;
                font-size: 0.8rem;
                padding: 0.45rem 0.5rem;
            }
        }

        @keyframes invalidPulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.8); border-color: #ef4444; }
            50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); border-color: #dc2626; }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.8); border-color: #ef4444; }
        }
        .invalid-highlight {
            animation: invalidPulse 1.2s ease-out 2 !important;
            border-color: #ef4444 !important;
        }
    </style>

    @stack('styles')
</head>
<body>

    <!-- Mobile Sidebar Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileSidebar(false)"></div>

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        @php
            $activeTenantId = session('tenant_id', 'default-tenant');
            $tenantModel = \App\Models\Tenant::find($activeTenantId);
            $displayBrandName = ($activeTenantId !== 'default-tenant' && $tenantModel) 
                ? $tenantModel->name 
                : config('saas.platform_name', 'VMARKET POS');

            $globalAuthUser = auth()->user();
            $currentRole = $globalAuthUser->role ?? 'admin';
            $isBranchLocked = $globalAuthUser && $globalAuthUser->isBranchScoped();

            if ($globalAuthUser) {
                if ($isBranchLocked) {
                    $accessibleWarehouses = \App\Models\Warehouse::where('id', $globalAuthUser->warehouse_id)->get();
                    $globalActiveWarehouseId = $globalAuthUser->warehouse_id;
                } else {
                    $accessibleWarehouses = \App\Models\Warehouse::where('is_active', true)->get();
                    $globalActiveWarehouseId = session('active_warehouse_id');
                }
                $globalActiveWarehouse = $globalActiveWarehouseId ? \App\Models\Warehouse::find($globalActiveWarehouseId) : null;
            } else {
                $accessibleWarehouses = collect();
                $globalActiveWarehouse = null;
                $globalActiveWarehouseId = null;
            }
        @endphp

        <div class="sidebar-header">
            <div class="brand-icon">📦</div>
            <div class="brand-text">
                <h1>{{ $displayBrandName }}</h1>
                <p>{{ $activeTenantId === 'default-tenant' ? 'Platform Master Suite' : 'Multi-Branch POS' }}</p>
            </div>
            <button type="button" class="mobile-close-sidebar" onclick="toggleMobileSidebar(false)" aria-label="Close navigation menu">✕</button>
        </div>

        @php
            $currentRole = auth()->user()->role ?? session('user_role') ?? ($authUser->role ?? 'admin');
        @endphp
        <nav class="sidebar-menu">
            <div class="menu-category">Main Operations</div>
            <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span>🏠</span> <span>{{ $currentRole === 'cashier' ? 'My Shift Summary' : ($currentRole === 'storekeeper' ? 'Stock Hub' : 'Dashboard') }}</span>
            </a>

            @if(in_array($currentRole, ['admin', 'manager', 'branch_manager', 'cashier', 'staff', 'sales_officer']))
                <!-- Big POS Button -->
                <a href="{{ route('pos.index') }}" class="nav-item pos-btn {{ request()->routeIs('pos.index') ? 'active' : '' }}">
                    <span>💰</span> <span>Sell Goods (POS)</span>
                </a>
            @endif

            @if(in_array($currentRole, ['admin', 'manager', 'branch_manager', 'storekeeper', 'viewer', 'executive_readonly']))
                <div class="menu-category">Inventory & Stock</div>
                <a href="{{ route('products.index') }}" class="nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}">
                    <span>🛍️</span> <span>Products Catalog</span>
                </a>
                <a href="{{ route('stock.index') }}" class="nav-item {{ request()->routeIs('stock.index') ? 'active' : '' }}">
                    <span>📥</span> <span>Stock In</span>
                </a>
                <a href="{{ route('stock.transfers') }}" class="nav-item {{ request()->routeIs('stock.transfers') ? 'active' : '' }}">
                    <span>🚚</span> <span>Shop Transfers</span>
                </a>
                <a href="{{ route('stock.unsupplied') }}" class="nav-item {{ request()->routeIs('stock.unsupplied') ? 'active' : '' }}">
                    <span>⏳</span> <span>Pending Orders</span>
                </a>
                <a href="{{ route('stock.adjustments') }}" class="nav-item {{ request()->routeIs('stock.adjustments') ? 'active' : '' }}">
                    <span>📉</span> <span>Stock Out & Deductions</span>
                </a>
            @endif

            <div class="menu-category">Ledgers & History</div>
            <a href="{{ route('transactions.index') }}" class="nav-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
                <span>📜</span> <span>{{ $currentRole === 'cashier' ? 'My Sales History' : 'History & Ledgers' }}</span>
            </a>

            @if(in_array($currentRole, ['admin', 'manager', 'branch_manager', 'cashier', 'staff', 'sales_officer']))
                <a href="{{ route('pos.returns') }}" class="nav-item {{ request()->routeIs('pos.returns') ? 'active' : '' }}">
                    <span>🔄</span> <span>Returns & Refunds</span>
                </a>
            @endif

            @if(in_array($currentRole, ['admin', 'manager', 'branch_manager', 'cashier', 'staff', 'sales_officer', 'viewer', 'executive_readonly']))
                <a href="{{ route('debts.index') }}" class="nav-item {{ request()->routeIs('debts.*') ? 'active' : '' }}">
                    <span>💳</span> <span>Debts & Installments</span>
                </a>
            @endif

            @if(in_array($currentRole, ['admin', 'super_admin', 'manager', 'branch_manager', 'owner', 'store_owner', 'viewer', 'executive_readonly']))
                <div class="menu-category">Management & Reports</div>
                @if(in_array($currentRole, ['admin', 'super_admin', 'owner', 'store_owner', 'viewer', 'executive_readonly']))
                    <a href="{{ route('auditor.index') }}" class="nav-item auditor-btn {{ request()->routeIs('auditor.*') ? 'active' : '' }}">
                        <span>🚨</span> <span>Auditor Control Hub</span>
                    </a>
                @endif
                <a href="{{ route('reports.index') }}" class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <span>📊</span> <span>Reports & AI Exports</span>
                </a>
                @if(in_array($currentRole, ['admin', 'super_admin', 'owner', 'store_owner']))
                    <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <span>👥</span> <span>Workers & Roles</span>
                    </a>
                    <a href="{{ route('subscription.index') }}" class="nav-item {{ request()->routeIs('subscription.*') ? 'active' : '' }}" style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="display: flex; align-items: center; gap: 0.6rem;">
                            <span>⭐</span> <span>Subscription &amp; Plan</span>
                        </span>
                        @if(isset($tenantModel) && $tenantModel && $tenantModel->status === 'trial')
                            <span style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); font-size: 9px; font-weight: 800; padding: 1px 6px; border-radius: 4px; text-transform: uppercase;">Trial</span>
                        @elseif(isset($tenantModel) && $tenantModel && $tenantModel->status === 'active')
                            <span style="background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); font-size: 9px; font-weight: 800; padding: 1px 6px; border-radius: 4px; text-transform: uppercase;">Active</span>
                        @endif
                    </a>
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
            <!-- Left: Mobile Menu Toggle & Live Clock -->
            <div class="topbar-left-wrap">
                <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" onclick="toggleMobileSidebar()" aria-label="Toggle navigation menu">
                    <span>☰</span>
                </button>

                <div id="liveClockWidget">
                    <span>📅</span> <span id="headerDate">--</span>
                    <span class="clock-divider">|</span>
                    <span>⏰</span> <span id="headerTime">--:--:--</span>
                </div>

                @if($currentRole === 'viewer')
                    <div class="observer-pill">
                        <span>👑</span> <span>Executive Observer</span>
                    </div>
                @endif
            </div>

            <!-- Center: Active Branch Context Switcher -->
            <div class="topbar-branch-wrap">
                @if($globalAuthUser)
                    @if($isBranchLocked)
                        <div class="branch-badge-locked" title="Assigned branch location (Locked for frontline staff)">
                            <span class="b-pill-indicator">🏬</span>
                            <div class="b-meta">
                                <span class="b-label">Assigned Branch</span>
                                <span class="b-name">{{ $globalActiveWarehouse->name ?? 'My Branch' }}</span>
                            </div>
                            <span class="b-lock-tag">🔒 Assigned</span>
                        </div>
                    @else
                        <div class="branch-selector-dropdown-wrapper" id="globalBranchDropdownWrapper">
                            <button type="button" class="branch-selector-btn" id="globalBranchDropdownBtn" onclick="toggleGlobalBranchDropdown(event)" aria-haspopup="true" aria-expanded="false" title="Click to switch active branch context">
                                <span class="b-pill-indicator">{{ $globalActiveWarehouse ? '🏬' : '🌐' }}</span>
                                <div class="b-meta">
                                    <span class="b-label">{{ $globalActiveWarehouse ? 'Active Branch' : 'Scope' }}</span>
                                    <span class="b-name">{{ $globalActiveWarehouse ? $globalActiveWarehouse->name : 'All Branches (Consolidated)' }}</span>
                                </div>
                                <span class="b-chevron">▾</span>
                            </button>
                            
                            <div class="branch-dropdown-menu" id="globalBranchDropdownMenu">
                                <div class="branch-dropdown-header">
                                    <span>Select Branch Context</span>
                                    <span class="count-pill">{{ $accessibleWarehouses->count() }} Available</span>
                                </div>
                                
                                <div class="branch-dropdown-list">
                                    <!-- Consolidated Option -->
                                    <form method="POST" action="{{ route('branch.switch') }}" style="margin: 0;">
                                        @csrf
                                        <input type="hidden" name="warehouse_id" value="ALL">
                                        <button type="submit" class="branch-option-btn {{ is_null($globalActiveWarehouseId) ? 'active' : '' }}">
                                            <span class="bo-icon">🌐</span>
                                            <div class="bo-content">
                                                <div class="bo-title">All Branches (Consolidated)</div>
                                                <div class="bo-subtitle">Company-wide analytics & totals</div>
                                            </div>
                                            @if(is_null($globalActiveWarehouseId))
                                                <span class="bo-badge">Active ✓</span>
                                            @endif
                                        </button>
                                    </form>

                                    <div class="branch-dropdown-divider"></div>

                                    <!-- Individual Authorized Branches -->
                                    @foreach($accessibleWarehouses as $wh)
                                        <form method="POST" action="{{ route('branch.switch') }}" style="margin: 0;">
                                            @csrf
                                            <input type="hidden" name="warehouse_id" value="{{ $wh->id }}">
                                            <button type="submit" class="branch-option-btn {{ (string)$globalActiveWarehouseId === (string)$wh->id ? 'active' : '' }}">
                                                <span class="bo-icon">🏬</span>
                                                <div class="bo-content">
                                                    <div class="bo-title">{{ $wh->name }}</div>
                                                    <div class="bo-subtitle">{{ $wh->address ?: ($wh->location ?: 'Physical Store') }}</div>
                                                </div>
                                                @if((string)$globalActiveWarehouseId === (string)$wh->id)
                                                    <span class="bo-badge">Active ✓</span>
                                                @endif
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <!-- Right: Operator, Quick Calculator, Password & Logout -->
            <div class="topbar-right-wrap">
                <div class="topbar-operator-badge" title="Active Logged In Operator">
                    <span class="op-icon">👤</span>
                    <span class="op-label">Operator:</span>
                    <strong class="op-name">{{ auth()->user()->name ?? session('user_name', 'Auditor / Lead') }}</strong>
                </div>

                <button type="button" class="topbar-btn topbar-btn-calc" onclick="toggleCalculator()" title="Quick POS Calculator (Alt+C)">
                    <span>🧮</span> <span class="btn-text-desktop">Calculator</span><span class="btn-text-mobile">Calc</span>
                </button>

                <a href="{{ route('account.password') }}" class="topbar-btn topbar-btn-password" title="Change your account password">
                    <span>🔑</span> <span class="btn-text-desktop">Password</span><span class="btn-text-mobile">Pass</span>
                </a>

                <a href="{{ route('logout') }}" class="topbar-btn topbar-btn-logout" title="Sign out of system">
                    <span>🚪</span> <span class="btn-text-desktop">Log Out</span><span class="btn-text-mobile">Exit</span>
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
    <div id="modalGlobalConfirm" class="modal-backdrop" style="display: none; z-index: 999998;">
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
    <div id="modalCalculator" class="modal-backdrop" style="display: none;" onclick="handleCalcBackdropClick(event)">
        <div class="modal calc-modal-card" onclick="event.stopPropagation()">
            <!-- Modal Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.3rem;">🧮</span>
                    <div>
                        <h3 style="font-size: 1.05rem; font-weight: 800; color: #f8fafc; line-height: 1.2;">POS Calculator</h3>
                        <span style="font-size: 0.72rem; color: #64748b; font-weight: 600;">Supports Keyboard & Numpad</span>
                    </div>
                </div>
                <button type="button" onclick="toggleCalculator()" class="calc-close-btn" title="Close (Esc)">✕</button>
            </div>

            <!-- Dual-Line Display (History + Main) -->
            <div class="calc-display-box">
                <div id="calcHistory" class="calc-history"></div>
                <div id="calcDisplay" class="calc-screen">0</div>
            </div>

            <!-- Keypad Grid (4 columns x 5 rows) -->
            <div class="calc-keypad-grid">
                <!-- Row 1 -->
                <button type="button" class="calc-key calc-key-clear" onclick="calcClear()" title="Clear (C or Esc)">C</button>
                <button type="button" class="calc-key calc-key-del" onclick="calcBackspace()" title="Backspace (⌫)">⌫</button>
                <button type="button" class="calc-key calc-key-op" onclick="calcPercent()" title="Percentage (%)">%</button>
                <button type="button" class="calc-key calc-key-op" onclick="calcInput('/')" title="Divide (/)">÷</button>

                <!-- Row 2 -->
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('7')">7</button>
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('8')">8</button>
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('9')">9</button>
                <button type="button" class="calc-key calc-key-op" onclick="calcInput('*')" title="Multiply (*)">×</button>

                <!-- Row 3 -->
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('4')">4</button>
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('5')">5</button>
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('6')">6</button>
                <button type="button" class="calc-key calc-key-op" onclick="calcInput('-')" title="Subtract (-)">−</button>

                <!-- Row 4 -->
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('1')">1</button>
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('2')">2</button>
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('3')">3</button>
                <button type="button" class="calc-key calc-key-op" onclick="calcInput('+')" title="Add (+)">+</button>

                <!-- Row 5 -->
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('0')">0</button>
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('00')">00</button>
                <button type="button" class="calc-key calc-key-num" onclick="calcInput('.')">.</button>
                <button type="button" class="calc-key calc-key-equals" onclick="calcEquals()" title="Equals (= or Enter)">=</button>
            </div>
        </div>
    </div>

    <!-- Global Action Blocked / Business Rule Constraint Reason Modal -->
    <div id="modalActionBlocked" class="modal-backdrop" style="display: none; z-index: 999999;">
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

    <!-- PWA Smart Install Prompt Banner -->
    <div id="pwaInstallBanner" class="pwa-install-banner">
        <div class="pwa-banner-content">
            <div class="pwa-banner-icon">📱</div>
            <div class="pwa-banner-text">
                <h4>Install Victorious POS</h4>
                <p>1-tap instant launch without browser controls</p>
            </div>
        </div>
        <div class="pwa-banner-actions">
            <button type="button" class="pwa-btn-install" id="btnPwaInstall" onclick="installPwaApp()">Install</button>
            <button type="button" class="pwa-btn-dismiss" onclick="dismissPwaBanner()" title="Dismiss">✕</button>
        </div>
    </div>

    <!-- Mobile Bottom Navigation Bar (5 Primary Tabs) -->
    <nav class="mobile-bottom-nav" aria-label="Mobile Navigation">
        <!-- Tab 1: Dashboard -->
        <a href="{{ route('dashboard') }}" class="mobile-nav-tab {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
            <span class="tab-icon">📊</span>
            <span class="tab-label">Dashboard</span>
        </a>

        <!-- Tab 2: POS Checkout Counter (Accented) -->
        <a href="{{ route('pos.index') }}" class="mobile-nav-tab pos-accent {{ request()->routeIs('pos.*') ? 'active' : '' }}">
            <div class="tab-icon-wrap">
                <span class="tab-icon" style="font-size: 1.25rem;">🛒</span>
            </div>
            <span class="tab-label">POS</span>
        </a>

        <!-- Tab 3: Products Catalog -->
        @if(Route::has('products.index'))
        <a href="{{ route('products.index') }}" class="mobile-nav-tab {{ request()->routeIs('products.*') ? 'active' : '' }}">
            <span class="tab-icon">📦</span>
            <span class="tab-label">Products</span>
        </a>
        @endif

        <!-- Tab 4: Reports & Audits -->
        @if(Route::has('reports.index'))
        <a href="{{ route('reports.index') }}" class="mobile-nav-tab {{ request()->routeIs('reports.*') ? 'active' : '' }}">
            <span class="tab-icon">📑</span>
            <span class="tab-label">Reports</span>
        </a>
        @endif

        <!-- Tab 5: More / Drawer Toggle -->
        <button type="button" class="mobile-nav-tab" onclick="window.toggleMobileSidebar ? window.toggleMobileSidebar(true) : toggleMobileSidebar(true)" style="background: none; border: none; cursor: pointer;">
            <span class="tab-icon">☰</span>
            <span class="tab-label">Menu</span>
        </button>
    </nav>

    <!-- Unified Core UI Engine -->
    <script src="{{ asset('js/core-ui.js') }}"></script>

    <!-- PWA Registration & Install Handler Script -->
    <script>
        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('{{ asset('sw.js') }}')
                    .then(function(reg) {
                        // SW registered successfully
                    })
                    .catch(function(err) {
                        console.warn('VM POS SW registration failed:', err);
                    });
            });
        }

        // PWA Install Prompt Banner Logic
        let deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            // Only show if user hasn't dismissed in current session
            if (!sessionStorage.getItem('pwa_banner_dismissed')) {
                const banner = document.getElementById('pwaInstallBanner');
                if (banner) {
                    banner.style.display = 'flex';
                }
            }
        });

        function installPwaApp() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        const banner = document.getElementById('pwaInstallBanner');
                        if (banner) banner.style.display = 'none';
                    }
                    deferredPrompt = null;
                });
            }
        }

        function dismissPwaBanner() {
            const banner = document.getElementById('pwaInstallBanner');
            if (banner) banner.style.display = 'none';
            sessionStorage.setItem('pwa_banner_dismissed', 'true');
        }
    </script>

    @stack('scripts')
</body>
</html>

