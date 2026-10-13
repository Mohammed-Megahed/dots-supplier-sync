<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model;

use Company\SupplierSync\Api\Data\StockUpdateAuditInterface;
use Company\SupplierSync\Api\StockUpdateAuditRepositoryInterface;
use Company\SupplierSync\Model\ResourceModel\StockUpdateAudit as AuditResource;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class StockUpdateAuditRepository implements StockUpdateAuditRepositoryInterface
{
    private AuditResource $resource;
    private StockUpdateAuditFactory $auditFactory;

    public function __construct(
        AuditResource $resource,
        StockUpdateAuditFactory $auditFactory
    ) {
        $this->resource = $resource;
        $this->auditFactory = $auditFactory;
    }

    public function save(StockUpdateAuditInterface $audit): StockUpdateAuditInterface
    {
        try {
            $this->resource->save($audit);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save stock update audit: %1', $exception->getMessage()),
                $exception
            );
        }
        return $audit;
    }

    public function getById(int $auditId): StockUpdateAuditInterface
    {
        $audit = $this->auditFactory->create();
        $this->resource->load($audit, $auditId);
        if (!$audit->getAuditId()) {
            throw new NoSuchEntityException(__('Stock update audit record "%1" does not exist.', $auditId));
        }
        return $audit;
    }

    public function getByIdempotencyKey(string $idempotencyKey): StockUpdateAuditInterface
    {
        $audit = $this->auditFactory->create();
        $this->resource->loadByIdempotencyKey($audit, $idempotencyKey);
        if (!$audit->getAuditId()) {
            throw new NoSuchEntityException(
                __('Stock update audit with idempotency key "%1" does not exist.', $idempotencyKey)
            );
        }
        return $audit;
    }
}
