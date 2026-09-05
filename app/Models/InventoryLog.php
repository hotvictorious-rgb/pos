<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class InventoryLog extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'productId',
        'warehouse_id',
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
        'warehouse_id' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'productId', 'id');
    }
}
