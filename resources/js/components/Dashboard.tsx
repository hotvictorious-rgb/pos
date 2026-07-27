/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useMemo, useState, useEffect, useCallback } from 'react';
import { Product, Sale, Payment, InventoryLog, SalesReturn } from '../types';
import { 
  TrendingUp, 
  TrendingDown, 
  Package, 
  ShoppingCart, 
  CreditCard, 
  AlertCircle,
  Calendar,
  Filter,
  X,
  Download,
  FileText,
  ClipboardCheck,
  RotateCcw,
  ArrowUpCircle, 
  ArrowDownCircle, 
  CheckCircle2,
  Calculator
} from 'lucide-react';
import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import { 
  BarChart, 
  Bar, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  ResponsiveContainer, 
  LineChart, 
  Line 
} from 'recharts';
import { storage } from '../lib/storage';
import { format, subDays, isAfter, startOfDay, isValid, endOfDay } from 'date-fns';

const safeFormat = (dateStr: string | undefined, formatStr: string) => {
  if (!dateStr) return 'N/A';
  const d = new Date(dateStr);
  if (!isValid(d)) return 'N/A';
  return format(d, formatStr);
};

type FilterType = 'today' | 'yesterday' | 'thisWeek' | '7days' | 'thisMonth' | '30days' | 'thisYear' | 'custom' | 'all';

