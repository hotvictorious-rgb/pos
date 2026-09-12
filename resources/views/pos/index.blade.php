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

    .btn-branch-pos {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(59, 130, 246, 0.4);
        border-radius: 8px;
        padding: 0.2rem 0.6rem;
        font-size: 0.82rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.2s ease;
    }
    .btn-branch-pos:hover {
        background: rgba(59, 130, 246, 0.15);
        border-color: #3b82f6;
        transform: translateY(-1px);
    }
    .badge-switch {
        font-size: 0.72rem;
        font-weight: 700;
        color: #93c5fd;
        background: rgba(59, 130, 246, 0.2);
        border: 1px solid rgba(59, 130, 246, 0.35);
        border-radius: 6px;
        padding: 0.1rem 0.4rem;
    }
    .branch-modal-choice {
        width: 100%;
        padding: 0.85rem 1rem;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(71, 85, 105, 0.5);
        border-radius: 12px;
        cursor: pointer;
        text-align: left;
        transition: all 0.15s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .branch-modal-choice:hover {
        background: rgba(30, 41, 59, 0.95);
        border-color: #3b82f6;
        transform: translateY(-1px);
    }
    .branch-modal-choice.active {
        background: rgba(37, 99, 235, 0.15);
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.4);
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

    /* Mobile Responsive POS */
    .pos-mobile-nav {
        display: none;
        gap: 0.5rem;
        margin-bottom: 1rem;
        background: rgba(17, 24, 39, 0.95);
        padding: 0.4rem;
        border-radius: 14px;
        border: 1px solid var(--border);
        position: sticky;
        top: 70px;
        z-index: 30;
    }

    .pos-mobile-nav .pos-tab-btn {
        flex: 1;
        padding: 0.75rem 0.5rem;
        border: none;
        background: transparent;
        color: var(--text-muted);
        font-weight: 700;
        font-size: 0.9rem;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
    }

    .pos-mobile-nav .pos-tab-btn.active {
        background: var(--primary);
        color: #fff;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
    }

    .pulse-badge {
        animation: pulseTab 0.4s ease;
    }
    @keyframes pulseTab {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); background: #16a34a; }
        100% { transform: scale(1); }
    }

    @media (max-width: 1024px) {
        .pos-layout {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .pos-mobile-nav {
            display: flex;
        }
        .pos-col-catalog, .pos-col-cart {
            width: 100%;
        }
        .pos-col-cart {
            display: none;
            position: static;
            max-height: none;
        }
        .pos-col-cart.mobile-active {
            display: block;
        }
        .pos-col-catalog.mobile-hidden {
            display: none;
        }
        .product-grid {
            max-height: 65vh;
        }
    }

    @media (max-width: 640px) {
        .product-grid {
            grid-template-columns: 1fr;
        }
        .catalog-header {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
        }
        .catalog-header > div:last-child {
            max-width: 100% !important;
        }
    }

    /* Mobile Bottom Sticky Floating Cart Bar */
    .pos-mobile-bottom-bar {
        display: none;
        position: fixed;
        bottom: 16px;
        left: 16px;
        right: 16px;
        background: rgba(15, 23, 42, 0.96);
        border: 2px solid #3b82f6;
        border-radius: 16px;
        padding: 0.75rem 1.15rem;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(10px);
        z-index: 95;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: transform 0.2s, opacity 0.2s;
        animation: pulseTab 0.3s ease;
    }
    @media (max-width: 1024px) {
        .pos-mobile-bottom-bar.show-bar {
            display: flex;
        }
    }
</style>
@endpush

@section('content')

<!-- Mobile Segmented View Tabs (Screens <= 1024px) -->
<div class="pos-mobile-nav" id="posMobileNav">
    <button type="button" class="pos-tab-btn active" id="tabCatalogBtn" onclick="switchPosTab('catalog')">
        🛍️ Products Catalog
    </button>
    <button type="button" class="pos-tab-btn" id="tabCartBtn" onclick="switchPosTab('cart')">
        🛒 Cart (<span id="mobileCartCount">0</span>) · ₦<span id="mobileCartTotal">0</span>
    </button>
</div>

<!-- Mobile Bottom Sticky Floating Cart Bar (Screens <= 1024px) -->
<div class="pos-mobile-bottom-bar" id="posMobileBottomBar" onclick="switchPosTab('cart')">
    <div style="display: flex; align-items: center; gap: 0.65rem;">
        <span style="font-size: 1.35rem;">🛒</span>
        <div>
            <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 700;">Active Cart (<span id="stickyCartUnits">0</span> items)</div>
            <div style="font-size: 1.1rem; font-weight: 800; color: #4ade80;">₦<span id="stickyCartTotal">0</span></div>
        </div>
    </div>
    <button type="button" class="btn btn-primary" style="padding: 0.55rem 1.1rem; font-size: 0.88rem; font-weight: 800; border-radius: 10px; pointer-events: none;">
        Review & Pay →
    </button>
</div>

<div class="pos-layout">

    <!-- Left Column: Product Catalog -->
    <div class="pos-col-catalog" id="posColCatalog">
        <div class="catalog-header">
            <div>
                <h2 style="font-size: 1.35rem; font-weight: 800;">Point of Sale 💰</h2>
                @if($warehouses->count() > 1)
                    <div style="display: flex; align-items: center; gap: 0.45rem; margin-top: 0.25rem;">
                        <span style="font-size: 0.82rem; color: var(--text-muted);">Selling from:</span>
                        <button type="button" onclick="openBranchModal()" class="btn-branch-pos" title="Click to switch selling branch">
                            <span style="font-weight: 800; color: #60a5fa;">🏬 {{ $activeWarehouse->name }}</span>
                            <span class="badge-switch">⇄ Switch Branch</span>
                        </button>
                    </div>
                @else
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.25rem;">
                        Selling from: <strong style="color: #60a5fa;">🏬 {{ $activeWarehouse->name }}</strong>
                        <span style="font-size: 0.7rem; color: #94a3b8; background: rgba(148,163,184,0.15); padding: 0.15rem 0.4rem; border-radius: 4px; margin-left: 0.3rem;">🔒 Assigned</span>
                    </p>
                @endif

            </div>

            <!-- Instant Search -->
            <div style="flex: 1; max-width: 300px;">
                <input type="text" id="searchInput" placeholder="🔍 Search SKU code (e.g. M10DE, 54X14)..." onkeyup="filterProducts()">
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
                 onclick="addToCart('{{ $product->id }}', '{{ addslashes($product->code) }}', {{ $product->unitPrice }}, {{ $product->physical_stock }})">
                <div style="flex: 1; min-width: 0;">
                    <div class="p-name" title="SKU: {{ $product->code }}" style="font-size: 1.05rem; font-weight: 800; color: #60a5fa; letter-spacing: 0.03em;">
                        {{ $product->code }}
                    </div>
                    <div style="font-size: 0.72rem; color: #94a3b8; margin-bottom: 0.25rem;">
                        <span style="color: #c084fc; font-weight: 600;">{{ $product->category }}</span>@if($product->size) · <span style="color: #cbd5e1;">{{ $product->size }}</span>@endif
                    </div>
                    <div class="p-price">₦{{ number_format($product->unitPrice, 0) }}</div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.25rem;">
                    @if($product->physical_stock > 0)
                        <span class="p-stock-badge badge-success">✓ {{ $product->physical_stock }} Shelf Stock</span>
                    @else
                        <span class="p-stock-badge badge-danger">0 Shelf Stock</span>
                    @endif
                    @if($product->allocated_stock > 0)
                        <span style="font-size: 0.68rem; font-weight: 700; color: #cbd5e1; background: rgba(148,163,184,0.15); padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid rgba(148,163,184,0.3);" title="Reserved for pending customer collections">
                            🔒 {{ $product->allocated_stock }} Reserved
                        </span>
                    @endif
                    @if($product->reservation_shortfall > 0)
                        <span style="font-size: 0.68rem; font-weight: 800; color: #f87171; background: rgba(239,68,68,0.15); padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid rgba(239,68,68,0.3);" title="Reservation Shortfall: outstanding customer buffer awaiting supplier stock-in">
                            ⚠️ Shortfall: {{ $product->reservation_shortfall }}
                        </span>
                    @endif
                    <span style="font-size: 0.75rem; font-weight: 800; color: #3b82f6; background: rgba(59,130,246,0.15); padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); margin-top: 0.15rem;">
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
    <div class="cart-drawer pos-col-cart" id="posColCart">
        <div class="cart-title">
            <span>🛒 Current Sale</span>
            <button class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;" onclick="clearCart()">
                Clear All
            </button>
        </div>

        <form id="checkoutForm" method="POST" action="{{ route('pos.checkout') }}">
            @csrf
            <input type="hidden" name="idempotency_key" id="idempotencyKeyInput" value="">
            <input type="hidden" name="warehouse_id" value="{{ $activeWarehouse->id }}">
            <input type="hidden" name="sale_type" id="saleTypeInput" value="RETAIL">
            <input type="hidden" name="totalAmount" id="hiddenTotal" value="0">
            <input type="hidden" name="paidAmount" id="hiddenPaid" value="0">
            <input type="hidden" name="cashAmount" id="hiddenCash" value="0">
            <input type="hidden" name="posAmount" id="hiddenPos" value="0">

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
                    <option value="" data-name="Walk-in Customer" data-phone="" data-debt="0" data-code="">-- 🛒 Walk-in Customer (Paid in Full Only) --</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" 
                                data-id="{{ $c->id }}"
                                data-name="{{ $c->name }}" 
                                data-phone="{{ $c->phone }}" 
                                data-debt="{{ $c->total_debt }}" 
                                data-code="{{ $c->customer_code }}">
                            {{ $c->name }} ({{ $c->phone ?: 'No Phone' }}) [{{ $c->customer_code }}] — Debt: ₦{{ number_format($c->total_debt) }}
                        </option>
                    @endforeach
                </select>

                <input type="hidden" name="customerId" id="hiddenCustomerId" value="">

                <div id="manualCustomerFields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.72rem;">Customer Name <span id="custNameReq" style="color: #f87171; display: none;">*</span></label>
                        <input type="text" name="customerName" id="customerNameInput" placeholder="Walk-in (or Name)" style="padding: 0.4rem 0.6rem; font-size: 0.82rem;" oninput="onManualCustomerTyping()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.72rem;">Phone Number (11 Digits) <span id="custPhoneReq" style="color: #f87171; display: none;">* (Required for Credit/Pickup)</span></label>
                        <input type="tel" name="customerPhone" id="customerPhoneInput" placeholder="08031234567" maxlength="11" inputmode="numeric" style="padding: 0.4rem 0.6rem; font-size: 0.82rem;" oninput="onManualPhoneTyping()">
                    </div>
                </div>

                <!-- Live Debt Badge for Selected Customer -->
                <div id="customerFinancialBadge" style="display: none; margin-top: 0.5rem; background: rgba(15,23,42,0.85); border: 1px solid #334155; border-radius: 8px; padding: 0.45rem 0.65rem; font-size: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Code: <strong id="badgeCustCode" style="color: #60a5fa;">CUST-0001</strong></span>
                        <span>Current Debt: <strong id="badgeCustDebt" style="color: #f87171;">₦0</strong></span>
                    </div>
                </div>
            </div>

            <!-- Total Amount Card -->
            <div id="totalBillCard" style="background: rgba(15,23,42,0.8); border: 2px solid #334155; border-radius: 14px; padding: 1rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted);">
                    <span id="totalBillLabel">Total Bill:</span>
                    <span style="font-size: 1.5rem; font-weight: 800; color: #4ade80;" id="displayTotal">₦0.00</span>
                </div>
            </div>

            <!-- Handover / Physical Stock Rule Selector -->
            <div class="handover-box" id="handoverSection">
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

            <!-- Standard Payment Breakdown Section (Retail Mode) -->
            <div id="retailPaymentSection">
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.4rem; text-transform: uppercase;">
                    💳 Payment Status & Method
                </div>
                <div class="pay-tabs">
                    <div class="pay-tab" id="tabCash" onclick="selectPaymentMode('CASH')">💵 Paid (Cash)</div>
                    <div class="pay-tab active" id="tabPos" onclick="selectPaymentMode('POS')">💳 Paid (POS Terminal)</div>
                    <div class="pay-tab" id="tabDebt" onclick="selectPaymentMode('DEBT')">🤝 Part-Paid / Not Paid</div>
                </div>

                <!-- Part-Payment Input (Visible when Part-Paid / Not Paid is selected) -->
                <div id="debtBox" style="display: none; background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.3); border-radius: 12px; padding: 0.85rem; margin-bottom: 1rem;">
                    <label style="color: #c084fc; font-weight: 700; font-size: 0.82rem;">Amount Paying Now (₦) [Part-Paid or 0 for Not Paid]:</label>
                    <input type="number" id="partPayInput" placeholder="e.g. 5000 (or 0 if totally unpaid)" onkeyup="updateDebtCalculation()" step="any" style="margin-bottom: 0.6rem;">

                    <!-- Sleek Part-Payment Tender Method Selector (Defaults to POS) -->
                    <div id="partPayTenderBox" style="margin-bottom: 0.75rem; background: rgba(15,23,42,0.6); padding: 0.5rem 0.65rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.06);">
                        <div style="font-size: 0.72rem; font-weight: 800; color: #cbd5e1; text-transform: uppercase; margin-bottom: 0.35rem; display: flex; align-items: center; gap: 0.35rem;">
                            <span>💳</span> Tender Method for Amount Paid Now:
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                            <button type="button" id="btnPartPos" onclick="setPartPayTender('POS')" style="padding: 0.45rem 0.6rem; font-size: 0.8rem; font-weight: 700; border-radius: 8px; border: 2px solid #3b82f6; background: rgba(59,130,246,0.25); color: #93c5fd; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 0.35rem; box-shadow: 0 0 10px rgba(59,130,246,0.3);">
                                <span>💳</span> POS (Default)
                            </button>
                            <button type="button" id="btnPartCash" onclick="setPartPayTender('CASH')" style="padding: 0.45rem 0.6rem; font-size: 0.8rem; font-weight: 700; border-radius: 8px; border: 1px solid #475569; background: rgba(15,23,42,0.8); color: #94a3b8; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 0.35rem;">
                                <span>💵</span> Cash
                            </button>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #cbd5e1;">
                        <span>Remaining Debt Balance:</span>
                        <strong style="color: #f87171;" id="remainingDebtDisplay">₦0</strong>
                    </div>
                </div>
            </div>

            <!-- Complete Sale Button -->
            <button type="button" class="btn btn-success btn-lg btn-block" onclick="submitSale()" id="completeSaleBtn" disabled style="opacity: 0.5; cursor: not-allowed;">
                ✅ Complete Sale & Print
            </button>
        </form>
    </div>

</div>

@if($warehouses->count() > 1)
<!-- Switch Selling Branch Modal -->
<div id="modalBranchSelect" class="modal-backdrop" style="display: none; z-index: 1060;" onclick="if(event.target === this) closeBranchModal()">
    <div class="modal" style="max-width: 520px; padding: 1.5rem; background: #0f172a; border: 2px solid #3b82f6; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.8);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <h3 style="font-size: 1.2rem; font-weight: 800; color: #f8fafc; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <span>🏬</span> Select Selling Branch
            </h3>
            <button type="button" onclick="closeBranchModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.25rem; cursor: pointer;">✕</button>
        </div>
        <p style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 1.25rem;">
            Choose which physical branch register you are ringing up sales for. Catalog inventory will instantly refresh.
        </p>

        <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 55vh; overflow-y: auto; padding-right: 0.25rem;">
            @foreach($warehouses as $wh)
            <form method="POST" action="{{ route('branch.switch') }}" style="margin: 0;">
                @csrf
                <input type="hidden" name="warehouse_id" value="{{ $wh->id }}">
                <button type="submit" class="branch-modal-choice {{ $activeWarehouse->id === $wh->id ? 'active' : '' }}">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span style="font-size: 1.4rem;">🏬</span>
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <strong style="font-size: 0.95rem; color: {{ $activeWarehouse->id === $wh->id ? '#60a5fa' : '#f8fafc' }};">{{ $wh->name }}</strong>
                                @if($activeWarehouse->id === $wh->id)
                                    <span style="font-size: 0.68rem; font-weight: 800; background: rgba(34,197,94,0.2); color: #4ade80; border: 1px solid rgba(34,197,94,0.4); padding: 0.1rem 0.45rem; border-radius: 6px;">CURRENT</span>
                                @endif
                            </div>
                            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;">
                                📍 {{ $wh->address ?: ($wh->location ?: 'Physical Store') }}
                                @if($wh->manager_name) • 👤 {{ $wh->manager_name }} @endif
                            </div>
                        </div>
                    </div>
                    <div>
                        @if($activeWarehouse->id === $wh->id)
                            <span style="color: #4ade80; font-size: 1.2rem; font-weight: 800;">✓</span>
                        @else
                            <span style="font-size: 0.78rem; font-weight: 700; color: #93c5fd; background: rgba(59,130,246,0.15); border: 1px solid rgba(59,130,246,0.3); padding: 0.3rem 0.65rem; border-radius: 8px;">Switch Here</span>
                        @endif
                    </div>
                </button>
            </form>
            @endforeach
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem;">
            <button type="button" class="btn btn-secondary" onclick="closeBranchModal()">Close</button>
        </div>
    </div>
</div>
@endif

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
            Enforce verified 11-digit phone number and unique account code for debt tracking & delayed pickups.
        </p>

        <form id="quickCustomerForm" onsubmit="submitQuickCustomer(event)">
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label style="font-size: 0.75rem;">Full Customer / Business Name *</label>
                <input type="text" id="qc_name" required placeholder="e.g. Alhaji Musa Stores">
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label style="font-size: 0.75rem;">GSM Phone Number (Exactly 11 Digits) *</label>
                <input type="tel" id="qc_phone" required placeholder="08031234567" maxlength="11" pattern="0[0-9]{10}" inputmode="numeric">
            </div>
            <div class="form-group" style="margin-bottom: 0.75rem;">
                <label style="font-size: 0.75rem;">Shop / Delivery Address</label>
                <input type="text" id="qc_address" placeholder="e.g. Shop 12 Main Market">
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
            <button type="button" id="btnFinalProceedSale" class="btn btn-success" style="flex: 1.3; padding: 0.75rem; font-weight: 800;" onclick="finalProceedSale()">
                ✅ Yes, Complete Sale
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let cart = [];
let paymentMode = 'POS';

window.openBranchModal = function() {
    const m = document.getElementById('modalBranchSelect');
    if (m) m.style.display = 'flex';
};

window.closeBranchModal = function() {
    const m = document.getElementById('modalBranchSelect');
    if (m) m.style.display = 'none';
};

function ensureCheckoutIdempotencyKey() {

    const input = document.getElementById('idempotencyKeyInput');
    if (input && !input.value) {
        input.value = 'pos_cart_' + (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : (Date.now().toString(36) + '-' + Math.random().toString(36).substring(2)));
    }
    return input ? input.value : '';
}

function resetCheckoutIdempotencyKey() {
    const input = document.getElementById('idempotencyKeyInput');
    if (input) input.value = '';
}

function addToCart(id, name, price, stock) {
    if (cart.length === 0) {
        ensureCheckoutIdempotencyKey();
    }
    const existing = cart.find(i => i.id === id);
    const numericStock = (typeof stock === 'number') ? stock : (parseInt(stock) || 0);
    if (existing) {
        existing.qty += 1;
        existing.stock = numericStock;
    } else {
        cart.push({ id, name, price, stock: numericStock, qty: 1 });
    }
    renderCart();

    if (window.innerWidth <= 1024) {
        const cartBtn = document.getElementById('tabCartBtn');
        if (cartBtn) {
            cartBtn.classList.add('pulse-badge');
            setTimeout(() => cartBtn.classList.remove('pulse-badge'), 400);
        }
    }
}

function switchPosTab(tab) {
    const colCatalog = document.getElementById('posColCatalog');
    const colCart = document.getElementById('posColCart');
    const tabCatBtn = document.getElementById('tabCatalogBtn');
    const tabCartBtn = document.getElementById('tabCartBtn');
    const bottomBar = document.getElementById('posMobileBottomBar');

    if (tab === 'cart') {
        if (colCatalog) colCatalog.classList.add('mobile-hidden');
        if (colCart) colCart.classList.add('mobile-active');
        if (tabCatBtn) tabCatBtn.classList.remove('active');
        if (tabCartBtn) tabCartBtn.classList.add('active');
        if (bottomBar) bottomBar.classList.remove('show-bar');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } else {
        if (colCatalog) colCatalog.classList.remove('mobile-hidden');
        if (colCart) colCart.classList.remove('mobile-active');
        if (tabCatBtn) tabCatBtn.classList.add('active');
        if (tabCartBtn) tabCartBtn.classList.remove('active');
        if (bottomBar && cart.length > 0) bottomBar.classList.add('show-bar');
    }
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) {
        cart = cart.filter(i => i.id !== id);
    }
    if (cart.length === 0) {
        resetCheckoutIdempotencyKey();
    }
    renderCart();
}

