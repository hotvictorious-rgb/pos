/**
 * VMPOS Retail Engine
 * Unified Client-Side Cart, Fast Keyboard Barcode Picker, Multi-SKU Exchange, and Tender Logic
 */

(function() {
    'use strict';

    let cart = [];
    let exchangeReturns = [];
    let currentLookupSale = null;
    let paymentMode = 'POS';
    let partPayTender = 'POS';
    let currentCheckoutIdempotencyKey = null;
    let activeCategory = 'ALL';

    // ─────────────────────────────────────────────────────────
    // 1. MODALS & IDEMPOTENCY KEYS
    // ─────────────────────────────────────────────────────────
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
        const posInput = document.getElementById('posIdempotencyKey');
        if (posInput) posInput.value = '';
        currentCheckoutIdempotencyKey = null;
    }

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

    // ─────────────────────────────────────────────────────────
    // 2. PRODUCT EXCHANGE LOOKUP & MULTI-SKU RETURN ENGINE
    // ─────────────────────────────────────────────────────────
    function openExchangeModal() {
        const m = document.getElementById('modalExchangeItem');
        if (m) {
            document.getElementById('ex_search_term').value = '';
            document.getElementById('ex_lookup_error').style.display = 'none';
            document.getElementById('ex_lookup_spinner').style.display = 'none';
            document.getElementById('ex_sale_details').style.display = 'none';
            document.getElementById('ex_items_container').style.display = 'none';
            document.getElementById('ex_footer_summary').style.display = 'none';
            document.getElementById('btnApplyExCredit').style.display = 'none';
            currentLookupSale = null;
            m.style.display = 'flex';
            setTimeout(() => {
                const termInput = document.getElementById('ex_search_term');
                if (termInput) termInput.focus();
            }, 100);
        }
    }

    function closeExchangeModal() {
        const m = document.getElementById('modalExchangeItem');
        if (m) m.style.display = 'none';
    }

    function searchSaleForExchange() {
        const term = document.getElementById('ex_search_term').value.trim();
        const errorEl = document.getElementById('ex_lookup_error');
        const spinner = document.getElementById('ex_lookup_spinner');
        const saleDetails = document.getElementById('ex_sale_details');
        const itemsContainer = document.getElementById('ex_items_container');
        const footerSummary = document.getElementById('ex_footer_summary');
        const btnApply = document.getElementById('btnApplyExCredit');

        errorEl.style.display = 'none';
        if (!term) {
            errorEl.textContent = 'Please enter a Receipt / Invoice Number or Customer Phone Number.';
            errorEl.style.display = 'block';
            return;
        }

        spinner.style.display = 'block';
        saleDetails.style.display = 'none';
        itemsContainer.style.display = 'none';
        footerSummary.style.display = 'none';
        btnApply.style.display = 'none';

        const lookupUrl = (window.POS_CONFIG && window.POS_CONFIG.lookupSaleUrl) || '/pos/lookup-sale';

        fetch(lookupUrl + "?term=" + encodeURIComponent(term), {
            headers: { "Accept": "application/json" }
        })
        .then(r => r.json().then(data => ({ status: r.status, body: data })))
        .then(({ status, body }) => {
            spinner.style.display = 'none';
            if (status !== 200 || !body.success) {
                errorEl.textContent = body.error || 'No matching sale found.';
                errorEl.style.display = 'block';
                return;
            }

            currentLookupSale = body.sale;
            document.getElementById('ex_sale_ref').textContent = body.sale.ref;
            document.getElementById('ex_sale_date').textContent = body.sale.date;
            document.getElementById('ex_sale_customer').textContent = body.sale.customerName + (body.sale.customerPhone ? ` (${body.sale.customerPhone})` : '');
            document.getElementById('ex_sale_total').textContent = '₦' + Math.round(body.sale.totalAmount).toLocaleString('en-US');
            saleDetails.style.display = 'block';

            const listEl = document.getElementById('ex_items_list');
            listEl.innerHTML = '';

            if (!body.sale.items || body.sale.items.length === 0) {
                listEl.innerHTML = '<div style="color:#94a3b8; font-size:0.8rem; padding:1rem; text-align:center;">No line items found on this invoice.</div>';
            } else {
                body.sale.items.forEach((item, idx) => {
                    const isEligible = item.eligibleQty > 0;
                    const row = document.createElement('div');
                    row.style.cssText = `background: rgba(15,23,42,0.6); border: 1px solid ${isEligible ? '#334155' : '#1e293b'}; border-radius: 8px; padding: 0.6rem 0.75rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; opacity: ${isEligible ? '1' : '0.5'};`;
                    
                    row.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 0.6rem; flex: 1.5;">
                            <input type="checkbox" id="ex_chk_${idx}" class="ex-item-check" data-idx="${idx}" ${isEligible ? 'checked' : 'disabled'} onchange="calculateModalExchangeCredit()" style="width: 1.1rem; height: 1.1rem; cursor: ${isEligible ? 'pointer' : 'not-allowed'}; accent-color: #f59e0b;">
                            <div>
                                <div style="font-weight: 700; color: #f8fafc; font-size: 0.85rem;">${item.productName}</div>
                                <div style="font-size: 0.72rem; color: #94a3b8;">
                                    Code: <strong>${item.productCode}</strong> · Sold: <strong>${item.soldQty}</strong> ${item.alreadyReturnedQty > 0 ? `(Already Ret: <span style="color:#f87171;">${item.alreadyReturnedQty}</span>)` : ''}
                                </div>
                            </div>
                        </div>
                        <div style="text-align: right; min-width: 90px;">
                            <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Sold Unit Price</div>
                            <div style="font-weight: 800; color: #60a5fa; font-size: 0.88rem;">₦${Math.round(item.unitPrice).toLocaleString('en-US')}</div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <label style="font-size: 0.7rem; color: #cbd5e1; margin-bottom: 0;">Ret Qty:</label>
                            <input type="number" id="ex_qty_${idx}" class="ex-item-qty" data-idx="${idx}" min="1" max="${item.eligibleQty}" value="${isEligible ? 1 : 0}" ${isEligible ? '' : 'disabled'} oninput="calculateModalExchangeCredit()" style="width: 55px; padding: 0.25rem 0.4rem; font-size: 0.82rem; background: #0b0f19; border: 1px solid #475569; border-radius: 6px; color: #fbbf24; font-weight: 800; text-align: center;">
                        </div>
                        <div style="text-align: right; min-width: 85px;">
                            <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase;">Line Credit</div>
                            <div id="ex_credit_${idx}" style="font-weight: 800; color: #4ade80; font-size: 0.88rem;">₦${isEligible ? Math.round(item.unitPrice).toLocaleString('en-US') : '0'}</div>
                        </div>
                    `;
                    listEl.appendChild(row);
                });
            }

            itemsContainer.style.display = 'block';
            footerSummary.style.display = 'flex';
            btnApply.style.display = 'block';
            calculateModalExchangeCredit();
        })
        .catch(() => {
            spinner.style.display = 'none';
            errorEl.textContent = 'Network or server error while looking up sale.';
            errorEl.style.display = 'block';
        });
    }

    function calculateModalExchangeCredit() {
        if (!currentLookupSale || !currentLookupSale.items) return;
        let totalCredit = 0;
        currentLookupSale.items.forEach((item, idx) => {
            const chk = document.getElementById(`ex_chk_${idx}`);
            const qtyInp = document.getElementById(`ex_qty_${idx}`);
            const creditEl = document.getElementById(`ex_credit_${idx}`);
            if (!chk || !qtyInp || !creditEl) return;

            if (chk.checked && item.eligibleQty > 0) {
                let q = parseInt(qtyInp.value) || 0;
                if (q < 1) q = 1;
                if (q > item.eligibleQty) q = item.eligibleQty;
                qtyInp.value = q;
                const line = q * item.unitPrice;
                creditEl.textContent = '₦' + Math.round(line).toLocaleString('en-US');
                totalCredit += line;
            } else {
                creditEl.textContent = '₦0';
            }
        });
        const summaryEl = document.getElementById('ex_modal_total_credit');
        if (summaryEl) {
            summaryEl.textContent = '₦' + Math.round(totalCredit).toLocaleString('en-US');
        }
    }

    function applyMultiSkuExchangeCredit() {
        if (!currentLookupSale || !currentLookupSale.items) return;

        const selectedReturns = [];
        currentLookupSale.items.forEach((item, idx) => {
            const chk = document.getElementById(`ex_chk_${idx}`);
            const qtyInp = document.getElementById(`ex_qty_${idx}`);
            if (chk && chk.checked && item.eligibleQty > 0) {
                const q = Math.min(item.eligibleQty, Math.max(1, parseInt(qtyInp.value) || 1));
                selectedReturns.push({
                    saleId: currentLookupSale.id,
                    origSaleRef: currentLookupSale.ref,
                    productId: item.productId,
                    productCode: item.productCode,
                    productName: item.productName,
                    quantity: q,
                    unitPrice: item.unitPrice,
                    creditAmount: Math.round(q * item.unitPrice * 100) / 100
                });
            }
        });

        if (selectedReturns.length === 0) {
            alert('Please select at least one product with eligible quantity to exchange.');
            return;
        }

        if (cart.length === 0) {
            alert('Please add the new replacement product(s) to the cart first, then apply the exchange credit.');
            closeExchangeModal();
            return;
        }

        const cartGross = cart.reduce((sum, item) => sum + (item.qty * item.price), 0);
        const totalCredit = selectedReturns.reduce((sum, i) => sum + (i.quantity * i.unitPrice), 0);

        if (totalCredit > cartGross) {
            alert(`⚠️ Exchange Credit (₦${Math.round(totalCredit).toLocaleString('en-US')}) exceeds replacement items total in cart (₦${Math.round(cartGross).toLocaleString('en-US')}).\n\nExchanges are strictly for items of equal or higher value.\n\n👉 If the customer wants to downgrade and receive a cash/transfer refund for the difference, please process a "Return / Refund" under Transactions first, then start a fresh sale.`);
            return;
        }

        exchangeReturns = selectedReturns;

        const receiptRefInput = document.getElementById('receiptRefInput');
        if (receiptRefInput && !receiptRefInput.value.trim()) {
            receiptRefInput.value = currentLookupSale.ref;
        }

        closeExchangeModal();
        renderCart();

        alert(`✓ Exchange Credit Applied!\n\nReceipt: ${currentLookupSale.ref}\nReturned Items: ${exchangeReturns.length} product(s)\nTotal Credit Deducted: -₦${Math.round(totalCredit).toLocaleString('en-US')}`);
    }

    function removeExchangeCredit() {
        exchangeReturns = [];
        renderCart();
    }

    // ─────────────────────────────────────────────────────────
    // 3. SHOPPING CART ENGINE
    // ─────────────────────────────────────────────────────────
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
        if (!cart || cart.length === 0) {
            if (typeof showActionBlockedModal === 'function') {
                showActionBlockedModal({
                    title: 'Cart is Already Empty',
                    subtitle: 'No items to discard',
                    errors: [{
                        title: 'Shopping Cart Empty',
                        desc: 'There are currently no items in your shopping cart to discard.'
                    }]
                });
            }
            return;
        }

        const totalUnits = cart.reduce((sum, item) => sum + item.qty, 0);
        if (typeof showConfirmPopup === 'function') {
            showConfirmPopup({
                icon: '🗑️',
                title: 'Confirm Clear Cart',
                subtitle: 'Discard all items from active sale?',
                borderColor: '#ef4444',
                message: `Are you sure you want to discard all <strong>${totalUnits} item(s)</strong> currently in the cart? This cannot be undone.`,
                confirmText: '🗑️ Yes, Discard Cart',
                confirmClass: 'btn-danger',
                onConfirm: function() {
                    cart = [];
                    resetCheckoutIdempotencyKey();
                    selectPaymentMode('POS');
                    renderCart();
                }
            });
        } else {
            if (confirm(`Discard all ${totalUnits} items in cart?`)) {
                cart = [];
                resetCheckoutIdempotencyKey();
                selectPaymentMode('POS');
                renderCart();
            }
        }
    }

    function renderCart() {
        const list = document.getElementById('cartItemsList');
        const btn = document.getElementById('completeSaleBtn');
        const bottomBar = document.getElementById('posMobileBottomBar');

        if (cart.length === 0) {
            if (list) list.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:2rem 0;" id="emptyCartMessage">Tap any item on the left to add to sale 👈</div>';
            const dispTotal = document.getElementById('displayTotal');
            const hidTotal = document.getElementById('hiddenTotal');
            const dispGross = document.getElementById('displayGrossTotal');
            if (dispTotal) dispTotal.textContent = '₦0.00';
            if (hidTotal) hidTotal.value = 0;
            if (dispGross) dispGross.textContent = '₦0.00';

            const hidPaid = document.getElementById('hiddenPaid');
            const hidCash = document.getElementById('hiddenCash');
            const hidPos = document.getElementById('hiddenPos');
            if (hidPaid) hidPaid.value = 0;
            if (hidCash) hidCash.value = 0;
            if (hidPos) hidPos.value = 0;

            const splitCashInp = document.getElementById('splitCashInput');
            const splitPosInp = document.getElementById('splitPosInput');
            if (splitCashInp) splitCashInp.value = '';
            if (splitPosInp) splitPosInp.value = '';

            const splitTotalEl = document.getElementById('splitTotalTenderedDisplay');
            if (splitTotalEl) splitTotalEl.textContent = '₦0';
            const splitChangeRow = document.getElementById('splitChangeRow');
            if (splitChangeRow) splitChangeRow.style.display = 'none';
            const splitBalanceRow = document.getElementById('splitBalanceRow');
            if (splitBalanceRow) splitBalanceRow.style.display = 'none';
            const splitNoticeEl = document.getElementById('splitIncompleteNotice');
            if (splitNoticeEl) splitNoticeEl.style.display = 'none';

            const partPayInput = document.getElementById('partPayInput');
            if (partPayInput) partPayInput.value = '';
            const partSplitCashInp = document.getElementById('partSplitCashInput');
            if (partSplitCashInp) partSplitCashInp.value = '';
            const partSplitPosInp = document.getElementById('partSplitPosInput');
            if (partSplitPosInp) partSplitPosInp.value = '';
            const partSplitSumEl = document.getElementById('partSplitSumDisplay');
            if (partSplitSumEl) partSplitSumEl.textContent = 'Deposit: ₦0';

            const remainingEl = document.getElementById('remainingDebtDisplay');
            if (remainingEl) remainingEl.textContent = '₦0';

            const mobileCountEl = document.getElementById('mobileCartCount');
            const mobileTotalEl = document.getElementById('mobileCartTotal');
            if (mobileCountEl) mobileCountEl.textContent = '0';
            if (mobileTotalEl) mobileTotalEl.textContent = '0';
            if (bottomBar) bottomBar.classList.remove('show-bar');
            if (btn) {
                btn.disabled = true;
                btn.style.opacity = 0.5;
                btn.style.cursor = 'not-allowed';
            }
            updateCustomerRequirements();
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

        if (list) list.innerHTML = html;

        const totalExchangeCredit = exchangeReturns.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
        const netDue = Math.max(0, total - totalExchangeCredit);

        const hidTotal = document.getElementById('hiddenTotal');
        if (hidTotal) hidTotal.value = total;
        const grossTotalEl = document.getElementById('displayGrossTotal');
        if (grossTotalEl) grossTotalEl.textContent = '₦' + Math.round(total).toLocaleString('en-US');

        const rowExEl = document.getElementById('rowExchangeDeduction');
        const badgeExEl = document.getElementById('exchangeCreditCartBadge');
        const labelBillEl = document.getElementById('totalBillLabel');
        const inputExReturns = document.getElementById('exchangeReturnsInput');

        if (inputExReturns) {
            inputExReturns.value = JSON.stringify(exchangeReturns);
        }

        if (totalExchangeCredit > 0) {
            if (rowExEl) {
                rowExEl.style.display = 'flex';
                const dispExCredit = document.getElementById('displayTotalExchangeCredit');
                if (dispExCredit) dispExCredit.textContent = '-₦' + Math.round(totalExchangeCredit).toLocaleString('en-US');
            }
            if (badgeExEl) {
                badgeExEl.style.display = 'block';
                const badgeDisp = document.getElementById('displayExchangeCredit');
                if (badgeDisp) badgeDisp.textContent = '-₦' + Math.round(totalExchangeCredit).toLocaleString('en-US');
                const summaryList = document.getElementById('exchangeItemsSummaryList');
                if (summaryList) {
                    summaryList.innerHTML = exchangeReturns.map(i => `
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dotted rgba(245,158,11,0.2); padding: 0.15rem 0;">
                            <span>• <strong>${i.productName}</strong> (x${i.quantity} @ ₦${Math.round(i.unitPrice).toLocaleString('en-US')}) <span style="color:#60a5fa;">[${i.origSaleRef}]</span></span>
                            <span style="color:#4ade80; font-weight:700;">-₦${Math.round(i.quantity * i.unitPrice).toLocaleString('en-US')}</span>
                        </div>
                    `).join('');
                }
            }
            if (labelBillEl) labelBillEl.textContent = 'Net Top-Up Due:';
        } else {
            if (rowExEl) rowExEl.style.display = 'none';
            if (badgeExEl) badgeExEl.style.display = 'none';
            if (labelBillEl) labelBillEl.textContent = 'Total Bill:';
        }

        const dispTotal = document.getElementById('displayTotal');
        if (dispTotal) dispTotal.textContent = '₦' + Math.round(netDue).toLocaleString('en-US');

        const mobileCountEl = document.getElementById('mobileCartCount');
        const mobileTotalEl = document.getElementById('mobileCartTotal');
        if (mobileCountEl) mobileCountEl.textContent = totalUnitsCount;
        if (mobileTotalEl) mobileTotalEl.textContent = Math.round(netDue).toLocaleString('en-US');

        const stickyUnitsEl = document.getElementById('stickyCartUnits');
        const stickyTotalEl = document.getElementById('stickyCartTotal');
        if (stickyUnitsEl) stickyUnitsEl.textContent = totalUnitsCount;
        if (stickyTotalEl) stickyTotalEl.textContent = Math.round(netDue).toLocaleString('en-US');

        const colCatalog = document.getElementById('posColCatalog');
        if (bottomBar) {
            if (cart.length > 0 && (!colCatalog || !colCatalog.classList.contains('mobile-hidden'))) {
                bottomBar.classList.add('show-bar');
            } else {
                bottomBar.classList.remove('show-bar');
            }
        }

        updateDebtCalculation();

        if (btn) {
            btn.disabled = false;
            btn.style.opacity = 1;
            btn.style.cursor = 'pointer';
        }
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

    // ─────────────────────────────────────────────────────────
    // 4. DELIVERY HANDOVER & PAYMENT MODES
    // ─────────────────────────────────────────────────────────
    function selectHandover(val) {
        const yesLabel = document.getElementById('labelSuppliedYes');
        const noLabel = document.getElementById('labelSuppliedNo');
        const radioYes = document.getElementById('radioYes');
        const radioNo = document.getElementById('radioNo');

        if (val === 'yes') {
            if (radioYes) radioYes.checked = true;
            if (yesLabel) yesLabel.className = 'radio-card selected-yes';
            if (noLabel) noLabel.className = 'radio-card';
        } else {
            if (radioNo) radioNo.checked = true;
            if (noLabel) noLabel.className = 'radio-card selected-no';
            if (yesLabel) yesLabel.className = 'radio-card';
        }
        updateCustomerRequirements();
    }

    function setPartPayTender(tender) {
        partPayTender = tender;
        const btnPos = document.getElementById('btnPartPos');
        const btnCash = document.getElementById('btnPartCash');
        const btnSplit = document.getElementById('btnPartSplit');
        const partSplitSubBox = document.getElementById('partSplitSubBox');

        [btnPos, btnCash, btnSplit].forEach(b => {
            if (b) {
                b.style.border = '1px solid #475569';
                b.style.background = 'rgba(15,23,42,0.8)';
                b.style.color = '#94a3b8';
                b.style.boxShadow = 'none';
            }
        });

        if (tender === 'POS') {
            if (btnPos) {
                btnPos.style.border = '2px solid #3b82f6';
                btnPos.style.background = 'rgba(59,130,246,0.25)';
                btnPos.style.color = '#93c5fd';
                btnPos.style.boxShadow = '0 0 10px rgba(59,130,246,0.3)';
            }
            if (partSplitSubBox) partSplitSubBox.style.display = 'none';
        } else if (tender === 'CASH') {
            if (btnCash) {
                btnCash.style.border = '2px solid #22c55e';
                btnCash.style.background = 'rgba(34,197,94,0.25)';
                btnCash.style.color = '#86efac';
                btnCash.style.boxShadow = '0 0 10px rgba(34,197,94,0.3)';
            }
            if (partSplitSubBox) partSplitSubBox.style.display = 'none';
        } else if (tender === 'SPLIT') {
            if (btnSplit) {
                btnSplit.style.border = '2px solid #a855f7';
                btnSplit.style.background = 'rgba(168,85,247,0.25)';
                btnSplit.style.color = '#d8b4fe';
                btnSplit.style.boxShadow = '0 0 10px rgba(168,85,247,0.3)';
            }
            if (partSplitSubBox) partSplitSubBox.style.display = 'block';

            // Auto-initialize split inputs from partPayInput if available
            const partPayInput = document.getElementById('partPayInput');
            const cashInp = document.getElementById('partSplitCashInput');
            const posInp = document.getElementById('partSplitPosInput');
            const curPay = parseFloat(partPayInput ? partPayInput.value : 0) || 0;
            if (cashInp && posInp && (!cashInp.value && !posInp.value) && curPay > 0) {
                posInp.value = curPay;
                cashInp.value = '';
            }
            updatePartSplitCalculation();
            return;
        }
        updateDebtCalculation();
    }

    function updatePartSplitCalculation() {
        const cashInp = document.getElementById('partSplitCashInput');
        const posInp = document.getElementById('partSplitPosInput');
        const partCash = parseFloat(cashInp ? cashInp.value : 0) || 0;
        const partPos = parseFloat(posInp ? posInp.value : 0) || 0;
        const totalDeposit = partCash + partPos;

        const partPayInput = document.getElementById('partPayInput');
        if (partPayInput) {
            partPayInput.value = totalDeposit > 0 ? totalDeposit : '';
        }

        const sumEl = document.getElementById('partSplitSumDisplay');
        if (sumEl) {
            sumEl.textContent = 'Deposit: ₦' + Math.round(totalDeposit).toLocaleString('en-US');
        }

        updateDebtCalculation();
    }

    function selectPaymentMode(mode) {
        paymentMode = mode;
        const tabCash = document.getElementById('tabCash');
        const tabPos = document.getElementById('tabPos');
        const tabSplit = document.getElementById('tabSplit');
        const tabDebt = document.getElementById('tabDebt');
        if (tabCash) tabCash.className = mode === 'CASH' ? 'pay-tab active' : 'pay-tab';
        if (tabPos) tabPos.className = mode === 'POS' ? 'pay-tab active' : 'pay-tab';
        if (tabSplit) tabSplit.className = mode === 'SPLIT' ? 'pay-tab active' : 'pay-tab';
        if (tabDebt) tabDebt.className = mode === 'DEBT' ? 'pay-tab active' : 'pay-tab';

        const debtBox = document.getElementById('debtBox');
        if (debtBox) {
            debtBox.style.display = mode === 'DEBT' ? 'block' : 'none';
            if (mode === 'DEBT') {
                setPartPayTender('POS');
            }
        }

        const splitBox = document.getElementById('splitBox');
        if (splitBox) {
            splitBox.style.display = mode === 'SPLIT' ? 'block' : 'none';
            if (mode === 'SPLIT') {
                const cashInp = document.getElementById('splitCashInput');
                const posInp = document.getElementById('splitPosInput');
                const hidTotal = document.getElementById('hiddenTotal');
                const grossTotal = parseFloat(hidTotal ? hidTotal.value : 0) || 0;
                const totalExchangeCredit = exchangeReturns.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
                const netPayable = Math.max(0, grossTotal - totalExchangeCredit);
                if (cashInp && posInp && (!cashInp.value && !posInp.value)) {
                    cashInp.value = '';
                    posInp.value = netPayable > 0 ? netPayable : '';
                }
                updateSplitCalculation();
            }
        }

        if (mode === 'SPLIT') {
            updateSplitCalculation();
        } else {
            updateDebtCalculation();
        }
        updateCustomerRequirements();
    }

    function updateCustomerRequirements() {
        const isSupplied = document.getElementById('radioYes') ? document.getElementById('radioYes').checked : true;
        const isDebt = (paymentMode === 'DEBT');
        const isStrict = isDebt || !isSupplied;

        const nameReq = document.getElementById('custNameReq');
        const receiptReq = document.getElementById('receiptRefReq');
        const receiptInput = document.getElementById('receiptRefInput');

        if (nameReq) nameReq.style.display = isStrict ? 'inline' : 'none';
        if (receiptReq) receiptReq.style.display = isStrict ? 'inline' : 'none';
        if (receiptInput) {
            if (isStrict) {
                receiptInput.style.borderColor = '#38bdf8';
                receiptInput.style.background = 'rgba(56,189,248,0.08)';
                receiptInput.style.boxShadow = '0 0 10px rgba(56,189,248,0.25)';
            } else {
                receiptInput.style.borderColor = '#475569';
                receiptInput.style.background = '#0b0f19';
                receiptInput.style.boxShadow = 'none';
            }
        }
    }

    function updateDebtCalculation() {
        if (paymentMode === 'SPLIT') {
            updateSplitCalculation();
            return;
        }

        const hidTotal = document.getElementById('hiddenTotal');
        const grossTotal = parseFloat(hidTotal ? hidTotal.value : 0) || 0;
        const totalExchangeCredit = exchangeReturns.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
        const netPayable = Math.max(0, grossTotal - totalExchangeCredit);

        const partPayInput = document.getElementById('partPayInput');
        const remainingEl = document.getElementById('remainingDebtDisplay');
        const hidPaid = document.getElementById('hiddenPaid');
        const hidCash = document.getElementById('hiddenCash');
        const hidPos = document.getElementById('hiddenPos');

        if (paymentMode === 'DEBT') {
            if (partPayTender === 'SPLIT') {
                const partCash = parseFloat(document.getElementById('partSplitCashInput')?.value || 0) || 0;
                const partPos = parseFloat(document.getElementById('partSplitPosInput')?.value || 0) || 0;
                const totalDeposit = partCash + partPos;
                const remaining = Math.max(0, netPayable - totalDeposit);
                if (remainingEl) {
                    remainingEl.textContent = '₦' + Math.round(remaining).toLocaleString('en-US');
                }
                if (hidPaid) hidPaid.value = totalDeposit;
                if (hidCash) hidCash.value = partCash;
                if (hidPos) hidPos.value = partPos;
            } else {
                const partPay = parseFloat(partPayInput ? partPayInput.value : 0) || 0;
                const remaining = Math.max(0, netPayable - partPay);
                if (remainingEl) {
                    remainingEl.textContent = '₦' + Math.round(remaining).toLocaleString('en-US');
                }
                if (hidPaid) hidPaid.value = partPay;
                if (partPayTender === 'CASH') {
                    if (hidCash) hidCash.value = partPay;
                    if (hidPos) hidPos.value = 0;
                } else {
                    if (hidPos) hidPos.value = partPay;
                    if (hidCash) hidCash.value = 0;
                }
            }
        } else if (paymentMode === 'POS') {
            if (remainingEl) remainingEl.textContent = '₦0';
            if (hidPaid) hidPaid.value = netPayable;
            if (hidPos) hidPos.value = netPayable;
            if (hidCash) hidCash.value = 0;
        } else { // CASH
            if (remainingEl) remainingEl.textContent = '₦0';
            if (hidPaid) hidPaid.value = netPayable;
            if (hidCash) hidCash.value = netPayable;
            if (hidPos) hidPos.value = 0;
        }
    }

    function updateSplitCalculation() {
        const hidTotal = document.getElementById('hiddenTotal');
        const grossTotal = parseFloat(hidTotal ? hidTotal.value : 0) || 0;
        const totalExchangeCredit = exchangeReturns.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
        const netPayable = Math.max(0, grossTotal - totalExchangeCredit);

        const cashInput = document.getElementById('splitCashInput');
        const posInput = document.getElementById('splitPosInput');
        const cashVal = parseFloat(cashInput ? cashInput.value : 0) || 0;
        const posVal = parseFloat(posInput ? posInput.value : 0) || 0;

        const totalTendered = cashVal + posVal;
        const hidPaid = document.getElementById('hiddenPaid');
        const hidCash = document.getElementById('hiddenCash');
        const hidPos = document.getElementById('hiddenPos');

        if (hidCash) hidCash.value = cashVal;
        if (hidPos) hidPos.value = posVal;
        if (hidPaid) hidPaid.value = Math.min(netPayable, totalTendered);

        const totalTenderedEl = document.getElementById('splitTotalTenderedDisplay');
        if (totalTenderedEl) totalTenderedEl.textContent = '₦' + Math.round(totalTendered).toLocaleString('en-US');

        const changeEl = document.getElementById('splitChangeDisplay');
        const changeRow = document.getElementById('splitChangeRow');
        const remainingEl = document.getElementById('splitRemainingDisplay');
        const balanceRow = document.getElementById('splitBalanceRow');
        const noticeEl = document.getElementById('splitIncompleteNotice');

        if (totalTendered >= netPayable) {
            const change = Math.max(0, cashVal - Math.max(0, netPayable - posVal));
            if (changeEl) changeEl.textContent = '₦' + Math.round(change).toLocaleString('en-US');
            if (changeRow) changeRow.style.display = change > 0 ? 'flex' : 'none';
            if (balanceRow) balanceRow.style.display = 'none';
            if (noticeEl) noticeEl.style.display = 'none';
        } else {
            const shortfall = Math.max(0, netPayable - totalTendered);
            if (changeRow) changeRow.style.display = 'none';
            if (remainingEl) remainingEl.textContent = '-₦' + Math.round(shortfall).toLocaleString('en-US');
            if (balanceRow) balanceRow.style.display = 'flex';
            if (noticeEl) noticeEl.style.display = 'block';
        }

        updateCustomerRequirements();
    }

    function fillRemainingToPos() {
        const hidTotal = document.getElementById('hiddenTotal');
        const grossTotal = parseFloat(hidTotal ? hidTotal.value : 0) || 0;
        const totalExchangeCredit = exchangeReturns.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
        const netPayable = Math.max(0, grossTotal - totalExchangeCredit);
        const cashVal = parseFloat(document.getElementById('splitCashInput')?.value || 0) || 0;
        const posNeeded = Math.max(0, netPayable - cashVal);
        const posInput = document.getElementById('splitPosInput');
        if (posInput) {
            posInput.value = posNeeded > 0 ? posNeeded : 0;
            updateSplitCalculation();
        }
    }

    function fillRemainingToCash() {
        const hidTotal = document.getElementById('hiddenTotal');
        const grossTotal = parseFloat(hidTotal ? hidTotal.value : 0) || 0;
        const totalExchangeCredit = exchangeReturns.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
        const netPayable = Math.max(0, grossTotal - totalExchangeCredit);
        const posVal = parseFloat(document.getElementById('splitPosInput')?.value || 0) || 0;
        const cashNeeded = Math.max(0, netPayable - posVal);
        const cashInput = document.getElementById('splitCashInput');
        if (cashInput) {
            cashInput.value = cashNeeded > 0 ? cashNeeded : 0;
            updateSplitCalculation();
        }
    }

    // ─────────────────────────────────────────────────────────
    // 5. CUSTOMER SELECTION & VALIDATION
    // ─────────────────────────────────────────────────────────
    function onCustomerSelected(sel) {
        const opt = sel.options[sel.selectedIndex];
        const custId = opt.value;
        const name = opt.getAttribute('data-name') || '';
        const phone = opt.getAttribute('data-phone') || '';
        const debt = parseFloat(opt.getAttribute('data-debt') || 0);
        const code = opt.getAttribute('data-code') || '';

        const hidCustId = document.getElementById('hiddenCustomerId');
        const custNameInp = document.getElementById('customerNameInput');
        const custPhoneInp = document.getElementById('customerPhoneInput');
        if (hidCustId) hidCustId.value = custId;
        if (custNameInp) custNameInp.value = custId ? name : '';
        if (custPhoneInp) custPhoneInp.value = phone;

        const badge = document.getElementById('customerFinancialBadge');
        if (badge) {
            if (custId) {
                badge.style.display = 'block';
                const codeEl = document.getElementById('badgeCustCode');
                const debtEl = document.getElementById('badgeCustDebt');
                if (codeEl) codeEl.textContent = code;
                if (debtEl) debtEl.textContent = '₦' + Math.round(debt).toLocaleString('en-US');
            } else {
                badge.style.display = 'none';
            }
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
        if (sel && sel.value) {
            sel.value = "";
            const hidCustId = document.getElementById('hiddenCustomerId');
            if (hidCustId) hidCustId.value = "";
            const badge = document.getElementById('customerFinancialBadge');
            if (badge) badge.style.display = 'none';
        }
    }

    function onManualPhoneTyping() {
        const input = document.getElementById('customerPhoneInput');
        if (!input) return;
        let raw = input.value.replace(/[\s\-\(\)\+]/g, '');
        if (raw.startsWith('234') && raw.length === 13) {
            raw = '0' + raw.slice(3);
            input.value = raw;
        }
        const phoneInput = raw.trim();
        const sel = document.getElementById('customerSelect');
        if (!sel) return;
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
            const hidCustId = document.getElementById('hiddenCustomerId');
            if (hidCustId) hidCustId.value = "";
            const badge = document.getElementById('customerFinancialBadge');
            if (badge) badge.style.display = 'none';
        }
    }

    let receiptRefDuplicateError = null;
    let receiptRefCheckTimeout = null;

    function onReceiptRefInput(inputEl) {
        if (!inputEl) inputEl = document.getElementById('receiptRefInput');
        if (!inputEl) return;

        const feedbackEl = document.getElementById('receiptRefFeedback');
        const rawVal = inputEl.value.trim();

        if (receiptRefCheckTimeout) {
            clearTimeout(receiptRefCheckTimeout);
        }

        if (!rawVal) {
            receiptRefDuplicateError = null;
            inputEl.style.borderColor = '#475569';
            inputEl.style.background = '#0b0f19';
            if (feedbackEl) {
                feedbackEl.style.display = 'none';
                feedbackEl.innerHTML = '';
            }
            return;
        }

        const checkUrl = inputEl.getAttribute('data-check-url') || '/pos/check-receipt-ref';

        receiptRefCheckTimeout = setTimeout(() => {
            fetch(`${checkUrl}?ref=${encodeURIComponent(rawVal)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (inputEl.value.trim() !== rawVal) return;

                if (data.exists) {
                    receiptRefDuplicateError = data.message || `Receipt slip #${rawVal} was already used on Sale #${data.saleId} by ${data.cashier} at this branch.`;
                    inputEl.style.borderColor = '#ef4444';
                    inputEl.style.background = 'rgba(239, 68, 68, 0.12)';
                    if (feedbackEl) {
                        feedbackEl.style.display = 'block';
                        feedbackEl.innerHTML = `<span style="color: #fca5a5; font-weight: 700;">⚠️ Duplicate Receipt Slip:</span> <span style="color: #fecaca;">Already used on Sale #<strong>${data.saleId}</strong> by <strong>${data.cashier}</strong> (${data.date}). Users cannot reuse receipt numbers in this branch.</span>`;
                        feedbackEl.style.background = 'rgba(239, 68, 68, 0.18)';
                        feedbackEl.style.border = '1px solid rgba(239, 68, 68, 0.4)';
                    }
                } else {
                    receiptRefDuplicateError = null;
                    inputEl.style.borderColor = '#22c55e';
                    inputEl.style.background = 'rgba(34, 197, 94, 0.08)';
                    if (feedbackEl) {
                        feedbackEl.style.display = 'block';
                        feedbackEl.innerHTML = `<span style="color: #4ade80; font-weight: 700;">✓ Available:</span> <span style="color: #86efac;">Receipt slip #<strong>${rawVal}</strong> is unique and valid for this branch.</span>`;
                        feedbackEl.style.background = 'rgba(34, 197, 94, 0.12)';
                        feedbackEl.style.border = '1px solid rgba(34, 197, 94, 0.3)';
                    }
                }
            })
            .catch(() => {
                // Fail-safe network fallback: handled by server during checkout
            });
        }, 300);
    }

    function openQuickCustomerModal() {
        const modal = document.getElementById('modalQuickCustomer');
        if (modal) modal.style.display = 'flex';
        const qcName = document.getElementById('qc_name');
        if (qcName) qcName.focus();
    }

    function closeQuickCustomerModal() {
        const modal = document.getElementById('modalQuickCustomer');
        if (modal) modal.style.display = 'none';
    }

    function submitQuickCustomer(e) {
        if (e) e.preventDefault();
        const qcName = document.getElementById('qc_name');
        const qcPhone = document.getElementById('qc_phone');
        const qcAddress = document.getElementById('qc_address');
        const rawName = qcName ? qcName.value.trim() : '';
        const rawPhone = qcPhone ? qcPhone.value.trim() : '';
        const rawAddress = qcAddress ? qcAddress.value.trim() : '';
        const errors = [];

        if (!rawName || rawName.length < 2) {
            errors.push({
                title: 'Customer Name Mandatory',
                desc: 'Please enter a valid customer full name (at least 2 characters).',
                focus: 'qc_name'
            });
        }

        const pCheck = validateNigerianPhone(rawPhone);
        if (!pCheck.valid) {
            errors.push({
                title: 'Invalid Nigerian Phone Number',
                desc: 'Phone number must be exactly 11 digits starting with 0 (e.g. 08031234567 or 09012345678).',
                focus: 'qc_phone'
            });
        }

        if (errors.length > 0) {
            if (typeof showActionBlockedModal === 'function') {
                showActionBlockedModal({
                    title: 'Customer Registration Blocked',
                    subtitle: 'Please resolve the following requirements:',
                    errors: errors
                });
            } else {
                alert(errors.map(e => e.title + ': ' + e.desc).join('\n'));
            }
            return;
        }

        const proceedRegistration = function() {
            const btn = document.getElementById('btnSaveQuickCust');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Saving...';
            }

            const token = (window.POS_CONFIG && window.POS_CONFIG.csrfToken) || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const regUrl = (window.POS_CONFIG && window.POS_CONFIG.quickRegisterCustomerUrl) || '/pos/quick-register-customer';

            const data = {
                _token: token,
                name: rawName,
                phone: pCheck.phone,
                address: rawAddress
            };

            fetch(regUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(res => {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = '💾 Save Customer';
                }
                if (res.success && res.customer) {
                    const c = res.customer;
                    const sel = document.getElementById('customerSelect');
                    if (sel) {
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
                    }
                    closeQuickCustomerModal();
                } else {
                    if (typeof showActionBlockedModal === 'function') {
                        showActionBlockedModal({
                            title: 'Registration Error',
                            subtitle: 'Could not register customer',
                            errors: [{
                                title: 'Server Rejected',
                                desc: res.message || 'Customer phone number may already exist or database validation failed.'
                            }]
                        });
                    } else {
                        alert(res.message || 'Failed to register customer.');
                    }
                }
            })
            .catch(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = '💾 Save Customer';
                }
                if (typeof showActionBlockedModal === 'function') {
                    showActionBlockedModal({
                        title: 'Network / System Error',
                        subtitle: 'Failed to communicate with server',
                        errors: [{
                            title: 'Connection Error',
                            desc: 'Please verify your network connection and try again.'
                        }]
                    });
                } else {
                    alert('Network error registering customer.');
                }
            });
        };

        if (typeof showConfirmPopup === 'function') {
            showConfirmPopup({
                icon: '👤',
                title: 'Confirm Customer Registration',
                subtitle: 'Add new client to store directory:',
                borderColor: '#3b82f6',
                items: [
                    { label: 'Full Name', value: rawName, color: '#f8fafc' },
                    { label: 'Phone Number', value: pCheck.phone, color: '#93c5fd' },
                    { label: 'Address', value: rawAddress || 'Walk-in / In-store', color: '#cbd5e1' }
                ],
                impact: {
                    text: '✓ DIRECTORY SYNC: Generates an automatic unique Customer ID for debt tracking and purchase history.',
                    type: 'info'
                },
                confirmText: '💾 Yes, Register Customer',
                confirmClass: 'btn-primary',
                onConfirm: proceedRegistration
            });
        } else {
            proceedRegistration();
        }
    }

    // ─────────────────────────────────────────────────────────
    // 6. SALE SUBMISSION & INTEGRITY CHECKS
    // ─────────────────────────────────────────────────────────
    function submitSale() {
        if (syncCartPricesFromDom()) {
            renderCart();
        }
        const total = parseFloat(document.getElementById('hiddenTotal').value) || 0;
        const custNameInput = document.getElementById('customerNameInput');
        const custPhoneInput = document.getElementById('customerPhoneInput');
        const custName = custNameInput ? custNameInput.value.trim() || 'Walk-in Customer' : 'Walk-in Customer';
        const rawCustPhone = custPhoneInput ? custPhoneInput.value.trim() : '';
        const phoneCheck = validateNigerianPhone(rawCustPhone);
        const custPhone = phoneCheck.phone;
        const radioYes = document.getElementById('radioYes');
        const isSupplied = radioYes ? radioYes.checked : true;
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

        // 2. Phone format validation if provided
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

        // 4. Exchange Trade-In Value Boundary Check
        const totalExchangeCredit = exchangeReturns.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
        if (totalExchangeCredit > total) {
            errors.push({
                title: 'Exchange Credit Exceeds Cart Total',
                desc: `Exchange trade-in credit (<strong>₦${Math.round(totalExchangeCredit).toLocaleString('en-US')}</strong>) cannot exceed the replacement cart total (<strong>₦${Math.round(total).toLocaleString('en-US')}</strong>).<br><span style="color:#fbbf24;font-size:0.78rem;">👉 Exchanges are strictly for items of equal or higher value. To downgrade and receive cash back, process a "Return / Refund" under Transactions first.</span>`,
                focus: 'searchInput'
            });
        }

        // 5. Retail Payment Mode & Debt Validation
        if (paymentMode === 'SPLIT') {
            const cashVal = parseFloat(document.getElementById('splitCashInput')?.value || 0) || 0;
            const posVal = parseFloat(document.getElementById('splitPosInput')?.value || 0) || 0;
            const totalTendered = cashVal + posVal;

            const totalExchangeCredit = exchangeReturns.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
            const netDue = Math.max(0, total - totalExchangeCredit);

            if (totalTendered < netDue) {
                const shortfall = netDue - totalTendered;
                errors.push({
                    title: 'Split Payment Incomplete (Strict Full Settlement)',
                    desc: `Split payment requires 100% full settlement of the net bill (<strong>₦${Math.round(netDue).toLocaleString('en-US')}</strong>).<br>You tendered <strong>₦${Math.round(totalTendered).toLocaleString('en-US')}</strong> (Shortfall: <strong>₦${Math.round(shortfall).toLocaleString('en-US')}</strong>).<br><span style="color:#fbbf24;font-size:0.78rem;">👉 If customer is paying a deposit and taking the rest on credit, switch to the <strong>"🤝 Part / Debt"</strong> tab.</span>`,
                    focus: 'splitPosInput'
                });
            }
        } else if (paymentMode === 'DEBT') {
            const partPayRaw = document.getElementById('partPayInput') ? document.getElementById('partPayInput').value : '0';
            const partPayInput = parseFloat(partPayRaw);

            const totalExchangeCredit = exchangeReturns.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
            const netDue = Math.max(0, total - totalExchangeCredit);

            if (isNaN(partPayInput) || partPayInput < 0) {
                errors.push({
                    title: 'Invalid Payment Amount',
                    desc: 'Please enter a valid amount paying now (enter 0 if totally unpaid).',
                    focus: 'partPayInput'
                });
            } else if (partPayInput > netDue) {
                errors.push({
                    title: 'Payment Amount Exceeds Net Bill',
                    desc: `Amount paying now (₦${Math.round(partPayInput).toLocaleString('en-US')}) cannot be greater than the net bill (₦${Math.round(netDue).toLocaleString('en-US')}).`,
                    focus: 'partPayInput'
                });
            }

            if (partPayTender === 'SPLIT') {
                const partCash = parseFloat(document.getElementById('partSplitCashInput')?.value || 0) || 0;
                const partPos = parseFloat(document.getElementById('partSplitPosInput')?.value || 0) || 0;
                const splitDeposit = partCash + partPos;

                if (splitDeposit > netDue) {
                    errors.push({
                        title: 'Deposit Amount Exceeds Net Bill',
                        desc: `Split deposit (₦${Math.round(splitDeposit).toLocaleString('en-US')}) cannot be greater than the net bill (₦${Math.round(netDue).toLocaleString('en-US')}). For full payment, choose Cash, POS, or Split.`,
                        focus: 'partSplitCashInput'
                    });
                }
            }

            const receiptRefVal = document.getElementById('receiptRefInput') ? document.getElementById('receiptRefInput').value.trim() : '';
            const hidCustIdVal = document.getElementById('hiddenCustomerId') ? document.getElementById('hiddenCustomerId').value : '';
            const hasIdentifier = (rawCustPhone && phoneCheck.valid) || (receiptRefVal.length > 0) || (hidCustIdVal);

            if (remaining > 0) {
                if (!hasIdentifier) {
                    errors.push({
                        title: 'Physical Receipt Slip # Required for Credit',
                        desc: 'Please enter the Physical Paper Receipt Slip Number (or customer phone) to identify the customer for this debt/credit order.',
                        focus: 'receiptRefInput'
                    });
                }
                if (!custName || custName.toLowerCase() === 'walk-in customer') {
                    if (receiptRefVal.length > 0 && custNameInput) {
                        custNameInput.value = `Customer (Receipt #${receiptRefVal})`;
                    } else {
                        errors.push({
                            title: 'Customer Identification Mandatory',
                            desc: 'Credit / Debt sales cannot be issued to an anonymous "Walk-in Customer". Please enter Physical Receipt Slip # or customer name.',
                            focus: 'receiptRefInput'
                        });
                    }
                }
            }
        }

        // 5. Delayed Pickup (Not Supplied) Rule
        if (!isSupplied) {
            const receiptRefVal = document.getElementById('receiptRefInput') ? document.getElementById('receiptRefInput').value.trim() : '';
            const hidCustIdVal = document.getElementById('hiddenCustomerId') ? document.getElementById('hiddenCustomerId').value : '';
            const hasIdentifier = (rawCustPhone && phoneCheck.valid) || (receiptRefVal.length > 0) || (hidCustIdVal);

            if (!hasIdentifier) {
                errors.push({
                    title: 'Physical Receipt Slip # Required for Pickup',
                    desc: 'Please enter the Physical Paper Receipt Slip Number (or customer phone) so the warehouse can verify the customer when collecting buffer goods.',
                    focus: 'receiptRefInput'
                });
            }
            if (!custName || custName.toLowerCase() === 'walk-in customer') {
                if (receiptRefVal.length > 0 && custNameInput) {
                    custNameInput.value = `Customer (Receipt #${receiptRefVal})`;
                } else {
                    errors.push({
                        title: 'Customer Identification Required',
                        desc: 'Pending orders (unsupplied goods) must have a Physical Receipt Slip # or customer name so the shop knows who owns the reserved stock.',
                        focus: 'receiptRefInput'
                    });
                }
            }
        }

        // 6. Strict Physical Receipt Uniqueness Check
        if (receiptRefDuplicateError) {
            errors.push({
                title: 'Duplicate Receipt Slip Number',
                desc: `${receiptRefDuplicateError}<br><span style="color:#fbbf24;font-size:0.78rem;">👉 Cashiers and users cannot enter an already existing receipt number again at this branch. Please check the paper booklet slip.</span>`,
                focus: 'receiptRefInput'
            });
        }

        if (errors.length > 0) {
            if (typeof showActionBlockedModal === 'function') {
                showActionBlockedModal({
                    title: 'Sale Cannot Be Completed',
                    subtitle: 'Please resolve the following business rule requirements:',
                    errors: errors
                });
            } else {
                alert(errors.map(e => e.title + ': ' + e.desc).join('\n'));
            }
            return;
        }

        // Populate Products & Quantities List in Modal
        const itemsListEl = document.getElementById('confirmItemsList');
        if (itemsListEl) {
            itemsListEl.innerHTML = '';
            const totalUnitsEl = document.getElementById('confirmItemsTotalUnits');
            if (totalUnitsEl) totalUnitsEl.textContent = totalUnits;

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
        }

        const confirmCustEl = document.getElementById('confirmCustName');
        if (confirmCustEl) {
            confirmCustEl.innerHTML = `<strong>${custName}</strong> ${custPhone ? '<span style="color:#93c5fd;font-size:0.78rem;">(' + custPhone + ')</span>' : ''}`;
        }
        
        const totalExCredit = exchangeReturns.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
        const netDueAmt = Math.max(0, total - totalExCredit);
        const confirmTotalEl = document.getElementById('confirmTotalBill');
        if (confirmTotalEl) {
            confirmTotalEl.textContent = '₦' + Math.round(netDueAmt).toLocaleString('en-US') + (totalExCredit > 0 ? ' (Net Due after -₦' + Math.round(totalExCredit).toLocaleString('en-US') + ' exchange credit)' : '');
        }

        const confirmPayingEl = document.getElementById('confirmPayingNow');
        if (confirmPayingEl) {
            let payMethodLabel = 'Paid ' + paymentMode;
            if (paymentMode === 'DEBT') {
                if (paid <= 0) {
                    payMethodLabel = 'Fully Unpaid (Debt)';
                } else if (partPayTender === 'SPLIT') {
                    payMethodLabel = 'Part-Paid via Split Deposit';
                } else {
                    payMethodLabel = 'Part-Paid via ' + partPayTender;
                }
            } else if (paymentMode === 'SPLIT') {
                payMethodLabel = 'Split Tender (Full Settle)';
            }
            confirmPayingEl.textContent = '₦' + Math.round(paid).toLocaleString('en-US') + ' (' + payMethodLabel + ')';
            confirmPayingEl.style.color = '#60a5fa';
        }

        const confirmSplitRow = document.getElementById('confirmSplitBreakdownRow');
        const confirmSplitText = document.getElementById('confirmSplitBreakdownText');
        const confirmChangeRow = document.getElementById('confirmChangeRow');
        const confirmChangeEl = document.getElementById('confirmChange');

        const cashVal = parseFloat(document.getElementById('hiddenCash')?.value || 0) || 0;
        const posVal = parseFloat(document.getElementById('hiddenPos')?.value || 0) || 0;

        if (paymentMode === 'SPLIT' || (paymentMode === 'DEBT' && partPayTender === 'SPLIT' && (cashVal > 0 || posVal > 0))) {
            if (confirmSplitRow) confirmSplitRow.style.display = 'flex';
            if (confirmSplitText) confirmSplitText.textContent = `💵 ₦${Math.round(cashVal).toLocaleString('en-US')} Cash + 💳 ₦${Math.round(posVal).toLocaleString('en-US')} POS`;
            const totalTender = cashVal + posVal;
            if (totalTender > netDueAmt && cashVal > 0 && paymentMode === 'SPLIT') {
                const change = Math.max(0, cashVal - Math.max(0, netDueAmt - posVal));
                if (confirmChangeRow) confirmChangeRow.style.display = change > 0 ? 'flex' : 'none';
                if (confirmChangeEl) confirmChangeEl.textContent = '₦' + Math.round(change).toLocaleString('en-US');
            } else {
                if (confirmChangeRow) confirmChangeRow.style.display = 'none';
            }
        } else {
            if (confirmSplitRow) confirmSplitRow.style.display = 'none';
            if (confirmChangeRow) confirmChangeRow.style.display = 'none';
        }
        
        const confirmDebtEl = document.getElementById('confirmDebtLedger');
        if (confirmDebtEl) {
            if (remaining > 0) {
                confirmDebtEl.textContent = '+ ₦' + Math.round(remaining).toLocaleString('en-US') + ' Added to Debtor Ledger';
                confirmDebtEl.style.color = '#f87171';
            } else {
                confirmDebtEl.textContent = '₦0 (Fully Settled)';
                confirmDebtEl.style.color = '#4ade80';
            }
        }

        const impactEl = document.getElementById('confirmStockImpact');
        if (impactEl) {
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
        }

        getOrCreatePosIdempotencyKey();
        const saleConfirmModal = document.getElementById('modalSaleConfirm');
        if (saleConfirmModal) saleConfirmModal.style.display = 'flex';
    }

    function closeSaleConfirm() {
        const saleConfirmModal = document.getElementById('modalSaleConfirm');
        if (saleConfirmModal) saleConfirmModal.style.display = 'none';
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
        const form = document.getElementById('checkoutForm');
        if (form) form.submit();
    }

    // ─────────────────────────────────────────────────────────
    // 7. CATALOG SEARCH & CATEGORY FILTERING
    // ─────────────────────────────────────────────────────────
    function filterCategory(category, btn) {
        activeCategory = category;
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        applyProductFilters();
    }

    function filterProducts() {
        applyProductFilters();
    }

    function applyProductFilters() {
        const searchInput = document.getElementById('searchInput');
        const rawQuery = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const query = rawQuery.replace(/[\s\-_]/g, '');
        const cards = document.querySelectorAll('.product-card');

        cards.forEach(card => {
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const code = (card.getAttribute('data-code') || '').toLowerCase();
            const brand = (card.getAttribute('data-brand') || '').toLowerCase();
            const size = (card.getAttribute('data-size') || '').toLowerCase();
            const category = card.getAttribute('data-category') || '';

            const categoryMatches = (activeCategory === 'ALL' || category === activeCategory || rawQuery.length > 0);

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

    // ─────────────────────────────────────────────────────────
    // 8. INITIALIZATION & KEYBOARD LISTENERS
    // ─────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
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

    // ─────────────────────────────────────────────────────────
    // EXPORT GLOBALS TO WINDOW
    // ─────────────────────────────────────────────────────────
    window.addToCart = addToCart;
    window.updateQty = updateQty;
    window.clearCart = clearCart;
    window.renderCart = renderCart;
    window.updateItemPrice = updateItemPrice;
    window.syncCartPricesFromDom = syncCartPricesFromDom;
    window.switchPosTab = switchPosTab;
    window.selectHandover = selectHandover;
    window.selectPaymentMode = selectPaymentMode;
    window.handlePayTabClick = selectPaymentMode;
    window.setPartPayTender = setPartPayTender;
    window.updateDebtCalculation = updateDebtCalculation;
    window.updateSplitCalculation = updateSplitCalculation;
    window.updatePartSplitCalculation = updatePartSplitCalculation;
    window.fillRemainingToPos = fillRemainingToPos;
    window.fillRemainingToCash = fillRemainingToCash;
    window.onCustomerSelected = onCustomerSelected;
    window.validateNigerianPhone = validateNigerianPhone;
    window.onManualCustomerTyping = onManualCustomerTyping;
    window.onManualPhoneTyping = onManualPhoneTyping;
    window.onReceiptRefInput = onReceiptRefInput;
    window.openQuickCustomerModal = openQuickCustomerModal;
    window.closeQuickCustomerModal = closeQuickCustomerModal;
    window.submitQuickCustomer = submitQuickCustomer;
    window.submitSale = submitSale;
    window.closeSaleConfirm = closeSaleConfirm;
    window.finalProceedSale = finalProceedSale;
    window.filterCategory = filterCategory;
    window.filterProducts = filterProducts;
    window.openExchangeModal = openExchangeModal;
    window.closeExchangeModal = closeExchangeModal;
    window.searchSaleForExchange = searchSaleForExchange;
    window.calculateModalExchangeCredit = calculateModalExchangeCredit;
    window.applyMultiSkuExchangeCredit = applyMultiSkuExchangeCredit;
    window.removeExchangeCredit = removeExchangeCredit;
    window.ensureCheckoutIdempotencyKey = ensureCheckoutIdempotencyKey;
    window.resetCheckoutIdempotencyKey = resetCheckoutIdempotencyKey;
    window.getOrCreatePosIdempotencyKey = getOrCreatePosIdempotencyKey;
    window.resetPosIdempotencyKey = resetPosIdempotencyKey;
})();
