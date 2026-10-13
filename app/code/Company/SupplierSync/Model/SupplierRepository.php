<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model;

use Company\SupplierSync\Api\Data\SupplierInterface;
use Company\SupplierSync\Api\Data\SupplierSearchResultsInterface;
use Company\SupplierSync\Api\Data\SupplierSearchResultsInterfaceFactory;
use Company\SupplierSync\Api\SupplierRepositoryInterface;
use Company\SupplierSync\Model\ResourceModel\Supplier as SupplierResource;
use Company\SupplierSync\Model\ResourceModel\Supplier\CollectionFactory as SupplierCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class SupplierRepository implements SupplierRepositoryInterface
{
    private SupplierResource $resource;
    private SupplierFactory $supplierFactory;
    private SupplierCollectionFactory $collectionFactory;
    private SupplierSearchResultsInterfaceFactory $searchResultsFactory;
    private CollectionProcessorInterface $collectionProcessor;

    public function __construct(
        SupplierResource $resource,
        SupplierFactory $supplierFactory,
        SupplierCollectionFactory $collectionFactory,
        SupplierSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->supplierFactory = $supplierFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    public function save(SupplierInterface $supplier): SupplierInterface
    {
        try {
            $this->resource->save($supplier);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the supplier: %1', $exception->getMessage()),
                $exception
            );
        }
        return $supplier;
    }

    public function getById(int $supplierId): SupplierInterface
    {
        $supplier = $this->supplierFactory->create();
        $this->resource->load($supplier, $supplierId);
        if (!$supplier->getSupplierId()) {
            throw new NoSuchEntityException(__('Supplier with ID "%1" does not exist.', $supplierId));
        }
        return $supplier;
    }

    public function getByCode(string $supplierCode): SupplierInterface
    {
        $supplier = $this->supplierFactory->create();
        $this->resource->loadByCode($supplier, $supplierCode);
        if (!$supplier->getSupplierId()) {
            throw new NoSuchEntityException(__('Supplier with code "%1" does not exist.', $supplierCode));
        }
        return $supplier;
    }

    public function delete(SupplierInterface $supplier): bool
    {
        try {
            $this->resource->delete($supplier);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the supplier: %1', $exception->getMessage()),
                $exception
            );
        }
        return true;
    }

    public function deleteById(int $supplierId): bool
    {
        return $this->delete($this->getById($supplierId));
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SupplierSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
