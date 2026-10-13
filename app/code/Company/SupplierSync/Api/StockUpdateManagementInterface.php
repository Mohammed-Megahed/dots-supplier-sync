<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api;

use Company\SupplierSync\Api\Data\StockUpdateInputInterface;
use Company\SupplierSync\Api\Data\StockUpdateResponseInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;

interface StockUpdateManagementInterface
{
    /**
     * Submit asynchronous stock update with idempotency protection.
     *
     * @param StockUpdateInputInterface $update
     * @return StockUpdateResponseInterface
     * @throws InputException
     * @throws LocalizedException
     */
    public function postUpdate(StockUpdateInputInterface $update): StockUpdateResponseInterface;
}
