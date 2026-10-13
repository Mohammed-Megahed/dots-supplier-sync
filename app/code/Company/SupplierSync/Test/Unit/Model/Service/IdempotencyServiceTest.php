<?php
declare(strict_types=1);

namespace Company\SupplierSync\Test\Unit\Model\Service;

use Company\SupplierSync\Api\Data\StockUpdateAuditInterface;
use Company\SupplierSync\Api\Data\StockUpdateInputInterface;
use Company\SupplierSync\Api\StockUpdateAuditRepositoryInterface;
use Company\SupplierSync\Model\Service\IdempotencyService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IdempotencyServiceTest extends TestCase
{
    private StockUpdateAuditRepositoryInterface|MockObject $auditRepositoryMock;
    private SerializerInterface|MockObject $serializerMock;
    private IdempotencyService $service;

    protected function setUp(): void
    {
        $this->auditRepositoryMock = $this->createMock(StockUpdateAuditRepositoryInterface::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);

        $this->service = new IdempotencyService(
            $this->auditRepositoryMock,
            $this->serializerMock
        );
    }

    public function testGetNormalizedPayload(): void
    {
        $inputMock = $this->createMock(StockUpdateInputInterface::class);
        $inputMock->method('getCurrency')->willReturn(' usd ');
        $inputMock->method('getQuantity')->willReturn(15.5);
        $inputMock->method('getSku')->willReturn(' SKU-99 ');
        $inputMock->method('getSupplierCode')->willReturn(' SUP-1 ');
        $inputMock->method('getUnitPrice')->willReturn(12.3);
        $inputMock->method('getWebsiteCode')->willReturn(' base ');

        $normalized = $this->service->getNormalizedPayload($inputMock);

        $this->assertSame('USD', $normalized['currency']);
        $this->assertSame(15.5, $normalized['quantity']);
        $this->assertSame('SKU-99', $normalized['sku']);
        $this->assertSame('SUP-1', $normalized['supplier_code']);
        $this->assertSame(12.3, $normalized['unit_price']);
        $this->assertSame('base', $normalized['website_code']);
    }

    public function testValidateAndCheckExistingNewKey(): void
    {
        $inputMock = $this->createMock(StockUpdateInputInterface::class);
        $inputMock->method('getIdempotencyKey')->willReturn('brand-new-key');

        $this->auditRepositoryMock->method('getByIdempotencyKey')
            ->with('brand-new-key')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $result = $this->service->validateAndCheckExisting($inputMock, 'hash123');
        $this->assertNull($result);
    }

    public function testValidateAndCheckExistingDuplicateMatch(): void
    {
        $inputMock = $this->createMock(StockUpdateInputInterface::class);
        $inputMock->method('getIdempotencyKey')->willReturn('existing-key');

        $existingAuditMock = $this->createMock(StockUpdateAuditInterface::class);
        $existingAuditMock->method('getAuditId')->willReturn(42);
        $existingAuditMock->method('getPayloadHash')->willReturn('hash123');

        $this->auditRepositoryMock->method('getByIdempotencyKey')
            ->with('existing-key')
            ->willReturn($existingAuditMock);

        $result = $this->service->validateAndCheckExisting($inputMock, 'hash123');
        $this->assertSame($existingAuditMock, $result);
    }

    public function testValidateAndCheckExistingConflictingPayloadThrowsException(): void
    {
        $inputMock = $this->createMock(StockUpdateInputInterface::class);
        $inputMock->method('getIdempotencyKey')->willReturn('existing-key');

        $existingAuditMock = $this->createMock(StockUpdateAuditInterface::class);
        $existingAuditMock->method('getAuditId')->willReturn(42);
        $existingAuditMock->method('getPayloadHash')->willReturn('original-hash');

        $this->auditRepositoryMock->method('getByIdempotencyKey')
            ->with('existing-key')
            ->willReturn($existingAuditMock);

        $this->expectException(LocalizedException::class);
        $this->service->validateAndCheckExisting($inputMock, 'different-conflicting-hash');
    }
}
