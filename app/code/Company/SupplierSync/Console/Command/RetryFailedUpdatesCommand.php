<?php
declare(strict_types=1);

namespace Company\SupplierSync\Console\Command;

use Company\SupplierSync\Api\Data\StockUpdateAuditInterface;
use Company\SupplierSync\Api\StockUpdateAuditRepositoryInterface;
use Company\SupplierSync\Model\Queue\StockUpdateConsumer;
use Company\SupplierSync\Model\Queue\StockUpdateMessageFactory;
use Company\SupplierSync\Model\Queue\StockUpdatePublisher;
use Company\SupplierSync\Model\ResourceModel\StockUpdateAudit\CollectionFactory as AuditCollectionFactory;
use Magento\Framework\Console\Cli;
use Magento\Framework\Serialize\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RetryFailedUpdatesCommand extends Command
{
    private AuditCollectionFactory $auditCollectionFactory;
    private StockUpdateAuditRepositoryInterface $auditRepository;
    private StockUpdateMessageFactory $messageFactory;
    private StockUpdatePublisher $publisher;
    private StockUpdateConsumer $consumer;
    private SerializerInterface $serializer;

    public function __construct(
        AuditCollectionFactory $auditCollectionFactory,
        StockUpdateAuditRepositoryInterface $auditRepository,
        StockUpdateMessageFactory $messageFactory,
        StockUpdatePublisher $publisher,
        StockUpdateConsumer $consumer,
        SerializerInterface $serializer,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->auditCollectionFactory = $auditCollectionFactory;
        $this->auditRepository = $auditRepository;
        $this->messageFactory = $messageFactory;
        $this->publisher = $publisher;
        $this->consumer = $consumer;
        $this->serializer = $serializer;
    }

    protected function configure(): void
    {
        $this->setName('supplier:sync:retry-failed')
            ->setDescription('Inspect and recover failed or pending supplier stock updates')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum updates to process', '50')
            ->addOption('immediate', 'i', InputOption::VALUE_NONE, 'Process immediately in-process without re-queuing')
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Filter by status (PENDING, RETRYABLE_FAILED, FAILED)', 'RETRYABLE_FAILED,PENDING');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int)$input->getOption('limit');
        $immediate = (bool)$input->getOption('immediate');
        $statusStr = (string)$input->getOption('status');
        $statuses = array_map('trim', explode(',', $statusStr));

        $collection = $this->auditCollectionFactory->create();
        $collection->addFieldToFilter('status', ['in' => $statuses]);
        $collection->setPageSize($limit);
        $collection->setOrder('audit_id', 'ASC');

        $total = $collection->getSize();
        $output->writeln(sprintf('<info>Found %d update records matching statuses [%s]. Processing up to %d...</info>', $total, implode(', ', $statuses), $limit));

        $successCount = 0;
        $errorCount = 0;

        foreach ($collection as $audit) {
            $output->write(sprintf('Processing Audit ID #%d (Key: %s)... ', $audit->getAuditId(), $audit->getIdempotencyKey()));

            try {
                $payloadData = $this->serializer->unserialize($audit->getRequestPayload());
                $message = $this->messageFactory->create();
                $message->setAuditId((int)$audit->getAuditId());
                $message->setIdempotencyKey($audit->getIdempotencyKey());
                $message->setSupplierCode($audit->getSupplierCode());
                $message->setSku($audit->getSku());
                $message->setQuantity((float)($payloadData['quantity'] ?? 0));
                $message->setUnitPrice((float)($payloadData['unit_price'] ?? 0));
                $message->setCurrency((string)($payloadData['currency'] ?? 'USD'));
                $message->setWebsiteCode((string)($payloadData['website_code'] ?? 'base'));

                if ($immediate) {
                    $this->consumer->process($message);
                    $output->writeln('<info>Processed synchronously.</info>');
                } else {
                    $this->publisher->publish($message);
                    $output->writeln('<info>Re-published to message queue.</info>');
                }
                $successCount++;
            } catch (\Exception $e) {
                $output->writeln(sprintf('<error>Failed: %s</error>', $e->getMessage()));
                $errorCount++;
            }
        }

        $output->writeln(sprintf('<info>Completed. Succeeded: %d, Failed: %d</info>', $successCount, $errorCount));
        return Cli::RETURN_SUCCESS;
    }
}
