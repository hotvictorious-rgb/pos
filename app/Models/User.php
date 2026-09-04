<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use App\Traits\BelongsToTenant;
use App\Models\Scopes\TenantScope;

class User extends Authenticatable
{
    use HasFactory, Notifiable, BelongsToTenant;

    /**
     * Look up user strictly for pre-authentication identity verification.
     * Controlled global scope bypass before session tenant context is established.
     */
    public static function findForAuthentication(string $email): ?self
    {
        $normalized = strtolower(trim($email));
        return static::withoutGlobalScope(TenantScope::class)
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->first();
    }

    /**
     * Look up user strictly for session re-hydration before session tenant context is verified.
     */
    public static function findForAuthenticationById($id): ?self
    {
        if (empty($id)) {
            return null;
        }
        return static::withoutGlobalScope(TenantScope::class)->find($id);
    }

    protected static function booted(): void
    {
        static::saving(function ($user) {
            $superAdminEmail = strtolower(trim(config('saas.super_admin_email') ?: env('SUPER_ADMIN_EMAIL', 'superadmin@hysam.com')));
            // Prevent non-root identities from acquiring super_admin role
            if ($user->role === 'super_admin') {
                if ($user->tenant_id !== 'default-tenant' || strtolower(trim($user->email)) !== $superAdminEmail) {
                    $user->role = 'admin'; // Normalize privilege escalation attempt
                }
            }
        });
    }

    // ─────────────────────────────────────────────────────────
    // FOUR PRIMARY AUTHORITY CATEGORIES
    // ─────────────────────────────────────────────────────────
    public const AUTH_PLATFORM_ADMIN    = 'platform_admin';
    public const AUTH_PLATFORM_EMPLOYEE = 'platform_employee';
    public const AUTH_TENANT_ADMIN      = 'tenant_admin';
    public const AUTH_TENANT_EMPLOYEE   = 'tenant_employee';

    /**
     * Determine if user has platform-level SaaS super-administrator authority.
     */
    public function isPlatformAdmin(): bool
    {
        if ($this->tenant_id !== 'default-tenant') {
            return false;
        }

        $superAdminEmail = strtolower(trim(config('saas.super_admin_email') ?: env('SUPER_ADMIN_EMAIL', 'superadmin@hysam.com')));

        return in_array($this->role, ['admin', 'super_admin'], true) 
            && (strtolower(trim($this->email)) === $superAdminEmail);
    }

    /**
     * Determine if user has platform-level SaaS super-administrator authority (alias for isPlatformAdmin).
     */
    public function isSuperAdmin(): bool
    {
        return $this->isPlatformAdmin();
    }

    /**
     * Determine if user is a platform-level employee under default-tenant.
     */
    public function isPlatformEmployee(): bool
    {
        return ($this->tenant_id === 'default-tenant') && !$this->isPlatformAdmin();
    }

    /**
     * Determine if user is any platform user (admin or employee).
     */
    public function isPlatformUser(): bool
    {
        return $this->tenant_id === 'default-tenant';
    }

    /**
     * Determine if user is a business owner / tenant administrator.
     */
    public function isTenantAdmin(): bool
    {
        if (config('saas.enabled')) {
            return !empty($this->tenant_id) && $this->tenant_id !== 'default-tenant' && $this->role === 'admin';
        }
        return $this->role === 'admin';
    }

    /**
     * Determine if user is a business employee/cashier/storekeeper under a tenant.
     */
    public function isTenantEmployee(): bool
    {
        if (config('saas.enabled')) {
            return !empty($this->tenant_id) && $this->tenant_id !== 'default-tenant' && $this->role !== 'admin';
        }
        return $this->role !== 'admin';
    }

    /**
     * Determine if user is any tenant user (admin or employee).
     */
    public function isTenantUser(): bool
    {
        if (config('saas.enabled')) {
            return !empty($this->tenant_id) && $this->tenant_id !== 'default-tenant';
        }
        return true;
    }

    /**
     * Get the canonical four-level authority category for this user.
     */
    public function getAuthorityCategory(): string
    {
        if ($this->isPlatformAdmin()) {
            return self::AUTH_PLATFORM_ADMIN;
        }
        if ($this->isPlatformEmployee()) {
            return self::AUTH_PLATFORM_EMPLOYEE;
        }
        if ($this->isTenantAdmin()) {
            return self::AUTH_TENANT_ADMIN;
        }
        return self::AUTH_TENANT_EMPLOYEE;
    }

    /**
     * Backward-compatible helper for platform employee.
     */
    public function isSuperAdminEmployee(): bool
    {
        return $this->isPlatformEmployee();
    }

    /**
     * Check if user possesses a specific capability.
     */
    public function hasCapability(string $capability): bool
    {
        return \App\Services\Auth\CapabilityService::userHasCapability($this, $capability);
    }

    /**
     * Check if user possesses at least one capability from a list.
     * Note: Strictly checks actual user capabilities; no universal superadmin bypass.
     */
    public function hasAnyCapability(array $capabilities): bool
    {
        $userCaps = \App\Services\Auth\CapabilityService::getCapabilitiesForUser($this);
        foreach ($capabilities as $cap) {
            if (in_array($cap, $userCaps, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all assigned capabilities for this user.
     */
    public function getCapabilities(): array
    {
        return \App\Services\Auth\CapabilityService::getCapabilitiesForUser($this);
    }

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
     * Strictly applies to tenant administration within a tenant.
     */
    public function isExecutive(): bool
    {
        return $this->isTenantAdmin();
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
