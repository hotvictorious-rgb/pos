/**
 * ARCHITECTURAL INVARIANT (Pass 16 Retirement):
 * VMarket POS is strictly 100% online and authoritative through Laravel PHP.
 * Client-side shadow ledgers, localStorage mutation, and background offline sync
 * are permanently retired to prevent financial desynchronization and double-spending.
 * All mutations MUST be submitted directly to authoritative Laravel endpoints.
 */

import { Product, Sale, Payment, User, InventoryLog, SalesReturn, Activity, SyncVerificationResult, TableVerification } from '../types';

const STORAGE_KEYS = {
  USERS: 'hysam_users',
  PRODUCTS: 'hysam_products',
  SALES: 'hysam_sales',
  PAYMENTS: 'hysam_payments',
  LOGS: 'hysam_logs',
  ACTIVITIES: 'hysam_activities',
  RETURNS: 'hysam_returns',
  SETTINGS: 'hysam_settings',
  AUTH: 'hysam_auth',
  CUSTOM_ROLES: 'hysam_custom_roles'
};

const getAuthHeaders = async () => {
  const headers: Record<string, string> = { 'Content-Type': 'application/json' };
  const csrfToken = typeof document !== 'undefined'
    ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    : null;
  if (csrfToken) {
    headers['X-CSRF-TOKEN'] = csrfToken;
  }
  return headers;
};

const INITIAL_USERS: User[] = [];

const INITIAL_PRODUCTS: Product[] = [
  {
    id: 'p1',
    code: 'GEN-001',
    name: 'Industrial Generator',
    size: '500kVA',
    brand: 'Cummins',
    description: 'High capacity power backup',
    category: 'Power',
    unitPrice: 250000,
    currentStock: 0,
    minStockLevel: 2,
    updatedAt: new Date().toISOString()
  },
  {
    id: 'p2',
    code: 'SOL-400',
    name: 'Solar Panel',
    size: '400W',
    brand: 'Jinko',
    description: 'Monocrystalline solar panel',
    category: 'Solar',
    unitPrice: 45000,
    currentStock: 0,
    minStockLevel: 10,
    updatedAt: new Date().toISOString()
  }
];

const INITIAL_SETTINGS: import('../types').AppSettings = {
  businessName: 'HYSAM VENTURES',
  businessAddress: '123 Main Street, Lagos, Nigeria',
  businessPhone: '+234 800 000 0000',
  businessEmail: 'info@hysam.com',
  currency: '₦',
  categories: ['Power', 'Solar', 'Battery', 'Inverter', 'Accessories', 'General'],
  reportFooter: 'Thank you for your business!',
  lowStockThreshold: 5,
  transactionEditLimitDays: 7,
  fontFamily: 'Inter'
};

const notifyDataUpdated = () => {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('hysam-data-updated'));
  }
};

let syncTimeout: any = null;
let isSyncing = false;
let hasPendingSyncRequest = false;

const triggerDebouncedSync = () => {
  if (syncTimeout) {
    clearTimeout(syncTimeout);
  }
  syncTimeout = setTimeout(() => {
    storage.sync();
    syncTimeout = null;
  }, 250);
};

let autoSyncInterval: any = null;

