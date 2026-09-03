<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class TransferItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'transfer_id',
        'product_id',
        'product_name',
        'product_code',
        'dispatched_qty',
        'received_qty',
        'discrepancy_qty',
    ];

    protected $casts = [
        'dispatched_qty' => 'integer',
        'received_qty' => 'integer',
        'discrepancy_qty' => 'integer',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
