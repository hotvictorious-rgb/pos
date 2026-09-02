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
     * Determine if user has executive tenant-wide oversight (Auditor Admin / Executive Owner).
     */
    public function isExecutive(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    /**
     * Check if user is restricted to a specific branch location.
     */
    public function isBranchScoped(): bool
    {
        return !empty($this->warehouse_id) && !$this->isExecutive();
    }

    /**
     * Determine if user has permission to access or operate on a warehouse branch.
     */
    public function canAccessWarehouse($warehouseId): bool
    {
        // 1. Verify tenant alignment if warehouse model or object is passed
        if ($warehouseId instanceof Warehouse) {
            if ($warehouseId->tenant_id && $this->tenant_id && $warehouseId->tenant_id !== $this->tenant_id) {
                return false; // Cross-tenant access strictly blocked!
            }
            $warehouseId = $warehouseId->id;
        } else {
            $wh = Warehouse::withoutGlobalScopes()->find($warehouseId);
            if ($wh && $wh->tenant_id && $this->tenant_id && $wh->tenant_id !== $this->tenant_id) {
                return false; // Cross-tenant access strictly blocked!
            }
        }

        // 2. Executive HQ owners have business-wide branch access
        if ($this->isExecutive() && empty($this->warehouse_id)) {
            return true;
        }

        // 3. Unassigned non-executives CANNOT access any branch!
        if (empty($this->warehouse_id)) {
            return false;
        }

        // 4. Branch staff can ONLY access their assigned branch
        return (int) $this->warehouse_id === (int) $warehouseId;
    }

    /**
     * Determine if user has permission to view or manipulate a stock transfer.
     */
    public function canAccessTransfer(Transfer $transfer): bool
    {
        // 1. Cross-tenant check: Must belong to same tenant!
        if ($transfer->tenant_id && $this->tenant_id && $transfer->tenant_id !== $this->tenant_id) {
            return false;
        }

        // 2. Executive HQ owners have business-wide transfer access
        if ($this->isExecutive() && empty($this->warehouse_id)) {
            return true;
        }

        // 3. Unassigned non-executives CANNOT access any transfer!
        if (empty($this->warehouse_id)) {
            return false;
        }

        // 4. Branch staff can ONLY access transfers where their branch is Source or Destination
        return (int) $this->warehouse_id === (int) $transfer->source_warehouse_id
            || (int) $this->warehouse_id === (int) $transfer->destination_warehouse_id;
    }
}
