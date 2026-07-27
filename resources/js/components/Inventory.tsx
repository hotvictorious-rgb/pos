/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useMemo, useEffect, useRef, ChangeEvent, FormEvent } from 'react';
import { 
  Plus, 
  Minus, 
  Search, 
  Package, 
  ArrowUpRight, 
  ArrowDownLeft, 
  AlertTriangle, 
  Edit2, 
  Trash2, 
  Truck, 
  RotateCcw, 
  Clock, 
  X, 
  Filter, 
  Upload, 
  Download, 
  FileText, 
  CheckCircle, 
  AlertCircle, 
  FileSpreadsheet,
  Boxes,
  TrendingUp,
  TrendingDown,
  Layers,
  Building2,
  Check
} from 'lucide-react';
import { storage } from '../lib/storage';
import { Product, User, InventoryLog } from '../types';
import { canPerformStockIn, canPerformStockOut, hasModulePermission } from '../lib/rbac';
import { format } from 'date-fns';

interface InventoryProps {
  user: User;
  initialSubTab?: InventorySubTab;
  onSubTabChange?: (tab: InventorySubTab) => void;
}

type InventorySubTab = 'products' | 'stock-in' | 'stock-out' | 'movement-logs';

export default function Inventory({ user, initialSubTab = 'products', onSubTabChange }: InventoryProps) {
  const [settings] = useState(storage.getSettings());
  const [products, setProducts] = useState<Product[]>(storage.getProducts());
  const [allLogs, setAllLogs] = useState<InventoryLog[]>(storage.getLogs());

  useEffect(() => {
    const refreshData = () => {
      setProducts(storage.getProducts());
      setAllLogs(storage.getLogs());
    };
    window.addEventListener('hysam-data-updated', refreshData);
    window.addEventListener('hysam-sync-end', refreshData);
    return () => {
      window.removeEventListener('hysam-data-updated', refreshData);
      window.removeEventListener('hysam-sync-end', refreshData);
    };
  }, []);

  // Inventory Sub-Tab State
  const [activeTab, setActiveTab] = useState<InventorySubTab>(initialSubTab);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (containerRef.current) {
      containerRef.current.scrollTo({ top: 0 });
    }
    window.scrollTo({ top: 0 });
  }, [activeTab]);

  useEffect(() => {
    if (initialSubTab) {
      setActiveTab(initialSubTab);
    }
  }, [initialSubTab]);

  const handleSubTabSwitch = (tab: InventorySubTab) => {
    setActiveTab(tab);
    if (onSubTabChange) {
      onSubTabChange(tab);
    }
  };

  // Products Tab State
  const [search, setSearch] = useState('');
  const [selectedBrand, setSelectedBrand] = useState('All Brands');
  const [selectedCategory, setSelectedCategory] = useState('All Categories');
  const [stockStatus, setStockStatus] = useState('All Status');
  const [showArchived, setShowArchived] = useState(false);
  
  // Modals & Quick Log States
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [isLogOpen, setIsLogOpen] = useState(false);
  const [isAddProductOpen, setIsAddProductOpen] = useState(false);
  const [isEditProductOpen, setIsEditProductOpen] = useState(false);
  const [editingProduct, setEditingProduct] = useState<Product | null>(null);
  const [logType, setLogType] = useState<'stock-in' | 'stock-out'>('stock-in');
  const [quantity, setQuantity] = useState<number | string>(1);
  const [notes, setNotes] = useState('');

  // Dedicated Stock-In Form State
  const [stockInProductId, setStockInProductId] = useState<string>('');
  const [stockInQty, setStockInQty] = useState<number | string>(1);
  const [stockInSupplier, setStockInSupplier] = useState<string>('');
  const [stockInNotes, setStockInNotes] = useState<string>('');
  const [stockInFeedback, setStockInFeedback] = useState<string | null>(null);

  // Dedicated Stock-Out Form State
  const [stockOutProductId, setStockOutProductId] = useState<string>('');
  const [stockOutQty, setStockOutQty] = useState<number | string>(1);
  const [stockOutReason, setStockOutReason] = useState<string>('');
  const [stockOutNotes, setStockOutNotes] = useState<string>('');
  const [stockOutFeedback, setStockOutFeedback] = useState<string | null>(null);

  // Movement Log Filters State
  const [logSearch, setLogSearch] = useState<string>('');
  const [logFilterType, setLogFilterType] = useState<string>('all');

  // CSV Import State
  const [isImportModalOpen, setIsImportModalOpen] = useState(false);
  const [parsedProducts, setParsedProducts] = useState<{
    code: string;
    name: string;
    brand: string;
    size: string;
    category: string;
    unitPrice: number;
    minStockLevel: number;
    isValid: boolean;
    validationError?: string;
    isExisting?: boolean;
  }[] | null>(null);
  const [importFileName, setImportFileName] = useState<string>('');
  const [importSuccessMessage, setImportSuccessMessage] = useState<string | null>(null);

  // Fine-grained permission helpers
  const canCreate = hasModulePermission(user, 'inventory', 'create');
  const canEdit = hasModulePermission(user, 'inventory', 'edit');
  const canDelete = hasModulePermission(user, 'inventory', 'delete');
  const canStockIn = canPerformStockIn(user);
  const canStockOut = canPerformStockOut(user);

  const refreshInventoryData = () => {
    setProducts(storage.getProducts());
    setAllLogs(storage.getLogs());
  };

  const downloadSampleCSV = () => {
    const headers = ['Product Code', 'Size / Spec', 'Product Name', 'Brand', 'Category', 'Unit Price', 'Low Stock Threshold'];
    const sampleRows = [
      ['HV-001', '50kg', 'Premium Cement 50kg', 'Dangote', 'Building Materials', '4500', '10'],
      ['HV-002', '12mm', 'Steel Rod 12mm', 'Tiger', 'Metal & Steel', '8500', '5'],
      ['HV-003', '5kVA', 'Solar Inverter 5kVA', 'Luminous', 'Electricals', '450000', '2']
    ];

    const csvContent = [
      headers.join(','),
      ...sampleRows.map(row => row.map(val => `"${val.replace(/"/g, '""')}"`).join(','))
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', 'HYSAM_Inventory_Import_Template.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleFileUpload = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setImportFileName(file.name);
    const reader = new FileReader();
    reader.onload = (event) => {
      const text = event.target?.result as string;
      if (!text) return;

      const lines: string[] = [];
      let currentLine = '';
      let inQuotes = false;

      for (let i = 0; i < text.length; i++) {
        const char = text[i];
        const nextChar = text[i + 1];

        if (char === '"') {
          if (inQuotes && nextChar === '"') {
            currentLine += '"';
            i++;
          } else {
            inQuotes = !inQuotes;
          }
        } else if ((char === '\r' || char === '\n') && !inQuotes) {
          if (char === '\r' && nextChar === '\n') i++;
          if (currentLine.trim()) lines.push(currentLine);
          currentLine = '';
        } else {
          currentLine += char;
        }
      }
      if (currentLine.trim()) lines.push(currentLine);

      if (lines.length <= 1) {
        alert('CSV file appears to be empty or missing data rows.');
        return;
      }

      const parseRow = (rowStr: string): string[] => {
        const cells: string[] = [];
        let cell = '';
        let inside = false;

        for (let i = 0; i < rowStr.length; i++) {
          const c = rowStr[i];
          const next = rowStr[i + 1];

          if (c === '"') {
            if (inside && next === '"') {
              cell += '"';
              i++;
            } else {
              inside = !inside;
            }
          } else if (c === ',' && !inside) {
            cells.push(cell.trim());
            cell = '';
          } else {
            cell += c;
          }
        }
        cells.push(cell.trim());
        return cells;
      };

      const rawHeaders = parseRow(lines[0]).map(h => h.toLowerCase().trim().replace(/[^a-z0-9]/g, ''));
      
      const findHeaderIndex = (keys: string[]) => {
        return rawHeaders.findIndex(h => keys.some(k => h.includes(k)));
      };

      const codeIdx = findHeaderIndex(['code', 'sku', 'itemcode']);
      const sizeIdx = findHeaderIndex(['size', 'spec', 'dimension', 'capacity']);
      const nameIdx = findHeaderIndex(['name', 'title', 'productname', 'itemname']);
      const brandIdx = findHeaderIndex(['brand', 'manufacturer', 'make']);
      const catIdx = findHeaderIndex(['category', 'cat', 'type']);
      const priceIdx = findHeaderIndex(['price', 'unitprice', 'cost', 'rate']);
      const minStockIdx = findHeaderIndex(['minstock', 'reorder', 'threshold', 'minimum', 'lowstock']);

      const seenCodesInCSV = new Set<string>();

      const parsed = lines.slice(1).map((rowStr) => {
        const cells = parseRow(rowStr);
        if (cells.length === 0 || (cells.length === 1 && !cells[0])) return null;

        const rawCode = codeIdx >= 0 ? cells[codeIdx] : '';
        const rawSize = sizeIdx >= 0 ? cells[sizeIdx] : '';
        const rawName = nameIdx >= 0 ? cells[nameIdx] : (cells[0] || '');
        const rawBrand = brandIdx >= 0 ? cells[brandIdx] : '';
        const rawCategory = catIdx >= 0 ? cells[catIdx] : '';
        const rawPrice = priceIdx >= 0 ? cells[priceIdx] : '';
        const rawMinStock = minStockIdx >= 0 ? cells[minStockIdx] : '';

        const code = rawCode.trim() || `PRD-${Math.random().toString(36).substr(2, 6).toUpperCase()}`;
        const name = rawName.trim();
        const brand = rawBrand.trim() || 'General';
        const size = rawSize.trim() || 'N/A';
        const category = rawCategory.trim() || settings.categories[0] || 'General';

        const cleanNum = (str: string) => parseFloat(str.replace(/[^0-9.]/g, '')) || 0;
        const unitPrice = cleanNum(rawPrice);
        const minStockLevel = rawMinStock ? Math.max(1, cleanNum(rawMinStock)) : settings.lowStockThreshold;

        const codeLower = code.toLowerCase();
        const existsInSystem = products.some(p => p.code.toLowerCase().trim() === codeLower);
        const existsInCSV = seenCodesInCSV.has(codeLower);
        seenCodesInCSV.add(codeLower);

        let isValid = !!name;
        let validationError: string | undefined = !name ? 'Missing product name' : undefined;

        if (isValid && existsInSystem) {
          isValid = false;
          validationError = `Product code '${code}' already exists in system`;
        } else if (isValid && existsInCSV) {
          isValid = false;
          validationError = `Duplicate product code '${code}' in CSV`;
        }

        return {
          code,
          name,
          brand,
          size,
          category,
          unitPrice,
          minStockLevel,
          isValid,
          validationError,
          isExisting: existsInSystem
        };
      }).filter(Boolean) as Array<{
        code: string;
        name: string;
        brand: string;
        size: string;
        category: string;
        unitPrice: number;
        minStockLevel: number;
        isValid: boolean;
        validationError?: string;
        isExisting?: boolean;
      }>;

      setParsedProducts(parsed);
    };

    reader.readAsText(file);
  };

  const handleConfirmImport = () => {
    if (!parsedProducts || parsedProducts.length === 0) return;

    const validItems = parsedProducts.filter(p => p.isValid);
    if (validItems.length === 0) {
      alert('No valid products to import. Ensure product codes are unique and names are provided.');
      return;
    }

    let createdCount = 0;
    let currentProductsList = [...products];

    validItems.forEach(item => {
      const newProdId = Math.random().toString(36).substr(2, 9);
      const newProd: Product = {
        id: newProdId,
        code: item.code,
        name: item.name,
        brand: item.brand,
        size: item.size,
        category: item.category,
        unitPrice: item.unitPrice,
        currentStock: 0,
        minStockLevel: item.minStockLevel,
        description: `${item.name} - ${item.brand}`,
        updatedAt: new Date().toISOString(),
        archived: false
      };

      currentProductsList.push(newProd);
      createdCount++;
    });

    storage.saveProducts(currentProductsList);

    storage.logActivity({
      type: 'product-created',
      description: `Bulk CSV Import completed: ${createdCount} new unique products registered by ${user.name}`,
      userId: user.id,
      userName: user.name
    });

    setProducts(currentProductsList);
    setAllLogs(storage.getLogs());
    setImportSuccessMessage(`Successfully registered ${createdCount} new products! All product codes are unique.`);
  };

  const resetImportState = () => {
    setIsImportModalOpen(false);
    setParsedProducts(null);
    setImportFileName('');
    setImportSuccessMessage(null);
  };

  const brands = useMemo(() => {
    const uniqueBrands = Array.from(new Set(products.map(p => p.brand).filter(Boolean)));
    return ['All Brands', ...uniqueBrands.sort()];
  }, [products]);

  const categories = useMemo(() => {
    return ['All Categories', ...settings.categories];
  }, [settings.categories]);

  // New Product State
  const [newProduct, setNewProduct] = useState<{
    code: string;
    name: string;
    size: string;
    brand: string;
    category: string;
    unitPrice: number;
    minStockLevel: number;
  }>({
    code: '',
    name: '',
    size: '',
    brand: '',
    category: settings.categories[0] || 'General',
    unitPrice: 0,
    minStockLevel: settings.lowStockThreshold
  });

  const filteredProducts = useMemo(() => {
    return products.filter(p => {
      const matchesArchived = showArchived ? p.archived : !p.archived;
      const matchesSearch = p.name.toLowerCase().includes(search.toLowerCase()) ||
                            p.code.toLowerCase().includes(search.toLowerCase()) ||
                            p.brand.toLowerCase().includes(search.toLowerCase());
      const matchesBrand = selectedBrand === 'All Brands' || p.brand === selectedBrand;
      const matchesCategory = selectedCategory === 'All Categories' || p.category === selectedCategory;
      
      const closingStock = storage.calculateClosingStock(p.id);
      let matchesStock = true;
      if (stockStatus === 'Low Stock') {
        matchesStock = closingStock > 0 && closingStock <= p.minStockLevel;
      } else if (stockStatus === 'Out of Stock') {
        matchesStock = closingStock <= 0;
      } else if (stockStatus === 'In Stock') {
        matchesStock = closingStock > p.minStockLevel;
      }

      return matchesArchived && matchesSearch && matchesBrand && matchesCategory && matchesStock;
    });
  }, [products, search, showArchived, selectedBrand, selectedCategory, stockStatus]);

  const productMetrics = useMemo(() => {
    const activeProds = products.filter(p => !p.archived);
    const count = activeProds.length;
    const totalValue = activeProds.reduce((acc, p) => acc + (p.unitPrice * storage.calculateClosingStock(p.id)), 0);
    const lowStockCount = activeProds.filter(p => {
      const stock = storage.calculateClosingStock(p.id);
      return stock <= p.minStockLevel && stock > 0;
    }).length;
    const outOfStockCount = activeProds.filter(p => storage.calculateClosingStock(p.id) <= 0).length;
    
    return { count, totalValue, lowStockCount, outOfStockCount };
  }, [products]);

  // Stock In Logs & Metrics
  const stockInLogs = useMemo(() => {
    return allLogs.filter(l => l.type === 'stock-in');
  }, [allLogs]);

  const stockInMetrics = useMemo(() => {
    const todayStr = new Date().toISOString().split('T')[0];
    const todayUnits = stockInLogs
      .filter(l => l.timestamp.startsWith(todayStr))
      .reduce((sum, l) => sum + l.quantity, 0);

    const totalUnits = stockInLogs.reduce((sum, l) => sum + l.quantity, 0);

    return { totalEntries: stockInLogs.length, todayUnits, totalUnits };
  }, [stockInLogs]);

  // Stock Out Logs & Metrics
  const stockOutLogs = useMemo(() => {
    return allLogs.filter(l => l.type === 'stock-out');
  }, [allLogs]);

  const stockOutMetrics = useMemo(() => {
    const todayStr = new Date().toISOString().split('T')[0];
    const todayUnits = stockOutLogs
      .filter(l => l.timestamp.startsWith(todayStr))
      .reduce((sum, l) => sum + l.quantity, 0);

    const totalUnits = stockOutLogs.reduce((sum, l) => sum + l.quantity, 0);

    return { totalEntries: stockOutLogs.length, todayUnits, totalUnits };
  }, [stockOutLogs]);

  // Comprehensive Unified Movement History (Stock In, Stock Out, Stock Delivered, Stock Returned)
  const allMovementEntries = useMemo(() => {
    interface UnifiedMovementEntry {
      id: string;
      type: 'stock-in' | 'stock-out' | 'delivery' | 'return';
      productId: string;
      quantity: number;
      notes: string;
      timestamp: string;
    }

    const entries: UnifiedMovementEntry[] = [];

    // 1. Manual Inventory Logs (Stock In / Stock Out)
    allLogs.forEach(log => {
      entries.push({
        id: log.id,
        type: log.type,
        productId: log.productId,
        quantity: log.quantity,
        notes: log.notes || '—',
        timestamp: log.timestamp
      });
    });

    // 2. Deliveries (Stock Delivered)
    const sales = storage.getSales();
    sales.forEach(sale => {
      if (sale.deliveryStatus === 'delivered') {
        sale.items.forEach((item, idx) => {
          entries.push({
            id: `DEL-${sale.id}-${item.productId}-${idx}`,
            type: 'delivery',
            productId: item.productId,
            quantity: item.quantity,
            notes: `Delivery Completed (Delivery #${sale.id.substring(0, 8).toUpperCase()} - ${sale.customerName})`,
            timestamp: sale.deliveredAt || sale.createdAt
          });
        });
      }
    });

    // 3. Customer Returns (Stock Returned)
    const returns = storage.getReturns();
    returns.forEach(ret => {
      const linkedSale = sales.find(s => s.id === ret.saleId);
      const isDeliveredSale = linkedSale ? linkedSale.deliveryStatus === 'delivered' : (ret.wasDelivered === true);

      if (isDeliveredSale) {
        const itemInSale = linkedSale?.items.find(i => i.productId === ret.productId);
        const maxDeliveredQty = itemInSale ? itemInSale.quantity : ret.quantity;
        const validReturnQty = Math.min(ret.quantity, maxDeliveredQty);

        if (validReturnQty > 0) {
          entries.push({
            id: `RET-${ret.id}`,
            type: 'return',
            productId: ret.productId,
            quantity: validReturnQty,
            notes: `Stock Returned from Delivery #${ret.saleId ? ret.saleId.substring(0, 8).toUpperCase() : 'N/A'}: ${ret.reason || 'No reason specified'}`,
            timestamp: ret.createdAt || ret.timestamp || new Date().toISOString()
          });
        }
      }
    });

    return entries.sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime());
  }, [allLogs, products]);

  const handleAddProduct = () => {
    const trimmedCode = newProduct.code.trim();
    if (!trimmedCode || !newProduct.name.trim()) return;

    const codeExists = products.some(p => p.code.toLowerCase().trim() === trimmedCode.toLowerCase());
    if (codeExists) {
      alert(`⚠️ CONFLICT: Product code "${trimmedCode}" is already in use by another product in the database. Product codes must be unique.`);
      return;
    }

    const product: Product = {
      ...newProduct,
      code: trimmedCode,
      id: Math.random().toString(36).substr(2, 9),
      description: `${newProduct.name} - ${newProduct.brand}`,
      currentStock: 0,
      updatedAt: new Date().toISOString()
    };

    const updated = [...products, product];
    storage.saveProducts(updated);
    storage.logActivity({
      type: 'product-created',
      description: `New product registered: ${product.name} (${product.code})`,
      userId: user.id,
      userName: user.name
    });
    setProducts(updated);
    setIsAddProductOpen(false);
    setNewProduct({
      code: '',
      name: '',
      size: '',
      brand: '',
      category: settings.categories[0] || 'General',
      unitPrice: 0,
      minStockLevel: settings.lowStockThreshold
    });
  };

  const handleStartEdit = (product: Product) => {
    setEditingProduct(product);
    setIsEditProductOpen(true);
  };

  const handleEditProduct = () => {
    if (!editingProduct || !editingProduct.code.trim() || !editingProduct.name.trim()) return;

    const trimmedCode = editingProduct.code.trim();
    const codeExists = products.some(p => p.id !== editingProduct.id && p.code.toLowerCase().trim() === trimmedCode.toLowerCase());
    if (codeExists) {
      alert(`⚠️ DUPLICATE CODE: The product code "${trimmedCode}" already exists for another item. Please use a unique code.`);
      return;
    }

    const updated = products.map(p => 
      p.id === editingProduct.id 
        ? { ...editingProduct, code: trimmedCode, description: `${editingProduct.name} - ${editingProduct.brand}`, updatedAt: new Date().toISOString() } 
        : p
    );
    storage.saveProducts(updated);
    storage.logActivity({
      type: 'product-created',
      description: `Product modified: ${editingProduct.name} (${editingProduct.code})`,
      userId: user.id,
      userName: user.name
    });
    setProducts(updated);
    setIsEditProductOpen(false);
    setEditingProduct(null);
  };

  const [productToDelete, setProductToDelete] = useState<Product | null>(null);
  const [isConfirmingStock, setIsConfirmingStock] = useState(false);

  const handleDeleteProduct = () => {
    if (!productToDelete) return;

    const id = productToDelete.id;
    const updated = products.map(p => 
      p.id === id ? { ...p, archived: true, updatedAt: new Date().toISOString() } : p
    );
    storage.saveProducts(updated);
    storage.logActivity({
      type: 'product-created',
      description: `Product archived: ${productToDelete.name} (${productToDelete.code})`,
      userId: user.id,
      userName: user.name
    });
    setProducts(updated);
    setProductToDelete(null);
  };

  const [isHistoryOpen, setIsHistoryOpen] = useState(false);
  const [historyProduct, setHistoryProduct] = useState<Product | null>(null);

  const productHistory = useMemo(() => {
    if (!historyProduct) return [];
    
    const productLogs = storage.getLogs().filter(l => l.productId === historyProduct.id).map(l => ({
      date: l.timestamp,
      type: l.type === 'stock-in' ? 'Stock In' : 'Stock Out',
      qty: l.type === 'stock-in' ? l.quantity : -l.quantity,
      note: l.notes,
      icon: l.type === 'stock-in' ? ArrowUpRight : ArrowDownLeft,
      color: l.type === 'stock-in' ? 'text-accent-theme' : 'text-rose-600'
    }));

    const productDeliveries = storage.getSales().filter(s => 
      s.deliveryStatus === 'delivered' && 
      s.items.some(i => i.productId === historyProduct.id)
    ).map(s => {
      const item = s.items.find(i => i.productId === historyProduct.id);
      return {
        date: s.deliveredAt || s.createdAt,
        type: 'Delivery',
        qty: -(item?.quantity || 0),
        note: `Order #${s.id} to ${s.customerName}`,
        icon: Truck,
        color: 'text-amber-600'
      };
    });

    const productReturns = storage.getReturns().filter(r => r.productId === historyProduct.id).map(r => ({
      date: r.createdAt,
      type: 'Return',
      qty: r.quantity,
      note: `Return for Order #${r.saleId} (${r.customerName})`,
      icon: RotateCcw,
      color: 'text-primary-theme'
    }));

    return [...productLogs, ...productDeliveries, ...productReturns].sort((a, b) => 
      new Date(b.date).getTime() - new Date(a.date).getTime()
    );
  }, [historyProduct]);

  // Handle Quick Modal Stock Action
  const initiateStockAction = () => {
    if (!selectedProduct || Number(quantity) <= 0) return;
    setIsConfirmingStock(true);
  };

  const handleStockAction = () => {
    if (!selectedProduct || !quantity) return;

    const qtyNum = typeof quantity === 'string' ? parseFloat(quantity) : quantity;
    if (isNaN(qtyNum) || qtyNum <= 0) {
      alert('Please enter a valid quantity greater than zero.');
      return;
    }

    const closingStock = storage.calculateClosingStock(selectedProduct.id);
    const change = logType === 'stock-in' ? qtyNum : -qtyNum;
    const newStock = closingStock + change;

    if (newStock < 0) {
      alert(`Insufficient stock. Current closing stock is ${closingStock} units.`);
      return;
    }

    const updatedProducts = products.map(p => 
      p.id === selectedProduct.id 
        ? { ...p, currentStock: p.currentStock + change, updatedAt: new Date().toISOString() }
        : p
    );

    const newLog: InventoryLog = {
      id: Math.random().toString(36).substr(2, 9),
      productId: selectedProduct.id,
      type: logType,
      quantity: qtyNum,
      userId: user.id,
      notes,
      timestamp: new Date().toISOString()
    };

    const logs = storage.getLogs();
    storage.saveLogs([newLog, ...logs]);
    storage.logActivity({
      type: 'stock-update',
      description: `${logType === 'stock-in' ? 'Stock In' : 'Stock Out'} for ${selectedProduct.name}: ${qtyNum} units`,
      userId: user.id,
      userName: user.name
    });
    storage.saveProducts(updatedProducts);
    setProducts(updatedProducts);
    setAllLogs(storage.getLogs());
    setIsLogOpen(false);
    setIsConfirmingStock(false);
    setQuantity(1);
    setNotes('');
  };

  // Dedicated Stock In Submit
  const handleSubmitDirectStockIn = (e: FormEvent) => {
    e.preventDefault();
    if (!stockInProductId) return;

    const qtyNum = typeof stockInQty === 'string' ? parseFloat(stockInQty) : stockInQty;
    if (isNaN(qtyNum) || qtyNum <= 0) {
      alert('Please enter a valid quantity greater than zero.');
      return;
    }

    const targetProduct = products.find(p => p.id === stockInProductId);
    if (!targetProduct) return;

    const updatedProducts = products.map(p => 
      p.id === targetProduct.id 
        ? { ...p, currentStock: p.currentStock + qtyNum, updatedAt: new Date().toISOString() }
        : p
    );

    const fullNote = [stockInSupplier ? `Supplier: ${stockInSupplier}` : '', stockInNotes].filter(Boolean).join(' | ');

    const newLog: InventoryLog = {
      id: Math.random().toString(36).substr(2, 9),
      productId: targetProduct.id,
      type: 'stock-in',
      quantity: qtyNum,
      userId: user.id,
      notes: fullNote || 'Stock In batch entry',
      timestamp: new Date().toISOString()
    };

    const logs = storage.getLogs();
    storage.saveLogs([newLog, ...logs]);
    storage.saveProducts(updatedProducts);
    storage.logActivity({
      type: 'stock-update',
      description: `Dedicated Stock In: +${qtyNum} units for ${targetProduct.name} by ${user.name}`,
      userId: user.id,
      userName: user.name
    });

    setProducts(updatedProducts);
    setAllLogs(storage.getLogs());
    setStockInFeedback(`Successfully registered Stock In for ${targetProduct.name} (+${qtyNum} units)`);
    setStockInQty(1);
    setStockInSupplier('');
    setStockInNotes('');

    setTimeout(() => setStockInFeedback(null), 4000);
  };

  // Dedicated Stock Out Submit
  const handleSubmitDirectStockOut = (e: FormEvent) => {
    e.preventDefault();
    if (!stockOutProductId) return;

    const qtyNum = typeof stockOutQty === 'string' ? parseFloat(stockOutQty) : stockOutQty;
    if (isNaN(qtyNum) || qtyNum <= 0) {
      alert('Please enter a valid quantity greater than zero.');
      return;
    }

    const targetProduct = products.find(p => p.id === stockOutProductId);
    if (!targetProduct) return;

    const closingStock = storage.calculateClosingStock(targetProduct.id);
    if (closingStock < qtyNum) {
      alert(`Cannot dispatch ${qtyNum} units. Only ${closingStock} units currently in stock.`);
      return;
    }

    const updatedProducts = products.map(p => 
      p.id === targetProduct.id 
        ? { ...p, currentStock: p.currentStock - qtyNum, updatedAt: new Date().toISOString() }
        : p
    );

    const fullNote = [stockOutReason ? `Reason: ${stockOutReason}` : '', stockOutNotes].filter(Boolean).join(' | ');

    const newLog: InventoryLog = {
      id: Math.random().toString(36).substr(2, 9),
      productId: targetProduct.id,
      type: 'stock-out',
      quantity: qtyNum,
      userId: user.id,
      notes: fullNote || 'Stock Out dispatch entry',
      timestamp: new Date().toISOString()
    };

    const logs = storage.getLogs();
    storage.saveLogs([newLog, ...logs]);
    storage.saveProducts(updatedProducts);
    storage.logActivity({
      type: 'stock-update',
      description: `Dedicated Stock Out: -${qtyNum} units for ${targetProduct.name} by ${user.name}`,
      userId: user.id,
      userName: user.name
    });

    setProducts(updatedProducts);
    setAllLogs(storage.getLogs());
    setStockOutFeedback(`Successfully registered Stock Out for ${targetProduct.name} (-${qtyNum} units)`);
    setStockOutQty(1);
    setStockOutReason('');
    setStockOutNotes('');

    setTimeout(() => setStockOutFeedback(null), 4000);
  };

  const handleRestoreProduct = (product: Product) => {
    const updated = products.map(p => 
      p.id === product.id ? { ...p, archived: false, updatedAt: new Date().toISOString() } : p
    );
    storage.saveProducts(updated);
    storage.logActivity({
      type: 'product-created',
      description: `Product restored: ${product.name} (${product.code})`,
      userId: user.id,
      userName: user.name
    });
    setProducts(updated);
  };

  return (
    <div ref={containerRef} className="space-y-6">
      {/* Module Title Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-black text-slate-900 tracking-tight">INVENTORY MANAGEMENT</h2>
          <p className="text-slate-500 text-xs font-medium mt-0.5">Centralized warehouse catalog, Stock In receiving, and Stock Out dispatches</p>
        </div>
      </div>

      {/* Sub-Navigation Tabs under Inventory */}
      <div className="bg-card-theme-bg p-2 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap gap-1.5" id="inventory-sub-navigation">
        <button
          onClick={() => handleSubTabSwitch('products')}
          className={`flex items-center gap-2.5 px-4 py-2.5 rounded-xl font-bold text-xs transition-all cursor-pointer ${
            activeTab === 'products'
              ? 'bg-slate-900 text-white shadow-md'
              : 'text-slate-600 hover:bg-slate-100'
          }`}
          id="tab-products-catalog"
        >
          <Package size={16} />
          <span>Products Catalog</span>
          <span className={`px-2 py-0.5 rounded-full text-[10px] ${
            activeTab === 'products' ? 'bg-card-theme-bg/20 text-white' : 'bg-slate-100 text-slate-700'
          }`}>
            {products.filter(p => !p.archived).length}
          </span>
        </button>

        <button
          onClick={() => handleSubTabSwitch('stock-in')}
          className={`flex items-center gap-2.5 px-4 py-2.5 rounded-xl font-bold text-xs transition-all cursor-pointer ${
            activeTab === 'stock-in'
              ? 'bg-accent-theme text-white shadow-md'
              : 'text-slate-600 hover:bg-accent-theme-light hover:text-accent-theme-dark'
          }`}
          id="tab-stock-in-dashboard"
        >
          <ArrowUpRight size={16} className={activeTab === 'stock-in' ? 'text-white' : 'text-accent-theme'} />
          <span>Stock In Dashboard</span>
          <span className={`px-2 py-0.5 rounded-full text-[10px] ${
            activeTab === 'stock-in' ? 'bg-card-theme-bg/20 text-white' : 'bg-accent-theme-light text-accent-theme-dark'
          }`}>
            +{stockInMetrics.todayUnits} today
          </span>
        </button>

        <button
          onClick={() => handleSubTabSwitch('stock-out')}
          className={`flex items-center gap-2.5 px-4 py-2.5 rounded-xl font-bold text-xs transition-all cursor-pointer ${
            activeTab === 'stock-out'
              ? 'bg-rose-600 text-white shadow-md'
              : 'text-slate-600 hover:bg-rose-50 hover:text-rose-800'
          }`}
          id="tab-stock-out-dashboard"
        >
          <ArrowDownLeft size={16} className={activeTab === 'stock-out' ? 'text-white' : 'text-rose-600'} />
          <span>Stock Out Dashboard</span>
          <span className={`px-2 py-0.5 rounded-full text-[10px] ${
            activeTab === 'stock-out' ? 'bg-card-theme-bg/20 text-white' : 'bg-rose-100 text-rose-800'
          }`}>
            -{stockOutMetrics.todayUnits} today
          </span>
        </button>

        <button
          onClick={() => handleSubTabSwitch('movement-logs')}
          className={`flex items-center gap-2.5 px-4 py-2.5 rounded-xl font-bold text-xs transition-all cursor-pointer ml-auto ${
            activeTab === 'movement-logs'
              ? 'bg-purple-600 text-white shadow-md'
              : 'text-slate-600 hover:bg-purple-50 hover:text-purple-800'
          }`}
          id="tab-movement-audit-logs"
        >
          <Clock size={16} />
          <span>Movement History</span>
          <span className={`px-2 py-0.5 rounded-full text-[10px] ${
            activeTab === 'movement-logs' ? 'bg-card-theme-bg/20 text-white' : 'bg-purple-100 text-purple-800'
          }`}>
            {allLogs.length}
          </span>
        </button>
      </div>

      {/* ========================================================================= */}
      {/* TAB 1: PRODUCTS CATALOG DASHBOARD */}
      {/* ========================================================================= */}
      {activeTab === 'products' && (
        <div className="space-y-6 animate-fade-in">
          {/* Top KPI Metric Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="bg-card-theme-bg p-5 rounded-2xl border border-slate-200 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Total Products</span>
                <div className="w-9 h-9 bg-primary-theme-light text-primary-theme rounded-xl flex items-center justify-center">
                  <Package size={18} />
                </div>
              </div>
              <div className="text-2xl font-black text-slate-900 mt-2">{productMetrics.count}</div>
              <p className="text-[11px] text-slate-400 mt-1 font-medium">Registered in catalog</p>
            </div>

            <div className="bg-card-theme-bg p-5 rounded-2xl border border-slate-200 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Inventory Value</span>
                <div className="w-9 h-9 bg-accent-theme-light text-accent-theme rounded-xl flex items-center justify-center">
                  <TrendingUp size={18} />
                </div>
              </div>
              <div className="text-2xl font-black text-slate-900 mt-2">{settings.currency}{productMetrics.totalValue.toLocaleString()}</div>
              <p className="text-[11px] text-slate-400 mt-1 font-medium">Stock at retail price</p>
            </div>

            <div className="bg-card-theme-bg p-5 rounded-2xl border border-slate-200 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Low Stock Alert</span>
                <div className="w-9 h-9 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                  <AlertTriangle size={18} />
                </div>
              </div>
              <div className="text-2xl font-black text-amber-600 mt-2">{productMetrics.lowStockCount}</div>
              <p className="text-[11px] text-amber-700 mt-1 font-medium">Below reorder threshold</p>
            </div>

            <div className="bg-card-theme-bg p-5 rounded-2xl border border-slate-200 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Out of Stock</span>
                <div className="w-9 h-9 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                  <AlertCircle size={18} />
                </div>
              </div>
              <div className="text-2xl font-black text-rose-600 mt-2">{productMetrics.outOfStockCount}</div>
              <p className="text-[11px] text-rose-700 mt-1 font-medium">Requires immediate restock</p>
            </div>
          </div>

          {/* Controls & Search Bar */}
          <div className="bg-card-theme-bg p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-3">
              <div className="relative flex-1 max-w-md">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                <input 
                  type="text"
                  placeholder="Search products by code, name or brand..."
                  className="pl-10 pr-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-theme w-full text-xs font-semibold bg-layout-theme-bg/50"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                />
              </div>

              <button
                onClick={() => setShowArchived(!showArchived)}
                className={`px-3.5 py-2 rounded-xl text-xs font-bold transition-all border cursor-pointer whitespace-nowrap ${
                  showArchived 
                    ? 'bg-amber-50 border-amber-200 text-amber-700' 
                    : 'bg-card-theme-bg border-slate-200 text-slate-600 hover:bg-layout-theme-bg'
                }`}
              >
                {showArchived ? 'Active Products' : 'Archived Items'}
              </button>
            </div>

            {canCreate && (
              <div className="flex items-center gap-2">
                <button 
                  onClick={() => setIsImportModalOpen(true)}
                  className="bg-accent-theme hover:bg-accent-theme-hover text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 transition-colors cursor-pointer shadow-sm"
                  title="Bulk Product CSV Import"
                  id="inventory-bulk-csv-import-btn"
                >
                  <Upload size={16} />
                  Import CSV
                </button>
                <button 
                  onClick={() => setIsAddProductOpen(true)}
                  className="bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-xl font-bold text-xs flex items-center gap-2 transition-colors cursor-pointer shadow-sm"
                >
                  <Plus size={16} />
                  Add Product
                </button>
              </div>
            )}
          </div>

          {/* Filters Bar */}
          <div className="flex flex-wrap gap-3 items-center bg-card-theme-bg p-3 rounded-2xl border border-slate-200 shadow-sm">
            <div className="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-2">
              <Filter size={14} />
              Filter Catalog:
            </div>
            
            <select 
              value={selectedBrand}
              onChange={(e) => setSelectedBrand(e.target.value)}
              className="text-xs font-bold text-slate-600 bg-layout-theme-bg border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-theme/20 cursor-pointer"
            >
              {brands.map(b => <option key={b} value={b}>{b}</option>)}
            </select>

            <select 
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="text-xs font-bold text-slate-600 bg-layout-theme-bg border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-theme/20 cursor-pointer"
            >
              {categories.map(c => <option key={c} value={c}>{c}</option>)}
            </select>

            <select 
              value={stockStatus}
              onChange={(e) => setStockStatus(e.target.value)}
              className="text-xs font-bold text-slate-600 bg-layout-theme-bg border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-theme/20 cursor-pointer"
            >
              <option value="All Status">Stock Level: All</option>
              <option value="In Stock">In Stock</option>
              <option value="Low Stock">Low Stock</option>
              <option value="Out of Stock">Out of Stock</option>
            </select>

            {(selectedBrand !== 'All Brands' || selectedCategory !== 'All Categories' || stockStatus !== 'All Status') && (
              <button 
                onClick={() => {
                  setSelectedBrand('All Brands');
                  setSelectedCategory('All Categories');
                  setStockStatus('All Status');
                }}
                className="text-xs font-bold text-rose-600 hover:bg-rose-50 px-3 py-2 rounded-xl transition-colors ml-auto flex items-center gap-1.5 cursor-pointer"
              >
                <X size={14} />
                Reset Filters
              </button>
            )}
          </div>

          {/* Products Table */}
          <div className="bg-card-theme-bg rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-layout-theme-bg border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                    <th className="px-6 py-4">Product Details</th>
                    <th className="px-6 py-4">Code / Size</th>
                    <th className="px-6 py-4">Category</th>
                    <th className="px-6 py-4">Price</th>
                    <th className="px-6 py-4 text-center">Closing Stock</th>
                    <th className="px-6 py-4 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {filteredProducts.map((product) => {
                    const closingStock = storage.calculateClosingStock(product.id);
                    return (
                      <tr key={product.id} className="hover:bg-layout-theme-bg/80 transition-colors">
                        <td className="px-6 py-4">
                          <div className="font-bold text-slate-900 text-sm">{product.name}</div>
                          <div className="text-xs text-slate-500 font-medium">{product.brand}</div>
                        </td>
                        <td className="px-6 py-4">
                          <div className="font-mono text-xs font-bold text-primary-theme">{product.code}</div>
                          <div className="text-xs text-slate-400 font-medium">{product.size}</div>
                        </td>
                        <td className="px-6 py-4">
                          <span className="px-2.5 py-1 bg-slate-100 rounded-lg text-[10px] font-bold text-slate-600">
                            {product.category}
                          </span>
                        </td>
                        <td className="px-6 py-4 font-mono text-sm font-bold text-slate-800">
                          {settings.currency}{product.unitPrice.toLocaleString()}
                        </td>
                        <td className="px-6 py-4 text-center">
                          {(() => {
                            const b = storage.getStockBreakdown(product.id);
                            return (
                              <div className="flex flex-col items-center">
                                <div 
                                  className="inline-flex items-center justify-center gap-2 px-3 py-1 bg-layout-theme-bg rounded-xl border border-slate-200 shadow-xs" 
                                  title={`Closing Stock = Opening (${b.openingStock}) + Stock In (${b.stockIn}) + Stock Return (${b.stockReturn}) - Stock Out (${b.stockOut}) - Delivered (${b.delivered}) = ${b.closingStock}`}
                                >
                                  <span className={`w-2 h-2 rounded-full ${
                                    b.closingStock <= 0 ? 'bg-rose-500 animate-ping' :
                                    b.closingStock <= product.minStockLevel ? 'bg-amber-500 animate-pulse' : 'bg-accent-theme'
                                  }`} />
                                  <span className="font-mono font-black text-sm text-slate-900">{b.closingStock}</span>
                                </div>
                                <div className="text-[10px] text-slate-500 font-mono mt-1" title="Formula: Opening + Stock In + Stock Return - Stock Out - Delivered">
                                  O:{b.openingStock} | In:+{b.stockIn} | Ret:+{b.stockReturn} | Out:-{b.stockOut} | Del:-{b.delivered}
                                </div>
                              </div>
                            );
                          })()}
                        </td>
                        <td className="px-6 py-4 text-right">
                          <div className="flex items-center justify-end gap-1">
                            <button 
                              onClick={() => {
                                setHistoryProduct(product);
                                setIsHistoryOpen(true);
                              }}
                              className="p-1.5 text-primary-theme hover:bg-primary-theme-light rounded-lg transition-colors cursor-pointer"
                              title="Stock Movement History"
                            >
                              <Clock size={16} />
                            </button>
                            
                            {!product.archived ? (
                              <>
                                {canStockIn && (
                                  <button 
                                    onClick={() => {
                                      setSelectedProduct(product);
                                      setLogType('stock-in');
                                      setIsLogOpen(true);
                                    }}
                                    className="p-1.5 text-accent-theme hover:bg-accent-theme-light rounded-lg transition-colors cursor-pointer"
                                    title="Quick Stock In"
                                  >
                                    <Plus size={16} />
                                  </button>
                                )}
                                {canStockOut && (
                                  <button 
                                    onClick={() => {
                                      setSelectedProduct(product);
                                      setLogType('stock-out');
                                      setIsLogOpen(true);
                                    }}
                                    className="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                    title="Quick Stock Out"
                                  >
                                    <Minus size={16} />
                                  </button>
                                )}
                                {canEdit && (
                                  <button 
                                    onClick={() => handleStartEdit(product)}
                                    className="p-1.5 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer"
                                    title="Edit Product Details"
                                  >
                                    <Edit2 size={16} />
                                  </button>
                                )}
                                {canDelete && (
                                  <button 
                                    onClick={() => setProductToDelete(product)}
                                    className="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                    title="Archive Product"
                                  >
                                    <Trash2 size={16} />
                                  </button>
                                )}
                              </>
                            ) : (
                              <button 
                                onClick={() => handleRestoreProduct(product)}
                                className="px-2.5 py-1 bg-amber-100 hover:bg-amber-200 text-amber-800 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                              >
                                Restore
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                  {filteredProducts.length === 0 && (
                    <tr>
                      <td colSpan={6} className="px-6 py-12 text-center text-slate-400">
                        <Package className="mx-auto mb-2 opacity-50" size={32} />
                        <p className="text-sm font-semibold">No products found matching your search and filter criteria.</p>
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 2: DEDICATED STOCK-IN DASHBOARD */}
      {/* ========================================================================= */}
      {activeTab === 'stock-in' && (
        <div className="space-y-6 animate-fade-in">
          {/* Stock In Metrics */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-accent-theme-dark text-white p-5 rounded-2xl shadow-sm relative overflow-hidden">
              <div className="absolute right-3 top-3 text-accent-theme-dark/40">
                <ArrowUpRight size={56} />
              </div>
              <span className="text-xs font-bold uppercase tracking-wider text-accent-theme-light">Stock In Today</span>
              <div className="text-3xl font-black text-white mt-1">+{stockInMetrics.todayUnits} units</div>
              <p className="text-xs text-accent-theme-light mt-1 font-medium">Added to warehouse inventory today</p>
            </div>

            <div className="bg-card-theme-bg p-5 rounded-2xl border border-slate-200 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Total Stock In Batches</span>
                <div className="w-9 h-9 bg-accent-theme-light text-accent-theme rounded-xl flex items-center justify-center">
                  <Boxes size={18} />
                </div>
              </div>
              <div className="text-2xl font-black text-slate-900 mt-2">{stockInMetrics.totalEntries}</div>
              <p className="text-[11px] text-slate-400 mt-1 font-medium">Recorded receiving logs</p>
            </div>

            <div className="bg-card-theme-bg p-5 rounded-2xl border border-slate-200 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Total Units Received</span>
                <div className="w-9 h-9 bg-accent-theme-light text-accent-theme rounded-xl flex items-center justify-center">
                  <TrendingUp size={18} />
                </div>
              </div>
              <div className="text-2xl font-black text-accent-theme mt-2">+{stockInMetrics.totalUnits.toLocaleString()}</div>
              <p className="text-[11px] text-slate-400 mt-1 font-medium">Cumulative units stocked in</p>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {/* Direct Stock-In Processing Form */}
            <div className="lg:col-span-5 bg-card-theme-bg p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
              <div className="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div className="w-10 h-10 bg-accent-theme-light text-accent-theme-hover rounded-xl flex items-center justify-center">
                  <ArrowUpRight size={22} />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-base">Receive New Stock (Stock In)</h3>
                  <p className="text-xs text-slate-500">Record incoming inventory from suppliers or factory</p>
                </div>
              </div>

              {stockInFeedback && (
                <div className="p-3 bg-accent-theme-light border border-accent-theme-light text-accent-theme-dark text-xs rounded-xl font-bold flex items-center gap-2">
                  <CheckCircle size={16} className="text-accent-theme shrink-0" />
                  {stockInFeedback}
                </div>
              )}

              {canStockIn ? (
                <form onSubmit={handleSubmitDirectStockIn} className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                      Select Target Product
                    </label>
                    <select
                      value={stockInProductId}
                      onChange={(e) => setStockInProductId(e.target.value)}
                      required
                      className="w-full px-3.5 py-2.5 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-accent-theme cursor-pointer"
                    >
                      <option value="">Select product to receive stock...</option>
                      {products.filter(p => !p.archived).map(p => {
                        const stock = storage.calculateClosingStock(p.id);
                        return (
                          <option key={p.id} value={p.id}>
                            [{p.code}] {p.name} ({p.brand}) — Current: {stock} units
                          </option>
                        );
                      })}
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                      Quantity to Stock In
                    </label>
                    <div className="flex items-center gap-2">
                      <input 
                        type="number"
                        min="1"
                        step="any"
                        value={stockInQty}
                        onChange={(e) => setStockInQty(e.target.value)}
                        className="flex-1 px-3.5 py-2.5 bg-layout-theme-bg border border-slate-200 rounded-xl text-sm font-bold font-mono focus:outline-none focus:ring-2 focus:ring-accent-theme"
                      />
                      <div className="flex gap-1">
                        {[10, 50, 100].map(val => (
                          <button
                            type="button"
                            key={val}
                            onClick={() => setStockInQty(val)}
                            className="px-2.5 py-2 bg-slate-100 hover:bg-accent-theme-light hover:text-accent-theme-dark text-slate-700 text-xs font-bold rounded-lg transition-colors cursor-pointer"
                          >
                            +{val}
                          </button>
                        ))}
                      </div>
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                      Supplier / Source Reference
                    </label>
                    <div className="relative">
                      <Building2 className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
                      <input 
                        type="text"
                        placeholder="e.g. Dangote Cement Plc / Batch #4092"
                        value={stockInSupplier}
                        onChange={(e) => setStockInSupplier(e.target.value)}
                        className="w-full pl-9 pr-3.5 py-2.5 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-accent-theme"
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                      Additional Notes / Bay Location
                    </label>
                    <textarea 
                      rows={2}
                      placeholder="e.g. Received at Bay 3, verified by store officer"
                      value={stockInNotes}
                      onChange={(e) => setStockInNotes(e.target.value)}
                      className="w-full px-3.5 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-accent-theme"
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={!stockInProductId}
                    className="w-full py-3 bg-accent-theme hover:bg-accent-theme-hover disabled:opacity-50 text-white font-bold text-sm rounded-xl transition-all shadow-md shadow-accent-theme-light flex items-center justify-center gap-2 cursor-pointer"
                  >
                    <ArrowUpRight size={18} />
                    Confirm Stock In Entry
                  </button>
                </form>
              ) : (
                <div className="p-4 bg-amber-50 text-amber-800 rounded-xl text-xs font-semibold">
                  You do not have authorization permissions to perform Stock In entries.
                </div>
              )}
            </div>

            {/* Stock-In History Table */}
            <div className="lg:col-span-7 bg-card-theme-bg p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
              <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 className="font-bold text-slate-900 text-base">Recent Stock In Entries</h3>
                <span className="text-xs font-bold text-accent-theme bg-accent-theme-light px-2.5 py-1 rounded-lg">
                  {stockInLogs.length} Records
                </span>
              </div>

              <div className="border border-slate-200 rounded-2xl overflow-hidden max-h-[460px] overflow-y-auto">
                <table className="w-full text-left text-xs">
                  <thead className="bg-layout-theme-bg text-[10px] font-bold text-slate-500 uppercase tracking-wider sticky top-0">
                    <tr>
                      <th className="px-4 py-3">Timestamp</th>
                      <th className="px-4 py-3">Product Name</th>
                      <th className="px-4 py-3 text-center">Qty Added</th>
                      <th className="px-4 py-3">Notes / Source</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {stockInLogs.map((log) => {
                      const prod = products.find(p => p.id === log.productId);
                      return (
                        <tr key={log.id} className="hover:bg-layout-theme-bg">
                          <td className="px-4 py-3 font-mono text-slate-500 text-[11px]">
                            {format(new Date(log.timestamp), 'MMM dd, HH:mm')}
                          </td>
                          <td className="px-4 py-3 font-bold text-slate-900">
                            {prod ? prod.name : 'Unknown Product'}
                            <div className="text-[10px] text-slate-400 font-mono">{prod?.code}</div>
                          </td>
                          <td className="px-4 py-3 text-center">
                            <span className="px-2.5 py-1 bg-accent-theme-light text-accent-theme-dark rounded-lg font-mono font-black text-xs">
                              +{log.quantity}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-slate-600 text-xs">{log.notes || '—'}</td>
                        </tr>
                      );
                    })}
                    {stockInLogs.length === 0 && (
                      <tr>
                        <td colSpan={4} className="px-4 py-8 text-center text-slate-400 font-medium">
                          No stock-in records found.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 3: DEDICATED STOCK-OUT DASHBOARD */}
      {/* ========================================================================= */}
      {activeTab === 'stock-out' && (
        <div className="space-y-6 animate-fade-in">
          {/* Stock Out Metrics */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="bg-rose-900 text-white p-5 rounded-2xl shadow-sm relative overflow-hidden">
              <div className="absolute right-3 top-3 text-rose-800/40">
                <ArrowDownLeft size={56} />
              </div>
              <span className="text-xs font-bold uppercase tracking-wider text-rose-200">Dispatched Today</span>
              <div className="text-3xl font-black text-white mt-1">-{stockOutMetrics.todayUnits} units</div>
              <p className="text-xs text-rose-200 mt-1 font-medium">Dispatched or issued from warehouse today</p>
            </div>

            <div className="bg-card-theme-bg p-5 rounded-2xl border border-slate-200 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Stock Out Entries</span>
                <div className="w-9 h-9 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                  <Truck size={18} />
                </div>
              </div>
              <div className="text-2xl font-black text-slate-900 mt-2">{stockOutMetrics.totalEntries}</div>
              <p className="text-[11px] text-slate-400 mt-1 font-medium">Recorded dispatch logs</p>
            </div>

            <div className="bg-card-theme-bg p-5 rounded-2xl border border-slate-200 shadow-sm">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Total Units Dispatched</span>
                <div className="w-9 h-9 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center">
                  <TrendingDown size={18} />
                </div>
              </div>
              <div className="text-2xl font-black text-rose-600 mt-2">-{stockOutMetrics.totalUnits.toLocaleString()}</div>
              <p className="text-[11px] text-slate-400 mt-1 font-medium">Cumulative units dispatched</p>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {/* Direct Stock-Out Processing Form */}
            <div className="lg:col-span-5 bg-card-theme-bg p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
              <div className="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div className="w-10 h-10 bg-rose-100 text-rose-700 rounded-xl flex items-center justify-center">
                  <ArrowDownLeft size={22} />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-base">Issue / Dispatch Stock (Stock Out)</h3>
                  <p className="text-xs text-slate-500">Record stock issued for store dispatch or store transfers</p>
                </div>
              </div>

              {stockOutFeedback && (
                <div className="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl font-bold flex items-center gap-2">
                  <CheckCircle size={16} className="text-rose-600 shrink-0" />
                  {stockOutFeedback}
                </div>
              )}

              {canStockOut ? (
                <form onSubmit={handleSubmitDirectStockOut} className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                      Select Target Product
                    </label>
                    <select
                      value={stockOutProductId}
                      onChange={(e) => setStockOutProductId(e.target.value)}
                      required
                      className="w-full px-3.5 py-2.5 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500 cursor-pointer"
                    >
                      <option value="">Select product to dispatch...</option>
                      {products.filter(p => !p.archived).map(p => {
                        const stock = storage.calculateClosingStock(p.id);
                        return (
                          <option key={p.id} value={p.id} disabled={stock <= 0}>
                            [{p.code}] {p.name} — Current: {stock} units {stock <= 0 ? '(OUT OF STOCK)' : ''}
                          </option>
                        );
                      })}
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                      Quantity to Dispatch
                    </label>
                    <div className="flex items-center gap-2">
                      <input 
                        type="number"
                        min="1"
                        step="any"
                        value={stockOutQty}
                        onChange={(e) => setStockOutQty(e.target.value)}
                        className="flex-1 px-3.5 py-2.5 bg-layout-theme-bg border border-slate-200 rounded-xl text-sm font-bold font-mono focus:outline-none focus:ring-2 focus:ring-rose-500"
                      />
                      <div className="flex gap-1">
                        {[1, 5, 10, 20].map(val => (
                          <button
                            type="button"
                            key={val}
                            onClick={() => setStockOutQty(val)}
                            className="px-2.5 py-2 bg-slate-100 hover:bg-rose-100 hover:text-rose-800 text-slate-700 text-xs font-bold rounded-lg transition-colors cursor-pointer"
                          >
                            -{val}
                          </button>
                        ))}
                      </div>
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                      Dispatch Reason / Destination Ref
                    </label>
                    <input 
                      type="text"
                      placeholder="e.g. Sales Issue / Order #4029 / Store Outlet Transfer"
                      value={stockOutReason}
                      onChange={(e) => setStockOutReason(e.target.value)}
                      className="w-full px-3.5 py-2.5 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                      Additional Dispatch Notes
                    </label>
                    <textarea 
                      rows={2}
                      placeholder="e.g. Dispatched via delivery van #2"
                      value={stockOutNotes}
                      onChange={(e) => setStockOutNotes(e.target.value)}
                      className="w-full px-3.5 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500"
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={!stockOutProductId}
                    className="w-full py-3 bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white font-bold text-sm rounded-xl transition-all shadow-md shadow-rose-200 flex items-center justify-center gap-2 cursor-pointer"
                  >
                    <ArrowDownLeft size={18} />
                    Confirm Stock Out Dispatch
                  </button>
                </form>
              ) : (
                <div className="p-4 bg-amber-50 text-amber-800 rounded-xl text-xs font-semibold">
                  You do not have authorization permissions to perform Stock Out dispatches.
                </div>
              )}
            </div>

            {/* Stock-Out History Table */}
            <div className="lg:col-span-7 bg-card-theme-bg p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
              <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 className="font-bold text-slate-900 text-base">Recent Stock Out Dispatches</h3>
                <span className="text-xs font-bold text-rose-600 bg-rose-50 px-2.5 py-1 rounded-lg">
                  {stockOutLogs.length} Records
                </span>
              </div>

              <div className="border border-slate-200 rounded-2xl overflow-hidden max-h-[460px] overflow-y-auto">
                <table className="w-full text-left text-xs">
                  <thead className="bg-layout-theme-bg text-[10px] font-bold text-slate-500 uppercase tracking-wider sticky top-0">
                    <tr>
                      <th className="px-4 py-3">Timestamp</th>
                      <th className="px-4 py-3">Product Name</th>
                      <th className="px-4 py-3 text-center">Qty Dispatched</th>
                      <th className="px-4 py-3">Notes / Destination</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {stockOutLogs.map((log) => {
                      const prod = products.find(p => p.id === log.productId);
                      return (
                        <tr key={log.id} className="hover:bg-layout-theme-bg">
                          <td className="px-4 py-3 font-mono text-slate-500 text-[11px]">
                            {format(new Date(log.timestamp), 'MMM dd, HH:mm')}
                          </td>
                          <td className="px-4 py-3 font-bold text-slate-900">
                            {prod ? prod.name : 'Unknown Product'}
                            <div className="text-[10px] text-slate-400 font-mono">{prod?.code}</div>
                          </td>
                          <td className="px-4 py-3 text-center">
                            <span className="px-2.5 py-1 bg-rose-100 text-rose-800 rounded-lg font-mono font-black text-xs">
                              -{log.quantity}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-slate-600 text-xs">{log.notes || '—'}</td>
                        </tr>
                      );
                    })}
                    {stockOutLogs.length === 0 && (
                      <tr>
                        <td colSpan={4} className="px-4 py-8 text-center text-slate-400 font-medium">
                          No stock-out records found.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 4: MOVEMENT AUDIT LOGS */}
      {/* ========================================================================= */}
      {activeTab === 'movement-logs' && (
        <div className="space-y-6 animate-fade-in">
          {/* Header & Controls */}
          <div className="bg-card-theme-bg p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-3">
              <div className="relative flex-1 max-w-md">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
                <input 
                  type="text"
                  placeholder="Filter movement logs by product or notes..."
                  value={logSearch}
                  onChange={(e) => setLogSearch(e.target.value)}
                  className="pl-10 pr-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-500 w-full text-xs font-semibold bg-layout-theme-bg/50"
                />
              </div>

              <select
                value={logFilterType}
                onChange={(e) => setLogFilterType(e.target.value)}
                className="px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none cursor-pointer"
              >
                <option value="all">All Movements ({allMovementEntries.length})</option>
                <option value="stock-in">Stock In (+)</option>
                <option value="stock-out">Stock Out (-)</option>
                <option value="delivery">Stock Delivered (-)</option>
                <option value="return">Stock Returned (+)</option>
              </select>
            </div>
          </div>

          {/* Logs Audit Table */}
          <div className="bg-card-theme-bg rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-layout-theme-bg border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                    <th className="px-6 py-4">Timestamp</th>
                    <th className="px-6 py-4">Movement Type</th>
                    <th className="px-6 py-4">Product Details</th>
                    <th className="px-6 py-4 text-center">Quantity</th>
                    <th className="px-6 py-4">Reference Notes</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-xs">
                  {allMovementEntries
                    .filter(entry => {
                      if (logFilterType !== 'all' && entry.type !== logFilterType) return false;
                      if (!logSearch) return true;
                      const prod = products.find(p => p.id === entry.productId);
                      const searchLower = logSearch.toLowerCase();
                      return (
                        (prod && prod.name.toLowerCase().includes(searchLower)) ||
                        (prod && prod.code.toLowerCase().includes(searchLower)) ||
                        (entry.notes && entry.notes.toLowerCase().includes(searchLower))
                      );
                    })
                    .map((entry) => {
                      const prod = products.find(p => p.id === entry.productId);

                      let typeBadge = null;
                      let qtyDisplay = null;

                      if (entry.type === 'stock-in') {
                        typeBadge = (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">
                            <ArrowUpRight size={12} />
                            Stock In
                          </span>
                        );
                        qtyDisplay = (
                          <span className="font-mono font-black text-sm text-emerald-600">
                            +{entry.quantity}
                          </span>
                        );
                      } else if (entry.type === 'stock-out') {
                        typeBadge = (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase bg-rose-100 text-rose-800">
                            <ArrowDownLeft size={12} />
                            Stock Out
                          </span>
                        );
                        qtyDisplay = (
                          <span className="font-mono font-black text-sm text-rose-600">
                            -{entry.quantity}
                          </span>
                        );
                      } else if (entry.type === 'delivery') {
                        typeBadge = (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase bg-amber-100 text-amber-800">
                            <Truck size={12} />
                            Stock Delivered
                          </span>
                        );
                        qtyDisplay = (
                          <span className="font-mono font-black text-sm text-amber-600">
                            -{entry.quantity}
                          </span>
                        );
                      } else if (entry.type === 'return') {
                        typeBadge = (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase bg-purple-100 text-purple-800">
                            <RotateCcw size={12} />
                            Stock Returned
                          </span>
                        );
                        qtyDisplay = (
                          <span className="font-mono font-black text-sm text-purple-600">
                            +{entry.quantity}
                          </span>
                        );
                      }

                      return (
                        <tr key={entry.id} className="hover:bg-layout-theme-bg">
                          <td className="px-6 py-4 font-mono text-slate-500 font-medium">
                            {format(new Date(entry.timestamp), 'yyyy-MM-dd HH:mm')}
                          </td>
                          <td className="px-6 py-4">{typeBadge}</td>
                          <td className="px-6 py-4">
                            <div className="font-bold text-slate-900">{prod ? prod.name : 'Unknown Product'}</div>
                            <div className="font-mono text-[10px] text-slate-400">{prod?.code}</div>
                          </td>
                          <td className="px-6 py-4 text-center">{qtyDisplay}</td>
                          <td className="px-6 py-4 text-slate-600 font-medium">{entry.notes || '—'}</td>
                        </tr>
                      );
                    })}
                  {allMovementEntries.length === 0 && (
                    <tr>
                      <td colSpan={5} className="px-6 py-12 text-center text-slate-400 font-medium">
                        No movement audit logs logged yet.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODALS */}
      {/* ========================================================================= */}

      {/* Add Product Modal */}
      {isAddProductOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in">
          <div className="bg-card-theme-bg rounded-3xl p-6 max-w-lg w-full shadow-2xl space-y-4 border border-slate-100">
            <div className="flex justify-between items-center border-b pb-3 border-slate-100">
              <h3 className="font-bold text-lg text-slate-900">Register New Product</h3>
              <button onClick={() => setIsAddProductOpen(false)} className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                <X size={20} />
              </button>
            </div>
            
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Product Code</label>
                  <input 
                    type="text" 
                    placeholder="e.g. PRD-001"
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                    value={newProduct.code}
                    onChange={(e) => setNewProduct({...newProduct, code: e.target.value})}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Size / Spec</label>
                  <input 
                    type="text" 
                    placeholder="e.g. 50kg, 12mm"
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                    value={newProduct.size}
                    onChange={(e) => setNewProduct({...newProduct, size: e.target.value})}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Product Name</label>
                <input 
                  type="text" 
                  placeholder="e.g. Dangote Cement 50kg"
                  className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                  value={newProduct.name}
                  onChange={(e) => setNewProduct({...newProduct, name: e.target.value})}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Brand</label>
                  <input 
                    type="text" 
                    placeholder="e.g. Dangote"
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                    value={newProduct.brand}
                    onChange={(e) => setNewProduct({...newProduct, brand: e.target.value})}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Category</label>
                  <select 
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold cursor-pointer"
                    value={newProduct.category}
                    onChange={(e) => setNewProduct({...newProduct, category: e.target.value})}
                  >
                    {settings.categories.map(c => <option key={c} value={c}>{c}</option>)}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Unit Price ({settings.currency})</label>
                  <input 
                    type="number" 
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                    value={newProduct.unitPrice}
                    onChange={(e) => setNewProduct({...newProduct, unitPrice: parseFloat(e.target.value) || 0})}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Low Stock Limit</label>
                  <input 
                    type="number" 
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                    value={newProduct.minStockLevel}
                    onChange={(e) => setNewProduct({...newProduct, minStockLevel: parseInt(e.target.value) || 5})}
                  />
                </div>
              </div>

              <div className="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 flex items-start gap-2">
                <span className="font-bold shrink-0">💡 Note:</span>
                <span>All newly registered products start with 0 stock. Add physical inventory using the <strong>Stock In</strong> feature to ensure date-based audit accuracy.</span>
              </div>
            </div>

            <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
              <button 
                onClick={() => setIsAddProductOpen(false)}
                className="px-4 py-2 border border-slate-200 text-slate-600 font-bold text-xs rounded-xl hover:bg-layout-theme-bg cursor-pointer"
              >
                Cancel
              </button>
              <button 
                onClick={handleAddProduct}
                className="px-4 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl hover:bg-slate-800 cursor-pointer"
              >
                Save Product
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Edit Product Modal */}
      {isEditProductOpen && editingProduct && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in">
          <div className="bg-card-theme-bg rounded-3xl p-6 max-w-lg w-full shadow-2xl space-y-4 border border-slate-100">
            <div className="flex justify-between items-center border-b pb-3 border-slate-100">
              <h3 className="font-bold text-lg text-slate-900">Edit Product</h3>
              <button onClick={() => setIsEditProductOpen(false)} className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                <X size={20} />
              </button>
            </div>
            
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Product Code</label>
                  <input 
                    type="text" 
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                    value={editingProduct.code}
                    onChange={(e) => setEditingProduct({...editingProduct, code: e.target.value})}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Size / Spec</label>
                  <input 
                    type="text" 
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                    value={editingProduct.size}
                    onChange={(e) => setEditingProduct({...editingProduct, size: e.target.value})}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Product Name</label>
                <input 
                  type="text" 
                  className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                  value={editingProduct.name}
                  onChange={(e) => setEditingProduct({...editingProduct, name: e.target.value})}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Brand</label>
                  <input 
                    type="text" 
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                    value={editingProduct.brand}
                    onChange={(e) => setEditingProduct({...editingProduct, brand: e.target.value})}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Category</label>
                  <select 
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold cursor-pointer"
                    value={editingProduct.category}
                    onChange={(e) => setEditingProduct({...editingProduct, category: e.target.value})}
                  >
                    {settings.categories.map(c => <option key={c} value={c}>{c}</option>)}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Unit Price ({settings.currency})</label>
                  <input 
                    type="number" 
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                    value={editingProduct.unitPrice}
                    onChange={(e) => setEditingProduct({...editingProduct, unitPrice: parseFloat(e.target.value) || 0})}
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Low Stock Limit</label>
                  <input 
                    type="number" 
                    className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-bold"
                    value={editingProduct.minStockLevel}
                    onChange={(e) => setEditingProduct({...editingProduct, minStockLevel: parseInt(e.target.value) || 5})}
                  />
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
              <button 
                onClick={() => setIsEditProductOpen(false)}
                className="px-4 py-2 border border-slate-200 text-slate-600 font-bold text-xs rounded-xl hover:bg-layout-theme-bg cursor-pointer"
              >
                Cancel
              </button>
              <button 
                onClick={handleEditProduct}
                className="px-4 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl hover:bg-slate-800 cursor-pointer"
              >
                Update Product
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Quick Stock Log Modal */}
      {isLogOpen && selectedProduct && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in">
          <div className="bg-card-theme-bg rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4 border border-slate-100">
            <div className="flex justify-between items-center border-b pb-3 border-slate-100">
              <h3 className="font-bold text-lg text-slate-900">
                {logType === 'stock-in' ? 'Quick Stock In' : 'Quick Stock Out'}
              </h3>
              <button onClick={() => { setIsLogOpen(false); setIsConfirmingStock(false); }} className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                <X size={20} />
              </button>
            </div>

            <div className="p-3 bg-layout-theme-bg rounded-2xl border border-slate-100">
              <div className="font-bold text-slate-900 text-sm">{selectedProduct.name}</div>
              <div className="text-xs text-slate-500 font-mono mt-0.5">[{selectedProduct.code}] — Current Stock: {storage.calculateClosingStock(selectedProduct.id)} units</div>
            </div>

            <div className="space-y-3">
              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Quantity</label>
                <input 
                  type="number" 
                  min="1"
                  step="any"
                  className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl font-mono text-sm font-bold"
                  value={quantity}
                  onChange={(e) => setQuantity(e.target.value)}
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Notes / Reference</label>
                <input 
                  type="text" 
                  placeholder="e.g. Supplier delivery invoice #204"
                  className="w-full px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-xs font-medium"
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                />
              </div>
            </div>

            <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
              <button 
                onClick={() => { setIsLogOpen(false); setIsConfirmingStock(false); }}
                className="px-4 py-2 border border-slate-200 text-slate-600 font-bold text-xs rounded-xl hover:bg-layout-theme-bg cursor-pointer"
              >
                Cancel
              </button>
              <button 
                onClick={handleStockAction}
                className={`px-4 py-2 text-white font-bold text-xs rounded-xl cursor-pointer ${
                  logType === 'stock-in' ? 'bg-accent-theme hover:bg-accent-theme-hover' : 'bg-rose-600 hover:bg-rose-700'
                }`}
              >
                Confirm {logType === 'stock-in' ? 'Stock In' : 'Stock Out'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Stock History Modal */}
      {isHistoryOpen && historyProduct && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in">
          <div className="bg-card-theme-bg rounded-3xl p-6 max-w-xl w-full shadow-2xl space-y-4 border border-slate-100 max-h-[85vh] flex flex-col">
            <div className="flex justify-between items-center border-b pb-3 border-slate-100 shrink-0">
              <div>
                <h3 className="font-bold text-lg text-slate-900">{historyProduct.name}</h3>
                <p className="text-xs text-slate-500 font-mono">[{historyProduct.code}] Movement Log History</p>
              </div>
              <button onClick={() => setIsHistoryOpen(false)} className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                <X size={20} />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto space-y-2 pr-1">
              {productHistory.map((item, idx) => (
                <div key={idx} className="p-3 bg-layout-theme-bg rounded-2xl border border-slate-100 flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className={`p-2 rounded-xl ${item.color.includes('emerald') ? 'bg-accent-theme-light' : item.color.includes('rose') ? 'bg-rose-100' : 'bg-primary-theme-light'}`}>
                      <item.icon size={16} className={item.color} />
                    </div>
                    <div>
                      <div className="text-xs font-bold text-slate-800">{item.type}</div>
                      <div className="text-[10px] text-slate-400">{item.note || 'No notes'}</div>
                    </div>
                  </div>
                  <div className="text-right">
                    <div className={`font-mono font-bold text-xs ${item.qty > 0 ? 'text-accent-theme' : 'text-rose-600'}`}>
                      {item.qty > 0 ? `+${item.qty}` : item.qty}
                    </div>
                    <div className="text-[10px] text-slate-400">{format(new Date(item.date), 'MMM dd, HH:mm')}</div>
                  </div>
                </div>
              ))}
              {productHistory.length === 0 && (
                <p className="text-center py-8 text-xs text-slate-400">No stock history recorded for this item yet.</p>
              )}
            </div>
          </div>
        </div>
      )}

      {/* CSV Bulk Import Modal */}
      {isImportModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in" id="csv-import-modal">
          <div className="bg-card-theme-bg rounded-3xl p-0 max-w-3xl w-full shadow-2xl relative overflow-hidden flex flex-col max-h-[90vh] border border-slate-100">
            <div className="p-6 bg-slate-900 text-white flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-accent-theme rounded-xl flex items-center justify-center text-white shadow-md">
                  <FileSpreadsheet size={22} />
                </div>
                <div>
                  <h3 className="text-xl font-bold">Bulk Product Import</h3>
                  <p className="text-xs text-slate-400">Upload CSV file to import multiple products at once</p>
                </div>
              </div>
              <button 
                onClick={resetImportState}
                className="text-slate-400 hover:text-white p-2 hover:bg-slate-800 rounded-xl transition-colors cursor-pointer"
                id="close-csv-import-modal-btn"
              >
                <X size={20} />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-6 space-y-6">
              {importSuccessMessage ? (
                <div className="p-6 bg-accent-theme-light border border-accent-theme-light rounded-2xl text-center space-y-4">
                  <div className="w-16 h-16 bg-accent-theme-light text-accent-theme rounded-full flex items-center justify-center mx-auto shadow-sm">
                    <CheckCircle size={36} />
                  </div>
                  <div>
                    <h4 className="text-lg font-bold text-accent-theme-dark">Import Completed Successfully</h4>
                    <p className="text-sm text-accent-theme-hover mt-1 font-medium">{importSuccessMessage}</p>
                  </div>
                  <button
                    onClick={resetImportState}
                    className="px-6 py-2.5 bg-accent-theme text-white font-bold text-sm rounded-xl hover:bg-accent-theme-hover transition-all shadow-md cursor-pointer"
                    id="finish-csv-import-btn"
                  >
                    Done & Close
                  </button>
                </div>
              ) : (
                <>
                  <div className="p-4 bg-primary-theme-light/70 border border-primary-theme-light rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="flex items-start gap-3">
                      <FileText className="text-primary-theme shrink-0 mt-0.5" size={20} />
                      <div>
                        <div className="text-xs font-bold text-primary-theme-dark">Need a starting template?</div>
                        <p className="text-xs text-primary-theme-hover mt-0.5">Download our pre-formatted CSV template with standard column headers.</p>
                      </div>
                    </div>
                    <button
                      onClick={downloadSampleCSV}
                      className="px-3.5 py-2 bg-primary-theme hover:bg-primary-theme-hover text-white font-bold text-xs rounded-xl transition-all shadow-sm flex items-center gap-2 shrink-0 cursor-pointer"
                      id="download-csv-template-btn"
                    >
                      <Download size={14} />
                      Download Template CSV
                    </button>
                  </div>

                  {!parsedProducts ? (
                    <div className="border-2 border-dashed border-slate-200 hover:border-primary-theme rounded-2xl p-8 text-center bg-layout-theme-bg/50 hover:bg-primary-theme-light/30 transition-all group">
                      <input 
                        type="file" 
                        accept=".csv, text/csv"
                        onChange={handleFileUpload}
                        className="hidden" 
                        id="inventory-csv-file-input"
                      />
                      <label 
                        htmlFor="inventory-csv-file-input"
                        className="cursor-pointer flex flex-col items-center justify-center space-y-3"
                      >
                        <div className="w-14 h-14 bg-card-theme-bg rounded-2xl flex items-center justify-center text-primary-theme shadow-md group-hover:scale-110 transition-transform border border-slate-100">
                          <Upload size={28} />
                        </div>
                        <div>
                          <span className="text-sm font-bold text-slate-800 group-hover:text-primary-theme block">
                            Click to select CSV file or drag and drop
                          </span>
                          <span className="text-xs text-slate-400 mt-1 block">
                            Supported headers: Product Code, Size / Spec, Product Name, Brand, Category, Unit Price, Low Stock Threshold
                          </span>
                        </div>
                      </label>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      <div className="flex flex-wrap items-center justify-between gap-3 p-4 bg-layout-theme-bg rounded-2xl border border-slate-200">
                        <div className="flex items-center gap-3">
                          <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">File Loaded:</span>
                          <span className="text-xs font-bold text-slate-800 bg-card-theme-bg px-2.5 py-1 rounded-lg border border-slate-200">{importFileName}</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <span className="px-2.5 py-1 bg-accent-theme-light text-accent-theme-dark text-xs font-bold rounded-lg">
                            {parsedProducts.filter(p => p.isValid).length} Ready to Import
                          </span>
                          {parsedProducts.some(p => !p.isValid) && (
                            <span className="px-2.5 py-1 bg-rose-100 text-rose-800 text-xs font-bold rounded-lg">
                              {parsedProducts.filter(p => !p.isValid).length} Blocked (Duplicate / Invalid)
                            </span>
                          )}
                          <button
                            onClick={() => { setParsedProducts(null); setImportFileName(''); }}
                            className="text-xs font-bold text-slate-500 hover:text-slate-800 underline ml-2 cursor-pointer"
                          >
                            Change File
                          </button>
                        </div>
                      </div>

                      <div className="border border-slate-200 rounded-2xl overflow-hidden max-h-60 overflow-y-auto">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-slate-100 text-[10px] font-bold text-slate-500 uppercase tracking-wider sticky top-0">
                            <tr>
                              <th className="px-3 py-2">Code</th>
                              <th className="px-3 py-2">Size / Spec</th>
                              <th className="px-3 py-2">Product Name</th>
                              <th className="px-3 py-2">Brand / Cat</th>
                              <th className="px-3 py-2 text-right">Unit Price</th>
                              <th className="px-3 py-2 text-center">Low Stock Threshold</th>
                              <th className="px-3 py-2 text-center">Status</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-slate-100">
                            {parsedProducts.map((item, idx) => (
                              <tr key={idx} className={!item.isValid ? 'bg-rose-50/60' : 'hover:bg-layout-theme-bg'}>
                                <td className="px-3 py-2 font-mono font-bold text-slate-700">{item.code}</td>
                                <td className="px-3 py-2 font-bold text-slate-700">{item.size}</td>
                                <td className="px-3 py-2 font-bold text-slate-900">{item.name || <span className="text-rose-500 italic">Missing</span>}</td>
                                <td className="px-3 py-2 text-slate-500">{item.brand} ({item.category})</td>
                                <td className="px-3 py-2 text-right font-mono font-bold">{settings.currency}{item.unitPrice.toLocaleString()}</td>
                                <td className="px-3 py-2 text-center font-mono font-bold">{item.minStockLevel}</td>
                                <td className="px-3 py-2 text-center">
                                  {!item.isValid ? (
                                    <div className="flex flex-col items-center gap-1">
                                      <span className="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 bg-rose-100 px-2 py-0.5 rounded-full">
                                        <AlertCircle size={10} /> {item.isExisting ? 'CODE EXISTS' : 'INVALID'}
                                      </span>
                                      <span className="text-[9px] text-rose-500 font-bold max-w-[120px] text-center leading-tight">
                                        {item.validationError}
                                      </span>
                                    </div>
                                  ) : (
                                    <span className="text-[10px] font-bold text-accent-theme-hover bg-accent-theme-light px-2 py-0.5 rounded-full">
                                      Valid New
                                    </span>
                                  )}
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}
                </>
              )}
            </div>

            {!importSuccessMessage && parsedProducts && (
              <div className="p-6 bg-layout-theme-bg border-t border-slate-100 flex items-center justify-between">
                <button
                  onClick={resetImportState}
                  className="px-4 py-2.5 border border-slate-200 text-slate-600 font-bold text-sm rounded-xl hover:bg-slate-100 transition-colors cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  onClick={handleConfirmImport}
                  disabled={!parsedProducts.some(p => p.isValid)}
                  className="px-6 py-2.5 bg-accent-theme hover:bg-accent-theme-hover disabled:opacity-50 text-white font-bold text-sm rounded-xl transition-all shadow-lg shadow-accent-theme-light flex items-center gap-2 cursor-pointer"
                  id="confirm-csv-import-submit-btn"
                >
                  <Upload size={16} />
                  Import {parsedProducts.filter(p => p.isValid).length} Products
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
