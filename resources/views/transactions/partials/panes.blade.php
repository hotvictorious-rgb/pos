<!-- PANE: SALES -->
<div id="pane-sales" class="ledger-tab-pane" style="{{ $activeTab === 'sales' ? '' : 'display: none;' }}">
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Invoices</h4>
                <div class="val" style="color: #60a5fa;">{{ number_format($totalSalesCount) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Gross Sales</h4>
                <div class="val" style="color: #f8fafc;">₦{{ number_format($totalRevenue, 0) }}</div>
            </div>
            <div class="summary-card">
                <h4>Cash / POS Collected</h4>
                <div class="val" style="color: #4ade80;">₦{{ number_format($totalPaid, 0) }}</div>
            </div>
            <div class="summary-card">
                <h4>Outstanding Debt Created</h4>
                <div class="val" style="color: #f87171;">₦{{ number_format($totalDebt, 0) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" id="liveSearchSales" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('salesTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'sales'], request()->query())) }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                    <a href="{{ route('reports.export.json', array_merge(['type' => 'sales'], request()->query())) }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📊 Export JSON
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="salesTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Invoice Ref</th>
                            <th>Customer</th>
                            <th>Items Count</th>
                            <th>Total Bill</th>
                            <th>Paid Amount</th>
                            <th>Payment Status</th>
                            <th>Handover Status</th>
                            <th>Cashier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                        @php
                            $balance = max(0, $sale->totalAmount - $sale->paidAmount);
                            $isSupplied = in_array(strtoupper($sale->deliveryStatus ?? ''), ['DELIVERED', 'SUPPLIED']);
                        @endphp
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($sale->createdAt)) }}
                            </td>
                            <td>
                                <strong style="color: #93c5fd;">#{{ substr($sale->id, 0, 8) }}</strong>
                            </td>
                            <td>
                                <strong>{{ $sale->customerName ?: 'Walk-in Customer' }}</strong>
                                @if($sale->customerPhone)
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $sale->customerPhone }}</div>
                                @endif
                            </td>
                            <td><span class="badge badge-info">{{ count($sale->items ?? []) }} items</span></td>
                            <td style="font-weight: 800; font-size: 1rem; color: #f8fafc;">
                                ₦{{ number_format($sale->totalAmount, 0) }}
                            </td>
                            <td style="font-weight: 700; color: #4ade80;">
                                ₦{{ number_format($sale->paidAmount, 0) }}
                            </td>
                            <td>
                                @if($sale->paidAmount >= $sale->totalAmount)
                                    <span class="badge badge-success">✓ Paid</span>
                                @elseif($sale->paidAmount > 0)
                                    <span class="badge badge-warning">💳 Part-Paid (Owes ₦{{ number_format($balance, 0) }})</span>
                                @else
                                    <span class="badge badge-danger">🔴 Unpaid (Owes ₦{{ number_format($balance, 0) }})</span>
                                @endif
                            </td>
                            <td>
                                @if($isSupplied)
                                    <span class="badge badge-success">🟢 Supplied</span>
                                @else
                                    <span class="badge badge-warning">⏳ Awaiting Pickup</span>
                                @endif
                            </td>
                            <td style="font-size: 0.85rem; color: #cbd5e1;">{{ $sale->userName ?: 'Cashier' }}</td>
                            <td>
                                <div class="action-btn-group">
                                    <a href="{{ route('pos.receipt', $sale->id) }}" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" target="_blank">
                                        🧾 Receipt
                                    </a>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewSaleDetails({{ json_encode($sale) }})">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No sales invoices found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $sales->links() }}
            </div>
        </div>
</div>

