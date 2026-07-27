<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'customerName',
        'totalAmount',
        'paidAmount',
        'cashAmount',
        'posAmount',
        'note',
        'status',
        'deliveryStatus',
        'deliveredAt',
        'deliveredBy',
        'returnReason',
        'userId',
        'userName',
        'createdAt',
    ];

    protected $casts = [
        'totalAmount' => 'double',
        'paidAmount' => 'double',
        'cashAmount' => 'double',
        'posAmount' => 'double',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'saleId', 'id');
    }
}
