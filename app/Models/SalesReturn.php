<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class SalesReturn extends Model
{
    use BelongsToTenant;

    protected $table = 'sales_returns';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'saleId',
        'customerName',
        'code',
        'productId',
        'productName',
        'quantity',
        'refundAmount',
        'reason',
        'createdAt',
        'userId',
        'userName',
        'timestamp',
        'productCode',
        'wasDelivered',
        'deliveryStatus',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'refundAmount' => 'double',
        'wasDelivered' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($return) {
            if (empty($return->tenant_id) && !empty($return->saleId)) {
                $parentSale = Sale::withoutGlobalScopes()->find($return->saleId);
                if ($parentSale && !empty($parentSale->tenant_id)) {
                    $return->tenant_id = $parentSale->tenant_id;
                }
            }
        });
    }
}
