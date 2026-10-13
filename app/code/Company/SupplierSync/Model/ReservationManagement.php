<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model;

use Company\SupplierSync\Api\Data\ReservationInterface;
use Company\SupplierSync\Api\ReservationManagementInterface;
use Company\SupplierSync\Api\SupplierStockRepositoryInterface;
use Company\SupplierSync\Model\ResourceModel\Reservation\CollectionFactory as ReservationCollectionFactory;
use Company\SupplierSync\Model\ResourceModel\SupplierStock as SupplierStockResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Psr\Log\LoggerInterface;

class ReservationManagement implements ReservationManagementInterface
{
    private ResourceConnection $resourceConnection;
    private SupplierStockRepositoryInterface $supplierStockRepository;
    private SupplierStockResource $supplierStockResource;
    private ReservationFactory $reservationFactory;
    private ReservationRepository $reservationRepository;
    private ReservationCollectionFactory $reservationCollectionFactory;
    private \Magento\Store\Model\StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        SupplierStockRepositoryInterface $supplierStockRepository,
        SupplierStockResource $supplierStockResource,
        ReservationFactory $reservationFactory,
        ReservationRepository $reservationRepository,
        ReservationCollectionFactory $reservationCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->supplierStockRepository = $supplierStockRepository;
        $this->supplierStockResource = $supplierStockResource;
        $this->reservationFactory = $reservationFactory;
        $this->reservationRepository = $reservationRepository;
        $this->reservationCollectionFactory = $reservationCollectionFactory;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function reserveOrderItems(OrderInterface $order): void
    {
        $websiteId = (int)$this->storeManager->getStore($order->getStoreId())->getWebsiteId();
        $connection = $this->resourceConnection->getConnection();

        $connection->beginTransaction();
        try {
            /** @var OrderItemInterface $item */
            foreach ($order->getItems() as $item) {
                // Skip parent items of configurable/bundle to avoid double counting
                if (method_exists($item, 'getChildrenItems') && !empty($item->getChildrenItems())) {
                    continue;
                }

                $orderItemId = (int)$item->getItemId();
                $qty = (float)$item->getQtyOrdered();
                if ($qty <= 0) {
                    continue;
                }

                // Check if already reserved (idempotency guard)
                try {
                    $existing = $this->reservationRepository->getByOrderItemId($orderItemId);
                    if ($existing->getReservationId()) {
                        $this->logger->info(sprintf(
                            '[SupplierSync] Order item %d already reserved in reservation %d. Skipping.',
                            $orderItemId,
                            $existing->getReservationId()
                        ));
                        continue;
                    }
                } catch (NoSuchEntityException $e) {
                    // Not reserved yet, proceed
                }

                $sku = $item->getSku();

                // Find designated supplier stock
                try {
                    $stock = $this->supplierStockRepository->getDesignatedStockBySkuAndWebsite($sku, $websiteId);
                } catch (NoSuchEntityException $e) {
                    $this->logger->warning(sprintf(
                        '[SupplierSync] No designated active supplier stock found for SKU "%s" on website %d. Order: %s',
                        $sku,
                        $websiteId,
                        $order->getIncrementId()
                    ));
                    // If no supplier mapping exists, allow order or throw depending on configuration
                    continue;
                }

                $stockId = (int)$stock->getStockId();
                $supplierId = (int)$stock->getSupplierId();

                // Atomic reservation at database level
                $success = $this->supplierStockResource->atomicReserveById($stockId, $qty);
                if (!$success) {
                    throw new LocalizedException(__(
                        'Insufficient supplier stock for SKU "%1". Could not reserve %2 units.',
                        $sku,
                        $qty
                    ));
                }

                /** @var Reservation $reservation */
                $reservation = $this->reservationFactory->create();
                $reservation->setOrderId((int)$order->getEntityId());
                $reservation->setOrderIncrementId((string)$order->getIncrementId());
                $reservation->setOrderItemId($orderItemId);
                $reservation->setSupplierId($supplierId);
                $reservation->setSku($sku);
                $reservation->setWebsiteId($websiteId);
                $reservation->setQuantity($qty);
                $reservation->setReleasedQuantity(0.0);
                $reservation->setStatus(ReservationInterface::STATUS_RESERVED);

                $this->reservationRepository->save($reservation);

                $this->logger->info(sprintf(
                    '[SupplierSync] Successfully reserved %.2f units of SKU "%s" from Supplier %d for Order %s (Item: %d)',
                    $qty,
                    $sku,
                    $supplierId,
                    $order->getIncrementId(),
                    $orderItemId
                ));
            }

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->error(sprintf(
                '[SupplierSync] Reservation failed for Order %s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
            throw $e;
        }
    }

    public function cancelOrderReservation(OrderInterface $order): void
    {
        $collection = $this->reservationCollectionFactory->create();
        $collection->addFieldToFilter('order_id', (int)$order->getEntityId());
        $collection->addFieldToFilter('status', ['neq' => ReservationInterface::STATUS_RELEASED_FULL]);

        foreach ($collection as $reservation) {
            $remaining = (float)$reservation->getQuantity() - (float)$reservation->getReleasedQuantity();
            if ($remaining > 0) {
                $this->releaseOrderItem((int)$reservation->getOrderItemId(), $remaining);
            }
        }
    }

    public function releaseOrderItem(int $orderItemId, float $qtyToRelease): void
    {
        if ($qtyToRelease <= 0) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $reservation = $this->reservationRepository->getByOrderItemId($orderItemId);
            if ($reservation->getStatus() === ReservationInterface::STATUS_RELEASED_FULL) {
                $connection->commit();
                return;
            }

            $unreleased = (float)$reservation->getQuantity() - (float)$reservation->getReleasedQuantity();
            if ($unreleased <= 0) {
                $reservation->setStatus(ReservationInterface::STATUS_RELEASED_FULL);
                $this->reservationRepository->save($reservation);
                $connection->commit();
                return;
            }

            $actualRelease = min($qtyToRelease, $unreleased);

            // Fetch supplier stock record
            $stock = $this->supplierStockRepository->getBySupplierSkuAndWebsite(
                (int)$reservation->getSupplierId(),
                $reservation->getSku(),
                (int)$reservation->getWebsiteId()
            );

            // Atomic release at database level
            $this->supplierStockResource->atomicReleaseById((int)$stock->getStockId(), $actualRelease);

            $newReleased = (float)$reservation->getReleasedQuantity() + $actualRelease;
            $reservation->setReleasedQuantity($newReleased);

            if ($newReleased >= (float)$reservation->getQuantity()) {
                $reservation->setStatus(ReservationInterface::STATUS_RELEASED_FULL);
            } else {
                $reservation->setStatus(ReservationInterface::STATUS_RELEASED_PARTIAL);
            }

            $this->reservationRepository->save($reservation);

            $this->logger->info(sprintf(
                '[SupplierSync] Released %.2f units for Order Item %d (SKU %s, Supplier %d). New status: %s',
                $actualRelease,
                $orderItemId,
                $reservation->getSku(),
                $reservation->getSupplierId(),
                $reservation->getStatus()
            ));

            $connection->commit();
        } catch (NoSuchEntityException $e) {
            $connection->rollBack();
            // Reservation did not exist for this item
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->error(sprintf(
                '[SupplierSync] Error releasing reservation for Order Item %d: %s',
                $orderItemId,
                $e->getMessage()
            ));
            throw $e;
        }
    }
}
