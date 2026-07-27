/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useMemo, useEffect, useRef } from 'react';
import { 
  Plus, 
  Search, 
  ShoppingCart, 
  Trash2, 
  CreditCard, 
  RotateCcw, 
  CheckCircle2, 
  Package, 
  User as UserIcon,
  ChevronRight,
  Printer,
  X,
  Download,
  FileText,
  Truck,
  AlertCircle,
  Clock,
  Coins,
  History,
  Filter,
  Calendar
} from 'lucide-react';
import { storage } from '../lib/storage';
import { DateFilterOption, matchesDateFilter } from '../lib/dateUtils';
import { Product, User, Sale, SaleItem, SaleStatus, DeliveryStatus, SalesReturn, Payment } from '../types';
import { hasModulePermission } from '../lib/rbac';
import ReceiptModal from './ReceiptModal';

interface SalesProps {
  user: User;
}

export default function Sales({ user }: SalesProps) {
  const [settings] = useState(storage.getSettings());
  const [view, setView] = useState<'list' | 'pos'>('list');
  const [subTab, setSubTab] = useState<'sales' | 'returns'>('sales');
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (containerRef.current) {
      containerRef.current.scrollTo({ top: 0 });
    }
    window.scrollTo({ top: 0 });
  }, [subTab, view]);
  const [products, setProducts] = useState<Product[]>(storage.getProducts().filter(p => !p.archived));
  const [sales, setSales] = useState<Sale[]>(storage.getSales());
  const [returns, setReturns] = useState<SalesReturn[]>(storage.getReturns());
  const [payments, setPayments] = useState<Payment[]>(storage.getPayments());

  useEffect(() => {
    const refreshData = () => {
      setProducts(storage.getProducts().filter(p => !p.archived));
      setSales(storage.getSales());
      setReturns(storage.getReturns());
      setPayments(storage.getPayments());
    };
    refreshData();
    window.addEventListener('hysam-data-updated', refreshData);
    window.addEventListener('hysam-sync-end', refreshData);
    return () => {
      window.removeEventListener('hysam-data-updated', refreshData);
      window.removeEventListener('hysam-sync-end', refreshData);
    };
  }, [subTab]);

  // Helper to accurately derive paid amount for any sale from payments log or sale fallback
  const getSalePaidAmount = (sale: Sale, paymentList: Payment[]) => {
    const salePayments = paymentList.filter(p => p.saleId === sale.id);
    const paidFromPayments = salePayments.reduce((acc, p) => acc + p.amount, 0);
    const basePaid = sale.paidAmount !== undefined 
      ? sale.paidAmount 
      : ((sale.cashAmount || 0) + (sale.posAmount || 0));
    return Math.max(basePaid, paidFromPayments);
  };

  // Sales History Date, Status Filter & Search State
  const [dateFilter, setDateFilter] = useState<DateFilterOption>('today');
  const [startDate, setStartDate] = useState<string>('');
  const [endDate, setEndDate] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'completed' | 'installment' | 'returned'>('all');
  const [historySearch, setHistorySearch] = useState('');

  // Installment Payment Modal State
  const [payInstallmentSale, setPayInstallmentSale] = useState<Sale | null>(null);
  const [installmentPayAmount, setInstallmentPayAmount] = useState<number>(0);
  const [installmentPayMethod, setInstallmentPayMethod] = useState<string>('Cash');
  const [isSubmittingInstallment, setIsSubmittingInstallment] = useState(false);
  const [showInstallmentConfirm, setShowInstallmentConfirm] = useState(false);

  // Fine-grained permissions
  const canCreate = hasModulePermission(user, 'sales', 'create');
  const canEdit = hasModulePermission(user, 'sales', 'edit');
  const canDelete = hasModulePermission(user, 'sales', 'delete');
  
  // Return processing states
  const [saleToReturn, setSaleToReturn] = useState<Sale | null>(null);
  const [returnProductId, setReturnProductId] = useState<string>('');
  const [returnQty, setReturnQty] = useState<number>(1);
  const [returnRefundAmount, setReturnRefundAmount] = useState<number>(0);
  const [returnReason, setReturnReason] = useState<string>('');
  
  // POS State
  const [cart, setCart] = useState<SaleItem[]>([]);
  const [customerName, setCustomerName] = useState('');
  const [paymentType, setPaymentType] = useState<SaleStatus>('completed');
  const [search, setSearch] = useState('');
  const [cashAmount, setCashAmount] = useState<number>(0);
  const [posAmount, setPosAmount] = useState<number>(0);
  const [note, setNote] = useState('');

  const [isConfirmingOrder, setIsConfirmingOrder] = useState(false);
  const [isSubmittingOrder, setIsSubmittingOrder] = useState(false);
  const [submitStatus, setSubmitStatus] = useState<'idle' | 'success' | 'error'>('idle');
  const [receiptSale, setReceiptSale] = useState<Sale | null>(null);
  const [receiptFormat, setReceiptFormat] = useState<'thermal' | 'a4' | 'invoice'>('thermal');

  const updateCartItemQty = (productId: string, qty: number) => {
    const product = products.find(p => p.id === productId);
    if (!product) return;
    
    const closingStock = storage.calculateClosingStock(productId);
    const finalQty = Math.min(closingStock, qty > 0 ? qty : 0.001);

    setCart(cart.map(item => 
      item.productId === productId 
        ? { ...item, quantity: finalQty, totalPrice: finalQty * item.unitPrice }
        : item
    ));
  };

  const updateCartItemPrice = (productId: string, price: number) => {
    setCart(cart.map(item => 
      item.productId === productId 
        ? { ...item, unitPrice: price, totalPrice: item.quantity * price }
        : item
    ));
  };

  const cartTotal = useMemo(() => {
    return cart.reduce((acc, item) => acc + item.totalPrice, 0);
  }, [cart]);

  // Filtered Sales History & Status Counts
  const filteredSales = useMemo(() => {
    return sales.filter(s => {
      if (!matchesDateFilter(s.createdAt, dateFilter, startDate, endDate)) return false;

      const query = historySearch.toLowerCase().trim();
      const matchesSearch = !query || 
        s.id.toLowerCase().includes(query) ||
        s.customerName.toLowerCase().includes(query) ||
        (s.note && s.note.toLowerCase().includes(query)) ||
        s.items.some(i => i.productName.toLowerCase().includes(query));

      if (!matchesSearch) return false;

      const paid = getSalePaidAmount(s, payments);
      const isCompleted = s.status === 'completed' || (s.status !== 'returned' && paid >= s.totalAmount - 0.01);
      const isInstallment = s.status === 'installment' && paid < s.totalAmount - 0.01;
      const isReturned = s.status === 'returned';

      if (statusFilter === 'all') return true;
      if (statusFilter === 'completed') return isCompleted;
      if (statusFilter === 'installment') return isInstallment;
      if (statusFilter === 'returned') return isReturned;
      return true;
    });
  }, [sales, payments, historySearch, statusFilter, dateFilter, startDate, endDate]);

  const statusCounts = useMemo(() => {
    let completed = 0;
    let installment = 0;
    let returned = 0;
    let totalInPeriod = 0;

    sales.forEach(s => {
      if (!matchesDateFilter(s.createdAt, dateFilter, startDate, endDate)) return;
      totalInPeriod++;
      const paid = getSalePaidAmount(s, payments);
      if (s.status === 'returned') {
        returned++;
      } else if (s.status === 'completed' || paid >= s.totalAmount - 0.01) {
        completed++;
      } else if (s.status === 'installment') {
        installment++;
      }
    });

    return {
      all: totalInPeriod,
      completed,
      installment,
      returned
    };
  }, [sales, payments, dateFilter, startDate, endDate]);

  const filteredReturns = useMemo(() => {
    return returns.filter(r => {
      if (!matchesDateFilter(r.createdAt, dateFilter, startDate, endDate)) return false;

      const query = historySearch.toLowerCase().trim();
      if (!query) return true;
      return (
        r.id.toLowerCase().includes(query) ||
        r.saleId.toLowerCase().includes(query) ||
        r.customerName.toLowerCase().includes(query) ||
        r.code.toLowerCase().includes(query) ||
        r.productName.toLowerCase().includes(query) ||
        (r.reason && r.reason.toLowerCase().includes(query))
      );
    });
  }, [returns, dateFilter, startDate, endDate, historySearch]);

  const handleRecordInstallmentPayment = async () => {
    if (!payInstallmentSale || installmentPayAmount <= 0) return;

    const currentPaid = getSalePaidAmount(payInstallmentSale, payments);
    const balanceRemaining = Math.max(0, payInstallmentSale.totalAmount - currentPaid);

    if (installmentPayAmount > balanceRemaining + 0.01) {
      alert(`Payment amount (${settings.currency}${installmentPayAmount.toLocaleString()}) cannot exceed remaining balance (${settings.currency}${balanceRemaining.toLocaleString()})`);
      return;
    }

    if (!showInstallmentConfirm) {
      setShowInstallmentConfirm(true);
      return;
    }

    setIsSubmittingInstallment(true);
    try {
      const newTotalPaid = currentPaid + installmentPayAmount;
      const isFullyPaid = newTotalPaid >= payInstallmentSale.totalAmount - 0.01;
      const newStatus: SaleStatus = isFullyPaid ? 'completed' : 'installment';

      // 1. Save new payment record
      const newPayment: Payment = {
        id: 'PAY-' + Math.random().toString(36).substr(2, 9).toUpperCase(),
        saleId: payInstallmentSale.id,
        amount: installmentPayAmount,
        method: installmentPayMethod,
        timestamp: new Date().toISOString(),
        recordedBy: user.id
      };
      const existingPayments = storage.getPayments();
      const updatedPaymentsList = [newPayment, ...existingPayments];
      storage.savePayments(updatedPaymentsList);
      setPayments(updatedPaymentsList);

      // 2. Update sale status and paidAmount in storage
      const existingSales = storage.getSales();
      const updatedSales = existingSales.map(s => {
        if (s.id === payInstallmentSale.id) {
          return {
            ...s,
            paidAmount: newTotalPaid,
            status: newStatus
          };
        }
        return s;
      });
      storage.saveSales(updatedSales);
      setSales(updatedSales);

      // 3. Log activity
      storage.logActivity({
        type: 'payment',
        description: `Installment payment of ${settings.currency}${installmentPayAmount.toLocaleString()} received for order #${payInstallmentSale.id} (${payInstallmentSale.customerName}). Total Paid: ${settings.currency}${newTotalPaid.toLocaleString()}. Status: ${newStatus.toUpperCase()}`,
        userId: user.id,
        userName: user.name
      });

      // 4. Remote sync
      await storage.sync();

      setPayInstallmentSale(null);
      setInstallmentPayAmount(0);
      setShowInstallmentConfirm(false);
    } catch (err) {
      console.error('Failed to process payment:', err);
      alert('Payment saved locally. Remote sync will complete in background.');
      setPayInstallmentSale(null);
      setShowInstallmentConfirm(false);
    } finally {
      setIsSubmittingInstallment(false);
    }
  };

  // Auto select payment type (Full Payment vs Installment) based on amounts
  useEffect(() => {
    const paidTotal = cashAmount + posAmount;
    if (cartTotal > 0 && Math.abs(paidTotal - cartTotal) < 0.01) {
      setPaymentType('completed');
    } else {
      setPaymentType('installment');
    }
  }, [cashAmount, posAmount, cartTotal]);

  const violations = useMemo(() => {
    const list: string[] = [];
    if (!customerName.trim()) {
      list.push("Customer Name is required.");
    }
    if (cart.length === 0) {
      list.push("Cart is empty.");
    }
    if (cashAmount < 0) {
      list.push(`Cash amount cannot be less than ${settings.currency}0.`);
    }
    if (posAmount < 0) {
      list.push(`POS amount cannot be less than ${settings.currency}0.`);
    }
    const currentSum = cashAmount + posAmount;
    if (currentSum > cartTotal + 0.01) {
      list.push(`Total payment (${settings.currency}${currentSum.toLocaleString()}) cannot exceed the order total (${settings.currency}${cartTotal.toLocaleString()}). Overpaid by: ${settings.currency}${(currentSum - cartTotal).toLocaleString()}`);
    }
    return list;
  }, [customerName, cart, cashAmount, posAmount, cartTotal, settings.currency]);

  const filteredProducts = useMemo(() => {
    const query = search.trim().toLowerCase();
    const inStock = products.filter(p => storage.calculateClosingStock(p.id) > 0);
    if (!query) {
      return inStock;
    }
    return inStock.filter(p => {
      const codeMatch = p.code ? p.code.toLowerCase().includes(query) : false;
      const nameMatch = p.name ? p.name.toLowerCase().includes(query) : false;
      const brandMatch = p.brand ? p.brand.toLowerCase().includes(query) : false;
      const sizeMatch = p.size ? p.size.toLowerCase().includes(query) : false;
      const categoryMatch = p.category ? p.category.toLowerCase().includes(query) : false;
      const descMatch = p.description ? p.description.toLowerCase().includes(query) : false;

      return codeMatch || nameMatch || brandMatch || sizeMatch || categoryMatch || descMatch;
    });
  }, [products, search]);

  const addToCart = (product: Product) => {
    const existing = cart.find(item => item.productId === product.id);
    const closingStock = storage.calculateClosingStock(product.id);
    
    if (existing) {
      if (existing.quantity >= closingStock) return;
      setCart(cart.map(item => 
        item.productId === product.id 
          ? { ...item, quantity: item.quantity + 1, totalPrice: (item.quantity + 1) * item.unitPrice }
          : item
      ));
    } else {
      if (closingStock <= 0) return;
      setCart([...cart, {
        productId: product.id,
        productName: product.name,
        quantity: 1,
        unitPrice: product.unitPrice,
        totalPrice: product.unitPrice
      }]);
    }
  };

  const removeFromCart = (productId: string) => {
    setCart(cart.filter(item => item.productId !== productId));
  };

  const handleCompleteSale = () => {
    if (!customerName || cart.length === 0) {
      alert('Please enter customer name and add items to cart');
      return;
    }

    const newSale: Sale = {
      id: Math.random().toString(36).substr(2, 9),
      customerName,
      items: cart,
      totalAmount: cartTotal,
      paidAmount: paymentType === 'completed' ? cartTotal : (cashAmount + posAmount),
      cashAmount,
      posAmount,
      note,
      status: paymentType,
      deliveryStatus: 'pending',
      deliveredAt: undefined,
      deliveredBy: undefined,
      userId: user.id,
      createdAt: new Date().toISOString()
    };

    const allSales = [newSale, ...storage.getSales()];
    storage.saveSales(allSales);
    storage.logActivity({
      type: 'sale',
      description: `Sale recorded for ${customerName} - Total: ${settings.currency}${cartTotal.toLocaleString()} (Cash: ${settings.currency}${cashAmount}, POS: ${settings.currency}${posAmount})`,
      userId: user.id,
      userName: user.name
    });
    setSales(allSales);

    // If there is any payment, record it
    if (cashAmount > 0 || posAmount > 0) {
      const payments = storage.getPayments();
      const newPayments = [];
      
      if (cashAmount > 0) {
        newPayments.push({
          id: Math.random().toString(36).substr(2, 9),
          saleId: newSale.id,
          amount: cashAmount,
          method: 'Cash',
          timestamp: new Date().toISOString(),
          recordedBy: user.id
        });
      }
      
      if (posAmount > 0) {
        newPayments.push({
          id: Math.random().toString(36).substr(2, 9),
          saleId: newSale.id,
          amount: posAmount,
          method: 'POS',
          timestamp: new Date().toISOString(),
          recordedBy: user.id
        });
      }
      
      const existingPayments = storage.getPayments();
      const updatedPayments = [...newPayments, ...existingPayments];
      storage.savePayments(updatedPayments);
      setPayments(updatedPayments);
    }

    setCart([]);
    setCustomerName('');
    setCashAmount(0);
    setPosAmount(0);
    setNote('');
    setView('list');
    setReceiptSale(newSale);
  };

  const startReturnFlow = (sale: Sale) => {
    setSaleToReturn(sale);
    const isDelivered = sale.deliveryStatus === 'delivered';
    if (isDelivered) {
      if (sale.items.length > 0) {
        const firstItem = sale.items[0];
        setReturnProductId(firstItem.productId);
        setReturnQty(firstItem.quantity);
        setReturnRefundAmount(firstItem.totalPrice);
      } else {
        setReturnProductId('');
        setReturnQty(1);
        setReturnRefundAmount(0);
      }
    } else {
      setReturnProductId(sale.items[0]?.productId || '');
      setReturnQty(0);
      setReturnRefundAmount(sale.paidAmount > 0 ? sale.paidAmount : sale.totalAmount);
    }
    setReturnReason('');
  };

  const [isConfirmingReturn, setIsConfirmingReturn] = useState(false);

  const handleProcessReturn = () => {
    if (!saleToReturn || !returnReason.trim()) return;
    const isDelivered = saleToReturn.deliveryStatus === 'delivered';
    if (isDelivered) {
      const item = saleToReturn.items.find(i => i.productId === returnProductId);
      if (!item || returnQty <= 0 || returnQty > item.quantity) return;
      const maxRefund = Math.min(item.totalPrice, Math.max(0, returnQty) * item.unitPrice);
      if (returnRefundAmount < 0 || returnRefundAmount > maxRefund + 0.01) return;
    } else {
      const maxRefund = saleToReturn.paidAmount > 0 ? saleToReturn.paidAmount : saleToReturn.totalAmount;
      if (returnRefundAmount < 0 || returnRefundAmount > maxRefund + 0.01) return;
    }
    setIsConfirmingReturn(true);
  };

  const confirmProcessReturn = () => {
    if (!saleToReturn) return;

    const wasDelivered = saleToReturn.deliveryStatus === 'delivered';

    if (wasDelivered) {
      if (!returnProductId) return;
      const returnItem = saleToReturn.items.find(i => i.productId === returnProductId);
      if (!returnItem) return;

      const maxQty = returnItem.quantity;
      const maxRefund = Math.min(returnItem.totalPrice, Math.max(0, returnQty) * returnItem.unitPrice);
      if (returnQty < 1 || returnQty > maxQty) return;
      if (returnRefundAmount < 0 || returnRefundAmount > maxRefund + 0.01) return;

      const p = products.find(prod => prod.id === returnProductId);
      const productCode = p?.code || returnItem.productCode || 'N/A';
      const productName = p?.name || returnItem.productName;

      // Create the Return Record for Delivered Item
      const newReturn: SalesReturn = {
        id: 'RET-' + Math.random().toString(36).substr(2, 6).toUpperCase(),
        saleId: saleToReturn.id,
        customerName: saleToReturn.customerName,
        code: productCode,
        productId: returnProductId,
        productName: productName,
        quantity: returnQty,
        refundAmount: returnRefundAmount,
        reason: returnReason,
        createdAt: new Date().toISOString(),
        userId: user.id,
        wasDelivered: true,
        deliveryStatus: 'delivered'
      };

      const updatedReturns = [newReturn, ...returns];
      storage.saveReturns(updatedReturns);
      setReturns(updatedReturns);

      // Update Stock for delivered returns
      const currentProducts = storage.getProducts();
      const updatedProducts = currentProducts.map(prod => {
        if (prod.id === returnProductId) {
          return { ...prod, currentStock: prod.currentStock + returnQty };
        }
        return prod;
      });
      storage.saveProducts(updatedProducts);
      setProducts(updatedProducts);

      // Update Sale Status
      const currentSales = storage.getSales();
      const updatedSales = currentSales.map(s => {
        if (s.id === saleToReturn.id) {
          return { 
            ...s, 
            status: 'returned' as SaleStatus,
            returnReason: returnReason
          };
        }
        return s;
      });
      storage.saveSales(updatedSales);
      setSales(updatedSales);

      storage.logActivity({
        type: 'return',
        description: `Stock Return processed for delivered order #${saleToReturn.id} (${saleToReturn.customerName}) - Item: ${productName}, Qty: ${returnQty}, Refund: ${settings.currency}${returnRefundAmount.toLocaleString()} (Delivered & Restocked)`,
        userId: user.id,
        userName: user.name
      });
    } else {
      // Undelivered Order Refund
      const maxRefund = saleToReturn.paidAmount > 0 ? saleToReturn.paidAmount : saleToReturn.totalAmount;
      if (returnRefundAmount < 0 || returnRefundAmount > maxRefund + 0.01) return;

      const summaryItems = saleToReturn.items.map(i => `${i.productName} (x${i.quantity})`).join(', ');
      const firstCode = saleToReturn.items[0]?.productCode || saleToReturn.items[0]?.code || 'N/A';
      const firstProdId = saleToReturn.items[0]?.productId || '';

      const newReturn: SalesReturn = {
        id: 'REF-' + Math.random().toString(36).substr(2, 6).toUpperCase(),
        saleId: saleToReturn.id,
        customerName: saleToReturn.customerName,
        code: firstCode,
        productId: firstProdId,
        productName: summaryItems || 'Undelivered Order Items',
        quantity: 0,
        refundAmount: returnRefundAmount,
        reason: returnReason,
        createdAt: new Date().toISOString(),
        userId: user.id,
        wasDelivered: false,
        deliveryStatus: saleToReturn.deliveryStatus
      };

      const updatedReturns = [newReturn, ...returns];
      storage.saveReturns(updatedReturns);
      setReturns(updatedReturns);

      // Update Sale Status
      const currentSales = storage.getSales();
      const updatedSales = currentSales.map(s => {
        if (s.id === saleToReturn.id) {
          return { 
            ...s, 
            status: 'returned' as SaleStatus,
            returnReason: returnReason
          };
        }
        return s;
      });
      storage.saveSales(updatedSales);
      setSales(updatedSales);

      storage.logActivity({
        type: 'return',
        description: `Order Refund processed for undelivered order #${saleToReturn.id} (${saleToReturn.customerName}) - Refund: ${settings.currency}${returnRefundAmount.toLocaleString()}, Reason: ${returnReason} (Undelivered Order, Stock Unchanged)`,
        userId: user.id,
        userName: user.name
      });
    }

    setSaleToReturn(null);
    setIsConfirmingReturn(false);
  };

  const exportSalesToCSV = () => {
    if (sales.length === 0) {
      alert('No sales records available to export.');
      return;
    }
    const headers = ['Date', 'Sale ID', 'Status', 'Product Codes', 'Items Detail', 'Total Quantity', 'Cash Amount', 'POS Amount', 'Total Amount', 'Delivery Status', 'Customer Name', 'Note', 'Served By'];
    const rows = sales.map(s => {
      const totalQty = s.items.reduce((acc, item) => acc + item.quantity, 0);
      const codes = s.items.map(item => {
        const p = products.find(prod => prod.id === item.productId);
        return p?.code || 'N/A';
      }).join('; ');
      const itemsDetail = s.items.map(item => `${item.productName} (Qty: ${item.quantity}, Unit Price: ${item.unitPrice})`).join('; ');
      const dateStr = new Date(s.createdAt).toISOString().split('T')[0];
      
      return [
        dateStr,
        s.id,
        s.status || 'completed',
        codes,
        itemsDetail,
        totalQty,
        s.cashAmount || 0,
        s.posAmount || 0,
        s.totalAmount || 0,
        s.deliveryStatus || 'none',
        s.customerName || 'Walk-in Customer',
        s.status === 'returned' ? `RETURN: ${s.returnReason || ''}` : (s.note || ''),
        s.userName || ''
      ];
    });

    const csvContent = [
      headers.join(','),
      ...rows.map(r => r.map(val => `"${String(val).replace(/"/g, '""')}"`).join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `Sales_History_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const exportReturnsToCSV = () => {
    if (returns.length === 0) {
      alert('No sales returns records available to export.');
      return;
    }
    const headers = ['Date', 'Return ID', 'Sale ID', 'Customer Name', 'Product Code', 'Product Name', 'Quantity Returned', 'Refund Amount', 'Reason', 'Processed By'];
    const rows = returns.map(r => {
      const dateStr = new Date(r.createdAt).toISOString().split('T')[0];
      return [
        dateStr,
        r.id,
        r.saleId,
        r.customerName || '',
        r.code || '',
        r.productName || '',
        r.quantity || 0,
        r.refundAmount || 0,
        r.reason || '',
        r.userName || ''
      ];
    });

    const csvContent = [
      headers.join(','),
      ...rows.map(row => row.map(val => `"${String(val).replace(/"/g, '""')}"`).join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `Sales_Returns_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div ref={containerRef} className="space-y-6 pb-24">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-slate-900">Sales Management</h2>
          <p className="text-slate-500">Record sales, process returns, and manage POS</p>
        </div>
        {canCreate && (
          <button 
            onClick={() => setView(view === 'list' ? 'pos' : 'list')}
            className="flex items-center gap-2 bg-primary-theme text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-primary-theme-light hover:bg-primary-theme-hover transition-all cursor-pointer"
          >
            {view === 'list' ? (
              <>
                <Plus size={20} />
                New Sale (POS)
              </>
            ) : (
              <>
                <RotateCcw size={20} />
                View Sales History
              </>
            )}
          </button>
        )}
      </div>

      {view === 'list' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-3">
            <div className="flex gap-4">
              <button
                onClick={() => setSubTab('sales')}
                className={`pb-2 px-1 font-bold text-sm transition-all relative ${
                  subTab === 'sales' ? 'text-primary-theme' : 'text-slate-400 hover:text-slate-600'
                }`}
              >
                Sales History
                {subTab === 'sales' && (
                  <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-theme rounded-full" />
                )}
              </button>
              <button
                onClick={() => setSubTab('returns')}
                className={`pb-2 px-1 font-bold text-sm transition-all relative flex items-center gap-2 ${
                  subTab === 'returns' ? 'text-primary-theme' : 'text-slate-400 hover:text-slate-600'
                }`}
              >
                Sales Returns
                <span className="px-1.5 py-0.5 bg-rose-100 text-rose-700 text-[10px] rounded-full font-bold">
                  {filteredReturns.length}
                </span>
                {subTab === 'returns' && (
                  <span className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary-theme rounded-full" />
                )}
              </button>
            </div>

            <button
              onClick={subTab === 'sales' ? exportSalesToCSV : exportReturnsToCSV}
              className="flex items-center gap-2 bg-accent-theme-light text-accent-theme-hover hover:bg-accent-theme-light border border-accent-theme-light/80 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all shadow-sm self-start sm:self-auto cursor-pointer"
              title={`Export ${subTab === 'sales' ? 'Sales History' : 'Sales Returns'} to CSV file`}
            >
              <Download size={15} />
              <span>Export {subTab === 'sales' ? 'Sales Log' : 'Returns Log'} to CSV</span>
            </button>
          </div>

          {/* Date & Status Filter Pills & Search Bar for Sales & Returns */}
          <div className="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 bg-layout-theme-bg p-3 rounded-2xl border border-slate-200">
            <div className="flex flex-wrap items-center gap-2">
              {/* Date Dropdown Filter */}
              <div className="flex flex-wrap items-center gap-2">
                <div className="flex items-center gap-1.5 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-2xs">
                  <Calendar size={14} className="text-primary-theme shrink-0" />
                  <span className="text-xs font-bold text-slate-400 uppercase tracking-wider shrink-0">Period:</span>
                  <select
                    value={dateFilter}
                    onChange={(e) => setDateFilter(e.target.value as DateFilterOption)}
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
              </div>

              {subTab === 'sales' && (
                <>
                  <span className="hidden sm:inline text-slate-300">|</span>

                  <span className="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
                    <Filter size={13} /> Status:
                  </span>
                  <button
                    onClick={() => setStatusFilter('all')}
                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                      statusFilter === 'all' 
                        ? 'bg-slate-900 text-white shadow-sm' 
                        : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                    }`}
                  >
                    All ({statusCounts.all})
                  </button>
                  <button
                    onClick={() => setStatusFilter('completed')}
                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 ${
                      statusFilter === 'completed' 
                        ? 'bg-emerald-600 text-white shadow-sm' 
                        : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200'
                    }`}
                  >
                    <CheckCircle2 size={13} />
                    Completed ({statusCounts.completed})
                  </button>
                  <button
                    onClick={() => setStatusFilter('installment')}
                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 ${
                      statusFilter === 'installment' 
                        ? 'bg-amber-600 text-white shadow-sm' 
                        : 'bg-amber-50 text-amber-900 hover:bg-amber-100 border border-amber-200'
                    }`}
                  >
                    <CreditCard size={13} />
                    Installment ({statusCounts.installment})
                  </button>
                  <button
                    onClick={() => setStatusFilter('returned')}
                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5 ${
                      statusFilter === 'returned' 
                        ? 'bg-rose-600 text-white shadow-sm' 
                        : 'bg-rose-50 text-rose-800 hover:bg-rose-100 border border-rose-200'
                    }`}
                  >
                    <RotateCcw size={13} />
                    Returns ({statusCounts.returned})
                  </button>
                </>
              )}
            </div>

            <div className="relative w-full md:w-64">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={15} />
              <input
                type="text"
                placeholder={subTab === 'sales' ? "Search ID, customer, item..." : "Search return ID, sale ID, customer..."}
                value={historySearch}
                onChange={(e) => setHistorySearch(e.target.value)}
                className="w-full pl-9 pr-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-primary-theme focus:outline-none"
              />
            </div>
          </div>
        </div>
      )}

      {view === 'list' ? (
        subTab === 'sales' ? (
          <div className="bg-card-theme-bg rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-layout-theme-bg border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                    <th className="px-5 py-4">Date</th>
                    <th className="px-5 py-4">Sale ID</th>
                    <th className="px-5 py-4">Status</th>
                    <th className="px-5 py-4">Codes / Items</th>
                    <th className="px-5 py-4 text-right">Total Amount</th>
                    <th className="px-5 py-4 text-right">Paid / Balance</th>
                    <th className="px-5 py-4">Delivery</th>
                    <th className="px-5 py-4">Customer</th>
                    <th className="px-5 py-4">Note</th>
                    <th className="px-5 py-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {filteredSales.length === 0 ? (
                    <tr>
                      <td colSpan={10} className="px-6 py-12 text-center text-slate-400 italic">
                        No sales found matching current status filter or search query.
                      </td>
                    </tr>
                  ) : (
                    filteredSales.map((sale) => {
                      const totalQty = sale.items.reduce((acc, item) => acc + item.quantity, 0);
                      const codes = sale.items.map(item => {
                        const p = products.find(prod => prod.id === item.productId);
                        return p?.code || 'N/A';
                      }).join(', ');

                      const currentPaid = getSalePaidAmount(sale, payments);
                      const balanceRemaining = Math.max(0, sale.totalAmount - currentPaid);
                      const isFullyPaid = balanceRemaining <= 0.01;

                      return (
                        <tr key={sale.id} className={`hover:bg-layout-theme-bg transition-colors text-sm ${sale.status === 'returned' ? 'bg-rose-50/30 opacity-80' : ''}`}>
                          <td className="px-5 py-4 whitespace-nowrap text-slate-500 text-xs font-medium">
                            {new Date(sale.createdAt).toLocaleDateString()}
                          </td>
                          <td className="px-5 py-4 font-mono text-xs uppercase text-primary-theme font-bold">
                            #{sale.id}
                          </td>
                          <td className="px-5 py-4">
                            {sale.status === 'returned' ? (
                              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200 shadow-xs">
                                <RotateCcw size={12} className="text-rose-600 shrink-0" />
                                Returned
                              </span>
                            ) : sale.status === 'completed' || isFullyPaid ? (
                              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shadow-xs">
                                <CheckCircle2 size={12} className="text-emerald-600 shrink-0" />
                                Completed
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300/60 shadow-xs">
                                <CreditCard size={12} className="text-amber-700 shrink-0" />
                                Installment
                              </span>
                            )}
                          </td>
                          <td className="px-5 py-4 max-w-[160px]">
                            <div className="text-xs font-bold text-slate-800 truncate" title={codes}>
                              {codes}
                            </div>
                            <div className="text-[10px] text-slate-400 font-medium">
                              {totalQty} unit{totalQty !== 1 ? 's' : ''} ({sale.items.length} item{sale.items.length !== 1 ? 's' : ''})
                            </div>
                          </td>
                          <td className="px-5 py-4 text-right font-mono font-bold text-slate-900">
                            {settings.currency}{sale.totalAmount.toLocaleString()}
                          </td>
                          <td className="px-5 py-4 text-right">
                            <div className="font-mono text-xs font-bold text-emerald-700">
                              Paid: {settings.currency}{currentPaid.toLocaleString()}
                            </div>
                            {balanceRemaining > 0.01 && sale.status !== 'returned' && (
                              <div className="font-mono text-[10px] font-bold text-amber-700">
                                Due: {settings.currency}{balanceRemaining.toLocaleString()}
                              </div>
                            )}
                          </td>
                          <td className="px-5 py-4">
                            {sale.deliveryStatus === 'delivered' ? (
                              <span className="inline-flex items-center gap-1 text-emerald-700 font-bold text-xs uppercase tracking-tight">
                                <CheckCircle2 size={12} /> Delivered
                              </span>
                            ) : sale.deliveryStatus === 'pending' ? (
                              <span className="inline-flex items-center gap-1 text-amber-600 font-bold text-xs uppercase tracking-tight">
                                <Package size={12} className="animate-pulse" /> Pending
                              </span>
                            ) : (
                              <span className="text-slate-400 font-medium text-xs">-</span>
                            )}
                          </td>
                          <td className="px-5 py-4 font-medium text-slate-900 text-xs">
                            {sale.customerName}
                          </td>
                          <td className="px-5 py-4 text-slate-400 text-xs truncate max-w-[100px]" title={sale.status === 'returned' ? `RETURN REASON: ${sale.returnReason || ''}\nOriginal Note: ${sale.note || ''}` : sale.note}>
                            {sale.status === 'returned' ? (
                              <span className="text-rose-600 font-medium italic">
                                Return: {sale.returnReason || '-'}
                              </span>
                            ) : (
                              sale.note || '-'
                            )}
                          </td>
                          <td className="px-5 py-4 text-right whitespace-nowrap">
                            <div className="flex items-center justify-end gap-1.5">
                              {canCreate && sale.status === 'installment' && balanceRemaining > 0.01 && (
                                <button
                                  onClick={() => {
                                    setPayInstallmentSale(sale);
                                    setInstallmentPayAmount(balanceRemaining);
                                    setInstallmentPayMethod('Cash');
                                  }}
                                  className="inline-flex items-center gap-1 bg-amber-500 hover:bg-amber-600 text-white font-bold px-2.5 py-1 rounded-lg text-xs transition-colors shadow-xs cursor-pointer"
                                  title="Record Installment Payment"
                                >
                                  <CreditCard size={12} />
                                  + Pay
                                </button>
                              )}

                              <button
                                onClick={() => {
                                  setReceiptFormat('thermal');
                                  setReceiptSale(sale);
                                }}
                                className="text-primary-theme hover:text-primary-theme-dark font-bold flex items-center gap-1 cursor-pointer bg-primary-theme-light px-2 py-1 rounded-lg text-xs transition-colors"
                                title="Print Receipt"
                              >
                                <Printer size={12} />
                                Receipt
                              </button>

                              <button
                                onClick={() => {
                                  setReceiptFormat('invoice');
                                  setReceiptSale(sale);
                                }}
                                className="text-accent-theme hover:text-accent-theme-dark font-bold flex items-center gap-1 cursor-pointer bg-accent-theme-light px-2 py-1 rounded-lg text-xs transition-colors"
                                title="Print Invoice"
                              >
                                <FileText size={12} />
                                Invoice
                              </button>

                              {canCreate && (
                                sale.status !== 'returned' ? (
                                  (() => {
                                    const saleDate = new Date(sale.createdAt);
                                    const diffDays = (new Date().getTime() - saleDate.getTime()) / (1000 * 3600 * 24);
                                    const isWithinLimit = diffDays <= (settings.transactionEditLimitDays || 7);
                                    const isDelivered = sale.deliveryStatus === 'delivered';
                                    
                                    return isWithinLimit ? (
                                      <button 
                                        onClick={() => startReturnFlow(sale)}
                                        className={`${isDelivered ? 'text-rose-600 hover:text-rose-800 bg-rose-50' : 'text-amber-600 hover:text-amber-800 bg-amber-50'} font-bold flex items-center gap-1 cursor-pointer text-xs px-2.5 py-1 rounded-lg transition-colors`}
                                        title={isDelivered ? "Process Stock Return" : "Process Order Refund"}
                                      >
                                        <RotateCcw size={12} />
                                        {isDelivered ? 'Return' : 'Refund'}
                                      </button>
                                    ) : null;
                                  })()
                                ) : (
                                  <span className={`inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md ${
                                    sale.deliveryStatus === 'delivered' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'
                                  }`}>
                                    {sale.deliveryStatus === 'delivered' ? 'Returned' : 'Refunded'}
                                  </span>
                                )
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
        ) : (
          <div className="bg-card-theme-bg rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-layout-theme-bg border-bottom border-slate-200 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                    <th className="px-6 py-4">Date</th>
                    <th className="px-6 py-4">Return ID</th>
                    <th className="px-6 py-4">Sales ID</th>
                    <th className="px-6 py-4">Customer Name</th>
                    <th className="px-6 py-4">Code</th>
                    <th className="px-6 py-4 text-center">Qty</th>
                    <th className="px-6 py-4">Refund Amount</th>
                    <th className="px-6 py-4">Stock Restock Status</th>
                    <th className="px-6 py-4">Reason</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {filteredReturns.length === 0 ? (
                    <tr>
                      <td colSpan={9} className="px-6 py-12 text-center text-slate-400 font-medium">
                        No sales returns matching current filters.
                      </td>
                    </tr>
                  ) : (
                    filteredReturns.map((ret) => {
                      const linkedSale = sales.find(s => s.id === ret.saleId);
                      const isDelivered = ret.wasDelivered !== undefined 
                        ? ret.wasDelivered 
                        : (linkedSale ? linkedSale.deliveryStatus === 'delivered' : false);

                      return (
                        <tr key={ret.id} className="hover:bg-layout-theme-bg transition-colors text-sm">
                          <td className="px-6 py-4 whitespace-nowrap text-slate-500">
                            {new Date(ret.createdAt).toLocaleDateString()}
                          </td>
                          <td className="px-6 py-4 font-mono text-xs uppercase text-rose-600 font-bold">
                            #{ret.id}
                          </td>
                          <td className="px-6 py-4 font-mono text-xs uppercase text-primary-theme font-bold">
                            #{ret.saleId}
                          </td>
                          <td className="px-6 py-4 font-medium text-slate-900">
                            {ret.customerName}
                          </td>
                          <td className="px-6 py-4 font-mono text-xs font-bold text-primary-theme">
                            {ret.code}
                          </td>
                          <td className="px-6 py-4 text-center font-mono font-bold">
                            {ret.quantity}
                          </td>
                          <td className="px-6 py-4 font-mono text-rose-600 font-bold">
                            {settings.currency}{ret.refundAmount?.toLocaleString() || 0}
                          </td>
                          <td className="px-6 py-4 whitespace-nowrap">
                            {isDelivered ? (
                              <span className="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 bg-emerald-100 border border-emerald-300 px-2.5 py-1 rounded-full">
                                <CheckCircle2 size={12} className="text-emerald-600" /> Restocked (+{ret.quantity})
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1 text-[11px] font-bold text-amber-800 bg-amber-100 border border-amber-300 px-2.5 py-1 rounded-full">
                                <RotateCcw size={12} className="text-amber-600" /> Refund Only (Undelivered)
                              </span>
                            )}
                          </td>
                          <td className="px-6 py-4 text-slate-600 text-xs">
                            {ret.reason}
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )
      ) : (
        <div className="space-y-6">
          {/* Search Bar with Autocomplete Dropdown */}
          <div className="relative z-30">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" size={20} />
            <input 
              type="text"
              placeholder="Search or scan product code, name, brand, size, category..."
              className="w-full pl-12 pr-10 py-4 bg-card-theme-bg border border-slate-200 rounded-2xl focus:ring-2 focus:ring-primary-theme focus:outline-none shadow-sm placeholder:text-slate-400 font-medium text-sm"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            {search.trim() && (
              <button 
                onClick={() => setSearch('')}
                className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1 rounded-full hover:bg-slate-100 transition-colors"
                title="Clear search"
              >
                <X size={18} />
              </button>
            )}

            {/* Autocomplete Dropdown Suggestions */}
            {search.trim().length > 0 && (
              <div className="absolute left-0 right-0 top-full mt-2 bg-card-theme-bg border border-slate-200 rounded-2xl shadow-2xl overflow-hidden max-h-96 overflow-y-auto z-50">
                <div className="px-4 py-2.5 bg-layout-theme-bg border-b border-slate-100 flex items-center justify-between text-xs font-bold text-slate-500">
                  <span>SUGGESTED PRODUCTS ({filteredProducts.length})</span>
                  <span className="text-[10px] text-slate-400 font-normal">Click item to add to cart</span>
                </div>

                {filteredProducts.length === 0 ? (
                  <div className="p-8 text-center text-slate-500">
                    <p className="font-semibold text-sm text-slate-700">No matching in-stock products found</p>
                    <p className="text-xs text-slate-400 mt-1">No products match "{search}". Please try a different search query.</p>
                  </div>
                ) : (
                  <div className="divide-y divide-slate-100">
                    {filteredProducts.map((product) => {
                      const stock = storage.calculateClosingStock(product.id);
                      return (
                        <div
                          key={product.id}
                          onClick={() => {
                            addToCart(product);
                            setSearch('');
                          }}
                          className="p-3.5 hover:bg-primary-theme-light/70 transition-colors cursor-pointer flex items-center justify-between gap-4 group"
                        >
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 mb-1">
                              <span className="font-mono text-[10px] bg-primary-theme-light text-primary-theme-hover font-bold px-2 py-0.5 rounded border border-primary-theme-light uppercase">
                                {product.code || 'NO CODE'}
                              </span>
                              <span className="font-bold text-slate-900 text-sm truncate group-hover:text-primary-theme-hover">
                                {product.name}
                              </span>
                            </div>
                            <div className="text-xs text-slate-500 flex items-center gap-2 flex-wrap">
                              <span className="font-medium text-slate-700">{product.brand}</span>
                              {product.size && (
                                <>
                                  <span className="text-slate-300">•</span>
                                  <span className="font-mono bg-slate-100 px-1.5 py-0.2 rounded text-[10px] text-slate-600">{product.size}</span>
                                </>
                              )}
                              <span className="text-slate-300">•</span>
                              <span className="text-slate-500 text-[11px]">{product.category}</span>
                            </div>
                          </div>

                          <div className="flex items-center gap-4 shrink-0">
                            <div className="text-right">
                              <div className="text-primary-theme font-mono font-bold text-sm">
                                {settings.currency}{product.unitPrice.toLocaleString()}
                              </div>
                              <div className={`text-[10px] font-bold ${stock < 5 ? 'text-rose-600' : 'text-slate-400'}`}>
                                {stock} in stock
                              </div>
                            </div>
                            <button
                              type="button"
                              className="p-2 bg-primary-theme-light group-hover:bg-primary-theme text-primary-theme group-hover:text-white rounded-xl transition-all shadow-sm"
                              title="Add to cart"
                            >
                              <Plus size={16} />
                            </button>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Current Cart & Payment Interface */}
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {/* Left: Cart Items Card */}
            <div className="lg:col-span-7 bg-card-theme-bg rounded-2xl border border-slate-200 shadow-xl overflow-hidden flex flex-col">
              <div className="p-6 border-b border-slate-100 bg-layout-theme-bg/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div className="flex items-center gap-2 text-slate-900 font-bold text-lg">
                  <ShoppingCart className="text-primary-theme" size={22} />
                  <span>Current Cart</span>
                  {cart.length > 0 && (
                    <span className="bg-primary-theme-light text-primary-theme-hover text-xs font-mono font-bold px-2.5 py-0.5 rounded-full">
                      {cart.reduce((acc, i) => acc + i.quantity, 0)} items
                    </span>
                  )}
                </div>

                {cart.length > 0 && (
                  <button 
                    onClick={() => setCart([])}
                    className="text-xs text-rose-600 hover:text-rose-800 font-semibold transition-colors cursor-pointer"
                  >
                    Clear All
                  </button>
                )}
              </div>

              <div className="p-6 border-b border-slate-100">
                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Customer Name *</label>
                <input 
                  type="text"
                  placeholder="Enter customer name..."
                  className="w-full px-4 py-2.5 bg-layout-theme-bg border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-theme font-medium text-sm"
                  value={customerName}
                  onChange={(e) => setCustomerName(e.target.value)}
                />
              </div>

              <div className="p-6 space-y-4 min-h-[260px]">
                {cart.length === 0 ? (
                  <div className="flex flex-col items-center justify-center py-16 text-slate-400 gap-3">
                    <div className="p-4 bg-layout-theme-bg rounded-full text-slate-400">
                      <ShoppingCart size={40} />
                    </div>
                    <p className="font-bold text-slate-700 text-base">Cart is empty</p>
                    <p className="text-xs text-slate-400 text-center max-w-sm leading-relaxed">
                      Search and select products using the search bar above to build your order.
                    </p>
                  </div>
                ) : (
                  <div className="space-y-3">
                    {cart.map((item) => {
                      const product = products.find(p => p.id === item.productId);
                      return (
                        <div 
                          key={item.productId} 
                          className="p-3.5 bg-layout-theme-bg/70 rounded-xl border border-slate-200/80 hover:border-primary-theme-light transition-all space-y-3"
                        >
                          {/* Item Title & Details Header */}
                          <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0 flex-1">
                              <div className="flex items-center gap-2 flex-wrap">
                                {product?.code && (
                                  <span className="font-mono text-[10px] bg-primary-theme-light text-primary-theme-hover font-bold px-1.5 py-0.5 rounded border border-primary-theme-light/60 uppercase">
                                    {product.code}
                                  </span>
                                )}
                                <span className="text-sm font-bold text-slate-900 leading-snug">
                                  {item.productName}
                                </span>
                              </div>
                              {product && (
                                <div className="text-[11px] text-slate-500 mt-1 flex items-center gap-1.5 flex-wrap">
                                  {product.brand && <span className="font-medium text-slate-700">{product.brand}</span>}
                                  {product.size && (
                                    <>
                                      <span className="text-slate-300">•</span>
                                      <span className="font-mono bg-card-theme-bg px-1.5 py-0.2 rounded border border-slate-200 text-slate-600 text-[10px]">{product.size}</span>
                                    </>
                                  )}
                                  {product.category && (
                                    <>
                                      <span className="text-slate-300">•</span>
                                      <span className="text-slate-500">{product.category}</span>
                                    </>
                                  )}
                                </div>
                              )}
                            </div>

                            <button 
                              onClick={() => removeFromCart(item.productId)}
                              className="text-slate-400 hover:text-rose-600 p-1.5 rounded-lg hover:bg-rose-50 transition-colors shrink-0"
                              title="Remove item"
                            >
                              <Trash2 size={16} />
                            </button>
                          </div>

                          {/* Item Controls: Quantity, Unit Price Input, and Subtotal */}
                          <div className="flex flex-wrap items-center justify-between gap-3 pt-2.5 border-t border-slate-200/80">
                            {/* Quantity Selector */}
                            <div className="flex flex-col">
                              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Quantity</span>
                              <div className="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-card-theme-bg shadow-sm">
                                <button 
                                  type="button"
                                  onClick={() => {
                                    if (item.quantity > 1) {
                                      updateCartItemQty(item.productId, item.quantity - 1);
                                    } else {
                                      removeFromCart(item.productId);
                                    }
                                  }}
                                  className="px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-100 font-bold transition-colors"
                                >
                                  -
                                </button>
                                <span className="px-3 text-xs font-bold font-mono text-slate-800">{item.quantity}</span>
                                <button 
                                  type="button"
                                  onClick={() => {
                                    const closingStock = storage.calculateClosingStock(item.productId);
                                    if (product && item.quantity < closingStock) {
                                      updateCartItemQty(item.productId, item.quantity + 1);
                                    } else {
                                      alert(`Only ${closingStock} units available in stock.`);
                                    }
                                  }}
                                  className="px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-100 font-bold transition-colors"
                                >
                                  +
                                </button>
                              </div>
                            </div>

                            {/* Editable Unit Price Input */}
                            <div className="flex flex-col">
                              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Unit Price ({settings.currency})</span>
                              <div className="flex items-center bg-card-theme-bg border border-slate-200 rounded-lg px-2 py-1 shadow-sm focus-within:ring-2 focus-within:ring-primary-theme focus-within:border-primary-theme">
                                <span className="text-xs font-semibold text-slate-400 mr-1">{settings.currency}</span>
                                <input 
                                  type="number"
                                  className="w-20 sm:w-24 text-xs font-mono font-bold text-slate-800 focus:outline-none bg-transparent"
                                  value={item.unitPrice || ''}
                                  min="0"
                                  onChange={(e) => {
                                    const val = e.target.value === '' ? 0 : parseFloat(e.target.value);
                                    updateCartItemPrice(item.productId, val);
                                  }}
                                  title="Edit Unit Price"
                                />
                              </div>
                            </div>

                            {/* Subtotal */}
                            <div className="flex flex-col items-end min-w-[80px]">
                              <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Subtotal</span>
                              <span className="text-sm font-mono font-bold text-primary-theme">
                                {settings.currency}{item.totalPrice.toLocaleString()}
                              </span>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>

            {/* Right: Payment & Summary Panel */}
            <div className="lg:col-span-5 bg-card-theme-bg rounded-2xl border border-slate-200 shadow-xl p-6 space-y-4">
              <h4 className="font-bold text-slate-900 text-lg border-b border-slate-100 pb-3">
                Payment Details
              </h4>

              <div className="space-y-3">
                <div className="flex gap-2">
                  <div className="flex-1">
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Cash Amount</label>
                    <input 
                      type="number"
                      className="w-full px-3 py-2 bg-card-theme-bg border border-slate-200 rounded-lg focus:ring-2 focus:ring-accent-theme focus:outline-none"
                      value={cashAmount || ''}
                      min="0"
                      onChange={(e) => {
                        const val = e.target.value === '' ? 0 : parseFloat(e.target.value);
                        setCashAmount(val);
                      }}
                    />
                  </div>
                  <div className="flex-1">
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">POS Amount</label>
                    <input 
                      type="number"
                      className="w-full px-3 py-2 bg-card-theme-bg border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-theme focus:outline-none"
                      value={posAmount || ''}
                      min="0"
                      onChange={(e) => {
                        const val = e.target.value === '' ? 0 : parseFloat(e.target.value);
                        setPosAmount(val);
                      }}
                    />
                  </div>
                </div>

                {/* Quick Fill Helpers to Easily Balance Split Payments */}
                <div className="flex justify-between gap-1.5 mt-1">
                  <button 
                    type="button"
                    onClick={() => { setCashAmount(cartTotal); setPosAmount(0); }}
                    className="flex-1 py-1 text-[10px] bg-accent-theme-light hover:bg-accent-theme-light text-accent-theme-hover rounded font-bold border border-accent-theme-light transition-colors"
                  >
                    100% Cash
                  </button>
                  <button 
                    type="button"
                    onClick={() => { setPosAmount(cartTotal); setCashAmount(0); }}
                    className="flex-1 py-1 text-[10px] bg-primary-theme-light hover:bg-primary-theme-light text-primary-theme-hover rounded font-bold border border-primary-theme-light transition-colors"
                  >
                    100% POS
                  </button>
                  <button 
                    type="button"
                    onClick={() => { setCashAmount(cartTotal / 2); setPosAmount(cartTotal / 2); }}
                    className="flex-1 py-1 text-[10px] bg-slate-100 hover:bg-slate-200 text-slate-700 rounded font-bold border border-slate-300 transition-colors"
                  >
                    50/50 Split
                  </button>
                </div>

                <div>
                  <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Sale Note</label>
                  <textarea 
                    className="w-full px-3 py-2 bg-card-theme-bg border border-slate-200 rounded-lg focus:ring-2 focus:ring-primary-theme focus:outline-none text-sm"
                    placeholder="Add special instructions or notes..."
                    rows={2}
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                  />
                </div>
              </div>

              <div className="flex justify-between items-center text-xl font-bold text-slate-900 border-t border-slate-100 pt-4">
                <span>Total</span>
                <span className="text-primary-theme font-mono">{settings.currency}{cartTotal.toLocaleString()}</span>
              </div>

              {/* Dynamic Live Violations Block */}
              {violations.length > 0 && (
                <div className="bg-rose-50 border border-rose-200 rounded-xl p-3 text-xs text-rose-800 space-y-1">
                  <span className="font-bold block text-rose-950 flex items-center gap-1.5">
                    ⚠️ Payment Constraints:
                  </span>
                  <ul className="list-disc pl-4 space-y-0.5 text-rose-700 font-medium">
                    {violations.map((v, i) => (
                      <li key={i}>{v}</li>
                    ))}
                  </ul>
                </div>
              )}

              {/* Interactive Automatic Payment Mode Classification */}
              <div className="mt-4">
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">
                  Payment Classification (Auto-Selected)
                </label>
                
                {Math.abs((cashAmount + posAmount) - cartTotal) < 0.01 && cartTotal > 0 ? (
                  <div className="p-3.5 bg-emerald-50 border-2 border-emerald-300 rounded-xl flex items-start gap-3 shadow-sm transition-all">
                    <div className="p-1.5 bg-emerald-600 text-white rounded-lg shrink-0 mt-0.5">
                      <CheckCircle2 size={16} />
                    </div>
                    <div>
                      <div className="text-xs font-bold text-emerald-950 flex items-center gap-1.5">
                        <span>FULL PAYMENT</span>
                        <span className="text-[10px] bg-emerald-200 text-emerald-900 px-1.5 py-0.2 rounded font-mono font-bold uppercase">Automated</span>
                      </div>
                      <p className="text-[11px] text-emerald-800 font-medium leading-tight mt-0.5">
                        Total paid ({settings.currency}{(cashAmount + posAmount).toLocaleString()}) equals full order total. Order will be marked fully settled.
                      </p>
                    </div>
                  </div>
                ) : (cashAmount + posAmount) > cartTotal ? (
                  <div className="p-3.5 bg-rose-50 border-2 border-rose-300 rounded-xl flex items-start gap-3 shadow-sm transition-all">
                    <div className="p-1.5 bg-rose-600 text-white rounded-lg shrink-0 mt-0.5">
                      <X size={16} />
                    </div>
                    <div>
                      <div className="text-xs font-bold text-rose-950 flex items-center gap-1.5">
                        <span>OVERPAYMENT DETECTED</span>
                      </div>
                      <p className="text-[11px] text-rose-800 font-medium leading-tight mt-0.5">
                        Payment amount exceeds order total by {settings.currency}{((cashAmount + posAmount) - cartTotal).toLocaleString()}.
                      </p>
                    </div>
                  </div>
                ) : (
                  <div className="p-3.5 bg-amber-50 border-2 border-amber-300 rounded-xl flex items-start gap-3 shadow-sm transition-all">
                    <div className="p-1.5 bg-amber-600 text-white rounded-lg shrink-0 mt-0.5">
                      <CreditCard size={16} />
                    </div>
                    <div>
                      <div className="text-xs font-bold text-amber-950 flex items-center gap-1.5">
                        <span>INSTALLMENT PAYMENT</span>
                        <span className="text-[10px] bg-amber-200 text-amber-900 px-1.5 py-0.2 rounded font-mono font-bold uppercase">Automated</span>
                      </div>
                      <div className="text-[11px] text-amber-900 font-medium leading-tight mt-0.5 space-y-0.5">
                        <p>Initial Payment: <span className="font-bold font-mono text-amber-950">{settings.currency}{(cashAmount + posAmount).toLocaleString()}</span></p>
                        <p>Balance Due: <span className="font-bold font-mono text-amber-950">{settings.currency}{Math.max(0, cartTotal - (cashAmount + posAmount)).toLocaleString()}</span></p>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              <button 
                onClick={() => {
                  if (violations.length > 0) {
                    alert(`Cannot confirm order. Please fix the following violations first:\n\n${violations.map(v => '• ' + v).join('\n')}`);
                    return;
                  }
                  setIsConfirmingOrder(true);
                }}
                disabled={violations.length > 0}
                className={`w-full py-4 rounded-xl font-bold shadow-lg mt-4 transition-all flex items-center justify-center gap-2 ${
                  violations.length > 0 
                    ? 'bg-slate-300 text-slate-500 cursor-not-allowed shadow-none' 
                    : 'bg-slate-900 text-white hover:bg-slate-800 active:scale-95 cursor-pointer'
                }`}
              >
                <CheckCircle2 size={20} />
                Confirm Order
              </button>
            </div>
          </div>
        </div>
      )}

      {saleToReturn && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
          <div className="bg-card-theme-bg rounded-3xl p-8 max-w-lg w-full shadow-2xl overflow-y-auto max-h-[90vh] border border-slate-100">
            <h3 className="text-xl font-bold text-slate-900 mb-1">
              {saleToReturn.deliveryStatus === 'delivered' ? 'Process Stock Return' : 'Process Order Refund'}
            </h3>
            <p className="text-xs text-slate-500 mb-6">
              {saleToReturn.deliveryStatus === 'delivered' 
                ? 'Return delivered goods back to inventory and set refund details.' 
                : 'Issue a refund for an undelivered order. (No stock will be modified and pending delivery will be removed).'
              }
            </p>
            
            <div className="space-y-4">
              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Sales ID</label>
                <div className="w-full px-4 py-2 bg-layout-theme-bg border border-slate-100 rounded-xl font-mono text-xs uppercase text-primary-theme font-bold">
                  #{saleToReturn.id}
                </div>
              </div>

              <div>
                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Customer Name</label>
                <div className="w-full px-4 py-2 bg-layout-theme-bg border border-slate-100 rounded-xl font-medium text-slate-700 text-sm">
                  {saleToReturn.customerName}
                </div>
              </div>

              {saleToReturn.deliveryStatus === 'delivered' ? (
                <>
                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Select Product to Return</label>
                    <select 
                      className="w-full px-4 py-2.5 bg-card-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:outline-none text-sm"
                      value={returnProductId}
                      onChange={(e) => {
                        const prodId = e.target.value;
                        setReturnProductId(prodId);
                        const item = saleToReturn.items.find(i => i.productId === prodId);
                        if (item) {
                          setReturnQty(item.quantity);
                          setReturnRefundAmount(item.totalPrice);
                        }
                      }}
                    >
                      {saleToReturn.items.map(item => {
                        const p = products.find(prod => prod.id === item.productId);
                        return (
                          <option key={item.productId} value={item.productId}>
                            {p?.code || item.productCode || 'N/A'} - {item.productName} ({item.quantity} purchased)
                          </option>
                        );
                      })}
                    </select>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Return Qty</label>
                      {(() => {
                        const targetItem = saleToReturn.items.find(i => i.productId === returnProductId);
                        const maxQty = targetItem?.quantity || 0;
                        const isQtyInvalid = returnQty <= 0 || returnQty > maxQty;
                        return (
                          <>
                            <input 
                              type="number"
                              step="any"
                              min="1"
                              max={maxQty}
                              className={`w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-primary-theme focus:outline-none text-sm font-mono font-bold ${
                                isQtyInvalid 
                                ? 'border-rose-300 bg-rose-50 text-rose-600' 
                                : 'border-slate-200 bg-card-theme-bg'
                              }`}
                              value={returnQty || ''}
                              onChange={(e) => {
                                const qty = e.target.value === '' ? 0 : parseFloat(e.target.value);
                                setReturnQty(qty);
                                
                                if (targetItem) {
                                  setReturnRefundAmount(qty * targetItem.unitPrice);
                                }
                              }}
                            />
                            {returnQty > maxQty && (
                              <p className="text-[9px] text-rose-600 font-bold mt-1 uppercase">Cannot exceed purchased qty ({maxQty})</p>
                            )}
                            {returnQty <= 0 && (
                              <p className="text-[9px] text-rose-600 font-bold mt-1 uppercase">Min return qty is 1</p>
                            )}
                          </>
                        );
                      })()}
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Refund Amount ({settings.currency})</label>
                      {(() => {
                        const targetItem = saleToReturn.items.find(i => i.productId === returnProductId);
                        const maxRefund = targetItem ? Math.min(targetItem.totalPrice, Math.max(0, returnQty) * targetItem.unitPrice) : 0;
                        const isExceeded = returnRefundAmount > maxRefund + 0.01;
                        const isNegative = returnRefundAmount < 0;
                        return (
                          <>
                            <input 
                              type="number"
                              step="any"
                              min="0"
                              max={maxRefund}
                              className={`w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-primary-theme focus:outline-none text-sm font-mono font-bold ${
                                isExceeded || isNegative
                                  ? 'border-rose-300 bg-rose-50 text-rose-600'
                                  : 'border-slate-200 bg-card-theme-bg'
                              }`}
                              value={returnRefundAmount || ''}
                              onChange={(e) => setReturnRefundAmount(e.target.value === '' ? 0 : parseFloat(e.target.value))}
                            />
                            {isExceeded && (
                              <p className="text-[9px] text-rose-600 font-bold mt-1 uppercase">Cannot exceed initial price ({settings.currency}{maxRefund.toLocaleString()})</p>
                            )}
                            {isNegative && (
                              <p className="text-[9px] text-rose-600 font-bold mt-1 uppercase">Refund cannot be negative</p>
                            )}
                          </>
                        );
                      })()}
                    </div>
                  </div>

                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Reason for Return *</label>
                    <textarea 
                      className={`w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-primary-theme focus:outline-none text-sm ${
                        !returnReason.trim() ? 'border-amber-300 bg-amber-50/50' : 'border-slate-200'
                      }`}
                      rows={3}
                      placeholder="e.g. Wrong size, minor defect..."
                      value={returnReason}
                      onChange={(e) => setReturnReason(e.target.value)}
                    />
                    {!returnReason.trim() && (
                      <p className="text-[9px] text-amber-700 font-bold mt-1 uppercase">Reason for return is required</p>
                    )}
                  </div>
                </>
              ) : (
                <>
                  <div className="bg-amber-50 border border-amber-200 rounded-xl p-3.5 text-xs text-amber-900 space-y-1">
                    <p className="font-bold flex items-center gap-1.5 text-amber-950">
                      <span>UNDELIVERED ORDER REFUND</span>
                    </p>
                    <p>Order Total: <strong className="font-mono">{settings.currency}{saleToReturn.totalAmount.toLocaleString()}</strong> | Paid: <strong className="font-mono">{settings.currency}{saleToReturn.paidAmount.toLocaleString()}</strong></p>
                    <p className="text-[11px] text-amber-800">Items: {saleToReturn.items.map(i => `${i.productName} (x${i.quantity})`).join(', ')}</p>
                  </div>

                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Refund Amount ({settings.currency})</label>
                    {(() => {
                      const maxRefund = saleToReturn.paidAmount > 0 ? saleToReturn.paidAmount : saleToReturn.totalAmount;
                      const isExceeded = returnRefundAmount > maxRefund + 0.01;
                      const isNegative = returnRefundAmount < 0;
                      return (
                        <>
                          <input 
                            type="number"
                            step="any"
                            min="0"
                            max={maxRefund}
                            className={`w-full px-4 py-2.5 border rounded-xl focus:ring-2 focus:ring-primary-theme focus:outline-none text-sm font-mono font-bold ${
                              isExceeded || isNegative
                                ? 'border-rose-300 bg-rose-50 text-rose-600'
                                : 'border-slate-200 bg-card-theme-bg text-slate-900'
                            }`}
                            value={returnRefundAmount || ''}
                            onChange={(e) => setReturnRefundAmount(e.target.value === '' ? 0 : parseFloat(e.target.value))}
                          />
                          {isExceeded && (
                            <p className="text-[9px] text-rose-600 font-bold mt-1 uppercase">Cannot exceed initial order amount ({settings.currency}{maxRefund.toLocaleString()})</p>
                          )}
                          {isNegative && (
                            <p className="text-[9px] text-rose-600 font-bold mt-1 uppercase">Refund cannot be negative</p>
                          )}
                        </>
                      );
                    })()}
                  </div>

                  <div>
                    <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Reason for Return / Refund *</label>
                    <textarea 
                      className={`w-full px-4 py-2.5 border rounded-xl focus:ring-2 focus:ring-primary-theme focus:outline-none text-sm ${
                        !returnReason.trim() ? 'border-amber-300 bg-amber-50/50' : 'border-slate-200'
                      }`}
                      rows={3}
                      placeholder="e.g. Order cancelled before delivery, customer requested refund..."
                      value={returnReason}
                      onChange={(e) => setReturnReason(e.target.value)}
                    />
                    {!returnReason.trim() && (
                      <p className="text-[9px] text-amber-700 font-bold mt-1 uppercase">Reason for refund is required</p>
                    )}
                  </div>
                </>
              )}
            </div>

            <div className="flex gap-3 pt-6">
              <button 
                onClick={() => setSaleToReturn(null)}
                className="flex-1 py-3 border border-slate-200 text-slate-600 rounded-xl hover:bg-layout-theme-bg transition-colors font-bold text-sm cursor-pointer"
              >
                Cancel
              </button>
              <button 
                onClick={handleProcessReturn}
                disabled={(() => {
                  if (!returnReason.trim()) return true;
                  if (saleToReturn.deliveryStatus === 'delivered') {
                    const targetItem = saleToReturn.items.find(i => i.productId === returnProductId);
                    const maxQty = targetItem?.quantity || 0;
                    const maxRefund = targetItem ? Math.min(targetItem.totalPrice, Math.max(0, returnQty) * targetItem.unitPrice) : 0;
                    if (returnQty < 1 || returnQty > maxQty) return true;
                    if (returnRefundAmount < 0 || returnRefundAmount > maxRefund + 0.01) return true;
                  } else {
                    const maxRefund = saleToReturn.paidAmount > 0 ? saleToReturn.paidAmount : saleToReturn.totalAmount;
                    if (returnRefundAmount < 0 || returnRefundAmount > maxRefund + 0.01) return true;
                  }
                  return false;
                })()}
                className="flex-[2] bg-rose-600 disabled:opacity-50 disabled:cursor-not-allowed text-white py-3 rounded-xl font-bold hover:bg-rose-700 transition-all text-sm shadow-md cursor-pointer"
              >
                {saleToReturn.deliveryStatus === 'delivered' ? 'Process Return' : 'Process Refund'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Return / Refund Confirmation Modal */}
      {isConfirmingReturn && saleToReturn && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md animate-fade-in" id="confirm-return-modal">
          <div className="bg-card-theme-bg rounded-3xl p-8 max-w-md w-full shadow-2xl border border-rose-100">
            <div className="w-16 h-16 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center mb-6">
              <RotateCcw size={32} />
            </div>
            <h3 className="text-xl font-bold text-slate-900 mb-2">
              {saleToReturn.deliveryStatus === 'delivered' ? 'Confirm Stock Return' : 'Confirm Order Refund'}
            </h3>
            <p className="text-slate-600 text-sm mb-6 leading-relaxed">
              You are about to process a {saleToReturn.deliveryStatus === 'delivered' ? 'stock return' : 'refund'} for <strong className="text-slate-900">{saleToReturn.customerName}</strong>. 
              {saleToReturn.deliveryStatus === 'delivered' ? (
                <span className="block mt-2 text-accent-theme font-medium">
                  Note: Since this order was already delivered, {returnQty} unit(s) will be added back to active inventory stock.
                </span>
              ) : (
                <span className="block mt-2 text-amber-600 font-medium">
                  Note: Since this order was NOT delivered, no stock will be modified. The order will be cancelled and removed from pending deliveries.
                </span>
              )}
            </p>
            <div className="flex gap-3">
              <button 
                onClick={() => setIsConfirmingReturn(false)}
                className="flex-1 py-3 bg-slate-100 text-slate-600 rounded-xl font-bold text-sm hover:bg-slate-200 transition-colors cursor-pointer"
                id="cancel-return-confirm-btn"
              >
                Go Back
              </button>
              <button 
                onClick={confirmProcessReturn}
                className="flex-1 py-3 bg-rose-600 text-white rounded-xl font-bold text-sm hover:bg-rose-700 transition-colors shadow-lg cursor-pointer"
                id="confirm-return-final-btn"
              >
                {saleToReturn.deliveryStatus === 'delivered' ? 'Confirm Return' : 'Confirm Refund'}
              </button>
            </div>
          </div>
        </div>
      )}
      {isConfirmingOrder && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 animate-fade-in" id="confirm-pos-modal">
          <div className="bg-card-theme-bg rounded-3xl border border-slate-100 shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
            {/* Modal Header */}
            <div className="p-6 bg-slate-900 text-white flex items-center justify-between shrink-0">
              <div className="flex items-center gap-3">
                <div className="p-2.5 bg-white/10 text-emerald-400 rounded-2xl backdrop-blur-md">
                  <CheckCircle2 size={24} />
                </div>
                <div>
                  <h3 className="text-xl font-bold tracking-tight">Confirm POS Transaction</h3>
                  <p className="text-xs text-slate-300">Verify order, itemized breakdown, and payment details before completing</p>
                </div>
              </div>
              <button
                onClick={() => {
                  if (!isSubmittingOrder) {
                    setIsConfirmingOrder(false);
                    setSubmitStatus('idle');
                  }
                }}
                disabled={isSubmittingOrder}
                className="p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-xl transition-colors cursor-pointer"
                id="close-confirm-modal-x"
              >
                <X size={20} />
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-6 space-y-6 overflow-y-auto flex-1">
              
              {/* Transaction & Customer Metadata Grid */}
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3 bg-layout-theme-bg p-4 rounded-2xl border border-slate-100">
                <div>
                  <span className="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Customer</span>
                  <span className="text-xs font-bold text-slate-900 truncate block" title={customerName}>
                    {customerName || 'Walk-in Customer'}
                  </span>
                </div>
                <div>
                  <span className="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Cashier / Staff</span>
                  <span className="text-xs font-bold text-slate-900 truncate block">
                    {user.name}
                  </span>
                </div>
                <div>
                  <span className="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Fulfillment</span>
                  <span className="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 bg-amber-100/80 px-2 py-0.5 rounded-md">
                    <Truck size={12} /> Pending Dispatch
                  </span>
                </div>
                <div>
                  <span className="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Date & Time</span>
                  <span className="text-xs font-semibold text-slate-700 block">
                    {new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </span>
                </div>
              </div>

              {/* Itemized Order Breakdown */}
              <div>
                <div className="flex justify-between items-center mb-2">
                  <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">
                    Order Items Breakdown ({cart.length} line{cart.length !== 1 ? 's' : ''}, {cart.reduce((sum, i) => sum + i.quantity, 0)} unit{cart.reduce((sum, i) => sum + i.quantity, 0) !== 1 ? 's' : ''})
                  </h4>
                  <span className="text-xs font-bold text-slate-900 font-mono">
                    Total: {settings.currency}{cartTotal.toLocaleString()}
                  </span>
                </div>

                <div className="border border-slate-200 rounded-2xl overflow-hidden bg-white shadow-sm">
                  <table className="w-full text-left text-xs border-collapse">
                    <thead>
                      <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                        <th className="py-2.5 px-3">Item Description</th>
                        <th className="py-2.5 px-3 text-right">Unit Price</th>
                        <th className="py-2.5 px-3 text-center">Qty</th>
                        <th className="py-2.5 px-3 text-right">Subtotal</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {cart.map((item) => (
                        <tr key={item.productId} className="hover:bg-slate-50/50 transition-colors">
                          <td className="py-2.5 px-3">
                            <div className="font-bold text-slate-900">{item.productName}</div>
                            {item.code && <div className="text-[10px] font-mono text-slate-400">Code: {item.code}</div>}
                          </td>
                          <td className="py-2.5 px-3 text-right font-mono text-slate-600">
                            {settings.currency}{item.unitPrice.toLocaleString()}
                          </td>
                          <td className="py-2.5 px-3 text-center font-mono font-bold text-slate-800">
                            {item.quantity}
                          </td>
                          <td className="py-2.5 px-3 text-right font-mono font-bold text-slate-900">
                            {settings.currency}{item.totalPrice.toLocaleString()}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>

              {/* Financial & Settlement Breakdown */}
              <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-3">
                <div className="flex justify-between items-center pb-2 border-b border-slate-200">
                  <span className="text-xs font-bold uppercase text-slate-500 tracking-wider">Payment & Settlement Status</span>
                  {(cashAmount + posAmount) >= cartTotal ? (
                    <span className="px-2.5 py-1 bg-emerald-100 text-emerald-800 font-bold text-xs rounded-lg flex items-center gap-1">
                      <CheckCircle2 size={14} />
                      FULL PAYMENT (SETTLED)
                    </span>
                  ) : (cashAmount + posAmount) > 0 ? (
                    <span className="px-2.5 py-1 bg-amber-100 text-amber-800 font-bold text-xs rounded-lg flex items-center gap-1">
                      <CreditCard size={14} />
                      PARTIAL PAYMENT (CREDIT DUE)
                    </span>
                  ) : (
                    <span className="px-2.5 py-1 bg-rose-100 text-rose-800 font-bold text-xs rounded-lg flex items-center gap-1">
                      <X size={14} />
                      UNPAID (FULL DEBT)
                    </span>
                  )}
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs pt-1">
                  <div className="bg-white p-3 rounded-xl border border-slate-200">
                    <span className="text-[10px] font-bold text-slate-400 uppercase block mb-1">Cash Paid</span>
                    <span className="font-mono font-bold text-sm text-slate-900">{settings.currency}{cashAmount.toLocaleString()}</span>
                  </div>
                  <div className="bg-white p-3 rounded-xl border border-slate-200">
                    <span className="text-[10px] font-bold text-slate-400 uppercase block mb-1">POS Card Paid</span>
                    <span className="font-mono font-bold text-sm text-slate-900">{settings.currency}{posAmount.toLocaleString()}</span>
                  </div>
                  <div className="bg-white p-3 rounded-xl border border-slate-200">
                    <span className="text-[10px] font-bold text-slate-400 uppercase block mb-1">Total Paid</span>
                    <span className="font-mono font-bold text-sm text-emerald-700">{settings.currency}{(cashAmount + posAmount).toLocaleString()}</span>
                  </div>
                  <div className="bg-white p-3 rounded-xl border border-slate-200">
                    <span className="text-[10px] font-bold text-slate-400 uppercase block mb-1">
                      {(cashAmount + posAmount) >= cartTotal ? 'Balance Due' : 'Outstanding Debt'}
                    </span>
                    <span className={`font-mono font-bold text-sm ${(cartTotal - (cashAmount + posAmount)) > 0 ? 'text-amber-600' : 'text-slate-900'}`}>
                      {settings.currency}{Math.max(0, cartTotal - (cashAmount + posAmount)).toLocaleString()}
                    </span>
                  </div>
                </div>
              </div>

              {note && (
                <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 text-xs text-amber-900 flex items-start gap-2">
                  <FileText size={16} className="text-amber-600 shrink-0 mt-0.5" />
                  <div>
                    <strong className="font-bold">Sale Note: </strong>
                    <span>{note}</span>
                  </div>
                </div>
              )}

              {/* Next Steps / Info Note */}
              <div className="p-3 bg-blue-50/70 border border-blue-100 rounded-xl text-xs text-blue-800 flex items-start gap-2">
                <Truck size={16} className="text-blue-600 shrink-0 mt-0.5" />
                <span>
                  Confirming this transaction will log the sale, save payment records, and route fulfillment to <strong>Deliveries</strong> as pending dispatch. Printable sales slip will open automatically.
                </span>
              </div>

              {/* Submission Feedback Messages */}
              {isSubmittingOrder && (
                <div className="flex items-center justify-center gap-3 py-3 text-xs text-slate-700 font-medium bg-slate-100 rounded-xl animate-pulse">
                  <div className="w-4 h-4 border-2 border-slate-700 border-t-transparent rounded-full animate-spin"></div>
                  Saving transaction and syncing with host database...
                </div>
              )}

              {submitStatus === 'success' && (
                <div className="p-3.5 bg-emerald-50 text-emerald-900 rounded-xl text-xs font-bold border border-emerald-200 flex items-center gap-2">
                  <CheckCircle2 className="text-emerald-600 shrink-0" size={18} />
                  Sale confirmed and successfully synced with remote database!
                </div>
              )}

              {submitStatus === 'error' && (
                <div className="p-3.5 bg-rose-50 text-rose-900 rounded-xl text-xs font-medium border border-rose-200 flex flex-col gap-1">
                  <span className="font-bold flex items-center gap-1.5">
                    <AlertCircle size={16} className="text-rose-600" />
                    Database Sync Notice:
                  </span>
                  <span>Transaction saved locally! Could not reach remote server; it will automatically sync in the background when connectivity resumes.</span>
                </div>
              )}
            </div>

            {/* Modal Footer */}
            <div className="p-5 bg-slate-50 border-t border-slate-200 flex items-center justify-between gap-3 shrink-0">
              <button
                onClick={() => {
                  if (!isSubmittingOrder) {
                    setIsConfirmingOrder(false);
                    setSubmitStatus('idle');
                  }
                }}
                disabled={isSubmittingOrder}
                className="px-5 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-200 disabled:opacity-50 transition-colors cursor-pointer"
                id="cancel-pos-confirm-btn"
              >
                Back to Edit
              </button>

              <button
                onClick={async () => {
                  setIsSubmittingOrder(true);
                  setSubmitStatus('idle');
                  try {
                    const totalPaid = cashAmount + posAmount;
                    const calculatedStatus: SaleStatus = totalPaid >= cartTotal ? 'completed' : 'installment';

                    const newSale: Sale = {
                      id: 'SALE-' + Math.random().toString(36).substr(2, 9).toUpperCase(),
                      customerName: customerName || 'Walk-in Customer',
                      items: cart,
                      totalAmount: cartTotal,
                      paidAmount: totalPaid,
                      cashAmount,
                      posAmount,
                      note,
                      status: calculatedStatus,
                      deliveryStatus: 'pending',
                      userId: user.id,
                      createdAt: new Date().toISOString()
                    };

                    const allSales = [newSale, ...storage.getSales()];
                    storage.saveSales(allSales);
                    storage.logActivity({
                      type: 'sale',
                      description: `Sale recorded for ${newSale.customerName} - Total: ${settings.currency}${cartTotal.toLocaleString()} (Cash: ${settings.currency}${cashAmount}, POS: ${settings.currency}${posAmount})`,
                      userId: user.id,
                      userName: user.name
                    });
                    setSales(allSales);

                    if (cashAmount > 0 || posAmount > 0) {
                      const payments = storage.getPayments();
                      const newPayments = [];
                      
                      if (cashAmount > 0) {
                        newPayments.push({
                          id: Math.random().toString(36).substr(2, 9),
                          saleId: newSale.id,
                          amount: cashAmount,
                          method: 'Cash',
                          timestamp: new Date().toISOString(),
                          recordedBy: user.id
                        });
                      }
                      
                      if (posAmount > 0) {
                        newPayments.push({
                          id: Math.random().toString(36).substr(2, 9),
                          saleId: newSale.id,
                          amount: posAmount,
                          method: 'POS',
                          timestamp: new Date().toISOString(),
                          recordedBy: user.id
                        });
                      }
                      
                      const existingPayments = storage.getPayments();
                      const updatedPayments = [...newPayments, ...existingPayments];
                      storage.savePayments(updatedPayments);
                      setPayments(updatedPayments);
                    }

                    await storage.sync();
                    
                    setSubmitStatus('success');
                    
                    setTimeout(() => {
                      setCart([]);
                      setCustomerName('');
                      setCashAmount(0);
                      setPosAmount(0);
                      setNote('');
                      setIsConfirmingOrder(false);
                      setSubmitStatus('idle');
                      setView('list');
                      setReceiptSale(newSale);
                    }, 1200);

                  } catch (err) {
                    console.error('Failed to sync sale to database:', err);
                    setSubmitStatus('error');
                  } finally {
                    setIsSubmittingOrder(false);
                  }
                }}
                disabled={isSubmittingOrder || submitStatus === 'success'}
                id="submit-pos-sale-btn"
                className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 transition-all flex items-center gap-2 cursor-pointer"
              >
                {isSubmittingOrder ? (
                  <>
                    <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    <span>Saving...</span>
                  </>
                ) : submitStatus === 'success' ? (
                  <>
                    <CheckCircle2 size={16} />
                    <span>Done!</span>
                  </>
                ) : (
                  <>
                    <CheckCircle2 size={16} />
                    <span>Confirm & Complete Sale</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Installment Payment Management Modal */}
      {payInstallmentSale && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
          <div className="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden border border-slate-100 flex flex-col max-h-[92vh]">
            {/* Modal Header */}
            <div className="p-5 bg-gradient-to-r from-amber-600 to-amber-700 text-white flex items-center justify-between shrink-0">
              <div className="flex items-center gap-2.5">
                <div className="p-2 bg-white/20 rounded-xl">
                  <CreditCard size={20} />
                </div>
                <div>
                  <h3 className="text-base font-bold leading-tight">Record Installment Payment</h3>
                  <p className="text-xs text-amber-100 font-medium">
                    Order #{payInstallmentSale.id} • {payInstallmentSale.customerName}
                  </p>
                </div>
              </div>
              <button
                onClick={() => {
                  setPayInstallmentSale(null);
                  setShowInstallmentConfirm(false);
                }}
                className="p-1.5 hover:bg-white/20 rounded-lg text-white transition-colors cursor-pointer"
              >
                <X size={18} />
              </button>
            </div>

            <div className="p-6 overflow-y-auto space-y-5">
              {/* Financial Progress Banner */}
              {(() => {
                const currentPaid = getSalePaidAmount(payInstallmentSale, payments);
                const balanceRemaining = Math.max(0, payInstallmentSale.totalAmount - currentPaid);
                const progressPercent = Math.min(100, Math.round((currentPaid / payInstallmentSale.totalAmount) * 100));

                const salePayments = payments
                  .filter(p => p.saleId === payInstallmentSale.id)
                  .sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime());
                const newBalanceAfter = Math.max(0, balanceRemaining - installmentPayAmount);
                const willBeFullyPaid = newBalanceAfter <= 0.01;

                if (showInstallmentConfirm) {
                  return (
                    <div className="space-y-4 py-1 animate-fadeIn">
                      <div className="bg-amber-50 border-2 border-amber-300/80 rounded-2xl p-4 text-center">
                        <div className="w-12 h-12 bg-amber-100 text-amber-700 rounded-full flex items-center justify-center mx-auto mb-2">
                          <CreditCard size={24} />
                        </div>
                        <h4 className="text-base font-bold text-slate-900">Please Confirm Payment Recording</h4>
                        <p className="text-xs text-slate-600 mt-1">
                          Review details below before finalizing payment into order ledger.
                        </p>
                      </div>

                      <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-3 text-xs">
                        <div className="flex justify-between items-center py-1 border-b border-slate-200/60">
                          <span className="text-slate-500 font-medium">Customer Name:</span>
                          <span className="font-bold text-slate-800">{payInstallmentSale.customerName}</span>
                        </div>
                        <div className="flex justify-between items-center py-1 border-b border-slate-200/60">
                          <span className="text-slate-500 font-medium">Order Reference:</span>
                          <span className="font-mono font-bold text-slate-800">#{payInstallmentSale.id}</span>
                        </div>
                        <div className="flex justify-between items-center py-1 border-b border-slate-200/60">
                          <span className="text-slate-500 font-medium">Payment Method:</span>
                          <span className="font-bold text-slate-800">{installmentPayMethod}</span>
                        </div>
                        <div className="flex justify-between items-center py-1 border-b border-slate-200/60">
                          <span className="text-slate-500 font-medium">Payment Amount:</span>
                          <span className="font-mono text-sm font-bold text-emerald-700">
                            +{settings.currency}{installmentPayAmount.toLocaleString()}
                          </span>
                        </div>
                        <div className="flex justify-between items-center py-1 border-b border-slate-200/60">
                          <span className="text-slate-500 font-medium">Remaining Due After:</span>
                          <span className="font-mono text-xs font-bold text-amber-800">
                            {settings.currency}{newBalanceAfter.toLocaleString()}
                          </span>
                        </div>
                        <div className="flex justify-between items-center py-1">
                          <span className="text-slate-500 font-medium">New Order Status:</span>
                          <span className={`font-bold px-2 py-0.5 rounded text-[11px] ${willBeFullyPaid ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-amber-100 text-amber-900 border border-amber-300'}`}>
                            {willBeFullyPaid ? 'COMPLETED (Paid in Full)' : 'INSTALLMENT'}
                          </span>
                        </div>
                      </div>
                    </div>
                  );
                }

                return (
                  <>
                    <div className="bg-amber-50/80 border border-amber-200/80 rounded-2xl p-4 space-y-3">
                      <div className="grid grid-cols-3 gap-2 text-center">
                        <div className="bg-white p-2.5 rounded-xl border border-amber-100 shadow-xs">
                          <span className="block text-[10px] font-bold text-slate-400 uppercase">Total Order</span>
                          <span className="font-mono text-sm font-bold text-slate-900">
                            {settings.currency}{payInstallmentSale.totalAmount.toLocaleString()}
                          </span>
                        </div>
                        <div className="bg-white p-2.5 rounded-xl border border-emerald-100 shadow-xs">
                          <span className="block text-[10px] font-bold text-emerald-600 uppercase">Total Paid</span>
                          <span className="font-mono text-sm font-bold text-emerald-700">
                            {settings.currency}{currentPaid.toLocaleString()}
                          </span>
                        </div>
                        <div className="bg-white p-2.5 rounded-xl border border-rose-100 shadow-xs">
                          <span className="block text-[10px] font-bold text-rose-600 uppercase">Balance Due</span>
                          <span className="font-mono text-sm font-bold text-rose-700">
                            {settings.currency}{balanceRemaining.toLocaleString()}
                          </span>
                        </div>
                      </div>

                      {/* Progress Bar */}
                      <div>
                        <div className="flex justify-between text-[11px] font-bold text-amber-900 mb-1">
                          <span>Payment Progress</span>
                          <span>{progressPercent}% Settled</span>
                        </div>
                        <div className="w-full bg-amber-200 rounded-full h-2.5 overflow-hidden">
                          <div 
                            className="bg-emerald-600 h-2.5 rounded-full transition-all duration-500" 
                            style={{ width: `${progressPercent}%` }}
                          />
                        </div>
                      </div>
                    </div>

                    {/* Order Items Summary */}
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                        Order Items Summary
                      </label>
                      <div className="bg-slate-50 rounded-xl p-3 border border-slate-200 max-h-32 overflow-y-auto divide-y divide-slate-100 text-xs">
                        {payInstallmentSale.items.map((item, idx) => (
                          <div key={idx} className="py-1.5 flex justify-between items-center">
                            <div>
                              <span className="font-bold text-slate-800">{item.productName}</span>
                              <span className="text-slate-400 text-[10px] ml-1.5 font-mono">
                                x{item.quantity} @ {settings.currency}{item.unitPrice.toLocaleString()}
                              </span>
                            </div>
                            <span className="font-mono font-bold text-slate-700">
                              {settings.currency}{item.totalPrice.toLocaleString()}
                            </span>
                          </div>
                        ))}
                      </div>
                    </div>

                    {/* Prior Payments History */}
                    {salePayments.length > 0 && (
                      <div>
                        <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                          <History size={12} /> Payment History ({salePayments.length})
                        </label>
                        <div className="bg-slate-50 rounded-xl p-3 border border-slate-200 max-h-32 overflow-y-auto space-y-1.5 text-xs">
                          {salePayments.map((p) => (
                            <div key={p.id} className="flex items-center justify-between bg-white p-2 rounded-lg border border-slate-100">
                              <div>
                                <span className="font-mono text-[10px] font-bold text-slate-400 uppercase mr-2">
                                  {p.method}
                                </span>
                                <span className="text-slate-500 text-[10px]">
                                  {new Date(p.timestamp).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}
                                </span>
                              </div>
                              <span className="font-mono font-bold text-emerald-700">
                                +{settings.currency}{p.amount.toLocaleString()}
                              </span>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* New Payment Entry Form */}
                    <div className="space-y-3 pt-2 border-t border-slate-100">
                      <div>
                        <div className="flex justify-between items-center mb-1">
                          <label className="block text-xs font-bold text-slate-800">
                            New Payment Amount ({settings.currency})
                          </label>
                          <div className="flex gap-1.5">
                            <button
                              type="button"
                              onClick={() => setInstallmentPayAmount(Math.round(balanceRemaining / 2))}
                              className="text-[10px] font-bold bg-amber-100 text-amber-900 px-2 py-0.5 rounded hover:bg-amber-200 transition-colors cursor-pointer"
                            >
                              50% Balance
                            </button>
                            <button
                              type="button"
                              onClick={() => setInstallmentPayAmount(balanceRemaining)}
                              className="text-[10px] font-bold bg-emerald-100 text-emerald-900 px-2 py-0.5 rounded hover:bg-emerald-200 transition-colors cursor-pointer"
                            >
                              Pay Full Due
                            </button>
                          </div>
                        </div>
                        <input
                          type="number"
                          min="1"
                          max={balanceRemaining}
                          step="0.01"
                          value={installmentPayAmount || ''}
                          onChange={(e) => setInstallmentPayAmount(parseFloat(e.target.value) || 0)}
                          className="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl font-mono text-base font-bold text-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                          placeholder="Enter payment amount..."
                        />
                      </div>

                      <div>
                        <label className="block text-xs font-bold text-slate-800 mb-1">
                          Payment Method
                        </label>
                        <select
                          value={installmentPayMethod}
                          onChange={(e) => setInstallmentPayMethod(e.target.value)}
                          className="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        >
                          <option value="Cash">Cash</option>
                          <option value="POS">POS Terminal</option>
                        </select>
                      </div>
                    </div>
                  </>
                );
              })()}
            </div>

            {/* Modal Footer */}
            <div className="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-3 shrink-0">
              {showInstallmentConfirm ? (
                <>
                  <button
                    type="button"
                    onClick={() => setShowInstallmentConfirm(false)}
                    disabled={isSubmittingInstallment}
                    className="px-4 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer"
                  >
                    ← Back & Edit
                  </button>
                  <button
                    type="button"
                    onClick={handleRecordInstallmentPayment}
                    disabled={isSubmittingInstallment}
                    className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 transition-all flex items-center gap-2 cursor-pointer"
                  >
                    {isSubmittingInstallment ? (
                      <>
                        <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                        Saving Payment...
                      </>
                    ) : (
                      <>
                        <CheckCircle2 size={16} />
                        Yes, Confirm Payment
                      </>
                    )}
                  </button>
                </>
              ) : (
                <>
                  <button
                    type="button"
                    onClick={() => {
                      setPayInstallmentSale(null);
                      setShowInstallmentConfirm(false);
                    }}
                    disabled={isSubmittingInstallment}
                    className="px-4 py-2 border border-slate-300 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={handleRecordInstallmentPayment}
                    disabled={isSubmittingInstallment || installmentPayAmount <= 0}
                    className="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 active:scale-95 disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-md shadow-amber-600/20 transition-all flex items-center gap-2 cursor-pointer"
                  >
                    {isSubmittingInstallment ? (
                      <>
                        <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                        Processing...
                      </>
                    ) : (
                      <>
                        <CheckCircle2 size={16} />
                        Record Payment
                      </>
                    )}
                  </button>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      <ReceiptModal 
        sale={receiptSale} 
        onClose={() => setReceiptSale(null)} 
        settings={settings} 
        initialFormat={receiptFormat}
      />
    </div>
  );
}
