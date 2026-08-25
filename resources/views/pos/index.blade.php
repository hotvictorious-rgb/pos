@extends('layouts.app')

@section('title', 'Point of Sale (POS)')

@push('styles')
<style>
    .pos-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(380px, 440px);
        gap: 1.25rem;
        align-items: start;
    }

    /* Product Catalog Grid */
    .catalog-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .category-pills {
        display: flex;
        gap: 0.4rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .cat-btn {
        padding: 0.4rem 0.85rem;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 99px;
        color: var(--text-muted);
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s;
    }
    .cat-btn.active, .cat-btn:hover {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    .product-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
        max-height: calc(100vh - 200px);
        overflow-y: auto;
        padding-right: 0.35rem;
    }

    .product-card {
        background: var(--card-bg);
        border: 2px solid var(--border);
        border-radius: 16px;
        padding: 0.85rem 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
    }

    .product-card:hover {
        transform: translateY(-2px);
        border-color: #3b82f6;
        box-shadow: 0 8px 20px rgba(0,0,0,0.35);
        background: rgba(30, 41, 59, 0.75);
    }
    .product-card:active { transform: scale(0.98); }

    .p-name {
        font-weight: 800;
        font-size: 0.95rem;
        color: #f8fafc;
        margin-bottom: 0.15rem;
        line-height: 1.25;
    }

    .p-price {
        font-size: 1.1rem;
        font-weight: 800;
        color: #4ade80;
    }

    .p-stock-badge {
        font-size: 0.72rem;
        padding: 0.2rem 0.5rem;
        border-radius: 6px;
        font-weight: 700;
        white-space: nowrap;
    }

    /* Cart Drawer */
    .cart-drawer {
        background: var(--card-bg);
        border: 2px solid var(--border);
        border-radius: 20px;
        padding: 1.25rem;
        box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        position: sticky;
        top: 80px;
        max-height: calc(100vh - 100px);
        overflow-y: auto;
    }

    .cart-title {
        font-size: 1.15rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 0.75rem;
    }

    .cart-items-list {
        max-height: 24vh;
        overflow-y: auto;
        margin-bottom: 0.75rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding-right: 0.25rem;
    }

    .cart-item {
        background: rgba(15,23,42,0.6);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.65rem 0.85rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .qty-controls {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .qty-btn {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: var(--card-bg);
        border: 1px solid var(--border);
        color: #f8fafc;
        font-size: 1.1rem;
        font-weight: 800;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .qty-btn:active { transform: scale(0.9); }

    /* Handover / Physical Stock Box */
    .handover-box {
        background: rgba(217, 119, 6, 0.12);
        border: 2px solid rgba(217, 119, 6, 0.4);
        border-radius: 14px;
        padding: 0.85rem;
        margin-bottom: 0.85rem;
    }

    .handover-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-top: 0.4rem;
    }

    .radio-card {
        padding: 0.6rem;
        border-radius: 10px;
        border: 2px solid var(--border);
        background: var(--card-bg);
        cursor: pointer;
        text-align: center;
        font-size: 0.8rem;
        font-weight: 700;
        transition: all 0.2s;
    }

    .radio-card input { display: none; }
    .radio-card.selected-yes {
        border-color: #22c55e;
        background: rgba(34,197,94,0.15);
        color: #4ade80;
    }
    .radio-card.selected-no {
        border-color: #f59e0b;
        background: rgba(217,119,6,0.15);
        color: #fbbf24;
    }

    /* Payment Tabs */
    .pay-tabs {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.4rem;
        margin-bottom: 0.75rem;
    }

    .pay-tab {
        padding: 0.5rem 0.25rem;
        background: rgba(15,23,42,0.6);
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        text-align: center;
        color: var(--text-muted);
    }
    .pay-tab.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    @media (max-width: 960px) {
        .pos-layout { grid-template-columns: 1fr; }
        .product-grid { grid-template-columns: repeat(2, 1fr); max-height: 50vh; }
        .cart-drawer { position: static; max-height: none; }
    }
</style>
@endpush

@section('content')

<div class="pos-layout">

    <!-- Left Column: Product Catalog in 2 Clean Columns -->
    <div>
        <div class="catalog-header">
            <div>
                <h2 style="font-size: 1.35rem; font-weight: 800;">Point of Sale 💰</h2>
                <p style="font-size: 0.82rem; color: var(--text-muted);">
                    Selling from: <strong style="color: #60a5fa;">{{ $activeWarehouse->name }}</strong>
                </p>
            </div>

            <!-- Instant Search -->
            <div style="flex: 1; max-width: 300px;">
                <input type="text" id="searchInput" placeholder="🔍 Search item name or SKU..." onkeyup="filterProducts()">
            </div>
        </div>

        <!-- Category Pills -->
        <div class="category-pills">
            <button class="cat-btn active" onclick="filterCategory('ALL', this)">All Items</button>
            @foreach($categories as $cat)
                <button class="cat-btn" onclick="filterCategory('{{ $cat }}', this)">{{ $cat }}</button>
            @endforeach
        </div>

        <!-- Product Cards Grid (2 Columns) -->
        <div class="product-grid" id="productGrid">
            @forelse($products as $product)
            <div class="product-card"
                 data-id="{{ $product->id }}"
                 data-name="{{ $product->name }}"
                 data-code="{{ $product->code }}"
                 data-brand="{{ $product->brand }}"
                 data-size="{{ $product->size }}"
                 data-price="{{ $product->unitPrice }}"
                 data-category="{{ $product->category }}"
                 data-stock="{{ $product->physical_stock }}"
                 onclick="addToCart('{{ $product->id }}', '{{ addslashes($product->name) }}', {{ $product->unitPrice }}, {{ $product->physical_stock }})">
                <div style="flex: 1; min-width: 0;">
                    <div class="p-name" title="{{ $product->name }}">{{ $product->name }}</div>
                    <div style="font-size: 0.72rem; color: #94a3b8; margin-bottom: 0.25rem;">
                        SKU: <span style="color: #93c5fd; font-weight: 700;">{{ $product->code }}</span> · <span style="color: #c084fc;">{{ $product->category }}</span>
                    </div>
                    <div class="p-price">₦{{ number_format($product->unitPrice, 0) }}</div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.35rem;">
                    @if($product->physical_stock > 0)
                        <span class="p-stock-badge badge-success">✓ {{ $product->physical_stock }} In Stock</span>
                    @else
                        <span class="p-stock-badge badge-danger">0 (Out of Stock)</span>
                    @endif
                    <span style="font-size: 0.75rem; font-weight: 800; color: #3b82f6; background: rgba(59,130,246,0.15); padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3);">
                        + Add
                    </span>
                </div>
            </div>
            @empty
            <div style="grid-column: 1/-1; text-align: center; padding: 3rem; background: var(--card-bg); border-radius: 16px;">
                <div style="font-size: 3rem; margin-bottom: 0.5rem;">📦</div>
                <h3>No Products Found</h3>
                <p style="color: var(--text-muted);">Add products in Stock Management first.</p>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Right Column: Interactive Cart Drawer -->
    <div class="cart-drawer">
        <div class="cart-title">
            <span>🛒 Current Sale</span>
            <button class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;" onclick="clearCart()">
                Clear All
            </button>
        </div>

        <form id="checkoutForm" method="POST" action="{{ route('pos.checkout') }}">
            @csrf
            <input type="hidden" name="warehouse_id" value="{{ $activeWarehouse->id }}">
            <input type="hidden" name="totalAmount" id="hiddenTotal" value="0">
            <input type="hidden" name="paidAmount" id="hiddenPaid" value="0">
            <input type="hidden" name="cashAmount" id="hiddenCash" value="0">
            <input type="hidden" name="posAmount" id="hiddenPos" value="0">
            <input type="hidden" name="transferAmount" id="hiddenTransfer" value="0">

            <!-- Cart Items Container -->
            <div class="cart-items-list" id="cartItemsList">
                <div style="text-align: center; color: var(--text-muted); padding: 2rem 0;" id="emptyCartMessage">
                    Tap any item on the left to add to sale 👈
                </div>
            </div>
            <!-- Customer Account Selector & Live Debt / Credit Limit Badges -->
            <div style="background: rgba(30,41,59,0.5); border: 1px solid rgba(59,130,246,0.3); border-radius: 12px; padding: 0.75rem; margin-bottom: 0.75rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                    <label style="font-size: 0.78rem; font-weight: 800; color: #93c5fd; text-transform: uppercase; margin-bottom: 0;">
                        👤 Customer Account
                    </label>
                    <button type="button" class="btn btn-secondary" onclick="openQuickCustomerModal()" style="padding: 0.2rem 0.55rem; font-size: 0.72rem; border-color: #3b82f6; color: #93c5fd;">
                        ➕ Quick Add
                    </button>
                </div>
                
                <select id="customerSelect" style="width: 100%; padding: 0.45rem 0.65rem; font-size: 0.82rem; background: #0b0f19; border: 1px solid #475569; border-radius: 8px; color: #f8fafc; margin-bottom: 0.5rem;" onchange="onCustomerSelected(this)">
                    <option value="" data-name="Walk-in Customer" data-phone="" data-debt="0" data-limit="0" data-code="">-- 🛒 Walk-in Customer (Paid in Full Only) --</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" 
                                data-id="{{ $c->id }}"
                                data-name="{{ $c->name }}" 
                                data-phone="{{ $c->phone }}" 
                                data-debt="{{ $c->total_debt }}" 
                                data-limit="{{ $c->credit_limit }}"
                                data-code="{{ $c->customer_code }}">
                            {{ $c->name }} ({{ $c->phone ?: 'No Phone' }}) [{{ $c->customer_code }}] — Debt: ₦{{ number_format($c->total_debt) }}
                        </option>
                    @endforeach
                </select>

                <input type="hidden" name="customerId" id="hiddenCustomerId" value="">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.72rem;">Customer Name <span id="custNameReq" style="color: #f87171; display: none;">*</span></label>
                        <input type="text" name="customerName" id="customerNameInput" placeholder="Walk-in (or Name)" style="padding: 0.4rem 0.6rem; font-size: 0.82rem;" oninput="onManualCustomerTyping()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.72rem;">Phone Number <span id="custPhoneReq" style="color: #f87171; display: none;">* (Required for Credit/Pickup)</span></label>
                        <input type="text" name="customerPhone" id="customerPhoneInput" placeholder="080..." style="padding: 0.4rem 0.6rem; font-size: 0.82rem;" oninput="onManualPhoneTyping()">
                    </div>
                </div>

                <!-- Live Debt & Credit Limit Badge for Selected Customer -->
                <div id="customerFinancialBadge" style="display: none; margin-top: 0.5rem; background: rgba(15,23,42,0.85); border: 1px solid #334155; border-radius: 8px; padding: 0.45rem 0.65rem; font-size: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Code: <strong id="badgeCustCode" style="color: #60a5fa;">CUST-0001</strong></span>
                        <span>Current Debt: <strong id="badgeCustDebt" style="color: #f87171;">₦0</strong></span>
                        <span>Credit Limit: <strong id="badgeCustLimit" style="color: #4ade80;">Unlimited</strong></span>
                    </div>
                </div>
            </div>

            <!-- Total Amount Card -->
            <div style="background: rgba(15,23,42,0.8); border: 2px solid #334155; border-radius: 14px; padding: 1rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted);">
                    <span>Total Bill:</span>
                    <span style="font-size: 1.5rem; font-weight: 800; color: #4ade80;" id="displayTotal">₦0.00</span>
                </div>
            </div>

            <!-- Handover / Physical Stock Rule Selector -->
            <div class="handover-box">
                <div style="font-size: 0.8rem; font-weight: 800; color: #fbbf24; text-transform: uppercase;">
                    📦 Goods Delivery: Supplied Now or Not Supplied?
                </div>
                <div class="handover-options">
                    <label class="radio-card selected-yes" id="labelSuppliedYes" onclick="selectHandover('yes')">
                        <input type="radio" name="is_supplied" value="yes" checked id="radioYes">
                        <div>🟢 SUPPLIED</div>
                        <div style="font-size: 0.7rem; opacity: 0.8;">Customer took goods away (Deduct stock)</div>
                    </label>

                    <label class="radio-card" id="labelSuppliedNo" onclick="selectHandover('no')">
                        <input type="radio" name="is_supplied" value="no" id="radioNo">
                        <div>🟠 NOT SUPPLIED</div>
                        <div style="font-size: 0.7rem; opacity: 0.8;">Goods stay in shop for pickup (Keep in stock)</div>
                    </label>
                </div>
            </div>

            <!-- Payment Breakdown Tabs -->
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem; text-transform: uppercase;">
                💳 Payment Status & Method
            </div>
            <div class="pay-tabs">
                <div class="pay-tab active" id="tabCash" onclick="selectPaymentMode('CASH')">💵 Paid (Cash)</div>
                <div class="pay-tab" id="tabPos" onclick="selectPaymentMode('POS')">💳 Paid (POS/Bank)</div>
                <div class="pay-tab" id="tabDebt" onclick="selectPaymentMode('DEBT')">🤝 Part-Paid / Not Paid</div>
            </div>

            <!-- Part-Payment Input (Visible when Part-Paid / Not Paid is selected) -->
            <div id="debtBox" style="display: none; background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.3); border-radius: 12px; padding: 0.75rem; margin-bottom: 1rem;">
                <label style="color: #c084fc;">Amount Paying Now (₦) [Part-Paid or 0 for Not Paid]:</label>
                <input type="number" id="partPayInput" placeholder="e.g. 5000 (or 0 if totally unpaid)" onkeyup="updateDebtCalculation()" step="any">
                <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #cbd5e1;">
                    <span>Remaining Debt Balance:</span>
                    <strong style="color: #f87171;" id="remainingDebtDisplay">₦0</strong>
                </div>
            </div>

            <!-- Complete Sale Button -->
            <button type="button" class="btn btn-success btn-lg btn-block" onclick="submitSale()" id="completeSaleBtn" disabled style="opacity: 0.5; cursor: not-allowed;">
                ✅ Complete Sale & Print
            </button>
        </form>
    </div>

</div>

<!-- Quick Register Customer Modal -->
<div id="modalQuickCustomer" class="modal-backdrop" style="display: none; z-index: 1050;">
    <div class="modal" style="max-width: 420px; padding: 1.5rem; background: #0f172a; border: 2px solid #3b82f6; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.8);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: #f8fafc; display: flex; align-items: center; gap: 0.5rem;">
                <span>👤</span> Quick Register Customer
            </h3>
            <button type="button" onclick="closeQuickCustomerModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.25rem; cursor: pointer;">✕</button>
        </div>
        <p style="font-size: 0.78rem; color: #94a3b8; margin-bottom: 1rem;">
            Enforce verified phone number and unique account code for debt tracking & delayed pickups.
        </p>

        <form id="quickCustomerForm" onsubmit="submitQuickCustomer(event)">
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label style="font-size: 0.75rem;">Full Customer / Business Name *</label>
                <input type="text" id="qc_name" required placeholder="e.g. Alhaji Musa Stores">
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label style="font-size: 0.75rem;">GSM Phone Number (Unique Identifier) *</label>
                <input type="tel" id="qc_phone" required placeholder="e.g. 08012345678" pattern="[0-9+ ]{7,20}">
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label style="font-size: 0.75rem;">Shop / Delivery Address</label>
                <input type="text" id="qc_address" placeholder="e.g. Shop 12 Main Market">
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label style="font-size: 0.75rem;">Credit Limit (₦) [0 for Unlimited]</label>
                <input type="number" id="qc_credit_limit" placeholder="e.g. 100000" min="0" step="any">
            </div>

            <div style="display: flex; gap: 0.5rem; margin-top: 1.25rem;">
                <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeQuickCustomerModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSaveQuickCust" style="flex: 1.2;">💾 Save Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- POS Checkout Confirmation Modal (What Will Happen) -->
<div id="modalSaleConfirm" class="modal-backdrop" style="display: none;">
    <div class="modal" style="max-width: 480px; padding: 1.5rem; background: #0f172a; border: 2px solid #3b82f6; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.7); animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        <div style="text-align: center; margin-bottom: 1.25rem;">
            <div style="font-size: 2.5rem; margin-bottom: 0.25rem;">⚡</div>
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #f8fafc;">Confirm Sale Transaction</h3>
            <p style="font-size: 0.8rem; color: #94a3b8;">Review products and payment before completing:</p>
        </div>

        <!-- Products & Quantities Bought -->
        <div style="background: rgba(11,15,25,0.75); border: 1px solid var(--border); border-radius: 12px; padding: 0.85rem; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span style="font-size: 0.75rem; font-weight: 800; color: #93c5fd; text-transform: uppercase; letter-spacing: 0.05em;">🛍️ Items Bought:</span>
                <span style="font-size: 0.75rem; color: #94a3b8;"><strong id="confirmItemsTotalUnits" style="color: #fbbf24;">0</strong> total units</span>
            </div>
            <div id="confirmItemsList" style="display: flex; flex-direction: column; gap: 0.45rem; max-height: 150px; overflow-y: auto; padding-right: 0.25rem;">
                <!-- Dynamically populated line items -->
            </div>
        </div>

        <div style="background: rgba(15,23,42,0.85); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem; font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.6rem;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #334155; padding-bottom: 0.4rem;">
                <span style="color: #94a3b8;">Customer:</span>
                <strong id="confirmCustName" style="color: #f8fafc;">Walk-in Customer</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #334155; padding-bottom: 0.4rem;">
                <span style="color: #94a3b8;">Total Bill:</span>
                <strong id="confirmTotalBill" style="color: #4ade80; font-size: 1.05rem;">₦0</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #334155; padding-bottom: 0.4rem;">
                <span style="color: #94a3b8;">Amount Paying Now:</span>
                <strong id="confirmPayingNow" style="color: #60a5fa;">₦0</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #334155; padding-bottom: 0.4rem;">
                <span style="color: #94a3b8;">Debt Ledger Impact:</span>
                <strong id="confirmDebtLedger" style="color: #f87171;">₦0</strong>
            </div>
            <div style="padding-top: 0.2rem;">
                <span style="color: #94a3b8; display: block; margin-bottom: 0.25rem;">📦 Stock Fulfillment Impact:</span>
                <div id="confirmStockImpact" style="font-weight: 700; padding: 0.5rem 0.75rem; border-radius: 8px; font-size: 0.8rem;"></div>
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <button type="button" class="btn btn-secondary" style="flex: 1; padding: 0.75rem;" onclick="closeSaleConfirm()">
                ✕ Cancel / Edit
            </button>
            <button type="button" class="btn btn-success" style="flex: 1.3; padding: 0.75rem; font-weight: 800;" onclick="finalProceedSale()">
                ✅ Yes, Complete Sale
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let cart = [];
let paymentMode = 'CASH';

function addToCart(id, name, price, stock) {
    const existing = cart.find(i => i.id === id);
    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({ id, name, price, qty: 1 });
    }
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) {
        cart = cart.filter(i => i.id !== id);
    }
    renderCart();
}

function clearCart() {
    cart = [];
    renderCart();
}

function renderCart() {
    const list = document.getElementById('cartItemsList');
    const emptyMsg = document.getElementById('emptyCartMessage');
    const btn = document.getElementById('completeSaleBtn');

    if (cart.length === 0) {
        list.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:2rem 0;" id="emptyCartMessage">Tap any item on the left to add to sale 👈</div>';
        document.getElementById('displayTotal').textContent = '₦0';
        document.getElementById('hiddenTotal').value = 0;
        btn.disabled = true;
        btn.style.opacity = 0.5;
        btn.style.cursor = 'not-allowed';
        return;
    }

    let html = '';
    let total = 0;

    cart.forEach((item, index) => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;

        html += `
        <div class="cart-item">
            <div>
                <input type="hidden" name="items[${index}][productId]" value="${item.id}">
                <input type="hidden" name="items[${index}][quantity]" value="${item.qty}">
                <input type="hidden" name="items[${index}][unitPrice]" value="${item.price}">
                <div style="font-weight:700;font-size:0.9rem;">${item.name}</div>
                <div style="font-size:0.75rem;color:#94a3b8;display:flex;align-items:center;gap:0.35rem;margin-top:0.25rem;">
                    <span>Price (₦):</span>
                    <input type="number" step="any" min="0" value="${item.price}" 
                           style="width:90px;padding:0.2rem 0.4rem;font-size:0.8rem;background:#0b0f19;border:1px solid #475569;border-radius:6px;color:#4ade80;font-weight:700;" 
                           onchange="updateItemPrice('${item.id}', this.value)" title="Click to edit selling price for market negotiation / bulk discount">
                    <span>x ${item.qty}</span>
                </div>
            </div>
            <div class="qty-controls">
                <button type="button" class="qty-btn" onclick="updateQty('${item.id}', -1)">−</button>
                <span style="font-weight:800;font-size:1rem;min-width:20px;text-align:center;">${item.qty}</span>
                <button type="button" class="qty-btn" onclick="updateQty('${item.id}', 1)">+</button>
            </div>
        </div>
        `;
    });

    list.innerHTML = html;
    document.getElementById('displayTotal').textContent = '₦' + Math.round(total).toLocaleString('en-US');
    document.getElementById('hiddenTotal').value = total;

    updateDebtCalculation();

    btn.disabled = false;
    btn.style.opacity = 1;
    btn.style.cursor = 'pointer';
}

