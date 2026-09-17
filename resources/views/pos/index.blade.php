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
        grid-template-columns: repeat(4, 1fr);
        gap: 0.35rem;
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
    <button type="button" class="btn btn-primary" style="padding: 0.55rem 1.1rem; font-size: 0.88rem; font-weight: 800; border-radius: 10px; cursor: pointer;" onclick="event.stopPropagation(); switchPosTab('cart')">
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
            <div style="display: flex; gap: 0.4rem;">
                <button type="button" class="btn btn-warning" style="padding: 0.35rem 0.65rem; font-size: 0.72rem; font-weight: 700; background: #d97706; border-color: #b45309;" onclick="openExchangeModal()">
                    🔄 Exchange / Return Item
                </button>
                <button type="button" class="btn btn-secondary" style="padding: 0.35rem 0.65rem; font-size: 0.72rem;" onclick="clearCart()">
                    Clear All
                </button>
            </div>
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
            <input type="hidden" name="exchange_returns" id="exchangeReturnsInput" value="[]">

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

                <!-- Option A: Physical Receipt Slip No (Primary) & Optional Phone -->
                <div class="form-group" style="margin-bottom: 0.45rem;">
                    <label style="font-size: 0.72rem; color: #94a3b8; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 700;">🧾 Physical Receipt Slip No <span id="receiptRefReq" style="color: #38bdf8; font-weight: 800; display: none;">* (Required for Pickup / Debt)</span></span>
                        <span style="color: #38bdf8; font-size: 0.68rem; font-weight: 800; background: rgba(56,189,248,0.12); padding: 0.1rem 0.4rem; border-radius: 4px; border: 1px solid rgba(56,189,248,0.3);">★ Primary Identifier</span>
                    </label>
                    <input type="text" name="receipt_ref" id="receiptRefInput" placeholder="e.g. 4082 or Paper Booklet Slip Ref" style="width: 100%; padding: 0.4rem 0.6rem; font-size: 0.85rem; font-weight: 700; background: #0b0f19; border: 1px solid #475569; border-radius: 6px; color: #f8fafc; transition: all 0.2s ease;">
                </div>

                <div id="manualCustomerFields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.72rem;">Customer Name <span id="custNameReq" style="color: #f87171; display: none;">*</span></label>
                        <input type="text" name="customerName" id="customerNameInput" placeholder="Walk-in (or Name)" style="padding: 0.4rem 0.6rem; font-size: 0.82rem;" oninput="onManualCustomerTyping()">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-size: 0.72rem; color: #94a3b8;">Phone Number <span style="color: #64748b; font-size: 0.68rem;">(Optional)</span></label>
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

            <!-- Active Exchange Credit Badge inside Cart Drawer -->
            <div id="exchangeCreditCartBadge" style="display: none; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 12px; padding: 0.75rem; margin-bottom: 0.75rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                    <span style="font-size: 0.78rem; font-weight: 800; color: #fbbf24; text-transform: uppercase; display: flex; align-items: center; gap: 0.35rem;">
                        <span>🔄</span> Exchange Credit Active
                    </span>
                    <button type="button" onclick="removeExchangeCredit()" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 6px; padding: 0.15rem 0.45rem; font-size: 0.68rem; font-weight: 700; cursor: pointer;">
                        ✕ Remove
                    </button>
                </div>
                <div id="exchangeItemsSummaryList" style="font-size: 0.72rem; color: #cbd5e1; max-height: 90px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.2rem;">
                    <!-- Filled dynamically -->
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.4rem; padding-top: 0.35rem; border-top: 1px dashed rgba(245,158,11,0.3); font-size: 0.82rem; font-weight: 800;">
                    <span style="color: #fbbf24;">Credit Value:</span>
                    <span style="color: #f87171;" id="displayExchangeCredit">-₦0.00</span>
                </div>
            </div>

            <!-- Total Amount Card -->
            <div id="totalBillCard" style="background: rgba(15,23,42,0.8); border: 2px solid #334155; border-radius: 14px; padding: 1rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--text-muted); margin-bottom: 0.25rem;">
                    <span>Gross Items Total:</span>
                    <span style="font-weight: 700; color: #f8fafc;" id="displayGrossTotal">₦0.00</span>
                </div>
                <div id="rowExchangeDeduction" style="display: none; justify-content: space-between; font-size: 0.82rem; color: #fbbf24; margin-bottom: 0.25rem;">
                    <span>Less Exchange Credit:</span>
                    <span style="font-weight: 700; color: #f87171;" id="displayTotalExchangeCredit">-₦0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.88rem; color: var(--text-muted); border-top: 1px solid #334155; padding-top: 0.35rem; margin-top: 0.35rem;">
                    <span id="totalBillLabel" style="font-weight: 700;">Net Payable Due:</span>
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
                    <div class="pay-tab" id="tabCash" onclick="selectPaymentMode('CASH')">💵 Cash</div>
                    <div class="pay-tab active" id="tabPos" onclick="selectPaymentMode('POS')">💳 POS</div>
                    <div class="pay-tab" id="tabSplit" onclick="selectPaymentMode('SPLIT')">🔀 Split</div>
                    <div class="pay-tab" id="tabDebt" onclick="selectPaymentMode('DEBT')">🤝 Part / Debt</div>
                </div>

                <!-- Split Payment Breakdown Box (Visible when Split is selected) -->
                <div id="splitBox" style="display: none; background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.3); border-radius: 12px; padding: 0.85rem; margin-bottom: 1rem;">
                    <div style="font-size: 0.78rem; font-weight: 800; color: #93c5fd; text-transform: uppercase; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                        <span>🔀 Mixed Tender Breakdown</span>
                        <span style="font-size: 0.7rem; color: #38bdf8;">Cash + POS Terminal</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin-bottom: 0.5rem;">
                        <div>
                            <label style="font-size: 0.72rem; color: #4ade80; font-weight: 700; margin-bottom: 0.2rem; display: block;">💵 Cash Amount (₦):</label>
                            <input type="number" id="splitCashInput" placeholder="0" min="0" step="any" oninput="updateSplitCalculation()" style="width: 100%; padding: 0.45rem 0.6rem; font-size: 0.85rem; background: #0b0f19; border: 1px solid #475569; border-radius: 8px; color: #4ade80; font-weight: 700;">
                            <button type="button" onclick="fillRemainingToCash()" style="margin-top: 0.3rem; width: 100%; padding: 0.2rem 0.4rem; font-size: 0.68rem; background: rgba(74,222,128,0.12); color: #4ade80; border: 1px solid rgba(74,222,128,0.3); border-radius: 5px; cursor: pointer; font-weight: 600;">
                                Balance to Cash ⚡
                            </button>
                        </div>
                        <div>
                            <label style="font-size: 0.72rem; color: #60a5fa; font-weight: 700; margin-bottom: 0.2rem; display: block;">💳 POS Terminal (₦):</label>
                            <input type="number" id="splitPosInput" placeholder="0" min="0" step="any" oninput="updateSplitCalculation()" style="width: 100%; padding: 0.45rem 0.6rem; font-size: 0.85rem; background: #0b0f19; border: 1px solid #475569; border-radius: 8px; color: #60a5fa; font-weight: 700;">
                            <button type="button" onclick="fillRemainingToPos()" style="margin-top: 0.3rem; width: 100%; padding: 0.2rem 0.4rem; font-size: 0.68rem; background: rgba(96,165,250,0.12); color: #60a5fa; border: 1px solid rgba(96,165,250,0.3); border-radius: 5px; cursor: pointer; font-weight: 600;">
                                Balance to POS ⚡
                            </button>
                        </div>
                    </div>

                    <!-- Split Reconciled Status Strip -->
                    <div style="background: rgba(15,23,42,0.8); border-radius: 8px; padding: 0.45rem 0.65rem; border: 1px solid rgba(255,255,255,0.06); font-size: 0.78rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.2rem;">
                            <span style="color: #94a3b8;">Total Tendered:</span>
                            <strong id="splitTotalTenderedDisplay" style="color: #f8fafc;">₦0</strong>
                        </div>
                        <div style="display: none; justify-content: space-between; margin-bottom: 0.2rem;" id="splitChangeRow">
                            <span style="color: #4ade80;">Change to Customer:</span>
                            <strong id="splitChangeDisplay" style="color: #4ade80;">₦0</strong>
                        </div>
                        <div style="display: none; justify-content: space-between;" id="splitBalanceRow">
                            <span style="color: #f87171;">Remaining Unpaid:</span>
                            <strong id="splitRemainingDisplay" style="color: #f87171;">₦0</strong>
                        </div>
                    </div>
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
            <div id="confirmSplitBreakdownRow" style="display: none; justify-content: space-between; font-size: 0.8rem; background: rgba(59,130,246,0.1); padding: 0.35rem 0.6rem; border-radius: 6px; border: 1px dashed rgba(59,130,246,0.3);">
                <span style="color: #93c5fd;">Tender Breakdown:</span>
                <span id="confirmSplitBreakdownText" style="font-weight: 700; color: #f8fafc;">💵 ₦0 + 💳 ₦0</span>
            </div>
            <div id="confirmChangeRow" style="display: none; justify-content: space-between; border-bottom: 1px dashed #334155; padding-bottom: 0.4rem;">
                <span style="color: #4ade80;">Change to Customer:</span>
                <strong id="confirmChange" style="color: #4ade80; font-size: 1.05rem;">₦0</strong>
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

