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

        // SaaS Platform Management (Super Admin only)
        'platform.tenants',
        'platform.settings',
        'platform.impersonate',
        'platform.backup',
        'platform.restore',
        'platform.reset',
    ];

    /**
     * Role-to-Capabilities Matrix.
     * Unknown roles return an empty array (fail-closed).
     */
    protected static array $roleCapabilities = [
        'super_admin' => [
            'pos.view', 'pos.checkout',
            'customer.read', 'customer.write',
            'debt.view', 'debt.pay',
            'returns.view', 'returns.process',
            'products.view', 'products.write',
            'stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.recall', 'stock.adjust',
            'reports.view', 'reports.export',
            'transactions.view', 'transactions.export',
            'users.manage',
            'settings.manage',
            'platform.tenants', 'platform.settings', 'platform.impersonate', 'platform.backup', 'platform.restore', 'platform.reset',
        ],
        'admin' => [
            'pos.view', 'pos.checkout',
            'customer.read', 'customer.write',
            'debt.view', 'debt.pay',
            'returns.view', 'returns.process',
            'products.view', 'products.write',
            'stock.view', 'stock.in', 'stock.transfer', 'stock.receive', 'stock.recall', 'stock.adjust',
            'reports.view', 'reports.export',
            'transactions.view', 'transactions.export',
            'users.manage',
            'settings.manage',
            'platform.backup', 'platform.restore',
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

        return array_values(array_unique($capabilities));
    }

    /**
     * Check if a user possesses a specific capability.
     */
    public static function userHasCapability(?User $user, string $capability): bool
    {
        if (!$user) {
            return false;
        }

        // Root super admin possesses all capabilities unconditionally
        if ($user->isSuperAdmin()) {
            return true;
        }

        $userCapabilities = self::getCapabilitiesForUser($user);
        return in_array($capability, $userCapabilities, true);
    }
}
