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

  try {
    const dbConfig = localStorage.getItem('hysam_external_db_config');
    if (dbConfig) {
      headers['X-Database-Config'] = btoa(unescape(encodeURIComponent(dbConfig)));
    }
  } catch (err) {
    console.error('Failed to encode DB Config:', err);
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
    // Default auto-sync to enabled
    if (localStorage.getItem('hysam_external_db_autosync') === null) {
      localStorage.setItem('hysam_external_db_autosync', 'true');
    }

    // Initial local setup defaults
    if (!localStorage.getItem(STORAGE_KEYS.USERS)) {
      localStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify([]));
    }
    if (!localStorage.getItem(STORAGE_KEYS.PRODUCTS)) {
      localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify([]));
    }
    if (!localStorage.getItem(STORAGE_KEYS.SETTINGS)) {
      localStorage.setItem(STORAGE_KEYS.SETTINGS, JSON.stringify(INITIAL_SETTINGS));
    }

    // Start background auto-sync timer (syncs every 15 seconds automatically)
    if (!autoSyncInterval && typeof window !== 'undefined') {
      autoSyncInterval = setInterval(() => {
        storage.sync();
      }, 15000);
    }

    // Set up auto-sync on browser back online
    if (typeof window !== 'undefined') {
      window.addEventListener('online', () => {
        console.log('Browser back online! Running real-time synchronization...');
        storage.sync();
      });
    }
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

    const serverSales = Array.isArray(serverData?.sales) ? serverData.sales.length : 0;
    const serverReturns = Array.isArray(serverData?.returns) ? serverData.returns.length : 0;
    const serverProducts = Array.isArray(serverData?.products) ? serverData.products.length : 0;
    const serverPayments = Array.isArray(serverData?.payments) ? serverData.payments.length : 0;
    const serverUsers = Array.isArray(serverData?.users) ? serverData.users.length : 0;
    const serverLogs = Array.isArray(serverData?.logs) ? serverData.logs.length : 0;

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

    const matchAll = salesTable.match && returnsTable.match && productsTable.match && paymentsTable.match && usersTable.match && logsTable.match;

    const discrepantList: string[] = [];
    if (!salesTable.match) discrepantList.push(`Sales (App: ${localSales} vs DB: ${serverSales})`);
    if (!returnsTable.match) discrepantList.push(`Returns (App: ${localReturns} vs DB: ${serverReturns})`);
    if (!productsTable.match) discrepantList.push(`Inventory (App: ${localProducts} vs DB: ${serverProducts})`);
    if (!paymentsTable.match) discrepantList.push(`Payments (App: ${localPayments} vs DB: ${serverPayments})`);
    if (!usersTable.match) discrepantList.push(`Users (App: ${localUsers} vs DB: ${serverUsers})`);
    if (!logsTable.match) discrepantList.push(`Logs (App: ${localLogs} vs DB: ${serverLogs})`);

    const result: SyncVerificationResult = {
      timestamp: new Date().toISOString(),
      status: matchAll ? 'verified' : 'discrepancy',
      hasDiscrepancy: !matchAll,
      message: matchAll
        ? 'All core database tables (Sales, Returns, Inventory, Payments, Users, Logs) are verified in exact sync.'
        : `Record count discrepancy flagged: ${discrepantList.join('; ')}`,
      tables: {
        sales: salesTable,
        returns: returnsTable,
        products: productsTable,
        payments: paymentsTable,
        users: usersTable,
        logs: logsTable
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
    return localStorage.getItem('hysam_sync_pending') === 'true';
  },

  setSyncPending: (pending: boolean) => {
    localStorage.setItem('hysam_sync_pending', String(pending));
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('hysam-sync-status', {
        detail: { pending, online: navigator.onLine }
      }));
    }
  },

  sync: async () => {
    if (syncTimeout) {
      clearTimeout(syncTimeout);
      syncTimeout = null;
    }

    if (isSyncing) {
      hasPendingSyncRequest = true;
      return;
    }
    isSyncing = true;
    hasPendingSyncRequest = false;

    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('hysam-sync-start'));
    }

    try {
      const headers = await getAuthHeaders();
      
      // 1. Build current local data payload to push
      const pushData = {
        users: storage.getUsers(),
        products: storage.getProducts(),
        sales: storage.getSales(),
        payments: storage.getPayments(),
        logs: storage.getLogs(),
        activities: storage.getActivities(),
        returns: storage.getReturns(),
        settings: storage.getSettings()
      };

      // 2. Push local changes first so the server has the latest client updates
      const pushRes = await fetch('/api/data', {
        method: 'POST',
        headers,
        body: JSON.stringify(pushData)
      });

      if (!pushRes.ok) {
        throw new Error(`Push sync failed with status code ${pushRes.status}`);
      }

      // 3. Pull fresh data from server (which now includes our updates)
      const pullRes = await fetch('/api/data', { headers });
      if (pullRes.ok) {
        const serverData = await pullRes.json();
        
        // Helper to safely merge local and server lists by item ID
        const mergeLists = <T extends { id: string }>(localList: T[], serverList: T[] | undefined): T[] => {
          if (!serverList || serverList.length === 0) return localList;
          const mergedMap = new Map<string, T>();
          // Server wins on conflicts for established records, but local keeps its new ones
          for (const item of serverList) {
            mergedMap.set(item.id, item);
          }
          for (const item of localList) {
            if (!mergedMap.has(item.id)) {
              mergedMap.set(item.id, item);
            }
          }
          return Array.from(mergedMap.values());
        };

        const localUsers = storage.getUsers();
        const localProducts = storage.getProducts();
        const localSales = storage.getSales();
        const localPayments = storage.getPayments();
        const localLogs = storage.getLogs();
        const localActivities = storage.getActivities();
        const localReturns = storage.getReturns();

        const mergedUsers = mergeLists(localUsers, serverData.users);
        const mergedProducts = mergeLists(localProducts, serverData.products);
        const mergedSales = mergeLists(localSales, serverData.sales);
        const mergedPayments = mergeLists(localPayments, serverData.payments);
        const mergedLogs = mergeLists(localLogs, serverData.logs);
        const mergedActivities = mergeLists(localActivities, serverData.activities);
        const mergedReturns = mergeLists(localReturns, serverData.returns);

        // Update local storage with merged results
        localStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify(mergedUsers));
        localStorage.setItem(STORAGE_KEYS.PRODUCTS, JSON.stringify(mergedProducts));
        localStorage.setItem(STORAGE_KEYS.SALES, JSON.stringify(mergedSales));
        localStorage.setItem(STORAGE_KEYS.PAYMENTS, JSON.stringify(mergedPayments));
        localStorage.setItem(STORAGE_KEYS.LOGS, JSON.stringify(mergedLogs));
        localStorage.setItem(STORAGE_KEYS.ACTIVITIES, JSON.stringify(mergedActivities));
        localStorage.setItem(STORAGE_KEYS.RETURNS, JSON.stringify(mergedReturns));
        
        if (serverData.settings) {
          localStorage.setItem(STORAGE_KEYS.SETTINGS, JSON.stringify(serverData.settings));
        }
        notifyDataUpdated();

        // Perform verification
        storage.verifyDataCounts(serverData);
      }

      storage.setSyncPending(false);
    } catch (e) {
      console.warn('Failed to sync with server, data remains local:', e);
      storage.setSyncPending(true);
    } finally {
      isSyncing = false;
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('hysam-sync-end'));
      }
      if (hasPendingSyncRequest) {
        setTimeout(() => storage.sync(), 50);
      }
    }
  },

  getData: <T>(key: string): T[] => {
    const data = localStorage.getItem(key);
    return data ? JSON.parse(data) : [];
  },

  saveData: <T>(key: string, data: T[]) => {
    localStorage.setItem(key, JSON.stringify(data));
    storage.setSyncPending(true);
    notifyDataUpdated();
    triggerDebouncedSync(); // Trigger background sync with debounce
  },

  forceSync: async () => {
    storage.setSyncPending(true);
    await storage.sync();
  },

  getProducts: () => storage.getData<Product>(STORAGE_KEYS.PRODUCTS),
  saveProducts: (products: Product[]) => storage.saveData(STORAGE_KEYS.PRODUCTS, products),

  getSales: () => storage.getData<Sale>(STORAGE_KEYS.SALES),
  saveSales: (sales: Sale[]) => storage.saveData(STORAGE_KEYS.SALES, sales),

  getPayments: () => storage.getData<Payment>(STORAGE_KEYS.PAYMENTS),
  savePayments: (payments: Payment[]) => storage.saveData(STORAGE_KEYS.PAYMENTS, payments),

  getUsers: () => storage.getData<User>(STORAGE_KEYS.USERS),
  saveUsers: (users: User[]) => storage.saveData(STORAGE_KEYS.USERS, users),

  getCustomRoles: (): import('../types').RoleConfig[] => {
    return storage.getData<import('../types').RoleConfig>(STORAGE_KEYS.CUSTOM_ROLES);
  },
  saveCustomRoles: (roles: import('../types').RoleConfig[]) => {
    storage.saveData(STORAGE_KEYS.CUSTOM_ROLES, roles);
  },
  
  getLogs: () => storage.getData<InventoryLog>(STORAGE_KEYS.LOGS),
  saveLogs: (logs: InventoryLog[]) => storage.saveData(STORAGE_KEYS.LOGS, logs),

  getActivities: () => storage.getData<Activity>(STORAGE_KEYS.ACTIVITIES),
  saveActivities: (activities: Activity[]) => storage.saveData(STORAGE_KEYS.ACTIVITIES, activities),

  getReturns: () => storage.getData<SalesReturn>(STORAGE_KEYS.RETURNS),
  saveReturns: (returns: SalesReturn[]) => storage.saveData(STORAGE_KEYS.RETURNS, returns),

  getSettings: (): import('../types').AppSettings => {
    const data = localStorage.getItem(STORAGE_KEYS.SETTINGS);
    return data ? JSON.parse(data) : INITIAL_SETTINGS;
  },
  saveSettings: (settings: import('../types').AppSettings) => {
    localStorage.setItem(STORAGE_KEYS.SETTINGS, JSON.stringify(settings));
    storage.setSyncPending(true);
    triggerDebouncedSync();
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent('hysam-settings-updated', { detail: settings }));
    }
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
