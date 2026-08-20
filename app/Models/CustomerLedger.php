<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLedger extends Model
{
    protected $fillable = [
        'customer_id',
        'sale_id',
        'type', // INVOICE, PAYMENT, RETURN, ADJUSTMENT
        'amount',
        'balance_after',
        'payment_method',
        'reference_no',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'double',
        'balance_after' => 'double',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
