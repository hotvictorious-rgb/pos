/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useEffect, useRef } from 'react';
import { motion, AnimatePresence } from 'motion/react';
import { 
  LayoutDashboard, 
  Package, 
  ShoppingCart, 
  CreditCard, 
  Truck, 
  LogOut, 
  Menu, 
  X, 
  RotateCcw,
  BarChart3,
  Server,
  WifiOff,
  Cloud,
  RefreshCw,
  Users as UsersIcon,
  Settings as SettingsIcon,
  Shield,
  Boxes,
  ShoppingBag,
  Lock,
  UserCheck,
  ChevronDown,
  ArrowUpRight,
  ArrowDownLeft,
  Clock,
  Globe,
  ShieldCheck,
  AlertTriangle,
  CheckCircle2
} from 'lucide-react';
import { storage } from './lib/storage';
import { User, UserRole, SyncVerificationResult } from './types';
import { canAccessModule, getRoleConfig } from './lib/rbac';

// Components
import Dashboard from './components/Dashboard';
import Inventory from './components/Inventory';
import Sales from './components/Sales';
import Payments from './components/Payments';
import Deliveries from './components/Deliveries';
import ActivityLog from './components/ActivityLog';
import Users from './components/Users';
import Settings from './components/Settings';
import Login from './components/Login';
import CalculatorDropdown from './components/CalculatorDropdown';

type Tab = 'dashboard' | 'inventory' | 'stock-in' | 'stock-out' | 'movement-logs' | 'sales' | 'payments' | 'deliveries' | 'activities' | 'users' | 'settings';

