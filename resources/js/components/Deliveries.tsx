/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useMemo, useEffect } from 'react';
import { Truck, Package, CheckCircle2, Search, MapPin, Clock, AlertTriangle, X, Printer, Download, Calendar, ArrowUpDown, RotateCcw } from 'lucide-react';
import { storage } from '../lib/storage';
import { DateFilterOption, matchesDateFilter } from '../lib/dateUtils';
import { Sale, User, SalesReturn } from '../types';
import { hasModulePermission } from '../lib/rbac';
import ReceiptModal from './ReceiptModal';

interface DeliveriesProps {
  user: User;
}

export default function Deliveries({ user }: DeliveriesProps) {
  const settings = storage.getSettings();
  const [sales, setSales] = useState<Sale[]>(storage.getSales());
  const [returns, setReturns] = useState<SalesReturn[]>(storage.getReturns());

  useEffect(() => {
    const refreshData = () => {
      setSales(storage.getSales());
      setReturns(storage.getReturns());
    };
    window.addEventListener('hysam-data-updated', refreshData);
    window.addEventListener('hysam-sync-end', refreshData);
    return () => {
      window.removeEventListener('hysam-data-updated', refreshData);
      window.removeEventListener('hysam-sync-end', refreshData);
    };
  }, []);
  const [search, setSearch] = useState('');
  const [confirmingSaleId, setConfirmingSaleId] = useState<string | null>(null);
  const [receiptSale, setReceiptSale] = useState<Sale | null>(null);

  // Pending Deliveries Date Filter State
  const [pendingDateFilter, setPendingDateFilter] = useState<DateFilterOption>('today');
  const [pendingStartDate, setPendingStartDate] = useState<string>('');
  const [pendingEndDate, setPendingEndDate] = useState<string>('');

  // Pending Deliveries Modal State
  const [showPendingModal, setShowPendingModal] = useState(false);
  const [pendingModalSearch, setPendingModalSearch] = useState('');
  const [pendingModalDateFilter, setPendingModalDateFilter] = useState<DateFilterOption>('today');
  const [pendingModalStartDate, setPendingModalStartDate] = useState<string>('');
  const [pendingModalEndDate, setPendingModalEndDate] = useState<string>('');
  const [pendingModalSortBy, setPendingModalSortBy] = useState<'newest' | 'oldest' | 'amount_high'>('newest');

  // Completed Deliveries Modal State
  const [showCompletedModal, setShowCompletedModal] = useState(false);
  const [modalSearch, setModalSearch] = useState('');
  const [dateFilter, setDateFilter] = useState<DateFilterOption>('today');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');
  const [sortBy, setSortBy] = useState<'newest' | 'oldest' | 'amount_high'>('newest');
  const [modalReturnFilter, setModalReturnFilter] = useState<'all' | 'delivered' | 'returned'>('all');

  const canEdit = hasModulePermission(user, 'deliveries', 'edit');

  // Set of Sale IDs that have been returned
  const returnedSaleIds = useMemo(() => {
    const ids = new Set<string>();
    returns.forEach(r => {
      if (r.saleId) ids.add(r.saleId);
    });
    sales.forEach(s => {
      if (s.status === 'returned') ids.add(s.id);
    });
    return ids;
  }, [sales, returns]);

  const pendingDeliveries = useMemo(() => {
    return sales.filter(s => {
      if (s.deliveryStatus !== 'pending' || s.status === 'returned') return false;
      const q = search.toLowerCase().trim();
      if (q) {
        const matchesSearch = s.customerName.toLowerCase().includes(q) ||
          s.id.toLowerCase().includes(q) ||
          (s.userName && s.userName.toLowerCase().includes(q)) ||
          s.items.some(i => i.productName.toLowerCase().includes(q) || (i as any).productCode?.toLowerCase().includes(q));
        if (!matchesSearch) return false;
      }
      return matchesDateFilter(s.createdAt, pendingDateFilter, pendingStartDate, pendingEndDate);
    });
  }, [sales, search, pendingDateFilter, pendingStartDate, pendingEndDate]);

  const completedSales = useMemo(() => {
    return sales.filter(s => s.deliveryStatus === 'delivered');
  }, [sales]);

  const returnedCompletedSalesCount = useMemo(() => {
    return sales.filter(s => s.deliveryStatus === 'delivered' && returnedSaleIds.has(s.id)).length;
  }, [sales, returnedSaleIds]);

  const activeCompletedSalesCount = useMemo(() => {
    return completedSales.length - returnedCompletedSalesCount;
  }, [completedSales, returnedCompletedSalesCount]);

  const filteredCompletedDeliveries = useMemo(() => {
    let result = sales.filter(s => s.deliveryStatus === 'delivered');

    if (modalReturnFilter === 'delivered') {
      result = result.filter(s => !returnedSaleIds.has(s.id));
    } else if (modalReturnFilter === 'returned') {
      result = result.filter(s => returnedSaleIds.has(s.id));
    }

    if (modalSearch.trim()) {
      const q = modalSearch.toLowerCase().trim();
      result = result.filter(s => 
        s.customerName.toLowerCase().includes(q) ||
        s.id.toLowerCase().includes(q) ||
        (s.deliveredBy && s.deliveredBy.toLowerCase().includes(q)) ||
        s.items.some(i => i.productName.toLowerCase().includes(q))
      );
    }

    result = result.filter(s => matchesDateFilter(s.deliveredAt || s.createdAt, dateFilter, startDate, endDate));

    result.sort((a, b) => {
      if (sortBy === 'oldest') {
        return new Date(a.deliveredAt || a.createdAt).getTime() - new Date(b.deliveredAt || b.createdAt).getTime();
      }
      if (sortBy === 'amount_high') {
        return b.totalAmount - a.totalAmount;
      }
      return new Date(b.deliveredAt || b.createdAt).getTime() - new Date(a.deliveredAt || a.createdAt).getTime();
    });

    return result;
  }, [sales, modalSearch, dateFilter, startDate, endDate, sortBy, modalReturnFilter, returnedSaleIds]);

  const filteredPendingDeliveriesModal = useMemo(() => {
    let result = sales.filter(s => s.deliveryStatus === 'pending' && s.status !== 'returned');

    if (pendingModalSearch) {
      const q = pendingModalSearch.toLowerCase();
      result = result.filter(s =>
        s.customerName.toLowerCase().includes(q) ||
        s.id.toLowerCase().includes(q) ||
        (s.userName && s.userName.toLowerCase().includes(q)) ||
        s.items.some(i => i.productName.toLowerCase().includes(q) || (i as any).productCode?.toLowerCase().includes(q))
      );
    }

    result = result.filter(s => matchesDateFilter(s.createdAt, pendingModalDateFilter, pendingModalStartDate, pendingModalEndDate));

    result.sort((a, b) => {
      if (pendingModalSortBy === 'oldest') {
        return new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime();
      } else if (pendingModalSortBy === 'amount_high') {
        return b.totalAmount - a.totalAmount;
      }
      return new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime();
    });

    return result;
  }, [sales, pendingModalSearch, pendingModalDateFilter, pendingModalStartDate, pendingModalEndDate, pendingModalSortBy]);

  const handleExportPendingCSV = () => {
    const headers = ['Order ID', 'Customer Name', 'Items Summary', `Total Amount (${settings.currency})`, `Paid Amount (${settings.currency})`, 'Order Date', 'Status', 'Served By'];
    const rows = filteredPendingDeliveriesModal.map(s => {
      const itemsSummary = s.items.map(i => `${i.quantity}x ${i.productName}`).join('; ');
      const orderDate = s.createdAt ? new Date(s.createdAt).toLocaleString() : '';
      const paid = s.paidAmount || 0;

      return [
        `"${s.id.substring(0, 8).toUpperCase()}"`,
        `"${s.customerName.replace(/"/g, '""')}"`,
        `"${itemsSummary.replace(/"/g, '""')}"`,
        s.totalAmount.toFixed(2),
        paid.toFixed(2),
        `"${orderDate}"`,
        `"${s.status}"`,
        `"${(s.userName || 'N/A').replace(/"/g, '""')}"`
      ].join(',');
    });

    const csvContent = [headers.join(','), ...rows].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `pending_deliveries_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleExportCSV = () => {
    const headers = ['Order ID', 'Customer Name', 'Items Summary', `Total Amount (${settings.currency})`, `Paid Amount (${settings.currency})`, 'Order Date', 'Delivered Date', 'Delivered By', 'Return Status', 'Return Details'];
    
    const rows = filteredCompletedDeliveries.map(s => {
      const itemsSummary = s.items.map(i => `${i.quantity}x ${i.productName}`).join('; ');
      const orderDate = s.createdAt ? new Date(s.createdAt).toLocaleString() : '';
      const deliveredDate = s.deliveredAt ? new Date(s.deliveredAt).toLocaleString() : '';
      const isRet = returnedSaleIds.has(s.id);
      const saleRets = returns.filter(r => r.saleId === s.id);
      const returnStatus = isRet ? 'Returned' : 'Delivered (Active)';
      const returnDetails = saleRets.length > 0 
        ? saleRets.map(r => `${r.productName} (x${r.quantity}) - ${r.reason || 'Returned'}`).join('; ')
        : (s.returnReason || 'N/A');
      
      return [
        `"${s.id}"`,
        `"${s.customerName.replace(/"/g, '""')}"`,
        `"${itemsSummary.replace(/"/g, '""')}"`,
        s.totalAmount.toFixed(2),
        s.paidAmount.toFixed(2),
        `"${orderDate}"`,
        `"${deliveredDate}"`,
        `"${(s.deliveredBy || 'N/A').replace(/"/g, '""')}"`,
        `"${returnStatus}"`,
        `"${returnDetails.replace(/"/g, '""')}"`
      ].join(',');
    });

    const csvContent = [headers.join(','), ...rows].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `Completed_Deliveries_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const confirmingSale = useMemo(() => {
    if (!confirmingSaleId) return null;
    return sales.find(s => s.id === confirmingSaleId) || null;
  }, [sales, confirmingSaleId]);

  const handleDeliver = (saleId: string) => {
    const saleToDeliver = sales.find(s => s.id === saleId);
    if (!saleToDeliver) return;

    // Deduct stock upon delivery
    const currentProducts = storage.getProducts();
    const updatedProducts = currentProducts.map(p => {
      const item = saleToDeliver.items.find(ci => ci.productId === p.id);
      if (item) return { ...p, currentStock: p.currentStock - item.quantity };
      return p;
    });
    storage.saveProducts(updatedProducts);
    storage.logActivity({
      type: 'delivery',
      description: `Order #${saleId} marked as delivered to ${saleToDeliver.customerName}`,
      userId: user.id,
      userName: user.name
    });

    const updatedSales = sales.map(s => 
      s.id === saleId 
        ? { 
            ...s, 
            deliveryStatus: 'delivered' as const,
            deliveredAt: new Date().toISOString(),
            deliveredBy: user.name
          } 
        : s
    );
    storage.saveSales(updatedSales);
    setSales(updatedSales);
    setConfirmingSaleId(null);
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900">Pending Deliveries</h2>
          <p className="text-slate-500">Manage and track product shipments to customers</p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          {/* Pending Deliveries Date Dropdown Filter */}
          <div className="flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl px-3 py-2 shadow-2xs text-xs">
            <Calendar size={14} className="text-primary-theme shrink-0" />
            <span className="text-xs font-bold text-slate-400 uppercase tracking-wider shrink-0">Period:</span>
            <select
              value={pendingDateFilter}
              onChange={(e) => setPendingDateFilter(e.target.value as DateFilterOption)}
              className="text-xs font-bold text-slate-800 bg-transparent border-none focus:outline-none cursor-pointer py-0.5"
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

          {pendingDateFilter === 'custom' && (
            <div className="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-2xs text-xs">
              <span className="text-[10px] font-bold text-slate-400 uppercase">From:</span>
              <input
                type="date"
                value={pendingStartDate}
                onChange={(e) => setPendingStartDate(e.target.value)}
                className="text-xs font-medium text-slate-800 bg-transparent outline-none focus:text-primary-theme"
              />
              <span className="text-[10px] font-bold text-slate-400 uppercase ml-1">To:</span>
              <input
                type="date"
                value={pendingEndDate}
                onChange={(e) => setPendingEndDate(e.target.value)}
                className="text-xs font-medium text-slate-800 bg-transparent outline-none focus:text-primary-theme"
              />
            </div>
          )}

          <button
            type="button"
            onClick={() => { setModalReturnFilter('all'); setShowCompletedModal(true); }}
            className="px-3.5 py-2 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-2xs"
            id="completed-deliveries-header-btn"
          >
            <CheckCircle2 size={16} className="text-emerald-600" />
            <span>Completed ({completedSales.length})</span>
          </button>
          <button
            type="button"
            onClick={() => { setModalReturnFilter('returned'); setShowCompletedModal(true); }}
            className="px-3.5 py-2 bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-2xs"
            id="returned-deliveries-header-btn"
          >
            <RotateCcw size={15} className="text-purple-600" />
            <span>Returned ({returnedCompletedSalesCount})</span>
          </button>
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
            <input 
              type="text"
              placeholder="Search delivery..."
              className="pl-10 pr-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-theme w-full md:w-64"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
        </div>
      </div>

      <div className="bg-card-theme-bg rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        {pendingDeliveries.length === 0 ? (
          <div className="py-16 text-center">
            <Truck size={48} className="mx-auto text-slate-200 mb-4" />
            <p className="text-slate-400 font-medium">No deliveries pending at the moment</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead className="bg-slate-50/80 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                <tr>
                  <th className="px-5 py-4">Order ID & Date</th>
                  <th className="px-5 py-4">Customer Name</th>
                  <th className="px-5 py-4">Items Summary</th>
                  <th className="px-5 py-4 text-right">Total Amount</th>
                  <th className="px-5 py-4 text-center">Payment Status</th>
                  <th className="px-5 py-4 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {pendingDeliveries.map((sale) => (
                  <tr key={sale.id} className="hover:bg-slate-50/60 transition-colors">
                    <td className="px-5 py-4 align-middle">
                      <div className="font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded text-[11px] inline-block mb-1">
                        #{sale.id.substring(0, 8).toUpperCase()}
                      </div>
                      <div className="text-[11px] text-slate-400 flex items-center gap-1">
                        <Clock size={12} />
                        {new Date(sale.createdAt).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' })}
                      </div>
                    </td>

                    <td className="px-5 py-4 align-middle">
                      <div className="font-bold text-slate-900 text-sm">{sale.customerName}</div>
                      <div className="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                        <MapPin size={12} className="text-primary-theme shrink-0" />
                        <span>Standard Address</span>
                      </div>
                    </td>

                    <td className="px-5 py-4 align-middle max-w-xs">
                      <div className="space-y-1">
                        {sale.items.map((item, i) => (
                          <div key={i} className="flex items-center gap-1.5 text-slate-700 text-xs">
                            <Package size={13} className="text-slate-400 shrink-0" />
                            <span className="font-semibold text-slate-900">{item.quantity}x</span>
                            <span className="truncate">{item.productName}</span>
                          </div>
                        ))}
                      </div>
                    </td>

                    <td className="px-5 py-4 text-right align-middle font-mono font-bold text-slate-900 text-sm">
                      {settings.currency}{sale.totalAmount.toLocaleString()}
                    </td>

                    <td className="px-5 py-4 text-center align-middle">
                      <span className={`inline-block px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                        sale.status === 'installment'
                          ? 'bg-amber-100 text-amber-800'
                          : 'bg-emerald-100 text-emerald-800'
                      }`}>
                        {sale.status === 'installment' ? 'Installment' : 'Paid'}
                      </span>
                    </td>

                    <td className="px-5 py-4 text-right align-middle">
                      <div className="flex items-center justify-end gap-2">
                        <button 
                          onClick={() => setReceiptSale(sale)}
                          className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                          title="Print Delivery Receipt / Slip"
                        >
                          <Printer size={14} />
                          <span>Slip</span>
                        </button>

                        {canEdit && (
                          <button 
                            onClick={() => setConfirmingSaleId(sale.id)}
                            className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer shadow-2xs"
                            id={`mark-delivered-btn-${sale.id}`}
                          >
                            <CheckCircle2 size={14} />
                            <span>Delivered</span>
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <div className="mt-12">
        <h3 className="text-lg font-bold text-slate-900 mb-4">Delivery Performance</h3>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <button 
            type="button"
            onClick={() => { setModalReturnFilter('all'); setShowCompletedModal(true); }}
            id="view-completed-deliveries-card"
            className="bg-accent-theme-light p-5 rounded-2xl border border-accent-theme-light text-left hover:shadow-md transition-all cursor-pointer group relative overflow-hidden"
          >
            <div className="flex justify-between items-start mb-1">
              <div className="text-accent-theme font-bold text-2xl">
                {completedSales.length}
              </div>
              <span className="text-[10px] font-bold uppercase tracking-wider bg-accent-theme/10 text-accent-theme px-2 py-0.5 rounded-full group-hover:bg-accent-theme group-hover:text-white transition-colors">
                View List →
              </span>
            </div>
            <div className="text-accent-theme-hover text-sm font-semibold">Completed Deliveries</div>
            <p className="text-[11px] text-slate-500 mt-1">Click to filter, view details or export CSV</p>
          </button>
          <button 
            type="button"
            onClick={() => setShowPendingModal(true)}
            id="view-pending-deliveries-card"
            className="bg-amber-50 p-5 rounded-2xl border border-amber-100 text-left hover:shadow-md transition-all cursor-pointer group relative overflow-hidden"
          >
            <div className="flex justify-between items-start mb-1">
              <div className="text-amber-600 font-bold text-2xl">
                {pendingDeliveries.length}
              </div>
              <span className="text-[10px] font-bold uppercase tracking-wider bg-amber-600/10 text-amber-700 px-2 py-0.5 rounded-full group-hover:bg-amber-600 group-hover:text-white transition-colors">
                View List →
              </span>
            </div>
            <div className="text-amber-700 text-sm font-semibold">Pending Shipments</div>
            <p className="text-[11px] text-amber-600/70 mt-1">Click to filter, view details or export CSV</p>
          </button>
          <button 
            type="button"
            onClick={() => { setModalReturnFilter('returned'); setShowCompletedModal(true); }}
            id="view-returned-deliveries-card"
            className="bg-purple-50 p-5 rounded-2xl border border-purple-100 text-left hover:shadow-md transition-all cursor-pointer group relative overflow-hidden"
          >
            <div className="flex justify-between items-start mb-1">
              <div className="text-purple-700 font-bold text-2xl">
                {returnedCompletedSalesCount}
              </div>
              <span className="text-[10px] font-bold uppercase tracking-wider bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full group-hover:bg-purple-600 group-hover:text-white transition-colors">
                Returned Tab →
              </span>
            </div>
            <div className="text-purple-900 text-sm font-semibold">Returned Deliveries</div>
            <p className="text-[11px] text-purple-600/70 mt-1">Completed shipments that were returned</p>
          </button>
          <div className="bg-primary-theme-light p-5 rounded-2xl border border-primary-theme-light">
            <div className="text-primary-theme font-bold text-2xl mb-1">
              {Math.round((completedSales.length / (sales.length || 1)) * 100)}%
            </div>
            <div className="text-primary-theme-hover text-sm font-semibold">Fulfillment Rate</div>
            <p className="text-[11px] text-primary-theme/70 mt-1">Ratio of completed vs total orders</p>
          </div>
        </div>
      </div>

      {/* Pending Deliveries Modal */}
      {showPendingModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in" id="pending-deliveries-modal">
          <div className="bg-card-theme-bg rounded-3xl p-6 md:p-8 max-w-5xl w-full shadow-2xl relative overflow-hidden max-h-[90vh] flex flex-col border border-slate-100">
            {/* Modal Header */}
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 shrink-0">
              <div className="flex items-center gap-3">
                <div className="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center shrink-0">
                  <Clock size={24} />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-slate-900">Pending Deliveries</h3>
                  <p className="text-xs text-slate-500">
                    Total {pendingDeliveries.length} pending order{pendingDeliveries.length !== 1 ? 's' : ''} awaiting dispatch
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-2">
                <button
                  onClick={handleExportPendingCSV}
                  disabled={filteredPendingDeliveriesModal.length === 0}
                  className="px-4 py-2.5 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white rounded-xl font-bold text-xs flex items-center gap-2 transition-all cursor-pointer shadow-sm"
                  id="export-pending-deliveries-csv-btn"
                >
                  <Download size={16} />
                  <span>Export CSV ({filteredPendingDeliveriesModal.length})</span>
                </button>
                <button
                  onClick={() => setShowPendingModal(false)}
                  className="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer"
                  id="close-pending-deliveries-modal-btn"
                >
                  <X size={20} />
                </button>
              </div>
            </div>

            {/* Modal Filters */}
            <div className="py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 shrink-0 bg-slate-50/50 -mx-6 px-6">
              <div className="relative flex-1 min-w-[200px]">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
                <input
                  type="text"
                  placeholder="Search customer, order ID, product, staff..."
                  className="pl-9 pr-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 w-full text-xs bg-white"
                  value={pendingModalSearch}
                  onChange={(e) => setPendingModalSearch(e.target.value)}
                  id="pending-deliveries-search-input"
                />
              </div>

              <div className="flex flex-wrap items-center gap-2">
                {/* Date Filter */}
                <div className="flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs shadow-2xs">
                  <Calendar size={14} className="text-amber-600 shrink-0" />
                  <span className="text-xs font-bold text-slate-400 uppercase tracking-wider shrink-0">Period:</span>
                  <select
                    value={pendingModalDateFilter}
                    onChange={(e) => setPendingModalDateFilter(e.target.value as DateFilterOption)}
                    className="bg-transparent font-bold text-slate-800 outline-none pr-2 cursor-pointer text-xs"
                    id="pending-deliveries-date-filter"
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

                {pendingModalDateFilter === 'custom' && (
                  <div className="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-1 shadow-2xs text-xs animate-fadeIn">
                    <span className="text-[10px] font-bold text-slate-400 uppercase">From:</span>
                    <input
                      type="date"
                      value={pendingModalStartDate}
                      onChange={(e) => setPendingModalStartDate(e.target.value)}
                      className="text-xs font-medium text-slate-800 bg-transparent outline-none focus:text-amber-600"
                    />
                    <span className="text-[10px] font-bold text-slate-400 uppercase ml-1">To:</span>
                    <input
                      type="date"
                      value={pendingModalEndDate}
                      onChange={(e) => setPendingModalEndDate(e.target.value)}
                      className="text-xs font-medium text-slate-800 bg-transparent outline-none focus:text-amber-600"
                    />
                  </div>
                )}

                {/* Sort Order */}
                <div className="flex items-center gap-1 bg-white border border-slate-200 rounded-xl p-1 text-xs">
                  <ArrowUpDown size={14} className="text-slate-400 ml-1.5" />
                  <select
                    value={pendingModalSortBy}
                    onChange={(e) => setPendingModalSortBy(e.target.value as any)}
                    className="bg-transparent font-medium text-slate-700 outline-none pr-2 cursor-pointer text-xs"
                    id="pending-deliveries-sort-filter"
                  >
                    <option value="newest">Newest Order</option>
                    <option value="oldest">Oldest Order</option>
                    <option value="amount_high">Highest Amount</option>
                  </select>
                </div>
              </div>
            </div>

            {/* Deliveries List Table / Cards */}
            <div className="overflow-y-auto flex-1 my-4 pr-1 space-y-3">
              {filteredPendingDeliveriesModal.length === 0 ? (
                <div className="py-16 text-center bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                  <Clock size={40} className="mx-auto text-slate-300 mb-3" />
                  <p className="text-slate-500 font-semibold text-sm">No pending deliveries match your criteria</p>
                  <p className="text-slate-400 text-xs mt-1">Try resetting your search or date filters</p>
                </div>
              ) : (
                filteredPendingDeliveriesModal.map((sale) => (
                  <div 
                    key={sale.id}
                    className="bg-white rounded-2xl border border-slate-200 p-4 hover:border-slate-300 transition-all shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
                  >
                    <div className="space-y-1.5 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-xs font-bold text-slate-900 bg-slate-100 px-2.5 py-0.5 rounded-md">
                          #{sale.id.substring(0, 8).toUpperCase()}
                        </span>
                        <span className="text-xs font-bold text-slate-900">{sale.customerName}</span>
                        <span className="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">
                          Pending Dispatch
                        </span>
                      </div>

                      <div className="text-xs text-slate-600 flex flex-wrap items-center gap-x-4 gap-y-1">
                        <span className="flex items-center gap-1 text-slate-500">
                          <Package size={13} className="text-slate-400" />
                          {sale.items.map(i => `${i.quantity}x ${i.productName}`).join(', ')}
                        </span>
                        <span className="flex items-center gap-1 text-slate-400">
                          <Clock size={13} />
                          {new Date(sale.createdAt).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })}
                        </span>
                        {sale.userName && (
                          <span className="text-slate-400">
                            Served By: <strong className="text-slate-600">{sale.userName}</strong>
                          </span>
                        )}
                      </div>
                    </div>

                    <div className="flex items-center justify-between md:justify-end gap-3 shrink-0 border-t md:border-t-0 pt-3 md:pt-0 border-slate-100">
                      <div className="text-right">
                        <div className="font-mono font-bold text-sm text-slate-900">
                          {settings.currency}{sale.totalAmount.toLocaleString()}
                        </div>
                        <div className="text-[10px] font-semibold text-amber-600 uppercase">
                          {sale.status}
                        </div>
                      </div>

                      <button
                        onClick={() => setReceiptSale(sale)}
                        className="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                        title="Print / View Slip"
                      >
                        <Printer size={14} />
                        <span>Slip</span>
                      </button>

                      {canEdit && (
                        <button
                          onClick={() => setConfirmingSaleId(sale.id)}
                          className="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
                          id={`modal-mark-delivered-btn-${sale.id}`}
                        >
                          <CheckCircle2 size={14} />
                          <span>Delivered</span>
                        </button>
                      )}
                    </div>
                  </div>
                ))
              )}
            </div>

            {/* Modal Footer */}
            <div className="pt-4 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500 shrink-0">
              <span>
                Showing {filteredPendingDeliveriesModal.length} of {pendingDeliveries.length} pending deliveries
              </span>
              <button
                onClick={() => setShowPendingModal(false)}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Completed Deliveries Modal */}
      {showCompletedModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in" id="completed-deliveries-modal">
          <div className="bg-card-theme-bg rounded-3xl p-6 md:p-8 max-w-5xl w-full shadow-2xl relative overflow-hidden max-h-[90vh] flex flex-col border border-slate-100">
            {/* Modal Header */}
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 shrink-0">
              <div className="flex items-center gap-3">
                <div className="w-12 h-12 bg-accent-theme-light text-accent-theme rounded-2xl flex items-center justify-center shrink-0">
                  <CheckCircle2 size={24} />
                </div>
                <div>
                  <h3 className="text-xl font-bold text-slate-900">Completed Deliveries</h3>
                  <p className="text-xs text-slate-500">
                    Total {completedSales.length} delivered order{completedSales.length !== 1 ? 's' : ''} recorded
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-2">
                <button
                  onClick={handleExportCSV}
                  disabled={filteredCompletedDeliveries.length === 0}
                  className="px-4 py-2.5 bg-accent-theme hover:bg-accent-theme-hover disabled:opacity-50 text-white rounded-xl font-bold text-xs flex items-center gap-2 transition-all cursor-pointer shadow-sm"
                  id="export-completed-deliveries-csv-btn"
                >
                  <Download size={16} />
                  <span>Export CSV ({filteredCompletedDeliveries.length})</span>
                </button>
                <button
                  onClick={() => setShowCompletedModal(false)}
                  className="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer"
                  id="close-completed-deliveries-modal-btn"
                >
                  <X size={20} />
                </button>
              </div>
            </div>

            {/* Modal Filters & Sub-Tabs */}
            <div className="py-4 border-b border-slate-100 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 shrink-0 bg-slate-50/50 -mx-6 px-6">
              {/* Category Filter Tabs */}
              <div className="flex items-center bg-slate-200/70 p-1 rounded-xl text-xs font-bold shrink-0">
                <button
                  type="button"
                  onClick={() => setModalReturnFilter('all')}
                  className={`px-3 py-1.5 rounded-lg transition-all cursor-pointer ${
                    modalReturnFilter === 'all'
                      ? 'bg-white text-slate-900 shadow-2xs font-extrabold'
                      : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  All ({completedSales.length})
                </button>
                <button
                  type="button"
                  onClick={() => setModalReturnFilter('delivered')}
                  className={`px-3 py-1.5 rounded-lg transition-all cursor-pointer ${
                    modalReturnFilter === 'delivered'
                      ? 'bg-white text-emerald-700 shadow-2xs font-extrabold'
                      : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  Active ({activeCompletedSalesCount})
                </button>
                <button
                  type="button"
                  onClick={() => setModalReturnFilter('returned')}
                  className={`px-3 py-1.5 rounded-lg transition-all cursor-pointer flex items-center gap-1 ${
                    modalReturnFilter === 'returned'
                      ? 'bg-purple-600 text-white shadow-2xs font-extrabold'
                      : 'text-purple-700 hover:bg-purple-100'
                  }`}
                >
                  <RotateCcw size={12} />
                  Returned ({returnedCompletedSalesCount})
                </button>
              </div>

              <div className="relative flex-1 min-w-[180px]">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
                <input
                  type="text"
                  placeholder="Search customer, order ID, product, staff..."
                  className="pl-9 pr-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-theme w-full text-xs bg-white"
                  value={modalSearch}
                  onChange={(e) => setModalSearch(e.target.value)}
                  id="completed-deliveries-search-input"
                />
              </div>

              <div className="flex flex-wrap items-center gap-2">
                {/* Date Filter */}
                <div className="flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs shadow-2xs">
                  <Calendar size={14} className="text-primary-theme shrink-0" />
                  <span className="text-xs font-bold text-slate-400 uppercase tracking-wider shrink-0">Period:</span>
                  <select
                    value={dateFilter}
                    onChange={(e) => setDateFilter(e.target.value as DateFilterOption)}
                    className="bg-transparent font-bold text-slate-800 outline-none pr-2 cursor-pointer text-xs"
                    id="completed-deliveries-date-filter"
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

                {dateFilter === 'custom' && (
                  <div className="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-1 shadow-2xs text-xs animate-fadeIn">
                    <span className="text-[10px] font-bold text-slate-400 uppercase">From:</span>
                    <input
                      type="date"
                      value={startDate}
                      onChange={(e) => setStartDate(e.target.value)}
                      className="text-xs font-medium text-slate-800 bg-transparent outline-none focus:text-primary-theme"
                    />
                    <span className="text-[10px] font-bold text-slate-400 uppercase ml-1">To:</span>
                    <input
                      type="date"
                      value={endDate}
                      onChange={(e) => setEndDate(e.target.value)}
                      className="text-xs font-medium text-slate-800 bg-transparent outline-none focus:text-primary-theme"
                    />
                  </div>
                )}

                {/* Sort Order */}
                <div className="flex items-center gap-1 bg-white border border-slate-200 rounded-xl p-1 text-xs">
                  <ArrowUpDown size={14} className="text-slate-400 ml-1.5" />
                  <select
                    value={sortBy}
                    onChange={(e) => setSortBy(e.target.value as any)}
                    className="bg-transparent font-medium text-slate-700 outline-none pr-2 cursor-pointer text-xs"
                    id="completed-deliveries-sort-filter"
                  >
                    <option value="newest">Newest Delivered</option>
                    <option value="oldest">Oldest Delivered</option>
                    <option value="amount_high">Highest Amount</option>
                  </select>
                </div>
              </div>
            </div>

            {/* Deliveries List Table / Cards */}
            <div className="overflow-y-auto flex-1 my-4 pr-1 space-y-3">
              {filteredCompletedDeliveries.length === 0 ? (
                <div className="py-16 text-center bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                  <CheckCircle2 size={40} className="mx-auto text-slate-300 mb-3" />
                  <p className="text-slate-500 font-semibold text-sm">No completed deliveries match your criteria</p>
                  <p className="text-slate-400 text-xs mt-1">Try resetting your search or date filters</p>
                </div>
              ) : (
                filteredCompletedDeliveries.map((sale) => {
                  const saleReturns = returns.filter(r => r.saleId === sale.id);
                  const isReturned = returnedSaleIds.has(sale.id);

                  return (
                    <div 
                      key={sale.id}
                      className={`bg-white rounded-2xl border p-4 transition-all shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4 ${
                        isReturned ? 'border-purple-200 bg-purple-50/20' : 'border-slate-200 hover:border-slate-300'
                      }`}
                    >
                      <div className="space-y-1.5 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-mono text-xs font-bold text-slate-900 bg-slate-100 px-2.5 py-0.5 rounded-md">
                            #{sale.id.substring(0, 8).toUpperCase()}
                          </span>
                          <span className="text-xs font-bold text-slate-900">{sale.customerName}</span>
                          <span className="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">
                            Delivered
                          </span>
                          {isReturned && (
                            <span className="bg-purple-100 text-purple-800 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider inline-flex items-center gap-1 shadow-2xs">
                              <RotateCcw size={11} />
                              Returned
                            </span>
                          )}
                        </div>

                        <div className="text-xs text-slate-600 flex flex-wrap items-center gap-x-4 gap-y-1">
                          <span className="flex items-center gap-1 text-slate-500">
                            <Package size={13} className="text-slate-400" />
                            {sale.items.map(i => `${i.quantity}x ${i.productName}`).join(', ')}
                          </span>
                          {sale.deliveredAt && (
                            <span className="flex items-center gap-1 text-slate-400">
                              <Clock size={13} />
                              {new Date(sale.deliveredAt).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })}
                            </span>
                          )}
                          {sale.deliveredBy && (
                            <span className="text-slate-400">
                              By: <strong className="text-slate-600">{sale.deliveredBy}</strong>
                            </span>
                          )}
                        </div>

                        {isReturned && (
                          <div className="text-xs text-purple-900 font-medium bg-purple-50 border border-purple-200/80 p-2.5 rounded-xl flex items-start gap-2 mt-2">
                            <RotateCcw size={14} className="text-purple-600 shrink-0 mt-0.5" />
                            <div>
                              <span className="font-bold text-purple-950">Stock Returned:</span>{' '}
                              {saleReturns.length > 0
                                ? saleReturns.map(r => `${r.productName || 'Product'} (x${r.quantity})`).join(', ')
                                : 'Order Refunded / Returned'}
                              {(saleReturns[0]?.reason || sale.returnReason) && (
                                <span className="block text-[11px] text-purple-700 mt-0.5 italic">
                                  Reason: "{saleReturns[0]?.reason || sale.returnReason}"
                                </span>
                              )}
                            </div>
                          </div>
                        )}
                      </div>

                      <div className="flex items-center justify-between md:justify-end gap-4 shrink-0 border-t md:border-t-0 pt-3 md:pt-0 border-slate-100">
                        <div className="text-right">
                          <div className="font-mono font-bold text-sm text-slate-900">
                            {settings.currency}{sale.totalAmount.toLocaleString()}
                          </div>
                          <div className="text-[10px] font-semibold text-slate-400 uppercase">
                            {sale.status}
                          </div>
                        </div>

                        <button
                          onClick={() => setReceiptSale(sale)}
                          className="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                          title="Print / View Receipt"
                        >
                          <Printer size={14} />
                          <span>Slip</span>
                        </button>
                      </div>
                    </div>
                  );
                })
              )}
            </div>

            {/* Modal Footer */}
            <div className="pt-4 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500 shrink-0">
              <span>
                Showing {filteredCompletedDeliveries.length} of {completedSales.length} completed deliveries
              </span>
              <button
                onClick={() => setShowCompletedModal(false)}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Confirmation Modal */}
      {confirmingSale && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in" id="delivery-confirm-modal">
          <div className="bg-card-theme-bg rounded-3xl p-8 max-w-md w-full shadow-2xl relative overflow-y-auto max-h-[90vh] border border-slate-100">
            <button 
              onClick={() => setConfirmingSaleId(null)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition-colors p-1 hover:bg-slate-100 rounded-lg cursor-pointer"
              id="close-confirm-modal-btn"
            >
              <X size={20} />
            </button>

            <div className="flex items-center gap-4 mb-6">
              <div className="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center shrink-0">
                <AlertTriangle size={24} />
              </div>
              <div>
                <h3 className="text-xl font-bold text-slate-900">Confirm Delivery</h3>
                <p className="text-xs text-slate-500">Order #{confirmingSale.id.substring(0, 8)}</p>
              </div>
            </div>

            <div className="space-y-4 mb-8">
              <p className="text-slate-600 text-sm leading-relaxed">
                Are you sure you want to mark this order as <strong className="text-accent-theme">delivered</strong> to <strong className="text-slate-900">{confirmingSale.customerName}</strong>?
              </p>
              
              <div className="bg-layout-theme-bg rounded-2xl p-4 border border-slate-100 space-y-2">
                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Items in shipment:</span>
                {confirmingSale.items.map((item, i) => (
                  <div key={i} className="flex justify-between items-center text-xs text-slate-700">
                    <span className="font-medium text-slate-600">{item.productName}</span>
                    <span className="bg-slate-200 text-slate-800 px-2 py-0.5 rounded font-mono font-bold">x{item.quantity}</span>
                  </div>
                ))}
              </div>

              <div className="flex items-start gap-2.5 bg-amber-50 border border-amber-100 p-3.5 rounded-2xl text-amber-800 text-xs leading-normal">
                <AlertTriangle size={16} className="shrink-0 mt-0.5 text-amber-600" />
                <span>
                  Confirming delivery will immediately deduct these quantities from your inventory stock and register a completed fulfillment event.
                </span>
              </div>
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => setConfirmingSaleId(null)}
                className="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-2xl text-sm transition-colors cursor-pointer"
                id="cancel-delivery-btn"
              >
                Cancel
              </button>
              <button
                onClick={() => handleDeliver(confirmingSale.id)}
                className="flex-1 py-3 bg-accent-theme hover:bg-accent-theme-hover text-white font-bold rounded-2xl text-sm transition-colors shadow-sm cursor-pointer"
                id="confirm-delivery-btn"
              >
                Yes, Delivered
              </button>
            </div>
          </div>
        </div>
      )}

      <ReceiptModal 
        sale={receiptSale} 
        onClose={() => setReceiptSale(null)} 
      />
    </div>
  );
}
