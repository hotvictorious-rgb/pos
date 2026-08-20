# Hysam Ventures – Business Logic, Audit & Anti-Theft Specification

> **Document Version:** 1.0.0  
> **Target Audience:** Business Owner, Lead Auditor, System Developers, Branch Managers  
> **Status:** APPROVED BUSINESS SPECIFICATION  

---

## 1. Executive Summary & Core Mission

Hysam Ventures is transitioning from error-prone, manual paper notebooks to a computerized, multi-location inventory and sales tracking system. As mandated by the Auditor, the primary objectives of this system are:
1. **Prevent Employee Theft & Pilferage:** Create airtight, tamper-proof audit trails for every physical item and financial transaction.
2. **Real-time Physical Stock Accuracy:** Ensure system stock levels match physical counts across all store locations and warehouses.
3. **Fulfillment Distinction (Supplied vs. Unsupplied):** Clearly decouple the financial sale from physical dispatch so closing stock accurately reflects items physically on ground.
4. **Reliable Multi-Location & Offline Operations:** Enable branch staff to record sales and stock operations seamlessly during network downtime, syncing reliably across locations.
5. **Accurate Part-Payment & Debt Ledger:** Efficiently track partial payments, credit sales, and customer payment histories.

---

## 2. Fundamental Inventory & Stock Rules

### 2.1 The Golden Law of Physical Closing Stock
> **Rule 2.1.1:** Closing Stock at any location MUST strictly represent goods that **have not physically left** that specific premises.

- When a customer purchases items but leaves them for later delivery/collection:
  - **Financial Status:** `PAID` or `PARTIALLY_PAID`
  - **Fulfillment Status:** `UNSUPPLIED` (or `PARTIALLY_SUPPLIED`)
  - **Physical Stock Impact:** The items **remain in physical closing stock** until an authorized **Dispatch / Stock-Out Note** is generated upon physical handover.
  - **Reserved Stock:** The system tracks both:
    - `Physical Stock on Hand` (actual items inside the shop/warehouse)
    - `Allocated / Unsupplied Stock` (sold items awaiting pickup/delivery)
    - `Available for Sale Stock` = `Physical Stock on Hand` − `Allocated Stock`

### 2.2 Strict Stock Inflow & Outflow Classifications
Every stock count change must be classified under a distinct, non-editable transaction type with mandatory user attribution:

| Transaction Type | Trigger | Required Fields | Physical Stock Effect |
|------------------|---------|-----------------|-----------------------|
| **Stock In (Purchase/Supplier)** | Receiving goods from supplier | Supplier, Invoice/Waybill Ref, Cost Price, Quantity Received, Receiving Staff | `+ Increase` |
| **Stock In (Customer Return)** | Return of sold goods | Original Sale Ref, Reason, Item Condition, Approving Auditor | `+ Increase` |
| **Stock In (Transfer Received)** | Acknowledging arrival of transfer | Transfer Number, Source Location, Quantity Counted | `+ Increase` (Dest.) |
| **Stock Out (Sale Dispatched)** | Physical delivery/handover to customer | Sale Invoice Ref, Dispatch Officer, Driver/Carrier Name | `- Decrease` |
| **Stock Out (Transfer Dispatched)** | Goods leaving location for another | Destination Location, Transfer Ref, Dispatch Staff | `- Decrease` (Src.) |
| **Stock Out (Damage / Loss)** | Broken, expired, or defective items | Reason, Evidence Photo, Auditor Approval | `- Decrease` |
| **Stock Out (Internal Use)** | Business operations consumption | Department, Purpose, Approving Manager | `- Decrease` |

---

## 3. Anti-Theft & Multi-Location Transfer Protocols

### 3.1 Two-Step Transfer Handshake (Anti-Loss in Transit)
To prevent stock from disappearing "between locations":
1. **Step 1 – Dispatch (`DISPATCHED`):**
   - Source branch creates a Transfer Order.
   - Physical stock is deducted from source `Physical Stock` and moved to `In-Transit Stock`.
   - A printed Transfer Waybill is generated with unique Transfer Barcode/Code.
2. **Step 2 – Acknowledgment & Count (`RECEIVED`):**
   - Destination branch receives the physical shipment and enters the actual counted quantity.
   - If destination count matches waybill: Transfer marked `COMPLETED` and stock added to destination.
   - If destination count is **less** than dispatched quantity: Transfer marked `DISCREPANCY DETECTED`. An immediate audit alert is raised showing:
     - Dispatched: $X$ units
     - Received: $Y$ units
     - Missing: $(X - Y)$ units attributed to the carrier/dispatching staff.

```
[ Source Location ] ──(Dispatches X)──► [ In-Transit Buffer ] ──(Receives Y)──► [ Destination Location ]
                                                │
                                       (If X ≠ Y: AUDIT ALERT)
```