<!-- PANE: STOCK_IN -->
<div id="pane-stock_in" class="ledger-tab-pane" style="{{ $activeTab === 'stock_in' ? '' : 'display: none;' }}">
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Stock In Batches</h4>
                <div class="val" style="color: #4ade80;">{{ number_format($stockInBatches) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Physical Units Added</h4>
                <div class="val" style="color: #4ade80;">+{{ number_format($stockInUnits) }} units</div>
            </div>
            <div class="summary-card">
                <h4>Distinct SKUs Stocked</h4>
                <div class="val" style="color: #60a5fa;">{{ number_format($stockInProducts) }} items</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('stockInTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'stock'], request()->query())) }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="stockInTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Product SKU</th>
                            <th>Quantity Added</th>
                            <th>Description / Supplier</th>
                            <th>Received By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stockInLogs as $log)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($log->timestamp)) }}
                            </td>
                            <td><strong style="color: #60a5fa; font-size: 1.05rem; letter-spacing: 0.03em;">{{ $log->productCode ?: $log->productName }}</strong></td>
                            <td style="font-weight: 800; font-size: 1.05rem; color: #4ade80;">
                                +{{ number_format($log->quantity) }} units
                            </td>
                            <td style="color: #cbd5e1;">{{ $log->description ?: 'Supplier Arrival / Purchase' }}</td>
                            <td><strong>{{ $log->userName ?: 'Storekeeper' }}</strong></td>
                            <td>
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="printGenericVoucher('GOODS RECEIVED NOTE (GRN)', 'GRN-{{ substr(md5($log->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($log->timestamp)) }}', 'Supplier / Source', '{{ addslashes($log->description ?: 'Official Supplier') }}', 'STOCK INFLOW', '#22c55e', [{name: '{{ addslashes($log->productCode ?: $log->productName) }}', qty: '{{ $log->quantity }} units', note: 'Added directly to physical shelf count'}], 'Total Units: +{{ $log->quantity }} units', '{{ addslashes($log->userName ?: 'Storekeeper') }}', 'Physical stock verified and added to shelf balance.')">
                                        📄 Print GRN
                                    </button>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewGenericDetails('Goods Received Entry (Stock In)', 'GRN-{{ substr(md5($log->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($log->timestamp)) }}', 'Supplier / Description', '{{ addslashes($log->description ?: 'Supplier Arrival') }}', 'Stock Inflow', '#22c55e', [{label: 'Product SKU', val: '{{ addslashes($log->productCode ?: $log->productName) }}'}, {label: 'Quantity Added', val: '+{{ $log->quantity }} units', color: '#4ade80'}, {label: 'Officer', val: '{{ addslashes($log->userName ?: 'Storekeeper') }}'}], 'Physical inventory count increased by {{ $log->quantity }} units.')">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No stock in entries found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $stockInLogs->links() }}
            </div>
        </div>
</div>

