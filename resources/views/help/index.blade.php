@extends('layouts.app')

@section('title', 'User Guide & Training Center')

@push('styles')
<style>
    .guide-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .guide-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 1.5rem;
        transition: all 0.2s;
    }
    .guide-card:hover { border-color: #3b82f6; transform: translateY(-2px); }

    .step-box {
        background: rgba(11,15,25,0.6);
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
            <h2 style="font-size: 1.6rem; font-weight: 800;">Learning Center & Operational Guide 📖</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                Step-by-step visual guides and answers for Cashiers, Storekeepers, Branch Managers, and Auditors.
            </p>
        </div>
        <a href="{{ route('pos.index') }}" class="btn btn-success">
            🚀 Go to POS
        </a>
    </div>

    <!-- 1. The Core Golden Law of Hysam Ventures -->
    <div style="background: linear-gradient(135deg, rgba(37,99,235,0.15), rgba(139,92,246,0.15)); border: 1px solid rgba(37,99,235,0.4); border-radius: 20px; padding: 1.5rem; margin-bottom: 2rem;">
        <div style="display: flex; align-items: start; gap: 1rem;">
            <div style="font-size: 2.2rem;">🛡️</div>
            <div>
                <h3 style="font-size: 1.2rem; font-weight: 800; color: #93c5fd; margin-bottom: 0.5rem;">
                    The Golden Law of Physical Closing Stock (Auditor Rule)
                </h3>
                <p style="font-size: 0.9rem; color: #cbd5e1; line-height: 1.6;">
                    <strong>"If an item is physically inside the shop or warehouse, it MUST be counted in Physical Closing Stock."</strong><br>
                    Even if a customer has paid for 10 bags of rice on invoice, as long as the bags have not driven away in a vehicle, they remain locked in the shop's <em>Physical Shelf Count</em> until the official <strong>Handover / Dispatch Note</strong> is stamped.
                </p>
            </div>
        </div>
    </div>

    <!-- Visual Workflow Cards -->
    <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">Visual Step-by-Step Guides</h3>

    <div class="guide-grid">

        <!-- Guide 1: Inter-Branch Transfers -->
        <div class="guide-card" style="border-top: 4px solid #3b82f6;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <span style="font-size: 1.5rem;">🚚</span>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #60a5fa;">How Inter-Branch Transfers Work</h3>
            </div>

            <div class="step-box">
                <strong style="color: #93c5fd;">Step 1: Dispatch Goods (Source Shop)</strong>
                <p style="font-size: 0.85rem; color: #9ca3af; margin-top: 0.25rem;">
                    Origin branch (e.g. Main Depot) goes to <em>Stock In/Out</em> $\rightarrow$ <em>Dispatch Transfer</em>. Selects destination, carrier driver, and quantity. Items enter <strong>In-Transit Buffer</strong>.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #fbbf24;">Step 2: Physical Count at Destination</strong>
                <p style="font-size: 0.85rem; color: #9ca3af; margin-top: 0.25rem;">
                    When driver arrives at destination shop (e.g. Nwaniba Branch), the receiving storekeeper physically counts every carton before tapping <em>Verify & Receive</em>.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #f87171;">Step 3: Automatic Theft/Variance Alert</strong>
                <p style="font-size: 0.85rem; color: #9ca3af; margin-top: 0.25rem;">
                    If 50 bags were sent but only 48 arrived, the system immediately flags a <strong>🚨 THEFT/VARIANCE ALERT</strong> on the Auditor Dashboard with the driver's name and exact missing value!
                </p>
            </div>
        </div>

        <!-- Guide 2: POS Selling & Price Bargaining -->
        <div class="guide-card" style="border-top: 4px solid #16a34a;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <span style="font-size: 1.5rem;">💰</span>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #4ade80;">How to Sell & Negotiate Prices</h3>
            </div>

            <div class="step-box">
                <strong style="color: #86efac;">Step 1: Tap Products into Cart</strong>
                <p style="font-size: 0.85rem; color: #9ca3af; margin-top: 0.25rem;">
                    Tap any product tile or search by name/SKU to add it to the cart. Adjust quantities with the <strong>+ / −</strong> buttons.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #60a5fa;">Step 2: Edit Selling Price (Market Bargaining)</strong>
                <p style="font-size: 0.85rem; color: #9ca3af; margin-top: 0.25rem;">
                    For bulk buyers or market bargaining, simply click the <strong>Price (₦)</strong> box inside the cart drawer and type the agreed negotiated unit price!
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #fde047;">Step 3: Part-Payment / Debt Recording</strong>
                <p style="font-size: 0.85rem; color: #9ca3af; margin-top: 0.25rem;">
                    If customer is paying half today, choose <strong>💳 Part-Payment / Debt</strong>, type the deposit amount, and enter their name. The remaining balance automatically moves to the Debtors Ledger.
                </p>
            </div>
        </div>

        <!-- Guide 3: Sales Returns & Damaged Goods -->
        <div class="guide-card" style="border-top: 4px solid #d97706;">
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <span style="font-size: 1.5rem;">🔄</span>
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #fbbf24;">Returns & Damaged Goods</h3>
            </div>

            <div class="step-box">
                <strong style="color: #fbbf24;">Customer Bringing Back Goods?</strong>
                <p style="font-size: 0.85rem; color: #9ca3af; margin-top: 0.25rem;">
                    Go to <em>Returns & Refunds</em> $\rightarrow$ Select the original invoice $\rightarrow$ Choose items returned. The system automatically restocks the shelf and gives a cash refund or reduces their debt.
                </p>
            </div>

            <div class="step-box">
                <strong style="color: #f87171;">Damaged or Broken Items on Shelf?</strong>
                <p style="font-size: 0.85rem; color: #9ca3af; margin-top: 0.25rem;">
                    Go to <em>Damaged Goods</em> $\rightarrow$ Record damaged quantity with incident reason. Never throw items away without recording; the system must deduct it from physical closing stock legitimately.
                </p>
            </div>
        </div>

    </div>

    <!-- Frequently Asked Questions (FAQ Accordions) -->
    <h3 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1rem;">Frequently Asked Questions (FAQ)</h3>

    <div class="faq-list">

        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ What happens if a customer buys goods but leaves them in the shop for delivery tomorrow?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                When checking out on POS, toggle <strong>"Did customer take goods away today? ➔ NO (Awaiting Pickup)"</strong>.  
                The sale and cash payment will be recorded immediately, but the physical items stay counted in your **Physical Closing Stock**. When the customer's truck arrives tomorrow, go to **⏳ Pickup Orders** and click <strong>"Handover Goods"</strong> to deduct them on ground.
            </div>
        </div>

        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ How does the Cashier End-of-Day (EOD) shift balancing work?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                At the end of every business day, the cashier counts all physical cash in their drawer (e.g. ₦150,000) and enters it in the **Auditor Control Hub**.  
                The system instantly compares this against total cash sales and debt recoveries. If there is a shortage (e.g. ₦5,000 missing), it is immediately logged as a cashier discrepancy for the Auditor to review.
            </div>
        </div>

        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ How can I track customer debt payments when they bring installment cash?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Go to **💳 Customer Debts** in the sidebar. Find the customer's name, click **"💰 Record Payment"**, enter the amount paid (e.g. ₦20,000), and choose Cash, POS, or Transfer. Their debt balance is immediately updated and a printable payment receipt is generated.
            </div>
        </div>

        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ Can I assign different workers to different shop branches?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Yes! In **👥 Workers & Roles**, when creating or editing a worker, you can assign them to a specific branch (e.g. *Main Depot*, *Shop 2*, or *Nwaniba Branch*). The Super Admin / Auditor can see and manage all branches from one centralized screen.
            </div>
        </div>

        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">
                <span>❓ What should I do if a worker leaves or is suspected of misconduct?</span>
                <span class="faq-toggle">▼</span>
            </div>
            <div class="faq-answer">
                Go to **👥 Workers & Roles** and click the red **"🔒 Lock Access"** button on their profile. Their login is immediately disabled, preventing any further POS sales or stock movements.
            </div>
        </div>

    </div>

@endsection

@push('scripts')
<script>
function toggleFaq(item) {
    item.classList.toggle('active');
}
</script>
@endpush
