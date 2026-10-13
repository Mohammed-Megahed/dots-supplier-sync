# Company_SupplierSync: Supplier Stock Synchronization & Order Reservation

An enterprise Magento 2 module engineered for high-reliability, asynchronous supplier inventory synchronization, idempotent REST ingestion, and concurrency-safe order stock reservation.

---

## Technical Specifications
- **Magento Version:** 2.4.9 (Open Source / Commerce compatible)
- **PHP Version:** 8.2 / 8.3 / 8.4 / 8.5
- **Message Broker:** RabbitMQ 3.12+ / 4.x via AMQP (`company.supplier.stock.update` exchange and queue)
- **Database:** MariaDB 10.11+ / 11.x or MySQL 8.0+ (InnoDB engine with row-level atomic locking)
- **Architecture Standards:** Service Contracts, Declarative Schema, Outbox Pattern, PSR-12, Dependency Injection

---

## Table of Contents
1. [Architecture & Design](#architecture--design)
2. [Installation & Removal](#installation--removal)
3. [Supplier Management CLI](#supplier-management-cli)
4. [Stock Synchronization REST API](#stock-synchronization-rest-api)
5. [Asynchronous Message Queue & Consumer](#asynchronous-message-queue--consumer)
6. [Order Reservation & Lifecycle Handlers](#order-reservation--lifecycle-handlers)
7. [Observability & Error Handling](#observability--error-handling)
8. [Automated Testing & Concurrency Verification](#automated-testing--concurrency-verification)
9. [Known Limitations & Production Hardening](#known-limitations--production-hardening)

---

## 1. Architecture & Design

For deep architectural documentation, refer to:
- [Architecture & Concurrency Control Guide](file:///Users/mohammedmegahed/Work/Dots%20Assignment/Dots/src/app/code/Company/SupplierSync/docs/architecture.md)
- [Business Rules & Domain Invariants](file:///Users/mohammedmegahed/Work/Dots%20Assignment/Dots/src/app/code/Company/SupplierSync/docs/business-rules.md)

### Key Architectural Invariants
- **Non-Invasive Core Integration:** Does not mutate Magento MSI core tables (`inventory_source_item`, `inventory_reservation`). Operates as an authoritative supplier-tier availability and reservation layer.
- **Transactional Outbox & Idempotency:** REST ingest persists normalized requests to `company_supplier_stock_audit` with SHA-256 canonical hashing before queue dispatch. Conflicting payloads under duplicate keys return HTTP 400/409; identical replays return the current processing status.
- **ACID Atomic Concurrency:** Eliminates race conditions by enforcing atomic predicate queries directly in MariaDB/MySQL:
  ```sql
  UPDATE company_supplier_stock
  SET reserved_quantity = reserved_quantity + :qty, version = version + 1
  WHERE stock_id = :stock_id AND (quantity - reserved_quantity) >= :qty;
  ```

---

## 2. Installation & Removal

### Installation
1. Place the module files in `app/code/Company/SupplierSync`.
2. Enable the module:
   ```bash
   bin/magento module:enable Company_SupplierSync
   ```
3. Run database migrations and compile:
   ```bash
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```

### Removal / Disabling
To disable the module:
```bash
bin/magento module:disable Company_SupplierSync
bin/magento setup:upgrade
bin/magento cache:flush
```
*(Database tables remain preserved to retain audit trails and active reservation records unless declarative schema drop is explicitly configured).*

---

## 3. Supplier Management CLI

### Creating or Updating a Supplier
```bash
bin/magento supplier:create --code="SUPPLIER_001" --name="ACME Parts Ltd." --enabled=1
```

Options:
- `-c`, `--code`: Unique supplier alphanumeric code (e.g. `SUPPLIER_001`).
- `-m`, `--name`: Supplier display name.
- `-e`, `--enabled`: `1` (Enabled) or `0` (Disabled).

---

## 4. Stock Synchronization REST API

### Endpoint Specification
```http
POST /rest/V1/supplier-sync/updates
Content-Type: application/json
Authorization: Bearer <ADMIN_OR_INTEGRATION_TOKEN>
```

### Request Payload Example
```json
{
  "update": {
    "supplier_code": "SUPPLIER_001",
    "idempotency_key": "supplier-update-10001",
    "sku": "SKU-1001",
    "quantity": 25,
    "unit_price": 49.99,
    "currency": "USD",
    "website_code": "base"
  }
}
```

### Response Examples

#### 1. Accepted (HTTP 200)
```json
{
  "status": "PENDING",
  "message": "Stock update request has been accepted for asynchronous processing.",
  "idempotency_key": "supplier-update-10001",
  "audit_id": 1
}
```

#### 2. Idempotent Replay (HTTP 200)
```json
{
  "status": "COMPLETED",
  "message": "Request already accepted under identical idempotency key.",
  "idempotency_key": "supplier-update-10001",
  "audit_id": 1
}
```

#### 3. Conflicting Idempotency Key (HTTP 400)
```json
{
  "message": "Conflicting idempotency key: The idempotency key \"supplier-update-10001\" was already submitted with different parameters."
}
```

#### 4. Validation Failure (HTTP 400)
```json
{
  "message": "Validation failed for stock update request.",
  "errors": [
    {"message": "Supplier \"%1\" is disabled and cannot accept stock updates.", "parameters": ["SUPPLIER_DISABLED"]},
    {"message": "Product with SKU \"%1\" does not exist in catalog.", "parameters": ["UNKNOWN-SKU"]}
  ]
}
```

---

## 5. Asynchronous Message Queue & Consumer

### Starting the Consumer
To process queued stock updates via RabbitMQ:
```bash
bin/magento queue:consumers:start company.supplier.stock.update.consumer
```
To process in batches (ideal for cron or systemd watchers):
```bash
bin/magento queue:consumers:start company.supplier.stock.update.consumer --max-messages=100
```

### Outbox Replay & Failure Recovery CLI
In case of message broker outages or temporary deadlock retries, execute:
```bash
bin/magento supplier:sync:retry-failed --limit=50
```
Options:
- `-l`, `--limit`: Maximum records to process (default: `50`).
- `-s`, `--status`: Comma-separated list of statuses (`PENDING,RETRYABLE_FAILED,FAILED`).
- `-i`, `--immediate`: Process synchronously in-process instead of re-publishing to RabbitMQ.

---

## 6. Order Reservation & Lifecycle Handlers

The module hooks into Magento core sales events to maintain synchronization without touching core code:
- **`sales_order_place_after`:** Automatically reserves stock for each physical item from the active designated supplier. If supplier stock is insufficient, rolls back and stops placement.
- **`order_cancel_after`:** Releases unreleased reserved stock back into available inventory.
- **`sales_order_item_cancel`:** Releases stock for partial item cancellations.
- **`sales_order_creditmemo_save_after`:** Releases stock for refunded items.

---

## 7. Observability & Error Handling

- **Logging:** All actions, queue emissions, idempotency replays, and failures log cleanly to:
  ```
  var/log/supplier_sync.log
  ```
- **Security:** Credential scrubbing guarantees no access tokens, payment credentials, or customer PII are logged.

---

## 8. Automated Testing & Concurrency Verification

### Running Unit & Integration Tests
Execute PHPUnit inside the container:
```bash
vendor/bin/phpunit app/code/Company/SupplierSync/Test/Unit
```
*Result: 100% passing across validation, idempotency, queue consumer, and reservation mechanics.*

### Concurrency Stress Test Simulation
Demonstrate race condition safety under 20 concurrent threads competing for 10 units:
```bash
bin/magento supplier:simulate:concurrency --threads=20 --units=10
```
**Output:**
```
=========================================================
 Supplier Stock Concurrency Safety Demonstration 
=========================================================
Parameters: Initial Stock = 10 units | Concurrent Attempts = 20 | Qty/Attempt = 1
Test Stock Row ID #1 prepared. Available = 10, Reserved = 0.00
Launching 20 parallel worker processes competing for the row...
---------------------------------------------------------
Total Attempts:     20
Successful Orders:  10
Rejected (No Stock):10
Final Stock Qty:    10.00
Final Reserved Qty: 10.00
---------------------------------------------------------
[PASS] CONCURRENCY SAFETY VERIFIED! No over-reservation occurred.
```

---

## 9. Known Limitations & Production Hardening

1. **Multi-Supplier Allocation:** The current version designates one active supplier per SKU and website scope. For multi-vendor fulfillment, implement a priority/lowest-cost `SupplierSelectionStrategyInterface`.
2. **Dead-Letter Queue (DLQ):** RabbitMQ can be paired with an AMQP dead-letter exchange (DLX) for unroutable packets exceeding max retry thresholds.
3. **High-Frequency Ingestion Throttling:** For tens of thousands of stock updates per minute, batch ingestion (`POST /rest/V1/supplier-sync/bulk-updates`) and Kafka partition streaming can be added.
