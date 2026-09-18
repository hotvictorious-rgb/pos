<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? 'Executive Business Report' }} — {{ $businessName ?? 'VMPOS' }}</title>
    <style>
        /* Base Reset & Corporate Typography */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.4;
            font-size: 11px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Screen Top Navigation Bar */
        .no-print-bar {
            background: #0f172a;
            color: #f8fafc;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .no-print-bar .brand-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .no-print-bar .actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
        }
        .btn-print {
            background: #2563eb;
            color: #ffffff;
        }
        .btn-print:hover {
            background: #1d4ed8;
        }
        .btn-back {
            background: #334155;
            color: #e2e8f0;
        }
        .btn-back:hover {
            background: #475569;
            color: #ffffff;
        }
        .btn-filter-toggle {
            background: #1e293b;
            color: #94a3b8;
            border: 1px solid #475569;
        }
        .btn-filter-toggle.active {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }

        /* Printable Document Sheet Container */
        .sheet-container {
            max-width: 1200px;
            margin: 20px auto 40px auto;
            background: #ffffff;
            padding: 24px 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border-radius: 4px;
        }

        /* Report Header Section */
        .report-header {
            margin-bottom: 18px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }
        .company-title {
            font-size: 20px;
            font-weight: 800;
            color: #0c2340;
            letter-spacing: -0.3px;
        }
        .report-subtitle {
            font-size: 12px;
            color: #475569;
            font-weight: 500;
            margin-top: 2px;
        }
        .audit-meta-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            margin-top: 8px;
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .audit-meta-bar span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .scope-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
        }

        /* KPI Executive Summary Grid (Exact match to executive screenshot) */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 20px;
            background: #ffffff;
        }
        .kpi-cell {
            padding: 10px 14px;
            border-right: 1px solid #cbd5e1;
            text-align: center;
            background: #ffffff;
        }
        .kpi-cell:last-child {
            border-right: none;
        }
        .kpi-label {
            font-size: 9.5px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            line-height: 1.2;
        }
        .kpi-value {
            font-size: 19px;
            font-weight: 800;
            color: #0c2340;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .kpi-sub {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Executive Enterprise Data Table */
        .data-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }
        table.executive-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            border: 1px solid #cbd5e1;
            font-size: 10px;
        }
        table.executive-table th {
            background-color: #0c2340 !important;
            color: #ffffff !important;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 9px;
            border: 1px solid #0c2340;
            white-space: nowrap;
            font-size: 9.5px;
        }
        table.executive-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #1e293b;
        }
        table.executive-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        table.executive-table tbody tr:hover {
            background-color: #f1f5f9;
        }

        /* Alignments */
        .text-left { text-align: left !important; }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        .font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-weight: 600;
        }
        .font-bold { font-weight: 700 !important; }

        /* Badges & Health Indicators */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .badge-instock { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-lowstock { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-outstock { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-partial { background: #fef3c7; color: #92400e; }
        .badge-unpaid { background: #fee2e2; color: #991b1b; }
        .badge-neutral { background: #f1f5f9; color: #475569; }

        /* Summary Total Footer Row */
        table.executive-table tfoot tr td {
            background-color: #f8fafc;
            color: #0c2340;
            font-weight: 800;
            border-top: 2px solid #0c2340;
            border-bottom: 2px solid #0c2340;
            padding: 8px 9px;
            font-size: 10.5px;
        }

        /* Document Footer & Stamp */
        .report-footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 9px;
            color: #64748b;
        }

        /* PRINT STYLESHEET (Clean A4 without blank rows or cutoff) */
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                font-size: 9.5px;
            }
            .no-print-bar {
                display: none !important;
            }
            .sheet-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            @page {
                size: A4 landscape;
                margin: 8mm 6mm;
            }
            table.executive-table {
                page-break-inside: auto;
            }
            table.executive-table thead {
                display: table-header-group !important; /* Repeats navy header on every page */
            }
            table.executive-table tbody tr {
                page-break-inside: avoid !important; /* Prevents rows from splitting awkwardly */
                page-break-after: auto;
            }
            table.executive-table tfoot {
                display: table-footer-group !important;
            }
            .kpi-grid {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- Screen Navigation & Print Toolbar -->
    <header class="no-print-bar">
        <div class="brand-badge">
            <span>🏛️</span>
            <span>{{ $businessName }} — Executive Reporting</span>
            <span style="font-size: 11px; font-weight: normal; color: #94a3b8;">({{ $reportTitle }})</span>
        </div>
        <div class="actions">
            @if(isset($allowInStockFilter) && $allowInStockFilter)
                <div style="display: inline-flex; align-items: center; gap: 6px; background: #1e293b; border: 1px solid #475569; padding: 4px 10px; border-radius: 6px; font-size: 11px;">
                    <span style="color: #94a3b8; font-weight: 600;">📦 Qty Filter:</span>
                    <select id="quickQtyFilter" onchange="applyQuantityFilter(this.value)" style="background: #0f172a; color: #f8fafc; border: 1px solid #475569; border-radius: 4px; padding: 3px 8px; font-size: 11px; font-weight: 700; cursor: pointer; outline: none;">
                        <option value="ALL">All Items</option>
                        <option value="GT0" {{ request('min_qty') == '1' || (request('min_qty') === '0' && request('qty_op') == '>') ? 'selected' : '' }}>In-Stock (> 0)</option>
                        <option value="GE1" {{ request('min_qty') == '1' && request('qty_op', '>=') == '>=' ? 'selected' : '' }}>Qty ≥ 1</option>
                        <option value="GE2" {{ request('min_qty') == '2' ? 'selected' : '' }}>Qty ≥ 2</option>
                        <option value="GE5" {{ request('min_qty') == '5' ? 'selected' : '' }}>Qty ≥ 5</option>
                        <option value="GE10" {{ request('min_qty') == '10' ? 'selected' : '' }}>Qty ≥ 10</option>
                        <option value="EQ0" {{ request('min_qty') === '0' && (request('qty_op') == '=' || !request('qty_op')) ? 'selected' : '' }}>Out of Stock (= 0)</option>
                    </select>
                    <span id="filterMatchCount" style="color: #38bdf8; font-weight: 700; font-size: 10px;"></span>
                </div>
            @endif
            <a href="{{ route('reports.index', request()->query()) }}" class="btn-action btn-back">
                ← Back to Reports
            </a>
            <button type="button" class="btn-action btn-print" onclick="window.print()">
                🖨️ Print / Save as PDF
            </button>
        </div>
    </header>

    <!-- Main Print Document Sheet -->
    <main class="sheet-container">

        <!-- Report Header -->
        <div class="report-header">
            <div class="header-top">
                <div>
                    <h1 class="company-title">{{ $businessName }} — {{ $reportTitle }}</h1>
                    <p class="report-subtitle">{{ $reportSubtitle ?? 'Official system audit and operational valuation statement' }}</p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 11px; font-weight: 700; color: #0c2340;">TENANT: {{ strtoupper($tenantId ?? 'PRIMARY') }}</div>
                    <div style="font-size: 9.5px; color: #64748b;">Generated: {{ $generatedAt ?? now('Africa/Lagos')->format('M d, Y • h:i A') }}</div>
                </div>
            </div>

            <!-- Audit Filter Scope Metadata Line -->
            <div class="audit-meta-bar">
                <span><strong>Scope:</strong> <span class="scope-badge">{{ $scopeDescription ?? 'Consolidated' }}</span></span>
                <span><strong>Date Period:</strong> {{ $datePeriodDescription ?? 'All Time' }}</span>
                @if(!empty($minQtyFilterDescription))
                    <span><strong>Quantity Filter:</strong> <span class="scope-badge" style="background: #e0f2fe; color: #0369a1;">{{ $minQtyFilterDescription }}</span></span>
                @endif
                @if(!empty($cashierDescription))
                    <span><strong>Staff/Cashier:</strong> {{ $cashierDescription }}</span>
                @endif
                @if(!empty($paymentFilterDescription))
                    <span><strong>Payment Status:</strong> {{ $paymentFilterDescription }}</span>
                @endif
                @if(!empty($deliveryFilterDescription))
                    <span><strong>Delivery:</strong> {{ $deliveryFilterDescription }}</span>
                @endif
                <span><strong>Currency:</strong> {{ $currency ?? 'NGN (₦)' }}</span>
            </div>
        </div>

        <!-- Executive KPI Cards Grid -->
        @if(!empty($kpiCards) && is_array($kpiCards))
            <div class="kpi-grid">
                @foreach($kpiCards as $kpi)
                    <div class="kpi-cell">
                        <div class="kpi-label">{{ $kpi['label'] }}</div>
                        <div class="kpi-value">{{ $kpi['value'] }}</div>
                        @if(!empty($kpi['sub']))
                            <div class="kpi-sub">{{ $kpi['sub'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Data Table Content Injected Here -->
        <div class="data-table-wrapper">
            @yield('content')
        </div>

        <!-- Document Footer -->
        <footer class="report-footer">
            <div>
                <strong>{{ $businessName }}</strong> • VMPOS Multi-Branch Enterprise Suite • {{ $reportFooter ?? 'Confidential Internal Record' }}
                <div style="font-size: 8.5px; color: #334155; margin-top: 3px; font-weight: 600;">
                    Powered by <strong>Victorious Market</strong> — Your Trusted Online Market
                </div>
            </div>
            <div style="text-align: right;">
                <div>System Audit Trace ID: <code>{{ substr(md5(now()->toIso8601String() . ($tenantId ?? '')), 0, 12) }}</code></div>
                <div style="font-size: 8px; color: #94a3b8; margin-top: 2px;">Official Multi-Branch Executive Record</div>
            </div>
        </footer>

    </main>

    <script>
        function applyQuantityFilter(filterVal) {
            const rows = document.querySelectorAll('tbody tr[data-stock]');
            if (!rows.length) return;

            let matchCount = 0;
            rows.forEach(r => {
                const stock = parseInt(r.getAttribute('data-stock') || '0', 10);
                let show = true;
                if (filterVal === 'GT0') {
                    show = (stock > 0);
                } else if (filterVal === 'GE1') {
                    show = (stock >= 1);
                } else if (filterVal === 'GE2') {
                    show = (stock >= 2);
                } else if (filterVal === 'GE5') {
                    show = (stock >= 5);
                } else if (filterVal === 'GE10') {
                    show = (stock >= 10);
                } else if (filterVal === 'EQ0') {
                    show = (stock === 0);
                } else {
                    show = true;
                }

                r.style.display = show ? '' : 'none';
                if (show) matchCount++;
            });

            const matchSpan = document.getElementById('filterMatchCount');
            if (matchSpan) {
                matchSpan.innerText = '(' + matchCount + ' of ' + rows.length + ' shown)';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const selectEl = document.getElementById('quickQtyFilter');
            if (selectEl) {
                applyQuantityFilter(selectEl.value);
            }

            // Auto-trigger print if requested
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === '1' || urlParams.get('auto_print') === '1') {
                setTimeout(() => window.print(), 500);
            }
        });
    </script>
</body>
</html>
