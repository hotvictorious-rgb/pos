<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'code',
        'name',
        'size',
        'brand',
        'description',
        'category',
        'unitPrice',
        'currentStock',
        'minStockLevel',
        'archived',
        'userId',
        'updatedAt',
    ];

    protected $casts = [
        'unitPrice' => 'double',
        'currentStock' => 'integer',
        'minStockLevel' => 'integer',
        'archived' => 'boolean',
    ];
}
