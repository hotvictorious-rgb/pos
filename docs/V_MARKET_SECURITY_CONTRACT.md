# VMarket Permanent Security Contract

The VMarket Security Contract establishes non-negotiable architectural and domain-level invariants for the VMarket POS system and serves as the reference implementation for future VMarket microservices (Marketplace, Delivery, CRM, Financial Ledgers).

---

## The Contract Invariants

### VM-001: Tenant Isolation
- Every business model belongs strictly to a tenant (`tenant_id`).
- All queries must be filtered by `TenantScope` unless explicitly executing under verified multi-tenant super-admin oversight.
- `BelongsToTenant` trait fails closed: persisting a tenant model in SaaS mode without an active `tenant_id` throws a runtime exception.
- Cross-tenant references (e.g., transfers, sales, payments, warehouse lookups) are rejected immediately at both the controller and service layers.

### VM-002: Branch Ownership
- Every sale, physical stock movement, and stock adjustment is permanently and authoritatively bound to the branch (`warehouse_id`) where it originated.
- Sales history and receipts never derive originating branch from volatile session state or user reassignment; the branch is immutable once persisted.
- Unassigned non-executive employees fail closed with `403 Forbidden` and cannot execute branch operations. Silent fallback to a default warehouse is strictly prohibited.

### VM-003: Server-Authoritative Pricing
- The server is the sole source of truth for product unit prices.
- Client requests cannot alter or supply unit prices for retail checkout. The server always resolves unit prices from the authoritative product catalog.
- Wholesale pricing is restricted to privileged back-office administrative endpoints and cannot be selected via POS checkout.

### VM-004: Server-Authoritative Totals
- Line totals (`quantity * unitPrice`), gross invoice totals, and payment balances are calculated and asserted server-side.
- Payment tenders (`cash + pos + transfer`) must equal or exceed recorded paid amount.
- Cash change returned to the customer is verified before creating ledger entries (`netCash = max(0, tender - change)`).

### VM-005: Explicit State Machines
- Entity transitions (e.g., transfers: `DISPATCHED` $\rightarrow$ `RECEIVED` / `DISCREPANCY` / `RECALLED`; deliveries: `UNSUPPLIED` $\rightarrow$ `DELIVERED`) must follow deterministic, forward-only state machines.
- Operations on invalid states (e.g., receiving an already received transfer, dispatching an already delivered sale) are rejected.

### VM-006: Durable Idempotency
- All 9 critical financial and stock mutations must support durable, database-backed idempotency protection via `IdempotencyService`:
  1. `pos_checkout`
  2. `debt_payment`
  3. `returns_process`
  4. `stock_in`
  5. `transfer_out`
  6. `transfer_in`
  7. `transfer_recall`
  8. `stock_adjustment`
  9. `stock_dispatch`
- Identical replays return the cached or stored completed result without duplicating stock deductions or financial movements.
- Payload tampering under an identical idempotency key is detected and rejected with `409 Conflict` / `422 InvalidArgument`.

### VM-007: Concurrency Protection
- All stock balance changes, sale reservations, debt updates, and state transitions must execute within database transactions using pessimistic row locking (`lockForUpdate`).
- Unallocated stock is checked prior to reserving goods for unsupplied sales (`physical_stock - allocated_stock >= quantity`).
- Unique constraints (`product_id, warehouse_id`) prevent duplicate stock rows under concurrent race conditions.

### VM-008: Immutable Financial History
- Past sales, completed payments, customer ledger entries, and audit logs are append-only.
- Returns and refunds do not erase past records:
  - `CASH_REFUND` creates an explicit negative `Payment` entry (`method = REFUND_CASH`) and adjusts `Sale.paidAmount` to balance the drawer.
  - `DEBT_REDUCTION` credits the customer's debt ledger with a `RETURN_CREDIT` entry.
- Refund amounts cannot exceed historical cash received or item quantities originally purchased.