---

## 4. Sales, Invoicing & Part-Payment Business Rules

### 4.1 Payment Breakdown & Debt Tracking
Sales transactions support split payments and installment tracking:
- **Payment Methods:** Cash, Bank Transfer, POS Terminal, Customer Credit/Balance.
- **Payment Statuses:**
  - `PAID_IN_FULL`: Total Amount Received = Invoice Amount.
  - `PARTIALLY_PAID`: Amount Received > 0 but < Invoice Amount. Balance added to Customer Debt Ledger.
  - `UNPAID / CREDIT`: Zero amount received at sale time. Full amount added to Debt Ledger.
- **Debt Repayments:**
  - Subsequent payments are recorded against the specific Customer Ledger & Invoice ID.
  - Generates a dedicated **Payment Receipt** showing Previous Balance, Amount Paid, and Remaining Balance.

### 4.2 Fulfillment Status Matrix
Every sale line item maintains independent payment and fulfillment states:

```
Sale Created
   ├── Payment State:   [ Unpaid ] ──► [ Partially Paid ] ──► [ Fully Paid ]
   └── Fulfillment State: [ Unsupplied ] ──► [ Partially Supplied ] ──► [ Fully Supplied ]
```

---

## 5. Auditor Controls & Worker Accountability

### 5.1 Immutable Audit Log
- Every create, update, delete, stock adjustment, price change, and login event is permanently written to `activity_logs`.
- **Zero Backdating:** System timestamps are enforced server-side. Workers cannot backdate sales or stock entries.
- **No Silent Deletions:** Invoices and stock entries cannot be hard-deleted. Corrections require formal credit notes or void approvals with audit justifications.

### 5.2 Role-Based Access Control (RBAC)

| Capability | Cashier / Sales Staff | Storekeeper / Inventory Staff | Branch Manager | Auditor / Super Admin |
|------------|-----------------------|--------------------------------|----------------|-----------------------|
| Record Daily Sales | ✅ Yes | ❌ No | ✅ Yes | ✅ Yes |
| Record Part Payments | ✅ Yes | ❌ No | ✅ Yes | ✅ Yes |
| Receive Stock from Supplier | ❌ No | ✅ Yes | ✅ Yes | ✅ Yes |
| Dispatch Transfers | ❌ No | ✅ Yes | ✅ Yes | ✅ Yes |
| Receive Transfers | ❌ No | ✅ Yes | ✅ Yes | ✅ Yes |
| Modify Unit Prices | ❌ No | ❌ No | ⚠️ With PIN | ✅ Yes |
| Approve Stock Adjustments | ❌ No | ❌ No | ❌ No | ✅ Yes (Auditor Only) |
| View Financial & Profit Reports | ❌ No | ❌ No | ⚠️ Branch Only | ✅ All Branches |
| Perform Database Backup/Restore | ❌ No | ❌ No | ❌ No | ✅ Yes |

### 5.3 Daily Cash & Stock Reconciliation (End-of-Day Shift Close)
At the end of each shift/day, workers must perform an End-of-Day (EOD) Reconciliation:
1. **Cash Reconciliation:**
   - Expected Cash = Opening Float + Cash Sales + Debt Recoveries − Approved Expenses.
   - Worker enters physical cash in drawer. System calculates Overage / Shortage.
2. **Physical Stock Spot-Check:**
   - Auditor or Manager runs periodic spot-checks on fast-moving SKUs.
   - Any variance between physical shelf count and system count flags an immediate shortage alert.

---

## 6. Multi-Location Sync & Offline Architecture

1. **Local Branch Resilience:**
   - When internet is unavailable, workers can continue entering sales, receipts, and deliveries locally.
   - Operations are assigned local UUIDs and queued.
2. **Automatic Synchronization:**
   - Upon network restoration, offline transactions are pushed to the central server.
   - Conflict resolution prioritizes chronological server timestamps and locks stock allocations to avoid negative inventory.
3. **Central Auditor Dashboard:**
   - The Auditor and Owner have real-time visibility across all physical locations from a single central portal.

---

## 7. Next Steps for Technical Implementation

1. **Database Schema Enhancements:**
   - Ensure tables for `sales`, `sale_items`, `payments`, `customer_ledgers`, `transfers`, `transfer_items`, `warehouses/locations`, `stock_levels`, and `inventory_logs` strictly support these states.
2. **Dedicated Auditor Reports:**
   - Physical vs. System Stock Variance Report.
   - Undelivered / Unsupplied Goods Liability Report.
   - Customer Debt Aging & Repayment History.
   - Transfer Discrepancy Log.
   - Cashier Shortage / Overage Ledger.

---

*Authored for Hysam Ventures. Documented as official engineering and business governance.*
