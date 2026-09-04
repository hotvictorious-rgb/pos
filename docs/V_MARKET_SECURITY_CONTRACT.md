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
- `/api/data` bulk pushing is disabled.
- Sensitive endpoints require CSRF protection and explicit capability authorization.
- Backup restore normalizes user privileges, preventing arbitrary escalation to platform super administrator.
