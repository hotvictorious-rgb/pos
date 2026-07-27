<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
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
}
