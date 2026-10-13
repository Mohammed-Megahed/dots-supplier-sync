<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model;

use Company\SupplierSync\Api\Data\SupplierStockInterface;
use Company\SupplierSync\Api\SupplierStockRepositoryInterface;
use Company\SupplierSync\Model\ResourceModel\SupplierStock as StockResource;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class SupplierStockRepository implements SupplierStockRepositoryInterface
{
    private StockResource $resource;
    private SupplierStockFactory $stockFactory;

    public function __construct(
        StockResource $resource,
        SupplierStockFactory $stockFactory
    ) {
        $this->resource = $resource;
        $this->stockFactory = $stockFactory;
    }

    public function save(SupplierStockInterface $stock): SupplierStockInterface
    {
        try {
            $this->resource->save($stock);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save supplier stock: %1', $exception->getMessage()),
                $exception
            );
        }
        return $stock;
    }

    public function getById(int $stockId): SupplierStockInterface
    {
        $stock = $this->stockFactory->create();
        $this->resource->load($stock, $stockId);
        if (!$stock->getStockId()) {
            throw new NoSuchEntityException(__('Supplier stock record with ID "%1" does not exist.', $stockId));
        }
        return $stock;
    }

    public function getBySupplierSkuAndWebsite(int $supplierId, string $sku, int $websiteId): SupplierStockInterface
    {
        $stock = $this->stockFactory->create();
        $this->resource->loadBySupplierSkuWebsite($stock, $supplierId, $sku, $websiteId);
        if (!$stock->getStockId()) {
            throw new NoSuchEntityException(
                __('Stock for supplier "%1", SKU "%2", and website "%3" does not exist.', $supplierId, $sku, $websiteId)
            );
        }
        return $stock;
    }

    public function getDesignatedStockBySkuAndWebsite(string $sku, int $websiteId): SupplierStockInterface
    {
        $stock = $this->stockFactory->create();
        $this->resource->loadDesignated($stock, $sku, $websiteId);
        if (!$stock->getStockId()) {
            throw new NoSuchEntityException(
                __('No designated active supplier found for SKU "%1" on website ID "%2".', $sku, $websiteId)
            );
        }
        return $stock;
    }
}