function updateItemPrice(id, newPrice) {
    const p = parseFloat(newPrice);
    if (isNaN(p) || p < 0) return;
    const item = cart.find(i => i.id === id);
    if (item) {
        item.price = p;
        renderCart();
    }
}


function selectHandover(val) {
    const yesLabel = document.getElementById('labelSuppliedYes');
    const noLabel = document.getElementById('labelSuppliedNo');
    const radioYes = document.getElementById('radioYes');
    const radioNo = document.getElementById('radioNo');

    if (val === 'yes') {
        radioYes.checked = true;
        yesLabel.className = 'radio-card selected-yes';
        noLabel.className = 'radio-card';
    } else {
        radioNo.checked = true;
        noLabel.className = 'radio-card selected-no';
        yesLabel.className = 'radio-card';
    }
    updateCustomerRequirements();
}

function selectPaymentMode(mode) {
    paymentMode = mode;
    document.getElementById('tabCash').className = mode === 'CASH' ? 'pay-tab active' : 'pay-tab';
    document.getElementById('tabPos').className = mode === 'POS' ? 'pay-tab active' : 'pay-tab';
    document.getElementById('tabDebt').className = mode === 'DEBT' ? 'pay-tab active' : 'pay-tab';

    const debtBox = document.getElementById('debtBox');
    debtBox.style.display = mode === 'DEBT' ? 'block' : 'none';

    updateDebtCalculation();
    updateCustomerRequirements();
}

