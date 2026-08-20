@extends('layouts.app')

@section('title', 'Point of Sale (POS)')

@push('styles')
<style>
    .pos-layout {
        display: grid;
        grid-template-columns: 1fr 420px;
        gap: 1.5rem;
        align-items: start;
    }

    /* Product Catalog Grid */
    .catalog-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
        flex-wrap: wrap;
    }

    .category-pills {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
    }

    .cat-btn {
        padding: 0.5rem 1rem;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 99px;
        color: var(--text-muted);
        font-size: 0.85rem;
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
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1rem;
        max-height: 72vh;
        overflow-y: auto;
        padding-right: 0.35rem;
    }

    .product-card {
        background: var(--card-bg);
        border: 2px solid var(--border);
        border-radius: 18px;
        padding: 1.25rem 1rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
    }

    .product-card:hover {
        transform: translateY(-4px);
        border-color: #3b82f6;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    }
    .product-card:active { transform: scale(0.96); }

    .p-icon {
        width: 54px;
        height: 54px;
        background: rgba(59, 130, 246, 0.15);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 0.75rem;
    }

    .p-name {
        font-weight: 800;
        font-size: 0.95rem;
        color: #f8fafc;
        margin-bottom: 0.25rem;
        line-height: 1.3;
    }

    .p-price {
        font-size: 1.15rem;
        font-weight: 800;
        color: #4ade80;
        margin-bottom: 0.5rem;
    }

    .p-stock-badge {
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        border-radius: 6px;
        font-weight: 700;
    }

    /* Cart Drawer */
    .cart-drawer {
        background: var(--card-bg);
        border: 2px solid var(--border);
        border-radius: 22px;
        padding: 1.5rem;
        box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        position: sticky;
        top: 85px;
    }

    .cart-title {
        font-size: 1.25rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 1rem;
    }

    .cart-items-list {
        max-height: 30vh;
        overflow-y: auto;
        margin-bottom: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .cart-item {
        background: rgba(15,23,42,0.6);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .qty-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .qty-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: var(--card-bg);
        border: 1px solid var(--border);
        color: #f8fafc;
        font-size: 1.2rem;
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
        padding: 1rem;
        margin-bottom: 1.25rem;
    }

    .handover-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .radio-card {
        padding: 0.75rem;
        border-radius: 10px;
        border: 2px solid var(--border);
        background: var(--card-bg);
        cursor: pointer;
        text-align: center;
        font-size: 0.85rem;
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
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .pay-tab {
        padding: 0.6rem;
        background: rgba(15,23,42,0.6);
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 0.8rem;
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

    @media (max-width: 900px) {
        .pos-layout { grid-template-columns: 1fr; }
        .cart-drawer { position: static; }
    }
</style>
@endpush

@section('content')

<div class="pos-layout">

    <!-- Left Column: Product Catalog -->
    <div>
        <div class="catalog-header">
            <div>
                <h2 style="font-size: 1.4rem; font-weight: 800;">Point of Sale 💰</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted);">
                    Selling from: <strong style="color: #60a5fa;">{{ $activeWarehouse->name }}</strong>
                </p>
            </div>

            <!-- Instant Search -->
            <div style="flex: 1; max-width: 320px;">
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

        <!-- Product Cards Grid -->
        <div class="product-grid" id="productGrid">
            @forelse($products as $product)
            <div class="product-card"
                 data-id="{{ $product->id }}"
                 data-name="{{ $product->name }}"
                 data-price="{{ $product->unitPrice }}"
                 data-category="{{ $product->category }}"
                 data-stock="{{ $product->available_stock }}"
                 onclick="addToCart('{{ $product->id }}', '{{ addslashes($product->name) }}', {{ $product->unitPrice }}, {{ $product->available_stock }})">
                <div class="p-name">{{ $product->name }}</div>
                <div class="p-sku">SKU: {{ $product->code }}</div>
                <div class="p-price">₦{{ number_format($product->unitPrice, 0) }}</div>
                @if($product->available_stock > 0)
                    <span class="p-stock-badge badge-success">✓ {{ $product->available_stock }} In Stock</span>
                @else
                    <span class="p-stock-badge badge-danger">0 in Stock (Pre-order)</span>
                @endif
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

            <!-- Customer Details (For Wholesale / Part-Payment / Debt tracking) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Customer Name</label>
                    <input type="text" name="customerName" id="customerNameInput" placeholder="Walk-in (or Name)">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Phone Number</label>
                    <input type="text" name="customerPhone" id="customerPhoneInput" placeholder="080...">
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

<!-- POS Checkout Confirmation Modal (What Will Happen) -->
<div id="modalSaleConfirm" class="modal-backdrop" style="display: none;">
    <div class="modal" style="max-width: 440px; padding: 1.5rem; background: #0f172a; border: 2px solid #3b82f6; border-radius: 18px; box-shadow: 0 25px 50px rgba(0,0,0,0.5);">
        <div style="text-align: center; margin-bottom: 1.25rem;">
            <div style="font-size: 2.5rem; margin-bottom: 0.25rem;">⚡</div>
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #f8fafc;">Confirm Sale Transaction</h3>
            <p style="font-size: 0.8rem; color: #94a3b8;">Review what will happen before completing:</p>
        </div>

        <div style="background: rgba(15,23,42,0.8); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem; font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.6rem;">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #334155; padding-bottom: 0.4rem;">
                <span style="color: #94a3b8;">Customer:</span>
                <strong id="confirmCustName" style="color: #f8fafc;">Walk-in Customer</strong>
            </div>
            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed #334155; padding-bottom: 0.4rem;">
                <span style="color: #94a3b8;">Total Bill:</span>
                <strong id="confirmTotalBill" style="color: #4ade80; font-size: 1rem;">₦0</strong>
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
}

function selectPaymentMode(mode) {
    paymentMode = mode;
    document.getElementById('tabCash').className = mode === 'CASH' ? 'pay-tab active' : 'pay-tab';
    document.getElementById('tabPos').className = mode === 'POS' ? 'pay-tab active' : 'pay-tab';
    document.getElementById('tabDebt').className = mode === 'DEBT' ? 'pay-tab active' : 'pay-tab';

    const debtBox = document.getElementById('debtBox');
    debtBox.style.display = mode === 'DEBT' ? 'block' : 'none';

    updateDebtCalculation();
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

    if (paymentMode === 'DEBT') {
        if (!custName || custName.toLowerCase() === 'walk-in customer') {
            alert('⚠️ Please enter the Customer Name to track their debt balance!');
            document.getElementById('customerNameInput').focus();
            return;
        }
    }

    const isSupplied = document.getElementById('radioYes').checked;
    const paid = parseFloat(document.getElementById('hiddenPaid').value) || 0;
    const remaining = Math.max(0, total - paid);
    const totalUnits = cart.reduce((sum, item) => sum + item.qty, 0);

    document.getElementById('confirmCustName').textContent = custName;
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

function filterCategory(category, btn) {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const cards = document.querySelectorAll('.product-card');
    cards.forEach(card => {
        const cardCat = card.getAttribute('data-category');
        if (category === 'ALL' || cardCat === category) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

function filterProducts() {
    const query = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.product-card');
    cards.forEach(card => {
        const name = card.getAttribute('data-name').toLowerCase();
        if (name.includes(query)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>
@endpush
