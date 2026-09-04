<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\BelongsToTenant;

class CustomerLedger extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
