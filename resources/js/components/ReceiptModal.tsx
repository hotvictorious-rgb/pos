import { useState } from 'react';
import { Printer, X, CheckCircle2, Building2, Phone, Mail, MapPin, Receipt, FileText } from 'lucide-react';
import { Sale, AppSettings } from '../types';
import { storage } from '../lib/storage';

interface ReceiptModalProps {
  sale: Sale | null;
  onClose: () => void;
  settings?: AppSettings;
  initialFormat?: 'thermal' | 'a4' | 'invoice';
}

export default function ReceiptModal({ sale, onClose, settings: customSettings, initialFormat = 'thermal' }: ReceiptModalProps) {
  if (!sale) return null;

  const settings = customSettings || storage.getSettings();
  const products = storage.getProducts();
  const [format, setFormat] = useState<'thermal' | 'a4' | 'invoice'>(initialFormat);

  const openPrintWindow = () => {
    const printContent = document.getElementById('printable-receipt');
    if (!printContent) return;

    const printWin = window.open('', '_blank', 'width=850,height=900,scrollbars=yes');
    if (!printWin) {
      window.print();
      return;
    }

    const titleStr = `${format === 'invoice' ? 'Invoice' : 'Receipt'} - #${sale.id.toUpperCase()}`;
    const isThermal = format === 'thermal';

    printWin.document.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>${titleStr}</title>
          <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap');
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
              font-family: ${isThermal ? "'JetBrains Mono', monospace" : "'Inter', system-ui, sans-serif"};
              font-size: ${isThermal ? "11px" : "13px"};
              color: #000;
              background: #fff;
              padding: ${format === 'invoice' ? '24px' : '12px'};
              width: ${isThermal ? "80mm" : "100%"};
              margin: 0 auto;
              -webkit-print-color-adjust: exact;
              print-color-adjust: exact;
            }
            .no-print { display: none !important; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
            th, td { padding: 6px 4px; text-align: left; vertical-align: top; border-bottom: 1px solid #e2e8f0; }
            th { border-bottom: 2px solid #000; font-size: 10px; text-transform: uppercase; font-weight: bold; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .font-bold { font-weight: 700; }
            .font-black { font-weight: 900; }
            .font-mono { font-family: 'JetBrains Mono', monospace; }
            .uppercase { text-transform: uppercase; }
            .border-b { border-bottom: 1px solid #e2e8f0; }
            .border-b-2 { border-bottom: 2px solid #000; }
            .border-t { border-top: 1px solid #e2e8f0; }
            .border-t-2 { border-top: 2px solid #000; }
            .border-dashed { border-style: dashed; }
            .py-1 { padding-top: 4px; padding-bottom: 4px; }
            .py-2 { padding-top: 8px; padding-bottom: 8px; }
            .pb-3 { padding-bottom: 12px; }
            .mb-3 { margin-bottom: 12px; }
            .pb-4 { padding-bottom: 16px; }
            .mb-4 { margin-bottom: 16px; }
            .pb-8 { padding-bottom: 32px; }
            .mb-8 { margin-bottom: 32px; }
            .flex { display: flex; }
            .flex-col { display: flex; flex-direction: column; }
            .justify-between { justify-content: space-between; }
            .items-center { align-items: center; }
            .items-start { align-items: flex-start; }
            .justify-center { justify-content: center; }
            .justify-end { justify-content: flex-end; }
            .gap-1 { gap: 4px; }
            .gap-2 { gap: 8px; }
            .gap-3 { gap: 12px; }
            .gap-8 { gap: 32px; }
            .w-full { width: 100%; }
            .grid { display: grid; }
            .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .text-xs { font-size: 11px; }
            .text-sm { font-size: 13px; }
            .text-base { font-size: 16px; }
            .text-lg { font-size: 18px; }
            .text-xl { font-size: 20px; }
            .text-2xl { font-size: 24px; }
            .text-4xl { font-size: 32px; }
            .text-slate-400 { color: #94a3b8; }
            .text-slate-500 { color: #64748b; }
            .text-slate-600 { color: #475569; }
            .text-slate-900 { color: #0f172a; }
            .text-rose-600 { color: #e11d48; }
            .bg-layout-theme-bg { background-color: #f8fafc; }
            .rounded-2xl { border-radius: 16px; }
            @page { margin: 5mm; size: ${isThermal ? "80mm auto" : "A4"}; }
          </style>
        </head>
        <body>
          ${printContent.outerHTML}
          <script>
            window.onload = function() {
              window.focus();
              setTimeout(function() {
                window.print();
              }, 250);
            };
          </script>
        </body>
      </html>
    `);
    printWin.document.close();
  };

  const handlePrint = () => {
    openPrintWindow();
  };

  // Compute total paid from recorded payments or base sale fields
  const recordedPayments = storage.getPayments()
    .filter(p => p.saleId === sale.id)
    .sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime());

  // Synthesize payment list if no explicit payments recorded for older sales
  const salePayments = [...recordedPayments];
  if (salePayments.length === 0) {
    const initialPaid = sale.paidAmount !== undefined 
      ? sale.paidAmount 
      : ((sale.cashAmount || 0) + (sale.posAmount || 0));
    if (initialPaid > 0) {
      if ((sale.cashAmount || 0) > 0 && (sale.posAmount || 0) > 0) {
        salePayments.push({
          id: `INIT-CASH-${sale.id}`,
          saleId: sale.id,
          amount: sale.cashAmount,
          method: 'Cash',
          timestamp: sale.createdAt,
          recordedBy: sale.userId
        });
        salePayments.push({
          id: `INIT-POS-${sale.id}`,
          saleId: sale.id,
          amount: sale.posAmount,
          method: 'POS',
          timestamp: sale.createdAt,
          recordedBy: sale.userId
        });
      } else {
        salePayments.push({
          id: `INIT-${sale.id}`,
          saleId: sale.id,
          amount: initialPaid,
          method: (sale.cashAmount || 0) > 0 ? 'Cash' : (sale.posAmount || 0) > 0 ? 'POS' : 'Initial Payment',
          timestamp: sale.createdAt,
          recordedBy: sale.userId
        });
      }
    }
  }

  const totalFromPayments = salePayments.reduce((acc, p) => acc + p.amount, 0);
  const basePaid = sale.paidAmount !== undefined 
    ? sale.paidAmount 
    : ((sale.cashAmount || 0) + (sale.posAmount || 0));
  const totalPaid = Math.max(basePaid, totalFromPayments);
  const balanceDue = Math.max(0, sale.totalAmount - totalPaid);
  const isFullyPaid = balanceDue <= 0.01;
  const effectiveStatus = sale.status === 'returned' 
    ? 'Returned' 
    : isFullyPaid 
      ? 'Completed (Paid in Full)' 
      : 'Installment';

  return (
    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
      <div className="bg-card-theme-bg rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden my-8 border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
        
        {/* Header bar - Screen view only */}
        <div className="no-print p-5 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-primary-theme/20 text-primary-theme rounded-xl">
              <Printer size={20} />
            </div>
            <div>
              <h3 className="font-bold text-base">Sales Receipt</h3>
              <p className="text-xs text-slate-400">Order #{sale.id.toUpperCase()}</p>
            </div>
          </div>
          <button 
            onClick={onClose}
            className="p-2 hover:bg-slate-800 rounded-xl transition-colors text-slate-400 hover:text-white"
          >
            <X size={20} />
          </button>
        </div>

        {/* Format Selector - Screen view only */}
        <div className="no-print p-4 bg-layout-theme-bg border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
          <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Document Type:</span>
          <div className="flex flex-wrap gap-2">
            <button
              onClick={() => setFormat('thermal')}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 ${
                format === 'thermal' 
                  ? 'bg-primary-theme text-white shadow-sm' 
                  : 'bg-card-theme-bg text-slate-600 border border-slate-200 hover:bg-slate-100'
              }`}
            >
              <Receipt size={14} />
              Thermal Receipt
            </button>
            <button
              onClick={() => setFormat('a4')}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 ${
                format === 'a4' 
                  ? 'bg-primary-theme text-white shadow-sm' 
                  : 'bg-card-theme-bg text-slate-600 border border-slate-200 hover:bg-slate-100'
              }`}
            >
              <FileText size={14} />
              A4 Receipt
            </button>
            <button
              onClick={() => setFormat('invoice')}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 ${
                format === 'invoice' 
                  ? 'bg-primary-theme text-white shadow-sm' 
                  : 'bg-card-theme-bg text-slate-600 border border-slate-200 hover:bg-slate-100'
              }`}
            >
              <FileText size={14} />
              Professional Invoice
            </button>
          </div>
        </div>

        {/* RECEIPT CONTENT AREA */}
        <div className="p-6 max-h-[70vh] overflow-y-auto bg-slate-100/50 flex justify-center">
          
          <div 
            id="printable-receipt" 
            className={`bg-card-theme-bg text-slate-900 border border-slate-200 rounded-2xl shadow-sm w-full transition-all ${
              format === 'thermal' ? 'max-w-[340px] p-6' : format === 'a4' ? 'a4-size p-8' : 'a4-size p-12'
            }`}
          >
            {format === 'invoice' ? (
              <div className="space-y-8">
                {/* Invoice Header */}
                <div className="flex justify-between items-start border-b-2 border-slate-900 pb-8">
                  <div>
                    <h1 className="text-4xl font-black uppercase tracking-tighter text-slate-900 leading-none">INVOICE</h1>
                    <p className="text-slate-500 font-mono mt-2 text-sm">#{sale.id.toUpperCase()}</p>
                  </div>
                  <div className="text-right">
                    <h2 className="text-xl font-bold uppercase">{settings.businessName || 'HYSAM VENTURES'}</h2>
                    <p className="text-sm text-slate-600 mt-1">{settings.businessAddress}</p>
                    <p className="text-sm text-slate-600">{settings.businessPhone}</p>
                    <p className="text-sm text-slate-600">{settings.businessEmail}</p>
                  </div>
                </div>

                {/* Billing Info */}
                <div className="grid grid-cols-2 gap-8">
                  <div>
                    <h3 className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Bill To:</h3>
                    <p className="text-lg font-bold text-slate-900">{sale.customerName}</p>
                    <p className="text-sm text-slate-500 mt-1 italic">Customer Record: #{sale.customerName.replace(/\s+/g, '-').toLowerCase()}</p>
                  </div>
                  <div className="text-right">
                    <div className="space-y-1">
                      <p className="text-sm"><span className="font-bold text-slate-400 uppercase mr-2 text-[10px]">Date:</span> {new Date(sale.createdAt).toLocaleDateString(undefined, { dateStyle: 'long' })}</p>
                      <p className="text-sm"><span className="font-bold text-slate-400 uppercase mr-2 text-[10px]">Status:</span> <span className={`font-bold uppercase ${isFullyPaid ? 'text-emerald-700' : 'text-amber-700'}`}>{effectiveStatus}</span></p>
                      <p className="text-sm"><span className="font-bold text-slate-400 uppercase mr-2 text-[10px]">Method:</span> {sale.cashAmount > 0 && sale.posAmount > 0 ? 'Split Payment' : sale.cashAmount > 0 ? 'Cash' : 'POS'}</p>
                    </div>
                  </div>
                </div>

                {/* Items Table */}
                <table className="w-full">
                  <thead>
                    <tr className="bg-layout-theme-bg">
                      <th className="px-4 py-3">Description</th>
                      <th className="px-4 py-3 text-center w-24">Qty</th>
                      <th className="px-4 py-3 text-right w-32">Unit Price</th>
                      <th className="px-4 py-3 text-right w-32">Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sale.items.map((item, i) => (
                      <tr key={i}>
                        <td className="px-4 py-4">
                          <div className="font-bold text-slate-900">{item.productName}</div>
                          {products.find(p => p.id === item.productId)?.code && (
                            <div className="text-[10px] text-slate-400 font-mono mt-1">CODE: {products.find(p => p.id === item.productId)?.code}</div>
                          )}
                        </td>
                        <td className="px-4 py-4 text-center font-mono font-bold">{item.quantity}</td>
                        <td className="px-4 py-4 text-right text-slate-600 font-mono">{settings.currency}{item.unitPrice.toLocaleString()}</td>
                        <td className="px-4 py-4 text-right font-bold text-slate-900 font-mono">{settings.currency}{item.totalPrice.toLocaleString()}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>

                {/* Summary & Installment Payment History Section */}
                <div className="grid grid-cols-2 gap-6 pt-4 border-t border-slate-200">
                  <div>
                    {salePayments.length > 0 && (
                      <div className="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs space-y-2">
                        <h4 className="font-bold text-slate-800 text-[11px] uppercase tracking-wider">Payment Ledger History</h4>
                        <div className="space-y-1.5 divide-y divide-slate-100">
                          {salePayments.map((p) => (
                            <div key={p.id} className="pt-1 flex justify-between items-center text-[11px]">
                              <div>
                                <span className="font-bold text-slate-700">{p.method}</span>
                                <span className="text-[10px] text-slate-400 ml-1.5 font-mono">
                                  {new Date(p.timestamp).toLocaleDateString([], { month: 'short', day: 'numeric', year: '2-digit' })} {new Date(p.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                </span>
                              </div>
                              <span className="font-mono font-bold text-emerald-700">+{settings.currency}{p.amount.toLocaleString()}</span>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>

                  <div className="space-y-3 justify-self-end w-64">
                    <div className="flex justify-between text-slate-600 text-sm">
                      <span>Subtotal</span>
                      <span className="font-mono">{settings.currency}{sale.totalAmount.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between text-xl font-black text-slate-900 border-t-2 border-slate-900 pt-3">
                      <span>TOTAL</span>
                      <span className="font-mono">{settings.currency}{sale.totalAmount.toLocaleString()}</span>
                    </div>
                    <div className="pt-3 space-y-1 text-xs">
                      <div className="flex justify-between font-bold text-emerald-700">
                        <span>Total Paid</span>
                        <span className="font-mono">{settings.currency}{totalPaid.toLocaleString()}</span>
                      </div>
                      {balanceDue > 0.01 ? (
                        <div className="flex justify-between font-bold text-rose-600 pt-1 border-t border-dashed border-slate-200">
                          <span>Balance Due</span>
                          <span className="font-mono">{settings.currency}{balanceDue.toLocaleString()}</span>
                        </div>
                      ) : (
                        <div className="flex justify-between font-bold text-emerald-700 pt-1 border-t border-dashed border-slate-200">
                          <span>Status</span>
                          <span className="font-mono uppercase text-[11px]">PAID IN FULL</span>
                        </div>
                      )}
                    </div>
                  </div>
                </div>

                {/* Footer Notes */}
                <div className="pt-12 mt-12 border-t border-slate-100">
                  <h4 className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Notes & Terms:</h4>
                  <p className="text-xs text-slate-500 leading-relaxed">
                    {sale.note || 'No additional notes.'}
                  </p>
                  <p className="text-[10px] text-slate-400 mt-6 pt-6 border-t border-slate-50 text-center uppercase tracking-widest">
                    {settings.reportFooter || 'Thank you for your business!'}
                  </p>
                </div>
              </div>
            ) : (
              <>
                {/* Business Brand Header */}
                <div className="text-center pb-4 mb-4 border-b border-dashed border-slate-300">
                  <h2 className="text-lg font-black tracking-tight text-slate-900 uppercase">
                    {settings.businessName || 'HYSAM VENTURES'}
                  </h2>
                  {settings.businessAddress && (
                    <p className="text-[11px] text-slate-600 flex items-center justify-center gap-1 mt-0.5">
                      <MapPin size={10} className="no-print text-slate-400 shrink-0" />
                      {settings.businessAddress}
                    </p>
                  )}
                  <div className="text-[11px] text-slate-600 flex items-center justify-center gap-3 mt-1 flex-wrap">
                    {settings.businessPhone && (
                      <span className="flex items-center gap-1">
                        <Phone size={10} className="no-print text-slate-400 shrink-0" />
                        {settings.businessPhone}
                      </span>
                    )}
                    {settings.businessEmail && (
                      <span className="flex items-center gap-1">
                        <Mail size={10} className="no-print text-slate-400 shrink-0" />
                        {settings.businessEmail}
                      </span>
                    )}
                  </div>
                </div>

                {/* Transaction Metadata */}
                <div className="text-xs space-y-1.5 pb-3 mb-3 border-b border-dashed border-slate-300 font-mono">
                  <div className="flex justify-between">
                    <span className="text-slate-500">Receipt No:</span>
                    <span className="font-bold text-slate-900">#{sale.id.toUpperCase()}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Date & Time:</span>
                    <span className="text-slate-800">
                      {new Date(sale.createdAt).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Customer:</span>
                    <span className="font-bold text-slate-900">{sale.customerName}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Sale Type:</span>
                    <span className={`uppercase font-bold ${isFullyPaid ? 'text-emerald-700' : 'text-amber-700'}`}>{effectiveStatus}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">Fulfillment:</span>
                    <span className={`font-bold ${sale.deliveryStatus === 'delivered' ? 'text-accent-theme' : 'text-amber-600'}`}>
                      {sale.deliveryStatus === 'delivered' ? 'Counter Handover / Delivered' : 'Pending Dispatch'}
                    </span>
                  </div>
                </div>

                {/* Purchased Items Table */}
                <div className="mb-4">
                  <table className="w-full text-xs font-mono border-collapse">
                    <thead>
                      <tr className="border-b border-slate-800 text-[10px] uppercase text-slate-700">
                        <th className="py-1 text-left">Item</th>
                        <th className="py-1 text-center">Qty</th>
                        <th className="py-1 text-right">Price</th>
                        <th className="py-1 text-right">Total</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {sale.items.map((item, index) => {
                        const prod = products.find(p => p.id === item.productId);
                        return (
                          <tr key={index} className="py-1">
                            <td className="py-1.5 pr-2 font-sans font-medium text-slate-900">
                              <div>{item.productName}</div>
                              {prod?.code && <div className="text-[9px] text-slate-400 font-mono">[{prod.code}]</div>}
                            </td>
                            <td className="py-1.5 text-center font-bold">{item.quantity}</td>
                            <td className="py-1.5 text-right text-slate-600">
                              {settings.currency}{(item.unitPrice || 0).toLocaleString()}
                            </td>
                            <td className="py-1.5 text-right font-bold text-slate-900">
                              {settings.currency}{(item.totalPrice || 0).toLocaleString()}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>

                {/* Payment Summary */}
                <div className="text-xs space-y-1.5 pt-3 border-t-2 border-slate-800 font-mono">
                  <div className="flex justify-between text-sm font-bold pt-1">
                    <span>Grand Total:</span>
                    <span>{settings.currency}{sale.totalAmount.toLocaleString()}</span>
                  </div>
                  
                  <div className="pt-2 space-y-1 border-t border-slate-200 text-[11px]">
                    {salePayments.length > 0 && (
                      <div className="py-1.5 my-1 border-t border-b border-dashed border-slate-300">
                        <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Payment History:</div>
                        {salePayments.map((p) => (
                          <div key={p.id} className="flex justify-between text-[10px] text-slate-700 py-0.5">
                            <span>
                              {new Date(p.timestamp).toLocaleDateString([], { month: 'numeric', day: 'numeric' })} {new Date(p.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} ({p.method}):
                            </span>
                            <span className="font-bold text-emerald-700">+{settings.currency}{p.amount.toLocaleString()}</span>
                          </div>
                        ))}
                      </div>
                    )}

                    <div className="flex justify-between font-bold text-slate-900 pt-1 border-t border-dashed border-slate-200 text-xs">
                      <span>Total Paid:</span>
                      <span className="text-emerald-700">{settings.currency}{totalPaid.toLocaleString()}</span>
                    </div>
                    {balanceDue > 0.01 ? (
                      <div className="flex justify-between font-bold text-rose-600">
                        <span>Balance Due:</span>
                        <span>{settings.currency}{balanceDue.toLocaleString()}</span>
                      </div>
                    ) : (
                      <div className="flex justify-between font-bold text-emerald-700">
                        <span>Status:</span>
                        <span>PAID IN FULL</span>
                      </div>
                    )}
                  </div>
                </div>

                {sale.note && (
                  <div className="mt-3 pt-2 border-t border-dashed border-slate-300 text-[10px] text-slate-500 italic">
                    <strong>Note:</strong> {sale.note}
                  </div>
                )}

                {/* Receipt Footer */}
                <div className="text-center pt-4 mt-4 border-t border-slate-200 text-[10px] text-slate-500 space-y-1">
                  <p className="font-semibold text-slate-700">{settings.reportFooter || 'Thank you for your business!'}</p>
                  <p className="text-[9px] text-slate-400">Computer Generated Receipt • All Rights Reserved</p>
                </div>
              </>
            )}
          </div>
        </div>

        {/* Action Controls - Screen view only */}
        <div className="no-print p-5 bg-card-theme-bg border-t border-slate-200 flex items-center justify-between gap-3">
          <button
            onClick={onClose}
            className="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-sm hover:bg-layout-theme-bg transition-colors cursor-pointer"
          >
            Close
          </button>
          
          <button
            onClick={openPrintWindow}
            className="px-6 py-2.5 bg-primary-theme hover:bg-primary-theme-hover text-white rounded-xl font-bold text-sm shadow-lg shadow-primary-theme/20 flex items-center gap-2 transition-all cursor-pointer"
          >
            <Printer size={18} />
            Print {format === 'invoice' ? 'Invoice' : 'Receipt'}
          </button>
        </div>

      </div>
    </div>
  );
}
