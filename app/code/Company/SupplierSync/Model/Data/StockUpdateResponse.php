<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\Data;

use Company\SupplierSync\Api\Data\StockUpdateResponseInterface;
use Magento\Framework\DataObject;

class StockUpdateResponse extends DataObject implements StockUpdateResponseInterface
{
    public function getStatus(): string
    {
        return (string)$this->getData(self::STATUS);
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getMessage(): string
    {
        return (string)$this->getData(self::MESSAGE);
    }

    public function setMessage(string $message): self
    {
        return $this->setData(self::MESSAGE, $message);
    }

    public function getIdempotencyKey(): string
    {
        return (string)$this->getData(self::IDEMPOTENCY_KEY);
    }

    public function setIdempotencyKey(string $idempotencyKey): self
    {
        return $this->setData(self::IDEMPOTENCY_KEY, $idempotencyKey);
    }

    public function getAuditId(): ?int
    {
        $id = $this->getData(self::AUDIT_ID);
        return $id !== null ? (int)$id : null;
    }

    public function setAuditId(?int $auditId): self
    {
        return $this->setData(self::AUDIT_ID, $auditId);
    }
}