<!-- Modal: Multi-SKU Product Exchange (Lookup & Exact Unit Price Restock) -->
<div id="modalExchangeItem" class="modal-backdrop" style="display: none; z-index: 1060;" onclick="if(event.target === this) closeExchangeModal()">
    <div class="modal" style="max-width: 620px; width: 95%; padding: 1.5rem; background: #0f172a; border: 2px solid #f59e0b; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.85); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <h3 style="font-size: 1.2rem; font-weight: 800; color: #fbbf24; display: flex; align-items: center; gap: 0.5rem; margin: 0;">
                <span>🔄</span> Product Exchange Lookup
            </h3>
            <button type="button" onclick="closeExchangeModal()" style="background: none; border: none; color: #94a3b8; font-size: 1.25rem; cursor: pointer;">✕</button>
        </div>
        <p style="font-size: 0.82rem; color: #94a3b8; margin-bottom: 1rem;">
            Enter the customer's <strong>Receipt / Invoice Number</strong> (e.g. <code>#4eae6d29</code>) or <strong>Phone Number</strong>. Only authentic items sold on that receipt can be returned, and credit is locked to the original unit price.
        </p>

        <!-- Search Bar -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
            <input type="text" id="ex_search_term" placeholder="Enter Receipt # (e.g. 4eae6d29) or Phone..." style="flex: 1; padding: 0.55rem 0.75rem; font-size: 0.85rem; background: #0b0f19; border: 1px solid #475569; border-radius: 8px; color: #f8fafc;" onkeydown="if(event.key==='Enter'){ searchSaleForExchange(); event.preventDefault(); }">
            <button type="button" id="btnSearchSaleEx" class="btn btn-primary" onclick="searchSaleForExchange()" style="padding: 0.55rem 1rem; font-size: 0.85rem; font-weight: 700; white-space: nowrap;">
                🔍 Find Sale
            </button>
        </div>

        <div id="ex_lookup_spinner" style="display: none; text-align: center; padding: 1.5rem; color: #fbbf24;">
            <div style="font-size: 1.5rem;">⏳</div>
            <div style="font-size: 0.8rem; margin-top: 0.4rem;">Querying authentic receipt...</div>
        </div>

        <div id="ex_lookup_error" style="display: none; background: rgba(239,68,68,0.15); border: 1px solid #ef4444; border-radius: 8px; padding: 0.65rem 0.85rem; color: #fca5a5; font-size: 0.82rem; margin-bottom: 1rem;">
        </div>

        <!-- Sale Header Info -->
        <div id="ex_sale_details" style="display: none; margin-bottom: 1rem; background: rgba(30,41,59,0.7); border: 1px solid #334155; border-radius: 10px; padding: 0.75rem 1rem; font-size: 0.82rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                <span>Receipt: <strong id="ex_sale_ref" style="color: #60a5fa;">#00000000</strong></span>
                <span id="ex_sale_date" style="color: #94a3b8;">16 Sep 2026</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span>Customer: <strong id="ex_sale_customer" style="color: #f8fafc;">Walk-in</strong></span>
                <span>Original Total: <strong id="ex_sale_total" style="color: #4ade80;">₦0.00</strong></span>
            </div>
        </div>

        <!-- Items Table Container -->
        <div id="ex_items_container" style="display: none; margin-bottom: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 800; color: #93c5fd; text-transform: uppercase; margin-bottom: 0.4rem;">
                Select Products & Quantities to Return:
            </div>
            <div id="ex_items_list" style="max-height: 240px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem; padding-right: 0.25rem;">
                <!-- Populated dynamically -->
            </div>
        </div>

        <!-- Live Modal Total Credit Footer -->
        <div id="ex_footer_summary" style="display: none; background: rgba(15,23,42,0.9); border: 1px solid #f59e0b; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1rem; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.85rem; font-weight: 700; color: #cbd5e1;">Total Exchange Credit Selected:</span>
            <span style="font-size: 1.25rem; font-weight: 800; color: #fbbf24;" id="ex_modal_total_credit">₦0.00</span>
        </div>

        <div style="display: flex; gap: 0.75rem;">
            <button type="button" class="btn btn-secondary" style="flex: 1; padding: 0.65rem;" onclick="closeExchangeModal()">Cancel</button>
            <button type="button" id="btnApplyExCredit" class="btn btn-warning" style="flex: 1.5; padding: 0.65rem; font-weight: 800; background: #d97706; border-color: #b45309; display: none;" onclick="applyMultiSkuExchangeCredit()">
                ✓ Apply Exchange Credit
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
window.POS_CONFIG = {
    lookupSaleUrl: "{{ route('pos.lookup_sale') }}",
    quickRegisterCustomerUrl: "{{ route('pos.customer.quick_register') }}",
    csrfToken: "{{ csrf_token() }}"
};
</script>
<script src="{{ asset('js/pos-engine.js') }}"></script>
@endpush
