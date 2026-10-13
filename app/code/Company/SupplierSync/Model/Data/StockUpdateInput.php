<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\Data;

use Company\SupplierSync\Api\Data\StockUpdateInputInterface;
use Magento\Framework\DataObject;

class StockUpdateInput extends DataObject implements StockUpdateInputInterface
{
    public function getSupplierCode(): string
    {
        return (string)$this->getData(self::SUPPLIER_CODE);
    }

    public function setSupplierCode(string $supplierCode): self
    {
        return $this->setData(self::SUPPLIER_CODE, $supplierCode);
    }

    public function getIdempotencyKey(): string
    {
        return (string)$this->getData(self::IDEMPOTENCY_KEY);
    }

    public function setIdempotencyKey(string $idempotencyKey): self
    {
        return $this->setData(self::IDEMPOTENCY_KEY, $idempotencyKey);
    }

    public function getSku(): string
    {
        return (string)$this->getData(self::SKU);
    }

    public function setSku(string $sku): self
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getQuantity(): float
    {
        return (float)$this->getData(self::QUANTITY);
    }

    public function setQuantity(float $quantity): self
    {
        return $this->setData(self::QUANTITY, $quantity);
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
        return (string)$this->getData(self::CURRENCY);
    }

    public function setCurrency(string $currency): self
    {
        return $this->setData(self::CURRENCY, $currency);
    }

    public function getWebsiteCode(): string
    {
        return (string)$this->getData(self::WEBSITE_CODE);
    }

    public function setWebsiteCode(string $websiteCode): self
    {
        return $this->setData(self::WEBSITE_CODE, $websiteCode);
    }
}
