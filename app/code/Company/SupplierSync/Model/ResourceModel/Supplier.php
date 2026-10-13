<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Supplier extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('company_supplier', 'supplier_id');
    }

    /**
     * Load supplier by code.
     *
     * @param \Company\SupplierSync\Model\Supplier $supplier
     * @param string $supplierCode
     * @return $this
     */
    public function loadByCode(\Company\SupplierSync\Model\Supplier $supplier, string $supplierCode): self
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('supplier_code = :supplier_code');
        $data = $connection->fetchRow($select, ['supplier_code' => $supplierCode]);

        if ($data) {
            $supplier->setData($data);
        }

        return $this;
    }
}