<!-- PANE: STOCK_OUT -->
<div id="pane-stock_out" class="ledger-tab-pane" style="{{ $activeTab === 'stock_out' ? '' : 'display: none;' }}">
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Outflow Events</h4>
                <div class="val" style="color: #f87171;">{{ number_format($stockOutCount) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Physical Units Out</h4>
                <div class="val" style="color: #f87171;">-{{ number_format($stockOutUnits) }} units</div>
            </div>
            <div class="summary-card">
                <h4>Pickup Deliveries</h4>
                <div class="val" style="color: #4ade80;">{{ number_format($stockOutFulfilled) }} orders</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('stockOutTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'stock'], request()->query())) }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="stockOutTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Event Type</th>
                            <th>Product SKU</th>
                            <th>Units Out</th>
                            <th>Description</th>
                            <th>Authorized Officer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($stockOutLogs as $log)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($log->timestamp)) }}
                            </td>
                            <td>
                                @if(str_contains($log->type, 'DISPATCH_FULFILLED'))
                                    <span class="badge badge-success">📦 Customer Pickup</span>
                                @elseif(str_contains($log->type, 'TRANSFER_OUT'))
                                    <span class="badge badge-info">🚚 Transfer Dispatch</span>
                                @elseif(str_contains($log->type, 'DAMAGE'))
                                    <span class="badge badge-danger">📉 Damage Write-off</span>
                                @elseif(str_contains($log->type, 'EXPIRED'))
                                    <span class="badge badge-warning">⏰ Expired Stock</span>
                                @elseif(str_contains($log->type, 'LOST'))
                                    <span class="badge badge-secondary">🔍 Lost / Audit</span>
                                @else
                                    <span class="badge badge-secondary">{{ $log->type }}</span>
                                @endif
                            </td>
                            <td><strong style="color: #60a5fa; font-size: 1.05rem; letter-spacing: 0.03em;">{{ $log->productCode ?: $log->productName }}</strong></td>
                            <td style="font-weight: 800; font-size: 1.05rem; color: #f87171;">
                                {{ number_format($log->quantity) }} units
                            </td>
                            <td style="color: #cbd5e1;">{{ $log->description }}</td>
                            <td><strong>{{ $log->userName }}</strong></td>
                            <td>
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="printGenericVoucher('STOCK DISPATCH & OUTFLOW SLIP', 'OUT-{{ substr(md5($log->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($log->timestamp)) }}', 'Outflow Type', '{{ addslashes($log->type) }}', 'PHYSICAL OUTFLOW', '#ef4444', [{name: '{{ addslashes($log->productCode ?: $log->productName) }}', qty: '-{{ $log->quantity }} units', note: '{{ addslashes($log->description) }}'}], 'Total Outflow: -{{ $log->quantity }} units', '{{ addslashes($log->userName) }}', 'Goods officially dispatched from physical shelf inventory.')">
                                        📄 Print Slip
                                    </button>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewGenericDetails('Stock Outflow Record', 'OUT-{{ substr(md5($log->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($log->timestamp)) }}', 'Event Type', '{{ addslashes($log->type) }}', 'Stock Outflow', '#ef4444', [{label: 'Product SKU', val: '{{ addslashes($log->productCode ?: $log->productName) }}'}, {label: 'Deducted Units', val: '-{{ $log->quantity }} units', color: '#f87171'}, {label: 'Description', val: '{{ addslashes($log->description) }}'}, {label: 'Authorized By', val: '{{ addslashes($log->userName) }}'}], 'Physical count reduced by {{ $log->quantity }} units.')">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No stock outflow records found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $stockOutLogs->links() }}
            </div>
        </div>
</div>

<!-- PANE: IN_TRANSIT -->
<div id="pane-in_transit" class="ledger-tab-pane" style="{{ $activeTab === 'in_transit' ? '' : 'display: none;' }}">
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Active In-Transit Shipments</h4>
                <div class="val" style="color: #fbbf24;">{{ number_format($inTransitCount) }}</div>
            </div>
            <div class="summary-card">
                <h4>Units on Vehicles Moving Between Shops</h4>
                <div class="val" style="color: #fbbf24;">{{ number_format($inTransitUnits) }} units</div>
            </div>
            <div class="summary-card">
                <h4>Assigned Drivers / Carriers</h4>
                <div class="val" style="color: #60a5fa;">{{ number_format($inTransitCarriers) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('inTransitTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'transfers'], request()->query())) }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="inTransitTable">
                    <thead>
                        <tr>
                            <th>Dispatched Date</th>
                            <th>Waybill Ref</th>
                            <th>Source Branch (Origin)</th>
                            <th>Destination Branch</th>
                            <th>Driver / Carrier</th>
                            <th>Items Count</th>
                            <th>Dispatched Units</th>
                            <th>Dispatched By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inTransitTransfers as $trf)
                        @php
                            $totalUnits = $trf->items->sum('dispatched_qty');
                        @endphp
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($trf->created_at)) }}
                            </td>
                            <td><strong style="color: #93c5fd;">{{ $trf->transfer_no }}</strong></td>
                            <td>🏢 {{ $trf->source->name ?? 'Origin Branch' }}</td>
                            <td>🏪 <strong>{{ $trf->destination->name ?? 'Destination' }}</strong></td>
                            <td>{{ $trf->carrier_name }}</td>
                            <td><span class="badge badge-info">{{ count($trf->items) }} SKUs</span></td>
                            <td style="font-weight: 800; color: #fbbf24; font-size: 1.05rem;">
                                {{ number_format($totalUnits) }} units
                            </td>
                            <td>{{ $trf->dispatched_by }}</td>
                            <td>
                                <div class="action-btn-group">
                                    <a href="{{ route('stock.waybill', $trf->id) }}" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" target="_blank">
                                        📄 Waybill
                                    </a>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewTransferDetails({{ json_encode($trf) }})">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No shipments currently in-transit matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $inTransitTransfers->links() }}
            </div>
        </div>
