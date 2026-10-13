<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\Service;

use Company\SupplierSync\Api\Data\StockUpdateAuditInterface;
use Company\SupplierSync\Api\Data\StockUpdateInputInterface;
use Company\SupplierSync\Api\StockUpdateAuditRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;

class IdempotencyService
{
    private StockUpdateAuditRepositoryInterface $auditRepository;
    private SerializerInterface $serializer;

    public function __construct(
        StockUpdateAuditRepositoryInterface $auditRepository,
        SerializerInterface $serializer
    ) {
        $this->auditRepository = $auditRepository;
        $this->serializer = $serializer;
    }

    /**
     * Build normalized deterministic payload array.
     *
     * @param StockUpdateInputInterface $input
     * @return array
     */
    public function getNormalizedPayload(StockUpdateInputInterface $input): array
    {
        return [
            'currency' => strtoupper(trim((string)$input->getCurrency())),
            'quantity' => (float)$input->getQuantity(),
            'sku' => trim((string)$input->getSku()),
            'supplier_code' => trim((string)$input->getSupplierCode()),
            'unit_price' => (float)$input->getUnitPrice(),
            'website_code' => trim((string)$input->getWebsiteCode())
        ];
    }

    /**
     * Compute SHA-256 hash of normalized payload.
     *
     * @param array $normalizedPayload
     * @return string
     */
    public function computePayloadHash(array $normalizedPayload): string
    {
        ksort($normalizedPayload);
        return hash('sha256', (string)$this->serializer->serialize($normalizedPayload));
    }

    /**
     * Check if request was already accepted or if conflict exists.
     * Returns existing audit record if matched identically, null if new key.
     * Throws LocalizedException on conflicting payload.
     *
     * @param StockUpdateInputInterface $input
     * @param string $currentHash
     * @return StockUpdateAuditInterface|null
     * @throws LocalizedException
     */
    public function validateAndCheckExisting(StockUpdateInputInterface $input, string $currentHash): ?StockUpdateAuditInterface
    {
        $idempotencyKey = trim($input->getIdempotencyKey());
        if (empty($idempotencyKey)) {
            throw new LocalizedException(__('Idempotency key is required.'));
        }

        try {
            $existing = $this->auditRepository->getByIdempotencyKey($idempotencyKey);
            if ($existing->getAuditId()) {
                if ($existing->getPayloadHash() !== $currentHash) {
                    throw new LocalizedException(__(
                        'Conflicting idempotency key: The idempotency key "%1" was already submitted with different parameters.',
                        $idempotencyKey
                    ));
                }
                return $existing;
            }
        } catch (NoSuchEntityException $e) {
            // No existing record found, safe to proceed
        }

        return null;
    }
}
