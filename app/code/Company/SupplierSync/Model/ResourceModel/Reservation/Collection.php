<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\ResourceModel\Reservation;

use Company\SupplierSync\Model\Reservation as ReservationModel;
use Company\SupplierSync\Model\ResourceModel\Reservation as ReservationResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ReservationModel::class, ReservationResource::class);
    }
}
