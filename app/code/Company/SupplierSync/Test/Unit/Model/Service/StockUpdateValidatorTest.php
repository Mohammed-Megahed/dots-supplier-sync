<?php
declare(strict_types=1);

namespace Company\SupplierSync\Test\Unit\Model\Service;

use Company\SupplierSync\Api\Data\StockUpdateInputInterface;
use Company\SupplierSync\Api\Data\SupplierInterface;
use Company\SupplierSync\Api\SupplierRepositoryInterface;
use Company\SupplierSync\Model\Service\StockUpdateValidator;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StockUpdateValidatorTest extends TestCase
{
    private SupplierRepositoryInterface|MockObject $supplierRepositoryMock;
    private ProductRepositoryInterface|MockObject $productRepositoryMock;
    private WebsiteRepositoryInterface|MockObject $websiteRepositoryMock;
    private StockUpdateValidator $validator;

    protected function setUp(): void
    {
        $this->supplierRepositoryMock = $this->createMock(SupplierRepositoryInterface::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->websiteRepositoryMock = $this->createMock(WebsiteRepositoryInterface::class);

        $this->validator = new StockUpdateValidator(
            $this->supplierRepositoryMock,
            $this->productRepositoryMock,
            $this->websiteRepositoryMock
        );
    }

    public function testValidateSuccessful(): void
    {
        $inputMock = $this->createMock(StockUpdateInputInterface::class);
        $inputMock->method('getSupplierCode')->willReturn('SUPPLIER_001');
        $inputMock->method('getIdempotencyKey')->willReturn('key-123');
        $inputMock->method('getSku')->willReturn('SKU-1001');
        $inputMock->method('getQuantity')->willReturn(25.0);
        $inputMock->method('getUnitPrice')->willReturn(49.99);
        $inputMock->method('getCurrency')->willReturn('USD');
        $inputMock->method('getWebsiteCode')->willReturn('base');

        $supplierMock = $this->createMock(SupplierInterface::class);
        $supplierMock->method('getIsEnabled')->willReturn(true);
        $this->supplierRepositoryMock->method('getByCode')->with('SUPPLIER_001')->willReturn($supplierMock);

        $productMock = $this->createMock(ProductInterface::class);
        $this->productRepositoryMock->method('get')->with('SKU-1001')->willReturn($productMock);

        $websiteMock = $this->createMock(WebsiteInterface::class);
        $this->websiteRepositoryMock->method('get')->with('base')->willReturn($websiteMock);

        // Expect no exception
        $this->validator->validate($inputMock);
        $this->assertTrue(true);
    }

    public function testValidateDisabledSupplierThrowsException(): void
    {
        $inputMock = $this->createMock(StockUpdateInputInterface::class);
        $inputMock->method('getSupplierCode')->willReturn('SUPPLIER_DISABLED');
        $inputMock->method('getIdempotencyKey')->willReturn('key-123');
        $inputMock->method('getSku')->willReturn('SKU-1001');
        $inputMock->method('getQuantity')->willReturn(10.0);
        $inputMock->method('getUnitPrice')->willReturn(10.0);
        $inputMock->method('getCurrency')->willReturn('USD');
        $inputMock->method('getWebsiteCode')->willReturn('base');

        $supplierMock = $this->createMock(SupplierInterface::class);
        $supplierMock->method('getIsEnabled')->willReturn(false);
        $this->supplierRepositoryMock->method('getByCode')->with('SUPPLIER_DISABLED')->willReturn($supplierMock);

        $this->expectException(InputException::class);
        $this->validator->validate($inputMock);
    }

    public function testValidateUnknownSkuThrowsException(): void
    {
        $inputMock = $this->createMock(StockUpdateInputInterface::class);
        $inputMock->method('getSupplierCode')->willReturn('SUPPLIER_001');
        $inputMock->method('getIdempotencyKey')->willReturn('key-123');
        $inputMock->method('getSku')->willReturn('UNKNOWN_SKU');
        $inputMock->method('getQuantity')->willReturn(10.0);
        $inputMock->method('getUnitPrice')->willReturn(10.0);
        $inputMock->method('getCurrency')->willReturn('USD');
        $inputMock->method('getWebsiteCode')->willReturn('base');

        $supplierMock = $this->createMock(SupplierInterface::class);
        $supplierMock->method('getIsEnabled')->willReturn(true);
        $this->supplierRepositoryMock->method('getByCode')->willReturn($supplierMock);

        $this->productRepositoryMock->method('get')->willThrowException(new NoSuchEntityException(__('No SKU')));

        $this->expectException(InputException::class);
        $this->validator->validate($inputMock);
    }

    public function testValidateNegativeQuantityThrowsException(): void
    {
        $inputMock = $this->createMock(StockUpdateInputInterface::class);
        $inputMock->method('getSupplierCode')->willReturn('SUPPLIER_001');
        $inputMock->method('getIdempotencyKey')->willReturn('key-123');
        $inputMock->method('getSku')->willReturn('SKU-1001');
        $inputMock->method('getQuantity')->willReturn(-5.0);
        $inputMock->method('getUnitPrice')->willReturn(10.0);
        $inputMock->method('getCurrency')->willReturn('USD');
        $inputMock->method('getWebsiteCode')->willReturn('base');

        $supplierMock = $this->createMock(SupplierInterface::class);
        $supplierMock->method('getIsEnabled')->willReturn(true);
        $this->supplierRepositoryMock->method('getByCode')->willReturn($supplierMock);

        $this->expectException(InputException::class);
        $this->validator->validate($inputMock);
    }
}
