<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use App\Traits\BelongsToTenant;

class User extends Authenticatable
{
    use HasFactory, Notifiable, BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'warehouse_id',
        'disabled',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'disabled' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Check if user is restricted to a specific branch location.
     */
    public function isBranchScoped(): bool
    {
        return !empty($this->warehouse_id);
    }

    /**
     * Determine if user has permission to access or operate on a warehouse branch.
     */
    public function canAccessWarehouse($warehouseId): bool
    {
        if (empty($this->warehouse_id)) {
            return true; // HQ Executive / Super Admin
        }
        return (int) $this->warehouse_id === (int) $warehouseId;
    }

    /**
     * Determine if user has permission to view or manipulate a stock transfer.
     */
    public function canAccessTransfer(Transfer $transfer): bool
    {
        if (empty($this->warehouse_id)) {
            return true; // HQ Executive / Super Admin
        }
        return (int) $this->warehouse_id === (int) $transfer->source_warehouse_id
            || (int) $this->warehouse_id === (int) $transfer->destination_warehouse_id;
    }
}
