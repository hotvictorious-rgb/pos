<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\BelongsToTenant;

class StockAdjustment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'product_id',
        'product_name',
        'product_code',
        'type', // DAMAGE, EXPIRED, LOST, FOUND
        'quantity',
        'reason',
        'recorded_by',
        'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
