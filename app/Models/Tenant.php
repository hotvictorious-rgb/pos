<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $table = 'tenants';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'owner_email',
        'owner_phone',
        'plan',
        'status',
        'trial_ends_at',
        'max_branches',
        'max_users',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'max_branches' => 'integer',
        'max_users' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'tenant_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'tenant_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'tenant_id');
    }

    public function isActive(): bool
    {
        if ($this->status === 'active') {
            return true;
        }

        if ($this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture()) {
            return true;
        }

        return false;
    }
}
