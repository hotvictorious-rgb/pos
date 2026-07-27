/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

export type UserRole = string;

export interface ModuleCRUDPermission {
  view: boolean;
  create: boolean;
  edit: boolean;
  delete: boolean;
}

export type ModulePermissionsMap = Record<string, ModuleCRUDPermission>;

export interface UserPermissions {
  create?: boolean;
  edit?: boolean;
  delete?: boolean;
  stockIn?: boolean;
  stockOut?: boolean;
  allowedModules?: string[];
  modulePermissions?: ModulePermissionsMap;
}

export interface RoleConfig {
  id: string;
  label: string;
  badgeBg?: string;
  badgeText?: string;
  badgeBorder?: string;
  description: string;
  isSystem?: boolean;
  modulePermissions: ModulePermissionsMap;
  allowedModules?: string[];
}

export interface User {
  id: string;
  name: string;
  email: string;
  password?: string;
  role: UserRole;
  disabled?: boolean;
  permissions?: UserPermissions;
  createdAt: string;
}

export interface Product {
  id: string;
  code: string;
  name: string;
  size: string;
  brand: string;
  description: string;
  category: string;
  unitPrice: number;
  currentStock: number;
  openingStock?: number;
  minStockLevel: number;
  archived?: boolean;
  userId?: string;
  updatedAt: string;
}

export type SaleStatus = 'completed' | 'installment' | 'returned';
export type DeliveryStatus = 'none' | 'pending' | 'delivered';

export interface SaleItem {
  productId: string;
  productName: string;
  quantity: number;
  unitPrice: number;
  totalPrice: number;
  code?: string;
  productCode?: string;
}

export interface Sale {
  id: string;
  customerName: string;
  items: SaleItem[];
  totalAmount: number;
  paidAmount: number;
  cashAmount: number;
  posAmount: number;
  note: string;
  status: SaleStatus;
  deliveryStatus: DeliveryStatus;
  deliveredAt?: string;
  deliveredBy?: string;
  returnReason?: string;
  userId: string;
  createdAt: string;
  userName?: string;
}

export interface Payment {
  id: string;
  saleId: string;
  amount: number;
  method: string;
  timestamp: string;
  recordedBy: string;
  createdAt?: string;
}

export interface SalesReturn {
  id: string;
  saleId: string;
  customerName: string;
  code: string;
  productId: string;
  productName: string;
  quantity: number;
  refundAmount: number;
  reason: string;
  createdAt: string;
  userId: string;
  userName?: string;
  timestamp?: string;
  productCode?: string;
  wasDelivered?: boolean;
  deliveryStatus?: DeliveryStatus;
}

export interface InventoryLog {
  id: string;
  productId: string;
  type: 'stock-in' | 'stock-out';
  quantity: number;
  userId: string;
  notes: string;
  timestamp: string;
  productCode?: string;
  productName?: string;
  description?: string;
  userName?: string;
}

export type ActivityType = 'stock-update' | 'sale' | 'payment' | 'return' | 'product-created' | 'delivery';

export interface Activity {
  id: string;
  type: ActivityType;
  description: string;
  userId: string;
  userName: string;
  timestamp: string;
  metadata?: any;
}

export interface AppSettings {
  businessName: string;
  businessAddress: string;
  businessPhone: string;
  businessEmail: string;
  currency: string;
  categories: string[];
  reportFooter: string;
  lowStockThreshold: number;
  transactionEditLimitDays: number;
  fontFamily: 'Inter' | 'Plus Jakarta Sans' | 'JetBrains Mono' | 'Space Grotesk' | 'Playfair Display';
}

export interface TableVerification {
  localCount: number;
  serverCount: number;
  match: boolean;
  diff: number;
}

export interface SyncVerificationResult {
  timestamp: string;
  status: 'verified' | 'discrepancy' | 'error';
  message: string;
  hasDiscrepancy: boolean;
  tables: {
    sales: TableVerification;
    returns: TableVerification;
    products: TableVerification;
    payments: TableVerification;
    users: TableVerification;
    logs: TableVerification;
  };
}
