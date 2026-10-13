<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api;

use Company\SupplierSync\Api\Data\StockUpdateAuditInterface;
use Magento\Framework\Exception\NoSuchEntityException;

interface StockUpdateAuditRepositoryInterface
{
    /**
     * @param StockUpdateAuditInterface $audit
     * @return StockUpdateAuditInterface
     */
    public function save(StockUpdateAuditInterface $audit): StockUpdateAuditInterface;

    /**
     * @param int $auditId
     * @return StockUpdateAuditInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $auditId): StockUpdateAuditInterface;

    /**
     * @param string $idempotencyKey
     * @return StockUpdateAuditInterface
     * @throws NoSuchEntityException
     */
    public function getByIdempotencyKey(string $idempotencyKey): StockUpdateAuditInterface;
}