### VM-009: Least-Privilege Authorization
- Role permissions are enforced as real server-side security boundaries via `CapabilityService` and `RequireCapability` middleware.
- Capabilities (`pos.*`, `stock.*`, `debt.*`, `returns.*`, `reports.*`, `users.*`, `settings.*`, `platform.*`) are validated at both the HTTP route/middleware boundary and inside domain services.
- Unknown roles fail closed (empty capability set $\rightarrow$ `403 Forbidden`).

### VM-010: Fail-Closed Behavior
- In the absence of an authenticated user, valid tenant context, assigned warehouse, or verified capability, the application must immediately abort or reject with 401/403.
- Permissive defaults are strictly disallowed.

### VM-011: Parent-Child Ownership Consistency
- Child models (`SaleItem`, `Payment`, `SalesReturn`, `TransferItem`) inherit their `tenant_id` from their parent record.
- Orphaned child records referencing foreign or nonexistent parents are dropped during backup restore.

### VM-012: Auditability
- Every state mutation creates an immutable `Activity` record and corresponding `InventoryLog` / `CustomerLedger` entry with acting `userId`, `userName`, timestamp, and payload context.

### VM-013: Secure Lifecycle Deletion
- Tenant deletion executes an atomic cascading purge across all 19 tenant-scoped tables, utilizing `forceDelete` on soft-deleting models (`Warehouse`, `Customer`, `Supplier`) to prevent residual orphaned records.
- Super admin platform root identity cannot be deleted or demoted.

### VM-014: No Alternate Mutation Paths
- No business fact can be created, updated, or bypassed through alternate endpoints.
- `/api/data` bulk pushing and client-side split-brain storage syncing are disabled.
- Sensitive endpoints require CSRF protection and explicit capability authorization.
- Backup restore normalizes user privileges, preventing arbitrary escalation to platform super administrator.