function updateCustomerRequirements() {
    const isSupplied = document.getElementById('radioYes').checked;
    const isDebt = (paymentMode === 'DEBT');
    const isStrict = isDebt || !isSupplied;

    const nameReq = document.getElementById('custNameReq');
    const phoneReq = document.getElementById('custPhoneReq');
    if (nameReq) nameReq.style.display = isStrict ? 'inline' : 'none';
    if (phoneReq) phoneReq.style.display = isStrict ? 'inline' : 'none';
}

function onCustomerSelected(sel) {
    const opt = sel.options[sel.selectedIndex];
    const custId = opt.value;
    const name = opt.getAttribute('data-name') || '';
    const phone = opt.getAttribute('data-phone') || '';
    const debt = parseFloat(opt.getAttribute('data-debt') || 0);
    const limit = parseFloat(opt.getAttribute('data-limit') || 0);
    const code = opt.getAttribute('data-code') || '';

    document.getElementById('hiddenCustomerId').value = custId;
    document.getElementById('customerNameInput').value = custId ? name : '';
    document.getElementById('customerPhoneInput').value = phone;

    const badge = document.getElementById('customerFinancialBadge');
    if (custId) {
        badge.style.display = 'block';
        document.getElementById('badgeCustCode').textContent = code;
        document.getElementById('badgeCustDebt').textContent = '₦' + Math.round(debt).toLocaleString('en-US');
        document.getElementById('badgeCustLimit').textContent = limit > 0 ? ('₦' + Math.round(limit).toLocaleString('en-US')) : 'Unlimited';
    } else {
        badge.style.display = 'none';
    }
}

