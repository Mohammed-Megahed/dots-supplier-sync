<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\ResourceModel\StockUpdateAudit;

use Company\SupplierSync\Model\ResourceModel\StockUpdateAudit as AuditResource;
use Company\SupplierSync\Model\StockUpdateAudit as AuditModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(AuditModel::class, AuditResource::class);
    }
}
