<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api\Data;

interface SupplierStockInterface
{
    public const STOCK_ID = 'stock_id';
    public const SUPPLIER_ID = 'supplier_id';
    public const SKU = 'sku';
    public const WEBSITE_ID = 'website_id';
    public const QUANTITY = 'quantity';
    public const RESERVED_QUANTITY = 'reserved_quantity';
    public const UNIT_PRICE = 'unit_price';
    public const CURRENCY = 'currency';
    public const VERSION = 'version';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return int|null
     */
    public function getStockId(): ?int;

    /**
     * @param int $stockId
     * @return $this
     */
    public function setStockId(int $stockId): self;

    /**
     * @return int
     */
    public function getSupplierId(): int;

    /**
     * @param int $supplierId
     * @return $this
     */
    public function setSupplierId(int $supplierId): self;

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
     * @return int
     */
    public function getWebsiteId(): int;

    /**
     * @param int $websiteId
     * @return $this
     */
    public function setWebsiteId(int $websiteId): self;

    /**
     * @return float
     */
    public function getQuantity(): float;

    /**
     * @param float $quantity
     * @return $this
     */
    public function setQuantity(float $quantity): self;

    /**
     * @return float
     */
    public function getReservedQuantity(): float;

    /**
     * @param float $reservedQuantity
     * @return $this
     */
    public function setReservedQuantity(float $reservedQuantity): self;

    /**
     * @return float
     */
    public function getUnitPrice(): float;

    /**
     * @param float $unitPrice
     * @return $this
     */
    public function setUnitPrice(float $unitPrice): self;

    /**
     * @return string
     */
    public function getCurrency(): string;

    /**
     * @param string $currency
     * @return $this
     */
    public function setCurrency(string $currency): self;

    /**
     * @return int
     */
    public function getVersion(): int;

    /**
     * @param int $version
     * @return $this
     */
    public function setVersion(int $version): self;

    /**
     * Available salable supplier quantity = quantity - reserved_quantity.
     *
     * @return float
     */
    public function getSalableQuantity(): float;
}
