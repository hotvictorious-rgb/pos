@props([
    'name' => 'product_id',
    'id' => 'product_id',
    'products' => [],
    'placeholder' => '🔍 Search product by name, brand, or SKU code...',
    'required' => true,
    'selectedId' => null,
])

@php
    $selectedProduct = null;
    if (!empty($selectedId)) {
        $selectedProduct = collect($products)->first(fn($item) => (string)$item->id === (string)$selectedId);
    }
@endphp

<div class="searchable-product-picker" id="spc_wrapper_{{ $id }}" style="position: relative; width: 100%;">
    {{-- Native select input preserved for standard form submission & programmatic compatibility --}}
    <select name="{{ $name }}" id="{{ $id }}" class="spc-raw-select" style="position: absolute; opacity: 0; pointer-events: none; width: 1px; height: 1px; bottom: 0; left: 0;" {{ $required ? 'required' : '' }}>
        <option value="">-- Select Product --</option>
        @foreach($products as $p)
            <option value="{{ $p->id }}"
                    data-id="{{ $p->id }}"
                    data-name="{{ $p->name }}"
                    data-code="{{ $p->code }}"
                    data-category="{{ $p->category ?? '' }}"
                    data-price="{{ $p->unitPrice ?? 0 }}"
                    {{ (string)($selectedId ?? '') === (string)$p->id ? 'selected' : '' }}>
                {{ $p->name }} ({{ $p->code }})
            </option>
        @endforeach
    </select>

    {{-- Selected Product Card (shown when an item is selected) --}}
    <div class="spc-selected-view" id="spc_selected_{{ $id }}" style="{{ $selectedProduct ? 'display: flex;' : 'display: none;' }} background: rgba(16, 185, 129, 0.08); border: 1.5px solid rgba(16, 185, 129, 0.35); border-radius: 10px; padding: 0.65rem 0.85rem; align-items: center; justify-content: space-between; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0;">
            <div style="width: 28px; height: 28px; border-radius: 7px; background: rgba(16, 185, 129, 0.2); color: #34d399; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 800; flex-shrink: 0;">✓</div>
            <div style="min-width: 0;">
                <div class="spc-selected-name" style="font-weight: 700; color: #f8fafc; font-size: 0.88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    {{ $selectedProduct ? $selectedProduct->name : '' }}
                </div>
                <div style="display: flex; gap: 0.4rem; align-items: center; margin-top: 0.15rem; font-size: 0.75rem;">
                    <span class="spc-selected-code" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); padding: 0.05rem 0.4rem; border-radius: 4px; font-weight: 700; font-family: monospace;">
                        {{ $selectedProduct ? $selectedProduct->code : '' }}
                    </span>
                    <span class="spc-selected-category" style="color: #94a3b8;">
                        {{ $selectedProduct && !empty($selectedProduct->category) ? '• ' . $selectedProduct->category : '' }}
                    </span>
                </div>
            </div>
        </div>
        <button type="button" class="spc-btn-change" onclick="window.spcReset('{{ $id }}')" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; border-radius: 6px; padding: 0.35rem 0.65rem; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0;" title="Select a different product">
            ✕ Change
        </button>
    </div>

    {{-- Interactive Live Search Input Box --}}
    <div class="spc-search-container" id="spc_search_{{ $id }}" style="{{ $selectedProduct ? 'display: none;' : 'position: relative;' }}">
        <input type="text"
               class="spc-input"
               id="spc_input_{{ $id }}"
               placeholder="{{ $placeholder }}"
               autocomplete="off"
               style="width: 100%; padding: 0.65rem 2.2rem 0.65rem 2.3rem; background: #0b0f19; border: 1.5px solid #334155; border-radius: 10px; color: #f8fafc; font-size: 0.88rem; outline: none; transition: all 0.2s;"
               onfocus="window.spcOpen('{{ $id }}')"
               oninput="window.spcFilter('{{ $id }}', this.value)"
               onkeydown="window.spcKeydown('{{ $id }}', event)">
        <span style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 0.95rem; pointer-events: none;">🔍</span>
        <span id="spc_clear_btn_{{ $id }}" style="position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 1rem; cursor: pointer; display: none;" onclick="window.spcClearInput('{{ $id }}')" title="Clear text">✕</span>
    </div>

    {{-- Popup Dropdown Menu --}}
    <div class="spc-dropdown" id="spc_dropdown_{{ $id }}" style="display: none; position: absolute; z-index: 99999; left: 0; right: 0; top: 100%; margin-top: 6px; max-height: 250px; overflow-y: auto; background: #111827; border: 1.5px solid #374151; border-radius: 10px; box-shadow: 0 15px 35px -5px rgba(0,0,0,0.7); padding: 0.4rem 0;">
        <div class="spc-items-list" id="spc_list_{{ $id }}">
            {{-- Dynamic list populated via JS --}}
        </div>
        <div class="spc-no-match" id="spc_empty_{{ $id }}" style="display: none; padding: 1.25rem; text-align: center; color: #9ca3af; font-size: 0.82rem;">
            No products found matching "<span id="spc_term_{{ $id }}" style="color: #f87171; font-weight: 700;"></span>"
        </div>
    </div>
</div>
