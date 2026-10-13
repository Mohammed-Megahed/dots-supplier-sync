<?php
declare(strict_types=1);

namespace Company\SupplierSync\Test\Unit\Model\Queue;

use Company\SupplierSync\Api\Data\StockUpdateAuditInterface;
use Company\SupplierSync\Api\Data\StockUpdateQueueMessageInterface;
use Company\SupplierSync\Api\Data\SupplierInterface;
use Company\SupplierSync\Api\Data\SupplierStockInterface;
use Company\SupplierSync\Api\StockUpdateAuditRepositoryInterface;
use Company\SupplierSync\Api\SupplierRepositoryInterface;
use Company\SupplierSync\Api\SupplierStockRepositoryInterface;
use Company\SupplierSync\Model\Queue\StockUpdateConsumer;
use Company\SupplierSync\Model\SupplierStock;
use Company\SupplierSync\Model\SupplierStockFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\LockWaitException;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StockUpdateConsumerTest extends TestCase
{
    private StockUpdateAuditRepositoryInterface|MockObject $auditRepositoryMock;
    private SupplierRepositoryInterface|MockObject $supplierRepositoryMock;
    private SupplierStockRepositoryInterface|MockObject $supplierStockRepositoryMock;
    private SupplierStockFactory|MockObject $supplierStockFactoryMock;
    private WebsiteRepositoryInterface|MockObject $websiteRepositoryMock;
    private ResourceConnection|MockObject $resourceConnectionMock;
    private AdapterInterface|MockObject $connectionMock;
    private LoggerInterface|MockObject $loggerMock;
    private StockUpdateConsumer $consumer;

    protected function setUp(): void
    {
        $this->auditRepositoryMock = $this->createMock(StockUpdateAuditRepositoryInterface::class);
        $this->supplierRepositoryMock = $this->createMock(SupplierRepositoryInterface::class);
        $this->supplierStockRepositoryMock = $this->createMock(SupplierStockRepositoryInterface::class);
        $this->supplierStockFactoryMock = $this->createMock(SupplierStockFactory::class);
        $this->websiteRepositoryMock = $this->createMock(WebsiteRepositoryInterface::class);
        $this->resourceConnectionMock = $this->createMock(ResourceConnection::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->resourceConnectionMock->method('getConnection')->willReturn($this->connectionMock);

        $this->consumer = new StockUpdateConsumer(
            $this->auditRepositoryMock,
            $this->supplierRepositoryMock,
            $this->supplierStockRepositoryMock,
            $this->supplierStockFactoryMock,
            $this->websiteRepositoryMock,
            $this->resourceConnectionMock,
            $this->loggerMock
        );
    }

    public function testProcessIgnoresDuplicateDeliveryWhenAlreadyCompleted(): void
    {
        $messageMock = $this->createMock(StockUpdateQueueMessageInterface::class);
        $messageMock->method('getAuditId')->willReturn(10);

        $auditMock = $this->createMock(StockUpdateAuditInterface::class);
        $auditMock->method('getStatus')->willReturn(StockUpdateAuditInterface::STATUS_COMPLETED);

        $this->auditRepositoryMock->method('getById')->with(10)->willReturn($auditMock);

        // Connection begin transaction should never be called
        $this->connectionMock->expects($this->never())->method('beginTransaction');

        $this->consumer->process($messageMock);
    }

    public function testProcessSuccessfulUpdate(): void
    {
        $messageMock = $this->createMock(StockUpdateQueueMessageInterface::class);
        $messageMock->method('getAuditId')->willReturn(10);
        $messageMock->method('getSupplierCode')->willReturn('SUPPLIER_001');
        $messageMock->method('getWebsiteCode')->willReturn('base');
        $messageMock->method('getSku')->willReturn('SKU-1001');
        $messageMock->method('getQuantity')->willReturn(50.0);
        $messageMock->method('getUnitPrice')->willReturn(29.99);
        $messageMock->method('getCurrency')->willReturn('USD');

        $auditMock = $this->createMock(StockUpdateAuditInterface::class);
        $auditMock->method('getStatus')->willReturn(StockUpdateAuditInterface::STATUS_PENDING);
        $auditMock->method('getAttempts')->willReturn(0);

        $this->auditRepositoryMock->method('getById')->with(10)->willReturn($auditMock);

        $supplierMock = $this->createMock(SupplierInterface::class);
        $supplierMock->method('getSupplierId')->willReturn(1);
        $supplierMock->method('getIsEnabled')->willReturn(true);
        $this->supplierRepositoryMock->method('getByCode')->with('SUPPLIER_001')->willReturn($supplierMock);

        $websiteMock = $this->createMock(WebsiteInterface::class);
        $websiteMock->method('getId')->willReturn(1);
        $this->websiteRepositoryMock->method('get')->with('base')->willReturn($websiteMock);

        $stockMock = $this->getMockBuilder(SupplierStock::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setQuantity', 'setUnitPrice', 'setCurrency', 'setVersion', 'getVersion'])
            ->getMock();
        $stockMock->method('getVersion')->willReturn(1);

        $this->supplierStockRepositoryMock->method('getBySupplierSkuAndWebsite')->willReturn($stockMock);

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('commit');
        $this->supplierStockRepositoryMock->expects($this->once())->method('save');

        $auditMock->expects($this->atLeastOnce())->method('setStatus');

        $this->consumer->process($messageMock);
    }

    public function testProcessRetryableErrorTriggersQueueRetry(): void
    {
        $messageMock = $this->createMock(StockUpdateQueueMessageInterface::class);
        $messageMock->method('getAuditId')->willReturn(10);
        $messageMock->method('getSupplierCode')->willReturn('SUPPLIER_001');

        $auditMock = $this->createMock(StockUpdateAuditInterface::class);
        $auditMock->method('getStatus')->willReturn(StockUpdateAuditInterface::STATUS_PENDING);
        $auditMock->method('getAttempts')->willReturn(0);

        $this->auditRepositoryMock->method('getById')->with(10)->willReturn($auditMock);

        $this->supplierRepositoryMock->method('getByCode')
            ->willThrowException(new LockWaitException('Lock wait timeout exceeded'));

        $this->connectionMock->expects($this->once())->method('beginTransaction');
        $this->connectionMock->expects($this->once())->method('rollBack');

        $this->expectException(LockWaitException::class);
        $this->consumer->process($messageMock);
    }
}
