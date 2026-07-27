<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'saleId',
        'productId',
        'productName',
        'quantity',
        'unitPrice',
        'totalPrice',
        'code',
        'productCode',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unitPrice' => 'double',
        'totalPrice' => 'double',
    ];
}
