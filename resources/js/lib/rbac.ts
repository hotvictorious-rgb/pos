/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { User, UserRole, RoleConfig, ModuleCRUDPermission, ModulePermissionsMap } from '../types';
import { storage } from './storage';

export const ALL_SYSTEM_MODULES = [
  { id: 'dashboard', label: 'Dashboard', desc: 'Analytics & Overview' },
  { id: 'inventory', label: 'Inventory Catalog', desc: 'Products & Stock Management' },
  { id: 'sales', label: 'POS & Sales Returns', desc: 'Process Sales & Returns' },
  { id: 'payments', label: 'Payments & Financial Ledger', desc: 'Full payment history, part payments, refunds & debtors' },
  { id: 'deliveries', label: 'Order Deliveries', desc: 'Dispatch & Delivery Tracking' },
  { id: 'activities', label: 'General Activity Log', desc: 'System Audit Logs' },
  { id: 'users', label: 'User Management', desc: 'Staff Accounts & Permissions' },
  { id: 'settings', label: 'App Settings', desc: 'Business & System Settings' },
];

export function createEmptyModulePermissions(): ModulePermissionsMap {
  const map: ModulePermissionsMap = {};
  ALL_SYSTEM_MODULES.forEach(m => {
    map[m.id] = { view: false, create: false, edit: false, delete: false };
  });
  return map;
}

export function createFullModulePermissions(): ModulePermissionsMap {
  const map: ModulePermissionsMap = {};
  ALL_SYSTEM_MODULES.forEach(m => {
    map[m.id] = { view: true, create: true, edit: true, delete: true };
  });
  return map;
}

const DEFAULT_SALES_PERMISSIONS: ModulePermissionsMap = {
  ...createEmptyModulePermissions(),
  dashboard: { view: true, create: false, edit: false, delete: false },
  sales: { view: true, create: true, edit: true, delete: false },
  payments: { view: true, create: true, edit: true, delete: false },
  deliveries: { view: true, create: true, edit: true, delete: false },
  activities: { view: true, create: false, edit: false, delete: false },
};

const DEFAULT_INVENTORY_PERMISSIONS: ModulePermissionsMap = {
  ...createEmptyModulePermissions(),
  dashboard: { view: true, create: false, edit: false, delete: false },
  inventory: { view: true, create: true, edit: true, delete: false },
  deliveries: { view: true, create: true, edit: true, delete: false },
  activities: { view: true, create: false, edit: false, delete: false },
};

const DEFAULT_STAFF_PERMISSIONS: ModulePermissionsMap = {
  ...createEmptyModulePermissions(),
  dashboard: { view: true, create: false, edit: false, delete: false },
  inventory: { view: true, create: true, edit: false, delete: false },
  sales: { view: true, create: true, edit: false, delete: false },
  payments: { view: true, create: true, edit: false, delete: false },
  deliveries: { view: true, create: true, edit: false, delete: false },
  activities: { view: true, create: false, edit: false, delete: false },
};

export const SYSTEM_ROLES: Record<string, RoleConfig> = {
  admin: {
    id: 'admin',
    label: 'Administrator',
    badgeBg: 'bg-purple-100',
    badgeText: 'text-purple-700',
    badgeBorder: 'border-purple-200',
    description: 'Full administrative control across all modules, settings, and user access.',
    isSystem: true,
    modulePermissions: createFullModulePermissions(),
    allowedModules: ALL_SYSTEM_MODULES.map(m => m.id)
  },
  sales: {
    id: 'sales',
    label: 'Sales Officer',
    badgeBg: 'bg-indigo-100',
    badgeText: 'text-indigo-700',
    badgeBorder: 'border-indigo-200',
    description: 'Authorized for point of sale (POS), sales returns, payment installments, and delivery tracking.',
    isSystem: true,
    modulePermissions: DEFAULT_SALES_PERMISSIONS,
    allowedModules: ['dashboard', 'sales', 'payments', 'deliveries', 'activities']
  },
  inventory: {
    id: 'inventory',
    label: 'Inventory Manager',
    badgeBg: 'bg-amber-100',
    badgeText: 'text-amber-800',
    badgeBorder: 'border-amber-200',
    description: 'Authorized for stock-in, stock-out, catalog management, low-stock monitoring, and deliveries.',
    isSystem: true,
    modulePermissions: DEFAULT_INVENTORY_PERMISSIONS,
    allowedModules: ['dashboard', 'inventory', 'deliveries', 'activities']
  },
  staff: {
    id: 'staff',
    label: 'Sales & Inventory Staff',
    badgeBg: 'bg-emerald-100',
    badgeText: 'text-emerald-800',
    badgeBorder: 'border-emerald-200',
    description: 'Standard staff access covering both general sales and inventory viewing.',
    isSystem: true,
    modulePermissions: DEFAULT_STAFF_PERMISSIONS,
    allowedModules: ['dashboard', 'inventory', 'sales', 'payments', 'deliveries', 'activities']
  }
};

