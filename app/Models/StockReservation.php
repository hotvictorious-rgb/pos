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
        'returned_fulfilled_qty',
        'cancelled_qty',
        'status', // ACTIVE, PARTIALLY_FULFILLED, FULFILLED, CANCELLED, RETURNED
        'customer_id',
        'customer_name',
        'notes',
    ];

    protected $casts = [
        'reserved_qty' => 'integer',
        'fulfilled_qty' => 'integer',
        'returned_fulfilled_qty' => 'integer',
        'cancelled_qty' => 'integer',
        'warehouse_id' => 'integer',
        'product_id' => 'string',
    ];

    /** Quantity of fulfilled units currently in the physical possession of the customer */
    public function getHeldByCustomerQtyAttribute(): int
    {
        return max(0, (int)$this->fulfilled_qty - (int)$this->returned_fulfilled_qty);
    }

    /** Outstanding units still owed to the customer under this reservation */
    public function getOutstandingQtyAttribute(): int
    {
        return max(0, (int)$this->reserved_qty - ((int)$this->fulfilled_qty + (int)$this->cancelled_qty));
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
