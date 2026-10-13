<?php
declare(strict_types=1);

namespace Company\SupplierSync\Test\Unit\Model;

use Company\SupplierSync\Api\Data\ReservationInterface;
use Company\SupplierSync\Api\Data\SupplierStockInterface;
use Company\SupplierSync\Api\SupplierStockRepositoryInterface;
use Company\SupplierSync\Model\Reservation;
use Company\SupplierSync\Model\ReservationFactory;
use Company\SupplierSync\Model\ReservationManagement;
use Company\SupplierSync\Model\ReservationRepository;
use Company\SupplierSync\Model\ResourceModel\Reservation\CollectionFactory as ReservationCollectionFactory;
use Company\SupplierSync\Model\ResourceModel\SupplierStock as SupplierStockResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReservationManagementTest extends TestCase
{
    private ResourceConnection|MockObject $resourceConnectionMock;
    private AdapterInterface|MockObject $connectionMock;
    private SupplierStockRepositoryInterface|MockObject $stockRepositoryMock;
    private SupplierStockResource|MockObject $stockResourceMock;
    private ReservationFactory|MockObject $reservationFactoryMock;
    private ReservationRepository|MockObject $reservationRepositoryMock;
    private ReservationCollectionFactory|MockObject $collectionFactoryMock;
    private \Magento\Store\Model\StoreManagerInterface|MockObject $storeManagerMock;
    private LoggerInterface|MockObject $loggerMock;
    private ReservationManagement $service;

    protected function setUp(): void
    {
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->stockRepositoryMock = $this->createMock(SupplierStockRepositoryInterface::class);
        $this->stockResourceMock = $this->createMock(SupplierStockResource::class);
        $this->reservationFactoryMock = $this->createMock(ReservationFactory::class);
        $this->reservationRepositoryMock = $this->createMock(ReservationRepository::class);
        $this->collectionFactoryMock = $this->createMock(ReservationCollectionFactory::class);
        $this->storeManagerMock = $this->createMock(\Magento\Store\Model\StoreManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->resourceConnectionMock->method('getConnection')->willReturn($this->connectionMock);

        $this->service = new ReservationManagement(
            $this->resourceConnectionMock,
            $this->stockRepositoryMock,
            $this->stockResourceMock,
            $this->reservationFactoryMock,
            $this->reservationRepositoryMock,
            $this->collectionFactoryMock,
            $this->storeManagerMock,
            $this->loggerMock
        );
    }

    public function testReserveOrderItemsDuplicateSkipped(): void
    {
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getStoreId')->willReturn(1);

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getWebsiteId')->willReturn(1);
        $this->storeManagerMock->method('getStore')->with(1)->willReturn($storeMock);

        $itemMock = $this->createMock(OrderItem::class);
        $itemMock->method('getItemId')->willReturn(101);
        $itemMock->method('getQtyOrdered')->willReturn(2.0);

        $orderMock->method('getItems')->willReturn([$itemMock]);

        // Existing reservation found
        $existingReservation = $this->createMock(Reservation::class);
        $existingReservation->method('getReservationId')->willReturn(55);

        $this->reservationRepositoryMock->method('getByOrderItemId')
            ->with(101)
            ->willReturn($existingReservation);

        // Atomic reserve should NOT be called
        $this->stockResourceMock->expects($this->never())->method('atomicReserveById');

        $this->service->reserveOrderItems($orderMock);
    }

    public function testReserveOrderItemsInsufficientStockThrowsException(): void
    {
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getStoreId')->willReturn(1);
        $orderMock->method('getIncrementId')->willReturn('100000001');

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getWebsiteId')->willReturn(1);
        $this->storeManagerMock->method('getStore')->with(1)->willReturn($storeMock);

        $itemMock = $this->createMock(OrderItem::class);
        $itemMock->method('getItemId')->willReturn(101);
        $itemMock->method('getQtyOrdered')->willReturn(5.0);
        $itemMock->method('getSku')->willReturn('SKU-1001');

        $orderMock->method('getItems')->willReturn([$itemMock]);

        $this->reservationRepositoryMock->method('getByOrderItemId')
            ->willThrowException(new NoSuchEntityException(__('None')));

        $stockMock = $this->createMock(SupplierStockInterface::class);
        $stockMock->method('getStockId')->willReturn(9);
        $stockMock->method('getSupplierId')->willReturn(1);

        $this->stockRepositoryMock->method('getDesignatedStockBySkuAndWebsite')
            ->with('SKU-1001', 1)
            ->willReturn($stockMock);

        // Atomic reserve returns false (out of stock)
        $this->stockResourceMock->method('atomicReserveById')
            ->with(9, 5.0)
            ->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->service->reserveOrderItems($orderMock);
    }
}
