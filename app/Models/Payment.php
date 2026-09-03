<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Payment extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'saleId',
        'amount',
        'method',
        'timestamp',
        'recordedBy',
        'createdAt',
    ];

    protected $casts = [
        'amount' => 'double',
    ];

    protected static function booted(): void
    {
        static::creating(function ($payment) {
            if (empty($payment->tenant_id) && !empty($payment->saleId)) {
                $parentSale = Sale::withoutGlobalScopes()->find($payment->saleId);
                if ($parentSale && !empty($parentSale->tenant_id)) {
                    $payment->tenant_id = $parentSale->tenant_id;
                }
            }
        });
    }
}
