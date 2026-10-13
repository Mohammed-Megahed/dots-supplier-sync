<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\Service;

use Company\SupplierSync\Api\Data\StockUpdateInputInterface;
use Company\SupplierSync\Api\SupplierRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\WebsiteRepositoryInterface;

class StockUpdateValidator
{
    private SupplierRepositoryInterface $supplierRepository;
    private ProductRepositoryInterface $productRepository;
    private WebsiteRepositoryInterface $websiteRepository;

    public function __construct(
        SupplierRepositoryInterface $supplierRepository,
        ProductRepositoryInterface $productRepository,
        WebsiteRepositoryInterface $websiteRepository
    ) {
        $this->supplierRepository = $supplierRepository;
        $this->productRepository = $productRepository;
        $this->websiteRepository = $websiteRepository;
    }

    /**
     * Validate input payload.
     *
     * @param StockUpdateInputInterface $input
     * @throws InputException
     */
    public function validate(StockUpdateInputInterface $input): void
    {
        $errors = [];

        // 1. Supplier validation
        $supplierCode = trim((string)$input->getSupplierCode());
        if (empty($supplierCode)) {
            $errors[] = __('Supplier code is required.');
        } else {
            try {
                $supplier = $this->supplierRepository->getByCode($supplierCode);
                if (!$supplier->getIsEnabled()) {
                    $errors[] = __('Supplier "%1" is disabled and cannot accept stock updates.', $supplierCode);
                }
            } catch (NoSuchEntityException $e) {
                $errors[] = __('Supplier "%1" does not exist.', $supplierCode);
            }
        }

        // 2. Idempotency Key validation
        $idempotencyKey = trim((string)$input->getIdempotencyKey());
        if (empty($idempotencyKey)) {
            $errors[] = __('Idempotency key is required.');
        } elseif (strlen($idempotencyKey) > 128) {
            $errors[] = __('Idempotency key exceeds maximum length of 128 characters.');
        }

        // 3. Product SKU validation
        $sku = trim((string)$input->getSku());
        if (empty($sku)) {
            $errors[] = __('SKU is required.');
        } else {
            try {
                $this->productRepository->get($sku);
            } catch (NoSuchEntityException $e) {
                $errors[] = __('Product with SKU "%1" does not exist in catalog.', $sku);
            }
        }

        // 4. Quantity validation
        $quantity = $input->getQuantity();
        if ($quantity < 0) {
            $errors[] = __('Quantity must be greater than or equal to 0.');
        }

        // 5. Unit Price validation
        $unitPrice = $input->getUnitPrice();
        if ($unitPrice < 0) {
            $errors[] = __('Unit price must be greater than or equal to 0.');
        }

        // 6. Currency validation
        $currency = trim((string)$input->getCurrency());
        if (empty($currency) || strlen($currency) !== 3) {
            $errors[] = __('Currency code must be a valid 3-letter ISO code.');
        }

        // 7. Website code validation
        $websiteCode = trim((string)$input->getWebsiteCode());
        if (empty($websiteCode)) {
            $errors[] = __('Website code is required.');
        } else {
            try {
                $this->websiteRepository->get($websiteCode);
            } catch (NoSuchEntityException $e) {
                $errors[] = __('Website "%1" does not exist.', $websiteCode);
            }
        }

        if (!empty($errors)) {
            $inputException = new InputException(__('Validation failed for stock update request.'));
            foreach ($errors as $error) {
                $inputException->addError($error);
            }
            throw $inputException;
        }
    }
}
