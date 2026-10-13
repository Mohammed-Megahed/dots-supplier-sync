<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\ResourceModel;

use Company\SupplierSync\Model\Reservation as ReservationModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Reservation extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('company_supplier_reservation', 'reservation_id');
    }

    /**
     * Load reservation by order item ID.
     *
     * @param ReservationModel $reservation
     * @param int $orderItemId
     * @return $this
     */
    public function loadByOrderItemId(ReservationModel $reservation, int $orderItemId): self
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('order_item_id = :order_item_id');

        $data = $connection->fetchRow($select, ['order_item_id' => $orderItemId]);
        if ($data) {
            $reservation->setData($data);
        }

        return $this;
    }
}
