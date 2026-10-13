<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model;

use Company\SupplierSync\Api\Data\StockUpdateAuditInterface;
use Company\SupplierSync\Model\ResourceModel\StockUpdateAudit as AuditResource;
use Magento\Framework\Model\AbstractModel;

class StockUpdateAudit extends AbstractModel implements StockUpdateAuditInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(AuditResource::class);
    }

    public function getAuditId(): ?int
    {
        $id = $this->getData(self::AUDIT_ID);
        return $id !== null ? (int)$id : null;
    }

    public function setAuditId(int $auditId): self
    {
        return $this->setData(self::AUDIT_ID, $auditId);
    }

    public function getIdempotencyKey(): string
    {
        return (string)$this->getData(self::IDEMPOTENCY_KEY);
    }

    public function setIdempotencyKey(string $idempotencyKey): self
    {
        return $this->setData(self::IDEMPOTENCY_KEY, $idempotencyKey);
    }

    public function getSupplierCode(): string
    {
        return (string)$this->getData(self::SUPPLIER_CODE);
    }

    public function setSupplierCode(string $supplierCode): self
    {
        return $this->setData(self::SUPPLIER_CODE, $supplierCode);
    }

    public function getSku(): string
    {
        return (string)$this->getData(self::SKU);
    }

    public function setSku(string $sku): self
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getWebsiteCode(): string
    {
        return (string)($this->getData(self::WEBSITE_CODE) ?: 'base');
    }

    public function setWebsiteCode(string $websiteCode): self
    {
        return $this->setData(self::WEBSITE_CODE, $websiteCode);
    }

    public function getPayloadHash(): string
    {
        return (string)$this->getData(self::PAYLOAD_HASH);
    }

    public function setPayloadHash(string $payloadHash): self
    {
        return $this->setData(self::PAYLOAD_HASH, $payloadHash);
    }

    public function getRequestPayload(): string
    {
        return (string)$this->getData(self::REQUEST_PAYLOAD);
    }

    public function setRequestPayload(string $requestPayload): self
    {
        return $this->setData(self::REQUEST_PAYLOAD, $requestPayload);
    }

    public function getStatus(): string
    {
        return (string)($this->getData(self::STATUS) ?: self::STATUS_PENDING);
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getAttempts(): int
    {
        return (int)($this->getData(self::ATTEMPTS) ?: 0);
    }

    public function setAttempts(int $attempts): self
    {
        return $this->setData(self::ATTEMPTS, $attempts);
    }

    public function getErrorMessage(): ?string
    {
        return $this->getData(self::ERROR_MESSAGE);
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        return $this->setData(self::ERROR_MESSAGE, $errorMessage);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(string $updatedAt): self
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
