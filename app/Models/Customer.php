<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
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

    public function ledgers(): HasMany
    {
        return $this->hasMany(CustomerLedger::class)->orderBy('created_at', 'desc');
    }
}
