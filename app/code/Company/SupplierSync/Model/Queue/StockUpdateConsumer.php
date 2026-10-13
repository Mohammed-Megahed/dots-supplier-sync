<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\Queue;

use Company\SupplierSync\Api\Data\StockUpdateAuditInterface;
use Company\SupplierSync\Api\Data\StockUpdateQueueMessageInterface;
use Company\SupplierSync\Api\StockUpdateAuditRepositoryInterface;
use Company\SupplierSync\Api\SupplierRepositoryInterface;
use Company\SupplierSync\Api\SupplierStockRepositoryInterface;
use Company\SupplierSync\Model\SupplierStock;
use Company\SupplierSync\Model\SupplierStockFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\LockWaitException;
use Magento\Framework\DB\Adapter\DeadlockException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Psr\Log\LoggerInterface;

class StockUpdateConsumer
{
    private StockUpdateAuditRepositoryInterface $auditRepository;
    private SupplierRepositoryInterface $supplierRepository;
    private SupplierStockRepositoryInterface $supplierStockRepository;
    private SupplierStockFactory $supplierStockFactory;
    private WebsiteRepositoryInterface $websiteRepository;
    private ResourceConnection $resourceConnection;
    private LoggerInterface $logger;

    public function __construct(
        StockUpdateAuditRepositoryInterface $auditRepository,
        SupplierRepositoryInterface $supplierRepository,
        SupplierStockRepositoryInterface $supplierStockRepository,
        SupplierStockFactory $supplierStockFactory,
        WebsiteRepositoryInterface $websiteRepository,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->auditRepository = $auditRepository;
        $this->supplierRepository = $supplierRepository;
        $this->supplierStockRepository = $supplierStockRepository;
        $this->supplierStockFactory = $supplierStockFactory;
        $this->websiteRepository = $websiteRepository;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Process asynchronous stock update message.
     *
     * @param StockUpdateQueueMessageInterface $message
     * @return void
     * @throws \Exception
     */
    public function process(StockUpdateQueueMessageInterface $message): void
    {
        $auditId = (int)$message->getAuditId();

        try {
            $audit = $this->auditRepository->getById($auditId);
        } catch (NoSuchEntityException $e) {
            $this->logger->error(sprintf(
                '[SupplierSync][Consumer] Audit record ID %d not found for message.',
                $auditId
            ));
            return;
        }

        // Duplicate delivery guard: If already completed, gracefully skip
        if ($audit->getStatus() === StockUpdateAuditInterface::STATUS_COMPLETED) {
            $this->logger->info(sprintf(
                '[SupplierSync][Consumer] Audit ID %d already completed. Ignoring duplicate queue message.',
                $auditId
            ));
            return;
        }

        // Mark processing & increment attempts
        $audit->setStatus(StockUpdateAuditInterface::STATUS_PROCESSING);
        $audit->setAttempts($audit->getAttempts() + 1);
        $this->auditRepository->save($audit);

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            // Validate supplier is active
            $supplier = $this->supplierRepository->getByCode($message->getSupplierCode());
            if (!$supplier->getIsEnabled()) {
                throw new \DomainException(sprintf(
                    'Supplier "%s" is disabled.',
                    $message->getSupplierCode()
                ));
            }

            // Retrieve website ID
            $website = $this->websiteRepository->get($message->getWebsiteCode());
            $websiteId = (int)$website->getId();
            $supplierId = (int)$supplier->getSupplierId();
            $sku = $message->getSku();

            // Find or create stock record
            try {
                /** @var SupplierStock $stock */
                $stock = $this->supplierStockRepository->getBySupplierSkuAndWebsite($supplierId, $sku, $websiteId);
            } catch (NoSuchEntityException $e) {
                /** @var SupplierStock $stock */
                $stock = $this->supplierStockFactory->create();
                $stock->setSupplierId($supplierId);
                $stock->setSku($sku);
                $stock->setWebsiteId($websiteId);
                $stock->setReservedQuantity(0.0);
            }

            // Update quantities and price
            $stock->setQuantity((float)$message->getQuantity());
            $stock->setUnitPrice((float)$message->getUnitPrice());
            $stock->setCurrency($message->getCurrency());
            $stock->setVersion($stock->getVersion() + 1);

            $this->supplierStockRepository->save($stock);

            // Mark audit completed
            $audit->setStatus(StockUpdateAuditInterface::STATUS_COMPLETED);
            $audit->setErrorMessage(null);
            $this->auditRepository->save($audit);

            $connection->commit();

            $this->logger->info(sprintf(
                '[SupplierSync][Consumer] Successfully processed stock update for SKU "%s", Supplier %s (Qty: %.2f, Price: %.2f)',
                $sku,
                $message->getSupplierCode(),
                $message->getQuantity(),
                $message->getUnitPrice()
            ));
        } catch (LockWaitException | DeadlockException $retryableException) {
            $connection->rollBack();
            $audit->setStatus(StockUpdateAuditInterface::STATUS_RETRYABLE_FAILED);
            $audit->setErrorMessage('Database lock conflict encountered. Ready for retry.');
            $this->auditRepository->save($audit);

            $this->logger->warning(sprintf(
                '[SupplierSync][Consumer] Transient DB lock error on Audit %d. Re-queuing: %s',
                $auditId,
                $retryableException->getMessage()
            ));
            // Rethrow so queue mechanism retries
            throw $retryableException;
        } catch (\Exception $permanentException) {
            $connection->rollBack();
            $audit->setStatus(StockUpdateAuditInterface::STATUS_FAILED);
            $audit->setErrorMessage($permanentException->getMessage());
            $this->auditRepository->save($audit);

            $this->logger->error(sprintf(
                '[SupplierSync][Consumer] Permanent processing failure on Audit %d: %s',
                $auditId,
                $permanentException->getMessage()
            ));
            // Do not rethrow permanent exceptions to avoid poison-pill infinite loops
        }
    }
}