function onManualCustomerTyping() {
    const sel = document.getElementById('customerSelect');
    if (sel.value) {
        sel.value = "";
        document.getElementById('hiddenCustomerId').value = "";
        document.getElementById('customerFinancialBadge').style.display = 'none';
    }
}

function onManualPhoneTyping() {
    const phoneInput = document.getElementById('customerPhoneInput').value.trim();
    const sel = document.getElementById('customerSelect');
    let matched = false;
    for (let i = 1; i < sel.options.length; i++) {
        const p = sel.options[i].getAttribute('data-phone');
        if (p && p.trim() === phoneInput) {
            sel.selectedIndex = i;
            onCustomerSelected(sel);
            matched = true;
            break;
        }
    }
    if (!matched && sel.value) {
        sel.value = "";
        document.getElementById('hiddenCustomerId').value = "";
        document.getElementById('customerFinancialBadge').style.display = 'none';
    }
}

function openQuickCustomerModal() {
    document.getElementById('modalQuickCustomer').style.display = 'flex';
    document.getElementById('qc_name').focus();
}

function closeQuickCustomerModal() {
    document.getElementById('modalQuickCustomer').style.display = 'none';
}

function submitQuickCustomer(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSaveQuickCust');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const data = {
        _token: "{{ csrf_token() }}",
        name: document.getElementById('qc_name').value.trim(),
        phone: document.getElementById('qc_phone').value.trim(),
        address: document.getElementById('qc_address').value.trim(),
        credit_limit: document.getElementById('qc_credit_limit').value || 0
    };

    fetch("{{ route('pos.customer.quick_register') }}", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
        },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.textContent = '💾 Save Customer';
        if (res.success && res.customer) {
            const c = res.customer;
            const sel = document.getElementById('customerSelect');
            let opt = sel.querySelector(`option[value="${c.id}"]`);
            if (!opt) {
                opt = document.createElement('option');
                opt.value = c.id;
                sel.appendChild(opt);
            }
            opt.setAttribute('data-id', c.id);
            opt.setAttribute('data-name', c.name);
            opt.setAttribute('data-phone', c.phone);
            opt.setAttribute('data-debt', c.total_debt);
            opt.setAttribute('data-limit', c.credit_limit);
            opt.setAttribute('data-code', c.customer_code);
            opt.textContent = `${c.name} (${c.phone}) [${c.customer_code}] — Debt: ₦${Math.round(c.total_debt).toLocaleString('en-US')}`;
            
            sel.value = c.id;
            onCustomerSelected(sel);
            closeQuickCustomerModal();
            document.getElementById('quickCustomerForm').reset();
        } else {
            alert(res.error || 'Failed to save customer');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = '💾 Save Customer';
        alert('Network error while saving customer');
    });
}

