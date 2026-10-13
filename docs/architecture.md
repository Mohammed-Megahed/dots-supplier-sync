# System Architecture & Technical Design

**Module:** `Company_SupplierSync`  
**Platform:** Magento 2.4 (PHP 8.2+ / 8.3+, RabbitMQ, MariaDB 10.11+ / 11.x)  
**Document:** [docs/architecture.md](file:///Users/mohammedmegahed/Work/Dots%20Assignment/Dots/src/app/code/Company/SupplierSync/docs/architecture.md)

---

## 1. High-Level Architectural Overview

The `Company_SupplierSync` module implements an enterprise-grade, asynchronous supplier stock synchronization and reservation system. It decouples high-throughput external supplier REST ingestion from database-intensive inventory updates, guarantees end-to-end idempotency, enforces strict concurrency boundaries, and protects against inventory over-allocation during order placement and order cancellations.

### Architectural Component Diagram

```
[ External Supplier / ERP ]
             |
             | 1. POST /rest/V1/supplier-sync/updates
             v
+--------------------------------------------------------+
| WebAPI Layer (StockUpdateManagement)                   |
| - Authentication & ACL Check                           |
| - Validation (Supplier, SKU, Price, Qty, Scope)        |
| - Idempotency Validation (SHA-256 Payload Hash Check)  |
| - Transactional Outbox Insert (Audit Table)            |
+--------------------------------------------------------+
             |                                |
             | 2. Publish Message             | Returns 200 OK
             v                                v (Status: PENDING / ACCEPTED)
+------------------------+        [ External Client ]
| RabbitMQ Topic Exchange|
| (company.supplier.     |
|  stock.update)         |
+------------------------+
             |
             | 3. Consume
             v
+--------------------------------------------------------+
| Queue Consumer (StockUpdateConsumer)                   |
| - Duplicate Delivery Guard                             |
| - DB Transaction Boundary                              |
| - Upgrades Supplier Stock Snapshot                     |
| - Marks Audit Record COMPLETED / RETRYABLE_FAILED      |
+--------------------------------------------------------+
             |
             v
+--------------------------------------------------------+
| Database Storage Layer                                 |
| - company_supplier                                     |
| - company_supplier_stock (Pessimistic/Atomic Locks)    |
| - company_supplier_stock_audit                         |
| - company_supplier_reservation (Unique order_item_id)   |
+--------------------------------------------------------+
             ^
             | 4. Atomic Reservation & Release
+--------------------------------------------------------+
| Order Lifecycle Observers                              |
| - sales_order_place_after (Atomic Reserve)             |
| - order_cancel_after (Full Release)                    |
| - sales_order_item_cancel (Partial Release)            |
| - sales_order_creditmemo_save_after (Refund Release)   |
+--------------------------------------------------------+
```

---

## 2. Transaction Boundaries & Data Consistency

### A. WebAPI Ingestion Phase
- **Scope:** Isolated audit insert transaction.
- **Boundary:**
  1. Acquire/check `idempotency_key` in `company_supplier_stock_audit`.
  2. If unique, insert new audit record with state `PENDING`.
  3. Publish message to RabbitMQ exchange.
  4. If RabbitMQ is unavailable, the audit record remains in `PENDING` state (the Outbox recovery worker `bin/magento supplier:sync:retry-failed` scans and publishes pending records without data loss).

### B. Asynchronous Consumer Phase
- **Scope:** Individual message transaction boundary.
- **Boundary:**
  ```php
  $connection->beginTransaction();
  try {
      // 1. Lock / check active supplier and website
      // 2. Insert or update company_supplier_stock with new quantity & price
      // 3. Mark audit record as COMPLETED
      $connection->commit();
  } catch (DeadlockException | LockWaitException $e) {
      $connection->rollBack();
      // Mark RETRYABLE_FAILED, rethrow to trigger queue retry
  } catch (\Exception $e) {
      $connection->rollBack();
      // Mark FAILED (permanent validation error), log securely
  }
  ```

### C. Order Stock Reservation Phase
- **Scope:** Runs during order placement (`sales_order_place_after`) inside the checkout transaction.
- **Boundary:**
  - Evaluates each physical item line.
  - Executes atomic SQL update on `company_supplier_stock`:
    ```sql
    UPDATE company_supplier_stock 
    SET reserved_quantity = reserved_quantity + :qty, version = version + 1
    WHERE stock_id = :stock_id AND (quantity - reserved_quantity) >= :qty;
    ```
  - If `rowCount == 0`: Insufficient available stock. Rollback entire transaction, throw `LocalizedException`, halting the order before inconsistent state occurs.
  - If successful: Insert `company_supplier_reservation` with unique `order_item_id`.

---

## 3. Concurrency Control & Race Condition Prevention

### Concurrency Challenge
When multiple customers checkout simultaneously for the same limited item (e.g., 20 customers racing for 10 units), traditional read-then-write logic (`if ($stock->getQty() >= $ordered) { $stock->save(); }`) causes **phantom reads** and catastrophic **over-reservation**.

### Architectural Solution
1. **Atomic Conditional Updates:** By combining the condition check `(quantity - reserved_quantity) >= :qty` directly into the atomic `UPDATE` query, the database storage engine (InnoDB) serializes the row lock internally using row-level locking.
2. **Deterministic Outcome:** Only the exact number of requests that satisfy the predicate succeed; any subsequent request encounters a zero affected row count and is immediately rejected.
3. **Reproducibility Test:** Verified via `bin/magento supplier:simulate:concurrency` where 20 parallel threads competing for 10 units guarantee exactly 10 successes and 10 rejections, leaving reserved quantity at exactly 10.00.

---

## 4. Idempotency Implementation

Idempotency guarantees that duplicate or repeated requests produce identical results without duplicate side-effects.

### Request Normalization & Hashing
1. **Payload Canonicalization:** Keys are sorted alphabetically and standard types normalized:
   ```php
   $normalized = [
       'currency' => strtoupper(trim($input->getCurrency())),
       'quantity' => (float)$input->getQuantity(),
       'sku' => trim($input->getSku()),
       'supplier_code' => trim($input->getSupplierCode()),
       'unit_price' => (float)$input->getUnitPrice(),
       'website_code' => trim($input->getWebsiteCode())
   ];
   $hash = hash('sha256', json_encode($normalized));
   ```
2. **Deterministic Decision Tree:**
   - **Key Not Found:** Safe to create audit entry, publish message, return `ACCEPTED`.
   - **Key Found & Stored Hash == Incoming Hash:** Identical request replay. Return current audit status with HTTP 200 without creating a new queue message or duplicating stock updates.
   - **Key Found & Stored Hash != Incoming Hash:** Conflict! Reject with `LocalizedException` ("Conflicting idempotency key...") which maps to an HTTP 400/409 error.

---

## 5. Message Queue Recovery & Outbox Pattern

### Resilience against Broker Outages
- If RabbitMQ goes down, the API persists incoming payloads into the `company_supplier_stock_audit` table with state `PENDING`.
- Once connectivity is restored, the CLI outbox worker `bin/magento supplier:sync:retry-failed` polls `PENDING` and `RETRYABLE_FAILED` records and dispatches them to RabbitMQ.

### Distinction of Failure Modes
- **Retryable Errors:** `LockWaitException`, `DeadlockException`, temporary connection dropouts. State: `RETRYABLE_FAILED`. Re-queued with exponential backoff or recovered via CLI.
- **Permanent Errors:** Inactive supplier, unmapped SKU, negative values. State: `FAILED`. Safely logged without spamming queue retries.

---

## 6. Observability & Security

- **Dedicated Log Channel:** All events written to `var/log/supplier_sync.log`.
- **Credential Masking:** No passwords, bearer tokens, or customer PII are logged.
- **Safe Error Reporting:** WebAPI exceptions suppress database stack traces from external callers while logging full traces internally for engineering diagnostics.

---

## 7. Trade-offs and Architectural Decisions

| Decision | Selected Approach | Alternative Considered | Trade-off Rationale |
|---|---|---|---|
| **Stock Reservation Concurrency** | Atomic SQL predicate `(qty - reserved) >= req` | Application-level Redis lock | Avoids distributed lock timeouts and Redis/DB desynchronization; utilizes ACID row-level locking directly in InnoDB. |
| **Ingestion Pipeline** | Asynchronous message queue (RabbitMQ) | Synchronous REST write | Provides sub-50ms API response latency, decouples high volume supplier ERP bursts, buffers traffic. |
| **MSI Integration** | Independent availability layer (no MSI mutation) | Mutating MSI core tables | Prevents corrupting Magento's inventory state machine and enables clear separation of supplier feeds from warehouse sources. |
| **Outbox Model** | Audit table as transactional outbox | In-memory message dispatch only | Eliminates message loss during network partitions between Magento PHP and RabbitMQ. |
