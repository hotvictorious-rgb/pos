<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'productId',
        'type',
        'quantity',
        'userId',
        'notes',
        'timestamp',
        'productCode',
        'productName',
        'description',
        'userName',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];
}
