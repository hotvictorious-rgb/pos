<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    protected $fillable = [
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

    /** Available to sell = Physical on Ground - Allocated (Unsupplied) */
    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->physical_stock - $this->allocated_stock);
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