function updateDebtCalculation() {
    const total = parseFloat(document.getElementById('hiddenTotal').value) || 0;
    const partPayInput = document.getElementById('partPayInput');
    const remainingDisplay = document.getElementById('remainingDebtDisplay');

    if (paymentMode === 'DEBT') {
        const payingNow = parseFloat(partPayInput.value) || 0;
        const remaining = Math.max(0, total - payingNow);
        remainingDisplay.textContent = '₦' + Math.round(remaining).toLocaleString('en-US');
        document.getElementById('hiddenPaid').value = payingNow;
        document.getElementById('hiddenCash').value = payingNow;
    } else if (paymentMode === 'CASH') {
        document.getElementById('hiddenPaid').value = total;
        document.getElementById('hiddenCash').value = total;
        document.getElementById('hiddenPos').value = 0;
    } else if (paymentMode === 'POS') {
        document.getElementById('hiddenPaid').value = total;
        document.getElementById('hiddenPos').value = total;
        document.getElementById('hiddenCash').value = 0;
    }
}

function submitSale() {
    const total = parseFloat(document.getElementById('hiddenTotal').value) || 0;
    if (total <= 0 || cart.length === 0) return;

    const custName = document.getElementById('customerNameInput').value.trim() || 'Walk-in Customer';
    const custPhone = document.getElementById('customerPhoneInput').value.trim();
    const isSupplied = document.getElementById('radioYes').checked;
    const paid = parseFloat(document.getElementById('hiddenPaid').value) || 0;
    const remaining = Math.max(0, total - paid);
    const totalUnits = cart.reduce((sum, item) => sum + item.qty, 0);

    // 🔒 ZERO BYPASS CHECK FOR CREDIT & NOT SUPPLIED ORDERS
    if (remaining > 0 || !isSupplied) {
        const reason = remaining > 0 ? 'Credit / Part-Payment' : 'Delayed Pickup (Not Supplied)';
        if (!custPhone || custPhone.length < 7) {
            alert(`🔒 PHONE NUMBER REQUIRED!\n\nA verified GSM Phone Number is mandatory for ${reason} to track debts and verify customer pickup.\n\nPlease enter the phone number or tap "+ Quick Add" to select a customer.`);
            document.getElementById('customerPhoneInput').focus();
            return;
        }
        if (!custName || custName.toLowerCase() === 'walk-in customer') {
            alert(`🔒 CUSTOMER NAME REQUIRED!\n\nA specific Customer Name is mandatory for ${reason}.\n\nPlease enter the customer name or select an account.`);
            document.getElementById('customerNameInput').focus();
            return;
        }

        // Check Credit Limit
        const sel = document.getElementById('customerSelect');
        if (sel.value) {
            const opt = sel.options[sel.selectedIndex];
            const currentDebt = parseFloat(opt.getAttribute('data-debt') || 0);
            const creditLimit = parseFloat(opt.getAttribute('data-limit') || 0);
            if (creditLimit > 0 && (currentDebt + remaining) > creditLimit) {
                alert(`⚠️ CREDIT LIMIT EXCEEDED!\n\nCustomer ${custName} has an allowed credit limit of ₦${Math.round(creditLimit).toLocaleString('en-US')} and current debt of ₦${Math.round(currentDebt).toLocaleString('en-US')}.\n\nThis new debt of ₦${Math.round(remaining).toLocaleString('en-US')} would bring total debt to ₦${Math.round(currentDebt + remaining).toLocaleString('en-US')}.`);
                return;
            }
        }
    }

    // Populate Products & Quantities List
    const itemsListEl = document.getElementById('confirmItemsList');
    itemsListEl.innerHTML = '';
    document.getElementById('confirmItemsTotalUnits').textContent = totalUnits;

    cart.forEach(item => {
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.justifyContent = 'space-between';
        row.style.alignItems = 'center';
        row.style.borderBottom = '1px dashed #1e293b';
        row.style.paddingBottom = '0.35rem';

        const subtotal = item.qty * item.price;
        row.innerHTML = `
            <div style="flex: 1; padding-right: 0.5rem;">
                <div style="font-weight: 700; color: #f8fafc; font-size: 0.88rem;">${item.name}</div>
                <div style="font-size: 0.75rem; color: #94a3b8;">₦${Math.round(item.price).toLocaleString('en-US')} per unit</div>
            </div>
            <div style="text-align: right; white-space: nowrap;">
                <span style="background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); padding: 0.15rem 0.45rem; border-radius: 6px; font-weight: 800; font-size: 0.8rem; margin-right: 0.4rem;">
                    × ${item.qty}
                </span>
                <strong style="color: #4ade80; font-size: 0.9rem;">₦${Math.round(subtotal).toLocaleString('en-US')}</strong>
            </div>
        `;
        itemsListEl.appendChild(row);
    });

    document.getElementById('confirmCustName').innerHTML = `<strong>${custName}</strong> ${custPhone ? '<span style="color:#93c5fd;font-size:0.78rem;">(' + custPhone + ')</span>' : ''}`;
    document.getElementById('confirmTotalBill').textContent = '₦' + Math.round(total).toLocaleString('en-US');
    document.getElementById('confirmPayingNow').textContent = '₦' + Math.round(paid).toLocaleString('en-US') + ' (' + (paymentMode === 'DEBT' ? (paid > 0 ? 'Part-Paid' : 'Not Paid') : 'Paid ' + paymentMode) + ')';
    
    if (remaining > 0) {
        document.getElementById('confirmDebtLedger').textContent = '+ ₦' + Math.round(remaining).toLocaleString('en-US') + ' Added to Debtor Ledger';
        document.getElementById('confirmDebtLedger').style.color = '#f87171';
    } else {
        document.getElementById('confirmDebtLedger').textContent = '₦0 (Fully Settled)';
        document.getElementById('confirmDebtLedger').style.color = '#4ade80';
    }

    const impactEl = document.getElementById('confirmStockImpact');
    if (isSupplied) {
        impactEl.style.background = 'rgba(34,197,94,0.15)';
        impactEl.style.color = '#4ade80';
        impactEl.style.border = '1px solid #22c55e';
        impactEl.textContent = '🟢 SUPPLIED: Deducts ' + totalUnits + ' unit(s) from physical shelf stock immediately.';
    } else {
        impactEl.style.background = 'rgba(245,158,11,0.15)';
        impactEl.style.color = '#fbbf24';
        impactEl.style.border = '1px solid #f59e0b';
        impactEl.textContent = '⏳ NOT SUPPLIED: ' + totalUnits + ' unit(s) remain locked in shop stock buffer until customer pickup.';
    }

    document.getElementById('modalSaleConfirm').style.display = 'flex';
}

