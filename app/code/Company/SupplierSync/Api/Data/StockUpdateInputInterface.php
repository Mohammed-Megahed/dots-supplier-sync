<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api\Data;

interface StockUpdateInputInterface
{
    public const SUPPLIER_CODE = 'supplier_code';
    public const IDEMPOTENCY_KEY = 'idempotency_key';
    public const SKU = 'sku';
    public const QUANTITY = 'quantity';
    public const UNIT_PRICE = 'unit_price';
    public const CURRENCY = 'currency';
    public const WEBSITE_CODE = 'website_code';

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
    public function getIdempotencyKey(): string;

    /**
     * @param string $idempotencyKey
     * @return $this
     */
    public function setIdempotencyKey(string $idempotencyKey): self;

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
     * @return string
     */
    public function getWebsiteCode(): string;

    /**
     * @param string $websiteCode
     * @return $this
     */
    public function setWebsiteCode(string $websiteCode): self;
}
