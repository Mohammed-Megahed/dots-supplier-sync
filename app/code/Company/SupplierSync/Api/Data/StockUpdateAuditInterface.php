<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api\Data;

interface StockUpdateAuditInterface
{
    public const AUDIT_ID = 'audit_id';
    public const IDEMPOTENCY_KEY = 'idempotency_key';
    public const SUPPLIER_CODE = 'supplier_code';
    public const SKU = 'sku';
    public const WEBSITE_CODE = 'website_code';
    public const PAYLOAD_HASH = 'payload_hash';
    public const REQUEST_PAYLOAD = 'request_payload';
    public const STATUS = 'status';
    public const ATTEMPTS = 'attempts';
    public const ERROR_MESSAGE = 'error_message';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_RETRYABLE_FAILED = 'RETRYABLE_FAILED';

    /**
     * @return int|null
     */
    public function getAuditId(): ?int;

    /**
     * @param int $auditId
     * @return $this
     */
    public function setAuditId(int $auditId): self;

    /**
     * @return string
     */
    public function getIdempotencyKey(): string;

    /**
     * @param string $idempotencyKey
     * @return $this
     */
    public function setIdempotencyKey(string $idempotencyKey): self;

    /**
     * @return string
     */
    public function getSupplierCode(): string;

    /**
     * @param string $supplierCode
     * @return $this
     */
    public function setSupplierCode(string $supplierCode): self;

    /**
     * @return string
     */
    public function getSku(): string;

    /**
     * @param string $sku
     * @return $this
     */
    public function setSku(string $sku): self;

    /**
     * @return string
     */
    public function getWebsiteCode(): string;

    /**
     * @param string $websiteCode
     * @return $this
     */
    public function setWebsiteCode(string $websiteCode): self;

    /**
     * @return string
     */
    public function getPayloadHash(): string;

    /**
     * @param string $payloadHash
     * @return $this
     */
    public function setPayloadHash(string $payloadHash): self;

    /**
     * @return string
     */
    public function getRequestPayload(): string;

    /**
     * @param string $requestPayload
     * @return $this
     */
    public function setRequestPayload(string $requestPayload): self;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * @return int
     */
    public function getAttempts(): int;

    /**
     * @param int $attempts
     * @return $this
     */
    public function setAttempts(int $attempts): self;

    /**
     * @return string|null
     */
    public function getErrorMessage(): ?string;

    /**
     * @param string|null $errorMessage
     * @return $this
     */
    public function setErrorMessage(?string $errorMessage): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): self;
}
