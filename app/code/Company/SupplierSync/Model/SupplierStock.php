<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model;

use Company\SupplierSync\Api\Data\SupplierStockInterface;
use Company\SupplierSync\Model\ResourceModel\SupplierStock as SupplierStockResource;
use Magento\Framework\Model\AbstractModel;

class SupplierStock extends AbstractModel implements SupplierStockInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(SupplierStockResource::class);
    }

    public function getStockId(): ?int
    {
        $id = $this->getData(self::STOCK_ID);
        return $id !== null ? (int)$id : null;
    }

    public function setStockId(int $stockId): self
    {
        return $this->setData(self::STOCK_ID, $stockId);
    }

    public function getSupplierId(): int
    {
        return (int)$this->getData(self::SUPPLIER_ID);
    }

    public function setSupplierId(int $supplierId): self
    {
        return $this->setData(self::SUPPLIER_ID, $supplierId);
    }

    public function getSku(): string
    {
        return (string)$this->getData(self::SKU);
    }

    public function setSku(string $sku): self
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getWebsiteId(): int
    {
        return (int)$this->getData(self::WEBSITE_ID);
    }

    public function setWebsiteId(int $websiteId): self
    {
        return $this->setData(self::WEBSITE_ID, $websiteId);
    }

    public function getQuantity(): float
    {
        return (float)$this->getData(self::QUANTITY);
    }

    public function setQuantity(float $quantity): self
    {
        return $this->setData(self::QUANTITY, $quantity);
    }

    public function getReservedQuantity(): float
    {
        return (float)$this->getData(self::RESERVED_QUANTITY);
    }

    public function setReservedQuantity(float $reservedQuantity): self
    {
        return $this->setData(self::RESERVED_QUANTITY, $reservedQuantity);
    }

    public function getUnitPrice(): float
    {
        return (float)$this->getData(self::UNIT_PRICE);
    }

    public function setUnitPrice(float $unitPrice): self
    {
        return $this->setData(self::UNIT_PRICE, $unitPrice);
    }

    public function getCurrency(): string
    {
        return (string)($this->getData(self::CURRENCY) ?: 'USD');
    }

    public function setCurrency(string $currency): self
    {
        return $this->setData(self::CURRENCY, $currency);
    }

    public function getVersion(): int
    {
        return (int)($this->getData(self::VERSION) ?: 1);
    }

    public function setVersion(int $version): self
    {
        return $this->setData(self::VERSION, $version);
    }

    public function getSalableQuantity(): float
    {
        return max(0.0, $this->getQuantity() - $this->getReservedQuantity());
    }
}
