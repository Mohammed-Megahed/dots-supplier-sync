<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api;

use Company\SupplierSync\Api\Data\SupplierStockInterface;
use Magento\Framework\Exception\NoSuchEntityException;

interface SupplierStockRepositoryInterface
{
    /**
     * @param SupplierStockInterface $stock
     * @return SupplierStockInterface
     */
    public function save(SupplierStockInterface $stock): SupplierStockInterface;

    /**
     * @param int $stockId
     * @return SupplierStockInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $stockId): SupplierStockInterface;

    /**
     * Find stock by supplier, SKU, and website ID.
     *
     * @param int $supplierId
     * @param string $sku
     * @param int $websiteId
     * @return SupplierStockInterface
     * @throws NoSuchEntityException
     */
    public function getBySupplierSkuAndWebsite(int $supplierId, string $sku, int $websiteId): SupplierStockInterface;

    /**
     * Find primary supplier stock record for an SKU in a specific website.
     *
     * @param string $sku
     * @param int $websiteId
     * @return SupplierStockInterface
     * @throws NoSuchEntityException
     */
    public function getDesignatedStockBySkuAndWebsite(string $sku, int $websiteId): SupplierStockInterface;
}
