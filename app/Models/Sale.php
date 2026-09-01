<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class Sale extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'customerName',
        'totalAmount',
        'paidAmount',
        'cashAmount',
        'posAmount',
        'note',
        'status',
        'sale_type',
        'customerId',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customerId', 'id');
    }
}
