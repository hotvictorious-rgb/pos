<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    protected $fillable = [
        'transfer_no',
        'source_warehouse_id',
        'destination_warehouse_id',
        'status', // DISPATCHED, RECEIVED, DISCREPANCY, CANCELLED
        'carrier_name',
        'dispatched_by',
        'received_by',
        'dispatched_at',
        'received_at',
        'discrepancy_notes',
        'notes',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }
}
