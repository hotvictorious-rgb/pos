<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToTenant;

class Product extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
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

    protected static function booted(): void
    {
        static::creating(function ($product) {
            if (empty($product->updatedAt)) {
                $product->updatedAt = now()->toIso8601String();
            }
        });
    }

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class, 'product_id', 'id');
    }
}
