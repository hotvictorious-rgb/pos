<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\BelongsToTenant;

class StockLevel extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'physical_stock',
        'allocated_stock',
        'min_stock_alert',
    ];

    protected $casts = [
        'physical_stock' => 'integer',
        'allocated_stock' => 'integer',
        'min_stock_alert' => 'integer',
    ];

    /** All physical stock on ground is directly sellable */
    public function getAvailableStockAttribute(): int
    {
        return $this->physical_stock;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
