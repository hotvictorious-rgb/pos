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
            $currentRole = auth()->user()->role ?? $authUser->role ?? 'admin';
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
                    <span>💳</span> <span>Customer Debts</span>
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

    // 2. Interactive POS Calculator Engine
    let calcExpression = '';
    let calcHistoryText = '';
    let calcJustEvaluated = false;

    function toggleCalculator() {
        const modal = document.getElementById('modalCalculator');
        if (!modal) return;
        const isVisible = (modal.style.display === 'flex');
        if (isVisible) {
            modal.style.display = 'none';
            document.removeEventListener('keydown', handleCalcKeyboard);
        } else {
            modal.style.display = 'flex';
            document.addEventListener('keydown', handleCalcKeyboard);
            updateCalcDisplay();
        }
    }

    function handleCalcBackdropClick(e) {
        if (e.target.id === 'modalCalculator') {
            toggleCalculator();
        }
    }

    function updateCalcDisplay() {
        const displayEl = document.getElementById('calcDisplay');
        const historyEl = document.getElementById('calcHistory');
        if (displayEl) {
            displayEl.textContent = calcExpression || '0';
        }
        if (historyEl) {
            historyEl.textContent = calcHistoryText;
        }
    }

    function calcInput(val) {
        // If user just evaluated an expression and types a number, start fresh
        if (calcJustEvaluated) {
            if (['0','1','2','3','4','5','6','7','8','9','00'].includes(val)) {
                calcExpression = '';
            } else if (val === '.') {
                calcExpression = '0';
            }
            calcJustEvaluated = false;
        }

        const operators = ['+', '-', '*', '/'];

        // If expression is empty or "0"
        if (!calcExpression || calcExpression === '0') {
            if (operators.includes(val)) {
                if (val === '-') {
                    calcExpression = '-';
                    updateCalcDisplay();
                    return;
                }
                return; // Ignore +, *, / on empty/0
            }
            if (val === '.') {
                calcExpression = '0.';
                updateCalcDisplay();
                return;
            }
            if (val === '00') {
                calcExpression = '0';
                updateCalcDisplay();
                return;
            }
            calcExpression = val;
            updateCalcDisplay();
            return;
        }

        const lastChar = calcExpression.slice(-1);

        // If new input is an operator and last char is an operator: replace last operator
        if (operators.includes(val)) {
            if (operators.includes(lastChar)) {
                calcExpression = calcExpression.slice(0, -1) + val;
                updateCalcDisplay();
                return;
            }
        }

        // Decimal point handling
        if (val === '.') {
            if (operators.includes(lastChar)) {
                calcExpression += '0.';
                updateCalcDisplay();
                return;
            }
            const segments = calcExpression.split(/[-+*/]/);
            const currentSegment = segments[segments.length - 1];
            if (currentSegment.includes('.')) {
                return; // already has decimal point
            }
        }

        // Leading zero handling within current number segment
        const segments = calcExpression.split(/[-+*/]/);
        const currentSegment = segments[segments.length - 1];
        if (currentSegment === '0') {
            if (['1','2','3','4','5','6','7','8','9'].includes(val)) {
                calcExpression = calcExpression.slice(0, -1) + val;
                updateCalcDisplay();
                return;
            }
            if (val === '0' || val === '00') {
                return; // prevent multiple zeros like "00"
            }
        }

        calcExpression += val;
        updateCalcDisplay();
    }

    function calcBackspace() {
        if (calcJustEvaluated) {
            calcClear();
            return;
        }
        if (calcExpression.length > 0) {
            calcExpression = calcExpression.slice(0, -1);
            if (calcExpression === '' || calcExpression === '-') {
                calcExpression = '0';
            }
        } else {
            calcExpression = '0';
        }
        updateCalcDisplay();
    }

    function calcClear() {
        calcExpression = '';
        calcHistoryText = '';
        calcJustEvaluated = false;
        updateCalcDisplay();
    }

    // Safe Arithmetic Evaluator without eval or new Function (CSP & sandbox proof)
    function safeEvaluate(str) {
        if (!str) return 0;
        str = str.replace(/\s+/g, '');
        if (!str) return 0;

        // Auto-close unclosed parentheses if any
        let openCount = (str.match(/\(/g) || []).length;
        let closeCount = (str.match(/\)/g) || []).length;
        if (openCount > closeCount) {
            str += ')'.repeat(openCount - closeCount);
        }

        // Parentheses reduction
        let parenRegex = /\(([^()]+)\)/;
        while (parenRegex.test(str)) {
            let match = str.match(parenRegex);
            let subVal = evaluateSimpleExpr(match[1]);
            if (subVal === 'Cannot divide by 0') return 'Cannot divide by 0';
            str = str.replace(match[0], subVal);
        }

        return evaluateSimpleExpr(str);
    }

    function evaluateSimpleExpr(expr) {
        if (!expr) return 0;
        let tokens = [];
        let i = 0;
        let len = expr.length;

        while (i < len) {
            let ch = expr[i];

            // Check if '-' is unary (at start or immediately after an operator)
            let lastToken = tokens[tokens.length - 1];
            if (ch === '-' && (tokens.length === 0 || ['+', '-', '*', '/'].includes(lastToken))) {
                let numStr = '-';
                i++;
                while (i < len && ((expr[i] >= '0' && expr[i] <= '9') || expr[i] === '.')) {
                    numStr += expr[i];
                    i++;
                }
                if (numStr === '-') numStr = '0';
                tokens.push(parseFloat(numStr) || 0);
                continue;
            }

            if (['+', '-', '*', '/'].includes(ch)) {
                tokens.push(ch);
                i++;
                continue;
            }

            if ((ch >= '0' && ch <= '9') || ch === '.') {
                let numStr = '';
                while (i < len && ((expr[i] >= '0' && expr[i] <= '9') || expr[i] === '.')) {
                    numStr += expr[i];
                    i++;
                }
                tokens.push(parseFloat(numStr) || 0);
                continue;
            }

            i++;
        }

        if (tokens.length === 0) return 0;

        // First pass: multiplication and division
        let j = 0;
        while (j < tokens.length) {
            if (tokens[j] === '*' || tokens[j] === '/') {
                let op = tokens[j];
                let prev = Number(tokens[j - 1]) || 0;
                let next = tokens[j + 1] !== undefined ? Number(tokens[j + 1]) : 0;

                let res = 0;
                if (op === '*') {
                    res = prev * next;
                } else {
                    if (next === 0) return 'Cannot divide by 0';
                    res = prev / next;
                }

                tokens.splice(j - 1, 3, res);
                j--;
            } else {
                j++;
            }
        }

        // Second pass: addition and subtraction
        j = 0;
        while (j < tokens.length) {
            if (tokens[j] === '+' || tokens[j] === '-') {
                let op = tokens[j];
                let prev = Number(tokens[j - 1]) || 0;
                let next = tokens[j + 1] !== undefined ? Number(tokens[j + 1]) : 0;

                let res = 0;
                if (op === '+') {
                    res = prev + next;
                } else {
                    res = prev - next;
                }

                tokens.splice(j - 1, 3, res);
                j--;
            } else {
                j++;
            }
        }

        return tokens[0] !== undefined ? tokens[0] : 0;
    }

    function calcPercent() {
        if (!calcExpression || calcExpression === '0') return;
        let expr = calcExpression;
        while (['+', '-', '*', '/', '.'].includes(expr.slice(-1))) {
            expr = expr.slice(0, -1);
        }
        if (!expr) return;

        // Match base expression, operator, and percentage operand, e.g. "1000 - 10" or "200 * 15" or "50"
        const match = expr.match(/(.*?)([-+*/])?(\d+(?:\.\d+)?)$/);
        if (match) {
            const before = match[1] || '';
            const op = match[2];
            const num = parseFloat(match[3]);

            if (op === '+' || op === '-') {
                // Retail POS percentage (e.g. 1000 + 7.5% = 1000 + 75, or 1000 - 10% = 1000 - 100)
                try {
                    const baseVal = safeEvaluate(before);
                    if (isFinite(baseVal)) {
                        const percentAmount = Math.round((Number(baseVal) * (num / 100) + Number.EPSILON) * 1000000) / 1000000;
                        calcExpression = before + op + percentAmount;
                        updateCalcDisplay();
                        return;
                    }
                } catch(e) {}
            }

            // Direct rate or standalone (e.g. 200 * 15% = 200 * 0.15, or 50% = 0.5)
            const percentVal = Math.round(((num / 100) + Number.EPSILON) * 1000000) / 1000000;
            calcExpression = (before || '') + (op || '') + percentVal;
            updateCalcDisplay();
        }
    }

    function calcEquals() {
        if (!calcExpression) return;
        try {
            // Strip trailing operators and trailing decimal dots
            let expr = calcExpression;
            while (['+', '-', '*', '/', '.'].includes(expr.slice(-1))) {
                expr = expr.slice(0, -1);
            }
            if (!expr) return;

            // Remove accidental leading zeros before digits (e.g. +07 -> +7)
            expr = expr.replace(/(^|[-+*/(])0+([1-9])/g, '$1$2');

            // Pure arithmetic evaluation without eval/new Function
            const result = safeEvaluate(expr);

            if (result === 'Cannot divide by 0' || !isFinite(result)) {
                const displayEl = document.getElementById('calcDisplay');
                if (displayEl) displayEl.textContent = 'Cannot divide by 0';
                calcHistoryText = expr + ' =';
                const historyEl = document.getElementById('calcHistory');
                if (historyEl) historyEl.textContent = calcHistoryText;
                calcExpression = '';
                calcJustEvaluated = true;
                return;
            }

            // High precision rounding (up to 6 decimal places, no trailing 0s)
            const rounded = Math.round((Number(result) + Number.EPSILON) * 1000000) / 1000000;
            calcHistoryText = expr + ' =';
            calcExpression = String(rounded);
            calcJustEvaluated = true;

            const displayEl = document.getElementById('calcDisplay');
            const historyEl = document.getElementById('calcHistory');
            if (displayEl) {
                displayEl.textContent = Number(rounded).toLocaleString('en-US', { maximumFractionDigits: 6 });
            }
            if (historyEl) {
                historyEl.textContent = calcHistoryText;
            }
        } catch (e) {
            console.error('Calculator Evaluation Error:', e, 'Expression:', calcExpression);
            document.getElementById('calcDisplay').textContent = 'Error';
            calcExpression = '';
            calcJustEvaluated = true;
        }
    }

    function handleCalcKeyboard(e) {
        const modal = document.getElementById('modalCalculator');
        if (!modal || modal.style.display !== 'flex') return;

        // Allow Esc to close
        if (e.key === 'Escape') {
            e.preventDefault();
            toggleCalculator();
            return;
        }

        if (e.key >= '0' && e.key <= '9') {
            e.preventDefault();
            calcInput(e.key);
        } else if (['+', '-', '*', '/'].includes(e.key)) {
            e.preventDefault();
            calcInput(e.key);
        } else if (e.key === '.') {
            e.preventDefault();
            calcInput('.');
        } else if (e.key === '%') {
            e.preventDefault();
            calcPercent();
        } else if (e.key === 'Enter' || e.key === '=') {
            e.preventDefault();
            calcEquals();
        } else if (e.key === 'Backspace') {
            e.preventDefault();
            calcBackspace();
        } else if (e.key.toLowerCase() === 'c') {
            e.preventDefault();
            calcClear();
        }
    }

    // Global Shortcut: Alt+C toggles calculator from anywhere
    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key.toLowerCase() === 'c') {
            e.preventDefault();
            toggleCalculator();
        }
    });

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

    // =========================================================================
    // GLOBAL SEARCHABLE PRODUCT PICKER ENGINE
    // =========================================================================
    window.spcInstances = {};

    function escapeSpcHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    window.initSearchableProductPickers = function() {
        document.querySelectorAll('.searchable-product-picker').forEach(wrapper => {
            const select = wrapper.querySelector('.spc-raw-select');
            if (!select) return;
            const id = select.id;
            const products = [];

            Array.from(select.options).forEach(opt => {
                if (!opt.value) return;
                products.push({
                    id: opt.value,
                    name: opt.dataset.name || opt.text,
                    code: opt.dataset.code || '',
                    category: opt.dataset.category || '',
                    price: parseFloat(opt.dataset.price || 0)
                });
            });

            window.spcInstances[id] = {
                id: id,
                wrapper: wrapper,
                select: select,
                products: products,
                filtered: products,
                highlightedIndex: -1,
                isOpen: false
            };

            // If select already has a value, sync selected card
            if (select.value) {
                const p = products.find(prod => String(prod.id) === String(select.value));
                if (p) {
                    window.spcShowSelected(id, p);
                }
            }
        });
    };

    window.spcOpen = function(id) {
        const inst = window.spcInstances[id];
        if (!inst) {
            window.initSearchableProductPickers();
            if (!window.spcInstances[id]) return;
        }
        const instance = window.spcInstances[id];
        const input = document.getElementById('spc_input_' + id);
        const query = input ? input.value : '';
        window.spcFilter(id, query);
        const dropdown = document.getElementById('spc_dropdown_' + id);
        if (dropdown) dropdown.style.display = 'block';
        instance.isOpen = true;
    };

    window.spcClose = function(id) {
        const dropdown = document.getElementById('spc_dropdown_' + id);
        if (dropdown) dropdown.style.display = 'none';
        const inst = window.spcInstances[id];
        if (inst) {
            inst.isOpen = false;
            inst.highlightedIndex = -1;
        }
    };

    window.spcFilter = function(id, query) {
        const inst = window.spcInstances[id];
        if (!inst) return;
        const q = (query || '').toLowerCase().trim();
        const clearBtn = document.getElementById('spc_clear_btn_' + id);
        if (clearBtn) clearBtn.style.display = q.length > 0 ? 'block' : 'none';

        if (!q) {
            inst.filtered = inst.products;
        } else {
            inst.filtered = inst.products.filter(p => {
                return (p.name && p.name.toLowerCase().includes(q)) ||
                       (p.code && p.code.toLowerCase().includes(q)) ||
                       (p.category && p.category.toLowerCase().includes(q));
            });
        }

        inst.highlightedIndex = inst.filtered.length > 0 ? 0 : -1;
        window.spcRenderList(id, q);
    };

    window.spcRenderList = function(id, query) {
        const inst = window.spcInstances[id];
        if (!inst) return;
        const listEl = document.getElementById('spc_list_' + id);
        const emptyEl = document.getElementById('spc_empty_' + id);
        const termEl = document.getElementById('spc_term_' + id);
        if (!listEl) return;

        if (inst.filtered.length === 0) {
            listEl.innerHTML = '';
            if (emptyEl) emptyEl.style.display = 'block';
            if (termEl) termEl.textContent = query;
            return;
        }

        if (emptyEl) emptyEl.style.display = 'none';

        const renderSlice = inst.filtered.slice(0, 40);
        let html = '';

        renderSlice.forEach((p, idx) => {
            const isHigh = (idx === inst.highlightedIndex);
            const highBg = isHigh ? 'background: #1e293b;' : '';
            const priceHtml = p.price > 0 
                ? `<span style="color: #34d399; font-weight: 700; font-size: 0.78rem;">₦${Number(p.price).toLocaleString()}</span>` 
                : '';

            html += `
            <div class="spc-item" data-idx="${idx}" onclick="window.spcSelect('${id}', '${p.id}')"
                 onmouseenter="window.spcHighlight('${id}', ${idx})"
                 style="padding: 0.65rem 0.95rem; cursor: pointer; border-bottom: 1px solid rgba(55,65,81,0.4); ${highBg} transition: background 0.12s;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.65rem;">
                    <span style="font-weight: 700; color: #f9fafb; font-size: 0.88rem;">${escapeSpcHtml(p.name)}</span>
                    <span style="background: rgba(37,99,235,0.2); color: #60a5fa; border: 1px solid rgba(37,99,235,0.4); padding: 0.12rem 0.45rem; border-radius: 4px; font-size: 0.72rem; font-family: monospace; font-weight: 700; flex-shrink: 0;">${escapeSpcHtml(p.code)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.25rem; font-size: 0.75rem; color: #9ca3af;">
                    <span>${escapeSpcHtml(p.category || 'General')}</span>
                    ${priceHtml}
                </div>
            </div>`;
        });

        if (inst.filtered.length > 40) {
            html += `<div style="padding: 0.5rem; text-align: center; color: #64748b; font-size: 0.75rem;">+ ${inst.filtered.length - 40} more products. Refine search to narrow down.</div>`;
        }

        listEl.innerHTML = html;
    };

    window.spcHighlight = function(id, idx) {
        const inst = window.spcInstances[id];
        if (!inst) return;
        inst.highlightedIndex = idx;
        const listEl = document.getElementById('spc_list_' + id);
        if (listEl) {
            listEl.querySelectorAll('.spc-item').forEach((item, i) => {
                item.style.background = (i === idx) ? '#1e293b' : 'transparent';
            });
        }
    };

    window.spcSelect = function(id, productId) {
        const inst = window.spcInstances[id];
        if (!inst) return;
        const p = inst.products.find(item => String(item.id) === String(productId));
        if (!p) return;

        inst.select.value = p.id;
        inst.select.dispatchEvent(new Event('change', { bubbles: true }));

        window.spcShowSelected(id, p);
        window.spcClose(id);

        // Auto-advance focus to the next field in the active form/modal
        setTimeout(() => {
            const wrapper = document.getElementById('spc_wrapper_' + id);
            const container = (wrapper && wrapper.closest('form, .modal, .modal-card, .card')) || document;
            if (wrapper && container) {
                const focusable = Array.from(container.querySelectorAll(
                    'input:not([type="hidden"]):not([type="button"]):not([type="reset"]):not([disabled]):not([readonly]):not(.spc-input), select:not([disabled]):not(.spc-raw-select), textarea:not([disabled])'
                )).filter(el => el.offsetParent !== null);
                const nextElem = focusable.find(el => (wrapper.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING) && !wrapper.contains(el));
                if (nextElem) {
                    nextElem.focus();
                    if (typeof nextElem.select === 'function') nextElem.select();
                }
            }
        }, 50);
    };

    window.spcShowSelected = function(id, p) {
        const selectedView = document.getElementById('spc_selected_' + id);
        const searchBox = document.getElementById('spc_search_' + id);
        if (selectedView) {
            const nameEl = selectedView.querySelector('.spc-selected-name');
            const codeEl = selectedView.querySelector('.spc-selected-code');
            const catEl = selectedView.querySelector('.spc-selected-category');
            if (nameEl) nameEl.textContent = p.name;
            if (codeEl) codeEl.textContent = p.code;
            if (catEl) catEl.textContent = p.category ? '• ' + p.category : '';
            selectedView.style.display = 'flex';
        }
        if (searchBox) searchBox.style.display = 'none';
    };

    window.spcReset = function(id) {
        const inst = window.spcInstances[id];
        if (inst) {
            inst.select.value = "";
            inst.select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const selectedView = document.getElementById('spc_selected_' + id);
        const searchBox = document.getElementById('spc_search_' + id);
        const input = document.getElementById('spc_input_' + id);

        if (selectedView) selectedView.style.display = 'none';
        if (searchBox) searchBox.style.display = 'block';
        if (input) {
            input.value = '';
            input.focus();
        }
        window.spcOpen(id);
    };

    window.spcClearInput = function(id) {
        const input = document.getElementById('spc_input_' + id);
        if (input) {
            input.value = '';
            input.focus();
        }
        window.spcFilter(id, '');
    };

    window.spcKeydown = function(id, event) {
        const inst = window.spcInstances[id];
        if (!inst) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (inst.highlightedIndex < inst.filtered.length - 1) {
                window.spcHighlight(id, inst.highlightedIndex + 1);
            }
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (inst.highlightedIndex > 0) {
                window.spcHighlight(id, inst.highlightedIndex - 1);
            }
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (inst.highlightedIndex >= 0 && inst.highlightedIndex < inst.filtered.length) {
                const chosen = inst.filtered[inst.highlightedIndex];
                window.spcSelect(id, chosen.id);
            }
        } else if (event.key === 'Escape') {
            window.spcClose(id);
        }
    };

    // Auto-init on page load
    document.addEventListener('DOMContentLoaded', function() {
        window.initSearchableProductPickers();
    });

    // Close any open product picker dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.searchable-product-picker')) {
            if (window.spcInstances) {
                Object.keys(window.spcInstances).forEach(id => {
                    window.spcClose(id);
                });
            }
        }
    });

    // Mobile Sidebar Drawer Toggle
    window.toggleMobileSidebar = function(force) {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (!sidebar) return;
        const isOpen = typeof force === 'boolean' ? force : !sidebar.classList.contains('open');
        if (isOpen) {
            sidebar.classList.add('open');
            if (backdrop) backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Auto-close mobile drawer when a nav link is clicked on small screens
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.sidebar .nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    window.toggleMobileSidebar(false);
                }
            });
        });
    });

    // ─────────────────────────────────────────────────────────
    // UNIVERSAL PASSWORD VISIBILITY TOGGLE (👁️ / 🙈)
    // ─────────────────────────────────────────────────────────
    window.initPasswordToggles = function(context) {
        const root = context || document;
        const passInputs = root.querySelectorAll('input[type="password"], input[data-password-toggle="true"]');
        passInputs.forEach(input => {
            if (input.dataset.hasPasswordToggle === 'true') return;
            input.dataset.hasPasswordToggle = 'true';

            // Wrap input in relative container if not already wrapped
            let wrapper = input.parentElement;
            if (!wrapper || !wrapper.classList.contains('password-field-wrapper')) {
                wrapper = document.createElement('div');
                wrapper.className = 'password-field-wrapper';
                input.parentNode.insertBefore(wrapper, input);
                wrapper.appendChild(input);
            }

            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'password-toggle-btn';
            toggleBtn.setAttribute('aria-label', 'Toggle password visibility');
            toggleBtn.setAttribute('tabindex', '-1'); // Do not disrupt Tab/Enter keyboard navigation
            toggleBtn.innerHTML = '👁️';
            toggleBtn.title = 'Show password';

            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (input.type === 'password') {
                    input.type = 'text';
                    input.dataset.passwordToggle = 'true';
                    toggleBtn.innerHTML = '🙈';
                    toggleBtn.title = 'Hide password';
                } else {
                    input.type = 'password';
                    toggleBtn.innerHTML = '👁️';
                    toggleBtn.title = 'Show password';
                }
                input.focus();
            });

            wrapper.appendChild(toggleBtn);
        });
    };

    // Auto-initialize toggles on load and watch for dynamic modals
    document.addEventListener('DOMContentLoaded', function() {
        window.initPasswordToggles();

        if (window.MutationObserver) {
            const observer = new MutationObserver(function(mutations) {
                let shouldCheck = false;
                for (let m of mutations) {
                    if (m.addedNodes && m.addedNodes.length > 0) {
                        shouldCheck = true;
                        break;
                    }
                }
                if (shouldCheck) {
                    window.initPasswordToggles();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    });

    // ─────────────────────────────────────────────────────────
    // UNIVERSAL ENTER-KEY ADVANCEMENT ACROSS FORM INPUTS & MODALS
    // ─────────────────────────────────────────────────────────
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        if (e.shiftKey || e.altKey) return;

        const target = e.target;
        if (!target || !target.matches('input:not([type="submit"]):not([type="button"]):not([type="reset"]), select')) {
            return;
        }

        // Preserve native textarea newlines & explicit opt-outs
        if (target.tagName === 'TEXTAREA' || target.getAttribute('data-enter-ignore') === 'true') {
            return;
        }

        // Preserve POS quick barcode/item picker enter behavior
        if (target.id === 'searchInput' && window.location.pathname.includes('/pos')) {
            return;
        }

        const modalContainer = target.closest('.modal, .modal-card, #modalQuickCustomer, #modalSaleConfirm');
        const container = modalContainer || target.closest('form, .cart-drawer, .card') || document;
        const form = target.closest('form');

        // Ctrl + Enter or Cmd + Enter immediately submits form or clicks primary submit button
        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            const submitBtn = container.querySelector('button[type="submit"], input[type="submit"], #completeSaleBtn, #btnSaveQuickCust, #btnFinalProceedSale, button.btn-primary');
            if (submitBtn) {
                submitBtn.click();
            } else if (form) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
            return;
        }

        // Query all focusable and visible elements in the current active scope
        const focusable = Array.from(container.querySelectorAll(
            'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled])'
        )).filter(el => el.offsetParent !== null && !el.hasAttribute('disabled') && !el.hasAttribute('readonly'));

        const currentIndex = focusable.indexOf(target);
        if (currentIndex === -1) return;

        e.preventDefault();

        if (currentIndex + 1 < focusable.length) {
            const nextElement = focusable[currentIndex + 1];
            nextElement.focus();
            if (typeof nextElement.select === 'function' && nextElement.type !== 'date' && nextElement.type !== 'time') {
                nextElement.select();
            }
        } else {
            // Last input in container: click primary submit action button or submit form
            const submitBtn = container.querySelector('button[type="submit"], input[type="submit"], #completeSaleBtn, #btnSaveQuickCust, #btnFinalProceedSale, button.btn-primary, button.btn-success');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.click();
            } else if (form) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        }
    });

    // ─────────────────────────────────────────────────────────
    // RESPONSIVE LIVE FILTERS (Debounced typing & dropdown auto-submit)
    // ─────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Debounced auto-submit for filter forms when typing in filter inputs
        const filterForms = document.querySelectorAll('form[method="GET"], form.filter-form, .filter-hub form');
        filterForms.forEach(form => {
            let debounceTimer = null;
            const inputs = form.querySelectorAll('input[type="text"], input[type="search"], input[type="number"]');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        form.classList.add('filtering-active');
                        form.submit();
                    }, 450);
                });
            });

            // 2. Immediate auto-submit on <select> dropdown changes in filter forms
            const selects = form.querySelectorAll('select');
            selects.forEach(sel => {
                sel.addEventListener('change', function() {
                    form.classList.add('filtering-active');
                    form.submit();
                });
            });
        });

        // Global row filtering utility for tables
        window.filterTableRows = function(tableId, query) {
            const q = (query || '').toLowerCase().trim();
            const table = document.getElementById(tableId);
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(r => {
                if (r.classList.contains('no-filter') || r.querySelector('th')) return;
                const text = r.textContent.toLowerCase();
                r.style.display = text.includes(q) ? '' : 'none';
            });
        };

        // Global Branch Selector Dropdown Handlers
        window.toggleGlobalBranchDropdown = function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const wrap = document.getElementById('globalBranchDropdownWrapper');
            if (!wrap) return;
            const isOpen = wrap.classList.contains('open');
            wrap.classList.toggle('open', !isOpen);
            const btn = document.getElementById('globalBranchDropdownBtn');
            if (btn) btn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
        };

        document.addEventListener('click', function(e) {
            const wrap = document.getElementById('globalBranchDropdownWrapper');
            if (wrap && !wrap.contains(e.target)) {
                wrap.classList.remove('open');
                const btn = document.getElementById('globalBranchDropdownBtn');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        });


        // 3. Instant client-side row filtering on pre-rendered tables (0ms latency!)
        const searchInputs = document.querySelectorAll('input[name="search"], input[placeholder*="Search"]');
        searchInputs.forEach(searchInput => {
            const container = searchInput.closest('.container, main, body');
            if (!container) return;
            const table = container.querySelector('table');
            if (!table) return;

            searchInput.addEventListener('input', function(e) {
                const query = (e.target.value || '').trim().toLowerCase();
                const tbody = table.querySelector('tbody');
                if (!tbody) return;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                if (rows.length === 0) return;

                rows.forEach(row => {
                    // Ignore special non-item rows
                    if (row.classList.contains('no-filter') || row.querySelector('th')) return;
                    const text = (row.textContent || '').toLowerCase();
                    if (!query || text.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    });
    </script>

    @stack('scripts')
</body>
</html>