</div>

<!-- PANE: TRANSFERS_IN -->
<div id="pane-transfers_in" class="ledger-tab-pane" style="{{ $activeTab === 'transfers_in' ? '' : 'display: none;' }}">
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Transfers Received</h4>
                <div class="val" style="color: #4ade80;">{{ number_format($incomingReceived) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Units Verified & Added</h4>
                <div class="val" style="color: #4ade80;">+{{ number_format($incomingUnits) }} units</div>
            </div>
            <div class="summary-card">
                <h4>Discrepancy Alerts</h4>
                <div class="val" style="color: #f87171;">{{ number_format($incomingDiscrepancies) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('incomingTransfersTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'transfers'], request()->query())) }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="incomingTransfersTable">
                    <thead>
                        <tr>
                            <th>Date Dispatched</th>
                            <th>Transfer Waybill</th>
                            <th>Origin Branch</th>
                            <th>Receiving Branch</th>
                            <th>Carrier Name</th>
                            <th>Status</th>
                            <th>Dispatched</th>
                            <th>Counted</th>
                            <th>Missing Variance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incomingTransfers as $trf)
                        @php
                            $dispUnits = $trf->items->sum('dispatched_qty');
                            $recvUnits = $trf->items->sum('received_qty');
                            $discUnits = $trf->items->sum('discrepancy_qty');
                        @endphp
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($trf->dispatched_at ?: $trf->created_at)) }}
                            </td>
                            <td><strong style="color: #93c5fd;">{{ $trf->transfer_no }}</strong></td>
                            <td>🏢 {{ $trf->source->name ?? 'Shop A' }}</td>
                            <td>🏪 {{ $trf->destination->name ?? 'Shop B' }}</td>
                            <td>{{ $trf->carrier_name }}</td>
                            <td>
                                @if($trf->status === 'RECEIVED')
                                    <span class="badge badge-success">✓ Received & Verified</span>
                                @elseif($trf->status === 'DISCREPANCY')
                                    <span class="badge badge-danger">🚨 Variance / Missing</span>
                                @else
                                    <span class="badge badge-warning">🚚 In-Transit / Pending Count</span>
                                @endif
                            </td>
                            <td style="font-weight: 700;">{{ number_format($dispUnits) }}</td>
                            <td style="font-weight: 700; color: #4ade80;">{{ $trf->status === 'DISPATCHED' ? '-' : number_format($recvUnits) }}</td>
                            <td>
                                @if($discUnits > 0)
                                    <strong style="color: #f87171;">{{ number_format($discUnits) }} units MISSING</strong>
                                @else
                                    <span style="color: #4ade80;">0 Variance</span>
                                @endif
                            </td>
                            <td>
                                <div class="action-btn-group">
                                    <a href="{{ route('stock.waybill', $trf->id) }}" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" target="_blank">
                                        📄 Waybill
                                    </a>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewTransferDetails({{ json_encode($trf) }})">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No incoming transfer records found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $incomingTransfers->links() }}
            </div>
        </div>
</div>

<!-- PANE: RETURNS -->
<div id="pane-returns" class="ledger-tab-pane" style="{{ $activeTab === 'returns' ? '' : 'display: none;' }}">
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Return Cases</h4>
                <div class="val" style="color: #fbbf24;">{{ number_format($returnsCount) }}</div>
            </div>
            <div class="summary-card">
                <h4>Units Restocked to Shelves</h4>
                <div class="val" style="color: #4ade80;">+{{ number_format($returnedUnits) }} units</div>
            </div>
            <div class="summary-card">
                <h4>Total Restituted Value</h4>
                <div class="val" style="color: #f8fafc;">₦{{ number_format($returnedValue, 0) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('returnsTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
            </div>

            <div class="table-wrap">
                <table id="returnsTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Return Ref</th>
                            <th>Sale Invoice #</th>
                            <th>Customer</th>
                            <th>Restocked Items</th>
                            <th>Refund Amount</th>
                            <th>Reason for Return</th>
                            <th>Processed By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesReturns as $ret)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($ret->createdAt)) }}
                            </td>
                            <td><strong style="color: #fbbf24;">{{ $ret->code }}</strong></td>
                            <td><strong style="color: #93c5fd;">#{{ substr($ret->saleId, 0, 8) }}</strong></td>
                            <td><strong>{{ $ret->customerName ?: 'Walk-in Customer' }}</strong></td>
                            <td><span class="badge badge-info">{{ $ret->productName }} ({{ $ret->quantity }} units)</span></td>
                            <td style="font-weight: 800; font-size: 1rem; color: #4ade80;">
                                ₦{{ number_format($ret->refundAmount, 0) }}
                            </td>
                            <td style="color: #cbd5e1;">{{ $ret->reason }}</td>
                            <td>{{ $ret->userName }}</td>
                            <td>
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="printGenericVoucher('SALES RETURN & RESTOCK SLIP', '{{ $ret->code }}', '{{ date('d M Y, h:i A', strtotime($ret->createdAt)) }}', 'Customer', '{{ addslashes($ret->customerName ?: 'Walk-in') }}', 'RESTOCK & REFUND', '#f59e0b', [{name: '{{ addslashes($ret->productName) }}', qty: '{{ $ret->quantity }} units', note: 'Restocked to shelf. Ref Original Sale #{{ substr($ret->saleId, 0, 8) }}'}], 'Refund Total: ₦{{ number_format($ret->refundAmount, 0) }}', '{{ addslashes($ret->userName) }}', 'Reason: {{ addslashes($ret->reason) }}')">
                                        📄 Print Slip
                                    </button>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewGenericDetails('Sales Return Record', '{{ $ret->code }}', '{{ date('d M Y, h:i A', strtotime($ret->createdAt)) }}', 'Customer', '{{ addslashes($ret->customerName ?: 'Customer') }}', 'Return & Restock', '#f59e0b', [{label: 'Original Sale Invoice', val: '#{{ substr($ret->saleId, 0, 8) }}'}, {label: 'Product Restocked', val: '{{ addslashes($ret->productName) }}'}, {label: 'Restocked Units', val: '+{{ $ret->quantity }} units', color: '#4ade80'}, {label: 'Refund Amount', val: '₦{{ number_format($ret->refundAmount, 0) }}', color: '#fbbf24'}, {label: 'Reason', val: '{{ addslashes($ret->reason) }}'}, {label: 'Officer', val: '{{ addslashes($ret->userName) }}'}], 'Items verified and physically restored to inventory.')">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No sales return records found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $salesReturns->links() }}
            </div>
        </div>
