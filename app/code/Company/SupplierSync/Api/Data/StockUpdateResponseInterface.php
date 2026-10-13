<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api\Data;

interface StockUpdateResponseInterface
{
    public const STATUS = 'status';
    public const MESSAGE = 'message';
    public const IDEMPOTENCY_KEY = 'idempotency_key';
    public const AUDIT_ID = 'audit_id';

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
     * @return string
     */
    public function getMessage(): string;

    /**
     * @param string $message
     * @return $this
     */
    public function setMessage(string $message): self;

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
     * @return int|null
     */
    public function getAuditId(): ?int;

    /**
     * @param int|null $auditId
     * @return $this
     */
    public function setAuditId(?int $auditId): self;
}
