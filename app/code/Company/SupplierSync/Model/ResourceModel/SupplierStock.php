<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\ResourceModel;

use Company\SupplierSync\Model\SupplierStock as StockModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SupplierStock extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('company_supplier_stock', 'stock_id');
    }

    /**
     * Load record by supplier, SKU and website ID.
     *
     * @param StockModel $stock
     * @param int $supplierId
     * @param string $sku
     * @param int $websiteId
     * @return $this
     */
    public function loadBySupplierSkuWebsite(StockModel $stock, int $supplierId, string $sku, int $websiteId): self
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('supplier_id = :supplier_id')
            ->where('sku = :sku')
            ->where('website_id = :website_id');

        $data = $connection->fetchRow($select, [
            'supplier_id' => $supplierId,
            'sku' => $sku,
            'website_id' => $websiteId
        ]);

        if ($data) {
            $stock->setData($data);
        }

        return $this;
    }

    /**
     * Load designated stock for SKU & website (joining active supplier).
     *
     * @param StockModel $stock
     * @param string $sku
     * @param int $websiteId
     * @return $this
     */
    public function loadDesignated(StockModel $stock, string $sku, int $websiteId): self
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['s' => $this->getMainTable()])
            ->joinInner(
                ['sup' => $this->getTable('company_supplier')],
                's.supplier_id = sup.supplier_id',
                []
            )
            ->where('s.sku = :sku')
            ->where('s.website_id = :website_id')
            ->where('sup.is_enabled = 1')
            ->limit(1);

        $data = $connection->fetchRow($select, [
            'sku' => $sku,
            'website_id' => $websiteId
        ]);

        if ($data) {
            $stock->setData($data);
        }

        return $this;
    }

    /**
     * Pessimistic row locking: SELECT ... FOR UPDATE.
     *
     * @param int $stockId
     * @return array|false
     */
    public function lockRowForUpdate(int $stockId)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('stock_id = :stock_id')
            ->forUpdate(true);

        return $connection->fetchRow($select, ['stock_id' => $stockId]);
    }

    /**
     * Atomic reservation update directly in the database.
     * Guarantees race-condition prevention across concurrent processes.
     *
     * Returns true if rows were updated (meaning available stock was sufficient), false otherwise.
     *
     * @param int $stockId
     * @param float $quantity
     * @return bool
     */
    public function atomicReserveById(int $stockId, float $quantity): bool
    {
        $connection = $this->getConnection();
        $tableName = $this->getMainTable();

        // Atomically increment reserved_quantity only if quantity - reserved_quantity >= required_qty
        $sql = sprintf(
            'UPDATE %s SET reserved_quantity = reserved_quantity + :qty, version = version + 1 ' .
            'WHERE stock_id = :stock_id AND (quantity - reserved_quantity) >= :qty',
            $tableName
        );

        $result = $connection->query($sql, [
            'qty' => $quantity,
            'stock_id' => $stockId
        ]);

        return $result->rowCount() > 0;
    }

    /**
     * Atomic release of reserved quantity.
     *
     * @param int $stockId
     * @param float $quantity
     * @return bool
     */
    public function atomicReleaseById(int $stockId, float $quantity): bool
    {
        $connection = $this->getConnection();
        $tableName = $this->getMainTable();

        $sql = sprintf(
            'UPDATE %s SET reserved_quantity = GREATEST(0, reserved_quantity - :qty), version = version + 1 ' .
            'WHERE stock_id = :stock_id',
            $tableName
        );

        $result = $connection->query($sql, [
            'qty' => $quantity,
            'stock_id' => $stockId
        ]);

        return $result->rowCount() > 0;
    }
}
