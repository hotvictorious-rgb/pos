<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    protected $table = 'sales_returns';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
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
}