</div>

<!-- PANE: REFUNDS -->
<div id="pane-refunds" class="ledger-tab-pane" style="{{ $activeTab === 'refunds' ? '' : 'display: none;' }}">
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Refunds Processed</h4>
                <div class="val" style="color: #f87171;">{{ number_format($refundsCount) }}</div>
            </div>
            <div class="summary-card">
                <h4>Total Financial Refund Sum</h4>
                <div class="val" style="color: #f87171;">₦{{ number_format($totalRefundAmount, 0) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('refundsTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
            </div>

            <div class="table-wrap">
                <table id="refundsTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Return Ref</th>
                            <th>Original Invoice</th>
                            <th>Customer Name</th>
                            <th>Refund Amount</th>
                            <th>Reason / Description</th>
                            <th>Authorized Officer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($refundRecords as $ref)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($ref->createdAt)) }}
                            </td>
                            <td><strong style="color: #fbbf24;">{{ $ref->code }}</strong></td>
                            <td><strong style="color: #93c5fd;">#{{ substr($ref->saleId, 0, 8) }}</strong></td>
                            <td><strong>{{ $ref->customerName ?: 'Customer' }}</strong></td>
                            <td style="font-weight: 800; font-size: 1.05rem; color: #f87171;">
                                ₦{{ number_format($ref->refundAmount, 0) }}
                            </td>
                            <td style="color: #cbd5e1;">{{ $ref->reason }}</td>
                            <td><strong>{{ $ref->userName }}</strong></td>
                            <td>
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="printGenericVoucher('OFFICIAL REFUND VOUCHER', 'REF-{{ substr(md5($ref->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($ref->createdAt)) }}', 'Beneficiary (Customer)', '{{ addslashes($ref->customerName ?: 'Customer') }}', 'CASH REFUND', '#ef4444', [{name: 'Refund Payout for Invoice #{{ substr($ref->saleId, 0, 8) }}', qty: '1 event', note: '{{ addslashes($ref->reason) }}'}], 'Total Refunded: ₦{{ number_format($ref->refundAmount, 0) }}', '{{ addslashes($ref->userName) }}', 'Paid out in full from cash drawer.')">
                                        📄 Print Voucher
                                    </button>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewGenericDetails('Financial Refund Record', 'REF-{{ substr(md5($ref->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($ref->createdAt)) }}', 'Beneficiary', '{{ addslashes($ref->customerName ?: 'Customer') }}', 'Cash Refund', '#ef4444', [{label: 'Original Sale Invoice', val: '#{{ substr($ref->saleId, 0, 8) }}'}, {label: 'Refund Amount', val: '₦{{ number_format($ref->refundAmount, 0) }}', color: '#f87171'}, {label: 'Reason', val: '{{ addslashes($ref->reason) }}'}, {label: 'Authorized Officer', val: '{{ addslashes($ref->userName) }}'}], 'Financial payout logged to cash reconciliation register.')">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No customer refunds recorded matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $refundRecords->links() }}
            </div>
        </div>