function closeSaleConfirm() {
    document.getElementById('modalSaleConfirm').style.display = 'none';
}

function finalProceedSale() {
    document.getElementById('checkoutForm').submit();
}

let activeCategory = 'ALL';

function filterCategory(category, btn) {
    activeCategory = category;
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyProductFilters();
}

function filterProducts() {
    applyProductFilters();
}

function applyProductFilters() {
    const rawQuery = (document.getElementById('searchInput').value || '').toLowerCase().trim();
    const query = rawQuery.replace(/[\s\-_]/g, '');
    const cards = document.querySelectorAll('.product-card');

    cards.forEach(card => {
        const name = (card.getAttribute('data-name') || '').toLowerCase();
        const code = (card.getAttribute('data-code') || '').toLowerCase();
        const brand = (card.getAttribute('data-brand') || '').toLowerCase();
        const size = (card.getAttribute('data-size') || '').toLowerCase();
        const category = card.getAttribute('data-category') || '';

        // Category filter check (if user types in search box, search across all categories automatically)
        const categoryMatches = (activeCategory === 'ALL' || category === activeCategory || rawQuery.length > 0);

        // Multi-attribute search match across SKU code, commercial name, brand, and size
        const cleanName = name.replace(/[\s\-_]/g, '');
        const cleanCode = code.replace(/[\s\-_]/g, '');
        const cleanSize = size.replace(/[\s\-_]/g, '');

        const searchMatches = (
            query === '' ||
            code.includes(rawQuery) ||
            name.includes(rawQuery) ||
            brand.includes(rawQuery) ||
            size.includes(rawQuery) ||
            cleanCode.includes(query) ||
            cleanName.includes(query) ||
            cleanSize.includes(query)
        );

        if (categoryMatches && searchMatches) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Support Barcode Scanner / Instant Enter Key Selection
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = this.value.trim().toUpperCase();
                if (!query) return;

                const visibleCards = Array.from(document.querySelectorAll('.product-card')).filter(c => c.style.display !== 'none');
                const exactMatch = visibleCards.find(c => (c.getAttribute('data-code') || '').toUpperCase() === query);

                if (exactMatch) {
                    exactMatch.click();
                    this.value = '';
                    applyProductFilters();
                } else if (visibleCards.length === 1) {
                    visibleCards[0].click();
                    this.value = '';
                    applyProductFilters();
                }
            }
        });
    }
});
</script>
@endpush
