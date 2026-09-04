<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class StockReservation extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'sale_id',
        'sale_item_id',
        'product_id',
        'warehouse_id',
        'reserved_qty',
        'fulfilled_qty',
        'cancelled_qty',
        'status', // ACTIVE, PARTIALLY_FULFILLED, FULFILLED, CANCELLED
        'customer_id',
        'customer_name',
        'notes',
    ];

    protected $casts = [
        'reserved_qty' => 'integer',
        'fulfilled_qty' => 'integer',
        'cancelled_qty' => 'integer',
        'warehouse_id' => 'integer',
        'product_id' => 'integer',
    ];

    /** Outstanding units still owed to the customer under this reservation */
    public function getOutstandingQtyAttribute(): int
    {
        return max(0, $this->reserved_qty - ($this->fulfilled_qty + $this->cancelled_qty));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
