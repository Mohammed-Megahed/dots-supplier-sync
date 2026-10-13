<?php
declare(strict_types=1);

namespace Company\SupplierSync\Observer;

use Company\SupplierSync\Api\ReservationManagementInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Psr\Log\LoggerInterface;

class OrderItemCancelObserver implements ObserverInterface
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
        /** @var OrderItemInterface $item */
        $item = $observer->getEvent()->getItem();
        if (!$item || !$item->getItemId()) {
            return;
        }

        $qtyToCancel = (float)$item->getQtyToCancel();
        if ($qtyToCancel <= 0) {
            return;
        }

        try {
            $this->reservationManagement->releaseOrderItem((int)$item->getItemId(), $qtyToCancel);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[SupplierSync][Observer] Item cancellation release failed for Item #%d: %s',
                $item->getItemId(),
                $e->getMessage()
            ));
        }
    }
}
