<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api\Data;

interface SupplierInterface
{
    public const SUPPLIER_ID = 'supplier_id';
    public const SUPPLIER_CODE = 'supplier_code';
    public const NAME = 'name';
    public const IS_ENABLED = 'is_enabled';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return int|null
     */
    public function getSupplierId(): ?int;

    /**
     * @param int $supplierId
     * @return $this
     */
    public function setSupplierId(int $supplierId): self;

    /**
     * @return string|null
     */
    public function getSupplierCode(): ?string;

    /**
     * @param string $supplierCode
     * @return $this
     */
    public function setSupplierCode(string $supplierCode): self;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * @return bool
     */
    public function getIsEnabled(): bool;

    /**
     * @param bool $isEnabled
     * @return $this
     */
    public function setIsEnabled(bool $isEnabled): self;

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
