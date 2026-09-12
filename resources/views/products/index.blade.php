@extends('layouts.app')

@section('title', 'Products Catalog')

@push('styles')
<style>
    .prod-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .table-wrap {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    th {
        background: rgba(11, 15, 25, 0.8);
        padding: 1rem 1.25rem;
        font-size: 0.8rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--border);
    }

    td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.95rem;
    }

    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(55, 65, 81, 0.25); }
</style>
@endpush

@section('content')

    @php $isAdmin = (Auth::user()?->role === 'admin' || !Auth::check()); @endphp

    <div class="prod-header">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 800;">Products & Pricing Catalog 🛍️</h2>
            <p style="font-size: 0.9rem; color: var(--text-muted);">
                @if($isAdmin)
                    Manage central product codes, master pricing, and stock across all shop locations.
                @else
                    View official catalog pricing and physical stock on ground across all shop branches.
                @endif
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <a href="{{ route('products.export.csv', request()->query()) }}" class="btn btn-secondary" style="font-size: 0.85rem;">
                📥 Export CSV
            </a>
            <a href="{{ route('products.export.json', request()->query()) }}" class="btn btn-secondary" style="font-size: 0.85rem; color: #93c5fd;">
                🤖 Export JSON (AI)
            </a>
            <button onclick="window.print()" class="btn btn-secondary" style="font-size: 0.85rem;">
                🖨️ Print Price List
            </button>
            @if($isAdmin)
                <a href="{{ route('products.template.csv') }}" class="btn btn-secondary" style="font-size: 0.85rem;">
                    📄 CSV Template
                </a>
                <button class="btn btn-primary" onclick="openModal('modalImportCsv')" style="font-size: 0.85rem;">
                    📥 Bulk Import (CSV)
                </button>
                <button class="btn btn-success" onclick="openModal('modalAddProduct')" style="font-size: 0.85rem;">
                    ➕ Add New Product
                </button>
            @else
                <a href="{{ route('stock.index') }}" class="btn btn-success btn-lg" style="font-size: 0.95rem;">
                    📥 Add Stock Quantity (Stock In)
                </a>
            @endif
        </div>
    </div>

    <!-- 1. MULTI-CRITERIA FILTER BAR -->
    <div style="background: var(--card-bg); border: 1px solid var(--border); border-radius: 18px; padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('products.index') }}">
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; align-items: center;">
                <span style="font-size: 0.8rem; font-weight: 800; color: var(--text-muted);">STOCK HEALTH:</span>
                <a href="{{ route('products.index', array_merge(request()->except('stock_status'), ['stock_status' => ''])) }}" 
                   class="badge {{ !request('stock_status') ? 'badge-primary' : 'badge-secondary' }}" style="padding: 0.4rem 0.85rem; text-decoration: none;">
                   All ({{ $products->count() }})
                </a>
                <a href="{{ route('products.index', array_merge(request()->except('stock_status'), ['stock_status' => 'IN_STOCK'])) }}" 
                   class="badge {{ request('stock_status') === 'IN_STOCK' ? 'badge-success' : 'badge-secondary' }}" style="padding: 0.4rem 0.85rem; text-decoration: none;">
                   🟢 In Stock
                </a>
                <a href="{{ route('products.index', array_merge(request()->except('stock_status'), ['stock_status' => 'LOW_STOCK'])) }}" 
                   class="badge {{ request('stock_status') === 'LOW_STOCK' ? 'badge-warning' : 'badge-secondary' }}" title="Products at or below their individual minimum stock alert level" style="padding: 0.4rem 0.85rem; text-decoration: none;">
                   🟡 Low Stock (≤ Min Alert)
                </a>
                <a href="{{ route('products.index', array_merge(request()->except('stock_status'), ['stock_status' => 'OUT_OF_STOCK'])) }}" 
                   class="badge {{ request('stock_status') === 'OUT_OF_STOCK' ? 'badge-danger' : 'badge-secondary' }}" style="padding: 0.4rem 0.85rem; text-decoration: none;">
                   🔴 Out of Stock (0 units)
                </a>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; align-items: end;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Category</label>
                    <select name="category">
                        <option value="">-- All Categories --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Min Price (₦)</label>
                    <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="e.g. 1000">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Max Price (₦)</label>
                    <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="e.g. 80000">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 0.75rem;">Search Product / SKU / Brand</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="e.g. Rice, M10DE, Devon Kings">
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.65rem;">🔍 Apply Filters</button>
                    <a href="{{ route('products.index') }}" class="btn btn-secondary" style="padding: 0.65rem;">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Products Table with Multi-Branch Stocks -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800;">Catalog Inventory List ({{ $products->count() }} items)</h3>
        </div>

        <div class="table-wrap">
            <table id="productsTable">
                <thead>
                    <tr>
                        <th>Product SKU</th>
                        <th>Category</th>
                        <th>Brand / Size</th>
                        <th>Selling Price (₦)</th>
                        @foreach($warehouses as $wh)
                            <th style="color: #60a5fa;">{{ $wh->name }}</th>
                        @endforeach
                        <th>Total Stock</th>
                        <th>Stock Health</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                    <tr id="product-row-{{ $p->id }}" data-id="{{ $p->id }}">
                        <td>
                            <strong style="font-size: 1.05rem; color: #60a5fa; letter-spacing: 0.03em;">{{ $p->code }}</strong>
                            <div class="product-name" style="font-size: 0.95rem; font-weight: 700; color: #f8fafc; margin-top: 0.2rem;">{{ $p->name }}</div>
                        </td>
                        <td><span class="badge badge-info product-cat">{{ $p->category }}</span></td>
                        <td class="product-brand-size">{{ $p->brand ?? 'Standard' }} {{ $p->size ? '('.$p->size.')' : '' }}</td>
                        <td class="product-price" style="font-size: 1.15rem; font-weight: 800; color: #4ade80;">
                            ₦{{ number_format($p->unitPrice, 0) }}
                        </td>
                        @foreach($warehouses as $wh)
                            <td>
                                <strong style="color: #cbd5e1;">{{ $p->branch_stocks[$wh->id] ?? 0 }}</strong>
                            </td>
                        @endforeach
                        <td>
                            @php $totStock = $p->total_physical_stock ?? $p->currentStock; @endphp
                            <span style="font-size: 1.15rem; font-weight: 800; color: {{ $totStock > 5 ? '#4ade80' : ($totStock > 0 ? '#fbbf24' : '#f87171') }};">
                                {{ $totStock }} units
                            </span>
                        </td>
                        <td>
                            @if($totStock <= 0)
                                <span class="badge badge-danger">OUT OF STOCK</span>
                            @elseif($totStock <= ($p->minStockLevel ?? 5))
                                <span class="badge badge-warning">LOW (≤ {{ $p->minStockLevel ?? 5 }})</span>
                            @else
                                <span class="badge badge-success">IN STOCK</span>
                            @endif
                        </td>
                        <td>
                            @if($isAdmin)
                                <button class="btn btn-secondary btn-edit-product" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;"
                                        onclick="openEditModal('{{ $p->id }}', '{{ addslashes($p->name) }}', '{{ addslashes($p->category) }}', {{ $p->unitPrice }}, '{{ addslashes($p->brand ?? '') }}', '{{ addslashes($p->size ?? '') }}')">
                                    ✏️ Edit
                                </button>
                            @else
                                <a href="{{ route('stock.index') }}" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; color: #4ade80;">
                                    📥 +Stock
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ 6 + count($warehouses) }}" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No products found in catalog. Tap <strong>➕ Add New Product</strong> above!
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Add New Product -->
    <div id="modalAddProduct" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">➕ Add New Product to Catalog</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                Register a new inventory SKU for sales and stock tracking.
            </p>

            <form id="addProductForm" method="POST" action="{{ route('products.store') }}">
                @csrf
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" id="addProdName" placeholder="e.g. Bag of Rice (50kg), Indomie Super Pack" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Item Code / SKU</label>
                        <input type="text" name="code" id="addProdCode" placeholder="e.g. RICE-50KG" required>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Category</span>
                            <span style="font-size: 0.72rem; color: #93c5fd; font-weight: normal;">Select or type new</span>
                        </label>
                        <input list="categoryDatalist" name="category" id="addProdCat" placeholder="Pick or type new category..." required autocomplete="off" style="width: 100%;">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Selling Price (₦)</label>
                        <input type="number" name="unitPrice" id="addProdPrice" step="any" placeholder="e.g. 85000" required>
                    </div>

                    <div class="form-group">
                        <label>Initial Opening Stock (Units)</label>
                        <input type="number" name="initial_stock" id="addProdStock" min="0" placeholder="e.g. 20" value="0">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Brand Name (Optional)</label>
                        <input type="text" name="brand" placeholder="e.g. Dangote, Golden Penny">
                    </div>

                    <div class="form-group">
                        <label>Pack / Size Specification</label>
                        <input type="text" name="size" placeholder="e.g. 50kg, 25L, 40pk carton">
                    </div>
                </div>

                <div class="form-group">
                    <label>Assign Opening Stock to Branch</label>
                    <select name="warehouse_id" id="addProdWarehouse">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalAddProduct')">Cancel</button>
                    <button type="button" class="btn btn-success" style="flex: 1;" onclick="confirmAddProduct()">✓ Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Product -->
    <div id="modalEditProduct" class="modal-backdrop" style="display: none;">
        <div class="modal">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">✏️ Edit Product</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;" id="editSubtitle"></p>

            <form id="editProductForm" method="POST" action="">
                @csrf
                <input type="hidden" name="return_url" id="editReturnUrl" value="">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" id="editName" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Category</span>
                            <span style="font-size: 0.72rem; color: #93c5fd; font-weight: normal;">Select or type new</span>
                        </label>
                        <input list="categoryDatalist" name="category" id="editCat" required autocomplete="off" style="width: 100%;">
                    </div>

                    <div class="form-group">
                        <label>Selling Price (₦)</label>
                        <input type="number" name="unitPrice" id="editPrice" step="any" required>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" name="brand" id="editBrand">
                    </div>
                    <div class="form-group">
                        <label>Size / Pack</label>
                        <input type="text" name="size" id="editSize">
                    </div>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalEditProduct')">Cancel</button>
                    <button type="button" class="btn btn-primary" style="flex: 1;" onclick="confirmEditProduct()">✓ Update Product</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Modal: Bulk CSV Import -->
    <div id="modalImportCsv" class="modal-backdrop" style="display: none;">
        <div class="modal" style="max-width: 550px;">
            <h3 style="font-size: 1.3rem; font-weight: 800; margin-bottom: 0.5rem;">📥 Bulk Import Products from CSV</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                Upload a CSV spreadsheet to import hundreds of items at once.
            </p>

            <div style="background: rgba(15,23,42,0.6); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem; font-size: 0.8rem; color: #cbd5e1;">
                <strong style="color: #93c5fd;">Required CSV Column Headers:</strong><br>
                <code>name, code, category, unitPrice</code>
                <div style="margin-top: 0.4rem; font-size: 0.75rem; color: #94a3b8;">
                    <strong>Optional Headers:</strong> <code>brand, size, description, minStockLevel, initial_stock</code>
                </div>
                <div style="margin-top: 0.5rem;">
                    <a href="{{ route('products.template.csv') }}" style="color: #4ade80; text-decoration: underline; font-weight: 700;">
                        📥 Download Sample CSV Template
                    </a>
                </div>
            </div>

            <form id="importCsvForm" method="POST" action="{{ route('products.import.csv') }}" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label>Select CSV File (.csv)</label>
                    <input type="file" name="csv_file" id="csvFileInput" accept=".csv,text/csv" required style="padding: 0.5rem;">
                </div>

                <div class="form-group">
                    <label>Assign Initial Stock to Branch</label>
                    <select name="warehouse_id" id="importWhSelect">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalImportCsv')">Cancel</button>
                    <button type="button" class="btn btn-primary" style="flex: 1;" onclick="confirmImportCsv()">✓ Upload & Import Products</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Master Dynamic Category Datalist (Shared for Quick Add & Edit) -->
    <datalist id="categoryDatalist">
        @foreach($categories as $cat)
            <option value="{{ $cat }}"></option>
        @endforeach
        <option value="Groceries"></option>
        <option value="Beverages"></option>
        <option value="Household"></option>
        <option value="Hardware"></option>
        <option value="Electronics"></option>
        <option value="Frozen Foods"></option>
        <option value="Cosmetics & Personal Care"></option>
    </datalist>

