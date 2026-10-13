<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model;

use Company\SupplierSync\Api\Data\SupplierInterface;
use Company\SupplierSync\Model\ResourceModel\Supplier as SupplierResource;
use Magento\Framework\Model\AbstractModel;

class Supplier extends AbstractModel implements SupplierInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(SupplierResource::class);
    }

    public function getSupplierId(): ?int
    {
        $id = $this->getData(self::SUPPLIER_ID);
        return $id !== null ? (int)$id : null;
    }

    public function setSupplierId(int $supplierId): self
    {
        return $this->setData(self::SUPPLIER_ID, $supplierId);
    }

    public function getSupplierCode(): ?string
    {
        return $this->getData(self::SUPPLIER_CODE);
    }

    public function setSupplierCode(string $supplierCode): self
    {
        return $this->setData(self::SUPPLIER_CODE, $supplierCode);
    }

    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function getIsEnabled(): bool
    {
        return (bool)$this->getData(self::IS_ENABLED);
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        return $this->setData(self::IS_ENABLED, $isEnabled ? 1 : 0);
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