</div>

<!-- PANE: DEBTS -->
<div id="pane-debts" class="ledger-tab-pane" style="{{ $activeTab === 'debts' ? '' : 'display: none;' }}">
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Repayments Collected</h4>
                <div class="val" style="color: #4ade80;">₦{{ number_format($totalRepayments, 0) }}</div>
            </div>
            <div class="summary-card">
                <h4>Credit / Debt Incurred</h4>
                <div class="val" style="color: #fbbf24;">₦{{ number_format($totalDebtCreated, 0) }}</div>
            </div>
            <div class="summary-card">
                <h4>Current Total Open Debt</h4>
                <div class="val" style="color: #f87171;">₦{{ number_format($totalOpenDebt, 0) }}</div>
            </div>
            <div class="summary-card">
                <h4>Ledger Entries</h4>
                <div class="val" style="color: #60a5fa;">{{ number_format($debtsEntryCount) }}</div>
            </div>
        </div>

        <div class="table-card">
            <div class="table-top-bar">
                <div style="flex: 1; max-width: 320px;">
                    <input type="text" placeholder="⚡ Live filter rows on this page..." onkeyup="filterTableRows('debtsTable', this.value)" style="padding: 0.45rem 0.85rem; font-size: 0.82rem;">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('reports.export.csv', array_merge(['type' => 'debtors'], request()->query())) }}" class="btn btn-secondary" style="padding: 0.45rem 0.85rem; font-size: 0.75rem;">
                        📥 Export CSV
                    </a>
                </div>
            </div>

            <div class="table-wrap">
                <table id="debtsTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Customer Name</th>
                            <th>Transaction Type</th>
                            <th>Amount</th>
                            <th>Balance Remaining After</th>
                            <th>Payment Method / Ref</th>
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($debtLedgers as $entry)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ date('d M Y, h:i A', strtotime($entry->created_at)) }}
                            </td>
                            <td>
                                <strong>{{ $entry->customer->name ?? 'Customer' }}</strong>
                                @if($entry->customer && $entry->customer->phone)
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $entry->customer->phone }}</div>
                                @endif
                            </td>
                            <td>
                                @if($entry->type === 'PAYMENT')
                                    <span class="badge badge-success">💵 Part Payment</span>
                                @elseif($entry->type === 'INVOICE')
                                    <span class="badge badge-danger">💳 Debt Incurred</span>
                                @elseif($entry->type === 'RETURN_CREDIT')
                                    <span class="badge badge-info">🔄 Return Offset</span>
                                @else
                                    <span class="badge badge-secondary">{{ $entry->type }}</span>
                                @endif
                            </td>
                            <td style="font-weight: 800; font-size: 1rem; color: {{ $entry->type === 'PAYMENT' ? '#4ade80' : '#f87171' }};">
                                {{ $entry->type === 'PAYMENT' ? '-' : '+' }}₦{{ number_format($entry->amount, 0) }}
                            </td>
                            <td style="font-weight: 700; color: {{ $entry->balance_after > 0 ? '#f87171' : '#4ade80' }};">
                                ₦{{ number_format($entry->balance_after, 0) }}
                            </td>
                            <td><span class="badge badge-info">{{ $entry->payment_method ?: 'N/A' }}</span></td>
                            <td>{{ $entry->recorded_by }}</td>
                            <td>
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="printGenericVoucher('CUSTOMER PAYMENT RECEIPT', 'REC-{{ substr(md5($entry->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($entry->created_at)) }}', 'Customer', '{{ addslashes($entry->customer->name ?? 'Customer') }}', '{{ $entry->type }}', '#22c55e', [{name: 'Payment via {{ $entry->payment_method ?: 'CASH' }} (Ref: {{ $entry->reference_no ?: 'Standard' }})', qty: '1 entry', note: 'New Balance Owed: ₦{{ number_format($entry->balance_after, 0) }}'}], 'Amount Paid: ₦{{ number_format($entry->amount, 0) }}', '{{ addslashes($entry->recorded_by) }}', 'Customer ledger balance updated.')">
                                        📄 Print Receipt
                                    </button>
                                    <button type="button" class="btn btn-primary" style="padding: 0.35rem 0.65rem; font-size: 0.75rem;" onclick="viewGenericDetails('Customer Debtor Statement Entry', 'REC-{{ substr(md5($entry->id), 0, 8) }}', '{{ date('d M Y, h:i A', strtotime($entry->created_at)) }}', 'Customer Name', '{{ addslashes($entry->customer->name ?? 'Customer') }}', '{{ $entry->type }}', '#22c55e', [{label: 'Transaction Type', val: '{{ $entry->type }}'}, {label: 'Amount Paid', val: '₦{{ number_format($entry->amount, 0) }}', color: '{{ $entry->type === 'PAYMENT' ? '#4ade80' : '#f87171' }}'}, {label: 'Balance Remaining After', val: '₦{{ number_format($entry->balance_after, 0) }}', color: '#fbbf24'}, {label: 'Payment Method', val: '{{ $entry->payment_method ?: 'N/A' }}'}, {label: 'Cashier / Officer', val: '{{ addslashes($entry->recorded_by) }}'}, {label: 'Notes', val: '{{ addslashes($entry->notes ?: 'None') }}'}], 'Ledger balance recalculated automatically.')">
                                        🔍 Details
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                No debt ledger records found matching filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 1.25rem;">
                {{ $debtLedgers->links() }}
            </div>
        </div>
</div>

