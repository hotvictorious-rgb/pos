@extends('layouts.app')

@section('title', 'Role-Based User Guide & Training Center')

@push('styles')
<style>
    .role-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.85rem;
        border-radius: 99px;
        font-size: 0.85rem;
        font-weight: 800;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        margin-bottom: 0.5rem;
    }
    .role-badge-cashier { background: rgba(34, 197, 94, 0.15); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.35); }
    .role-badge-storekeeper { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.35); }
    .role-badge-manager { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.35); }
    .role-badge-viewer { background: rgba(234, 179, 8, 0.15); color: #facc15; border: 1px solid rgba(234, 179, 8, 0.35); }
    .role-badge-admin { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.35); }

    .role-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border);
        padding-bottom: 0.75rem;
    }

    .role-tab-btn {
        padding: 0.75rem 1.25rem;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        color: var(--text-muted);
        font-size: 0.88rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
    }
    .role-tab-btn:hover {
        background: rgba(30, 41, 59, 0.8);
        border-color: #475569;
        color: #f8fafc;
    }
    .role-tab-btn.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(37,99,235,0.3);
    }

    .guide-section { display: none; }
    .guide-section.active { display: block; }

    .duty-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.25);
    }

    .step-box {
        background: rgba(11,15,25,0.7);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.15rem;
        margin-bottom: 0.85rem;
    }

    .step-box h4 {
        margin: 0 0 0.45rem 0;
        font-size: 0.98rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .faq-item {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 14px;
        margin-bottom: 0.75rem;
        overflow: hidden;
    }

    .faq-question {
        padding: 1rem 1.25rem;
        font-weight: 800;
        font-size: 0.92rem;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(31,41,55,0.4);
    }
    .faq-question:hover { background: rgba(55,65,81,0.5); }

    .faq-answer {
        padding: 1.25rem;
        font-size: 0.88rem;
        color: #cbd5e1;
        line-height: 1.6;
        border-top: 1px solid var(--border);
        display: none;
    }
    .faq-item.active .faq-answer { display: block; }
    .faq-item.active .faq-toggle { transform: rotate(180deg); }
    .faq-toggle { transition: transform 0.2s; }
</style>
@endpush

@section('content')

@php
    $currentUser = auth()->user();
    $rawRole = $currentUser?->role ?? 'cashier';
    $isAdmin = $currentUser?->isAdmin() ?? false;
    $isManager = in_array($rawRole, ['manager', 'branch_manager'], true);
    $isStorekeeper = ($rawRole === 'storekeeper');
    $isViewer = in_array($rawRole, ['viewer', 'executive_readonly'], true);
    $isCashier = ($rawRole === 'cashier') || (!$isAdmin && !$isManager && !$isStorekeeper && !$isViewer);
@endphp

    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            @if($isAdmin)
                <span class="role-header-badge role-badge-admin">👑 Store Owner & Administrator</span>
                <h2 style="font-size: 1.6rem; font-weight: 800; margin-top: 0.2rem;">Master Command & Staff Training Guides 📖</h2>
                <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 0.15rem;">
                    Access master administrative operations or switch roles below to review, print, and train staff members on their exact duties.
                </p>
            @elseif($isStorekeeper)
                <span class="role-header-badge role-badge-storekeeper">📦 Storekeeper & Inventory Specialist</span>
                <h2 style="font-size: 1.6rem; font-weight: 800; margin-top: 0.2rem;">Warehouse & Inventory Operations Guide 📦</h2>
                <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 0.15rem;">
                    Your assigned duties: physical receiving, delayed customer pickups, transfer piece-counting, and stock integrity.
                </p>
            @elseif($isManager)
                <span class="role-header-badge role-badge-manager">🏢 Branch Operations Manager</span>
                <h2 style="font-size: 1.6rem; font-weight: 800; margin-top: 0.2rem;">Branch Management & Supervision Guide 🏢</h2>
                <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 0.15rem;">
                    Your managerial duties and supervisory guides for the Cashier and Storekeeper staff under your branch.
                </p>
            @elseif($isViewer)
                <span class="role-header-badge role-badge-viewer">👁️ Executive Observer</span>
                <h2 style="font-size: 1.6rem; font-weight: 800; margin-top: 0.2rem;">Executive Oversight & Business Health Guide 👁️</h2>
                <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 0.15rem;">
                    Your assigned view: real-time business telemetry, inventory valuations, gross revenue, and anti-theft audits.
                </p>
            @else
                <span class="role-header-badge role-badge-cashier">💰 Cashier & Sales Specialist</span>
                <h2 style="font-size: 1.6rem; font-weight: 800; margin-top: 0.2rem;">Cashier Job Duties & POS Training Guide 💰</h2>
                <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 0.15rem;">
                    Your assigned duties: barcode selling, price negotiations, paper receipt tracking, cash/POS tenders, and shift balancing.
                </p>
            @endif
        </div>
        
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <button type="button" class="btn btn-secondary" onclick="window.print()" style="padding: 0.55rem 1rem; font-size: 0.85rem;">
                🖨️ Print Guide
            </button>
            <a href="{{ route('pos.index') }}" class="btn btn-success" style="padding: 0.55rem 1.1rem; font-size: 0.85rem; font-weight: 800;">
                🚀 Open POS
            </a>
        </div>
    </div>

    @if($isAdmin)
        <!-- Administrative Role Switcher Tabs (Visible to Store Owners / Admins Only) -->
        <div class="role-tabs">
            <button class="role-tab-btn active" onclick="showRoleGuide('roleAdmin', this)">👑 Store Owner / Master Guide</button>
            <button class="role-tab-btn" onclick="showRoleGuide('roleCashier', this)">💰 Cashier Guide</button>
            <button class="role-tab-btn" onclick="showRoleGuide('roleStorekeeper', this)">📦 Storekeeper Guide</button>
            <button class="role-tab-btn" onclick="showRoleGuide('roleManager', this)">🏢 Branch Manager Guide</button>
            <button class="role-tab-btn" onclick="showRoleGuide('roleViewer', this)">👁️ Executive Observer Guide</button>
            <button class="role-tab-btn" onclick="showRoleGuide('roleScreens', this)">🖥️ All Tabs & Screens Reference</button>
        </div>
    @elseif($isManager)
        <!-- Branch Manager Switcher Tabs (Supervision access to Cashier & Storekeeper) -->
        <div class="role-tabs">
            <button class="role-tab-btn active" onclick="showRoleGuide('roleManager', this)">🏢 Branch Manager Guide</button>
            <button class="role-tab-btn" onclick="showRoleGuide('roleCashier', this)">💰 Cashier Guide</button>
            <button class="role-tab-btn" onclick="showRoleGuide('roleStorekeeper', this)">📦 Storekeeper Guide</button>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 1. CASHIER GUIDE -->
    <!-- ========================================================================= -->
    @if($isAdmin || $isManager || $isCashier)
    <div id="roleCashier" class="guide-section {{ $isCashier ? 'active' : '' }}">
        <div class="duty-card" style="border-left: 6px solid #22c55e;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <span style="font-size: 2.2rem;">💰</span>
                <div>
                    <h3 style="font-size: 1.35rem; font-weight: 800; color: #4ade80; margin: 0;">Cashier & Sales Specialist Workflow</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.15rem 0 0 0;">Strict step-by-step duties for everyday counter sales, tender collection, and anti-theft rules.</p>
                </div>
            </div>

            <div class="step-box">
                <h4 style="color: #86efac;">1. Making a Sale & Scanning Items</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Navigate to <strong>💰 Sell Goods (POS)</strong> in the sidebar.<br>
                    • Tap any product card or use a barcode scanner. Use <strong>+ / −</strong> buttons to change item quantities.<br>
                    • <strong>Search Filter:</strong> Use the search box to search instantaneously by SKU code (e.g. <code>M10DE</code>), commercial product name, brand, or size.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #60a5fa;">2. Negotiating Prices (Editable Cart Price Box)</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • When a customer buys in bulk or negotiates a bargain price, <strong>click directly into the green Price (₦) box</strong> inside the cart item.<br>
                    • Type the agreed selling price and press Enter. The bill automatically recalculates at the new unit price without affecting stored catalog prices.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #38bdf8;">3. Physical Receipt Slip No (Primary Customer Identifier)</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • <strong>No Phone Number Required:</strong> For walk-in customers who do not provide a phone number, simply type the paper booklet number (e.g. <code>4082</code>) into the <strong>🧾 Physical Receipt Slip No</strong> box.<br>
                    • The system automatically registers them as <code>Customer (Receipt #4082)</code>, allowing immediate checkout for credit, debt, and delayed pickups.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #fbbf24;">4. Goods Handover Golden Law (🟢 SUPPLIED vs. 🟠 NOT SUPPLIED)</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • <strong>🟢 SUPPLIED:</strong> Choose this <em>only</em> if the customer is carrying the goods away with them right now. Physical shelf stock is decremented immediately.<br>
                    • <strong>🟠 NOT SUPPLIED:</strong> Choose this if the customer paid (or part-paid) but will return or send a driver to pick up later. Shelf stock is <strong>not decremented</strong>; units are safely locked in an allocated reservation buffer so shelf counts match physical stock on ground.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #f87171;">5. Handling Part-Payments & Debts</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Select <strong>🤝 Part-Paid / Not Paid</strong> payment tab.<br>
                    • Enter the amount paid now in <strong>Amount Paying Now (₦)</strong> (or type <code>0</code> if completely unpaid).<br>
                    • Select whether the deposit was paid via <strong>💳 POS</strong> or <strong>💵 Cash</strong>.<br>
                    • The system computes the remaining balance and logs it to the customer debt ledger automatically.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #c084fc;">6. Multi-SKU Product Exchange & Swap</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • If a customer brings back past purchases to swap for different items, click <strong>🔄 Exchange / Return Item</strong> in the POS header.<br>
                    • Search the authentic receipt by <strong>Receipt Slip #</strong> or customer phone.<br>
                    • Check each returned product. The credit is <strong>locked to the original purchase unit price</strong>.<br>
                    • Click <strong>Apply Exchange Credit</strong>: the credit is deducted from the new cart items, and the customer only pays the top-up difference.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #facc15;">7. End-of-Day Shift & Cash Drawer Balancing</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • At closing, count the physical banknotes in your cash drawer.<br>
                    • Collect and sum all bank POS card terminal merchant settlement slips.<br>
                    • Compare with the total recorded in your POS summary. Hand over cash to the manager or owner with the signed daily receipt sheet.
                </p>
            </div>
        </div>

        <!-- Cashier FAQs -->
        <h4 style="font-size: 1.1rem; font-weight: 800; margin: 1.5rem 0 0.75rem 0;">💡 Cashier Frequently Asked Questions</h4>

        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ Why does the Complete Sale button stay disabled when opening POS?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                The <strong>Complete Sale & Print</strong> button is intentionally protected by a safety interlock. It remains disabled until at least one product has been clicked and added to your active cart. Once items are in the cart, the button turns green and unlocks immediately.
            </div>
        </div>

        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ Why does the system block checkout saying "Physical Stock Handover Rule"?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                If you select 🟢 <strong>SUPPLIED</strong>, the system checks whether the shop physically has those units on shelf. If physical stock is <code>0</code> or less than requested, you cannot hand over non-existent goods! If the customer is ordering in advance, switch handover to 🟠 <strong>NOT SUPPLIED</strong>.
            </div>
        </div>

        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ Can I void or delete a sale if I make a cashier mistake?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Cashiers cannot delete or void sales for store security and audit compliance. If a mistake occurs, alert your <strong>Store Owner or Branch Manager</strong>. An Administrator can void the erroneous transaction from Universal History with a mandatory audit reason.
            </div>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 2. STOREKEEPER GUIDE -->
    <!-- ========================================================================= -->
    @if($isAdmin || $isManager || $isStorekeeper)
    <div id="roleStorekeeper" class="guide-section {{ $isStorekeeper ? 'active' : '' }}">
        <div class="duty-card" style="border-left: 6px solid #f59e0b;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <span style="font-size: 2.2rem;">📦</span>
                <div>
                    <h3 style="font-size: 1.35rem; font-weight: 800; color: #fbbf24; margin: 0;">Storekeeper & Inventory Logistics Workflow</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.15rem 0 0 0;">Physical inventory counts, receiving goods, releasing customer pickup slips, and transfer piece-counting.</p>
                </div>
            </div>

            <div class="step-box">
                <h4 style="color: #86efac;">1. Receiving New Goods from Suppliers (Stock In)</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Navigate to <strong>📥 Stock In</strong> in the sidebar.<br>
                    • Click <strong>📥 New Goods Arrived</strong>, select the supplier, choose the product SKU, and enter the physical cartons offloaded.<br>
                    • <strong>Strict Counting Rule:</strong> Count every piece physically before confirming. Never sign a supplier delivery note without counting!
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #60a5fa;">2. Fulfilling Customer Delayed Pickups (Pending Orders)</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • When a customer or their transport driver arrives with a physical paper receipt slip, go to <strong>⏳ Pending Orders</strong>.<br>
                    • Search the <strong>Physical Slip #</strong> (e.g. <code>4082</code>) or customer name.<br>
                    • Verify the products listed against the physical paper booklet receipt.<br>
                    • Click <strong>"✓ Handover Goods to Customer"</strong>. This formally marks the order as delivered and deducts the units from physical shelf stock.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #38bdf8;">3. Dispatching Inter-Branch Transfers</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Go to <strong>🚚 Shop Transfers</strong> and click <strong>Dispatch New Transfer</strong>.<br>
                    • Choose the destination branch, enter the driver's name, vehicle number, and product quantities.<br>
                    • Print the official Waybill and give a physical copy to the carrier driver. The items move to <em>IN-TRANSIT</em> status.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #f87171;">4. Accepting Inbound Transfers (Mandatory Piece Count)</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • When a delivery vehicle arrives from another branch, find the transfer under <strong>🚚 Shop Transfers</strong>.<br>
                    • Click <strong>"✅ Accept & Count Goods"</strong>.<br>
                    • <strong>Anti-Theft Protocol:</strong> Physically count every single carton in the presence of the driver. If any item is missing or damaged, record the variance immediately before accepting!
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #c084fc;">5. Logging Damaged or Expired Stock</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Broken, spoiled, or expired goods must never be discarded without logging.<br>
                    • Go to <strong>📉 Damaged Goods</strong> $\rightarrow$ Click <strong>Record Damaged Goods</strong>.<br>
                    • Enter the damaged quantity and a mandatory explanation (e.g. <em>"Crushed carton offloaded from truck"</em>).
                </p>
            </div>
        </div>

        <h4 style="font-size: 1.1rem; font-weight: 800; margin: 1.5rem 0 0.75rem 0;">💡 Storekeeper FAQs</h4>
        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ Can a storekeeper sell goods or change product prices on POS?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                No. Storekeeper accounts are strictly segregated from financial checkout to enforce anti-theft checks and balances. Storekeepers manage inventory movements, counts, and deliveries, while cashiers handle financial transactions.
            </div>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 3. BRANCH MANAGER GUIDE -->
    <!-- ========================================================================= -->
    @if($isAdmin || $isManager)
    <div id="roleManager" class="guide-section {{ $isManager ? 'active' : '' }}">
        <div class="duty-card" style="border-left: 6px solid #3b82f6;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <span style="font-size: 2.2rem;">🏢</span>
                <div>
                    <h3 style="font-size: 1.35rem; font-weight: 800; color: #60a5fa; margin: 0;">Branch Operations Manager Workflow</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.15rem 0 0 0;">Branch oversight, cashier register balancing, customer returns verification, and debtor recovery tracking.</p>
                </div>
            </div>

            <div class="step-box">
                <h4 style="color: #86efac;">1. Daily Shift Balancing & Register Auditing</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Review each cashier's register totals under <strong>📜 Universal History</strong> at the close of every shift.<br>
                    • Verify that physical cash handed over matches the Cash Inflow total, and bank card settlement slips match POS Card Inflows.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #fbbf24;">2. Customer Returns & The Golden Restitution Rule</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Go to <strong>🔄 Returns & Refunds</strong> $\rightarrow$ Click <strong>Process Return</strong>.<br>
                    • Search by <strong>Physical Slip #</strong> or Invoice ID.<br>
                    • <strong>Supplied Return:</strong> Customer carried the goods away earlier $\rightarrow$ Restores physical shelf stock (+Q) and refunds money.<br>
                    • <strong>Unsupplied Return (Buffer Order Cancellation):</strong> Customer paid but never collected goods $\rightarrow$ <strong>0 shelf units added</strong> (goods never left the shop!). The reservation is cancelled and money is refunded without creating phantom inventory.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #38bdf8;">3. Debtor Follow-ups & Debt Recovery Recording</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Open <strong>🤝 Customer Debts</strong> to review outstanding credit balances.<br>
                    • When a customer pays off past debt, click <strong>"Record Payment"</strong>, enter the recovered cash or transfer amount, and issue an official debt recovery receipt.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #f87171;">4. Unsupplied Buffer Oversight</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Regularly review <strong>⏳ Pending Orders</strong> to ensure stock allocated for delayed customer pickup is stored safely and never sold to walk-in buyers.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 4. EXECUTIVE OBSERVER GUIDE -->
    <!-- ========================================================================= -->
    @if($isAdmin || $isViewer)
    <div id="roleViewer" class="guide-section {{ $isViewer ? 'active' : '' }}">
        <div class="duty-card" style="border-left: 6px solid #eab308;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <span style="font-size: 2.2rem;">👁️</span>
                <div>
                    <h3 style="font-size: 1.35rem; font-weight: 800; color: #facc15; margin: 0;">Executive Observer (Read-Only Silent Auditor)</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.15rem 0 0 0;">Comprehensive visibility into money, stock valuations, and audit reports without operational editing risks.</p>
                </div>
            </div>

            <div class="step-box">
                <h4 style="color: #4ade80;">1. Remote Multi-Branch Monitoring</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Use the top <strong>Branch / Location Dropdown</strong> combined with date presets (<em>Today</em>, <em>This Week</em>, <em>This Month</em>, <em>Custom</em>) to analyze revenues, cash collections, and debtor balances across branches.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #60a5fa;">2. Stock Telemetry & Enterprise Valuation</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • View total physical shelf inventory count, monetary valuations (₦), low-stock warnings, and unsupplied order liabilities across the business.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #f87171;">3. Theft & Variance Radar</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Review discrepancy reports in real-time — damaged stock write-offs, transfer discrepancies, or drawer cash variations.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 5. STORE OWNER & SUPER ADMIN GUIDE -->
    <!-- ========================================================================= -->
    @if($isAdmin)
    <div id="roleAdmin" class="guide-section active">
        <div class="duty-card" style="border-left: 6px solid #a855f7;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <span style="font-size: 2.2rem;">👑</span>
                <div>
                    <h3 style="font-size: 1.35rem; font-weight: 800; color: #c084fc; margin: 0;">Store Owner & Super Administrator Command Center</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.15rem 0 0 0;">Master administrative control: Option A clean voiding, user permissions, multi-branch settings, and profit auditing.</p>
                </div>
            </div>

            <div class="step-box">
                <h4 style="color: #ef4444;">1. Option A: Clean Void Reconciliation (Error Reversals)</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • If a sale was registered mistakenly or needs to be completely voided, open <strong>📜 Universal History</strong>.<br>
                    • Click the red <strong>🗑️ Delete / Void</strong> button next to the transaction and provide a mandatory audit reason.<br>
                    • <strong>Automated Clean Reconciliation (Option A):</strong><br>
                    &nbsp;&nbsp;&nbsp;&nbsp;✓ <strong>Physical Shelf Stock:</strong> Automatically restored to ground inventory.<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;✓ <strong>Stock Out Tab:</strong> Original outflow logs are deleted, removing ghost entries completely.<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;✓ <strong>Customer Debt Ledgers:</strong> Any invoice or debtor liability from the voided sale is erased.<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;✓ <strong>Tamper-Evident Security Log:</strong> An indelible audit trail is sealed in Activity Logs recording the Admin's identity, timestamp, and full product breakdown.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #38bdf8;">2. Staff Management & Anti-Theft Permission Barriers</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Navigate to <strong>👥 Staff & Roles</strong> to create workers and assign branch locations.<br>
                    • <strong>Strict Separation of Powers:</strong> Keep Cashier roles separate from Storekeeper roles so no single staff member can both alter stock counts and collect sales cash.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #4ade80;">3. Multi-Branch Operations & Warehouse Isolation</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Under <strong>⚙️ Settings</strong>, create and manage branches (shops and warehouses).<br>
                    • Non-admin staff are strictly branch-scoped: cashiers and storekeepers can only see and operate on inventory in their assigned shop.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #fbbf24;">4. Day-Book Comprehensive Reconciliation (6 Audit Sections)</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    • Under <strong>📊 Reports ➔ Day-Book Tab</strong>, export the comprehensive daily audit report.<br>
                    • Reconciles gross revenue, physical drawer cash, card receipts, new credit issued, debt recoveries, and carried unsupplied order backlogs.
                </p>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 6. MASTER OPERATIONS GUIDE ACROSS ALL SCREENS -->
    <!-- ========================================================================= -->
    <div id="roleScreens" class="guide-section">
        <div class="duty-card" style="border-left: 6px solid #6366f1;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                <span style="font-size: 2.2rem;">🖥️</span>
                <div>
                    <h3 style="font-size: 1.35rem; font-weight: 800; color: #818cf8; margin: 0;">Master Reference Across All Screens & Tabs</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.15rem 0 0 0;">Technical reference for every tab and screen to guarantee 100% data integrity and zero leakage.</p>
                </div>
            </div>

            <div class="step-box">
                <h4 style="color: #4ade80;">POS & Checkout Screen</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    Rapid barcode scanning, instant multi-attribute SKU search, inline bargaining price edits, paper slip number primary identification, Cash/POS/Debt tenders, and physical handover confirmation.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #60a5fa;">Universal History & 8 Ledger Hubs</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    Zero-reload tabs: Sales, Payments, Stock In, Stock Out / Damages, Logistics / In-Transit, Waybills, Returns & Refunds, and Debtors. Live filtered CSV and JSON export engines.
                </p>
            </div>

            <div class="step-box">
                <h4 style="color: #fbbf24;">Pending Orders Backlog</h4>
                <p style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; margin: 0;">
                    Unsupplied orders pending customer pickup with aging breakdown (&lt;24h, 24-48h, 3-7d, &gt;7d). Handover button to authoritatively deduct goods upon physical collection.
                </p>
            </div>
        </div>
    </div>
    @endif

@endsection

@push('scripts')
<script>
function showRoleGuide(roleId, btn) {
    document.querySelectorAll('.role-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.guide-section').forEach(s => s.classList.remove('active'));

    if (btn) btn.classList.add('active');
    const target = document.getElementById(roleId);
    if (target) target.classList.add('active');
}

function toggleFaq(item) {
    item.classList.toggle('active');
}
</script>
@endpush
