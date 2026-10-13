<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\ResourceModel;

use Company\SupplierSync\Model\StockUpdateAudit as AuditModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class StockUpdateAudit extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('company_supplier_stock_audit', 'audit_id');
    }

    /**
     * Load record by idempotency key.
     *
     * @param AuditModel $audit
     * @param string $idempotencyKey
     * @return $this
     */
    public function loadByIdempotencyKey(AuditModel $audit, string $idempotencyKey): self
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('idempotency_key = :idempotency_key');

        $data = $connection->fetchRow($select, ['idempotency_key' => $idempotencyKey]);
        if ($data) {
            $audit->setData($data);
        }

        return $this;
    }
}
