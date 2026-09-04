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

    protected static function boot()
    {
        parent::boot();

        static::saving(function (StockLevel $stock) {
            // Core Invariant: Both physical_stock and allocated_stock must be non-negative.
            // allocated_stock > physical_stock IS FULLY VALID (representing reservation shortfall).
            if ($stock->physical_stock < 0) {
                throw new \InvalidArgumentException(
                    "Physical stock cannot be negative (attempted: {$stock->physical_stock}) for product #{$stock->product_id} at warehouse #{$stock->warehouse_id}."
                );
            }
            if ($stock->allocated_stock < 0) {
                throw new \InvalidArgumentException(
                    "Allocated stock cannot be negative (attempted: {$stock->allocated_stock}) for product #{$stock->product_id} at warehouse #{$stock->warehouse_id}."
                );
            }
        });
    }

    /** Authoritative capacity available for immediate walk-in / supplied sale */
    public function getAvailableStockAttribute(): int
    {
        return (int) $this->physical_stock;
    }

    /** Reservation shortfall when outstanding reservations exceed physical units on ground */
    public function getReservationShortfallAttribute(): int
    {
        return max(0, (int) $this->allocated_stock - (int) $this->physical_stock);
    }

    /** Net position = physical_stock - allocated_stock (can be negative if shortfall exists) */
    public function getNetPositionAttribute(): int
    {
        return (int) $this->physical_stock - (int) $this->allocated_stock;
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
