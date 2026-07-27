/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useMemo, useEffect } from 'react';
import { 
  CreditCard, 
  Search, 
  Calendar, 
  CheckCircle2, 
  Printer, 
  RotateCcw, 
  DollarSign, 
  Filter, 
  Download, 
  FileText, 
  Eye, 
  Coins, 
  Clock, 
  ArrowUpRight, 
  ArrowDownLeft, 
  X, 
  ShoppingBag,
  History,
  TrendingUp,
  Receipt,
  UserCheck,
  Banknote,
  Wallet
} from 'lucide-react';
import { storage } from '../lib/storage';
import { DateFilterOption, matchesDateFilter } from '../lib/dateUtils';
import { Sale, Payment, SalesReturn, User } from '../types';
import { hasModulePermission } from '../lib/rbac';
import ReceiptModal from './ReceiptModal';

interface PaymentsProps {
  user: User;
}

export interface UnifiedLedgerEntry {
  id: string;
  transactionRef: string;
  saleId: string;
  customerName: string;
  type: 'full_payment' | 'part_payment' | 'refund';
  amount: number;
  method: string;
  timestamp: string;
  recordedBy: string;
  recordedByName?: string;
  note?: string;
  sale?: Sale;
  itemsCount?: number;
}

export default function Payments({ user }: PaymentsProps) {
  const [settings] = useState(storage.getSettings());
  const [sales, setSales] = useState<Sale[]>(storage.getSales());
  const [payments, setPayments] = useState<Payment[]>(storage.getPayments());
  const [returns, setReturns] = useState<SalesReturn[]>(storage.getReturns());
  const [users, setUsers] = useState<User[]>(storage.getUsers());

  // Navigation sub-tab inside Payments module
  const [activeSubTab, setActiveSubTab] = useState<'history' | 'pending'>('history');

  // Search & Filters state
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState<'all' | 'full_payment' | 'part_payment' | 'refund'>('all');
  const [methodFilter, setMethodFilter] = useState<string>('all');
  const [dateFilter, setDateFilter] = useState<DateFilterOption>('today');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');

  // Modals state
  const [selectedSaleForPayment, setSelectedSaleForPayment] = useState<Sale | null>(null);
  const [paymentModalAmount, setPaymentModalAmount] = useState<number>(0);
  const [paymentModalMethod, setPaymentModalMethod] = useState('Cash');
  const [receiptSale, setReceiptSale] = useState<Sale | null>(null);
  const [detailSale, setDetailSale] = useState<Sale | null>(null);

  useEffect(() => {
    const refreshData = () => {
      setSales(storage.getSales());
      setPayments(storage.getPayments());
      setReturns(storage.getReturns());
      setUsers(storage.getUsers());
    };
    refreshData();
    window.addEventListener('hysam-data-updated', refreshData);
    window.addEventListener('hysam-sync-end', refreshData);
    return () => {
      window.removeEventListener('hysam-data-updated', refreshData);
      window.removeEventListener('hysam-sync-end', refreshData);
    };
  }, []);

  const canCreate = hasModulePermission(user, 'payments', 'create');

  // Helper to resolve user name from ID
  const getUserDisplayName = (userId: string) => {
    const matchedUser = users.find(u => u.id === userId);
    return matchedUser?.name || userId || 'Staff User';
  };

  // 1. Construct Complete Unified Ledger Entries
  const rawLedgerEntries = useMemo<UnifiedLedgerEntry[]>(() => {
    const entries: UnifiedLedgerEntry[] = [];
    const processedSalePaymentIds = new Set<string>();

    // A. Add explicit payment records
    payments.forEach(p => {
      const linkedSale = sales.find(s => s.id === p.saleId);
      processedSalePaymentIds.add(p.saleId);

      // Determine payment type
      let type: 'full_payment' | 'part_payment' = 'part_payment';
      if (linkedSale) {
        if (linkedSale.status === 'completed' && p.amount >= linkedSale.totalAmount - 0.01) {
          type = 'full_payment';
        } else if (linkedSale.status === 'completed') {
          type = 'full_payment';
        } else {
          type = 'part_payment';
        }
      }

      entries.push({
        id: p.id,
        transactionRef: p.id,
        saleId: p.saleId,
        customerName: linkedSale?.customerName || 'General Sale',
        type,
        amount: p.amount || 0,
        method: p.method || 'Cash',
        timestamp: p.timestamp || p.createdAt || new Date().toISOString(),
        recordedBy: p.recordedBy || 'System',
        recordedByName: getUserDisplayName(p.recordedBy),
        sale: linkedSale,
        itemsCount: linkedSale?.items?.length || 0
      });
    });

    // B. Fallback for sales that have paidAmount > 0 but were created before payments table tracking
    sales.forEach(s => {
      if (!processedSalePaymentIds.has(s.id)) {
        const initialPaid = s.paidAmount !== undefined 
          ? s.paidAmount 
          : ((s.cashAmount || 0) + (s.posAmount || 0));

        if (initialPaid > 0) {
          if ((s.cashAmount || 0) > 0 && (s.posAmount || 0) > 0) {
            entries.push({
              id: 'PAY-' + s.id + '-CASH',
              transactionRef: 'PAY-' + s.id,
              saleId: s.id,
              customerName: s.customerName,
              type: s.status === 'completed' ? 'full_payment' : 'part_payment',
              amount: s.cashAmount,
              method: 'Cash',
              timestamp: s.createdAt,
              recordedBy: s.userId || 'System',
              recordedByName: s.userName || getUserDisplayName(s.userId),
              sale: s,
              itemsCount: s.items?.length || 0
            });
            entries.push({
              id: 'PAY-' + s.id + '-POS',
              transactionRef: 'PAY-' + s.id,
              saleId: s.id,
              customerName: s.customerName,
              type: s.status === 'completed' ? 'full_payment' : 'part_payment',
              amount: s.posAmount,
              method: 'POS',
              timestamp: s.createdAt,
              recordedBy: s.userId || 'System',
              recordedByName: s.userName || getUserDisplayName(s.userId),
              sale: s,
              itemsCount: s.items?.length || 0
            });
          } else {
            let payMethod = 'Cash';
            if ((s.posAmount || 0) > 0) payMethod = 'POS';

            entries.push({
              id: 'PAY-' + s.id,
              transactionRef: 'PAY-' + s.id,
              saleId: s.id,
              customerName: s.customerName,
              type: s.status === 'completed' ? 'full_payment' : 'part_payment',
              amount: initialPaid,
              method: payMethod,
              timestamp: s.createdAt,
              recordedBy: s.userId || 'System',
              recordedByName: s.userName || getUserDisplayName(s.userId),
              sale: s,
              itemsCount: s.items?.length || 0
            });
          }
        }
      }
    });

    // C. Add Sales Returns / Refunds
    returns.forEach(r => {
      if (r.refundAmount && r.refundAmount > 0) {
        const linkedSale = sales.find(s => s.id === r.saleId);
        entries.push({
          id: 'REF-' + r.id,
          transactionRef: r.code || r.id,
          saleId: r.saleId,
          customerName: r.customerName || linkedSale?.customerName || 'Customer',
          type: 'refund',
          amount: r.refundAmount,
          method: 'Refund',
          timestamp: r.createdAt || r.timestamp || new Date().toISOString(),
          recordedBy: r.userId || 'System',
          recordedByName: r.userName || getUserDisplayName(r.userId),
          note: `Refund for ${r.productName || 'Returned Item'} (Qty: ${r.quantity || 1}) - Reason: ${r.reason || 'N/A'}`,
          sale: linkedSale,
          itemsCount: 1
        });
      }
    });

    // Sort chronologically descending (newest first)
    return entries.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());
  }, [payments, sales, returns, users]);

  // 2. Filtered Ledger Entries
  const filteredLedgerEntries = useMemo(() => {
    return rawLedgerEntries.filter(entry => {
      // Date filter
      if (!matchesDateFilter(entry.timestamp, dateFilter, startDate, endDate)) return false;

      // Type filter
      if (typeFilter !== 'all' && entry.type !== typeFilter) return false;

      // Method filter
      if (methodFilter !== 'all') {
        const entryMethod = entry.method.toLowerCase();
        const targetMethod = methodFilter.toLowerCase();
        if (!entryMethod.includes(targetMethod)) return false;
      }

      // Search query
      const query = search.trim().toLowerCase();
      if (!query) return true;

      const matchRef = entry.transactionRef.toLowerCase().includes(query);
      const matchSaleId = entry.saleId.toLowerCase().includes(query);
      const matchCustomer = entry.customerName.toLowerCase().includes(query);
      const matchMethod = entry.method.toLowerCase().includes(query);
      const matchUser = (entry.recordedByName || '').toLowerCase().includes(query);
      const matchNote = (entry.note || '').toLowerCase().includes(query);

      return matchRef || matchSaleId || matchCustomer || matchMethod || matchUser || matchNote;
    });
  }, [rawLedgerEntries, dateFilter, startDate, endDate, typeFilter, methodFilter, search]);

  // 2.5 Ledger entries filtered ONLY by Date (for stable overall summary cards)
  const dateFilteredLedgerEntries = useMemo(() => {
    return rawLedgerEntries.filter(entry => {
      return matchesDateFilter(entry.timestamp, dateFilter, startDate, endDate);
    });
  }, [rawLedgerEntries, dateFilter, startDate, endDate]);

  // 3. Financial Metrics Calculations for Date-Filtered View
  const financialMetrics = useMemo(() => {
    let totalCollected = 0;
    let totalCollectedCount = 0;
    let totalCash = 0;
    let cashCount = 0;
    let totalPos = 0;
    let posCount = 0;
    let totalRefunds = 0;
    let refundsCount = 0;

    dateFilteredLedgerEntries.forEach(entry => {
      if (entry.type === 'refund') {
        totalRefunds += entry.amount;
        refundsCount++;
      } else {
        totalCollected += entry.amount;
        totalCollectedCount++;

        const methodLower = (entry.method || '').toLowerCase();
        if (methodLower.includes('cash')) {
          totalCash += entry.amount;
          cashCount++;
        } else {
          totalPos += entry.amount;
          posCount++;
        }
      }
    });

    const netBalance = totalCollected - totalRefunds;

    return {
      totalCollected,
      totalCollectedCount,
      totalCash,
      cashCount,
      totalPos,
      posCount,
      totalRefunds,
      refundsCount,
      netBalance
    };
  }, [dateFilteredLedgerEntries]);

  // 4. Pending Installment Collections Logic
  const getSaleBalance = (sale: Sale) => {
    const salePayments = payments.filter(p => p.saleId === sale.id);
    const paidFromPayments = salePayments.reduce((acc, p) => acc + p.amount, 0);
    const initialPaid = sale.paidAmount !== undefined 
      ? sale.paidAmount 
      : ((sale.cashAmount || 0) + (sale.posAmount || 0));
    const currentPaid = Math.max(initialPaid, paidFromPayments);
    return Math.max(0, sale.totalAmount - currentPaid);
  };

  const installmentSales = useMemo(() => {
    return sales.filter(s => {
      if (s.status !== 'installment') return false;
      const query = search.toLowerCase().trim();
      if (!query) return true;
      return s.id.toLowerCase().includes(query) || s.customerName.toLowerCase().includes(query);
    });
  }, [sales, search]);

  const totalOutstandingBalance = useMemo(() => {
    return installmentSales.reduce((sum, sale) => sum + getSaleBalance(sale), 0);
  }, [installmentSales, payments]);

  // Handle adding an installment payment
  const handleAddPayment = async () => {
    if (!selectedSaleForPayment || paymentModalAmount <= 0) return;

    const balance = getSaleBalance(selectedSaleForPayment);
    if (paymentModalAmount > balance + 0.01) {
      alert(`Amount exceeds remaining balance (${settings.currency}${balance.toLocaleString()})`);
      return;
    }

    if (!window.confirm(`Confirm recording payment of ${settings.currency}${paymentModalAmount.toLocaleString()} via ${paymentModalMethod} for ${selectedSaleForPayment.customerName}?`)) {
      return;
    }

    const currentPaid = selectedSaleForPayment.totalAmount - balance;
    const newTotalPaid = currentPaid + paymentModalAmount;
    const isFullyPaid = newTotalPaid >= selectedSaleForPayment.totalAmount - 0.01;
    const newStatus = isFullyPaid ? 'completed' : 'installment';

    const newPayment: Payment = {
      id: 'PAY-' + Math.random().toString(36).substr(2, 9).toUpperCase(),
      saleId: selectedSaleForPayment.id,
      amount: paymentModalAmount,
      method: paymentModalMethod,
      timestamp: new Date().toISOString(),
      recordedBy: user.id
    };

    const allPayments = [newPayment, ...payments];
    storage.savePayments(allPayments);

    // Update sale status in storage
    const allSales = storage.getSales();
    const updatedSales = allSales.map(s => 
      s.id === selectedSaleForPayment.id ? { ...s, status: newStatus as any, paidAmount: newTotalPaid } : s
    );
    storage.saveSales(updatedSales);
    setSales(updatedSales);

    storage.logActivity({
      type: 'payment',
      description: `Payment of ${settings.currency}${paymentModalAmount.toLocaleString()} received for order #${selectedSaleForPayment.id} (${selectedSaleForPayment.customerName}). Status: ${newStatus.toUpperCase()}`,
      userId: user.id,
      userName: user.name
    });

    setPayments(allPayments);
    await storage.sync();

    setSelectedSaleForPayment(null);
    setPaymentModalAmount(0);
  };

  // 5. CSV Export Handler
  const handleExportCSV = () => {
    if (filteredLedgerEntries.length === 0) {
      alert('No payment entries available to export.');
      return;
    }

    const headers = [
      'Date & Time',
      'Transaction Ref',
      'Sale ID',
      'Customer Name',
      'Type',
      'Payment Method',
      'Amount',
      'Recorded By',
      'Notes'
    ];

    const rows = filteredLedgerEntries.map(e => [
      `"${new Date(e.timestamp).toLocaleString()}"`,
      `"${e.transactionRef}"`,
      `"${e.saleId}"`,
      `"${e.customerName.replace(/"/g, '""')}"`,
      `"${e.type === 'full_payment' ? 'Full Payment' : e.type === 'part_payment' ? 'Part / Installment' : 'Refund'}"`,
      `"${e.method}"`,
      e.type === 'refund' ? -e.amount : e.amount,
      `"${(e.recordedByName || e.recordedBy).replace(/"/g, '""')}"`,
      `"${(e.note || '').replace(/"/g, '""')}"`
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `HYSAM_Payment_Ledger_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="space-y-6">
      {/* Top Title & Navigation Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-card-theme-bg p-6 rounded-3xl border border-slate-200/80 shadow-2xs">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="p-2 bg-primary-theme-light text-primary-theme rounded-xl">
              <DollarSign size={20} />
            </span>
            <h2 className="text-2xl font-extrabold text-slate-900 tracking-tight">Financial Payments & Ledger</h2>
          </div>
          <p className="text-sm text-slate-500 pl-10">
            Real-time audit log of full payments, part installments, and customer refunds
          </p>
        </div>

        {/* Sub-Tab Selector */}
        <div className="flex items-center bg-slate-100 p-1.5 rounded-2xl border border-slate-200 shrink-0">
          <button
            onClick={() => setActiveSubTab('history')}
            className={`flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-xs transition-all cursor-pointer ${
              activeSubTab === 'history'
                ? 'bg-card-theme-bg text-slate-900 shadow-xs border border-slate-200/60'
                : 'text-slate-500 hover:text-slate-900'
            }`}
          >
            <History size={16} className={activeSubTab === 'history' ? 'text-primary-theme' : ''} />
            <span>Payment History Log</span>
            <span className="bg-slate-200 text-slate-700 px-2 py-0.5 rounded-full text-[10px] font-mono">
              {rawLedgerEntries.length}
            </span>
          </button>

          <button
            onClick={() => setActiveSubTab('pending')}
            className={`flex items-center gap-2 px-4 py-2 rounded-xl font-bold text-xs transition-all cursor-pointer ${
              activeSubTab === 'pending'
                ? 'bg-card-theme-bg text-slate-900 shadow-xs border border-slate-200/60'
                : 'text-slate-500 hover:text-slate-900'
            }`}
          >
            <Clock size={16} className={activeSubTab === 'pending' ? 'text-amber-600' : ''} />
            <span>Pending Collections</span>
            {installmentSales.length > 0 && (
              <span className="bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold animate-pulse">
                {installmentSales.length}
              </span>
            )}
          </button>
        </div>
      </div>

      {/* SUB-TAB 1: PAYMENT HISTORY LEDGER */}
      {activeSubTab === 'history' && (
        <div className="space-y-6">
          {/* Financial Metrics Cards (5 Cards) */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
            <button 
              onClick={() => { setMethodFilter('all'); setTypeFilter('all'); }}
              className={`p-5 rounded-2xl border text-left transition-all cursor-pointer ${
                methodFilter === 'all' && typeFilter === 'all'
                  ? 'bg-card-theme-bg border-primary-theme ring-2 ring-primary-theme-light shadow-sm'
                  : 'bg-card-theme-bg border-slate-200/80 shadow-2xs hover:border-primary-theme-light'
              }`}
            >
              <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Total Collected</span>
                <span className="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
                  <ArrowUpRight size={18} />
                </span>
              </div>
              <div className="text-2xl font-extrabold text-slate-900 font-mono tracking-tight">
                {settings.currency}{financialMetrics.totalCollected.toLocaleString()}
              </div>
              <p className="text-xs text-slate-500 mt-1 flex items-center gap-1">
                <span className="font-bold text-slate-700">{financialMetrics.totalCollectedCount}</span> transaction(s)
              </p>
            </button>

            <button 
              onClick={() => { setMethodFilter('Cash'); setTypeFilter('all'); }}
              className={`p-5 rounded-2xl border text-left transition-all cursor-pointer ${
                methodFilter === 'Cash'
                  ? 'bg-card-theme-bg border-blue-500 ring-2 ring-blue-100 shadow-sm'
                  : 'bg-card-theme-bg border-slate-200/80 shadow-2xs hover:border-blue-200'
              }`}
            >
              <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Total Cash</span>
                <span className="p-2 bg-blue-50 text-blue-600 rounded-xl">
                  <Banknote size={18} />
                </span>
              </div>
              <div className="text-2xl font-extrabold text-slate-900 font-mono tracking-tight">
                {settings.currency}{financialMetrics.totalCash.toLocaleString()}
              </div>
              <p className="text-xs text-slate-500 mt-1">
                <span className="font-bold text-slate-700">{financialMetrics.cashCount}</span> cash payment(s)
              </p>
            </button>

            <button 
              onClick={() => { setMethodFilter('POS'); setTypeFilter('all'); }}
              className={`p-5 rounded-2xl border text-left transition-all cursor-pointer ${
                methodFilter === 'POS'
                  ? 'bg-card-theme-bg border-indigo-500 ring-2 ring-indigo-100 shadow-sm'
                  : 'bg-card-theme-bg border-slate-200/80 shadow-2xs hover:border-indigo-200'
              }`}
            >
              <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Total POS</span>
                <span className="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                  <CreditCard size={18} />
                </span>
              </div>
              <div className="text-2xl font-extrabold text-slate-900 font-mono tracking-tight">
                {settings.currency}{financialMetrics.totalPos.toLocaleString()}
              </div>
              <p className="text-xs text-slate-500 mt-1">
                <span className="font-bold text-slate-700">{financialMetrics.posCount}</span> POS / digital payment(s)
              </p>
            </button>

            <button 
              onClick={() => { setTypeFilter('refund'); setMethodFilter('all'); }}
              className={`p-5 rounded-2xl border text-left transition-all cursor-pointer ${
                typeFilter === 'refund'
                  ? 'bg-card-theme-bg border-rose-500 ring-2 ring-rose-100 shadow-sm'
                  : 'bg-card-theme-bg border-slate-200/80 shadow-2xs hover:border-rose-200'
              }`}
            >
              <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Total Refunds</span>
                <span className="p-2 bg-rose-50 text-rose-600 rounded-xl">
                  <RotateCcw size={18} />
                </span>
              </div>
              <div className="text-2xl font-extrabold text-rose-600 font-mono tracking-tight">
                -{settings.currency}{financialMetrics.totalRefunds.toLocaleString()}
              </div>
              <p className="text-xs text-slate-500 mt-1">
                <span className="font-bold text-slate-700">{financialMetrics.refundsCount}</span> refund(s)
              </p>
            </button>

            <button 
              onClick={() => { setMethodFilter('all'); setTypeFilter('all'); }}
              className={`p-5 rounded-2xl border text-left transition-all cursor-pointer ${
                methodFilter === 'all' && typeFilter === 'all'
                  ? 'bg-card-theme-bg border-teal-500 ring-2 ring-teal-100 shadow-sm'
                  : 'bg-card-theme-bg border-slate-200/80 shadow-2xs hover:border-teal-200'
              }`}
            >
              <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Net Balance</span>
                <span className="p-2 bg-teal-50 text-teal-600 rounded-xl">
                  <Wallet size={18} />
                </span>
              </div>
              <div className="text-2xl font-extrabold text-slate-900 font-mono tracking-tight">
                {settings.currency}{financialMetrics.netBalance.toLocaleString()}
              </div>
              <p className="text-xs text-slate-500 mt-1">
                Net (Collected - Refunds)
              </p>
            </button>
          </div>

          {/* Filters & Actions Bar */}
          <div className="bg-card-theme-bg p-5 rounded-2xl border border-slate-200/80 shadow-2xs space-y-4">
            <div className="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
              {/* Search Bar */}
              <div className="relative flex-1">
                <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                <input 
                  type="text"
                  placeholder="Search customer, Sale ID, ref, method or cashier..."
                  className="w-full pl-10 pr-4 py-2.5 bg-layout-theme-bg border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-theme text-sm"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                />
                {search && (
                  <button 
                    onClick={() => setSearch('')}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  >
                    <X size={16} />
                  </button>
                )}
              </div>

              {/* Action Buttons */}
              <div className="flex items-center gap-3">
                <button
                  onClick={handleExportCSV}
                  className="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-xs transition-all cursor-pointer"
                  title="Export Payment Ledger to CSV"
                >
                  <Download size={15} />
                  <span>Export CSV Ledger</span>
                </button>
              </div>
            </div>

            {/* Filter Dropdowns */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2 border-t border-slate-100">
              {/* Type Filter */}
              <div>
                <label className="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Transaction Type</label>
                <select
                  value={typeFilter}
                  onChange={(e) => setTypeFilter(e.target.value as any)}
                  className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary-theme"
                >
                  <option value="all">All Types (Full, Part & Refunds)</option>
                  <option value="full_payment">Full Payments Only</option>
                  <option value="part_payment">Part / Installments Only</option>
                  <option value="refund">Refunds & Returns Only</option>
                </select>
              </div>

              {/* Method Filter */}
              <div>
                <label className="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Payment Method</label>
                <select
                  value={methodFilter}
                  onChange={(e) => setMethodFilter(e.target.value)}
                  className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary-theme"
                >
                  <option value="all">All Methods</option>
                  <option value="cash">Cash</option>
                  <option value="pos">POS</option>
                  <option value="refund">Refund</option>
                </select>
              </div>

              {/* Date Filter */}
              <div>
                <label className="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Date Range</label>
                <select
                  value={dateFilter}
                  onChange={(e) => setDateFilter(e.target.value as DateFilterOption)}
                  className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary-theme"
                >
                  <option value="today">Today (Default)</option>
                  <option value="yesterday">Yesterday</option>
                  <option value="thisWeek">This Week</option>
                  <option value="last7days">Last 7 Days</option>
                  <option value="thisMonth">This Month</option>
                  <option value="last30days">Last 30 Days</option>
                  <option value="lastMonth">Last Month</option>
                  <option value="thisYear">This Year</option>
                  <option value="custom">Custom Range</option>
                  <option value="all">All Time</option>
                </select>
              </div>

              {/* Reset Filters button */}
              <div className="flex items-end">
                <button
                  onClick={() => {
                    setSearch('');
                    setTypeFilter('all');
                    setMethodFilter('all');
                    setDateFilter('today');
                    setStartDate('');
                    setEndDate('');
                  }}
                  className="w-full py-2 px-3 text-xs font-bold text-slate-500 hover:text-slate-900 bg-layout-theme-bg hover:bg-slate-200/60 rounded-xl border border-slate-200 transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                >
                  <RotateCcw size={14} />
                  <span>Reset Filters</span>
                </button>
              </div>
            </div>

            {/* Custom Date Range Inputs */}
            {dateFilter === 'custom' && (
              <div className="flex items-center gap-4 pt-2 border-t border-slate-100">
                <div className="flex-1">
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Start Date</label>
                  <input
                    type="date"
                    value={startDate}
                    onChange={(e) => setStartDate(e.target.value)}
                    className="w-full px-3 py-1.5 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs"
                  />
                </div>
                <div className="flex-1">
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">End Date</label>
                  <input
                    type="date"
                    value={endDate}
                    onChange={(e) => setEndDate(e.target.value)}
                    className="w-full px-3 py-1.5 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs"
                  />
                </div>
              </div>
            )}
          </div>

          {/* Table View */}
          <div className="bg-card-theme-bg rounded-2xl border border-slate-200 overflow-hidden shadow-2xs">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                    <th className="py-3.5 px-5">Date & Time</th>
                    <th className="py-3.5 px-4">Ref / Transaction ID</th>
                    <th className="py-3.5 px-4">Type</th>
                    <th className="py-3.5 px-4">Customer & Sale</th>
                    <th className="py-3.5 px-4">Method</th>
                    <th className="py-3.5 px-4 text-right">Amount</th>
                    <th className="py-3.5 px-4">Cashier / Staff</th>
                    <th className="py-3.5 px-5 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-sm">
                  {filteredLedgerEntries.length === 0 ? (
                    <tr>
                      <td colSpan={8} className="py-12 text-center text-slate-500 italic">
                        No payment records matching your filter criteria.
                      </td>
                    </tr>
                  ) : (
                    filteredLedgerEntries.map((entry) => {
                      const isRefund = entry.type === 'refund';
                      const isFull = entry.type === 'full_payment';

                      return (
                        <tr key={entry.id} className="hover:bg-slate-50/80 transition-colors">
                          {/* Date & Time */}
                          <td className="py-3.5 px-5 font-mono text-xs text-slate-600 whitespace-nowrap">
                            {new Date(entry.timestamp).toLocaleString(undefined, {
                              year: 'numeric',
                              month: 'short',
                              day: '2-digit',
                              hour: '2-digit',
                              minute: '2-digit'
                            })}
                          </td>

                          {/* Ref / ID */}
                          <td className="py-3.5 px-4 font-mono text-xs font-bold text-slate-800 whitespace-nowrap">
                            {entry.transactionRef}
                          </td>

                          {/* Type Badge */}
                          <td className="py-3.5 px-4 whitespace-nowrap">
                            {isRefund ? (
                              <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                                <RotateCcw size={12} />
                                Refund
                              </span>
                            ) : isFull ? (
                              <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <CheckCircle2 size={12} />
                                Full Payment
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200">
                                <Coins size={12} />
                                Part / Installment
                              </span>
                            )}
                          </td>

                          {/* Customer & Sale ID */}
                          <td className="py-3.5 px-4">
                            <div className="font-bold text-slate-900 leading-snug">
                              {entry.customerName}
                            </div>
                            <div className="text-xs text-slate-500 font-mono">
                              Sale #{entry.saleId}
                              {entry.itemsCount ? ` (${entry.itemsCount} item${entry.itemsCount > 1 ? 's' : ''})` : ''}
                            </div>
                          </td>

                          {/* Method */}
                          <td className="py-3.5 px-4">
                            <span className="inline-block px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-xs uppercase tracking-wider">
                              {entry.method}
                            </span>
                          </td>

                          {/* Amount */}
                          <td className="py-3.5 px-4 text-right font-mono font-bold whitespace-nowrap">
                            <span className={isRefund ? 'text-rose-600' : 'text-emerald-700'}>
                              {isRefund ? '-' : '+'}{settings.currency}{entry.amount.toLocaleString()}
                            </span>
                          </td>

                          {/* Recorded By */}
                          <td className="py-3.5 px-4 text-xs text-slate-600 whitespace-nowrap">
                            <div className="flex items-center gap-1.5">
                              <UserCheck size={14} className="text-slate-400" />
                              <span>{entry.recordedByName || 'Staff'}</span>
                            </div>
                          </td>

                          {/* Actions */}
                          <td className="py-3.5 px-5 text-right whitespace-nowrap">
                            <div className="flex items-center justify-end gap-2">
                              {entry.sale && (
                                <>
                                  <button
                                    onClick={() => setReceiptSale(entry.sale!)}
                                    className="p-1.5 text-primary-theme hover:bg-primary-theme-light rounded-lg transition-colors cursor-pointer"
                                    title="Print / Export Receipt"
                                  >
                                    <Printer size={16} />
                                  </button>
                                  <button
                                    onClick={() => setDetailSale(entry.sale!)}
                                    className="p-1.5 text-slate-600 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer"
                                    title="View Full Sale Breakdown"
                                  >
                                    <Eye size={16} />
                                  </button>
                                </>
                              )}
                            </div>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* SUB-TAB 2: PENDING COLLECTIONS & DEBTORS */}
      {activeSubTab === 'pending' && (
        <div className="space-y-6">
          <div className="bg-amber-50 border border-amber-200 p-5 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h3 className="text-lg font-bold text-amber-900">Outstanding Installment Balances</h3>
              <p className="text-xs text-amber-700 mt-0.5">
                Manage accounts receivable and collect pending balances for installment orders
              </p>
            </div>
            <div className="text-right">
              <span className="text-xs uppercase font-bold text-amber-700 block">Total Uncollected Debt</span>
              <span className="text-2xl font-extrabold text-amber-900 font-mono">
                {settings.currency}{totalOutstandingBalance.toLocaleString()}
              </span>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {installmentSales.length === 0 ? (
              <div className="col-span-full p-12 text-center bg-card-theme-bg rounded-2xl border border-slate-200 text-slate-500 italic">
                All sales are fully settled! No pending installment collections.
              </div>
            ) : (
              installmentSales.map((sale) => {
                const balance = getSaleBalance(sale);
                const paid = sale.totalAmount - balance;
                const progressPercent = Math.min(100, Math.round((paid / sale.totalAmount) * 100));

                return (
                  <div 
                    key={sale.id} 
                    className="bg-card-theme-bg p-5 rounded-2xl border border-slate-200/80 shadow-2xs hover:border-primary-theme/50 transition-all flex flex-col justify-between"
                  >
                    <div>
                      <div className="flex items-center justify-between mb-2">
                        <span className="text-xs font-mono font-bold text-slate-400">Order #{sale.id}</span>
                        <span className="px-2 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-bold uppercase rounded-full">
                          Installment
                        </span>
                      </div>

                      <h4 className="font-bold text-slate-900 text-base mb-1">{sale.customerName}</h4>
                      <p className="text-xs text-slate-500 mb-4">{sale.items.length} item(s) purchased</p>

                      {/* Payment Progress Bar */}
                      <div className="space-y-1.5 mb-4">
                        <div className="flex justify-between text-xs font-mono font-bold">
                          <span className="text-slate-500">Paid: {settings.currency}{paid.toLocaleString()}</span>
                          <span className="text-rose-600">Balance: {settings.currency}{balance.toLocaleString()}</span>
                        </div>
                        <div className="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                          <div 
                            className="bg-primary-theme h-full transition-all duration-500" 
                            style={{ width: `${progressPercent}%` }}
                          />
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 pt-3 border-t border-slate-100">
                      <button
                        onClick={() => setDetailSale(sale)}
                        className="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors cursor-pointer"
                      >
                        View Items
                      </button>
                      {canCreate && (
                        <button
                          onClick={() => {
                            setSelectedSaleForPayment(sale);
                            setPaymentModalAmount(balance);
                          }}
                          className="flex-[2] py-2 bg-primary-theme hover:bg-primary-theme-hover text-white font-bold text-xs rounded-xl shadow-xs transition-colors cursor-pointer"
                        >
                          Record Payment
                        </button>
                      )}
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </div>
      )}

      {/* RECORD INSTALLMENT PAYMENT MODAL */}
      {selectedSaleForPayment && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-fade-in">
          <div className="bg-card-theme-bg rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl overflow-y-auto max-h-[90vh] border border-slate-200">
            <div className="flex items-center justify-between mb-6">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-primary-theme-light rounded-2xl flex items-center justify-center text-primary-theme">
                  <CreditCard size={20} />
                </div>
                <div>
                  <h3 className="text-lg font-extrabold text-slate-900">Record Payment</h3>
                  <p className="text-xs text-slate-500">{selectedSaleForPayment.customerName}</p>
                </div>
              </div>
              <button 
                onClick={() => setSelectedSaleForPayment(null)}
                className="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100"
              >
                <X size={18} />
              </button>
            </div>

            <div className="space-y-4">
              <div className="p-3 bg-slate-50 rounded-xl text-xs space-y-1">
                <div className="flex justify-between">
                  <span className="text-slate-500">Order Total:</span>
                  <span className="font-mono font-bold text-slate-800">{settings.currency}{selectedSaleForPayment.totalAmount.toLocaleString()}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Remaining Balance:</span>
                  <span className="font-mono font-bold text-rose-600">{settings.currency}{getSaleBalance(selectedSaleForPayment).toLocaleString()}</span>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Payment Amount</label>
                {(() => {
                  const balance = getSaleBalance(selectedSaleForPayment);
                  const isExceeded = paymentModalAmount > balance + 0.01;
                  const isZeroOrNegative = paymentModalAmount <= 0;
                  return (
                    <>
                      <div className="relative">
                        <span className="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-400">{settings.currency}</span>
                        <input 
                          type="number"
                          step="any"
                          min="0.01"
                          max={balance}
                          className={`w-full pl-8 pr-4 py-3 border rounded-xl focus:ring-2 focus:ring-primary-theme focus:outline-none font-bold text-lg ${
                            isExceeded || isZeroOrNegative
                              ? 'border-rose-300 bg-rose-50 text-rose-600'
                              : 'bg-layout-theme-bg border-slate-200'
                          }`}
                          value={paymentModalAmount || ''}
                          onChange={(e) => setPaymentModalAmount(e.target.value === '' ? 0 : parseFloat(e.target.value))}
                        />
                      </div>
                      {isExceeded && (
                        <p className="text-[10px] text-rose-600 font-bold mt-1 uppercase">Cannot exceed remaining balance ({settings.currency}{balance.toLocaleString()})</p>
                      )}
                      {isZeroOrNegative && (
                        <p className="text-[10px] text-rose-600 font-bold mt-1 uppercase">Payment amount must be greater than 0</p>
                      )}
                    </>
                  );
                })()}
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Payment Method</label>
                <select 
                  className="w-full px-4 py-3 bg-layout-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:outline-none text-sm font-medium"
                  value={paymentModalMethod}
                  onChange={(e) => setPaymentModalMethod(e.target.value)}
                >
                  <option>Cash</option>
                  <option>POS</option>
                </select>
              </div>

              <div className="flex gap-3 pt-4">
                <button 
                  onClick={() => setSelectedSaleForPayment(null)}
                  className="flex-1 py-3 text-slate-600 font-bold hover:bg-layout-theme-bg rounded-xl text-sm transition-colors"
                >
                  Cancel
                </button>
                <button 
                  onClick={handleAddPayment}
                  disabled={(() => {
                    const balance = getSaleBalance(selectedSaleForPayment);
                    return paymentModalAmount <= 0 || paymentModalAmount > balance + 0.01;
                  })()}
                  className="flex-[2] bg-primary-theme text-white py-3 rounded-xl font-bold text-sm shadow-md hover:bg-primary-theme-hover transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Confirm Payment
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* FULL SALE DETAIL BREAKDOWN MODAL */}
      {detailSale && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-fade-in">
          <div className="bg-card-theme-bg rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl overflow-y-auto max-h-[90vh] border border-slate-200">
            <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-700">
                  <ShoppingBag size={20} />
                </div>
                <div>
                  <h3 className="text-lg font-extrabold text-slate-900">Sale Breakdown</h3>
                  <p className="text-xs text-slate-500">Order #{detailSale.id} • {detailSale.customerName}</p>
                </div>
              </div>
              <button 
                onClick={() => setDetailSale(null)}
                className="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100"
              >
                <X size={18} />
              </button>
            </div>

            <div className="space-y-5">
              {/* Items List */}
              <div>
                <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Purchased Items</h4>
                <div className="bg-layout-theme-bg rounded-xl border border-slate-200 divide-y divide-slate-200/60 overflow-hidden text-xs">
                  {detailSale.items.map((item, idx) => (
                    <div key={idx} className="p-3 flex items-center justify-between">
                      <div>
                        <div className="font-bold text-slate-900">{item.productName}</div>
                        <div className="text-slate-500">{item.quantity} x {settings.currency}{item.unitPrice.toLocaleString()}</div>
                      </div>
                      <div className="font-mono font-bold text-slate-800">
                        {settings.currency}{item.totalPrice.toLocaleString()}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Total & Paid Breakdown */}
              <div className="p-4 bg-slate-50 rounded-xl space-y-2 text-xs">
                <div className="flex justify-between font-bold text-slate-700">
                  <span>Grand Total:</span>
                  <span className="font-mono text-sm">{settings.currency}{detailSale.totalAmount.toLocaleString()}</span>
                </div>
                <div className="flex justify-between font-bold text-emerald-700">
                  <span>Total Settled:</span>
                  <span className="font-mono">{settings.currency}{(detailSale.totalAmount - getSaleBalance(detailSale)).toLocaleString()}</span>
                </div>
                <div className="flex justify-between font-bold text-rose-600">
                  <span>Outstanding Balance:</span>
                  <span className="font-mono">{settings.currency}{getSaleBalance(detailSale).toLocaleString()}</span>
                </div>
              </div>

              {/* Payment History for this specific Sale */}
              <div>
                <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Payments Log for this Order</h4>
                <div className="bg-layout-theme-bg rounded-xl border border-slate-200 divide-y divide-slate-100 text-xs">
                  {payments.filter(p => p.saleId === detailSale.id).length === 0 ? (
                    <div className="p-3 text-slate-400 italic">No installment logs recorded yet.</div>
                  ) : (
                    payments.filter(p => p.saleId === detailSale.id).map(p => (
                      <div key={p.id} className="p-3 flex items-center justify-between">
                        <div>
                          <div className="font-bold text-slate-800">{p.method} Payment</div>
                          <div className="text-[10px] text-slate-400 font-mono">{new Date(p.timestamp).toLocaleString()}</div>
                        </div>
                        <div className="font-mono font-bold text-emerald-700">
                          +{settings.currency}{p.amount.toLocaleString()}
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  onClick={() => {
                    setReceiptSale(detailSale);
                    setDetailSale(null);
                  }}
                  className="w-full py-2.5 bg-primary-theme text-white font-bold text-xs rounded-xl shadow-xs hover:bg-primary-theme-hover transition-colors flex items-center justify-center gap-2 cursor-pointer"
                >
                  <Printer size={16} />
                  <span>Print Receipt</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* RECEIPT MODAL */}
      <ReceiptModal 
        sale={receiptSale} 
        onClose={() => setReceiptSale(null)} 
        settings={settings} 
      />
    </div>
  );
}
