<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api;

use Company\SupplierSync\Api\Data\SupplierInterface;
use Company\SupplierSync\Api\Data\SupplierSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

interface SupplierRepositoryInterface
{
    /**
     * Save supplier.
     *
     * @param SupplierInterface $supplier
     * @return SupplierInterface
     * @throws LocalizedException
     */
    public function save(SupplierInterface $supplier): SupplierInterface;

    /**
     * Get supplier by ID.
     *
     * @param int $supplierId
     * @return SupplierInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $supplierId): SupplierInterface;

    /**
     * Get supplier by unique code.
     *
     * @param string $supplierCode
     * @return SupplierInterface
     * @throws NoSuchEntityException
     */
    public function getByCode(string $supplierCode): SupplierInterface;

    /**
     * Delete supplier.
     *
     * @param SupplierInterface $supplier
     * @return bool
     * @throws LocalizedException
     */
    public function delete(SupplierInterface $supplier): bool;

    /**
     * Delete supplier by ID.
     *
     * @param int $supplierId
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteById(int $supplierId): bool;

    /**
     * Get list of suppliers.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SupplierSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SupplierSearchResultsInterface;
}