export default function App() {
  const [user, setUser] = useState<User | null>(null);
  const [activeTab, setActiveTab] = useState<Tab>('dashboard');
  const [inventorySubTab, setInventorySubTab] = useState<'products' | 'stock-in' | 'stock-out' | 'movement-logs'>('products');
  const [isInventoryExpanded, setIsInventoryExpanded] = useState<boolean>(true);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [loading, setLoading] = useState(true);

  const [isOnline, setIsOnline] = useState(typeof navigator !== 'undefined' ? navigator.onLine : true);
  const [syncPending, setSyncPending] = useState(storage.isSyncPending());
  const [isSyncing, setIsSyncing] = useState(false);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'info' | 'warning' } | null>(null);
  const [verificationResult, setVerificationResult] = useState<SyncVerificationResult | null>(storage.getVerificationResult());
  const [showAuditModal, setShowAuditModal] = useState(false);

  const [dbStatus, setDbStatus] = useState<{
    connected: boolean;
    synced: boolean;
    isGlobal: boolean;
    lastSync?: string;
    checking: boolean;
  }>({
    connected: false,
    synced: true,
    isGlobal: false,
    checking: true
  });

  const [currentTime, setCurrentTime] = useState(new Date());

  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentTime(new Date());
    }, 1000);
    return () => clearInterval(timer);
  }, []);

  const checkDbStatus = async () => {
    setDbStatus(prev => ({ ...prev, checking: true }));
    try {
      const res = await fetch('/api/me');
      const isConnected = res.ok;
      
      setDbStatus({
        connected: isConnected,
        isGlobal: true,
        synced: !storage.isSyncPending(),
        checking: false,
        lastSync: new Date().toLocaleTimeString()
      });

      // Online-only architecture: Laravel backend database is authoritative
      if (isConnected) {
        setDbStatus(prev => ({ ...prev, synced: true }));
      }
    } catch (e) {
      setDbStatus(prev => ({ ...prev, connected: false, checking: false }));
    }
  };

  const showToast = (message: string, type: 'success' | 'info' | 'warning') => {
    setToast({ message, type });
  };

  const [settings, setSettings] = useState(storage.getSettings());
  const mainContentRef = useRef<HTMLElement>(null);

  // Scroll to top when tab changes
  useEffect(() => {
    const timer = setTimeout(() => {
      if (mainContentRef.current) {
        mainContentRef.current.scrollTo({ top: 0 });
      }
      window.scrollTo({ top: 0 });
    }, 100);
    return () => clearTimeout(timer);
  }, [activeTab]);

  useEffect(() => {
    const applyTheme = (currentSettings: typeof settings) => {
      const root = document.documentElement;
      
      let fontStack = '"Inter", ui-sans-serif, system-ui, sans-serif';
      if (currentSettings.fontFamily === 'Plus Jakarta Sans') fontStack = '"Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif';
      else if (currentSettings.fontFamily === 'Space Grotesk') fontStack = '"Space Grotesk", ui-sans-serif, system-ui, sans-serif';
      else if (currentSettings.fontFamily === 'JetBrains Mono') fontStack = '"JetBrains Mono", monospace';
      else if (currentSettings.fontFamily === 'Playfair Display') fontStack = '"Playfair Display", serif';
      
      root.style.setProperty('--font-family', fontStack);
    };

    applyTheme(settings);

    const handleSettingsUpdate = (e: Event) => {
      const customEvent = e as CustomEvent;
      if (customEvent.detail) {
        setSettings(customEvent.detail);
        applyTheme(customEvent.detail);
      }
    };

    window.addEventListener('hysam-settings-updated', handleSettingsUpdate);
    return () => window.removeEventListener('hysam-settings-updated', handleSettingsUpdate);
  }, [settings]);

  useEffect(() => {
    const handleOnline = () => {
      setIsOnline(true);
      showToast('You are back online! Synchronizing offline data with servers...', 'success');
      setIsSyncing(true);
      storage.sync().finally(() => {
        setIsSyncing(false);
        showToast('Synchronized successfully! All records are up to date.', 'success');
      });
    };
    const handleOffline = () => {
      setIsOnline(false);
      showToast('Internet connection lost. You are now working offline.', 'warning');
    };

    const handleSyncStatus = (e: Event) => {
      const customEvent = e as CustomEvent;
      if (customEvent.detail) {
        setSyncPending(customEvent.detail.pending);
        setDbStatus(prev => ({ ...prev, synced: !customEvent.detail.pending }));
      } else {
        const pending = storage.isSyncPending();
        setSyncPending(pending);
        setDbStatus(prev => ({ ...prev, synced: !pending }));
      }
    };

    const handleSyncStart = () => setIsSyncing(true);
    const handleSyncEnd = () => {
      setIsSyncing(false);
      setDbStatus(prev => ({ ...prev, synced: !storage.isSyncPending(), lastSync: new Date().toLocaleTimeString() }));
    };

    const handleGlobalToast = (e: Event) => {
      const customEvent = e as CustomEvent;
      if (customEvent.detail) {
        showToast(customEvent.detail.message, customEvent.detail.type);
      }
    };

    const handleVerification = (e: Event) => {
      const customEvent = e as CustomEvent;
      if (customEvent.detail) {
        setVerificationResult(customEvent.detail);
      }
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    window.addEventListener('hysam-sync-status', handleSyncStatus);
    window.addEventListener('hysam-sync-start', handleSyncStart);
    window.addEventListener('hysam-sync-end', handleSyncEnd);
    window.addEventListener('hysam-show-toast', handleGlobalToast);
    window.addEventListener('hysam-sync-verification', handleVerification);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
      window.removeEventListener('hysam-sync-status', handleSyncStatus);
      window.removeEventListener('hysam-sync-start', handleSyncStart);
      window.removeEventListener('hysam-sync-end', handleSyncEnd);
      window.removeEventListener('hysam-show-toast', handleGlobalToast);
      window.removeEventListener('hysam-sync-verification', handleVerification);
    };
  }, []);

  // Set up auto-dismiss for toast
  useEffect(() => {
    if (toast) {
      const timer = setTimeout(() => {
        setToast(null);
      }, 5000);
      return () => clearTimeout(timer);
    }
  }, [toast]);

  // Reset page view scroll to top on tab transition
  useEffect(() => {
    const mainEl = document.querySelector('main');
    if (mainEl) {
      mainEl.scrollTop = 0;
    }
  }, [activeTab]);

  // Safety tab guard if role changes or tab is inaccessible
  useEffect(() => {
    if (user && !canAccessModule(user, activeTab)) {
      const availableTabs = [
        'dashboard', 'inventory', 'sales', 'payments', 
        'deliveries', 'activities', 'users', 'settings'
      ];
      const fallback = availableTabs.find(m => canAccessModule(user, m));
      if (fallback) {
        setActiveTab(fallback as Tab);
      }
    }
  }, [user, activeTab]);

  useEffect(() => {
    // 1. Initial cached auth check for immediate UI rendering
    storage.init().then(() => {
      const cached = storage.getAuth();
      if (cached) {
        setUser(cached);
      }
      
      // 2. Query active Laravel session to verify authenticity
      fetch('/api/me')
        .then(res => {
          if (res.ok) {
            return res.json();
          } else {
            throw new Error('Unauthorized');
          }
        })
        .then(serverUser => {
          if (serverUser.disabled) {
            throw new Error('Account disabled');
          }
          storage.setAuth(serverUser);
          setUser(serverUser);
          checkDbStatus();
        })
        .catch((err) => {
          console.warn('Session verification failed:', err.message);
          storage.setAuth(null);
          setUser(null);
        })
        .finally(() => {
          setLoading(false);
        });
    });
  }, []);

  // Poll DB status every 5 minutes
  useEffect(() => {
    const interval = setInterval(checkDbStatus, 300000);
    return () => clearInterval(interval);
  }, []);

  const handleLogout = async () => {
    try {
      await fetch('/api/logout', { 
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
    } catch (error) {
      console.error('API logout failed:', error);
    }
    storage.setAuth(null);
    setUser(null);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-layout-theme-bg flex items-center justify-center p-4">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-theme" />
      </div>
    );
  }

  if (!user) {
    return <Login onLoginSuccess={(loggedInUser) => setUser(loggedInUser)} settings={settings} />;
  }

  const navItems = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { id: 'inventory', label: 'Products Catalog', icon: Package },
    { id: 'stock-in', label: 'Stock In', icon: ArrowUpRight },
    { id: 'stock-out', label: 'Stock Out', icon: ArrowDownLeft },
    { id: 'movement-logs', label: 'Movement History', icon: Clock },
    { id: 'sales', label: 'Sales & Returns', icon: ShoppingCart },
    { id: 'payments', label: 'Payments & Ledger', icon: CreditCard },
    { id: 'deliveries', label: 'Deliveries', icon: Truck },
    { id: 'activities', label: 'General Log', icon: RotateCcw },
    { id: 'users', label: 'User Management', icon: UsersIcon },
    { id: 'settings', label: 'App Settings', icon: SettingsIcon }
  ].filter(item => user && canAccessModule(user, item.id));

  const formattedDate = currentTime.toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });

  const formattedTime = currentTime.toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true
  });

  const roleConfig = getRoleConfig(user.role);

  return (
    <div className="min-h-screen md:h-screen bg-layout-theme-bg flex flex-col md:flex-row md:overflow-hidden">
      {/* Sidebar - Desktop */}
      <aside className="hidden md:flex w-64 bg-sidebar-theme text-sidebar-theme-text flex-col overflow-y-auto">
        <div className="p-6">
          <div className="flex items-center gap-3 mb-8">
            <div className="w-10 h-10 bg-primary-theme rounded-lg flex items-center justify-center shadow-md shadow-primary-theme/20">
              <BarChart3 className="text-sidebar-theme-text w-6 h-6" />
            </div>
            <div>
              <span className="font-extrabold text-lg tracking-tight block leading-none">HYSAM</span>
              <span className="text-[10px] text-sidebar-theme-text/60 font-medium tracking-wider uppercase">Ventures Suite</span>
            </div>
          </div>

          <nav className="space-y-1">
            {navItems.map((item) => {
              const isActive = activeTab === item.id;

              return (
                <button
                  key={item.id}
                  onClick={() => setActiveTab(item.id as Tab)}
                  className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-medium text-sm cursor-pointer ${
                    isActive 
                      ? 'bg-primary-theme text-sidebar-theme-text shadow-md shadow-primary-theme-dark/30 font-bold' 
                      : 'text-sidebar-theme-text/60 hover:bg-sidebar-theme-hover hover:text-sidebar-theme-text'
                  }`}
                >
                  <item.icon size={18} />
                  <span>{item.label}</span>
                </button>
              );
            })}
          </nav>
        </div>

        <div className="mt-auto p-6 border-t border-sidebar-theme-border space-y-4">
          {/* Connection Status */}
          <div className="p-3 bg-sidebar-theme-hover/60 rounded-xl border border-sidebar-theme-border/50 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs text-sidebar-theme-text/60 font-medium">Network Status</span>
              <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${
                isOnline 
                  ? 'bg-accent-theme/10 text-accent-theme border border-accent-theme/20' 
                  : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'
              }`}>
                <span className={`w-1.5 h-1.5 rounded-full ${isOnline ? 'bg-accent-theme animate-pulse' : 'bg-rose-400'}`} />
                {isOnline ? 'Online' : 'Offline'}
              </span>
            </div>

            <div className="flex items-center justify-between text-xs">
              <span className="text-sidebar-theme-text/60 font-medium">Database Sync</span>
              {isSyncing ? (
                <span className="text-primary-theme flex items-center gap-1 text-[11px] font-medium animate-pulse">
                  <RefreshCw size={12} className="animate-spin" />
                  Syncing...
                </span>
              ) : syncPending ? (
                <span className="text-amber-400 flex items-center gap-1 text-[11px] font-medium" title="Data saved locally, waiting for internet connection">
                  <WifiOff size={12} />
                  Pending Sync
                </span>
              ) : (
                <span className="text-accent-theme flex items-center gap-1 text-[11px] font-medium">
                  <Cloud size={12} />
                  Up to Date
                </span>
              )}
            </div>

            {isOnline && syncPending && (
              <button
                onClick={async () => {
                  setIsSyncing(true);
                  await storage.sync();
                  setIsSyncing(false);
                }}
                disabled={isSyncing}
                className="w-full mt-1.5 py-1.5 bg-primary-theme hover:bg-primary-theme-hover disabled:opacity-50 text-sidebar-theme-text rounded-lg text-[10px] font-bold flex items-center justify-center gap-1.5 transition-all cursor-pointer"
              >
                <RefreshCw size={10} className={isSyncing ? 'animate-spin' : ''} />
                Sync Now
              </button>
            )}
          </div>

          <div className="flex items-center gap-3 pt-2">
            <div className="w-9 h-9 bg-sidebar-theme-hover rounded-xl border border-sidebar-theme-border flex items-center justify-center text-xs font-bold uppercase text-primary-theme">
              {user.name.charAt(0)}
            </div>
            <div className="flex flex-col overflow-hidden">
              <span className="text-sm font-semibold truncate text-sidebar-theme-text">{user.name}</span>
              <span className={`inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9px] font-bold uppercase tracking-wider ${roleConfig.badgeBg} ${roleConfig.badgeText} border ${roleConfig.badgeBorder} w-max mt-0.5`}>
                {roleConfig.label}
              </span>
            </div>
          </div>
          
          <button 
            onClick={handleLogout}
            className="w-full flex items-center gap-3 px-4 py-2 text-sidebar-theme-text/60 hover:text-red-400 transition-colors cursor-pointer"
          >
            <LogOut size={18} />
            <span className="text-sm font-medium">Logout</span>
          </button>
        </div>
      </aside>

      {/* Header - Mobile */}
      <header className="md:hidden bg-sidebar-theme text-sidebar-theme-text p-4 flex items-center justify-between border-b border-sidebar-theme-border">
        <div className="flex items-center gap-3">
          <BarChart3 className="text-primary-theme" />
          <span className="font-bold">HYSAM</span>
          <span className={`inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[9px] font-bold uppercase ${roleConfig.badgeBg} ${roleConfig.badgeText}`}>
            {user.role}
          </span>
        </div>
        <div className="flex items-center gap-3">
          {activeTab === 'settings' && (
            <button 
              onClick={() => window.dispatchEvent(new CustomEvent('hysam-trigger-save-settings'))}
              className="px-3 py-1 bg-primary-theme text-sidebar-theme-text rounded-lg text-[10px] font-bold uppercase tracking-wider animate-pulse shadow-lg shadow-primary-theme/20"
            >
              Save
            </button>
          )}
          <button onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}>
            {isMobileMenuOpen ? <X /> : <Menu />}
          </button>
        </div>
      </header>

      {/* Mobile Menu Overlay */}
      <AnimatePresence>
        {isMobileMenuOpen && (
          <motion.div
            initial={{ opacity: 0, x: -100 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -100 }}
            className="fixed inset-0 z-50 bg-sidebar-theme md:hidden flex flex-col"
          >
            {/* Mobile Menu Header */}
            <div className="flex justify-between items-center p-6 border-b border-sidebar-theme-border text-sidebar-theme-text shrink-0">
              <span className="font-bold text-xl">Menu</span>
              <button 
                onClick={() => setIsMobileMenuOpen(false)}
                className="p-1 hover:bg-sidebar-theme-hover rounded-lg transition-colors cursor-pointer text-sidebar-theme-text"
                id="close-menu-btn"
              >
                <X size={28} />
              </button>
            </div>

            {/* Scrollable Content */}
            <div className="flex-1 overflow-y-auto p-6 space-y-8 flex flex-col justify-between">
              <nav className="space-y-2">
                {navItems.map((item) => {
                  const isActive = activeTab === item.id;

                  return (
                    <button
                      key={item.id}
                      onClick={() => {
                        setActiveTab(item.id as Tab);
                        setIsMobileMenuOpen(false);
                      }}
                      className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl text-base font-medium transition-colors cursor-pointer ${
                        isActive 
                          ? 'bg-primary-theme text-sidebar-theme-text font-bold' 
                          : 'text-sidebar-theme-text/60 hover:bg-sidebar-theme-hover/50 hover:text-sidebar-theme-text'
                      }`}
                    >
                      <item.icon size={20} />
                      <span>{item.label}</span>
                    </button>
                  );
                })}
              </nav>

              <div className="pt-6 border-t border-sidebar-theme-border space-y-4">
                <div className="flex items-center gap-3 px-2 py-1">
                  <div className="w-9 h-9 bg-slate-700 rounded-full flex items-center justify-center text-xs font-bold uppercase text-sidebar-theme-text">
                    {user?.name?.charAt(0) || ''}
                  </div>
                  <div className="flex flex-col text-left">
                    <span className="text-sm font-semibold text-sidebar-theme-text">{user?.name}</span>
                    <span className="text-xs text-sidebar-theme-text/60 capitalize">{roleConfig.label}</span>
                  </div>
                </div>

                <button 
                  onClick={handleLogout}
                  className="w-full flex items-center gap-3 px-4 py-2.5 text-sidebar-theme-text/60 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-all text-sm font-medium cursor-pointer"
                >
                  <LogOut size={18} />
                  <span>Logout</span>
                </button>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Main Content */}
      <main ref={mainContentRef} className="flex-1 md:overflow-y-auto bg-layout-theme-bg flex flex-col">
        {/* Persistent Header for Status */}
        <header className="sticky top-0 z-30 bg-layout-theme-bg/80 backdrop-blur-md border-b border-slate-200 px-4 md:px-8 py-3 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <h1 className="text-sm font-bold text-slate-800 hidden md:block">
              {navItems.find(i => i.id === activeTab)?.label || 'Dashboard'}
            </h1>
            <div className="hidden sm:flex items-center gap-2 text-xs font-semibold text-slate-400 bg-slate-50 border border-slate-100 px-3 py-1.5 rounded-full select-none shadow-2xs">
              <Clock size={12} className="text-primary-theme shrink-0 animate-pulse" />
              <span>{formattedDate}</span>
              <span className="text-slate-300 font-normal">|</span>
              <span className="text-slate-800 font-bold font-mono text-[11px]">{formattedTime}</span>
            </div>
          </div>

          <div className="flex items-center gap-2 md:gap-4">
            {/* DB Status Indicator */}
            <div className="flex items-center bg-white border border-slate-200 rounded-full px-3 py-1.5 shadow-sm gap-2">
              {/* Sync Status */}
              <div className="flex items-center gap-1.5" title={dbStatus.lastSync ? `Last synced: ${dbStatus.lastSync}` : 'Never synced'}>
                {isSyncing ? (
                  <RefreshCw size={12} className="text-primary-theme animate-spin" />
                ) : dbStatus.synced ? (
                  <Cloud size={12} className="text-emerald-500" />
                ) : (
                  <WifiOff size={12} className="text-amber-500" />
                )}
                <div className="flex flex-col">
                  <span className="text-[10px] font-bold uppercase tracking-tight text-slate-600 leading-none">
                    {isSyncing ? 'Syncing...' : dbStatus.synced ? 'Synced' : 'Not Synced'}
                  </span>
                  {dbStatus.lastSync && (
                    <span className="text-[8px] text-slate-400 mt-0.5 leading-none">
                      {dbStatus.lastSync}
                    </span>
                  )}
                </div>
              </div>
              
              {!dbStatus.checking && (
                <button 
                  onClick={checkDbStatus}
                  className="p-1 hover:bg-slate-100 rounded-full transition-colors text-slate-400 hover:text-slate-600 cursor-pointer"
                  title="Sync with central database"
                >
                  <RefreshCw size={10} className={dbStatus.checking ? 'animate-spin' : ''} />
                </button>
              )}
            </div>

            {/* Interactive Calculator dropdown */}
            <CalculatorDropdown />

            <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-tight bg-emerald-50 text-emerald-700 border border-emerald-200">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              Authoritative Backend
            </div>

            {/* User Profile Mini */}
            <div className="hidden sm:flex items-center gap-3 pl-2 border-l border-slate-200">
              <div className="text-right">
                <p className="text-[10px] font-bold text-slate-900 leading-none">{user.name}</p>
                <p className="text-[9px] text-slate-500 mt-0.5 leading-none">{roleConfig.label}</p>
              </div>
              <div className="w-8 h-8 bg-slate-100 rounded-full border border-slate-200 flex items-center justify-center text-[10px] font-bold text-primary-theme">
                {user.name.charAt(0)}
              </div>
            </div>
          </div>
        </header>

        <div className="flex-1 p-4 md:p-8">
          <div className="max-w-7xl mx-auto">
          <AnimatePresence mode="wait">
            <motion.div
              key={activeTab}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              transition={{ duration: 0.2 }}
            >
              {activeTab === 'dashboard' && canAccessModule(user, 'dashboard') && <Dashboard />}
              {activeTab === 'inventory' && canAccessModule(user, 'inventory') && (
                <Inventory 
                  user={user} 
                  initialSubTab="products" 
                  onSubTabChange={(tab) => {
                    if (tab === 'products') setActiveTab('inventory');
                    else setActiveTab(tab as any);
                  }}
                />
              )}
              {activeTab === 'stock-in' && canAccessModule(user, 'stock-in') && (
                <Inventory 
                  user={user} 
                  initialSubTab="stock-in" 
                  onSubTabChange={(tab) => {
                    if (tab === 'products') setActiveTab('inventory');
                    else setActiveTab(tab as any);
                  }}
                />
              )}
              {activeTab === 'stock-out' && canAccessModule(user, 'stock-out') && (
                <Inventory 
                  user={user} 
                  initialSubTab="stock-out" 
                  onSubTabChange={(tab) => {
                    if (tab === 'products') setActiveTab('inventory');
                    else setActiveTab(tab as any);
                  }}
                />
              )}
              {activeTab === 'movement-logs' && canAccessModule(user, 'movement-logs') && (
                <Inventory 
                  user={user} 
                  initialSubTab="movement-logs" 
                  onSubTabChange={(tab) => {
                    if (tab === 'products') setActiveTab('inventory');
                    else setActiveTab(tab as any);
                  }}
                />
              )}
              {activeTab === 'sales' && canAccessModule(user, 'sales') && <Sales user={user} />}
              {activeTab === 'payments' && canAccessModule(user, 'payments') && <Payments user={user} />}
              {activeTab === 'deliveries' && canAccessModule(user, 'deliveries') && <Deliveries user={user} />}
              {activeTab === 'activities' && canAccessModule(user, 'activities') && <ActivityLog />}
              {activeTab === 'users' && canAccessModule(user, 'users') && <Users currentUser={user} />}
              {activeTab === 'settings' && canAccessModule(user, 'settings') && <Settings user={user} />}
            </motion.div>
          </AnimatePresence>
        </div>
      </div>
    </main>

      {/* Database Sync Routine Audit Modal */}
      <AnimatePresence>
        {showAuditModal && verificationResult && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: 10 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 10 }}
              className="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 overflow-hidden space-y-5"
            >
              <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                <div className="flex items-center gap-3">
                  <div className={`p-2.5 rounded-xl ${verificationResult.hasDiscrepancy ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>
                    <ShieldCheck size={24} />
                  </div>
                  <div>
                    <h3 className="font-extrabold text-slate-900 text-base">Database Sync Record Audit</h3>
                    <p className="text-xs text-slate-500 mt-0.5">
                      Verified at {new Date(verificationResult.timestamp).toLocaleTimeString()} on {new Date(verificationResult.timestamp).toLocaleDateString()}
                    </p>
                  </div>
                </div>

                <button
                  onClick={() => setShowAuditModal(false)}
                  className="p-1.5 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 transition-colors cursor-pointer"
                >
                  <X size={20} />
                </button>
              </div>

              <div className={`p-3.5 rounded-xl text-xs font-semibold flex items-start gap-2.5 ${
                verificationResult.hasDiscrepancy
                  ? 'bg-amber-50 text-amber-900 border border-amber-200'
                  : 'bg-emerald-50 text-emerald-900 border border-emerald-200'
              }`}>
                {verificationResult.hasDiscrepancy ? (
                  <AlertTriangle className="text-amber-600 shrink-0 mt-0.5" size={16} />
                ) : (
                  <CheckCircle2 className="text-emerald-600 shrink-0 mt-0.5" size={16} />
                )}
                <div className="leading-relaxed">
                  {verificationResult.message}
                </div>
              </div>

              <div className="space-y-2">
                <h4 className="text-xs font-bold text-slate-500 uppercase tracking-wider">Side-by-Side Table Comparison</h4>
                <div className="border border-slate-200 rounded-xl overflow-hidden">
                  <table className="w-full text-xs text-left">
                    <thead>
                      <tr className="bg-slate-50 text-slate-500 font-bold border-b border-slate-200">
                        <th className="px-3.5 py-2.5">Table Collection</th>
                        <th className="px-3.5 py-2.5 text-center">App State</th>
                        <th className="px-3.5 py-2.5 text-center">Database</th>
                        <th className="px-3.5 py-2.5 text-center">Diff</th>
                        <th className="px-3.5 py-2.5 text-right">Parity</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {[
                        { key: 'sales', label: 'Sales Records', ...verificationResult.tables.sales },
                        { key: 'returns', label: 'Sales Returns', ...verificationResult.tables.returns },
                        { key: 'products', label: 'Inventory Products', ...verificationResult.tables.products },
                        { key: 'payments', label: 'Payment Transactions', ...verificationResult.tables.payments },
                        { key: 'users', label: 'Users & Staff', ...verificationResult.tables.users },
                        { key: 'logs', label: 'Inventory Movement Logs', ...verificationResult.tables.logs },
                        { key: 'roles', label: 'Custom User Roles', ...verificationResult.tables.roles },
                      ].map((tbl) => (
                        <tr key={tbl.key} className="hover:bg-slate-50/60">
                          <td className="px-3.5 py-2.5 font-bold text-slate-800">{tbl.label}</td>
                          <td className="px-3.5 py-2.5 text-center font-mono font-bold text-slate-700">{tbl.localCount}</td>
                          <td className="px-3.5 py-2.5 text-center font-mono font-bold text-slate-700">{tbl.serverCount}</td>
                          <td className="px-3.5 py-2.5 text-center">
                            {tbl.diff === 0 ? (
                              <span className="text-slate-400 font-mono">0</span>
                            ) : (
                              <span className="text-rose-600 font-bold font-mono">{tbl.diff > 0 ? `+${tbl.diff}` : tbl.diff}</span>
                            )}
                          </td>
                          <td className="px-3.5 py-2.5 text-right">
                            {tbl.match ? (
                              <span className="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                <CheckCircle2 size={10} /> 1:1 Match
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                <AlertTriangle size={10} /> Discrepancy
                              </span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button
                  onClick={() => setShowAuditModal(false)}
                  className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50 transition-colors cursor-pointer"
                >
                  Close
                </button>
                <button
                  onClick={async () => {
                    setIsSyncing(true);
                    await storage.forceSync();
                    setIsSyncing(false);
                  }}
                  disabled={isSyncing}
                  className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold flex items-center gap-2 shadow-sm transition-colors cursor-pointer disabled:opacity-50"
                >
                  <RefreshCw size={13} className={isSyncing ? 'animate-spin' : ''} />
                  {isSyncing ? 'Re-Syncing & Verifying...' : 'Re-Sync & Re-Verify Now'}
                </button>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>

      {/* Toast Notification */}
      <AnimatePresence>
        {toast && (
          <motion.div
            initial={{ opacity: 0, y: 50, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 20, scale: 0.95 }}
            className="fixed bottom-6 right-6 z-50 max-w-sm"
          >
            <div className={`p-4 rounded-xl border shadow-xl flex items-start gap-3 ${
              toast.type === 'success' ? 'bg-accent-theme-light border-accent-theme-light text-accent-theme-dark' :
              toast.type === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800' :
              'bg-primary-theme-light border-primary-theme-light text-primary-theme-dark'
            }`}>
              <div className="mt-1">
                {toast.type === 'success' && <div className="w-2.5 h-2.5 bg-accent-theme rounded-full animate-ping" />}
                {toast.type === 'warning' && <div className="w-2.5 h-2.5 bg-amber-500 rounded-full animate-pulse" />}
                {toast.type === 'info' && <div className="w-2.5 h-2.5 bg-primary-theme rounded-full animate-pulse" />}
              </div>
              <div>
                <p className="text-[10px] font-bold uppercase tracking-wider text-sidebar-theme-text/60">System Notification</p>
                <p className="text-sm font-semibold mt-0.5 leading-relaxed">{toast.message}</p>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
