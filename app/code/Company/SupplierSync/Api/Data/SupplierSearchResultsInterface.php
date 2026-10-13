<?php
declare(strict_types=1);

namespace Company\SupplierSync\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface SupplierSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Company\SupplierSync\Api\Data\SupplierInterface[]
     */
    public function getItems();

    /**
     * @param \Company\SupplierSync\Api\Data\SupplierInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
