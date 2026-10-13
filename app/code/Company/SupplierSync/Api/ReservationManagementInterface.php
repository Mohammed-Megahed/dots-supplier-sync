<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Exception\LocalizedException;

interface ReservationManagementInterface
{
    /**
     * Reserve supplier stock for an eligible order.
     *
     * @param OrderInterface $order
     * @return void
     * @throws LocalizedException
     */
    public function reserveOrderItems(OrderInterface $order): void;

    /**
     * Cancel reservations for an entire order.
     *
     * @param OrderInterface $order
     * @return void
     */
    public function cancelOrderReservation(OrderInterface $order): void;

    /**
     * Release supplier reservation for an order item (supports partial/full release).
     *
     * @param int $orderItemId
     * @param float $qtyToRelease
     * @return void
     */
    public function releaseOrderItem(int $orderItemId, float $qtyToRelease): void;
}
