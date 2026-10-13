<?php
declare(strict_types=1);

namespace Company\SupplierSync\Observer;

use Company\SupplierSync\Api\ReservationManagementInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

class OrderCancelAfterObserver implements ObserverInterface
{
    private ReservationManagementInterface $reservationManagement;
    private LoggerInterface $logger;

    public function __construct(
        ReservationManagementInterface $reservationManagement,
        LoggerInterface $logger
    ) {
        $this->reservationManagement = $reservationManagement;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getEntityId()) {
            return;
        }

        try {
            $this->reservationManagement->cancelOrderReservation($order);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[SupplierSync][Observer] Order cancellation release failed for Order #%s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }
}
