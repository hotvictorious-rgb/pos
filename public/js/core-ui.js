/**
 * VMPOS Core UI Engine
 * Unified Client-Side Runtime for Navigation, Calculator, Modals, Forms & Search
 */

(function() {
    'use strict';

    // ─────────────────────────────────────────────────────────
    // 1. LIVE CLOCK ENGINE
    // ─────────────────────────────────────────────────────────
    function updateClock() {
        const now = new Date();
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        const dayName = days[now.getDay()];
        const day = String(now.getDate()).padStart(2, '0');
        const month = months[now.getMonth()];
        const year = now.getFullYear();

        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        const strHours = String(hours).padStart(2, '0');

        const dateEl = document.getElementById('headerDate');
        const timeEl = document.getElementById('headerTime');

        if (dateEl) dateEl.textContent = `${dayName}, ${day} ${month} ${year}`;
        if (timeEl) timeEl.textContent = `${strHours}:${minutes}:${seconds} ${ampm}`;
    }

    setInterval(updateClock, 1000);
    document.addEventListener('DOMContentLoaded', updateClock);

    // ─────────────────────────────────────────────────────────
    // 2. INTERACTIVE POS CALCULATOR ENGINE
    // ─────────────────────────────────────────────────────────
    let calcExpression = '';
    let calcHistoryText = '';
    let calcJustEvaluated = false;

    function toggleCalculator() {
        const modal = document.getElementById('modalCalculator');
        if (!modal) return;
        const isVisible = (modal.style.display === 'flex');
        if (isVisible) {
            modal.style.display = 'none';
            document.removeEventListener('keydown', handleCalcKeyboard);
        } else {
            modal.style.display = 'flex';
            document.addEventListener('keydown', handleCalcKeyboard);
            updateCalcDisplay();
        }
    }

    function handleCalcBackdropClick(e) {
        if (e.target && e.target.id === 'modalCalculator') {
            toggleCalculator();
        }
    }

    function updateCalcDisplay() {
        const displayEl = document.getElementById('calcDisplay');
        const historyEl = document.getElementById('calcHistory');
        if (displayEl) {
            displayEl.textContent = calcExpression || '0';
        }
        if (historyEl) {
            historyEl.textContent = calcHistoryText;
        }
    }

    function calcInput(val) {
        if (calcJustEvaluated) {
            if (['0','1','2','3','4','5','6','7','8','9','00'].includes(val)) {
                calcExpression = '';
            } else if (val === '.') {
                calcExpression = '0';
            }
            calcJustEvaluated = false;
        }

        const operators = ['+', '-', '*', '/'];

        if (!calcExpression || calcExpression === '0') {
            if (operators.includes(val)) {
                if (val === '-') {
                    calcExpression = '-';
                    updateCalcDisplay();
                    return;
                }
                return;
            }
            if (val === '.') {
                calcExpression = '0.';
                updateCalcDisplay();
                return;
            }
            if (val === '00') {
                calcExpression = '0';
                updateCalcDisplay();
                return;
            }
            calcExpression = val;
            updateCalcDisplay();
            return;
        }

        const lastChar = calcExpression.slice(-1);

        if (operators.includes(val)) {
            if (operators.includes(lastChar)) {
                calcExpression = calcExpression.slice(0, -1) + val;
                updateCalcDisplay();
                return;
            }
        }

        if (val === '.') {
            if (operators.includes(lastChar)) {
                calcExpression += '0.';
                updateCalcDisplay();
                return;
            }
            const segments = calcExpression.split(/[-+*/]/);
            const currentSegment = segments[segments.length - 1];
            if (currentSegment.includes('.')) {
                return;
            }
        }

        const segments = calcExpression.split(/[-+*/]/);
        const currentSegment = segments[segments.length - 1];
        if (currentSegment === '0') {
            if (['1','2','3','4','5','6','7','8','9'].includes(val)) {
                calcExpression = calcExpression.slice(0, -1) + val;
                updateCalcDisplay();
                return;
            }
            if (val === '0' || val === '00') {
                return;
            }
        }

        calcExpression += val;
        updateCalcDisplay();
    }

    function calcBackspace() {
        if (calcJustEvaluated) {
            calcClear();
            return;
        }
        if (calcExpression.length > 0) {
            calcExpression = calcExpression.slice(0, -1);
            if (calcExpression === '' || calcExpression === '-') {
                calcExpression = '0';
            }
        } else {
            calcExpression = '0';
        }
        updateCalcDisplay();
    }

    function calcClear() {
        calcExpression = '';
        calcHistoryText = '';
        calcJustEvaluated = false;
        updateCalcDisplay();
    }

    function safeEvaluate(str) {
        if (!str) return 0;
        str = str.replace(/\s+/g, '');
        if (!str) return 0;

        let openCount = (str.match(/\(/g) || []).length;
        let closeCount = (str.match(/\)/g) || []).length;
        if (openCount > closeCount) {
            str += ')'.repeat(openCount - closeCount);
        }

        let parenRegex = /\(([^()]+)\)/;
        while (parenRegex.test(str)) {
            let match = str.match(parenRegex);
            let subVal = evaluateSimpleExpr(match[1]);
            if (subVal === 'Cannot divide by 0') return 'Cannot divide by 0';
            str = str.replace(match[0], subVal);
        }

        return evaluateSimpleExpr(str);
    }

    function evaluateSimpleExpr(expr) {
        if (!expr) return 0;
        let tokens = [];
        let i = 0;
        let len = expr.length;

        while (i < len) {
            let ch = expr[i];

            let lastToken = tokens[tokens.length - 1];
            if (ch === '-' && (tokens.length === 0 || ['+', '-', '*', '/'].includes(lastToken))) {
                let numStr = '-';
                i++;
                while (i < len && ((expr[i] >= '0' && expr[i] <= '9') || expr[i] === '.')) {
                    numStr += expr[i];
                    i++;
                }
                if (numStr === '-') numStr = '0';
                tokens.push(parseFloat(numStr) || 0);
                continue;
            }

            if (['+', '-', '*', '/'].includes(ch)) {
                tokens.push(ch);
                i++;
                continue;
            }

            if ((ch >= '0' && ch <= '9') || ch === '.') {
                let numStr = '';
                while (i < len && ((expr[i] >= '0' && expr[i] <= '9') || expr[i] === '.')) {
                    numStr += expr[i];
                    i++;
                }
                tokens.push(parseFloat(numStr) || 0);
                continue;
            }

            i++;
        }

        if (tokens.length === 0) return 0;

        // First pass: multiplication and division
        let j = 0;
        while (j < tokens.length) {
            if (tokens[j] === '*' || tokens[j] === '/') {
                let op = tokens[j];
                let prev = Number(tokens[j - 1]) || 0;
                let next = tokens[j + 1] !== undefined ? Number(tokens[j + 1]) : 0;

                let res = 0;
                if (op === '*') {
                    res = prev * next;
                } else {
                    if (next === 0) return 'Cannot divide by 0';
                    res = prev / next;
                }

                tokens.splice(j - 1, 3, res);
                j--;
            } else {
                j++;
            }
        }

        // Second pass: addition and subtraction
        j = 0;
        while (j < tokens.length) {
            if (tokens[j] === '+' || tokens[j] === '-') {
                let op = tokens[j];
                let prev = Number(tokens[j - 1]) || 0;
                let next = tokens[j + 1] !== undefined ? Number(tokens[j + 1]) : 0;

                let res = 0;
                if (op === '+') {
                    res = prev + next;
                } else {
                    res = prev - next;
                }

                tokens.splice(j - 1, 3, res);
                j--;
            } else {
                j++;
            }
        }

        return tokens[0] !== undefined ? tokens[0] : 0;
    }

    function calcPercent() {
        if (!calcExpression || calcExpression === '0') return;
        let expr = calcExpression;
        while (['+', '-', '*', '/', '.'].includes(expr.slice(-1))) {
            expr = expr.slice(0, -1);
        }
        if (!expr) return;

        const match = expr.match(/(.*?)([-+*/])?(\d+(?:\.\d+)?)$/);
        if (match) {
            const before = match[1] || '';
            const op = match[2];
            const num = parseFloat(match[3]);

            if (op === '+' || op === '-') {
                try {
                    const baseVal = safeEvaluate(before);
                    if (isFinite(baseVal)) {
                        const percentAmount = Math.round((Number(baseVal) * (num / 100) + Number.EPSILON) * 1000000) / 1000000;
                        calcExpression = before + op + percentAmount;
                        updateCalcDisplay();
                        return;
                    }
                } catch(e) {}
            }

            const percentVal = Math.round(((num / 100) + Number.EPSILON) * 1000000) / 1000000;
            calcExpression = (before || '') + (op || '') + percentVal;
            updateCalcDisplay();
        }
    }

    function calcEquals() {
        if (!calcExpression) return;
        try {
            let expr = calcExpression;
            while (['+', '-', '*', '/', '.'].includes(expr.slice(-1))) {
                expr = expr.slice(0, -1);
            }
            if (!expr) return;

            expr = expr.replace(/(^|[-+*/(])0+([1-9])/g, '$1$2');
            const result = safeEvaluate(expr);

            if (result === 'Cannot divide by 0' || !isFinite(result)) {
                const displayEl = document.getElementById('calcDisplay');
                if (displayEl) displayEl.textContent = 'Cannot divide by 0';
                calcHistoryText = expr + ' =';
                const historyEl = document.getElementById('calcHistory');
                if (historyEl) historyEl.textContent = calcHistoryText;
                calcExpression = '';
                calcJustEvaluated = true;
                return;
            }

            const rounded = Math.round((Number(result) + Number.EPSILON) * 1000000) / 1000000;
            calcHistoryText = expr + ' =';
            calcExpression = String(rounded);
            calcJustEvaluated = true;

            const displayEl = document.getElementById('calcDisplay');
            const historyEl = document.getElementById('calcHistory');
            if (displayEl) {
                displayEl.textContent = Number(rounded).toLocaleString('en-US', { maximumFractionDigits: 6 });
            }
            if (historyEl) {
                historyEl.textContent = calcHistoryText;
            }
        } catch (e) {
            console.error('Calculator Evaluation Error:', e, 'Expression:', calcExpression);
            const displayEl = document.getElementById('calcDisplay');
            if (displayEl) displayEl.textContent = 'Error';
            calcExpression = '';
            calcJustEvaluated = true;
        }
    }

    function handleCalcKeyboard(e) {
        const modal = document.getElementById('modalCalculator');
        if (!modal || modal.style.display !== 'flex') return;

        if (e.key === 'Escape') {
            e.preventDefault();
            toggleCalculator();
            return;
        }

        if (e.key >= '0' && e.key <= '9') {
            e.preventDefault();
            calcInput(e.key);
        } else if (['+', '-', '*', '/'].includes(e.key)) {
            e.preventDefault();
            calcInput(e.key);
        } else if (e.key === '.') {
            e.preventDefault();
            calcInput('.');
        } else if (e.key === '%') {
            e.preventDefault();
            calcPercent();
        } else if (e.key === 'Enter' || e.key === '=') {
            e.preventDefault();
            calcEquals();
        } else if (e.key === 'Backspace') {
            e.preventDefault();
            calcBackspace();
        } else if (e.key && e.key.toLowerCase() === 'c') {
            e.preventDefault();
            calcClear();
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.altKey && e.key && e.key.toLowerCase() === 'c') {
            e.preventDefault();
            toggleCalculator();
        }
    });

    // ─────────────────────────────────────────────────────────
    // 3. UNIVERSAL ACTION CONFIRMATION MODAL ENGINE
    // ─────────────────────────────────────────────────────────
    let pendingConfirmAction = null;

    function showConfirmPopup({
        icon = '⚡',
        title = 'Confirm Action',
        subtitle = 'Review what will happen before proceeding:',
        items = [],
        message = '',
        impact = null,
        confirmText = '✅ Yes, Proceed',
        confirmClass = 'btn-success',
        borderColor = '#3b82f6',
        onConfirm = null,
        form = null
    }) {
        const iconEl = document.getElementById('globalConfirmIcon');
        const titleEl = document.getElementById('globalConfirmTitle');
        const subEl = document.getElementById('globalConfirmSubtitle');
        if (iconEl) iconEl.textContent = icon;
        if (titleEl) titleEl.textContent = title;
        if (subEl) subEl.textContent = subtitle;

        const card = document.getElementById('globalConfirmCard');
        if (card) card.style.borderColor = borderColor;

        const bodyEl = document.getElementById('globalConfirmBody');
        if (bodyEl) {
            bodyEl.innerHTML = '';
            if (items && items.length > 0) {
                items.forEach(item => {
                    const row = document.createElement('div');
                    row.style.display = 'flex';
                    row.style.justifyContent = 'space-between';
                    row.style.alignItems = 'center';
                    row.style.borderBottom = '1px dashed #334155';
                    row.style.paddingBottom = '0.45rem';

                    const labelSpan = document.createElement('span');
                    labelSpan.style.color = '#94a3b8';
                    labelSpan.textContent = item.label + ':';

                    const valSpan = document.createElement('strong');
                    valSpan.textContent = item.value;
                    valSpan.style.color = item.color || '#f8fafc';
                    if (item.size) valSpan.style.fontSize = item.size;

                    row.appendChild(labelSpan);
                    row.appendChild(valSpan);
                    bodyEl.appendChild(row);
                });
            } else if (message) {
                const p = document.createElement('div');
                p.style.color = '#cbd5e1';
                p.style.lineHeight = '1.5';
                p.innerHTML = message;
                bodyEl.appendChild(p);
            }
        }

        const impactWrap = document.getElementById('globalConfirmImpactWrap');
        const impactEl = document.getElementById('globalConfirmImpact');
        if (impact && impact.text && impactWrap && impactEl) {
            impactWrap.style.display = 'block';
            impactEl.textContent = impact.text;
            if (impact.type === 'danger') {
                impactEl.style.background = 'rgba(220,38,38,0.15)';
                impactEl.style.color = '#f87171';
                impactEl.style.border = '1px solid #ef4444';
            } else if (impact.type === 'warning') {
                impactEl.style.background = 'rgba(245,158,11,0.15)';
                impactEl.style.color = '#fbbf24';
                impactEl.style.border = '1px solid #f59e0b';
            } else if (impact.type === 'info') {
                impactEl.style.background = 'rgba(59,130,246,0.15)';
                impactEl.style.color = '#60a5fa';
                impactEl.style.border = '1px solid #3b82f6';
            } else {
                impactEl.style.background = 'rgba(34,197,94,0.15)';
                impactEl.style.color = '#4ade80';
                impactEl.style.border = '1px solid #22c55e';
            }
        } else if (impactWrap) {
            impactWrap.style.display = 'none';
        }

        const proceedBtn = document.getElementById('globalConfirmProceedBtn');
        if (proceedBtn) {
            proceedBtn.textContent = confirmText;
            proceedBtn.className = 'btn ' + confirmClass;
            proceedBtn.disabled = false;
        }

        pendingConfirmAction = () => {
            if (typeof onConfirm === 'function') {
                onConfirm();
            } else if (form) {
                try {
                    form.dataset.confirmed = 'true';
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                } catch (e) {
                    HTMLFormElement.prototype.submit.call(form);
                }
            }
        };

        const modal = document.getElementById('modalGlobalConfirm');
        if (modal) modal.style.display = 'flex';
    }

    function closeGlobalConfirm() {
        const modal = document.getElementById('modalGlobalConfirm');
        if (modal) modal.style.display = 'none';
        pendingConfirmAction = null;
        const proceedBtn = document.getElementById('globalConfirmProceedBtn');
        if (proceedBtn) proceedBtn.disabled = false;
        if (window.lastConfirmParentModal) {
            window.lastConfirmParentModal.style.display = 'flex';
            window.lastConfirmParentModal = null;
        }
    }

    function executeGlobalConfirm() {
        const proceedBtn = document.getElementById('globalConfirmProceedBtn');
        if (proceedBtn) {
            proceedBtn.disabled = true;
            proceedBtn.textContent = '⏳ Processing...';
        }
        const act = pendingConfirmAction;
        window.lastConfirmParentModal = null;
        closeGlobalConfirm();
        if (act) act();
    }

    // ─────────────────────────────────────────────────────────
    // 4. ACTION BLOCKED MODAL ENGINE
    // ─────────────────────────────────────────────────────────
    let actionBlockedFocusTarget = null;

    function showActionBlockedModal({
        title = 'Action Blocked',
        subtitle = 'Business Rule & Constraint Validation Failed',
        errors = [],
        focus = null
    }) {
        const titleEl = document.getElementById('actionBlockedTitle');
        const subEl = document.getElementById('actionBlockedSubtitle');
        if (titleEl) titleEl.textContent = title;
        if (subEl) subEl.textContent = subtitle;

        const listEl = document.getElementById('actionBlockedReasonsList');
        if (listEl) {
            listEl.innerHTML = '';
            actionBlockedFocusTarget = focus || (errors.length > 0 ? errors[0].focus : null);

            errors.forEach(err => {
                const item = document.createElement('div');
                item.style.display = 'flex';
                item.style.alignItems = 'flex-start';
                item.style.gap = '0.65rem';
                item.innerHTML = `
                    <span style="color: #ef4444; font-size: 1.1rem; line-height: 1.2;">⚠️</span>
                    <div style="flex: 1;">
                        <strong style="color: #f8fafc; font-size: 0.88rem; display: block;">${err.title || 'Validation Error'}</strong>
                        <div style="font-size: 0.8rem; color: #cbd5e1; margin-top: 0.15rem; line-height: 1.35;">${err.desc || err}</div>
                    </div>
                `;
                listEl.appendChild(item);
            });
        }

        const modal = document.getElementById('modalActionBlocked');
        if (modal) modal.style.display = 'flex';
    }

    function closeActionBlockedModal() {
        const modal = document.getElementById('modalActionBlocked');
        if (modal) modal.style.display = 'none';

        if (actionBlockedFocusTarget) {
            const target = typeof actionBlockedFocusTarget === 'string' ? document.getElementById(actionBlockedFocusTarget) : actionBlockedFocusTarget;
            if (target && typeof target.focus === 'function') {
                target.focus();
                if (typeof target.select === 'function' && target.type !== 'date' && target.type !== 'time') target.select();
                target.classList.add('invalid-highlight');
                setTimeout(() => target.classList.remove('invalid-highlight'), 2500);
            }
            actionBlockedFocusTarget = null;
        }
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'flex';
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    // Backdrop click and escape key
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('modal-backdrop')) {
            e.target.style.display = 'none';
            if (e.target.id === 'modalGlobalConfirm') {
                pendingConfirmAction = null;
            }
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop').forEach(modal => {
                if (modal.style.display !== 'none') {
                    modal.style.display = 'none';
                }
            });
            pendingConfirmAction = null;
        }
    });

    // ─────────────────────────────────────────────────────────
    // 5. UNIVERSAL FORM SUBMISSION GUARD & ACTION POINT SENTINEL
    // ─────────────────────────────────────────────────────────
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;

        const method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (method === 'GET' && !form.dataset.confirm) {
            return;
        }

        if (form.dataset.confirmed === 'true') {
            return;
        }

        if (e.defaultPrevented) {
            return;
        }

        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopImmediatePropagation();

            const errors = [];
            const elements = form.elements;
            for (let i = 0; i < elements.length; i++) {
                const el = elements[i];
                if (!el.validity || el.validity.valid) continue;

                let labelText = '';
                if (el.id) {
                    const lbl = form.querySelector(`label[for="${el.id}"]`);
                    if (lbl) labelText = lbl.textContent.trim();
                }
                if (!labelText) {
                    const parentLabel = el.closest('label');
                    if (parentLabel) labelText = parentLabel.textContent.trim();
                }
                if (!labelText) {
                    const formGroup = el.closest('.form-group');
                    if (formGroup) {
                        const fgLabel = formGroup.querySelector('label');
                        if (fgLabel) labelText = fgLabel.textContent.trim();
                    }
                }
                if (!labelText) {
                    labelText = el.getAttribute('placeholder') || el.name || 'Required Input';
                }
                labelText = labelText.replace(/[:\*]/g, '').trim();

                let desc = el.validationMessage || 'This field is required and must have a valid value.';
                if (el.validity.valueMissing) {
                    desc = `"${labelText}" is mandatory and cannot be left blank.`;
                } else if (el.validity.typeMismatch) {
                    desc = `The value entered for "${labelText}" does not match the required format.`;
                } else if (el.validity.rangeUnderflow) {
                    desc = `The value for "${labelText}" cannot be less than ${el.min}.`;
                } else if (el.validity.rangeOverflow) {
                    desc = `The value for "${labelText}" cannot be greater than ${el.max}.`;
                } else if (el.validity.tooShort) {
                    desc = `"${labelText}" must be at least ${el.minLength} characters long.`;
                }

                errors.push({
                    title: labelText,
                    desc: desc,
                    focus: el.id || el
                });
            }

            if (errors.length > 0) {
                showActionBlockedModal({
                    title: 'Form Submission Blocked',
                    subtitle: 'Please correct the following requirements before submitting:',
                    errors: errors
                });
            }
            return false;
        }

        if (form.dataset.noConfirm === 'true') {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        const formAction = (form.getAttribute('action') || '').toLowerCase();
        const isDeleteOrVoid = formAction.includes('delete') || formAction.includes('destroy') || formAction.includes('void') || formAction.includes('recall') || form.dataset.confirmType === 'danger';
        const isFinancial = formAction.includes('pay') || formAction.includes('checkout') || formAction.includes('refund') || formAction.includes('expense');
        const isInventory = formAction.includes('stock') || formAction.includes('transfer') || formAction.includes('adjust');

        let icon = '⚡';
        let title = form.dataset.confirmTitle || 'Confirm Action';
        let subtitle = form.dataset.confirmSubtitle || 'Please review and confirm before proceeding:';
        let confirmText = form.dataset.confirmButton || '✅ Yes, Proceed';
        let confirmClass = 'btn-success';
        let borderColor = '#3b82f6';
        let impact = null;

        if (isDeleteOrVoid) {
            icon = '🗑️';
            title = form.dataset.confirmTitle || 'Confirm Deletion / Reversal';
            subtitle = form.dataset.confirmSubtitle || 'Warning: This action will permanently remove or reverse records:';
            confirmText = form.dataset.confirmButton || '🗑️ Yes, Proceed';
            confirmClass = 'btn-danger';
            borderColor = '#ef4444';
            impact = {
                text: '🚨 HIGH-IMPACT ACTION: Please verify you intend to permanently delete or reverse this record.',
                type: 'danger'
            };
        } else if (isFinancial) {
            icon = '💳';
            title = form.dataset.confirmTitle || 'Confirm Financial Transaction';
            subtitle = form.dataset.confirmSubtitle || 'Authorize financial posting to business accounts:';
            confirmText = form.dataset.confirmButton || '💳 Yes, Confirm Transaction';
            confirmClass = 'btn-success';
            borderColor = '#22c55e';
            impact = {
                text: '✓ FINANCIAL AUDIT: This will update cash, bank, or customer debt ledgers immediately.',
                type: 'success'
            };
        } else if (isInventory) {
            icon = '📦';
            title = form.dataset.confirmTitle || 'Confirm Inventory Movement';
            subtitle = form.dataset.confirmSubtitle || 'Authorize updates to physical warehouse stock levels:';
            confirmText = form.dataset.confirmButton || '📦 Yes, Update Stock';
            confirmClass = 'btn-primary';
            borderColor = '#3b82f6';
            impact = {
                text: '🛡️ INVENTORY MUTATION: Shelf counts and immutable audit logs will be updated.',
                type: 'info'
            };
        }

        const summaryItems = [];
        const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
        inputs.forEach(inp => {
            if (inp.value && inp.type !== 'password' && !inp.name.startsWith('_')) {
                let lbl = '';
                const formGroup = inp.closest('.form-group');
                if (formGroup) {
                    const fgLabel = formGroup.querySelector('label');
                    if (fgLabel) lbl = fgLabel.textContent.replace(/[:\*]/g, '').trim();
                }
                if (!lbl) lbl = inp.getAttribute('placeholder') || inp.name;
                if (lbl && summaryItems.length < 5) {
                    let displayVal = inp.value;
                    if (inp.tagName === 'SELECT' && inp.selectedIndex >= 0) {
                        displayVal = inp.options[inp.selectedIndex].text;
                    }
                    summaryItems.push({
                        label: lbl,
                        value: displayVal
                    });
                }
            }
        });

        const parentModal = form.closest('.modal-backdrop');
        if (parentModal && parentModal.id !== 'modalGlobalConfirm') {
            window.lastConfirmParentModal = parentModal;
            parentModal.style.display = 'none';
        }

        showConfirmPopup({
            icon: icon,
            title: title,
            subtitle: subtitle,
            items: summaryItems,
            impact: impact,
            confirmText: confirmText,
            confirmClass: confirmClass,
            borderColor: borderColor,
            onConfirm: function() {
                form.dataset.confirmed = 'true';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    HTMLFormElement.prototype.submit.call(form);
                }
            }
        });
        return false;
    }, true);

    // ─────────────────────────────────────────────────────────
    // 6. GLOBAL SEARCHABLE PRODUCT PICKER ENGINE
    // ─────────────────────────────────────────────────────────
    window.spcInstances = window.spcInstances || {};

    function escapeSpcHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    window.initSearchableProductPickers = function() {
        document.querySelectorAll('.searchable-product-picker').forEach(wrapper => {
            const select = wrapper.querySelector('.spc-raw-select');
            if (!select) return;
            const id = select.id;
            const products = [];

            Array.from(select.options).forEach(opt => {
                if (!opt.value) return;
                products.push({
                    id: opt.value,
                    name: opt.dataset.name || opt.text,
                    code: opt.dataset.code || '',
                    category: opt.dataset.category || '',
                    price: parseFloat(opt.dataset.price || 0)
                });
            });

            window.spcInstances[id] = {
                id: id,
                wrapper: wrapper,
                select: select,
                products: products,
                filtered: products,
                highlightedIndex: -1,
                isOpen: false
            };

            if (select.value) {
                const p = products.find(prod => String(prod.id) === String(select.value));
                if (p) {
                    window.spcShowSelected(id, p);
                }
            }
        });
    };

    window.spcOpen = function(id) {
        const inst = window.spcInstances[id];
        if (!inst) {
            window.initSearchableProductPickers();
            if (!window.spcInstances[id]) return;
        }
        const instance = window.spcInstances[id];
        const input = document.getElementById('spc_input_' + id);
        const query = input ? input.value : '';
        window.spcFilter(id, query);
        const dropdown = document.getElementById('spc_dropdown_' + id);
        if (dropdown) dropdown.style.display = 'block';
        instance.isOpen = true;
    };

    window.spcClose = function(id) {
        const dropdown = document.getElementById('spc_dropdown_' + id);
        if (dropdown) dropdown.style.display = 'none';
        const inst = window.spcInstances[id];
        if (inst) {
            inst.isOpen = false;
            inst.highlightedIndex = -1;
        }
    };

    window.spcFilter = function(id, query) {
        const inst = window.spcInstances[id];
        if (!inst) return;
        const q = (query || '').toLowerCase().trim();
        const clearBtn = document.getElementById('spc_clear_btn_' + id);
        if (clearBtn) clearBtn.style.display = q.length > 0 ? 'block' : 'none';

        if (!q) {
            inst.filtered = inst.products;
        } else {
            inst.filtered = inst.products.filter(p => {
                return (p.name && p.name.toLowerCase().includes(q)) ||
                       (p.code && p.code.toLowerCase().includes(q)) ||
                       (p.category && p.category.toLowerCase().includes(q));
            });
        }

        inst.highlightedIndex = inst.filtered.length > 0 ? 0 : -1;
        window.spcRenderList(id, q);
    };

    window.spcRenderList = function(id, query) {
        const inst = window.spcInstances[id];
        if (!inst) return;
        const listEl = document.getElementById('spc_list_' + id);
        const emptyEl = document.getElementById('spc_empty_' + id);
        const termEl = document.getElementById('spc_term_' + id);
        if (!listEl) return;

        if (inst.filtered.length === 0) {
            listEl.innerHTML = '';
            if (emptyEl) emptyEl.style.display = 'block';
            if (termEl) termEl.textContent = query;
            return;
        }

        if (emptyEl) emptyEl.style.display = 'none';

        const renderSlice = inst.filtered.slice(0, 40);
        let html = '';

        renderSlice.forEach((p, idx) => {
            const isHigh = (idx === inst.highlightedIndex);
            const highBg = isHigh ? 'background: #1e293b;' : '';
            const priceHtml = p.price > 0 
                ? `<span style="color: #34d399; font-weight: 700; font-size: 0.78rem;">₦${Number(p.price).toLocaleString()}</span>` 
                : '';

            html += `
            <div class="spc-item" data-idx="${idx}" onclick="window.spcSelect('${id}', '${p.id}')"
                 onmouseenter="window.spcHighlight('${id}', ${idx})"
                 style="padding: 0.65rem 0.95rem; cursor: pointer; border-bottom: 1px solid rgba(55,65,81,0.4); ${highBg} transition: background 0.12s;">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.65rem;">
                    <span style="font-weight: 700; color: #f9fafb; font-size: 0.88rem;">${escapeSpcHtml(p.name)}</span>
                    <span style="background: rgba(37,99,235,0.2); color: #60a5fa; border: 1px solid rgba(37,99,235,0.4); padding: 0.12rem 0.45rem; border-radius: 4px; font-size: 0.72rem; font-family: monospace; font-weight: 700; flex-shrink: 0;">${escapeSpcHtml(p.code)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.25rem; font-size: 0.75rem; color: #9ca3af;">
                    <span>${escapeSpcHtml(p.category || 'General')}</span>
                    ${priceHtml}
                </div>
            </div>`;
        });

        if (inst.filtered.length > 40) {
            html += `<div style="padding: 0.5rem; text-align: center; color: #64748b; font-size: 0.75rem;">+ ${inst.filtered.length - 40} more products. Refine search to narrow down.</div>`;
        }

        listEl.innerHTML = html;
    };

    window.spcHighlight = function(id, idx) {
        const inst = window.spcInstances[id];
        if (!inst) return;
        inst.highlightedIndex = idx;
        const listEl = document.getElementById('spc_list_' + id);
        if (listEl) {
            listEl.querySelectorAll('.spc-item').forEach((item, i) => {
                item.style.background = (i === idx) ? '#1e293b' : 'transparent';
            });
        }
    };

    window.spcSelect = function(id, productId) {
        const inst = window.spcInstances[id];
        if (!inst) return;
        const p = inst.products.find(item => String(item.id) === String(productId));
        if (!p) return;

        inst.select.value = p.id;
        inst.select.dispatchEvent(new Event('change', { bubbles: true }));

        window.spcShowSelected(id, p);
        window.spcClose(id);

        setTimeout(() => {
            const wrapper = document.getElementById('spc_wrapper_' + id);
            const container = (wrapper && wrapper.closest('form, .modal, .modal-card, .card')) || document;
            if (wrapper && container) {
                const focusable = Array.from(container.querySelectorAll(
                    'input:not([type="hidden"]):not([type="button"]):not([type="reset"]):not([disabled]):not([readonly]):not(.spc-input), select:not([disabled]):not(.spc-raw-select), textarea:not([disabled])'
                )).filter(el => el.offsetParent !== null);
                const nextElem = focusable.find(el => (wrapper.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING) && !wrapper.contains(el));
                if (nextElem) {
                    nextElem.focus();
                    if (typeof nextElem.select === 'function') nextElem.select();
                }
            }
        }, 50);
    };

    window.spcShowSelected = function(id, p) {
        const selectedView = document.getElementById('spc_selected_' + id);
        const searchBox = document.getElementById('spc_search_' + id);
        if (selectedView) {
            const nameEl = selectedView.querySelector('.spc-selected-name');
            const codeEl = selectedView.querySelector('.spc-selected-code');
            const catEl = selectedView.querySelector('.spc-selected-category');
            if (nameEl) nameEl.textContent = p.name;
            if (codeEl) codeEl.textContent = p.code;
            if (catEl) catEl.textContent = p.category ? '• ' + p.category : '';
            selectedView.style.display = 'flex';
        }
        if (searchBox) searchBox.style.display = 'none';
    };

    window.spcReset = function(id) {
        const inst = window.spcInstances[id];
        if (inst) {
            inst.select.value = "";
            inst.select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const selectedView = document.getElementById('spc_selected_' + id);
        const searchBox = document.getElementById('spc_search_' + id);
        const input = document.getElementById('spc_input_' + id);

        if (selectedView) selectedView.style.display = 'none';
        if (searchBox) searchBox.style.display = 'block';
        if (input) {
            input.value = '';
            input.focus();
        }
        window.spcOpen(id);
    };

    window.spcClearInput = function(id) {
        const input = document.getElementById('spc_input_' + id);
        if (input) {
            input.value = '';
            input.focus();
        }
        window.spcFilter(id, '');
    };

    window.spcKeydown = function(id, event) {
        const inst = window.spcInstances[id];
        if (!inst) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (inst.highlightedIndex < inst.filtered.length - 1) {
                window.spcHighlight(id, inst.highlightedIndex + 1);
            }
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (inst.highlightedIndex > 0) {
                window.spcHighlight(id, inst.highlightedIndex - 1);
            }
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (inst.highlightedIndex >= 0 && inst.highlightedIndex < inst.filtered.length) {
                const chosen = inst.filtered[inst.highlightedIndex];
                window.spcSelect(id, chosen.id);
            }
        } else if (event.key === 'Escape') {
            window.spcClose(id);
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        window.initSearchableProductPickers();
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.searchable-product-picker')) {
            if (window.spcInstances) {
                Object.keys(window.spcInstances).forEach(id => {
                    window.spcClose(id);
                });
            }
        }
    });

    // ─────────────────────────────────────────────────────────
    // 7. MOBILE SIDEBAR DRAWER TOGGLE
    // ─────────────────────────────────────────────────────────
    window.toggleMobileSidebar = function(force) {
        const sidebar = document.querySelector('.sidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (!sidebar) return;
        const isOpen = typeof force === 'boolean' ? force : !sidebar.classList.contains('open');
        if (isOpen) {
            sidebar.classList.add('open');
            if (backdrop) backdrop.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.sidebar .nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    window.toggleMobileSidebar(false);
                }
            });
        });
    });

    // ─────────────────────────────────────────────────────────
    // 8. UNIVERSAL PASSWORD VISIBILITY TOGGLE (👁️ / 🙈)
    // ─────────────────────────────────────────────────────────
    window.initPasswordToggles = function(context) {
        const root = context || document;
        const passInputs = root.querySelectorAll('input[type="password"], input[data-password-toggle="true"]');
        passInputs.forEach(input => {
            if (input.dataset.hasPasswordToggle === 'true') return;
            input.dataset.hasPasswordToggle = 'true';

            let wrapper = input.parentElement;
            if (!wrapper || !wrapper.classList.contains('password-field-wrapper')) {
                wrapper = document.createElement('div');
                wrapper.className = 'password-field-wrapper';
                input.parentNode.insertBefore(wrapper, input);
                wrapper.appendChild(input);
            }

            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'password-toggle-btn';
            toggleBtn.setAttribute('aria-label', 'Toggle password visibility');
            toggleBtn.setAttribute('tabindex', '-1');
            toggleBtn.innerHTML = '👁️';
            toggleBtn.title = 'Show password';

            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (input.type === 'password') {
                    input.type = 'text';
                    input.dataset.passwordToggle = 'true';
                    toggleBtn.innerHTML = '🙈';
                    toggleBtn.title = 'Hide password';
                } else {
                    input.type = 'password';
                    toggleBtn.innerHTML = '👁️';
                    toggleBtn.title = 'Show password';
                }
                input.focus();
            });

            wrapper.appendChild(toggleBtn);
        });
    };

    document.addEventListener('DOMContentLoaded', function() {
        window.initPasswordToggles();

        if (window.MutationObserver) {
            const observer = new MutationObserver(function(mutations) {
                let shouldCheck = false;
                for (let m of mutations) {
                    if (m.addedNodes && m.addedNodes.length > 0) {
                        shouldCheck = true;
                        break;
                    }
                }
                if (shouldCheck) {
                    window.initPasswordToggles();
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    });

    // ─────────────────────────────────────────────────────────
    // 9. UNIVERSAL ENTER-KEY ADVANCEMENT ACROSS FORM INPUTS & MODALS
    // ─────────────────────────────────────────────────────────
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        if (e.shiftKey || e.altKey) return;

        const target = e.target;
        if (!target || !target.matches('input:not([type="submit"]):not([type="button"]):not([type="reset"]), select')) {
            return;
        }

        if (target.tagName === 'TEXTAREA' || target.getAttribute('data-enter-ignore') === 'true') {
            return;
        }

        if (target.id === 'searchInput' && window.location.pathname.includes('/pos')) {
            return;
        }

        const modalContainer = target.closest('.modal, .modal-card, #modalQuickCustomer, #modalSaleConfirm');
        const container = modalContainer || target.closest('form, .cart-drawer, .card') || document;
        const form = target.closest('form');

        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            const submitBtn = container.querySelector('button[type="submit"], input[type="submit"], #completeSaleBtn, #btnSaveQuickCust, #btnFinalProceedSale, button.btn-primary');
            if (submitBtn) {
                submitBtn.click();
            } else if (form) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
            return;
        }

        const focusable = Array.from(container.querySelectorAll(
            'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled])'
        )).filter(el => el.offsetParent !== null && !el.hasAttribute('disabled') && !el.hasAttribute('readonly'));

        const currentIndex = focusable.indexOf(target);
        if (currentIndex === -1) return;

        e.preventDefault();

        if (currentIndex + 1 < focusable.length) {
            const nextElement = focusable[currentIndex + 1];
            nextElement.focus();
            if (typeof nextElement.select === 'function' && nextElement.type !== 'date' && nextElement.type !== 'time') {
                nextElement.select();
            }
        } else {
            const submitBtn = container.querySelector('button[type="submit"], input[type="submit"], #completeSaleBtn, #btnSaveQuickCust, #btnFinalProceedSale, button.btn-primary, button.btn-success');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.click();
            } else if (form) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        }
    });

    // ─────────────────────────────────────────────────────────
    // 10. RESPONSIVE LIVE FILTERS & TABLE UTILITIES
    // ─────────────────────────────────────────────────────────
    window.filterTableRows = function(tableId, query) {
        const q = (query || '').toLowerCase().trim();
        const table = document.getElementById(tableId);
        if (!table) return;
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(r => {
            if (r.classList.contains('no-filter') || r.querySelector('th')) return;
            const text = r.textContent.toLowerCase();
            r.style.display = text.includes(q) ? '' : 'none';
        });
    };

    window.toggleGlobalBranchDropdown = function(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const wrap = document.getElementById('globalBranchDropdownWrapper');
        if (!wrap) return;
        const isOpen = wrap.classList.contains('open');
        wrap.classList.toggle('open', !isOpen);
        const btn = document.getElementById('globalBranchDropdownBtn');
        if (btn) btn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
    };

    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('globalBranchDropdownWrapper');
        if (wrap && !wrap.contains(e.target)) {
            wrap.classList.remove('open');
            const btn = document.getElementById('globalBranchDropdownBtn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const filterForms = document.querySelectorAll('form[method="GET"], form.filter-form, .filter-hub form');
        filterForms.forEach(form => {
            let debounceTimer = null;
            const inputs = form.querySelectorAll('input[type="text"], input[type="search"], input[type="number"]');
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        form.classList.add('filtering-active');
                        form.submit();
                    }, 450);
                });
            });

            const selects = form.querySelectorAll('select');
            selects.forEach(sel => {
                sel.addEventListener('change', function() {
                    form.classList.add('filtering-active');
                    form.submit();
                });
            });
        });

        // Instant client-side row filtering on pre-rendered tables (0ms latency!)
        const searchInputs = document.querySelectorAll('input[name="search"], input[placeholder*="Search"]');
        searchInputs.forEach(searchInput => {
            if (searchInput.id === 'searchInput') return;
            const container = searchInput.closest('.card, .container, main');
            if (!container) return;
            const table = container.querySelector('table');
            if (!table) return;

            searchInput.addEventListener('input', function(e) {
                const query = (e.target.value || '').trim().toLowerCase();
                const tbody = table.querySelector('tbody');
                if (!tbody) return;
                const rows = Array.from(tbody.querySelectorAll('tr'));
                if (rows.length === 0) return;

                rows.forEach(row => {
                    if (row.classList.contains('no-filter') || row.querySelector('th')) return;
                    const text = (row.textContent || '').toLowerCase();
                    if (!query || text.includes(query)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
    });

    // ─────────────────────────────────────────────────────────
    // EXPORT GLOBALS TO WINDOW
    // ─────────────────────────────────────────────────────────
    window.toggleCalculator = toggleCalculator;
    window.handleCalcBackdropClick = handleCalcBackdropClick;
    window.updateCalcDisplay = updateCalcDisplay;
    window.calcInput = calcInput;
    window.calcBackspace = calcBackspace;
    window.calcClear = calcClear;
    window.calcPercent = calcPercent;
    window.calcEquals = calcEquals;
    window.showConfirmPopup = showConfirmPopup;
    window.closeGlobalConfirm = closeGlobalConfirm;
    window.executeGlobalConfirm = executeGlobalConfirm;
    window.showActionBlockedModal = showActionBlockedModal;
    window.closeActionBlockedModal = closeActionBlockedModal;
    window.openModal = openModal;
    window.closeModal = closeModal;
})();