export default function Dashboard() {
  const [settings] = useState(storage.getSettings());
  const [filter, setFilter] = useState<FilterType>('today');
  const [selectedStat, setSelectedStat] = useState<string | null>(null);
  const [customRange, setCustomRange] = useState<{ start: string; end: string }>({
    start: format(subDays(new Date(), 7), 'yyyy-MM-dd'),
    end: format(new Date(), 'yyyy-MM-dd')
  });
  
  const [products, setProducts] = useState<Product[]>(() => storage.getProducts().filter(p => !p.archived));
  const [allSales, setAllSales] = useState<Sale[]>(() => storage.getSales());
  const [allPayments, setAllPayments] = useState<Payment[]>(() => storage.getPayments());
  const [allLogs, setAllLogs] = useState<InventoryLog[]>(() => storage.getLogs());
  const [allReturns, setAllReturns] = useState<SalesReturn[]>(() => storage.getReturns());

  useEffect(() => {
    const refreshData = () => {
      setProducts(storage.getProducts().filter(p => !p.archived));
      setAllSales(storage.getSales());
      setAllPayments(storage.getPayments());
      setAllLogs(storage.getLogs());
      setAllReturns(storage.getReturns());
    };

    window.addEventListener('hysam-data-updated', refreshData);
    window.addEventListener('hysam-sync-end', refreshData);
    return () => {
      window.removeEventListener('hysam-data-updated', refreshData);
      window.removeEventListener('hysam-sync-end', refreshData);
    };
  }, []);

  const lowStockProducts = useMemo(() => {
    return products.map(p => ({
      ...p,
      actualStock: storage.calculateClosingStock(p.id)
    })).filter(p => p.actualStock <= p.minStockLevel);
  }, [products, allLogs, allSales, allReturns]);

  const dateRange = useMemo(() => {
    if (filter === 'all') return { startDate: null as Date | null, endDate: null as Date | null };
    const now = new Date();
    let startDate: Date = startOfDay(now);
    let endDate: Date = endOfDay(now);

    if (filter === 'today') {
      startDate = startOfDay(now);
      endDate = endOfDay(now);
    } else if (filter === 'yesterday') {
      const yesterday = subDays(now, 1);
      startDate = startOfDay(yesterday);
      endDate = endOfDay(yesterday);
    } else if (filter === 'thisWeek') {
      const dayOfWeek = now.getDay();
      startDate = startOfDay(subDays(now, dayOfWeek));
      endDate = endOfDay(now);
    } else if (filter === '7days') {
      startDate = subDays(now, 7);
      endDate = endOfDay(now);
    } else if (filter === 'thisMonth') {
      startDate = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0, 0);
      endDate = endOfDay(now);
    } else if (filter === '30days') {
      startDate = subDays(now, 30);
      endDate = endOfDay(now);
    } else if (filter === 'thisYear') {
      startDate = new Date(now.getFullYear(), 0, 1, 0, 0, 0, 0);
      endDate = endOfDay(now);
    } else if (filter === 'custom') {
      const customStart = new Date(customRange.start);
      const customEnd = new Date(customRange.end);
      
      if (isValid(customStart) && isValid(customEnd)) {
        startDate = startOfDay(customStart);
        endDate = new Date(customRange.end);
        endDate.setHours(23, 59, 59, 999);
      } else {
        return { startDate: null, endDate: null };
      }
    }

    return { startDate, endDate };
  }, [filter, customRange]);

  const checkDateRange = useCallback((dateStr: string | undefined) => {
    if (!dateStr) return false;
    if (!dateRange.startDate || !dateRange.endDate) return true;
    const d = new Date(dateStr);
    if (!isValid(d)) return false;
    return d >= dateRange.startDate && d <= dateRange.endDate;
  }, [dateRange]);

  const filteredData = useMemo(() => {
    let sales = allSales;
    let payments = allPayments;
    let logs = allLogs;
    let returns = allReturns;

    if (filter !== 'all' && dateRange.startDate && dateRange.endDate) {
      sales = allSales.filter(s => checkDateRange(s.createdAt));
      payments = allPayments.filter(p => checkDateRange(p.timestamp || p.createdAt));
      logs = allLogs.filter(l => checkDateRange(l.timestamp));
      returns = allReturns.filter(r => checkDateRange(r.createdAt || r.timestamp));
    }

    return { sales, payments, logs, returns };
  }, [filter, dateRange, checkDateRange, allSales, allPayments, allLogs, allReturns]);

  const deliveredSalesInPeriod = useMemo(() => {
    return allSales.filter(s => s.deliveryStatus === 'delivered' && checkDateRange(s.deliveredAt || s.createdAt));
  }, [allSales, checkDateRange]);

  const pendingSalesInPeriod = useMemo(() => {
    return allSales.filter(s => s.deliveryStatus === 'pending' && checkDateRange(s.createdAt));
  }, [allSales, checkDateRange]);

  const getStockReturnQty = useCallback((r: SalesReturn, pId?: string) => {
    const linkedSale = allSales.find(s => s.id === r.saleId);
    if (linkedSale) {
      if (linkedSale.deliveryStatus !== 'delivered') return 0;
      const item = linkedSale.items.find(i => i.productId === (pId || r.productId));
      const maxDelivered = item ? item.quantity : r.quantity;
      return Math.min(r.quantity, maxDelivered);
    }
    return r.wasDelivered === true ? r.quantity : 0;
  }, [allSales]);

  const isStockReturn = useCallback((r: SalesReturn) => {
    return getStockReturnQty(r) > 0;
  }, [getStockReturnQty]);

  const reconciliationData = useMemo(() => {
    return products.map(product => {
      const breakdown = storage.getStockBreakdown(
        product.id, 
        filter !== 'all' ? (dateRange.startDate || undefined) : undefined,
        filter !== 'all' ? (dateRange.endDate || undefined) : undefined
      );

      return {
        id: product.id,
        code: product.code,
        name: product.name,
        openingStock: breakdown.openingStock,
        stockIn: breakdown.stockIn,
        returns: breakdown.stockReturn,
        stockOut: breakdown.stockOut,
        delivered: breakdown.delivered,
        closingStock: breakdown.closingStock
      };
    });
  }, [products, dateRange, filter, allLogs, allSales, allReturns]);

  const stats = useMemo(() => {
    const totalSales = filteredData.sales.filter(s => s.status !== 'returned').reduce((acc, s) => acc + s.totalAmount, 0);
    const totalPaymentsReceived = filteredData.payments.reduce((acc, p) => acc + p.amount, 0);
    const totalRefunds = filteredData.returns.reduce((acc, r) => acc + r.refundAmount, 0);

    const totalStockIn = reconciliationData.reduce((acc, r) => acc + r.stockIn, 0);
    const totalStockOut = reconciliationData.reduce((acc, r) => acc + r.stockOut, 0);
    const totalStockReturnsQty = reconciliationData.reduce((acc, r) => acc + r.returns, 0);
    const deliveredQty = reconciliationData.reduce((acc, r) => acc + r.delivered, 0);
    const pendingDeliveriesQty = pendingSalesInPeriod
      .filter(s => s.status !== 'returned')
      .reduce((acc, s) => acc + s.items.reduce((sum, item) => sum + item.quantity, 0), 0);

    const totalClosingStock = reconciliationData.reduce((acc, r) => acc + r.closingStock, 0);
    const totalStockValue = products.reduce((acc, p) => {
      const rec = reconciliationData.find(r => r.id === p.id);
      return acc + (p.unitPrice * (rec ? rec.closingStock : 0));
    }, 0);

    return [
      { id: 'sales', label: 'Total Sales', value: `${settings.currency}${totalSales.toLocaleString()}`, icon: ShoppingCart, color: 'bg-primary-theme' },
      { id: 'payments-received', label: 'Revenue Collected', value: `${settings.currency}${totalPaymentsReceived.toLocaleString()}`, icon: CreditCard, color: 'bg-emerald-600' },
      { id: 'low-stock', label: 'Low Stock Items', value: `${lowStockProducts.length} Items`, icon: AlertCircle, color: lowStockProducts.length > 0 ? 'bg-rose-600' : 'bg-slate-400' },
      { id: 'refunds', label: 'Refunds', value: `${settings.currency}${totalRefunds.toLocaleString()}`, icon: CreditCard, color: 'bg-amber-500' },
      { id: 'stock-in', label: 'Stock In', value: `${totalStockIn} Units`, icon: ArrowUpCircle, color: 'bg-accent-theme' },
      { id: 'stock-out', label: 'Stock Out', value: `${totalStockOut} Units`, icon: ArrowDownCircle, color: 'bg-orange-500' },
      { id: 'returns', label: 'Stock Returns', value: `${totalStockReturnsQty} Units`, icon: RotateCcw, color: 'bg-rose-500' },
      { id: 'delivered', label: 'Delivered', value: `${deliveredQty} Units`, icon: CheckCircle2, color: 'bg-accent-theme' },
      { id: 'pending', label: 'Pending Delivery', value: `${pendingDeliveriesQty} Units`, icon: Package, color: 'bg-purple-500' },
      { id: 'stock-value', label: 'Total Stock Value', value: `${settings.currency}${totalStockValue.toLocaleString()}`, icon: TrendingUp, color: 'bg-indigo-500' },
      { id: 'closing-stock', label: 'Total Closing Stock', value: `${totalClosingStock} Units`, icon: Package, color: 'bg-slate-700' },
    ];
  }, [filteredData, pendingSalesInPeriod, lowStockProducts, settings, reconciliationData, products]);

  const salesData = useMemo(() => {
    if (filter === 'today' || filter === 'yesterday') {
      const hours = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'];
      return hours.map(h => {
        const hNum = parseInt(h.split(':')[0], 10);
        const sum = filteredData.sales
          .filter(s => s.status !== 'returned')
          .filter(s => {
            const d = new Date(s.createdAt);
            if (!isValid(d)) return false;
            const hour = d.getHours();
            return hour >= hNum && hour < hNum + 2;
          })
          .reduce((acc, s) => acc + s.totalAmount, 0);
        return { name: h, sales: sum };
      });
    }

    if (filter === 'thisMonth' || filter === '30days') {
      const daysCount = filter === 'thisMonth' ? new Date().getDate() : 30;
      const step = Math.max(1, Math.floor(daysCount / 7));
      const slots: { name: string; sales: number }[] = [];
      const endDate = dateRange.endDate || new Date();
      const startDate = dateRange.startDate || subDays(endDate, 30);

      let curr = new Date(startDate);
      while (curr <= endDate) {
        const label = format(curr, 'MMM dd');
        const dayStart = startOfDay(curr);
        const dayEnd = endOfDay(curr);
        const sum = allSales
          .filter(s => s.status !== 'returned')
          .filter(s => {
            const d = new Date(s.createdAt);
            return isValid(d) && d >= dayStart && d <= dayEnd;
          })
          .reduce((acc, s) => acc + s.totalAmount, 0);
        slots.push({ name: label, sales: sum });
        curr.setDate(curr.getDate() + Math.max(1, step));
      }
      return slots;
    }

    if (filter === 'thisYear' || filter === 'all') {
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const targetSales = filter === 'thisYear' 
        ? allSales.filter(s => new Date(s.createdAt).getFullYear() === new Date().getFullYear())
        : allSales;
      return months.map(m => {
        const sum = targetSales
          .filter(s => s.status !== 'returned')
          .filter(s => {
            const d = new Date(s.createdAt);
            return isValid(d) && format(d, 'MMM') === m;
          })
          .reduce((acc, s) => acc + s.totalAmount, 0);
        return { name: m, sales: sum };
      });
    }

    // Default: Last 7 Days / thisWeek / custom
    const endDate = dateRange.endDate || new Date();
    const startDate = dateRange.startDate || subDays(endDate, 6);
    const slots: { name: string; sales: number }[] = [];
    let curr = new Date(startDate);

    while (curr <= endDate && slots.length < 14) {
      const label = format(curr, 'MMM dd');
      const dayStart = startOfDay(curr);
      const dayEnd = endOfDay(curr);
      const sum = allSales
        .filter(s => s.status !== 'returned')
        .filter(s => {
          const d = new Date(s.createdAt);
          return isValid(d) && d >= dayStart && d <= dayEnd;
        })
        .reduce((acc, s) => acc + s.totalAmount, 0);
      slots.push({ name: label, sales: sum });
      curr.setDate(curr.getDate() + 1);
    }
    return slots;
  }, [allSales, filteredData.sales, filter, dateRange]);

  const exportToCSV = (statId?: string) => {
    const targetId = statId || selectedStat;
    if (!targetId) return;
    const stat = stats.find(s => s.id === targetId) || { label: 'Stock Reconciliation', id: 'reconciliation' };
    let data: any[] = [];
    let headers: string[] = [];
    
    let dateStr = format(new Date(), 'yyyy-MM-dd');
    if (filter === 'today') dateStr = format(new Date(), 'yyyy-MM-dd');
    else if (filter === 'yesterday') dateStr = format(subDays(new Date(), 1), 'yyyy-MM-dd');
    else if (filter === 'custom' && customRange.start && customRange.end) {
      dateStr = `${customRange.start}_to_${customRange.end}`;
    } else if (filter !== 'all') {
      dateStr = `${filter}_as_of_${format(new Date(), 'yyyy-MM-dd')}`;
    }

    let filename = `${stat.label.replace(/\s+/g, '_')}_${dateStr}.csv`;

    if (targetId === 'sales' || targetId === 'delivered' || targetId === 'pending') {
      const list = targetId === 'sales' ? filteredData.sales.filter(s => s.status !== 'returned') : 
                 targetId === 'delivered' ? deliveredSalesInPeriod :
                 pendingSalesInPeriod.filter(s => s.status !== 'returned');
      headers = ['Product(s)', 'Customer', `Amount (${settings.currency})`, 'Delivery Status', 'Date'];
      data = list.map(s => [
        s.items.map(i => `${i.productCode}:${i.productName}(x${i.quantity})`).join('; '),
        s.customerName,
        s.totalAmount,
        s.deliveryStatus,
        format(new Date(s.deliveredAt || s.createdAt), 'yyyy-MM-dd HH:mm')
      ]);
    } else if (targetId === 'payments-received') {
      headers = ['Customer / Sale ID', 'Payment Method', `Amount Received (${settings.currency})`, 'Processed By', 'Date'];
      data = filteredData.payments.map(p => {
        const linkedSale = allSales.find(s => s.id === p.saleId);
        return [
          linkedSale ? `${linkedSale.customerName} (#${p.saleId.slice(0, 8)})` : `Sale #${p.saleId.slice(0, 8)}`,
          p.method,
          p.amount,
          p.userName || 'Staff',
          format(new Date(p.timestamp || p.createdAt), 'yyyy-MM-dd HH:mm')
        ];
      });
    } else if (targetId === 'returns' || targetId === 'refunds') {
      const list = targetId === 'returns' 
        ? filteredData.returns.filter(r => isStockReturn(r)) 
        : filteredData.returns;
      headers = ['Product', 'Return Qty', `Refund Amount (${settings.currency})`, 'Type / Status', 'Reason for Return', 'Date'];
      data = list.map(r => [
        `${r.productCode}: ${r.productName}`,
        r.quantity,
        r.refundAmount,
        isStockReturn(r) ? 'Stock Returned (Restocked)' : 'Order Refund (Undelivered)',
        r.reason,
        format(new Date(r.timestamp), 'yyyy-MM-dd HH:mm')
      ]);
    } else if (targetId === 'reconciliation') {
      headers = ['Product Code', 'Product Name', 'Opening Stock', 'Stock In', 'Stock Return', 'Stock Out', 'Delivered/Sold', 'Closing Stock'];
      data = reconciliationData.map(r => [
        r.code,
        r.name,
        r.openingStock,
        r.stockIn,
        r.returns,
        r.stockOut,
        r.delivered,
        r.closingStock
      ]);
    } else if (targetId === 'stock-in' || targetId === 'stock-out') {
      const typeLabel = targetId === 'stock-in' ? 'Added' : 'Removed';
      headers = ['Product', `${typeLabel} Qty`, 'Action Description', 'Processed By', 'Date'];
      data = filteredData.logs.filter(l => l.type === targetId).map(l => [
        `${l.productCode}: ${l.productName}`,
        l.quantity,
        l.description,
        l.userName,
        format(new Date(l.timestamp), 'yyyy-MM-dd HH:mm')
      ]);
    } else if (targetId === 'stock-value') {
      headers = ['Product Code', 'Product Name', 'Closing Stock Units', `Unit Price (${settings.currency})`, `Total Valuation (${settings.currency})`];
      data = products
        .map(p => {
          const stock = storage.calculateClosingStock(p.id);
          return [p.code, p.name, stock, p.unitPrice, p.unitPrice * stock];
        })
        .sort((a, b) => (b[4] as number) - (a[4] as number));
    } else if (targetId === 'closing-stock') {
      headers = ['Product Code', 'Product Name', 'Closing Stock Units', 'Reorder Level', `Unit Price (${settings.currency})`, 'Status'];
      data = products.map(p => {
        const stock = storage.calculateClosingStock(p.id);
        const status = stock === 0 ? 'Out of Stock' : stock <= p.minStockLevel ? 'Low Stock' : 'Healthy';
        return [p.code, p.name, stock, p.minStockLevel, p.unitPrice, status];
      });
    } else if (targetId === 'low-stock') {
      headers = ['Product Code', 'Product Name', 'Current Stock Units', 'Min Threshold', 'Status'];
      data = lowStockProducts.map(p => [
        p.code,
        p.name,
        p.actualStock,
        p.minStockLevel,
        p.actualStock === 0 ? 'Out of Stock' : 'Low Stock'
      ]);
    }

    const csvContent = [
      headers.join(','),
      ...data.map(row => row.map((cell: any) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const exportToPDF = (statId?: string) => {
    const targetId = statId || selectedStat;
    if (!targetId) return;
    const stat = stats.find(s => s.id === targetId) || { label: 'Stock Reconciliation', id: 'reconciliation' };
    const doc = new jsPDF();
    let headers: string[] = [];
    let body: any[] = [];
    
    let rangeLabel = filter === 'all' ? 'All Time' : filter.toUpperCase();
    if (filter === 'today') rangeLabel = format(new Date(), 'MMM dd, yyyy');
    else if (filter === 'yesterday') rangeLabel = format(subDays(new Date(), 1), 'MMM dd, yyyy');
    else if (filter === 'custom' && customRange.start && customRange.end) {
      rangeLabel = `${format(new Date(customRange.start), 'MMM dd')} - ${format(new Date(customRange.end), 'MMM dd, yyyy')}`;
    }

    let title = `${stat.label} Report - ${rangeLabel}`;

    if (targetId === 'sales' || targetId === 'delivered' || targetId === 'pending') {
      headers = ['Product Details', 'Customer', 'Amount', 'Status', 'Date'];
      const list = targetId === 'sales' ? filteredData.sales.filter(s => s.status !== 'returned') : 
                 targetId === 'delivered' ? deliveredSalesInPeriod :
                 pendingSalesInPeriod.filter(s => s.status !== 'returned');
      body = list.map(s => [
        s.items.map(i => `${i.productName} (x${i.quantity})`).join('\n'),
        s.customerName,
        `${settings.currency}${s.totalAmount.toLocaleString()}`,
        s.deliveryStatus.toUpperCase(),
        format(new Date(s.deliveredAt || s.createdAt), 'MMM dd, HH:mm')
      ]);
    } else if (targetId === 'payments-received') {
      headers = ['Customer / Sale ID', 'Method', 'Amount', 'Staff', 'Date'];
      body = filteredData.payments.map(p => {
        const linkedSale = allSales.find(s => s.id === p.saleId);
        return [
          linkedSale ? `${linkedSale.customerName}\n(#${p.saleId.slice(0, 8)})` : `Sale #${p.saleId.slice(0, 8)}`,
          p.method,
          `${settings.currency}${p.amount.toLocaleString()}`,
          p.userName || 'Staff',
          format(new Date(p.timestamp || p.createdAt), 'MMM dd, HH:mm')
        ];
      });
    } else if (targetId === 'returns' || targetId === 'refunds') {
      const list = targetId === 'returns' 
        ? filteredData.returns.filter(r => isStockReturn(r)) 
        : filteredData.returns;
      headers = ['Product', 'Qty', 'Refund', 'Type', 'Reason', 'Date'];
      body = list.map(r => [
        `${r.productCode}\n${r.productName}`,
        r.quantity,
        `${settings.currency}${r.refundAmount.toLocaleString()}`,
        isStockReturn(r) ? 'Restocked' : 'Refund Only',
        r.reason,
        format(new Date(r.timestamp), 'MMM dd, HH:mm')
      ]);
    } else if (targetId === 'reconciliation') {
      headers = ['Product', 'Op', 'In', 'Ret', 'Out', 'Sold', 'Cl'];
      body = reconciliationData.map(r => [
        `${r.code}\n${r.name}`,
        r.openingStock,
        r.stockIn,
        r.returns,
        r.stockOut,
        r.delivered,
        r.closingStock
      ]);
    } else if (targetId === 'stock-in' || targetId === 'stock-out') {
      const qtyHeader = targetId === 'stock-in' ? 'In' : 'Out';
      headers = ['Product', qtyHeader, 'Description', 'Staff', 'Date'];
      body = filteredData.logs.filter(l => l.type === targetId).map(l => [
        `${l.productCode}\n${l.productName}`,
        l.quantity,
        l.description,
        l.userName,
        format(new Date(l.timestamp), 'MMM dd, HH:mm')
      ]);
    } else if (targetId === 'stock-value') {
      headers = ['Code', 'Product Name', 'Stock', 'Unit Price', 'Total Value'];
      body = products
        .map(p => {
          const stock = storage.calculateClosingStock(p.id);
          return [
            p.code,
            p.name,
            stock,
            `${settings.currency}${p.unitPrice.toLocaleString()}`,
            `${settings.currency}${(p.unitPrice * stock).toLocaleString()}`
          ];
        });
    } else if (targetId === 'closing-stock') {
      headers = ['Code', 'Product Name', 'Stock', 'Min Level', 'Unit Price', 'Status'];
      body = products.map(p => {
        const stock = storage.calculateClosingStock(p.id);
        const status = stock === 0 ? 'Out of Stock' : stock <= p.minStockLevel ? 'Low Stock' : 'Healthy';
        return [p.code, p.name, stock, p.minStockLevel, `${settings.currency}${p.unitPrice.toLocaleString()}`, status];
      });
    } else if (targetId === 'low-stock') {
      headers = ['Code', 'Product Name', 'Current Stock', 'Min Level', 'Status'];
      body = lowStockProducts.map(p => [
        p.code,
        p.name,
        p.actualStock,
        p.minStockLevel,
        p.actualStock === 0 ? 'Out of Stock' : 'Low Stock'
      ]);
    }

    doc.setFontSize(18);
    doc.text(settings.businessName, 14, 22);
    doc.setFontSize(12);
    doc.text(title, 14, 30);
    doc.setFontSize(10);
    doc.text(`Generated: ${format(new Date(), 'yyyy-MM-dd HH:mm')}`, 14, 36);

    autoTable(doc, {
      head: [headers],
      body: body,
      startY: 42,
      styles: { fontSize: 8, cellPadding: 2 },
      headStyles: { fillColor: [37, 99, 235] },
      didDrawPage: (data) => {
        if (settings.reportFooter) {
          doc.setFontSize(8);
          doc.setTextColor(128);
          doc.text(settings.reportFooter, 14, doc.internal.pageSize.height - 10);
        }
      }
    });

    doc.save(`${stat?.label.replace(/\s+/g, '_')}_Report_${format(new Date(), 'yyyyMMdd')}.pdf`);
  };

  const renderDetails = () => {
    if (!selectedStat) return null;

    if (selectedStat === 'low-stock') {
      return (
        <div className="bg-card-theme-bg rounded-2xl border border-rose-100 shadow-xl overflow-hidden animate-in fade-in slide-in-from-top-4 duration-300">
          <div className="p-4 bg-rose-50 border-b border-rose-100 flex items-center justify-between">
            <h3 className="text-sm font-bold text-rose-900 uppercase tracking-wider flex items-center gap-2">
              <AlertCircle size={16} /> Low Stock Products
            </h3>
            <button 
              onClick={() => setSelectedStat(null)}
              className="text-rose-400 hover:text-rose-600 font-bold text-xs p-2 hover:bg-rose-100 rounded-lg transition-colors"
            >
              <X size={18} />
            </button>
          </div>
          <div className="max-h-[400px] overflow-y-auto overflow-x-auto">
            <table className="w-full text-left">
              <thead className="bg-layout-theme-bg sticky top-0 z-10">
                <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">
                  <th className="px-6 py-3">Product</th>
                  <th className="px-6 py-3 text-center">Current Stock</th>
                  <th className="px-6 py-3 text-center">Min Threshold</th>
                  <th className="px-6 py-3 text-center">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-50">
                {lowStockProducts.map((p) => (
                  <tr key={p.id} className="text-sm hover:bg-layout-theme-bg transition-colors">
                    <td className="px-6 py-4">
                      <div className="font-bold text-slate-900">{p.name}</div>
                      <div className="text-[10px] text-slate-400 font-mono">{p.code}</div>
                    </td>
                    <td className="px-6 py-4 text-center font-bold text-rose-600">{p.actualStock}</td>
                    <td className="px-6 py-4 text-center text-slate-500">{p.minStockLevel}</td>
                    <td className="px-6 py-4 text-center">
                      <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase ${
                        p.actualStock === 0 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'
                      }`}>
                        {p.actualStock === 0 ? 'Out of Stock' : 'Low Stock'}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {lowStockProducts.length === 0 && (
              <div className="py-12 text-center">
                <p className="text-slate-400 text-sm italic">No low stock items found</p>
              </div>
            )}
          </div>
        </div>
      );
    }
    
    const stat = stats.find(s => s.id === selectedStat);
    
    return (
      <div className="bg-card-theme-bg rounded-2xl border border-primary-theme-light shadow-xl overflow-hidden animate-in fade-in slide-in-from-top-4 duration-300">
        <div className="p-4 bg-primary-theme-light border-b border-primary-theme-light flex items-center justify-between">
          <h3 className="text-sm font-bold text-primary-theme-dark uppercase tracking-wider flex items-center gap-2">
            {stat?.label} Details
            <span className="text-[10px] bg-primary-theme-light text-primary-theme-hover px-2 py-0.5 rounded-full font-mono">
              {filter === 'all' ? 'All Time' : filter}
            </span>
          </h3>
          <div className="flex items-center gap-2">
            <button 
              onClick={() => exportToCSV()}
              className="flex items-center gap-2 px-3 py-1.5 bg-card-theme-bg border border-primary-theme-light text-primary-theme rounded-xl text-xs font-bold hover:bg-primary-theme-light transition-colors"
            >
              <Download size={14} />
              CSV
            </button>
            <button 
              onClick={() => exportToPDF()}
              className="flex items-center gap-2 px-3 py-1.5 bg-primary-theme text-white rounded-xl text-xs font-bold hover:bg-primary-theme-hover shadow-sm transition-colors"
            >
              <FileText size={14} />
              PDF
            </button>
            <div className="w-px h-6 bg-primary-theme-light mx-1" />
            <button 
              onClick={() => setSelectedStat(null)}
              className="text-primary-theme hover:text-primary-theme font-bold text-xs p-2 hover:bg-primary-theme-light rounded-lg transition-colors"
            >
              <X size={18} />
            </button>
          </div>
        </div>
        <div className="max-h-[400px] md:max-h-[70vh] overflow-y-auto">
          {selectedStat === 'sales' || selectedStat === 'delivered' || selectedStat === 'pending' ? (
            <div>
              {/* Summary Metrics Banner */}
              <div className="p-4 bg-slate-50 border-b border-slate-100 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                <div className="bg-white p-2.5 rounded-xl border border-slate-200">
                  <span className="text-slate-400 font-bold uppercase text-[10px] block">
                    {selectedStat === 'sales' ? 'Total Sales Count' : selectedStat === 'delivered' ? 'Delivered Sales Count' : 'Pending Sales Count'}
                  </span>
                  <span className="text-base font-extrabold text-slate-900 font-mono">
                    {(selectedStat === 'sales' ? filteredData.sales.filter(s => s.status !== 'returned') : 
                      selectedStat === 'delivered' ? deliveredSalesInPeriod :
                      pendingSalesInPeriod.filter(s => s.status !== 'returned')
                    ).length} Order(s)
                  </span>
                </div>
                <div className="bg-white p-2.5 rounded-xl border border-slate-200">
                  <span className="text-slate-400 font-bold uppercase text-[10px] block">
                    Total Value
                  </span>
                  <span className="text-base font-extrabold text-primary-theme font-mono">
                    {settings.currency}{(selectedStat === 'sales' ? filteredData.sales.filter(s => s.status !== 'returned') : 
                      selectedStat === 'delivered' ? deliveredSalesInPeriod :
                      pendingSalesInPeriod.filter(s => s.status !== 'returned')
                    ).reduce((sum, s) => sum + s.totalAmount, 0).toLocaleString()}
                  </span>
                </div>
                <div className="bg-white p-2.5 rounded-xl border border-slate-200 col-span-2 sm:col-span-1">
                  <span className="text-slate-400 font-bold uppercase text-[10px] block">
                    Total Units
                  </span>
                  <span className="text-base font-extrabold text-slate-700 font-mono">
                    {(selectedStat === 'sales' ? filteredData.sales.filter(s => s.status !== 'returned') : 
                      selectedStat === 'delivered' ? deliveredSalesInPeriod :
                      pendingSalesInPeriod.filter(s => s.status !== 'returned')
                    ).reduce((sum, s) => sum + s.items.reduce((iSum, i) => iSum + i.quantity, 0), 0)} Units
                  </span>
                </div>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-left">
                  <thead className="bg-layout-theme-bg sticky top-0 z-10">
                    <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">
                      <th className="px-6 py-3">Product</th>
                      <th className="px-6 py-3">Customer</th>
                      <th className="px-6 py-3">Total Amount</th>
                      <th className="px-6 py-3">Delivery Status</th>
                      <th className="px-6 py-3 text-right">Date</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-50">
                    {(selectedStat === 'sales' ? filteredData.sales.filter(s => s.status !== 'returned') : 
                      selectedStat === 'delivered' ? deliveredSalesInPeriod :
                      pendingSalesInPeriod.filter(s => s.status !== 'returned')
                    ).map((sale) => (
                      <tr key={sale.id} className="text-sm hover:bg-layout-theme-bg transition-colors">
                        <td className="px-6 py-4">
                          <div className="space-y-1">
                            {sale.items.map((item, idx) => (
                              <div key={idx} className="text-xs">
                                <span className="font-mono font-bold text-primary-theme bg-primary-theme-light px-1 rounded">{item.productCode}</span>
                                <span className="text-slate-600 ml-1">- {item.productName}</span>
                                <span className="text-slate-400 ml-1 font-bold">x{item.quantity}</span>
                              </div>
                            ))}
                          </div>
                        </td>
                        <td className="px-6 py-4 font-medium">{sale.customerName}</td>
                        <td className="px-6 py-4 font-bold text-primary-theme">{settings.currency}{sale.totalAmount.toLocaleString()}</td>
                        <td className="px-6 py-4">
                          <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase ${
                            sale.status === 'returned' ? 'bg-rose-100 text-rose-700' :
                            sale.deliveryStatus === 'delivered' ? 'bg-accent-theme-light text-accent-theme' : 'bg-amber-100 text-amber-600'
                          }`}>
                            {sale.status === 'returned' ? 'Delivered (Returned)' : sale.deliveryStatus}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-right text-slate-400 text-xs whitespace-nowrap">{safeFormat(sale.deliveredAt || sale.createdAt, 'MMM dd, HH:mm')}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : selectedStat === 'payments-received' ? (
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead className="bg-layout-theme-bg sticky top-0 z-10">
                  <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">
                    <th className="px-6 py-3">Customer / Order</th>
                    <th className="px-6 py-3 text-center">Payment Method</th>
                    <th className="px-6 py-3 text-right">Amount Received</th>
                    <th className="px-6 py-3">Processed By</th>
                    <th className="px-6 py-3 text-right">Date</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-50">
                  {filteredData.payments.map((p) => {
                    const linkedSale = allSales.find(s => s.id === p.saleId);
                    return (
                      <tr key={p.id} className="text-sm hover:bg-layout-theme-bg transition-colors">
                        <td className="px-6 py-4 font-medium">
                          <div className="font-bold text-slate-900">{linkedSale?.customerName || 'General Customer'}</div>
                          <div className="text-[10px] font-mono text-slate-400">Order #{p.saleId.slice(0, 8)}</div>
                        </td>
                        <td className="px-6 py-4 text-center font-bold text-slate-700">
                          <span className="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-800">
                            {p.method}
                          </span>
                        </td>
                        <td className="px-6 py-4 text-right font-mono font-bold text-emerald-600">{settings.currency}{p.amount.toLocaleString()}</td>
                        <td className="px-6 py-4 text-slate-500 text-xs">{p.userName || 'Staff'}</td>
                        <td className="px-6 py-4 text-right text-slate-400 text-xs whitespace-nowrap">{safeFormat(p.timestamp || p.createdAt, 'MMM dd, HH:mm')}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          ) : selectedStat === 'returns' || selectedStat === 'refunds' ? (
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead className="bg-layout-theme-bg sticky top-0 z-10">
                  <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">
                    <th className="px-6 py-3">Product</th>
                    <th className="px-6 py-3 text-center">Return Qty</th>
                    <th className="px-6 py-3">Refund Value</th>
                    <th className="px-6 py-3">Type / Status</th>
                    <th className="px-6 py-3">Reason</th>
                    <th className="px-6 py-3 text-right">Date</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-50">
                  {(selectedStat === 'returns' 
                    ? filteredData.returns.filter(ret => isStockReturn(ret))
                    : filteredData.returns
                  ).map((ret) => {
                    const isRestocked = isStockReturn(ret);
                    return (
                      <tr key={ret.id} className="text-sm hover:bg-layout-theme-bg transition-colors">
                        <td className="px-6 py-4 font-medium">
                          <span className="font-mono font-bold text-primary-theme bg-primary-theme-light px-1 rounded text-xs mr-2">{ret.productCode}</span>
                          {ret.productName}
                        </td>
                        <td className="px-6 py-4 text-center font-bold text-rose-600">{ret.quantity}</td>
                        <td className="px-6 py-4 font-bold text-primary-theme">{settings.currency}{ret.refundAmount.toLocaleString()}</td>
                        <td className="px-6 py-4 text-xs whitespace-nowrap">
                          {isRestocked ? (
                            <span className="inline-flex items-center gap-1 font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full text-[11px]">
                              Stock Restocked
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full text-[11px]">
                              Refund Only (Undelivered)
                            </span>
                          )}
                        </td>
                        <td className="px-6 py-4 text-slate-500 text-xs italic">{ret.reason}</td>
                        <td className="px-6 py-4 text-right text-slate-400 text-xs whitespace-nowrap">{safeFormat(ret.timestamp, 'MMM dd, HH:mm')}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          ) : selectedStat === 'stock-value' ? (
            <div>
              {/* Summary Metrics Banner */}
              <div className="p-4 bg-indigo-50/50 border-b border-indigo-100 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                <div className="bg-white p-2.5 rounded-xl border border-indigo-100 shadow-2xs">
                  <span className="text-slate-400 font-bold uppercase text-[10px] block">Total Stock Valuation</span>
                  <span className="text-base font-extrabold text-indigo-700 font-mono">
                    {settings.currency}{products.reduce((acc, p) => acc + (p.unitPrice * storage.calculateClosingStock(p.id)), 0).toLocaleString()}
                  </span>
                </div>
                <div className="bg-white p-2.5 rounded-xl border border-indigo-100 shadow-2xs">
                  <span className="text-slate-400 font-bold uppercase text-[10px] block">Active Catalog Products</span>
                  <span className="text-base font-extrabold text-slate-900 font-mono">{products.length} Items</span>
                </div>
                <div className="bg-white p-2.5 rounded-xl border border-indigo-100 shadow-2xs col-span-2 sm:col-span-1">
                  <span className="text-slate-400 font-bold uppercase text-[10px] block">Total Units In Inventory</span>
                  <span className="text-base font-extrabold text-slate-700 font-mono">
                    {products.reduce((acc, p) => acc + storage.calculateClosingStock(p.id), 0)} Units
                  </span>
                </div>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-left">
                  <thead className="bg-layout-theme-bg sticky top-0 z-10">
                    <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">
                      <th className="px-6 py-3">Product</th>
                      <th className="px-6 py-3 text-center">Reorder Level</th>
                      <th className="px-6 py-3 text-center">Closing Stock</th>
                      <th className="px-6 py-3 text-right">Unit Price</th>
                      <th className="px-6 py-3 text-right">Total Valuation</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-50">
                    {products
                      .map(p => {
                        const stock = storage.calculateClosingStock(p.id);
                        const value = p.unitPrice * stock;
                        return { ...p, stock, value };
                      })
                      .sort((a, b) => b.value - a.value)
                      .map((p) => (
                        <tr key={p.id} className="text-sm hover:bg-layout-theme-bg transition-colors">
                          <td className="px-6 py-4 font-medium">
                            <span className="font-mono font-bold text-indigo-600 bg-indigo-50 px-1 rounded text-xs mr-2">{p.code}</span>
                            {p.name}
                          </td>
                          <td className="px-6 py-4 text-center font-mono text-slate-400">{p.minStockLevel}</td>
                          <td className="px-6 py-4 text-center font-mono font-bold text-slate-800">{p.stock}</td>
                          <td className="px-6 py-4 text-right font-mono text-slate-600">{settings.currency}{p.unitPrice.toLocaleString()}</td>
                          <td className="px-6 py-4 text-right font-mono font-bold text-indigo-700">{settings.currency}{p.value.toLocaleString()}</td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
            </div>
          ) : selectedStat === 'closing-stock' ? (
            <div>
              {/* Summary Metrics Banner */}
              <div className="p-4 bg-slate-100/60 border-b border-slate-200 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                <div className="bg-white p-2.5 rounded-xl border border-slate-200 shadow-2xs">
                  <span className="text-slate-400 font-bold uppercase text-[10px] block">Total Closing Stock</span>
                  <span className="text-base font-extrabold text-slate-900 font-mono">
                    {products.reduce((acc, p) => acc + storage.calculateClosingStock(p.id), 0)} Units
                  </span>
                </div>
                <div className="bg-white p-2.5 rounded-xl border border-slate-200 shadow-2xs">
                  <span className="text-slate-400 font-bold uppercase text-[10px] block">Active Catalog Products</span>
                  <span className="text-base font-extrabold text-slate-900 font-mono">{products.length} Items</span>
                </div>
                <div className="bg-white p-2.5 rounded-xl border border-slate-200 shadow-2xs col-span-2 sm:col-span-1">
                  <span className="text-slate-400 font-bold uppercase text-[10px] block">Low / Out of Stock</span>
                  <span className="text-base font-extrabold text-rose-600 font-mono">
                    {products.filter(p => storage.calculateClosingStock(p.id) <= p.minStockLevel).length} Products
                  </span>
                </div>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-left">
                  <thead className="bg-layout-theme-bg sticky top-0 z-10">
                    <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">
                      <th className="px-6 py-3">Product</th>
                      <th className="px-6 py-3 text-center">Min Level</th>
                      <th className="px-6 py-3 text-center">Closing Stock Units</th>
                      <th className="px-6 py-3 text-right">Unit Price</th>
                      <th className="px-6 py-3 text-center">Stock Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-50">
                    {products.map((p) => {
                      const stock = storage.calculateClosingStock(p.id);
                      const isZero = stock === 0;
                      const isLow = stock <= p.minStockLevel;
                      return (
                        <tr key={p.id} className="text-sm hover:bg-layout-theme-bg transition-colors">
                          <td className="px-6 py-4 font-medium">
                            <span className="font-mono font-bold text-slate-700 bg-slate-100 px-1 rounded text-xs mr-2">{p.code}</span>
                            {p.name}
                          </td>
                          <td className="px-6 py-4 text-center font-mono text-slate-400">{p.minStockLevel}</td>
                          <td className={`px-6 py-4 text-center font-mono font-bold ${isZero ? 'text-rose-600' : isLow ? 'text-amber-600' : 'text-slate-900'}`}>
                            {stock}
                          </td>
                          <td className="px-6 py-4 text-right font-mono text-slate-600">{settings.currency}{p.unitPrice.toLocaleString()}</td>
                          <td className="px-6 py-4 text-center">
                            <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase ${
                              isZero ? 'bg-rose-100 text-rose-700' : isLow ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'
                            }`}>
                              {isZero ? 'Out of Stock' : isLow ? 'Low Stock' : 'Healthy'}
                            </span>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          ) : selectedStat === 'reconciliation' ? (
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead className="bg-layout-theme-bg sticky top-0 z-10">
                  <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">
                    <th className="px-6 py-3">Product</th>
                    <th className="px-6 py-3 text-center">Opening</th>
                    <th className="px-6 py-3 text-center text-accent-theme">In</th>
                    <th className="px-6 py-3 text-center text-primary-theme">Ret</th>
                    <th className="px-6 py-3 text-center text-orange-600">Out</th>
                    <th className="px-6 py-3 text-center text-rose-600">Sold</th>
                    <th className="px-6 py-3 text-center font-bold">Closing</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-50">
                  {reconciliationData.map((row) => (
                    <tr key={row.id} className="text-sm hover:bg-layout-theme-bg transition-colors">
                      <td className="px-6 py-4 font-medium">
                        <span className="font-mono font-bold text-primary-theme bg-primary-theme-light px-1 rounded text-xs mr-2">{row.code}</span>
                        {row.name}
                      </td>
                      <td className="px-6 py-4 text-center font-mono text-slate-500">{row.openingStock}</td>
                      <td className="px-6 py-4 text-center font-mono text-accent-theme">+{row.stockIn}</td>
                      <td className="px-6 py-4 text-center font-mono text-primary-theme">+{row.returns}</td>
                      <td className="px-6 py-4 text-center font-mono text-orange-600">-{row.stockOut}</td>
                      <td className="px-6 py-4 text-center font-mono text-rose-600">-{row.delivered}</td>
                      <td className="px-6 py-4 text-center font-mono font-bold">{row.closingStock}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead className="bg-layout-theme-bg sticky top-0 z-10">
                  <tr className="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">
                    <th className="px-6 py-3">Product</th>
                    <th className="px-6 py-3 text-center">{selectedStat === 'stock-in' ? 'Added' : 'Removed'} Qty</th>
                    <th className="px-6 py-3">Activity Description</th>
                    <th className="px-6 py-3">Processed By</th>
                    <th className="px-6 py-3 text-right">Date</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-50">
                  {filteredData.logs.filter(l => l.type === selectedStat).map((log) => (
                    <tr key={log.id} className="text-sm hover:bg-layout-theme-bg transition-colors">
                      <td className="px-6 py-4 font-medium">
                        <span className="font-mono font-bold text-primary-theme bg-primary-theme-light px-1 rounded text-xs mr-2">{log.productCode}</span>
                        {log.productName}
                      </td>
                      <td className="px-6 py-4 text-center font-bold text-accent-theme">{log.quantity}</td>
                      <td className="px-6 py-4 text-slate-500 text-xs">{log.description}</td>
                      <td className="px-6 py-4 text-slate-400 text-xs">{log.userName}</td>
                      <td className="px-6 py-4 text-right text-slate-400 text-xs whitespace-nowrap">{safeFormat(log.timestamp, 'MMM dd, HH:mm')}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          {((selectedStat === 'sales' && filteredData.sales.filter(s => s.status !== 'returned').length === 0) ||
            (selectedStat === 'delivered' && deliveredSalesInPeriod.length === 0) ||
            (selectedStat === 'pending' && pendingSalesInPeriod.filter(s => s.status !== 'returned').length === 0) ||
            (selectedStat === 'returns' && filteredData.returns.filter(ret => isStockReturn(ret)).length === 0) ||
            (selectedStat === 'refunds' && filteredData.returns.length === 0) ||
            ((selectedStat === 'stock-in' || selectedStat === 'stock-out') && filteredData.logs.filter(l => l.type === selectedStat).length === 0) ||
            ((selectedStat === 'stock-value' || selectedStat === 'closing-stock') && products.length === 0)
          ) && (
            <div className="py-12 text-center">
              <p className="text-slate-400 text-sm font-medium italic">No records found</p>
            </div>
          )}
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-8">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900">Dashboard Overview</h2>
          <p className="text-slate-500">Real-time performance analytics for {settings.businessName}</p>
        </div>
        
        <div className="flex flex-col items-end gap-3">
          <div className="flex items-center gap-2 bg-card-theme-bg p-1.5 border border-slate-200 rounded-2xl shadow-sm">
            <Filter size={16} className="text-slate-400 ml-2" />
            <div className="flex gap-1 overflow-x-auto no-scrollbar max-w-[80vw]">
              {(['today', 'yesterday', 'thisWeek', '7days', 'thisMonth', '30days', 'thisYear', 'custom', 'all'] as FilterType[]).map((t) => (
                <button
                  key={t}
                  onClick={() => setFilter(t)}
                  className={`px-4 py-1.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer ${
                    filter === t 
                      ? 'bg-primary-theme text-white shadow-md' 
                      : 'text-slate-500 hover:bg-slate-100'
                  }`}
                >
                  {t === 'today' ? 'Today (Default)' :
                   t === 'yesterday' ? 'Yesterday' :
                   t === 'thisWeek' ? 'This Week' :
                   t === '7days' ? 'Last 7 Days' :
                   t === 'thisMonth' ? 'This Month' :
                   t === '30days' ? 'Last 30 Days' :
                   t === 'thisYear' ? 'This Year' :
                   t === 'custom' ? 'Custom' : 'All Time'}
                </button>
              ))}
            </div>
          </div>

          {filter === 'custom' && (
            <div className="flex items-center gap-3 bg-card-theme-bg p-2 border border-primary-theme-light rounded-2xl shadow-lg animate-in fade-in slide-in-from-top-2">
              <div className="flex items-center gap-2">
                <Calendar size={14} className="text-slate-400" />
                <input 
                  type="date"
                  value={customRange.start}
                  onChange={(e) => setCustomRange({ ...customRange, start: e.target.value })}
                  className="text-xs font-bold text-slate-600 border-none focus:ring-0 p-0 cursor-pointer"
                />
              </div>
              <span className="text-slate-300">to</span>
              <div className="flex items-center gap-2">
                <Calendar size={14} className="text-slate-400" />
                <input 
                  type="date"
                  value={customRange.end}
                  onChange={(e) => setCustomRange({ ...customRange, end: e.target.value })}
                  className="text-xs font-bold text-slate-600 border-none focus:ring-0 p-0 cursor-pointer"
                />
              </div>
            </div>
          )}
        </div>
      </div>

      {lowStockProducts.length > 0 && (
        <div className="bg-rose-50 border border-rose-200 rounded-2xl p-4 flex items-start gap-4 animate-in fade-in slide-in-from-left-4 duration-500">
          <div className="bg-rose-100 p-2 rounded-xl text-rose-600">
            <AlertCircle size={24} />
          </div>
          <div className="flex-1">
            <h4 className="text-sm font-bold text-rose-900 uppercase tracking-wide">Critical Stock Alert</h4>
            <p className="text-sm text-rose-700 mt-1">
              There are <strong>{lowStockProducts.length}</strong> products currently below their minimum stock threshold. 
              {lowStockProducts.some(p => p.actualStock === 0) && " Some items are completely out of stock."}
            </p>
            <button 
              onClick={() => setSelectedStat('low-stock')}
              className="mt-3 text-xs font-bold text-rose-800 bg-rose-200 px-3 py-1.5 rounded-lg hover:bg-rose-300 transition-colors"
            >
              View Low Stock Items
            </button>
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-4">
        {stats.map((stat, i) => (
          <div key={stat.id} className="contents">
            <button 
              onClick={() => setSelectedStat(selectedStat === stat.id ? null : stat.id)}
              className={`bg-card-theme-bg p-5 rounded-2xl border transition-all hover:shadow-md text-left group ${
                selectedStat === stat.id 
                  ? 'border-primary-theme ring-2 ring-primary-theme-light shadow-sm' 
                  : 'border-slate-200 shadow-sm hover:border-primary-theme-light'
              }`}
            >
              <div className="flex flex-col gap-3">
                <div className={`${stat.color} w-10 h-10 rounded-xl text-white shadow-sm flex items-center justify-center transition-transform group-hover:scale-110`}>
                  <stat.icon size={20} />
                </div>
                <div>
                  <span className="text-slate-400 font-bold text-[9px] uppercase tracking-wider block mb-1">{stat.label}</span>
                  <div className="text-base font-bold text-slate-900 truncate">{stat.value}</div>
                </div>
              </div>
            </button>
            {selectedStat === stat.id && (
              <div className="md:hidden col-span-1">
                {renderDetails()}
              </div>
            )}
          </div>
        ))}
      </div>

      {selectedStat && (
        <div className="hidden md:flex fixed inset-0 z-50 items-center justify-center p-6 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-300">
          <div className="w-full max-w-5xl shadow-2xl animate-in zoom-in-95 duration-300">
            {renderDetails()}
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div className="bg-card-theme-bg p-6 rounded-2xl border border-slate-200 shadow-sm h-[400px]">
          <h3 className="text-lg font-bold text-slate-900 mb-6">
            Sales Performance ({filter === 'all' ? 'All Time' : filter === 'today' ? 'Today' : filter === 'yesterday' ? 'Yesterday' : filter === 'thisMonth' ? 'This Month' : filter === 'thisYear' ? 'This Year' : 'Period'})
          </h3>
          <ResponsiveContainer width="100%" height="80%">
            <BarChart data={salesData}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} />
              <XAxis dataKey="name" />
              <YAxis />
              <Tooltip />
              <Bar dataKey="sales" fill="#2563eb" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        <div className="bg-card-theme-bg p-6 rounded-2xl border border-slate-200 shadow-sm">
          <h3 className="text-lg font-bold text-slate-900 mb-6">Low Stock Alerts</h3>
          <div className="space-y-4">
            {lowStockProducts.length === 0 ? (
              <p className="text-slate-500 text-center py-8 italic">All stock levels are healthy</p>
            ) : (
              lowStockProducts
                .slice(0, 5)
                .map((product) => (
                  <div key={product.id} className="flex items-center justify-between p-3 bg-amber-50 rounded-lg border border-amber-100">
                    <div>
                      <div className="font-bold text-amber-900 text-sm">{product.name}</div>
                      <div className="text-xs text-amber-700">Stock: {product.actualStock} / Min: {product.minStockLevel}</div>
                    </div>
                    <AlertCircle className="text-amber-500" size={20} />
                  </div>
                ))
            )}
          </div>
        </div>
      </div>
      <div className="bg-card-theme-bg rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="p-6 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h3 className="text-lg font-bold text-slate-900 flex items-center gap-2">
              <Calculator size={20} className="text-primary-theme" />
              Stock Reconciliation
              <span className="ml-2 text-xs font-medium text-slate-400">
                ({filter === 'all' ? 'All Time' : 
                  filter === 'today' ? format(new Date(), 'MMM dd, yyyy') :
                  filter === 'yesterday' ? format(subDays(new Date(), 1), 'MMM dd, yyyy') :
                  filter === 'custom' && customRange.start ? `${customRange.start} to ${customRange.end || 'Now'}` : 
                  filter})
              </span>
            </h3>
            <p className="text-xs text-slate-500 mt-1">Formula: Opening + In + Returns - Out - Delivered</p>
          </div>
          <div className="flex items-center gap-2">
            <button 
              onClick={() => exportToCSV('reconciliation')}
              className="flex items-center gap-2 px-3 py-1.5 bg-card-theme-bg border border-slate-200 text-slate-600 rounded-xl text-xs font-bold hover:bg-layout-theme-bg transition-colors"
            >
              <Download size={14} />
              CSV
            </button>
            <button 
              onClick={() => exportToPDF('reconciliation')}
              className="flex items-center gap-2 px-3 py-1.5 bg-primary-theme text-white rounded-xl text-xs font-bold hover:bg-primary-theme-hover shadow-sm transition-colors"
            >
              <FileText size={14} />
              PDF
            </button>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-layout-theme-bg text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                <th className="px-6 py-4">Product Name</th>
                <th className="px-6 py-4 text-center">Opening Stock</th>
                <th className="px-6 py-4 text-center text-accent-theme">Stock In (+)</th>
                <th className="px-6 py-4 text-center text-primary-theme">Stock Return (+)</th>
                <th className="px-6 py-4 text-center text-rose-600">Stock Out (-)</th>
                <th className="px-6 py-4 text-center text-amber-600">Delivered (-)</th>
                <th className="px-6 py-4 text-right font-bold text-slate-900">Closing Stock</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {reconciliationData.map((row, i) => (
                <tr key={i} className="hover:bg-layout-theme-bg transition-colors">
                  <td className="px-6 py-4 font-medium text-slate-900">
                    <span className="text-[10px] text-slate-400 font-mono block mb-0.5">{row.code}</span>
                    {row.name}
                  </td>
                  <td className="px-6 py-4 text-center font-mono text-slate-500">{row.openingStock}</td>
                  <td className="px-6 py-4 text-center font-mono">{row.stockIn}</td>
                  <td className="px-6 py-4 text-center font-mono">{row.returns}</td>
                  <td className="px-6 py-4 text-center font-mono">{row.stockOut}</td>
                  <td className="px-6 py-4 text-center font-mono">{row.delivered}</td>
                  <td className="px-6 py-4 text-right font-mono font-bold text-primary-theme">
                    {row.closingStock}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
