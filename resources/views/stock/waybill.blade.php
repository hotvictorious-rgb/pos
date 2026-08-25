<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Waybill #{{ $transfer->transfer_no }} – Hysam Ventures</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            padding: 2rem;
            display: flex;
            justify-content: center;
        }

        .waybill-card {
            background: #fff;
            width: 100%;
            max-width: 800px;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .brand-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
        }

        .waybill-badge {
            background: #dbeafe;
            color: #1e40af;
            font-size: 0.85rem;
            font-weight: 800;
            padding: 0.4rem 0.85rem;
            border-radius: 8px;
            display: inline-block;
            margin-top: 0.35rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        th {
            background: #f1f5f9;
            text-align: left;
            padding: 0.75rem 1rem;
            font-size: 0.8rem;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            border-bottom: 2px solid #cbd5e1;
        }

        td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.95rem;
        }

        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 2px dashed #cbd5e1;
            text-align: center;
        }

        .sign-box {
            border-top: 1px solid #94a3b8;
            padding-top: 0.5rem;
            font-size: 0.85rem;
            font-weight: 700;
            color: #475569;
        }

        .no-print {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-print {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .waybill-card { box-shadow: none; padding: 0; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div style="width: 100%; max-width: 800px;">
        <div class="no-print">
            <a href="{{ route('stock.transfers') }}" class="btn-print" style="background: #64748b;">⬅ Back to Transfers</a>
            <button onclick="window.print()" class="btn-print">🖨️ Print Waybill</button>
        </div>

        <div class="waybill-card">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1 class="brand-title">Hysam Ventures Ltd</h1>
                    <p style="font-size: 0.85rem; color: #64748b;">Official Inter-Branch Stock Transfer Waybill</p>
                </div>
                <div style="text-align: right;">
                    <div class="waybill-badge">{{ $transfer->transfer_no }}</div>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.35rem;">
                        Date: {{ date('d M Y, h:i A', strtotime($transfer->created_at)) }}
                    </div>
                </div>
            </div>

            <!-- Transfer Logistics Info -->
            <div class="info-grid">
                <div>
                    <div style="color: #64748b; font-size: 0.75rem; text-transform: uppercase; font-weight: 700;">Origin Branch (Sending):</div>
                    <strong style="font-size: 1.05rem; color: #1e3a8a;">🏢 {{ $transfer->source->name ?? 'Origin Shop' }}</strong>
                    <div style="font-size: 0.8rem; color: #64748b;">Dispatched By: {{ $transfer->dispatched_by }}</div>
                </div>

                <div>
                    <div style="color: #64748b; font-size: 0.75rem; text-transform: uppercase; font-weight: 700;">Destination Branch (Receiving):</div>
                    <strong style="font-size: 1.05rem; color: #15803d;">🏢 {{ $transfer->destination->name ?? 'Destination Shop' }}</strong>
                    <div style="font-size: 0.8rem; color: #64748b;">Carrier / Driver: {{ $transfer->carrier_name }}</div>
                </div>
            </div>

            <!-- Manifest Table -->
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product SKU</th>
                        <th>Dispatched Qty</th>
                        <th>Received Qty</th>
                        <th>Variance / Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfer->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <strong style="font-size: 1.05rem; color: #1e3a8a; letter-spacing: 0.03em;">{{ $item->product_code ?? $item->product_name }}</strong>
                        </td>
                        <td style="font-weight: 800; font-size: 1rem; color: #1e3a8a;">
                            {{ $item->dispatched_qty }} units
                        </td>
                        <td>
                            {{ $transfer->status === 'DISPATCHED' ? 'Pending Count' : $item->received_qty . ' units' }}
                        </td>
                        <td>
                            @if($transfer->status === 'DISCREPANCY' && $item->discrepancy_qty > 0)
                                <strong style="color: #dc2626;">MISSING -{{ $item->discrepancy_qty }}</strong>
                            @else
                                <span style="color: #16a34a;">Intact</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if($transfer->notes)
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.85rem; margin-bottom: 2rem; font-size: 0.85rem;">
                <strong>Logistics Note:</strong> {{ $transfer->notes }}
            </div>
            @endif

            <!-- Signatures -->
            <div class="sign-grid">
                <div class="sign-box">
                    <div>{{ $transfer->dispatched_by }}</div>
                    <div>Dispatch Officer Sign & Date</div>
                </div>

                <div class="sign-box">
                    <div>{{ $transfer->carrier_name }}</div>
                    <div>Carrier / Driver Sign & Date</div>
                </div>

                <div class="sign-box">
                    <div>{{ $transfer->received_by ?? '____________________' }}</div>
                    <div>Receiving Storekeeper Sign & Date</div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 2rem; font-size: 0.75rem; color: #94a3b8;">
                Hysam Ventures Anti-Theft Inventory Control System • Generated {{ date('d M Y, h:i A') }}
            </div>
        </div>
    </div>

</body>
</html>
