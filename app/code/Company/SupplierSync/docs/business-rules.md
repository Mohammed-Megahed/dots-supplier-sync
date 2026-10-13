# Business Rules and Assumptions

## Document Overview
This document specifies the business domain rules, system invariants, integration assumptions with Magento 2 (including MSI), and exception handling policies for the `Company_SupplierSync` module.

---

## 1. Supplier Availability vs. Magento MSI Source & Salable Quantities

### Architectural Distinction
- **Magento MSI (Multi-Source Inventory):** Tracks physical stock across Magento Sources and calculates Salable Quantity across aggregated Stocks for catalog storefront display and standard checkout validations.
- **Company Supplier Inventory:** Tracks supplier-specific physical stock feeds, unit acquisition prices, currencies, and back-to-back/drop-ship inventory allocations per website.

### Isolation and Interaction Invariant
- The module **strictly does not mutate core Magento MSI tables** (`inventory_source_item`, `inventory_reservation`, etc.).
- Supplier inventory acts as an authoritative external availability layer.
- Salable supplier stock is calculated as:
  $$\text{Salable Qty} = \max(0, \text{Physical Qty} - \text{Reserved Qty})$$
- Order checkout passes through Magento's storefront validation first; upon successful order placement (`sales_order_place_after`), the module commits supplier reservations against the designated supplier.
- If supplier inventory is exhausted at the moment of reservation, an exception is thrown, halting order completion and preventing overselling.

---

## 2. Prevention of Inventory Double-Counting

- Configurable and bundle parent items in Magento orders generate child items representing physical SKUs.
- To avoid double-counting reservations:
  - Parent composite products (`$item->getHasChildren() && !$item->isDummy()`) are ignored during reservation passes.
  - Only physical child line items with real quantities decrement/reserve supplier stock.
- The `company_supplier_reservation` table enforces a strict database-level unique constraint on `order_item_id`. Re-evaluating the same order or order item never duplicates reservation quantities.

---

## 3. Website-Specific Supplier Configuration & Pricing

- In multi-store / multi-website environments, different currencies, base prices, and suppliers may service distinct regional websites.
- Supplier stock entries in `company_supplier_stock` are scoped by `(supplier_id, sku, website_id)`:
  - A product SKU can have different cost pricing and stock levels in Website A (`USD`, base website) versus Website B (`EUR`, European store).
  - WebAPI requests validate the `website_code` against Magento's `WebsiteRepositoryInterface`.

---

## 4. Supplier Selection & Disabled Supplier Behavior

### Single Designated Supplier Rule (Core Implementation)
- For the initial release, one active supplier is designated per SKU and website scope.
- Lookups join `company_supplier_stock` with `company_supplier` where `company_supplier.is_enabled = 1`.

### Disabled Supplier Behavior
- If a supplier is disabled (`is_enabled = 0`):
  1. **Stock Updates API:** Incoming updates are immediately rejected with an HTTP validation error (`Supplier "CODE" is disabled and cannot accept stock updates`).
  2. **Asynchronous Consumer:** Re-verifies supplier status. If a supplier was disabled while a message was in-flight in RabbitMQ, the message is marked `FAILED` in the audit log and dismissed without mutating inventory.
  3. **Order Reservation:** Orders cannot reserve units from disabled suppliers.

### Multi-Supplier Extension Path
To extend to multiple suppliers per SKU:
- Introduce a priority/cost-based ranking rule (e.g. lowest unit price, highest stock, or preferred supplier ranking).
- A supplier allocator strategy pattern (`SupplierSelectionStrategyInterface`) can iterate eligible suppliers until the full line item quantity is satisfied.

---

## 5. Stock Snapshot Semantics & Out-of-Order Updates

- Stock updates provided by external supplier feeds represent absolute state snapshots rather than incremental deltas.
- **Row Versioning:** Each stock row maintains an integer `version` field incremented on every write.
- **Audit Log Verification:** Stale updates are identified by timestamp and audit status. If an older update arrives out-of-sequence, it cannot overwrite newer stock values if an optimistic concurrency check fails.

---

## 6. Reservation Lifecycle: Creation, Cancellation, and Releases

### State Machine
```
[Order Placed] --------> RESERVED
                            |
           +----------------+----------------+
           |                                 |
 [Full Order Cancel]              [Partial Item Cancel / Refund]
           |                                 |
           v                                 v
     RELEASED_FULL                   RELEASED_PARTIAL
                                             |
                                  (Remaining Released)
                                             |
                                             v
                                       RELEASED_FULL
```

### Lifecycle Rules:
1. **Creation:** Atomic increment of `reserved_quantity` on `company_supplier_stock`. Creation of `company_supplier_reservation` with status `RESERVED`.
2. **Full Cancellation:** Full release of unreleased quantity:
   $$\text{Release Qty} = \text{Quantity} - \text{Released Quantity}$$
   Status transitions to `RELEASED_FULL`.
3. **Partial Cancellation:** Releases only the requested quantity ($\le \text{Remaining Unreleased}$). Status transitions to `RELEASED_PARTIAL`.
4. **Idempotent Release Guard:** Once status is `RELEASED_FULL`, subsequent cancellation or refund events for the same item are no-ops.

---

## 7. Refund & Creditmemo Behavior

- When a Magento Creditmemo is created (`sales_order_creditmemo_save_after`), refunded line quantities are released back to the supplier stock.
- Supplier confirmation is asynchronous: the local reservation record is updated immediately to restore availability, and an audit release event is logged.

---

## 8. Insufficient Stock & Reservation Failure Handling

- When an order item cannot be satisfied due to supplier stock exhaustion, the atomic SQL query fails (`rowCount == 0`).
- The transaction rolls back completely:
  - No partial reservations are left orphaned.
  - The customer is presented with an out-of-stock message during order submission.
  - An error log is recorded in `var/log/supplier_sync.log`.

---

## 9. Duplicate Order Lifecycle Events & Recovery

- If Magento dispatches multiple order placement signals (e.g., retried webhook or double click):
  - The unique constraint on `order_item_id` in `company_supplier_reservation` prevents duplicate insertion.
  - The observer detects the existing reservation and gracefully ignores duplicate execution.
