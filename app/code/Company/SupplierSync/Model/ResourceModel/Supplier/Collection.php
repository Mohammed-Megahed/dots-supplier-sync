<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\ResourceModel\Supplier;

use Company\SupplierSync\Model\ResourceModel\Supplier as SupplierResource;
use Company\SupplierSync\Model\Supplier as SupplierModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(SupplierModel::class, SupplierResource::class);
    }
}