export const ROLES = SYSTEM_ROLES;

/**
 * Get all available roles (System built-in roles + Admin custom created roles)
 */
export function getAllRoles(): RoleConfig[] {
  let customRoles: RoleConfig[] = [];
  try {
    customRoles = storage.getCustomRoles() || [];
  } catch (err) {
    console.error('Failed to load custom roles:', err);
  }

  const systemList = Object.values(SYSTEM_ROLES);
  
  // Merge custom roles overriding system roles if customized
  const combinedMap = new Map<string, RoleConfig>();
  systemList.forEach(r => combinedMap.set(r.id, r));
  customRoles.forEach(r => combinedMap.set(r.id, r));

  return Array.from(combinedMap.values());
}

/**
 * Get role configuration for a specific role ID
 */
export function getRoleConfig(roleId: string): RoleConfig {
  const all = getAllRoles();
  const match = all.find(r => r.id === roleId);
  if (match) return match;

  return SYSTEM_ROLES.staff;
}

/**
 * Check if a role or user has permission to perform an action on a module
 */
export function hasModulePermission(
  roleOrUser: UserRole | User, 
  moduleId: string, 
  action: 'view' | 'create' | 'edit' | 'delete'
): boolean {
  if (typeof roleOrUser === 'object' && roleOrUser !== null) {
    const user = roleOrUser as User;
    if (user.role === 'admin') return true;

    // Check specific user permissions map
    if (user.permissions?.modulePermissions?.[moduleId]) {
      const perm = user.permissions.modulePermissions[moduleId];
      if (perm && typeof perm[action] === 'boolean') {
        return perm[action];
      }
    }

    // Check user.permissions allowedModules for view action
    if (action === 'view' && user.permissions?.allowedModules && Array.isArray(user.permissions.allowedModules)) {
      if (user.permissions.allowedModules.includes(moduleId)) return true;
    }

    // Check action flags fallback
    if (action === 'create' && user.permissions?.create !== undefined) return user.permissions.create;
    if (action === 'edit' && user.permissions?.edit !== undefined) return user.permissions.edit;
    if (action === 'delete' && user.permissions?.delete !== undefined) return user.permissions.delete;

    // Fallback to role definition
    const roleConfig = getRoleConfig(user.role);
    if (roleConfig.modulePermissions?.[moduleId]) {
      return roleConfig.modulePermissions[moduleId][action];
    }
    if (action === 'view') {
      return roleConfig.allowedModules?.includes(moduleId) ?? false;
    }

    return false;
  } else {
    const roleId = roleOrUser as string;
    if (roleId === 'admin') return true;

    const roleConfig = getRoleConfig(roleId);
    if (roleConfig.modulePermissions?.[moduleId]) {
      return roleConfig.modulePermissions[moduleId][action];
    }
    if (action === 'view') {
      return roleConfig.allowedModules?.includes(moduleId) ?? false;
    }

    return false;
  }
}

/**
 * Check if a user can view/access a top-level module
 */
export function canAccessModule(roleOrUser: UserRole | User, moduleId: string): boolean {
  if (['stock-in', 'stock-out', 'movement-logs'].includes(moduleId)) {
    return hasModulePermission(roleOrUser, 'inventory', 'view');
  }
  return hasModulePermission(roleOrUser, moduleId, 'view');
}

/**
 * Helper permission checks for specific module actions
 */
export function canCreateInModule(user: User, moduleId: string): boolean {
  return hasModulePermission(user, moduleId, 'create');
}

export function canEditInModule(user: User, moduleId: string): boolean {
  return hasModulePermission(user, moduleId, 'edit');
}

export function canDeleteInModule(user: User, moduleId: string): boolean {
  return hasModulePermission(user, moduleId, 'delete');
}

/**
 * Legacy helper wrappers for backward compatibility
 */
export function canPerformStockIn(user: User): boolean {
  if (user.role === 'admin' || user.role === 'inventory') return true;
  if (user.permissions?.stockIn !== undefined) return user.permissions.stockIn;
  return canCreateInModule(user, 'inventory');
}

export function canPerformStockOut(user: User): boolean {
  if (user.role === 'admin' || user.role === 'inventory') return true;
  if (user.permissions?.stockOut !== undefined) return user.permissions.stockOut;
  return canCreateInModule(user, 'inventory');
}

export function canCreateItem(user: User): boolean {
  if (user.role === 'admin' || user.role === 'inventory' || user.role === 'sales') return true;
  return user.permissions?.create ?? true;
}

export function canEditItem(user: User): boolean {
  if (user.role === 'admin' || user.role === 'inventory') return true;
  return user.permissions?.edit ?? false;
}

export function canDeleteItem(user: User): boolean {
  if (user.role === 'admin') return true;
  return user.permissions?.delete ?? false;
}
