<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\BelongsToTenant;

class Customer extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_code',
        'name',
        'phone',
        'email',
        'address',
        'total_debt',
        'credit_limit',
    ];

    protected $casts = [
        'total_debt' => 'double',
        'credit_limit' => 'double',
    ];

    protected static function booted()
    {
        static::created(function ($customer) {
            if (empty($customer->customer_code)) {
                $customer->customer_code = 'CUST-' . str_pad($customer->id, 4, '0', STR_PAD_LEFT);
                $customer->saveQuietly();
            }
        });
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(CustomerLedger::class)->orderBy('created_at', 'desc');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customerId', 'id');
    }
}
