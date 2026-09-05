<?php

namespace App\Services\Auth;

use App\Models\User;

class CapabilityService
{
    /**
     * Canonical list of system capabilities.
     */
    public const CAPABILITIES = [
        // Point of Sale & Retail Checkout
        'pos.view',
        'pos.checkout',

        // Customer Directory
        'customer.read',
        'customer.write',

        // Debts & Part-Payment Recovery
        'debt.view',
        'debt.pay',
        'debt.correct',

        // Returns & Refunds
        'returns.view',
        'returns.process',

        // Products & Catalog
        'products.view',
        'products.write',

        // Stock Logistics & Warehousing
        'stock.view',
        'stock.in',
        'stock.transfer',
        'stock.receive',
        'stock.recall',
        'stock.adjust',

        // Reports & Intelligence
        'reports.view',
        'reports.export',

        // Transactions & Audit Trail
        'transactions.view',
        'transactions.export',

        // Workers & Permissions
        'users.manage',

        // Business Settings & Branches
        'settings.manage',

        // Tenant Backup & Disaster Recovery
        'tenant.backup',

        // SaaS Platform Management (Platform Admin & Platform Employee)
        'platform.tenants',
        'platform.settings',
        'platform.limits',
        'platform.health',
        'platform.backup',
        'platform.restore',
        'platform.reset',
    ];

    /**
     * Check if a capability is scoped to the SaaS platform.
     */
    public static function isPlatformCapability(string $capability): bool
    {
        return str_starts_with($capability, 'platform.');
    }

    /**
     * Role-to-Capabilities Matrix.
     * Unknown roles return an empty array (fail-closed).
     */
    protected static array $roleCapabilities = [
        'platform_admin' => [
            'platform.tenants',
            'platform.settings',
            'platform.limits',
            'platform.health',
            'platform.backup',
            'platform.restore',
            'platform.reset',
        ],
        'super_admin' => [
            'platform.tenants',
            'platform.settings',
            'platform.limits',
            'platform.health',
            'platform.backup',
            'platform.restore',
            'platform.reset',
        ],
        'platform_employee' => [
            // Platform employees perform ONLY explicitly assigned platform work
        ],
        'admin' => [
            'pos.view', 'pos.checkout',
            'customer.read', 'customer.write',
            'debt.view', 'debt.pay', 'debt.correct',
            'returns.view', 'returns.process',
            'products.view', 'products.write',
            'stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.recall', 'stock.adjust',
            'reports.view', 'reports.export',
            'transactions.view', 'transactions.export',
            'users.manage',
            'settings.manage',
            'tenant.backup',
        ],
        'branch_manager' => [
            'pos.view', 'pos.checkout',
            'customer.read', 'customer.write',
            'debt.view', 'debt.pay',
            'returns.view', 'returns.process',
            'products.view', 'products.write',
            'stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.recall', 'stock.adjust',
            'reports.view', 'reports.export',
            'transactions.view', 'transactions.export',
        ],
        'storekeeper' => [
            'products.view', 'products.write',
            'stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.recall', 'stock.adjust',
            'transactions.view', 'transactions.export',
        ],
        'cashier' => [
            'pos.view', 'pos.checkout',
            'customer.read', 'customer.write',
            'debt.view', 'debt.pay',
            'returns.view', 'returns.process',
            'transactions.view',
        ],
        'sales_officer' => [
            'pos.view', 'pos.checkout',
            'customer.read', 'customer.write',
            'debt.view', 'debt.pay',
            'returns.view', 'returns.process',
            'reports.view',
            'transactions.view',
        ],
        'viewer' => [
            'pos.view',
            'customer.read',
            'debt.view',
            'returns.view',
            'products.view',
            'stock.view',
            'reports.view',
            'transactions.view',
        ],
        'executive_readonly' => [
            'pos.view',
            'customer.read',
            'debt.view',
            'returns.view',
            'products.view',
            'stock.view',
            'reports.view',
            'transactions.view',
        ],
    ];

    /**
     * Get all capabilities assigned to a user based on role and explicit overrides.
     */
    public static function getCapabilitiesForUser(?User $user): array
    {
        if (!$user || $user->disabled) {
            return [];
        }

        $role = $user->role ?? '';

        // If user is designated as Super Admin, verify root super admin invariant
        if ($role === 'super_admin' && !$user->isSuperAdmin()) {
            $role = 'admin'; // Normalized fallback if not root super admin identity
        }

        // Map platform admin role
        if ($user->isPlatformAdmin()) {
            $role = 'platform_admin';
        } elseif ($user->isPlatformEmployee() && empty(self::$roleCapabilities[$role])) {
            $role = 'platform_employee';
        }

        $capabilities = self::$roleCapabilities[$role] ?? [];

        // Apply explicit permission overrides from user record if present
        if (is_array($user->permissions)) {
            // Map legacy key names to capability sets if needed
            $legacyMap = [
                'pos' => ['pos.view', 'pos.checkout'],
                'products' => ['products.view', 'products.write'],
                'stockIn' => ['stock.in'],
                'transfer' => ['stock.transfer', 'stock.receive', 'stock.recall'],
                'adjustments' => ['stock.adjust'],
                'reports' => ['reports.view', 'reports.export'],
                'debts' => ['debt.view', 'debt.pay'],
                'returns' => ['returns.view', 'returns.process'],
                'users' => ['users.manage'],
            ];

            foreach ($user->permissions as $key => $allowed) {
                // Support indexed string list: ['platform.health', 'platform.tenants']
                if (is_int($key) && is_string($allowed) && in_array($allowed, self::CAPABILITIES, true)) {
                    $capabilities[] = $allowed;
                    continue;
                }

                if (isset($legacyMap[$key])) {
                    if ($allowed === false) {
                        $capabilities = array_diff($capabilities, $legacyMap[$key]);
                    } elseif ($allowed === true) {
                        $capabilities = array_merge($capabilities, $legacyMap[$key]);
                    }
                } elseif (in_array($key, self::CAPABILITIES, true)) {
                    if ($allowed === false) {
                        $capabilities = array_diff($capabilities, [$key]);
                    } elseif ($allowed === true) {
                        $capabilities[] = $key;
                    }
                }
            }
        }

        // Hard boundary filtering:
        // Platform accounts can ONLY hold platform.* capabilities
        // Tenant accounts can NEVER hold platform.* capabilities
        if ($user->isPlatformUser()) {
            $capabilities = array_filter($capabilities, fn($c) => self::isPlatformCapability($c));
        } elseif ($user->isTenantUser()) {
            $capabilities = array_filter($capabilities, fn($c) => !self::isPlatformCapability($c));
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * Check if a user possesses a specific capability.
     */
    public static function userHasCapability(?User $user, string $capability): bool
    {
        if (!$user || $user->disabled) {
            return false;
        }

        // Hard Boundary: Platform users can never possess tenant business capabilities
        if ($user->isPlatformUser() && !self::isPlatformCapability($capability)) {
            return false;
        }

        // Hard Boundary: Tenant users can never possess platform capabilities
        if ($user->isTenantUser() && self::isPlatformCapability($capability)) {
            return false;
        }

        $userCapabilities = self::getCapabilitiesForUser($user);
        return in_array($capability, $userCapabilities, true);
    }
}