function clearCart() {
    cart = [];
    resetCheckoutIdempotencyKey();
    selectPaymentMode('POS');
    renderCart();
}

function renderCart() {
    const list = document.getElementById('cartItemsList');
    const emptyMsg = document.getElementById('emptyCartMessage');
    const btn = document.getElementById('completeSaleBtn');
    const bottomBar = document.getElementById('posMobileBottomBar');

    if (cart.length === 0) {
        list.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:2rem 0;" id="emptyCartMessage">Tap any item on the left to add to sale 👈</div>';
        document.getElementById('displayTotal').textContent = '₦0';
        document.getElementById('hiddenTotal').value = 0;
        const mobileCountEl = document.getElementById('mobileCartCount');
        const mobileTotalEl = document.getElementById('mobileCartTotal');
        if (mobileCountEl) mobileCountEl.textContent = '0';
        if (mobileTotalEl) mobileTotalEl.textContent = '0';
        if (bottomBar) bottomBar.classList.remove('show-bar');
        btn.disabled = true;
        btn.style.opacity = 0.5;
        btn.style.cursor = 'not-allowed';
        return;
    }

    let html = '';
    let total = 0;
    let totalUnitsCount = 0;

    cart.forEach((item, index) => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        totalUnitsCount += item.qty;

        const priceDisplay = `<div style="font-size:0.75rem;color:#94a3b8;display:flex;align-items:center;gap:0.35rem;margin-top:0.25rem;">
                    <span>Price (₦):</span>
                    <input type="number" step="any" min="0" value="${item.price}" id="cart_price_input_${item.id}"
                           style="width:95px;padding:0.25rem 0.4rem;font-size:0.82rem;background:#0b0f19;border:1px solid #475569;border-radius:6px;color:#4ade80;font-weight:700;" 
                           onchange="updateItemPrice('${item.id}', this.value)"
                           onkeyup="if(event.key==='Enter'){ this.blur(); updateItemPrice('${item.id}', this.value); }"
                           title="Click to edit selling price for market negotiation / bulk discount">
                    <span>x ${item.qty}</span>
                </div>`;

        html += `
        <div class="cart-item">
            <div>
                <input type="hidden" name="items[${index}][productId]" value="${item.id}">
                <input type="hidden" name="items[${index}][quantity]" value="${item.qty}">
                <input type="hidden" name="items[${index}][unitPrice]" id="cart_hidden_price_${item.id}" value="${item.price}">
                <div style="font-weight:700;font-size:0.9rem;">${item.name}</div>
                ${priceDisplay}
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

    const mobileCountEl = document.getElementById('mobileCartCount');
    const mobileTotalEl = document.getElementById('mobileCartTotal');
    if (mobileCountEl) mobileCountEl.textContent = totalUnitsCount;
    if (mobileTotalEl) mobileTotalEl.textContent = Math.round(total).toLocaleString('en-US');

    const stickyUnitsEl = document.getElementById('stickyCartUnits');
    const stickyTotalEl = document.getElementById('stickyCartTotal');
    if (stickyUnitsEl) stickyUnitsEl.textContent = totalUnitsCount;
    if (stickyTotalEl) stickyTotalEl.textContent = Math.round(total).toLocaleString('en-US');

    const colCatalog = document.getElementById('posColCatalog');
    if (bottomBar) {
        if (cart.length > 0 && (!colCatalog || !colCatalog.classList.contains('mobile-hidden'))) {
            bottomBar.classList.add('show-bar');
        } else {
            bottomBar.classList.remove('show-bar');
        }
    }

    updateDebtCalculation();

    btn.disabled = false;
    btn.style.opacity = 1;
    btn.style.cursor = 'pointer';
}

function syncCartPricesFromDom() {
    let changed = false;
    cart.forEach(item => {
        const inp = document.getElementById(`cart_price_input_${item.id}`);
        if (inp) {
            const val = parseFloat(inp.value);
            if (!isNaN(val) && val >= 0 && val !== item.price) {
                item.price = val;
                changed = true;
            }
        }
    });
    return changed;
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

let partPayTender = 'POS';

function setPartPayTender(tender) {
    partPayTender = tender;
    const btnPos = document.getElementById('btnPartPos');
    const btnCash = document.getElementById('btnPartCash');
    if (tender === 'POS') {
        if (btnPos) {
            btnPos.style.border = '2px solid #3b82f6';
            btnPos.style.background = 'rgba(59,130,246,0.25)';
            btnPos.style.color = '#93c5fd';
            btnPos.style.boxShadow = '0 0 10px rgba(59,130,246,0.3)';
        }
        if (btnCash) {
            btnCash.style.border = '1px solid #475569';
            btnCash.style.background = 'rgba(15,23,42,0.8)';
            btnCash.style.color = '#94a3b8';
            btnCash.style.boxShadow = 'none';
        }
    } else {
        if (btnCash) {
            btnCash.style.border = '2px solid #22c55e';
            btnCash.style.background = 'rgba(34,197,94,0.25)';
            btnCash.style.color = '#86efac';
            btnCash.style.boxShadow = '0 0 10px rgba(34,197,94,0.3)';
        }
        if (btnPos) {
            btnPos.style.border = '1px solid #475569';
            btnPos.style.background = 'rgba(15,23,42,0.8)';
            btnPos.style.color = '#94a3b8';
            btnPos.style.boxShadow = 'none';
        }
    }
    updateDebtCalculation();
}

function selectPaymentMode(mode) {
    paymentMode = mode;
    document.getElementById('tabCash').className = mode === 'CASH' ? 'pay-tab active' : 'pay-tab';
    document.getElementById('tabPos').className = mode === 'POS' ? 'pay-tab active' : 'pay-tab';
    document.getElementById('tabDebt').className = mode === 'DEBT' ? 'pay-tab active' : 'pay-tab';

    const debtBox = document.getElementById('debtBox');
    if (debtBox) {
        debtBox.style.display = mode === 'DEBT' ? 'block' : 'none';
        if (mode === 'DEBT') {
            setPartPayTender('POS');
        }
    }

    updateDebtCalculation();
    updateCustomerRequirements();
}

function updateCustomerRequirements() {
    const isSupplied = document.getElementById('radioYes') ? document.getElementById('radioYes').checked : true;
    const isDebt = (paymentMode === 'DEBT');
    const isStrict = isDebt || !isSupplied;

    const nameReq = document.getElementById('custNameReq');
    const phoneReq = document.getElementById('custPhoneReq');
    if (nameReq) nameReq.style.display = isStrict ? 'inline' : 'none';
    if (phoneReq) phoneReq.style.display = isStrict ? 'inline' : 'none';
}

function updateDebtCalculation() {
    const total = parseFloat(document.getElementById('hiddenTotal').value) || 0;
    const partPayInput = document.getElementById('partPayInput');
    const remainingEl = document.getElementById('remainingDebtDisplay');

    if (paymentMode === 'DEBT') {
        const partPay = parseFloat(partPayInput ? partPayInput.value : 0) || 0;
        const remaining = Math.max(0, total - partPay);
        if (remainingEl) {
            remainingEl.textContent = '₦' + Math.round(remaining).toLocaleString('en-US');
        }
        document.getElementById('hiddenPaid').value = partPay;
        if (partPayTender === 'CASH') {
            document.getElementById('hiddenCash').value = partPay;
            document.getElementById('hiddenPos').value = 0;
        } else {
            document.getElementById('hiddenPos').value = partPay;
            document.getElementById('hiddenCash').value = 0;
        }
    } else if (paymentMode === 'POS') {
        if (remainingEl) remainingEl.textContent = '₦0';
        document.getElementById('hiddenPaid').value = total;
        document.getElementById('hiddenPos').value = total;
        document.getElementById('hiddenCash').value = 0;
    } else { // CASH
        if (remainingEl) remainingEl.textContent = '₦0';
        document.getElementById('hiddenPaid').value = total;
        document.getElementById('hiddenCash').value = total;
        document.getElementById('hiddenPos').value = 0;
    }
}

function onCustomerSelected(sel) {
    const opt = sel.options[sel.selectedIndex];
    const custId = opt.value;
    const name = opt.getAttribute('data-name') || '';
    const phone = opt.getAttribute('data-phone') || '';
    const debt = parseFloat(opt.getAttribute('data-debt') || 0);
    const code = opt.getAttribute('data-code') || '';

    document.getElementById('hiddenCustomerId').value = custId;
    document.getElementById('customerNameInput').value = custId ? name : '';
    document.getElementById('customerPhoneInput').value = phone;

    const badge = document.getElementById('customerFinancialBadge');
    if (custId) {
        badge.style.display = 'block';
        document.getElementById('badgeCustCode').textContent = code;
        document.getElementById('badgeCustDebt').textContent = '₦' + Math.round(debt).toLocaleString('en-US');
    } else {
        badge.style.display = 'none';
    }
}

function validateNigerianPhone(rawPhone) {
    if (!rawPhone) return { valid: false, phone: '' };
    let clean = String(rawPhone).replace(/[\s\-\(\)\+]/g, '');
    if (clean.startsWith('234') && clean.length === 13) {
        clean = '0' + clean.slice(3);
    }
    const isValid = /^0\d{10}$/.test(clean);
    return { valid: isValid, phone: clean };
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
    const input = document.getElementById('customerPhoneInput');
    let raw = input.value.replace(/[\s\-\(\)\+]/g, '');
    if (raw.startsWith('234') && raw.length === 13) {
        raw = '0' + raw.slice(3);
        input.value = raw;
    }
    const phoneInput = raw.trim();
    const sel = document.getElementById('customerSelect');
    let matched = false;
    for (let i = 1; i < sel.options.length; i++) {
        const p = (sel.options[i].getAttribute('data-phone') || '').trim();
        if (p && p === phoneInput) {
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
    const rawPhone = document.getElementById('qc_phone').value.trim();
    const pCheck = validateNigerianPhone(rawPhone);

    if (!pCheck.valid) {
        showActionBlockedModal({
            title: 'Invalid Phone Number',
            subtitle: 'Nigerian Phone Number Validation Failed',
            errors: [{
                title: 'Exactly 11 Digits Required',
                desc: 'Phone number must be exactly 11 digits starting with 0 (e.g. 08031234567 or 09012345678).',
                focus: 'qc_phone'
            }]
        });
        return;
    }

    const btn = document.getElementById('btnSaveQuickCust');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const data = {
        _token: "{{ csrf_token() }}",
        name: document.getElementById('qc_name').value.trim(),
        phone: pCheck.phone,
        address: document.getElementById('qc_address').value.trim()
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
            opt.setAttribute('data-code', c.customer_code);
            opt.textContent = `${c.name} (${c.phone}) [${c.customer_code}] — Debt: ₦${Math.round(c.total_debt).toLocaleString('en-US')}`;
            
            sel.value = c.id;
            onCustomerSelected(sel);
            closeQuickCustomerModal();
            document.getElementById('quickCustomerForm').reset();
        } else {
            showActionBlockedModal({
                title: 'Registration Error',
                subtitle: 'Customer Creation Failed',
                errors: [{
                    title: 'Validation Error',
                    desc: res.error || (res.errors && res.errors.phone ? res.errors.phone[0] : 'Failed to register customer'),
                    focus: 'qc_phone'
                }]
            });
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = '💾 Save Customer';
        alert('Network error while saving customer');
    });
}

function submitSale() {
    if (syncCartPricesFromDom()) {
        renderCart();
    }
    const total = parseFloat(document.getElementById('hiddenTotal').value) || 0;
    const custName = document.getElementById('customerNameInput').value.trim() || 'Walk-in Customer';
    const rawCustPhone = document.getElementById('customerPhoneInput').value.trim();
    const phoneCheck = validateNigerianPhone(rawCustPhone);
    const custPhone = phoneCheck.phone;
    const isSupplied = document.getElementById('radioYes').checked;
    const paid = parseFloat(document.getElementById('hiddenPaid').value) || 0;
    const remaining = Math.max(0, total - paid);
    const totalUnits = cart.reduce((sum, item) => sum + item.qty, 0);

    const errors = [];

    // 1. Empty Cart Check
    if (cart.length === 0 || total <= 0) {
        errors.push({
            title: 'Cart is Empty',
            desc: 'Please select at least one product from the catalog on the left to begin a sale.',
            focus: 'searchInput'
        });
    }

    // Optional phone validation if typed for a regular cash sale
    if (rawCustPhone && !phoneCheck.valid) {
        errors.push({
            title: 'Invalid Nigerian Phone Number',
            desc: 'Customer phone number must be exactly 11 digits starting with 0 (e.g. 08031234567, 09012345678).',
            focus: 'customerPhoneInput'
        });
    }

    // 3. Physical Stock Handover Validation (The Golden Law)
    if (isSupplied && cart.length > 0) {
        cart.forEach(item => {
            const availStock = (typeof item.stock === 'number') ? item.stock : 0;
            if (availStock <= 0) {
                errors.push({
                    title: `Out of Stock: ${item.name}`,
                    desc: `<strong>"${item.name}"</strong> has <strong>0 physical units</strong> on ground in this shop.<br><span style="color:#fbbf24;font-size:0.78rem;">👉 Resolution: Switch Delivery below to "🟠 NOT SUPPLIED" if customer is picking up later.</span>`,
                    focus: 'labelSuppliedNo'
                });
            } else if (item.qty > availStock) {
                errors.push({
                    title: `Insufficient Stock: ${item.name}`,
                    desc: `You requested <strong>${item.qty} units</strong> of "${item.name}", but only <strong>${availStock} unit(s)</strong> physically exist in this shop.<br><span style="color:#fbbf24;font-size:0.78rem;">👉 Resolution: Reduce quantity to ${availStock} or switch Delivery to "🟠 NOT SUPPLIED".</span>`,
                    focus: 'labelSuppliedNo'
                });
            }
        });
    }

    // 4. Retail Payment Mode & Debt Validation
    if (paymentMode === 'DEBT') {
        const partPayRaw = document.getElementById('partPayInput').value;
        const partPayInput = parseFloat(partPayRaw);

        if (isNaN(partPayInput) || partPayInput < 0) {
            errors.push({
                title: 'Invalid Payment Amount',
                desc: 'Please enter a valid amount paying now (enter 0 if totally unpaid).',
                focus: 'partPayInput'
            });
        } else if (partPayInput > total) {
            errors.push({
                title: 'Payment Amount Exceeds Total Bill',
                desc: `Amount paying now (₦${Math.round(partPayInput).toLocaleString('en-US')}) cannot be greater than the total bill (₦${Math.round(total).toLocaleString('en-US')}).`,
                focus: 'partPayInput'
            });
        }

        if (remaining > 0) {
            if (!rawCustPhone || !phoneCheck.valid) {
                errors.push({
                    title: '11-Digit Phone Number Mandatory for Credit',
                    desc: 'A verified 11-digit Nigerian GSM phone number (e.g. 08031234567) is required for debt & credit sales to track customer debt recovery.',
                    focus: 'customerPhoneInput'
                });
            }
            if (!custName || custName.toLowerCase() === 'walk-in customer') {
                errors.push({
                    title: 'Customer Name / Account Mandatory',
                    desc: 'Credit / Debt sales cannot be issued to an anonymous "Walk-in Customer". Please enter customer name or tap "+ Quick Add".',
                    focus: 'customerNameInput'
                });
            }
        }
    }

    // 5. Delayed Pickup (Not Supplied) Rule
    if (!isSupplied) {
        if (!rawCustPhone || !phoneCheck.valid) {
            errors.push({
                title: '11-Digit Phone Number Required for Pending Orders',
                desc: 'A verified 11-digit Nigerian GSM phone number (e.g. 08031234567) is mandatory for unsupplied goods to contact and verify the customer during order handover.',
                focus: 'customerPhoneInput'
            });
        }
        if (!custName || custName.toLowerCase() === 'walk-in customer') {
            errors.push({
                title: 'Specific Customer Name Required',
                desc: 'Pending orders (unsupplied goods) must be assigned to a specific customer name so the warehouse knows who owns the buffer goods.',
                focus: 'customerNameInput'
            });
        }
    }

    // ⛔ IF ANY BUSINESS RULE IS VIOLATED: BLOCK SUBMISSION AND SHOW REASON POPUP MODAL!
    if (errors.length > 0) {
        showActionBlockedModal({
            title: 'Sale Cannot Be Completed',
            subtitle: 'Please resolve the following business rule requirements:',
            errors: errors
        });
        return;
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
        const priceInfo = `<div style="font-size: 0.75rem; color: #94a3b8;">₦${Math.round(item.price).toLocaleString('en-US')} per unit</div>`;
        const totalInfo = `<strong style="color: #4ade80; font-size: 0.9rem;">₦${Math.round(subtotal).toLocaleString('en-US')}</strong>`;

        row.innerHTML = `
            <div style="flex: 1; padding-right: 0.5rem;">
                <div style="font-weight: 700; color: #f8fafc; font-size: 0.88rem;">${item.name}</div>
                ${priceInfo}
            </div>
            <div style="text-align: right; white-space: nowrap;">
                <span style="background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); padding: 0.15rem 0.45rem; border-radius: 6px; font-weight: 800; font-size: 0.8rem; margin-right: 0.4rem;">
                    × ${item.qty}
                </span>
                ${totalInfo}
            </div>
        `;
        itemsListEl.appendChild(row);
    });

    document.getElementById('confirmCustName').innerHTML = `<strong>${custName}</strong> ${custPhone ? '<span style="color:#93c5fd;font-size:0.78rem;">(' + custPhone + ')</span>' : ''}`;
    
    document.getElementById('confirmTotalBill').textContent = '₦' + Math.round(total).toLocaleString('en-US');
    document.getElementById('confirmPayingNow').textContent = '₦' + Math.round(paid).toLocaleString('en-US') + ' (' + (paymentMode === 'DEBT' ? (paid > 0 ? 'Part-Paid' : 'Not Paid') : 'Paid ' + paymentMode) + ')';
    document.getElementById('confirmPayingNow').style.color = '#60a5fa';
    
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

    getOrCreatePosIdempotencyKey();
    document.getElementById('modalSaleConfirm').style.display = 'flex';
}

let currentCheckoutIdempotencyKey = null;

function getOrCreatePosIdempotencyKey() {
    if (!currentCheckoutIdempotencyKey) {
        currentCheckoutIdempotencyKey = 'pos-' + (window.crypto && crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).substring(2)));
    }
    const input = document.getElementById('posIdempotencyKey');
    if (input) {
        input.value = currentCheckoutIdempotencyKey;
    }
    return currentCheckoutIdempotencyKey;
}

function resetPosIdempotencyKey() {
    currentCheckoutIdempotencyKey = null;
    const input = document.getElementById('posIdempotencyKey');
    if (input) input.value = '';
}

function closeSaleConfirm() {
    document.getElementById('modalSaleConfirm').style.display = 'none';
    const btn = document.getElementById('btnFinalProceedSale');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '✅ Yes, Complete Sale';
    }
}

function finalProceedSale() {
    ensureCheckoutIdempotencyKey();
    const btn = document.querySelector('#modalSaleConfirm button.btn-primary') || document.getElementById('btnFinalProceedSale');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Processing Transaction...';
    }
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

// Support Barcode Scanner / Instant Enter Key Selection & POS Default Init
document.addEventListener('DOMContentLoaded', function() {
    // Ensure POS Terminal is the active default payment method
    selectPaymentMode('POS');

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

    // Customer Field Enter-Key Progression
    const custName = document.getElementById('customerNameInput');
    const custPhone = document.getElementById('customerPhoneInput');
    const partPay = document.getElementById('partPayInput');
    const completeBtn = document.getElementById('completeSaleBtn');

    if (custName && custPhone) {
        custName.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                custPhone.focus();
                if (typeof custPhone.select === 'function') custPhone.select();
            }
        });
    }

    if (custPhone) {
        custPhone.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (paymentMode === 'DEBT' && partPay && partPay.offsetParent !== null) {
                    partPay.focus();
                    if (typeof partPay.select === 'function') partPay.select();
                } else if (completeBtn && !completeBtn.disabled) {
                    completeBtn.click();
                }
            }
        });
    }

    if (partPay) {
        partPay.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (completeBtn && !completeBtn.disabled) {
                    completeBtn.click();
                }
            }
        });
    }

    // Confirmation Modal Enter Key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const confirmModal = document.getElementById('modalSaleConfirm');
            if (confirmModal && confirmModal.style.display !== 'none') {
                const btn = document.getElementById('btnFinalProceedSale');
                if (btn && !btn.disabled) {
                    e.preventDefault();
                    finalProceedSale();
                }
            }
        }
    });
});
</script>
@endpush
