<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class SaleItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
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

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'saleId', 'id');
    }

    protected static function booted(): void
    {
        static::creating(function ($item) {
            if (empty($item->tenant_id) && !empty($item->saleId)) {
                $parentSale = Sale::withoutGlobalScopes()->find($item->saleId);
                if ($parentSale && !empty($parentSale->tenant_id)) {
                    $item->tenant_id = $parentSale->tenant_id;
                }
            }
        });
    }
}
