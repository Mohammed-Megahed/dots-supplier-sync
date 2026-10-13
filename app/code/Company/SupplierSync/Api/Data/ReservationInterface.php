<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api\Data;

interface ReservationInterface
{
    public const RESERVATION_ID = 'reservation_id';
    public const ORDER_ID = 'order_id';
    public const ORDER_INCREMENT_ID = 'order_increment_id';
    public const ORDER_ITEM_ID = 'order_item_id';
    public const SUPPLIER_ID = 'supplier_id';
    public const SKU = 'sku';
    public const WEBSITE_ID = 'website_id';
    public const QUANTITY = 'quantity';
    public const RELEASED_QUANTITY = 'released_quantity';
    public const STATUS = 'status';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_RESERVED = 'RESERVED';
    public const STATUS_RELEASED_FULL = 'RELEASED_FULL';
    public const STATUS_RELEASED_PARTIAL = 'RELEASED_PARTIAL';

    /**
     * @return int|null
     */
    public function getReservationId(): ?int;

    /**
     * @param int $reservationId
     * @return $this
     */
    public function setReservationId(int $reservationId): self;

    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * @param int $orderId
     * @return $this
     */
    public function setOrderId(int $orderId): self;

    /**
     * @return string
     */
    public function getOrderIncrementId(): string;

    /**
     * @param string $orderIncrementId
     * @return $this
     */
    public function setOrderIncrementId(string $orderIncrementId): self;

    /**
     * @return int
     */
    public function getOrderItemId(): int;

    /**
     * @param int $orderItemId
     * @return $this
     */
    public function setOrderItemId(int $orderItemId): self;

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
    public function getReleasedQuantity(): float;

    /**
     * @param float $releasedQuantity
     * @return $this
     */
    public function setReleasedQuantity(float $releasedQuantity): self;

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
