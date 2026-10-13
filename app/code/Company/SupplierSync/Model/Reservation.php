<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model;

use Company\SupplierSync\Api\Data\ReservationInterface;
use Company\SupplierSync\Model\ResourceModel\Reservation as ReservationResource;
use Magento\Framework\Model\AbstractModel;

class Reservation extends AbstractModel implements ReservationInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReservationResource::class);
    }

    public function getReservationId(): ?int
    {
        $id = $this->getData(self::RESERVATION_ID);
        return $id !== null ? (int)$id : null;
    }

    public function setReservationId(int $reservationId): self
    {
        return $this->setData(self::RESERVATION_ID, $reservationId);
    }

    public function getOrderId(): int
    {
        return (int)$this->getData(self::ORDER_ID);
    }

    public function setOrderId(int $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getOrderIncrementId(): string
    {
        return (string)$this->getData(self::ORDER_INCREMENT_ID);
    }

    public function setOrderIncrementId(string $orderIncrementId): self
    {
        return $this->setData(self::ORDER_INCREMENT_ID, $orderIncrementId);
    }

    public function getOrderItemId(): int
    {
        return (int)$this->getData(self::ORDER_ITEM_ID);
    }

    public function setOrderItemId(int $orderItemId): self
    {
        return $this->setData(self::ORDER_ITEM_ID, $orderItemId);
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

    public function getReleasedQuantity(): float
    {
        return (float)($this->getData(self::RELEASED_QUANTITY) ?: 0.0);
    }

    public function setReleasedQuantity(float $releasedQuantity): self
    {
        return $this->setData(self::RELEASED_QUANTITY, $releasedQuantity);
    }

    public function getStatus(): string
    {
        return (string)($this->getData(self::STATUS) ?: self::STATUS_RESERVED);
    }

    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
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