### VM-015: Four-Level Authority Boundaries & Zero Universal Bypasses
- Authority is partitioned into exactly four categories:
  1. `PLATFORM ADMIN` (SaaS administration; ZERO tenant business data access).
  2. `PLATFORM EMPLOYEE` (assigned platform operational work; ZERO tenant business records).
  3. `TENANT ADMIN` (controls single tenant's business; ZERO platform administration, ZERO other tenants).
  4. `TENANT EMPLOYEE` (performs assigned operations in single tenant/branch; ZERO platform administration).
- Universal super-admin shortcuts and tenant impersonation endpoints are permanently eliminated.
- Platform owner internal business operations run as a standard registered tenant with no special bypass privileges.

### VM-016: Two-Dimensional Decoupled Inventory Model
- Physical stock on shelf (`physical_stock >= 0`) and customer reservations (`allocated_stock >= 0`) are decoupled.
- Reservation shortfall (`max(0, allocated_stock - physical_stock)`) is a legitimate, supported business state.
- Immediate walk-in POS sales require only `physical_stock >= quantity` and are never blocked by unsupplied reservations.
- Unsupplied reservation fulfillment or pickup requires both `physical_stock >= quantity` and `allocated_stock >= quantity` under pessimistic locks.

### VM-017: Closed Financial Event Model & Invariant Gross Invoices
- Historical gross invoices (`Sale.totalAmount`) are strictly immutable and never decremented by returns.
- Derived remaining invoice balance is governed by:
  `Net Invoice = Gross Total - Sum(Returns)`
  `Net Money Applied = Sum(Payments) - Sum(Cash Refunds)`
  `Invoice Balance = max(0, Net Invoice - Net Money Applied)`
- Returns are categorized strictly as `CASH_REFUND` (drawer cash outflow) or `DEBT_REDUCTION` (customer ledger credit).
- POS checkout pricing and debt requirements are strictly server-authoritative; client `totalAmount` inputs are discarded.
- Payment tenders are strictly `CASH` and `POS`. Electronic overpayment is rejected.

### VM-018: The Permanent Engineering Quality Criterion
> **No feature is considered complete merely because its happy path works. It is complete only when its authority, tenant isolation, branch isolation, ownership, concurrency, accounting/inventory effects, idempotency, auditability, and failure paths have been verified.**

Every feature must pass through the **Twelve-Point Architectural Evaluation Pipeline**:
1. `Authentication` → Who are you?
2. `Authority Category` → Platform or Tenant?
3. `Tenant Context` → Which tenant?
4. `Branch Context` → Which branch?
5. `Capability` → What are you allowed to do?
6. `Ownership` → Does this record belong to your tenant/branch?
7. `Server Validation` → Are the requested values legitimate?
8. `Transaction + Locks` → Can the operation safely mutate state?
9. `State Machine` → Is this transition legal?
10. `Accounting / Inventory` → What authoritative business effects occurred?
11. `Immutable Audit` → What happened and who did it?
12. `Idempotency` → Can this request safely be retried?

### VM-019: Platform vs Tenant Backup Segregation & Pure Tenders
- **Zero Tenant Data in Platform Backups**: Platform backups strictly archive platform infrastructure (`tenants`, platform settings, platform activities). Platform Admin has ZERO access to tenant business data (products, sales, customers, stock, ledgers) through backup files or download endpoints.
- **Tenant Backups Strict Ownership**: Tenant backups are strictly isolated to the authenticated tenant (`tenant_id`). Only the designated Tenant Admin can initiate, download, or restore tenant backups.
- **Cross-Tenant Restore Immunity**: Cross-tenant backup restoration is physically prevented. Neither Platform Admin nor foreign Tenant Admins can inject or restore backup snapshots into a foreign tenant.
- **Strict CSRF Boundary**: All state-mutating endpoints (`/api/*`, `/settings/backups/*`, `/stock/*`, `/sales/*`) enforce CSRF token verification. Only stateless credential exchange (`api/login`) is exempt from CSRF validation.
- **Pure Tender Authority**: Checkout strictly accepts separate `cashAmount` and `posAmount` tenders. Client-supplied `paidAmount` inputs are rejected, and total paid amount is strictly derived from verified tenders on the server.

### VM-020: Relational Integrity Across Restore & Accounting Math
- **Comprehensive Entity Backup**: Tenant backups encompass all business models (`StockReservation`, `CustomerLedger`, `StockAdjustment`, `SalesReturn`, `InventoryLog`, `Customer`, `Sale`, `SaleItem`, `Payment`, `Product`, `StockLevel`, `Transfer`). Platform-global definitions (`CustomRole`) are excluded from tenant backups.
- **Relational Key Remapping**: Tenant restore transactionally restores auto-increment models (`Customer`) first, maps old primary keys to newly generated keys, and remaps all foreign references (`Sale.customerId`, `CustomerLedger.customer_id`, `StockReservation.customer_id`), preventing relational dangling or cross-tenant pointer corruption.
- **Backup Tamper Resistance**: All backup archives feature SHA-256 HMAC cryptographic signatures and payload manifests that are verified before initiating transactional restoration.
- **Zero Double-Counting in Cash Drawer Math**: The cashier drawer expected cash equation cleanly distinguishes checkout sale cash from debt repayments, ensuring physical cash in drawer matches net cash sales plus unlinked debt recoveries minus cash refunds with zero duplicate additions.

### VM-021: Relational Warehouse Restore & Branch-Isolated Debt
- **Warehouse ID Remapping on Restore**: When restoring a tenant backup, warehouses are restored first to construct a complete `$oldWhId => $newWhId` mapping. All dependent models (`User`, `Sale`, `Transfer` [source and destination], `StockLevel`, `StockAdjustment`, `StockReservation`, `InventoryLog`) are remapped to new warehouse IDs.
- **Pure Financial Event Authority**: Invoice balance computation is strictly derived from `Payment` financial events (`amount > 0` and `method != 'REFUND_CASH'`) and `SalesReturn` events. Fallbacks to materialized `Sale.paidAmount` are strictly forbidden.
- **Zero Branch Debt Leakage**: Debt calculation and debtor dashboards for branch-scoped users are derived strictly from open sales originating at the assigned warehouse. Branch workers cannot view customer debts incurred at other branches.
- **Single Canonical Inventory Mutation Path**: All physical stock additions, including catalog initial stock creation and CSV imports, must route through the canonical `StockService::recordStockIn()` engine to guarantee transactional row locking and audit logging.

### VM-022: Unsupplied Return Partitioning, Systematic Branch Privacy & Cryptographic Backup Validation
- **Unsupplied Return State Machine & Stock Preservation**:
  - Unsupplied sales with partial collections preserve distinct accounting between goods physically returned to the shelf and reserved units cancelled prior to pickup.
  - Return operations partition quantities against `StockReservation`:
    - Units currently in customer possession (`held_by_customer_qty = fulfilled_qty - returned_fulfilled_qty`) strictly restore `StockLevel.physical_stock`.
    - Uncollected reservation units strictly decrement `StockLevel.allocated_stock` and increment `cancelled_qty`.
    - Double returns of fulfilled units are mathematically rejected.
  - Delivery status transitions automatically to `DELIVERED` or `RETURNED` once all line-item reservations reach complete resolution.
- **Universal Branch-Scoped Read Privacy**:
  - All read-layer endpoints (`/pos/receipt/{id}`, `/pos/returns`, `/transactions`, and transaction query filters) enforce `$user->isBranchScoped()`.
  - Cashiers, storekeepers, sales officers, and branch managers are strictly forbidden from viewing receipts, recent sales, returns, stock-in/out records, refund logs, or customer debt balances originating from branches other than their assigned branch.
- **Strict Cryptographic Backup Verification**:
  - All backup uploads and restores enforce non-empty SHA-256 HMAC checksums generated against `config('app.key')`. Static or hardcoded key fallbacks are strictly prohibited.
  - Uploaded backup files must pass cryptographic HMAC validation and manifest record count parity in-memory *before* any file is persisted to disk storage (`storage/app/backups/`).
- **Authoritative Debt Derivation**:
  - Accounting summaries (`AccountingReportService::getPeriodSummary()`) derive newly created debt (`$newDebtCreated`) strictly using `calculateInvoiceBalance($sale)` across period invoices, ensuring sales returns and ledger payments accurately reduce outstanding receivables.
- **Customer Tenant Isolation at Point of Sale**:
  - Checkout operations verify that `$customer->tenant_id === $activeTenantId`, preventing cross-tenant customer references or liability leakage across tenants.

### VM-023: Universal Scope-vs-Capability Hierarchy & Event-Authoritative Filters
- **Scope vs. Capability Principle**:
  - *"Capability determines WHAT a user may do; authority category and branch context determine WHERE they may do it."*
  - Permission overrides (e.g. `users.manage = true`, `settings.manage = true`) grant operational permissions but can NEVER bypass or widen the user's branch or authority category boundary.
  - Branch-scoped employees (`$user->isBranchScoped()`):
    - May strictly view, create, edit, lock, unlock, or reset passwords for staff assigned to their own warehouse (`$user->warehouse_id`).
    - Are strictly prohibited from creating administrator accounts, promoting staff to admin, or reassigning staff to other branches or HQ.
    - Are strictly prohibited from modifying tenant-wide business settings (currency, business name, receipt headers/footers) or managing branch locations (create, edit, toggle warehouses).
- **Platform Infrastructure Isolation in Legacy APIs**:
  - The tenant data synchronization endpoint (`/api/data`) strictly excludes platform-global `CustomRole` infrastructure metadata.
  - Tenant data reset operations cannot delete or mutate `custom_roles`.
- **Event-Authoritative Payment Status Filtering**:
  - Universal history tabs (`/transactions`) derive sales payment status (`PAID`, `PARTIAL`, `NOT_PAID`, `DEBT`) from authoritative `Payment` financial events (net inflows minus cash refunds) and `SalesReturn` credits rather than cached table columns.

