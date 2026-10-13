<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model\Queue;

use Company\SupplierSync\Api\Data\StockUpdateQueueMessageInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Psr\Log\LoggerInterface;

class StockUpdatePublisher
{
    public const TOPIC_NAME = 'company.supplier.stock.update';

    private PublisherInterface $publisher;
    private LoggerInterface $logger;

    public function __construct(
        PublisherInterface $publisher,
        LoggerInterface $logger
    ) {
        $this->publisher = $publisher;
        $this->logger = $logger;
    }

    /**
     * Publish stock update message to queue.
     *
     * @param StockUpdateQueueMessageInterface $message
     * @return bool
     */
    public function publish(StockUpdateQueueMessageInterface $message): bool
    {
        try {
            $this->publisher->publish(self::TOPIC_NAME, $message);
            $this->logger->info(sprintf(
                '[SupplierSync] Published message to queue for Idempotency Key: %s, Audit ID: %d',
                $message->getIdempotencyKey(),
                $message->getAuditId()
            ));
            return true;
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[SupplierSync] Failed to publish message to queue for Audit ID %d: %s',
                $message->getAuditId(),
                $e->getMessage()
            ));
            return false;
        }
    }
}
