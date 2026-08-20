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
            <a href="{{ route('products.export.csv') }}" class="btn btn-secondary" style="font-size: 0.85rem;">
                📥 Export CSV
            </a>
            <a href="{{ route('products.export.json') }}" class="btn btn-secondary" style="font-size: 0.85rem; color: #93c5fd;">
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
                   class="badge {{ request('stock_status') === 'LOW_STOCK' ? 'badge-warning' : 'badge-secondary' }}" style="padding: 0.4rem 0.85rem; text-decoration: none;">
                   🟡 Low Stock (≤ 5 units)
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
                    <label style="font-size: 0.75rem;">Search Name / SKU</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="e.g. Rice, Oil, PEAK">
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
                        <th>Product Name & SKU</th>
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
                    <tr>
                        <td>
                            <strong style="font-size: 1rem; color: #f9fafb;">{{ $p->name }}</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">SKU: {{ $p->code }}</div>
                        </td>
                        <td><span class="badge badge-info">{{ $p->category }}</span></td>
                        <td>{{ $p->brand ?? 'Standard' }} {{ $p->size ? '('.$p->size.')' : '' }}</td>
                        <td style="font-size: 1.15rem; font-weight: 800; color: #4ade80;">
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
                                <button class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;"
                                        onclick="openEditModal('{{ $p->id }}', '{{ addslashes($p->name) }}', '{{ $p->category }}', {{ $p->unitPrice }}, '{{ $p->brand }}', '{{ $p->size }}')">
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

            <form method="POST" action="{{ route('products.store') }}">
                @csrf
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" placeholder="e.g. Bag of Rice (50kg), Indomie Super Pack" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Item Code / SKU</label>
                        <input type="text" name="code" placeholder="e.g. RICE-50KG" required>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" required>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                            <option value="Groceries">Groceries</option>
                            <option value="Beverages">Beverages</option>
                            <option value="Household">Household</option>
                            <option value="Hardware">Hardware</option>
                        </select>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Selling Price (₦)</label>
                        <input type="number" name="unitPrice" step="any" placeholder="e.g. 85000" required>
                    </div>

                    <div class="form-group">
                        <label>Initial Opening Stock (Units)</label>
                        <input type="number" name="initial_stock" min="0" placeholder="e.g. 20" value="0">
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
                    <select name="warehouse_id">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalAddProduct')">Cancel</button>
                    <button type="submit" class="btn btn-success" style="flex: 1;">✓ Save Product</button>
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
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" id="editName" required>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" id="editCat" required>
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
                    <button type="submit" class="btn btn-primary" style="flex: 1;">✓ Update Product</button>
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
                <code>name, code, category, brand, size, unitPrice, minStockLevel, initial_stock</code>
                <div style="margin-top: 0.5rem;">
                    <a href="{{ route('products.template.csv') }}" style="color: #4ade80; text-decoration: underline; font-weight: 700;">
                        📥 Download Sample CSV Template
                    </a>
                </div>
            </div>

            <form method="POST" action="{{ route('products.import.csv') }}" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label>Select CSV File (.csv)</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv" required style="padding: 0.5rem;">
                </div>

                <div class="form-group">
                    <label>Assign Initial Stock to Branch</label>
                    <select name="warehouse_id">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('modalImportCsv')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">✓ Upload & Import Products</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openEditModal(id, name, cat, price, brand, size) {
    document.getElementById('editProductForm').action = '/products/' + id;
    document.getElementById('editSubtitle').textContent = 'Editing ' + name;
    document.getElementById('editName').value = name;
    document.getElementById('editCat').value = cat;
    document.getElementById('editPrice').value = price;
    document.getElementById('editBrand').value = brand || '';
    document.getElementById('editSize').value = size || '';
    openModal('modalEditProduct');
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