@endsection

@push('scripts')
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openEditModal(id, name, cat, price, brand, size) {
    document.getElementById('editProductForm').action = '/products/' + id;
    const returnUrlInput = document.getElementById('editReturnUrl');
    if (returnUrlInput) {
        returnUrlInput.value = window.location.href;
    }
    document.getElementById('editSubtitle').textContent = 'Editing ' + name;
    document.getElementById('editName').value = name;
    document.getElementById('editCat').value = cat;
    document.getElementById('editPrice').value = price;
    document.getElementById('editBrand').value = brand || '';
    document.getElementById('editSize').value = size || '';
    openModal('modalEditProduct');
}

function confirmAddProduct() {
    const form = document.getElementById('addProductForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const name = document.getElementById('addProdName').value;
    const code = document.getElementById('addProdCode').value;
    const cat = document.getElementById('addProdCat').value;
    const price = parseFloat(document.getElementById('addProdPrice').value) || 0;
    const stock = parseInt(document.getElementById('addProdStock').value) || 0;
    const whSelect = document.getElementById('addProdWarehouse');
    const whName = whSelect.options[whSelect.selectedIndex].text;

    closeModal('modalAddProduct');

    showConfirmPopup({
        icon: '➕',
        title: 'Confirm Adding New Product',
        subtitle: 'Review new catalog entry before saving:',
        borderColor: '#22c55e',
        items: [
            { label: 'Product Name', value: name, color: '#f8fafc' },
            { label: 'SKU / Code', value: code, color: '#93c5fd' },
            { label: 'Category', value: cat, color: '#c084fc' },
            { label: 'Master Unit Price', value: '₦' + Math.round(price).toLocaleString('en-US'), color: '#4ade80', size: '1rem' },
            { label: 'Initial Stock on Hand', value: stock + ' units (' + whName + ')', color: '#fbbf24' }
        ],
        impact: {
            text: '🛍️ CATALOG REGISTRATION: Product will immediately appear on all POS terminals and inventory ledgers.',
            type: 'success'
        },
        confirmText: '✅ Yes, Save Product',
        confirmClass: 'btn-success',
        form: form
    });
}

function confirmEditProduct() {
    const form = document.getElementById('editProductForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const name = document.getElementById('editName').value;
    const cat = document.getElementById('editCat').value;
    const price = parseFloat(document.getElementById('editPrice').value) || 0;
    const brand = document.getElementById('editBrand').value;
    const size = document.getElementById('editSize').value;

    closeModal('modalEditProduct');

    showConfirmPopup({
        icon: '✏️',
        title: 'Confirm Product Update',
        subtitle: 'Review changes to master product specs:',
        borderColor: '#3b82f6',
        items: [
            { label: 'Product Name', value: name, color: '#f8fafc' },
            { label: 'Category', value: cat, color: '#c084fc' },
            { label: 'Master Selling Price', value: '₦' + Math.round(price).toLocaleString('en-US'), color: '#4ade80', size: '1rem' }
        ],
        impact: {
            text: '🔄 MASTER PRICE UPDATE: New price and details will synchronize to all POS terminals instantly.',
            type: 'info'
        },
        confirmText: '✏️ Yes, Update Product',
        confirmClass: 'btn-primary',
        onConfirm: function() {
            const formData = new FormData(form);
            formData.set('return_url', window.location.href);

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(data => {
                if (data.success && data.product) {
                    const p = data.product;
                    const row = document.getElementById('product-row-' + p.id);
                    if (row) {
                        const nameEl = row.querySelector('.product-name');
                        if (nameEl) nameEl.textContent = p.name;

                        const catEl = row.querySelector('.product-cat');
                        if (catEl) catEl.textContent = p.category;

                        const brandSizeEl = row.querySelector('.product-brand-size');
                        if (brandSizeEl) {
                            brandSizeEl.textContent = (p.brand || 'Standard') + (p.size ? ' (' + p.size + ')' : '');
                        }

                        const priceEl = row.querySelector('.product-price');
                        if (priceEl) priceEl.textContent = p.unitPriceFormatted;

                        const editBtn = row.querySelector('.btn-edit-product');
                        if (editBtn) {
                            const esc = (s) => (s || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
                            editBtn.setAttribute('onclick', `openEditModal('${p.id}', '${esc(p.name)}', '${esc(p.category)}', ${p.unitPrice}, '${esc(p.brand)}', '${esc(p.size)}')`);
                        }

                        // Smooth emerald highlight animation
                        row.style.transition = 'all 0.4s ease';
                        const origBg = row.style.backgroundColor;
                        row.style.backgroundColor = 'rgba(34, 197, 94, 0.25)';
                        setTimeout(() => {
                            row.style.backgroundColor = origBg;
                        }, 2000);
                    }

                    showCatalogToast(data.message || "✓ Product updated successfully!", 'success');
                } else {
                    showCatalogToast(data.message || "Error updating product.", 'error');
                }
            })
            .catch(err => {
                console.warn('AJAX update failed, falling back to standard submit:', err);
                form.submit();
            });
        }
    });
}

function showCatalogToast(msg, type = 'success') {
    let toast = document.getElementById('catalogToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'catalogToast';
        toast.style.cssText = 'position: fixed; bottom: 2rem; right: 2rem; z-index: 99999; padding: 0.85rem 1.4rem; border-radius: 12px; font-weight: 700; font-size: 0.95rem; box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 0.6rem; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); transform: translateY(100px); opacity: 0;';
        document.body.appendChild(toast);
    }
    if (type === 'success') {
        toast.style.background = '#15803d';
        toast.style.color = '#ffffff';
        toast.style.border = '1px solid #22c55e';
    } else {
        toast.style.background = '#b91c1c';
        toast.style.color = '#ffffff';
        toast.style.border = '1px solid #ef4444';
    }
    toast.innerHTML = (type === 'success' ? '<span>✓</span> ' : '<span>⚠️</span> ') + msg;
    toast.style.transform = 'translateY(0)';
    toast.style.opacity = '1';
    setTimeout(() => {
        toast.style.transform = 'translateY(100px)';
        toast.style.opacity = '0';
    }, 3500);
}

function confirmImportCsv() {
    const form = document.getElementById('importCsvForm');
    const fileInput = document.getElementById('csvFileInput');
    if (!fileInput.files || fileInput.files.length === 0) {
        fileInput.reportValidity();
        return;
    }

    const fileName = fileInput.files[0].name;
    const whSelect = document.getElementById('importWhSelect');
    const whName = whSelect.options[whSelect.selectedIndex].text;

    closeModal('modalImportCsv');

    showConfirmPopup({
        icon: '📥',
        title: 'Confirm Bulk CSV Import',
        subtitle: 'Review spreadsheet upload settings:',
        borderColor: '#3b82f6',
        items: [
            { label: 'CSV File', value: fileName, color: '#93c5fd' },
            { label: 'Assign Opening Stock To', value: whName, color: '#4ade80' }
        ],
        impact: {
            text: '📊 BULK IMPORT: System will parse SKUs, create/update products, and populate physical opening stock levels.',
            type: 'info'
        },
        confirmText: '📥 Yes, Upload & Import',
        confirmClass: 'btn-primary',
        form: form
    });
}

function filterProdTable() {
    const q = document.getElementById('prodSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#productsTable tbody tr');
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(q) ? '' : 'none';
    });
}
</script>
@endpush
