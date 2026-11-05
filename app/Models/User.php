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
     * Supports Email, Username (Name), or WhatsApp Phone Number.
     * Controlled global scope bypass before session tenant context is established.
     */
    public static function findForAuthentication(string $identifier): ?self
    {
        $clean = trim($identifier);
        $normalized = strtolower($clean);

        // 1. Direct match on email (case-insensitive)
        $user = static::withoutGlobalScope(TenantScope::class)
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->first();

        if ($user) {
            return $user;
        }

        // 2. Direct match on name / username (case-insensitive)
        $user = static::withoutGlobalScope(TenantScope::class)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();

        if ($user) {
            return $user;
        }

        // 3. Match on phone number if digits provided
        $digits = preg_replace('/[^0-9]/', '', $clean);
        if (strlen($digits) >= 8) {
            $lastDigits = strlen($digits) >= 10 ? substr($digits, -10) : $digits;
            $user = static::withoutGlobalScope(TenantScope::class)
                ->where(function ($q) use ($digits, $lastDigits) {
                    $q->where('whatsapp_phone', 'like', "%{$digits}%")
                      ->orWhere('whatsapp_phone', 'like', "%{$lastDigits}%")
                      ->orWhere('email', 'like', "%{$digits}%");
                })
                ->first();

            if ($user) {
                return $user;
            }
        }

        // 4. Match by username prefix or email prefix (e.g. typing "admin" matches "admin@...")
        if (!str_contains($clean, '@')) {
            $user = static::withoutGlobalScope(TenantScope::class)
                ->where(function ($q) use ($normalized) {
                    $q->whereRaw('LOWER(email) LIKE ?', ["{$normalized}@%"])
                      ->orWhereRaw('LOWER(name) LIKE ?', ["{$normalized}%"]);
                })
                ->first();

            if ($user) {
                return $user;
            }
        }

        return null;
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

            // In multi-tenant strict mode, prevent privilege escalation to super_admin
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

        return ($this->role === 'super_admin') 
            || (in_array($this->role, ['admin', 'super_admin'], true) && (strtolower(trim($this->email)) === $superAdminEmail));
    }

    /**
     * Determine if user has platform-level SaaS super-administrator authority (alias for isPlatformAdmin).
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin' || $this->isPlatformAdmin();
    }

    /**
     * Determine if user is a platform-level employee under default-tenant.
     */
    public function isPlatformEmployee(): bool
    {
        if (in_array($this->role, ['platform_employee', 'super_admin_employee'], true)) {
            return true;
        }
        if ($this->isPlatformAdmin()) {
            return false;
        }
        // Only accounts without a standard retail store role in default-tenant are treated as platform employees
        if ($this->tenant_id === 'default-tenant' && !in_array($this->role, ['cashier', 'storekeeper', 'manager', 'branch_manager', 'staff', 'admin', 'owner', 'store_owner', 'viewer', 'accountant', 'sales_officer', 'executive_readonly'], true)) {
            return true;
        }
        return false;
    }

    /**
     * Determine if user is any platform user (admin or employee).
     */
    public function isPlatformUser(): bool
    {
        return $this->tenant_id === 'default-tenant';
    }

    /**
     * Determine if user has administrator role.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin', 'owner', 'store_owner'], true);
    }

    /**
     * Determine if user is a business owner / tenant administrator.
     */
    public function isTenantAdmin(): bool
    {
        if (config('saas.enabled')) {
            return !empty($this->tenant_id) && $this->tenant_id !== 'default-tenant' && in_array($this->role, ['admin', 'owner', 'store_owner'], true);
        }
        return in_array($this->role, ['admin', 'owner', 'store_owner'], true);
    }

    /**
     * Determine if user is a business employee/cashier/storekeeper under a tenant.
     */
    public function isTenantEmployee(): bool
    {
        return !$this->isTenantAdmin() && !$this->isPlatformAdmin();
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
        'whatsapp_phone',
        'whatsapp_verified_at',
        'whatsapp_verify_token',
        'whatsapp_verify_expires_at',
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
            'whatsapp_verified_at' => 'datetime',
            'whatsapp_verify_expires_at' => 'datetime',
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
        return $this->isAdmin() || in_array($this->role, ['executive_readonly', 'viewer'], true);
    }

    /**
     * Check if user is restricted to a specific branch location.
     */
    public function isBranchScoped(): bool
    {
        if ($this->isAdmin() || in_array($this->role, ['manager', 'branch_manager', 'viewer', 'auditor', 'executive_readonly'], true)) {
            return false;
        }
        return !empty($this->warehouse_id);
    }

    /**
     * Determine if user has permission to access or operate on a warehouse branch.
     */
    public function canAccessWarehouse($warehouseId): bool
    {
        // 0. Platform Super Admins can access any warehouse branch for platform maintenance/support
        if ($this->isSuperAdmin() || $this->isPlatformAdmin()) {
            return true;
        }

        // 1. Mandatory Tenant Boundary Verification
        if (config('saas.enabled')) {
            $userTenantId = $this->tenant_id ?? session('tenant_id');
            if ($warehouseId instanceof Warehouse) {
                $whTenantId = $warehouseId->tenant_id;
            } else {
                $wh = Warehouse::withoutGlobalScopes()->find($warehouseId);
                $whTenantId = $wh ? $wh->tenant_id : null;
            }

            $effectiveWhTenantId = $whTenantId ?: 'default-tenant';
            $effectiveUserTenantId = $userTenantId ?: 'default-tenant';

            if ($effectiveWhTenantId !== $effectiveUserTenantId) {
                return false; // Cross-tenant strictly blocked!
            }
        }

        // 2. Administrators, managers, and business-wide executives have full multi-branch access
        if ($this->isAdmin() || in_array($this->role, ['manager', 'branch_manager', 'viewer', 'auditor', 'executive_readonly'], true) || ($this->isExecutive() && empty($this->warehouse_id))) {
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
