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
        // 1. Mandatory Tenant Boundary Verification
        if (config('saas.enabled')) {
            $userTenantId = $this->tenant_id ?? session('tenant_id');
            if ($warehouseId instanceof Warehouse) {
                $whTenantId = $warehouseId->tenant_id;
            } else {
                $wh = Warehouse::withoutGlobalScopes()->find($warehouseId);
                $whTenantId = $wh ? $wh->tenant_id : null;
            }

            if (!$whTenantId || !$userTenantId || $whTenantId !== $userTenantId) {
                return false; // Cross-tenant or missing tenant ID strictly blocked!
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
        $targetId = ($warehouseId instanceof Warehouse) ? $warehouseId->id : $warehouseId;
        return (int) $this->warehouse_id === (int) $targetId;
    }

    /**
     * Determine if user has permission to view a stock transfer.
     */
    public function canAccessTransfer(Transfer $transfer): bool
    {
        // 1. Mandatory Tenant Boundary Verification
        if (config('saas.enabled')) {
            $userTenantId = $this->tenant_id ?? session('tenant_id');
            if (!$transfer->tenant_id || !$userTenantId || $transfer->tenant_id !== $userTenantId) {
                return false; // Cross-tenant access strictly blocked!
            }
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

    /**
     * Determine if user has permission to dispatch a transfer out of a source warehouse.
     */
    public function canDispatchTransfer($sourceWarehouseId): bool
    {
        return $this->canAccessWarehouse($sourceWarehouseId);
    }

    /**
     * Determine if user has permission to receive and count an incoming transfer.
     */
    public function canReceiveTransfer(Transfer $transfer): bool
    {
        if (!$this->canAccessTransfer($transfer)) {
            return false;
        }

        if ($this->isExecutive() && empty($this->warehouse_id)) {
            return true;
        }

        return (int) $this->warehouse_id === (int) $transfer->destination_warehouse_id;
    }

    /**
     * Determine if user has permission to recall or cancel an in-transit transfer.
     */
    public function canRecallTransfer(Transfer $transfer): bool
    {
        if (!$this->canAccessTransfer($transfer)) {
            return false;
        }

        if ($this->isExecutive() && empty($this->warehouse_id)) {
            return true;
        }

        return (int) $this->warehouse_id === (int) $transfer->source_warehouse_id;
    }
}
