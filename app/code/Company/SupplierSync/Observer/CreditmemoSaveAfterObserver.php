<?php
declare(strict_types=1);

namespace Company\SupplierSync\Observer;

use Company\SupplierSync\Api\ReservationManagementInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Psr\Log\LoggerInterface;

class CreditmemoSaveAfterObserver implements ObserverInterface
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
        /** @var CreditmemoInterface $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();
        if (!$creditmemo) {
            return;
        }

        try {
            /** @var CreditmemoItemInterface $item */
            foreach ($creditmemo->getItems() as $item) {
                $orderItemId = (int)$item->getOrderItemId();
                $qty = (float)$item->getQty();
                if ($orderItemId > 0 && $qty > 0) {
                    $this->reservationManagement->releaseOrderItem($orderItemId, $qty);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[SupplierSync][Observer] Refund release failed for Creditmemo #%s: %s',
                $creditmemo->getIncrementId(),
                $e->getMessage()
            ));
        }
    }
}