export const storage = {
  init: async () => {
    // Online-only architecture: Backend Laravel database is the sole authoritative source of truth.
    // Client-side shadow databases and background sync are permanently retired.
    if (!localStorage.getItem(STORAGE_KEYS.USERS)) {
      localStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify([]));
    }
    if (!localStorage.getItem(STORAGE_KEYS.PRODUCTS)) {
      localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify([]));
    }
    if (!localStorage.getItem(STORAGE_KEYS.SETTINGS)) {
      localStorage.setItem(STORAGE_KEYS.SETTINGS, JSON.stringify(INITIAL_SETTINGS));
    }

    // Online-only architecture: Backend Laravel database is the sole authoritative source of truth.
    // Periodic background client-side auto-merging is deactivated to preserve accounting and inventory integrity.
  },

  getVerificationResult: (): SyncVerificationResult | null => {
    try {
      const data = localStorage.getItem('hysam_sync_verification');
      return data ? JSON.parse(data) : null;
    } catch {
      return null;
    }
  },

  verifyDataCounts: (serverData: any): SyncVerificationResult => {
    const localSales = storage.getSales().length;
    const localReturns = storage.getReturns().length;
    const localProducts = storage.getProducts().length;
    const localPayments = storage.getPayments().length;
    const localUsers = storage.getUsers().length;
    const localLogs = storage.getLogs().length;
    const localRoles = storage.getCustomRoles().length;

    const serverSales = Array.isArray(serverData?.sales) ? serverData.sales.length : 0;
    const serverReturns = Array.isArray(serverData?.returns) ? serverData.returns.length : 0;
    const serverProducts = Array.isArray(serverData?.products) ? serverData.products.length : 0;
    const serverPayments = Array.isArray(serverData?.payments) ? serverData.payments.length : 0;
    const serverUsers = Array.isArray(serverData?.users) ? serverData.users.length : 0;
    const serverLogs = Array.isArray(serverData?.logs) ? serverData.logs.length : 0;
    const serverRoles = Array.isArray(serverData?.custom_roles) ? serverData.custom_roles.length : 0;

    const makeTable = (local: number, server: number): TableVerification => ({
      localCount: local,
      serverCount: server,
      match: local === server,
      diff: local - server
    });

    const salesTable = makeTable(localSales, serverSales);
    const returnsTable = makeTable(localReturns, serverReturns);
    const productsTable = makeTable(localProducts, serverProducts);
    const paymentsTable = makeTable(localPayments, serverPayments);
    const usersTable = makeTable(localUsers, serverUsers);
    const logsTable = makeTable(localLogs, serverLogs);
    const rolesTable = makeTable(localRoles, serverRoles);

    const matchAll = salesTable.match && returnsTable.match && productsTable.match && paymentsTable.match && usersTable.match && logsTable.match && rolesTable.match;

    const discrepantList: string[] = [];
    if (!salesTable.match) discrepantList.push(`Sales (App: ${localSales} vs DB: ${serverSales})`);
    if (!returnsTable.match) discrepantList.push(`Returns (App: ${localReturns} vs DB: ${serverReturns})`);
    if (!productsTable.match) discrepantList.push(`Inventory (App: ${localProducts} vs DB: ${serverProducts})`);
    if (!paymentsTable.match) discrepantList.push(`Payments (App: ${localPayments} vs DB: ${serverPayments})`);
    if (!usersTable.match) discrepantList.push(`Users (App: ${localUsers} vs DB: ${serverUsers})`);
    if (!logsTable.match) discrepantList.push(`Logs (App: ${localLogs} vs DB: ${serverLogs})`);
    if (!rolesTable.match) discrepantList.push(`Custom Roles (App: ${localRoles} vs DB: ${serverRoles})`);

    const result: SyncVerificationResult = {
      timestamp: new Date().toISOString(),
      status: matchAll ? 'verified' : 'discrepancy',
      hasDiscrepancy: !matchAll,
      message: matchAll
        ? 'All core database tables (Sales, Returns, Inventory, Payments, Users, Logs, Custom Roles) are verified in exact sync.'
        : `Record count discrepancy flagged: ${discrepantList.join('; ')}`,
      tables: {
        sales: salesTable,
        returns: returnsTable,
        products: productsTable,
        payments: paymentsTable,
        users: usersTable,
        logs: logsTable,
        roles: rolesTable
      }
    };

    try {
      localStorage.setItem('hysam_sync_verification', JSON.stringify(result));
    } catch (e) {
      console.warn('Failed to store sync verification result:', e);
    }

    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('hysam-sync-verification', { detail: result }));
    }

    return result;
  },

  isSyncPending: (): boolean => {
    return false;
  },

  setSyncPending: (pending: boolean) => {
    // No-op in online-only architecture
  },

  sync: async () => {
    // Online-only authoritative architecture:
    // The backend Laravel API and database are the single source of truth.
  },

  getData: <T>(key: string): T[] => {
    const data = localStorage.getItem(key);
    return data ? JSON.parse(data) : [];
  },

  saveData: <T>(key: string, data: T[]) => {
    localStorage.setItem(key, JSON.stringify(data));
    notifyDataUpdated();
  },

  forceSync: async () => {
    storage.setSyncPending(true);
    await storage.sync();
  },

  getProducts: () => storage.getData<Product>(STORAGE_KEYS.PRODUCTS),
  saveProducts: (_products: Product[]) => {
    throw new Error("DEPRECATED & DISABLED: Client-side shadow inventory mutation is disabled. Submit all stock changes via authoritative Laravel backend endpoints.");
  },

  getSales: () => storage.getData<Sale>(STORAGE_KEYS.SALES),
  saveSales: (_sales: Sale[]) => {
    throw new Error("DEPRECATED & DISABLED: Client-side shadow sales creation is disabled. Submit all POS transactions via authoritative /pos/checkout.");
  },

  getPayments: () => storage.getData<Payment>(STORAGE_KEYS.PAYMENTS),
  savePayments: (_payments: Payment[]) => {
    throw new Error("DEPRECATED & DISABLED: Client-side shadow payment creation is disabled. Record customer debt recoveries via authoritative /debts endpoints.");
  },

  getUsers: () => storage.getData<User>(STORAGE_KEYS.USERS),
  saveUsers: (_users: User[]) => {
    throw new Error("DEPRECATED & DISABLED: Client-side user management is disabled. Manage accounts through authoritative /users endpoints.");
  },

  getCustomRoles: (): import('../types').RoleConfig[] => {
    return storage.getData<import('../types').RoleConfig>(STORAGE_KEYS.CUSTOM_ROLES);
  },
  saveCustomRoles: (_roles: import('../types').RoleConfig[]) => {
    throw new Error("DEPRECATED & DISABLED: Client-side role modifications are disabled.");
  },
  
  getLogs: () => storage.getData<InventoryLog>(STORAGE_KEYS.LOGS),
  saveLogs: (_logs: InventoryLog[]) => {
    throw new Error("DEPRECATED & DISABLED: Client-side inventory log creation is disabled.");
  },

  getActivities: () => storage.getData<Activity>(STORAGE_KEYS.ACTIVITIES),
  saveActivities: (_activities: Activity[]) => {
    throw new Error("DEPRECATED & DISABLED: Client-side activity writes are disabled. All audit events are generated authoritatively on the backend.");
  },

  getReturns: () => storage.getData<SalesReturn>(STORAGE_KEYS.RETURNS),
  saveReturns: (_returns: SalesReturn[]) => {
    throw new Error("DEPRECATED & DISABLED: Client-side return processing is disabled. Submit returns via authoritative /pos/returns endpoint.");
  },

  getSettings: (): import('../types').AppSettings => {
    const data = localStorage.getItem(STORAGE_KEYS.SETTINGS);
    return data ? JSON.parse(data) : INITIAL_SETTINGS;
  },
  saveSettings: (_settings: import('../types').AppSettings) => {
    throw new Error("DEPRECATED & DISABLED: Client-side settings modifications are disabled. Update business settings via authoritative /settings endpoints.");
  },

  logActivity: (activity: Omit<Activity, 'id' | 'timestamp'>) => {
    const activities = storage.getActivities();
    const newActivity: Activity = {
      ...activity,
      id: Math.random().toString(36).substr(2, 9),
      timestamp: new Date().toISOString()
    };
    storage.saveActivities([newActivity, ...activities]);
  },

  calculateClosingStock: (productId: string, startDate?: Date, endDate?: Date) => {
    return storage.getStockBreakdown(productId, startDate, endDate).closingStock;
  },

  getStockBreakdown: (productId: string, startDate?: Date, endDate?: Date) => {
    const product = storage.getProducts().find(p => p.id === productId);
    if (!product) {
      return { openingStock: 0, stockIn: 0, stockReturn: 0, stockOut: 0, delivered: 0, closingStock: 0 };
    }

    const logs = storage.getLogs().filter(l => l.productId === productId);
    const allSales = storage.getSales();
    const productSales = allSales.filter(s => s.items.some(i => i.productId === productId));
    const allReturns = storage.getReturns().filter(r => r.productId === productId);

    const getStockReturnForRecord = (r: SalesReturn) => {
      const linkedSale = allSales.find(s => s.id === r.saleId);
      if (linkedSale) {
        if (linkedSale.deliveryStatus !== 'delivered') return 0;
        const item = linkedSale.items.find(i => i.productId === productId);
        return Math.min(r.quantity, item ? item.quantity : r.quantity);
      }
      return r.wasDelivered === true ? r.quantity : 0;
    };

    let openingStock = 0;
    let stockIn = 0;
    let stockOut = 0;
    let delivered = 0;
    let stockReturn = 0;

    if (startDate) {
      const logsBefore = logs.filter(l => new Date(l.timestamp) < startDate);
      const salesBefore = productSales.filter(s => new Date(s.deliveredAt || s.createdAt) < startDate && s.deliveryStatus === 'delivered');
      const returnsBefore = allReturns.filter(r => {
        if (new Date(r.createdAt || r.timestamp) >= startDate) return false;
        return getStockReturnForRecord(r) > 0;
      });

      const inBefore = logsBefore.filter(l => l.type === 'stock-in').reduce((acc, l) => acc + l.quantity, 0);
      const outBefore = logsBefore.filter(l => l.type === 'stock-out').reduce((acc, l) => acc + l.quantity, 0);
      const deliveredBefore = salesBefore.reduce((acc, s) => {
        const item = s.items.find(i => i.productId === productId);
        return acc + (item?.quantity || 0);
      }, 0);
      const stockReturnBefore = returnsBefore.reduce((acc, r) => acc + getStockReturnForRecord(r), 0);

      openingStock = (inBefore + stockReturnBefore) - (outBefore + deliveredBefore);

      const inDateRange = (dateStr?: string) => {
        if (!dateStr) return false;
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return false;
        if (d < startDate) return false;
        if (endDate && d > endDate) return false;
        return true;
      };

      const logsPeriod = logs.filter(l => inDateRange(l.timestamp));
      const salesPeriod = productSales.filter(s => s.deliveryStatus === 'delivered' && inDateRange(s.deliveredAt || s.createdAt));
      const returnsPeriod = allReturns.filter(r => inDateRange(r.createdAt || r.timestamp));

      stockIn = logsPeriod.filter(l => l.type === 'stock-in').reduce((acc, l) => acc + l.quantity, 0);
      stockOut = logsPeriod.filter(l => l.type === 'stock-out').reduce((acc, l) => acc + l.quantity, 0);
      delivered = salesPeriod.reduce((acc, s) => {
        const item = s.items.find(i => i.productId === productId);
        return acc + (item?.quantity || 0);
      }, 0);
      stockReturn = returnsPeriod.reduce((acc, r) => acc + getStockReturnForRecord(r), 0);
    } else {
      openingStock = 0;
      stockIn = logs.filter(l => l.type === 'stock-in').reduce((acc, l) => acc + l.quantity, 0);
      stockOut = logs.filter(l => l.type === 'stock-out').reduce((acc, l) => acc + l.quantity, 0);
      delivered = productSales
        .filter(s => s.deliveryStatus === 'delivered')
        .reduce((acc, s) => {
          const item = s.items.find(i => i.productId === productId);
          return acc + (item?.quantity || 0);
        }, 0);

      stockReturn = allReturns.reduce((acc, r) => acc + getStockReturnForRecord(r), 0);
    }

    const closingStock = (openingStock + stockIn + stockReturn) - (stockOut + delivered);

    return {
      openingStock,
      stockIn,
      stockReturn,
      stockOut,
      delivered,
      closingStock
    };
  },

  getAuth: (): User | null => {
    const auth = localStorage.getItem(STORAGE_KEYS.AUTH);
    return auth ? JSON.parse(auth) : null;
  },
  setAuth: (user: User | null) => {
    if (user) {
      localStorage.setItem(STORAGE_KEYS.AUTH, JSON.stringify(user));
    } else {
      localStorage.removeItem(STORAGE_KEYS.AUTH);
    }
  },

  clearAllData: async () => {
    localStorage.setItem(STORAGE_KEYS.SALES, JSON.stringify([]));
    localStorage.setItem(STORAGE_KEYS.PAYMENTS, JSON.stringify([]));
    localStorage.setItem(STORAGE_KEYS.LOGS, JSON.stringify([]));
    localStorage.setItem(STORAGE_KEYS.RETURNS, JSON.stringify([]));
    localStorage.setItem(STORAGE_KEYS.ACTIVITIES, JSON.stringify([]));

    const defaultProducts = INITIAL_PRODUCTS.map(p => ({ ...p, currentStock: 0 }));
    localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(defaultProducts));

    try {
      const headers = await getAuthHeaders();
      await fetch('/api/reset', { method: 'POST', headers });
    } catch (e) {
      console.warn('Reset server endpoint failed or unavailable:', e);
    }

    notifyDataUpdated();
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('hysam-data-updated'));
      window.dispatchEvent(new CustomEvent('hysam-sync-end'));
    }
  }
};
