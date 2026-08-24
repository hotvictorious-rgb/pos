@extends('layouts.app')

@section('title', 'Role-Based User Guide & Training Center')

@push('styles')
<style>
    .role-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--border);
        padding-bottom: 0.5rem;
    }

    .role-tab-btn {
        padding: 0.75rem 1.25rem;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        color: var(--text-muted);
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
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
    }

    .step-box {
        background: rgba(11,15,25,0.7);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 0.75rem;
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
        font-size: 0.95rem;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(31,41,55,0.4);
    }
    .faq-question:hover { background: rgba(55,65,81,0.5); }

    .faq-answer {
        padding: 1.25rem;
        font-size: 0.9rem;
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

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.6rem; font-weight: 800;">Role-Based Training & User Guides 📖</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Select your job role below to view your daily duties, step-by-step guides, and anti-theft rules.
            </p>
        </div>
        <a href="{{ route('pos.index') }}" class="btn btn-success">
            🚀 Open POS
        </a>
    </div>

    <!-- Role Selection Tabs -->
    <div class="role-tabs">
        <button class="role-tab-btn active" onclick="showRoleGuide('roleCashier', this)">💰 Cashier (Sales Only)</button>
        <button class="role-tab-btn" onclick="showRoleGuide('roleSalesStock', this)">💼 Sales & Stock (Combined)</button>
        <button class="role-tab-btn" onclick="showRoleGuide('roleStorekeeper', this)">📦 Storekeeper (Stock Only)</button>
        <button class="role-tab-btn" onclick="showRoleGuide('roleManager', this)">🏢 Branch Manager</button>
        <button class="role-tab-btn" onclick="showRoleGuide('roleAuditor', this)">🛡️ Auditor / Super Admin</button>
        <button class="role-tab-btn" onclick="showRoleGuide('roleViewer', this)">👑 Executive Owner (View-Only)</button>
    </div>

    <!-- ========================================================================= -->
    <!-- VIEW-ONLY: EXECUTIVE OWNER GUIDE -->
    <!-- ========================================================================= -->
    <div id="roleViewer" class="guide-section">
        <div class="duty-card" style="border-left: 6px solid #eab308;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <span style="font-size: 2rem;">👑</span>
                <div>
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: #facc15;">Executive Owner (View-Only / Silent Auditor)</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Designed for Business Owners and Investors who want full visibility into money, stock, and reports from their phone without altering data.</p>
                </div>
            </div>

            <div class="step-box">
                <strong style="color: #4ade80;">1. Live Remote Monitoring with Date Filter Presets</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    The main Executive Dashboard now features fast date filter buttons (<strong>Today</strong>, <strong>Yesterday</strong>, <strong>This Week</strong>, <strong>This Month</strong>, <strong>This Year</strong>, <strong>All-Time</strong>, or <strong>Custom Date Range</strong>). Instantly monitor gross sales revenue, physical cash drawer collections, POS/transfer payments, new debt issued, stock movements in/out, customer refunds, and debt recoveries in that exact time window.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #60a5fa;">2. Multi-Branch Inventory Valuation (₦)</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    See the total physical stock count on ground in each shop branch and the total gross monetary value of your entire inventory.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #fbbf24;">3. Inter-Branch Logistics & In-Transit Tracking</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Monitor shipments moving between branches, verify driver names, and view official printable waybills with staff signatures.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #f87171;">4. Theft & Variance Radar</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Review any discrepancies in real-time — missing transfer goods, damaged stock write-offs, or cashier cash drawer shortages.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #c084fc;">5. Zero Risk of Accidental Edits</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    All action buttons (deleting, editing prices, creating users, changing stock) are safely disabled for this role, so you can browse freely from any mobile device without accidental clicks.
                </p>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- COMBO: SALES & STOCK OFFICER GUIDE -->
    <!-- ========================================================================= -->
    <div id="roleSalesStock" class="guide-section">
        <div class="duty-card" style="border-left: 6px solid #a855f7;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <span style="font-size: 2rem;">💼</span>
                <div>
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: #c084fc;">Sales & Stock Officer (Solo Shop Attendant)</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Ideal for branches with 1 staff who handles POS selling, customer cash, and receiving stock deliveries on ground.</p>
                </div>
            </div>

            <div class="step-box">
                <strong style="color: #4ade80;">1. Selling & Price Bargaining on POS</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Tap products to cart $\rightarrow$ Edit unit price in cart for bargaining $\rightarrow$ Collect Cash/Transfer/Debt $\rightarrow$ Print Receipt.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #60a5fa;">2. Receiving Stock & Accepting Incoming Transfers</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    When supplier or depot driver arrives, go to <strong>🚚 Shop Transfers</strong> $\rightarrow$ Tap <strong>"✅ Accept & Count Goods"</strong> $\rightarrow$ Count physical cartons offloaded $\rightarrow$ Confirm into stock.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #fbbf24;">3. Customer Debt Recovery & Delayed Pickup Delivery</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Collect debt installments under <em>Customer Debts</em> and release delayed orders under <em>Pickup Orders</em> when customer vehicle arrives.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #f87171;">4. Damaged Goods & Daily Review</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Record damaged or broken items under <em>Damaged Goods</em> and review daily sales and debt recovery totals in the <em>Reports Hub</em> at closing time.
                </p>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 1. CASHIER & SALES OFFICER GUIDE -->
    <!-- ========================================================================= -->
    <div id="roleCashier" class="guide-section active">
        <div class="duty-card" style="border-left: 6px solid #22c55e;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <span style="font-size: 2rem;">💰</span>
                <div>
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: #4ade80;">Cashier & Sales Officer Job Duties</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Your main responsibility is selling products, collecting cash/POS payments, and handling customer debts.</p>
                </div>
            </div>

            <div class="step-box">
                <strong style="color: #86efac;">1. How to Make a POS Sale</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Go to <strong>💰 Sell Goods (POS)</strong> in the sidebar. Tap any product tile to add to cart. Use the <strong>+ / −</strong> buttons to change quantity.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #60a5fa;">2. Negotiating Prices (Editable Cart Price)</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    If a customer is buying in bulk or bargaining, click inside the <strong>Price (₦)</strong> box on the cart item and type the agreed negotiated unit price.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #fbbf24;">3. Handling Part-Payments, Not Paid (Full Debt), and Customer Ledgers</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    When a customer is paying partially or buying on credit:
                    <br>• Select <strong>🤝 Part-Paid / Not Paid</strong> payment mode.
                    <br>• In <strong>Amount Paying Now (₦)</strong>, enter the deposit collected (e.g. ₦30,000 for a ₦50,000 bill), or type <strong>0</strong> if totally unpaid.
                    <br>• The system instantly computes the <strong>Remaining Debt Balance</strong> (₦20,000).
                    <br>• Enter the Customer's Name and Phone Number. The system automatically creates/updates their customer debt profile, logs the payment into today's revenue, posts the balance to the debt ledger, and prints <strong>PART-PAID</strong> with the remaining debt balance directly on their receipt.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #f87171;">4. Goods Handover Matrix (Supplied vs. Not Supplied)</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Always confirm physical fulfillment at checkout:
                    <br>• <strong>🟢 SUPPLIED:</strong> Select if the customer is physically carrying goods away right now. Shelf closing stock decrements immediately.
                    <br>• <strong>🟠 NOT SUPPLIED:</strong> Select if the customer paid/part-paid but will send a vehicle tomorrow. Goods stay locked in your shop's stock buffer so your physical closing stock remains 100% accurate for the auditor!
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #93c5fd;">5. The 4 Main Sale State Combinations</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Every sale in the system is classified into one of these transparent states:
                    <br>• <strong>🟢 Paid & Supplied:</strong> Full payment received, goods taken away immediately.
                    <br>• <strong>🟠 Paid & Not Supplied:</strong> Full payment received, goods remain safely in shop for later pickup.
                    <br>• <strong>⚠️ Part-Paid & Supplied:</strong> Deposit collected, customer owes debt, goods taken away.
                    <br>• <strong>⏳ Part-Paid & Not Supplied:</strong> Deposit collected, customer owes debt, goods remain in shop until full collection.
                    <br>• <strong>🔴 Not Paid & Supplied:</strong> Full credit sale (₦0 deposit), customer takes goods away.
                    <br>• <strong>⏳ Not Paid & Not Supplied:</strong> Order reservation (₦0 deposit), goods remain in shop.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #4ade80;">6. Daily Sales & Receipts Audit</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Review your completed sales and customer receipts anytime under <strong>📜 Sales History</strong> to verify your total daily revenue collected and outstanding customer debts.
                </p>
            </div>
        </div>

        <!-- Cashier FAQs -->
        <h4 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 0.75rem;">Cashier & Sales FAQs</h4>
        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ How exactly are Part Payments (Part-Paid) handled in the system?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                When an order is completed as <strong>Part-Paid</strong> (e.g. ₦30,000 paid on a ₦50,000 invoice):
                <ol style="margin-left: 1.25rem; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.35rem;">
                    <li><strong>Revenue Accounting:</strong> Only the actual cash/POS received (₦30,000) is counted in today's collected money.</li>
                    <li><strong>Debtors Ledger:</strong> The remaining balance (₦20,000) is automatically posted to the customer's permanent ledger under <strong>💳 Customer Debts</strong>.</li>
                    <li><strong>Customer Receipt:</strong> The printed receipt clearly states <code>TOTAL: ₦50,000</code>, <code>Amount Paid: ₦30,000 (PART-PAID)</code>, and <code>Debt Balance: ₦20,000</code>.</li>
                    <li><strong>Debt Recovery:</strong> When the customer brings money later, staff records the repayment under <em>Customer Debts</em>, which reduces the balance and prints an installment recovery receipt.</li>
                </ol>
            </div>
        </div>
        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ What is the difference between "Paid & Supplied" vs "Paid & Not Supplied"?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                <strong>Paid & Supplied</strong> means the customer paid and carried their goods away immediately. The items are subtracted from physical shelf stock right away.<br><br>
                <strong>Paid & Not Supplied</strong> means the customer paid in full, but left the cartons in your shop to send their transport vehicle later. The items <strong>remain counted in your shop's physical closing stock</strong>. When their driver arrives, the storekeeper goes to <strong>⏳ Pickup Orders</strong> and taps <strong>"Mark as Supplied"</strong>. This prevents physical count discrepancies during audits!
            </div>
        </div>
        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ What happens if a customer buys on 100% credit without paying any deposit?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Select <strong>🤝 Part-Paid / Not Paid</strong> and enter <strong>0</strong> in the <em>Amount Paying Now</em> box. The sale will be recorded as <strong>Not Paid (Full Debt)</strong>, the full bill will be added to the customer's debt ledger, and the receipt will state <code>NOT PAID & SUPPLIED</code> (or <code>NOT PAID & NOT SUPPLIED</code>).
            </div>
        </div>
        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ Are past sales transactions editable? How are cashier mistakes handled?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                <strong>No. Invoices cannot be silently modified or deleted</strong> to prevent fraud and theft. If a cashier made a mistake or a customer returns items, go to <strong>🔄 Returns & Refunds</strong> in the sidebar. Select the invoice, choose the returned items, and refund the cash or deduct their debt balance. This restores physical stock and logs an immutable audit trail.
            </div>
        </div>
        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ Who creates products, and who adds stock quantity?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Only the <strong>Auditor / Super Admin</strong> can create, edit, or bulk-import new products into the central catalog (this prevents rogue staff from introducing ghost items). However, <strong>Branch Managers</strong>, <strong>Storekeepers</strong>, and <strong>Sales & Stock Officers</strong> can add stock quantities at any time via <strong>📦 Stock In / Out ➔ 📥 New Goods Arrived</strong>!
            </div>
        </div>
        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ What if a customer brings money later to pay their debt?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Go to <strong>💳 Customer Debts</strong> in the sidebar. Click <strong>"💰 Record Payment"</strong> next to their name, type the amount received, and print their updated receipt.
            </div>
        </div>
        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ Can I use the calculator while making a sale?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Yes! Click the <strong>🧮 Calculator</strong> button in the top header at any time to open the keypad without leaving your POS screen.
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. STOREKEEPER & INVENTORY LEAD GUIDE -->
    <!-- ========================================================================= -->
    <div id="roleStorekeeper" class="guide-section">
        <div class="duty-card" style="border-left: 6px solid #3b82f6;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <span style="font-size: 2rem;">📦</span>
                <div>
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: #60a5fa;">Storekeeper & Inventory Lead Job Duties</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Your main responsibility is physical stock count accuracy, accepting transfers, releasing pickups, and logging damages.</p>
                </div>
            </div>

            <div class="step-box">
                <strong style="color: #86efac;">1. Receiving New Goods from Suppliers (Stock In)</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Go to <strong>📦 Stock In / Out</strong> in the sidebar. Click <strong>📥 New Goods Arrived</strong>, select the product, enter quantity offloaded from supplier truck, and save.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #60a5fa;">2. Accepting & Counting Inter-Branch Transfers</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    When a carrier arrives from another branch, go to <strong>🚚 Shop Transfers</strong> in the sidebar. Locate the shipment card and click <strong>"✅ Accept & Count Goods"</strong>. Physically count every carton before clicking confirm.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #fbbf24;">3. Releasing Customer Pickups (Delayed Orders)</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    When a customer comes to carry goods they bought earlier, go to <strong>⏳ Pickup Orders</strong> in the sidebar and tap <strong>"✓ Handover Goods to Customer"</strong>. This deducts the items from physical closing stock count.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #f87171;">4. Logging Damaged or Expired Stock</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Never throw broken or expired items away without recording. Go to <strong>📉 Damaged Goods</strong> $\rightarrow$ Click <strong>Record Damaged Goods</strong> $\rightarrow$ Enter quantity and incident note.
                </p>
            </div>
        </div>

        <!-- Storekeeper FAQs -->
        <h4 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 0.75rem;">Storekeeper FAQs</h4>
        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ What happens if 50 cartons were sent on transfer but only 48 cartons arrived?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                In the Accept modal, enter the exact counted quantity: <strong>48</strong>. The system will add only 48 to your shop's stock and automatically raise a <strong>🚨 THEFT/DISCREPANCY ALERT</strong> for the Auditor showing that 2 units were lost in transit under the carrier's name.
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 3. BRANCH MANAGER GUIDE -->
    <!-- ========================================================================= -->
    <div id="roleManager" class="guide-section">
        <div class="duty-card" style="border-left: 6px solid #d97706;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <span style="font-size: 2rem;">🏢</span>
                <div>
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: #fbbf24;">Branch Manager Job Duties</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Your main responsibility is branch oversight, managing local staff, approving returns, and dispatching transfers.</p>
                </div>
            </div>

            <div class="step-box">
                <strong style="color: #fbbf24;">1. Processing Customer Returns & Refunds</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Go to <strong>🔄 Returns & Refunds</strong>. Select the original invoice ref, choose items returned, and select whether to refund cash or deduct from customer's debt balance. Stock is automatically restored to shelves.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #60a5fa;">2. Sending Transfers to Other Branches</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Go to <strong>🚚 Shop Transfers</strong> $\rightarrow$ Click <strong>Dispatch New Transfer</strong> $\rightarrow$ Select destination branch, enter driver name, and specify product quantities.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #86efac;">3. Reviewing Branch Sales & Transactions</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Go to <strong>📑 Sales History</strong>. Filter by <em>Today</em>, <em>This Week</em>, or specific cashiers to monitor revenue, collected cash, and outstanding debts.
                </p>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 4. AUDITOR & SUPER ADMIN GUIDE -->
    <!-- ========================================================================= -->
    <div id="roleAuditor" class="guide-section">
        <div class="duty-card" style="border-left: 6px solid #dc2626;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                <span style="font-size: 2rem;">🛡️</span>
                <div>
                    <h3 style="font-size: 1.3rem; font-weight: 800; color: #f87171;">Auditor & Super Admin Job Duties</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Your main responsibility is anti-theft oversight, inventory reconciliation, staff access control, and system security.</p>
                </div>
            </div>

            <div class="step-box">
                <strong style="color: #f87171;">1. The Theft & Variance Radar</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Go to <strong>🚨 Auditor Control Hub</strong> in the sidebar. Review the Discrepancy Radar for missing transfer items, unaccounted write-offs, or cashier cash shortages.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #60a5fa;">2. Multi-Branch Physical Closing Stock Valuation</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    In the Auditor Hub, view the complete stock matrix comparing physical on-ground counts against total inventory monetary value across all shops.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #86efac;">3. Creating Workers & Instant Anti-Theft Account Lock</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Go to <strong>👥 Workers & Roles</strong>. Create new staff with assigned roles and branch shops. If any staff is suspected of theft, click <strong>"🔒 Lock Access"</strong> to block their account immediately.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #fbbf24;">4. System Settings & Database Backups</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    Go to <strong>⚙️ System Settings</strong> to customize receipt footers, low stock alert limits, add new branch shops, and download one-click database backup snapshots.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #4ade80;">5. Universal History & AI Data Exports (CSV / JSON across all 8 Tabs)</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    In <strong>📜 Universal History & Ledgers</strong>, all 8 transaction tabs (<em>Sales, Stock In, Stock Out, In-Transit Buffer, Incoming Transfers, Returns, Refunds, and Customer Debts</em>) feature live filtered <strong>"📥 Export Filtered CSV"</strong> and <strong>"📄 Export JSON"</strong> buttons that stream only records matching your active filters for Excel, Google Sheets, or auditing.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #93c5fd;">6. Printable Transfer Waybills</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    In <strong>🚚 Shop Transfers</strong> or the Reports tab, click <strong>🖨️ Waybill</strong> next to any transfer to print an official delivery note with signature boxes for the dispatch officer, carrier driver, and storekeeper.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #f87171;">7. Secure Sign In & Topbar One-Click Log Out</strong>
                <p style="font-size: 0.85rem; color: #cbd5e1; margin-top: 0.35rem;">
                    All workers must sign in at <strong>/login</strong> using their work email and password. When closing shift or stepping away from the computer, click <strong>🚪 Log Out</strong> at the top right to lock the session and protect financial data.
                </p>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function showRoleGuide(roleId, btn) {
    document.querySelectorAll('.role-tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.guide-section').forEach(s => s.classList.remove('active'));

    btn.classList.add('active');
    document.getElementById(roleId).classList.add('active');
}

function toggleFaq(item) {
    item.classList.toggle('active');
}
</script>
@endpush
