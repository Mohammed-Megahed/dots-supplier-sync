<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\ResourceModel\SupplierStock;

use Company\SupplierSync\Model\ResourceModel\SupplierStock as StockResource;
use Company\SupplierSync\Model\SupplierStock as StockModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(StockModel::class, StockResource::class);
    }
}
